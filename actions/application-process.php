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

    // Cancel any linked pending tour applications
    require_once __DIR__ . '/../includes/future-tours-schema.php';
    ensureFutureToursSchema($pdo);
    $pdo->prepare("UPDATE future_tour_applications SET status='cancelled' WHERE member_application_id = ? AND status = 'pending'")
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

// Promote any linked pending tour applications to confirmed/waitlist
require_once __DIR__ . '/../includes/future-tours-schema.php';
ensureFutureToursSchema($pdo);

$linkedStmt = $pdo->prepare("
    SELECT fta.id, fta.future_tour_id, ft.name AS tour_name, ft.max_attendees, ft.start_date, ft.participation_fee
    FROM future_tour_applications fta
    JOIN future_tours ft ON ft.id = fta.future_tour_id
    WHERE fta.member_application_id = ? AND fta.status = 'pending'
");
$linkedStmt->execute([$appId]);
$linkedTourApps = $linkedStmt->fetchAll();

foreach ($linkedTourApps as $la) {
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM future_tour_applications WHERE future_tour_id = ? AND status = 'confirmed'");
    $cntStmt->execute([$la['future_tour_id']]);
    $confirmedCount = (int)$cntStmt->fetchColumn();
    $tourAppStatus  = $confirmedCount >= (int)$la['max_attendees'] ? 'waitlist' : 'confirmed';

    $pdo->prepare("
        UPDATE future_tour_applications
        SET user_id = ?, guest_name = NULL, guest_email = NULL, guest_phone = NULL,
            status = ?, applied_at = NOW()
        WHERE id = ?
    ")->execute([$newUserId, $tourAppStatus, $la['id']]);
}

recalcUserStats($pdo);

// Send tour confirmation email(s) for promoted applications
if (!empty($linkedTourApps)) {
    try {
        require_once __DIR__ . '/../includes/app-settings-schema.php';
        require_once __DIR__ . '/../includes/mailer.php';
        require_once __DIR__ . '/../includes/email-log-schema.php';
        ensureAppSettingsSchema($pdo);
        ensureEmailLogSchema($pdo);
        $smtp = getSmtpConfig($pdo);

        if ($smtp['host'] !== '') {
            $proto      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $absBaseUrl = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL;
            $fullName   = $app['lastname'] . ' ' . $app['firstname'];
            $mailer     = new SmtpMailer($smtp);

            foreach ($linkedTourApps as $la) {
                // Re-fetch the status we just set
                $promotedStatus = $pdo->prepare("SELECT status FROM future_tour_applications WHERE id = ? LIMIT 1");
                $promotedStatus->execute([$la['id']]);
                $promotedStatus = $promotedStatus->fetchColumn();

                $tourDate = $la['start_date'] ? formatDate($la['start_date']) : '—';
                $tourUrl  = $absBaseUrl . '/user/future-tour-detail.php?id=' . $la['future_tour_id'];

                if ($promotedStatus === 'confirmed') {
                    $subject    = 'Tagsági kérelem jóváhagyva – Megerősített túrajelentkezés: ' . $la['tour_name'];
                    $statusHtml = '<strong style="color:#29776F;">Státusz: Megerősített</strong>';
                    $extraHtml  = (float)($la['participation_fee'] ?? 0) > 0
                      ? '<div style="background:#fffbeb;border:1px solid #f59e0b;border-radius:6px;padding:12px 16px;margin-top:16px;font-size:13.5px;color:#b45309;">
                        ⚠ Kérjük, a részvételi díjat <strong>14 napon belül</strong> utald el.
                      </div>'
                      : '';
                } else {
                    $subject    = 'Tagsági kérelem jóváhagyva – Várólistán: ' . $la['tour_name'];
                    $statusHtml = '<strong style="color:#b45309;">Státusz: Várólistán</strong>';
                    $extraHtml  = '<p style="color:#666;font-size:13.5px;">Ha felszabadul egy hely, értesítést kapsz, és akkor kell a részvételi díjat befizetni.</p>';
                }

                $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f5efe4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px;">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
  <tr><td style="background:#1a3d39;padding:24px 32px;">
    <h1 style="color:#c8a84b;margin:0;font-size:22px;">' . APP_NAME . '</h1>
    <p style="color:#a8c5c2;margin:6px 0 0;font-size:14px;">' . htmlspecialchars($subject, ENT_QUOTES) . '</p>
  </td></tr>
  <tr><td style="padding:28px 32px;">
    <p style="font-size:15px;">Kedves ' . htmlspecialchars($fullName, ENT_QUOTES) . '!</p>
    <p style="font-size:14px;color:#555;">Tagsági kérelmed jóváhagyásra került, és egyben megerősítettük a túrajelentkezésedet is:</p>
    <table width="100%" style="background:#f5efe4;border-radius:8px;padding:16px;margin:16px 0;font-size:14px;">
      <tr><td style="padding:4px 0;color:#666;">Túra neve:</td><td style="font-weight:600;">' . htmlspecialchars($la['tour_name'], ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">Kezdés:</td><td>' . htmlspecialchars($tourDate, ENT_QUOTES) . '</td></tr>
    </table>
    ' . $statusHtml . $extraHtml . '
    <div style="text-align:center;margin-top:24px;">
      <a href="' . $tourUrl . '" style="background:#29776F;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:14px;font-weight:600;">Túra megtekintése</a>
    </div>
  </td></tr>
  <tr><td style="padding:16px 32px;background:#f5f5f5;text-align:center;font-size:12px;color:#999;">
    ' . APP_NAME . ' &bull; Automatikus értesítő
  </td></tr>
</table></td></tr></table></body></html>';

                try {
                    $mailer->send($app['email'], $fullName, $subject, $html);
                    logEmailEntry($pdo, $newUserId, $app['email'], $fullName, $subject, $html, 'tour_membership_promotion', 'sent');
                } catch (Throwable $ex) {
                    logEmailEntry($pdo, $newUserId, $app['email'], $fullName, $subject, $html, 'tour_membership_promotion', 'failed', $ex->getMessage());
                }
            }
        }
    } catch (Throwable $ex) {
        error_log('Tour promotion email error: ' . $ex->getMessage());
    }
}

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

header('Location: ' . BASE_URL . '/admin/member-detail.php?id=' . $newUserId);
exit;
