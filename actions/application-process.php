<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/join-schema.php';
require_once __DIR__ . '/../includes/user-schema.php';
requireAdmin();
verifyCsrf();

$pdo          = getDb();
$action       = $_POST['action'] ?? '';
$appId        = (int)($_POST['app_id'] ?? 0);
$adminId      = getCurrentUserId();
$redirectBack = BASE_URL . '/admin/members.php?tab=applications';

ensureJoinSchema($pdo);
ensureUserSchema($pdo);

if (!$appId || !in_array($action, ['approve', 'reject'], true)) {
    flash('error', 'Érvénytelen kérés.');
    header('Location: ' . $redirectBack); exit;
}

$appStmt = $pdo->prepare("SELECT * FROM member_applications WHERE id = ? LIMIT 1");
$appStmt->execute([$appId]);
$app = $appStmt->fetch();

if (!$app || $app['status'] !== 'pending') {
    flash('error', 'A jelentkezés nem található, vagy már feldolgozásra került.');
    header('Location: ' . $redirectBack); exit;
}

// ── REJECT ──────────────────────────────────────────────────────────
if ($action === 'reject') {
    $pdo->prepare("UPDATE member_applications SET status='rejected', reviewed_at=NOW(), reviewed_by=? WHERE id=?")
        ->execute([$adminId, $appId]);

    // Cancel any linked tour applications
    require_once __DIR__ . '/../includes/future-tours-schema.php';
    ensureFutureToursSchema($pdo);
    $pdo->prepare("UPDATE future_tour_applications SET status='cancelled' WHERE member_application_id = ? AND status != 'cancelled'")
        ->execute([$appId]);

    flash('success', $app['lastname'] . ' ' . $app['firstname'] . ' jelentkezése elutasítva.');
    header('Location: ' . $redirectBack); exit;
}

// ── APPROVE ─────────────────────────────────────────────────────────
$username    = trim($_POST['username']          ?? '');
$memberSince = $_POST['member_since']           ?? date('Y-m-d');
$sendWelcome = ($_POST['send_welcome_email'] ?? '') === '1';

if (!$username) {
    flash('error', 'A felhasználónév megadása kötelező.');
    header('Location: ' . $redirectBack); exit;
}

$password = generateMemberPassword();

$check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
$check->execute([$username, $app['email']]);
if ($check->fetch()) {
    flash('error', 'Ez a felhasználónév vagy e-mail-cím már foglalt egy meglévő tagnál.');
    header('Location: ' . $redirectBack); exit;
}

$pdo->prepare("INSERT INTO users
    (username, email, password, role, firstname, lastname,
     phone, dateofbirth, zipcode, city, address,
     member_since, active, consent_email_visibility, consent_photo)
    VALUES (?, ?, ?, 'user', ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)")
    ->execute([
        $username,
        $app['email'],
        password_hash($password, PASSWORD_DEFAULT),
        $app['firstname'],
        $app['lastname'],
        $app['phone']       ?: null,
        $app['dateofbirth'] ?: null,
        $app['zipcode']     ?: null,
        $app['city']        ?: null,
        $app['address']     ?: null,
        $memberSince ?: date('Y-m-d'),
        $app['consent_email'],
        $app['consent_photo'],
    ]);

$newUserId = (int)$pdo->lastInsertId();

$pdo->prepare("UPDATE member_applications SET status='approved', reviewed_at=NOW(), reviewed_by=? WHERE id=?")
    ->execute([$adminId, $appId]);

// A kapcsolt túrajelentkezések a tag nevére kerülnek. Státuszuk marad (a régi, 'pending' sor
// férőhely szerint kap státuszt). Túra-levél itt nem megy: azt a „Jelentkezés elfogadása” küldi.
require_once __DIR__ . '/../includes/future-tours-schema.php';
ensureFutureToursSchema($pdo);

$linkedStmt = $pdo->prepare("
    SELECT id, future_tour_id, status FROM future_tour_applications
    WHERE member_application_id = ? AND status != 'cancelled'
");
$linkedStmt->execute([$appId]);
$linkStmt = $pdo->prepare("
    UPDATE future_tour_applications
    SET user_id = ?, guest_name = NULL, guest_email = NULL, guest_phone = NULL, status = ?
    WHERE id = ?
");
foreach ($linkedStmt->fetchAll() as $la) {
    $status = $la['status'] === 'pending' ? getNewApplicationStatus($pdo, (int)$la['future_tour_id']) : $la['status'];
    $linkStmt->execute([$newUserId, $status, $la['id']]);
}

recalcUserStats($pdo);

logAudit($pdo, 'create', 'member', $newUserId, $app['lastname'] . ' ' . $app['firstname'], [
    ['k' => 'Felhasználónév',   'v' => $username],
    ['k' => 'E-mail',           'v' => $app['email']],
    ['k' => 'Szerepkör',        'v' => 'Tag'],
    ['k' => 'Forrás',           'v' => 'Tagfelvételi kérelem #' . $appId],
]);

$baseMsg = $app['lastname'] . ' ' . $app['firstname'] . ' tagfelvétele jóváhagyva, tagfiók létrehozva.';

if ($sendWelcome) {
    try {
        require_once __DIR__ . '/../includes/app-settings-schema.php';
        require_once __DIR__ . '/../includes/mailer.php';
        require_once __DIR__ . '/../includes/welcome-email.php';
        require_once __DIR__ . '/../includes/email-log-schema.php';

        ensureAppSettingsSchema($pdo);
        ensureEmailLogSchema($pdo);
        $smtp = getSmtpConfig($pdo);

        if ($smtp['host'] !== '') {
            $proto    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $loginUrl = $proto . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/login.php';
            $html     = buildWelcomeEmailHtml($app['firstname'], $username, $password, $loginUrl, APP_NAME);
            // A naplóba kimaszkolt jelszóval kerül — plaintext jelszó sose kerüljön az email_log-ba
            $logHtml  = buildWelcomeEmailHtml($app['firstname'], $username, '••••••••', $loginUrl, APP_NAME);
            $subject  = 'Üdvözlünk a ' . APP_NAME . '-ban!';
            $mailer   = new SmtpMailer($smtp);
            $mailer->send($app['email'], $app['lastname'] . ' ' . $app['firstname'], $subject, $html);
            logEmailEntry($pdo, $newUserId, $app['email'], $app['lastname'] . ' ' . $app['firstname'], $subject, $logHtml, 'welcome', 'sent');
            flash('success', $baseMsg . ' Az üdvözlő e-mail elküldve.');
        } else {
            flash('success', $baseMsg . ' (SMTP nincs beállítva, e-mail nem lett elküldve.)');
        }
    } catch (Throwable $ex) {
        error_log('Welcome email error (application approve): ' . $ex->getMessage());
        flash('success', $baseMsg);
        flash('error', 'Az üdvözlő e-mail küldése sikertelen: ' . $ex->getMessage());
    }
} else {
    flash('success', $baseMsg);
}

header('Location: ' . $redirectBack);
exit;
