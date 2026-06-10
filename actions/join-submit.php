<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/join-schema.php';
require_once __DIR__ . '/../includes/captcha.php';

verifyCsrf();

$pdo = getDb();
ensureJoinSchema($pdo);

$tourId      = (int)($_POST['tour_id']    ?? 0);
$joinEmbed   = !empty($_POST['join_embed']);
$redirectBack = $tourId > 0
    ? BASE_URL . '/public/tour-apply.php?id=' . $tourId
    : BASE_URL . '/join.php';

if (recaptchaEnabled($pdo) && !verifyRecaptcha($pdo, $_POST['g-recaptcha-response'] ?? '', $_SERVER['REMOTE_ADDR'] ?? null)) {
    flash('error', 'Kérjük, igazold, hogy nem vagy robot.');
    $_SESSION['join_old'] = $_POST;
    header('Location: ' . $redirectBack);
    exit;
}

$lastname    = trim($_POST['lastname']    ?? '');
$firstname   = trim($_POST['firstname']  ?? '');
$email       = trim($_POST['email']      ?? '');
$phone       = trim($_POST['phone']      ?? '');
$dateofbirth = $_POST['dateofbirth']     ?? '';
$zipcode     = trim($_POST['zipcode']    ?? '');
$city        = trim($_POST['city']       ?? '');
$address     = trim($_POST['address']    ?? '');
$message     = trim($_POST['message']    ?? '');
$consentEmail = isset($_POST['consent_email']) ? 1 : 0;
$consentPhoto = isset($_POST['consent_photo']) ? 1 : 0;
$consentRules = isset($_POST['consent_rules']) ? 1 : 0;

if (!$lastname || !$firstname || !$email || !$dateofbirth || !$zipcode || !$city || !$address) {
    flash('error', 'A csillaggal jelölt mezők kitöltése kötelező.');
    $_SESSION['join_old'] = $_POST;
    header('Location: ' . $redirectBack);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('error', 'Kérjük, adjon meg érvényes e-mail-címet.');
    $_SESSION['join_old'] = $_POST;
    header('Location: ' . $redirectBack);
    exit;
}
if (!$consentRules) {
    flash('error', 'Az adatvédelmi tájékoztató, az alapszabály és a részvételi feltételek elfogadása kötelező a jelentkezés elküldéséhez.');
    $_SESSION['join_old'] = $_POST;
    header('Location: ' . $redirectBack);
    exit;
}

// Check existing member with this email
$existingUser = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$existingUser->execute([$email]);
if ($existingUser->fetch()) {
    flash('error', 'Ezzel az e-mail-címmel már regisztrált tag létezik a rendszerben. Ha elfelejtette jelszavát, használja a jelszó-visszaállítást.');
    $_SESSION['join_old'] = $_POST;
    header('Location: ' . $redirectBack);
    exit;
}

// Duplicate pending/approved application check
$dup = $pdo->prepare("SELECT id FROM member_applications WHERE email = ? AND status IN ('pending','approved') LIMIT 1");
$dup->execute([$email]);
if ($dup->fetch()) {
    flash('error', 'Ezzel az e-mail-címmel már van folyamatban lévő jelentkezés. Ha nem te adtad le, lépj kapcsolatba az egyesülettel.');
    $_SESSION['join_old'] = $_POST;
    header('Location: ' . $redirectBack);
    exit;
}

$pdo->prepare("INSERT INTO member_applications
    (lastname, firstname, email, phone, dateofbirth, zipcode, city, address, message, consent_email, consent_photo, consent_rules)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
    ->execute([
        $lastname, $firstname, $email,
        $phone       ?: null,
        $dateofbirth ?: null,
        $zipcode     ?: null,
        $city        ?: null,
        $address     ?: null,
        $message     ?: null,
        $consentEmail, $consentPhoto, $consentRules,
    ]);
$memberAppId = (int)$pdo->lastInsertId();

// If submitted via a membership-required tour page, also create a pending tour application
if ($tourId > 0) {
    require_once __DIR__ . '/../includes/future-tours-schema.php';
    ensureFutureToursSchema($pdo);

    $tourRow = $pdo->prepare("SELECT id FROM future_tours WHERE id = ? AND status = 'open' LIMIT 1");
    $tourRow->execute([$tourId]);
    if ($tourRow->fetch()) {
        $carAvailable  = isset($_POST['car_available']) && $_POST['car_available'] === '1' ? 1 : 0;
        $passengers    = $carAvailable ? max(0, (int)($_POST['passengers'] ?? 0)) : 0;
        $sharingRoom   = in_array($_POST['sharing_room'] ?? '', ['same_gender','yes','no']) ? $_POST['sharing_room'] : 'same_gender';
        $tourNotes     = trim($_POST['notes'] ?? '') ?: null;
        $departureCity = trim($_POST['departure_city'] ?? '') ?: null;
        $guestName     = $lastname . ' ' . $firstname;

        $pdo->prepare("
            INSERT INTO future_tour_applications
                (future_tour_id, user_id, guest_name, guest_email, guest_phone, status,
                 car_available, passengers, sharing_room, notes, departure_city, member_application_id)
            VALUES (?, NULL, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?)
        ")->execute([$tourId, $guestName, $email, $phone ?: null,
                     $carAvailable, $passengers, $sharingRoom, $tourNotes, $departureCity, $memberAppId]);

        $tourAppId = (int)$pdo->lastInsertId();

        $cfStmt = $pdo->prepare("SELECT id FROM future_tour_custom_fields WHERE future_tour_id = ?");
        $cfStmt->execute([$tourId]);
        $answerStmt = $pdo->prepare("INSERT INTO future_tour_application_answers (application_id, field_id, answer) VALUES (?, ?, ?)");
        foreach ($cfStmt->fetchAll() as $cf) {
            $answer = isset($_POST['custom_field_' . $cf['id']]) ? trim($_POST['custom_field_' . $cf['id']]) : '';
            $answerStmt->execute([$tourAppId, $cf['id'], $answer]);
        }
    }
}

// Confirmation email (optional — only if SMTP is configured)
try {
    require_once __DIR__ . '/../includes/app-settings-schema.php';
    require_once __DIR__ . '/../includes/mailer.php';
    require_once __DIR__ . '/../includes/email-log-schema.php';

    ensureAppSettingsSchema($pdo);
    ensureEmailLogSchema($pdo);
    $smtp = getSmtpConfig($pdo);

    if ($smtp['host'] !== '') {
        $appName = APP_NAME;
        $html = <<<HTML
<!DOCTYPE html>
<html lang="hu">
<head><meta charset="UTF-8"><title>Jelentkezés beérkezett</title></head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;">
<div style="max-width:580px;margin:32px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,.08);">
  <div style="background:#29776F;padding:36px 40px;text-align:center;">
    <h1 style="color:#ffffff;margin:0;font-size:26px;font-weight:700;letter-spacing:-.5px;">{$appName}</h1>
    <p style="color:rgba(255,255,255,.75);margin:6px 0 0;font-size:13px;">Tagságkezelés</p>
  </div>
  <div style="padding:40px;">
    <h2 style="color:#1a1a1a;font-size:20px;margin:0 0 16px;font-weight:700;">Köszönjük a jelentkezésedet, {$firstname}!</h2>
    <p style="color:#444;font-size:15px;line-height:1.7;margin:0 0 14px;">Megkaptuk az <strong>{$appName}</strong> egyesületbe vonatkozó belépési kérelmedet.</p>
    <p style="color:#444;font-size:15px;line-height:1.7;margin:0 0 24px;">Az egyesület képviselői hamarosan átnézik a jelentkezésedet, és e-mailben értesítünk a döntésről.</p>
    <p style="color:#888;font-size:13px;margin:0;border-top:1px solid #eee;padding-top:20px;">Ha kérdésed van, válaszolj erre az e-mailre.</p>
  </div>
  <div style="background:#f9f9f9;padding:18px 40px;text-align:center;border-top:1px solid #eee;">
    <p style="color:#bbb;font-size:12px;margin:0;">&copy; {$appName}</p>
  </div>
</div>
</body>
</html>
HTML;
        $subject = 'Jelentkezésed beérkezett — ' . $appName;
        $mailer  = new SmtpMailer($smtp);
        $mailer->send($email, $lastname . ' ' . $firstname, $subject, $html);
        logEmailEntry($pdo, null, $email, $lastname . ' ' . $firstname, $subject, $html, 'join_confirm', 'sent');

        // Admin notification
        $adminRows = $pdo->query("SELECT email, firstname, lastname FROM users WHERE role IN ('admin','vezeto') AND active = 1")->fetchAll();
        if ($adminRows) {
            $proto      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $appsUrl    = $proto . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/admin/applications.php?status=pending';
            $applicantName = htmlspecialchars($lastname . ' ' . $firstname, ENT_QUOTES, 'UTF-8');
            $applicantEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
            $applicantCity  = htmlspecialchars($city ?: '—', ENT_QUOTES, 'UTF-8');
            $applicantPhone = htmlspecialchars($phone ?: '—', ENT_QUOTES, 'UTF-8');
            $safeApp = htmlspecialchars($appName, ENT_QUOTES, 'UTF-8');
            $safeUrl = htmlspecialchars($appsUrl, ENT_QUOTES, 'UTF-8');
            $adminHtml = <<<AHTML
<!DOCTYPE html>
<html lang="hu">
<head><meta charset="UTF-8"><title>Új tagfelvételi kérelem</title></head>
<body style="margin:0;padding:0;background:#f0ebe0;font-family:system-ui,-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f0ebe0;padding:32px 16px;">
  <tr><td align="center">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:540px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.12);">
    <tr>
      <td style="background:#1a3d39;padding:28px 40px;text-align:center;">
        <div style="font-size:22px;font-weight:800;color:#F4E7CF;letter-spacing:.05em;">{$safeApp}</div>
        <div style="font-size:11px;color:#8fb5b2;margin-top:4px;letter-spacing:.12em;text-transform:uppercase;">Admin értesítő</div>
      </td>
    </tr>
    <tr>
      <td style="padding:32px 40px;">
        <p style="font-size:15px;font-weight:700;color:#1a3d39;margin:0 0 6px;">Új tagfelvételi kérelem érkezett</p>
        <p style="font-size:13px;color:#666;margin:0 0 24px;">Az alábbi személy tagfelvételi kérelmet nyújtott be a rendszeren keresztül.</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5efe4;border:1px solid #ddd5c5;border-radius:8px;margin:0 0 24px;">
          <tr><td style="padding:18px 22px;">
            <table width="100%" cellpadding="4" cellspacing="0" style="font-size:13px;color:#444;">
              <tr><td style="color:#7a7269;width:110px;">Név:</td><td><strong>{$applicantName}</strong></td></tr>
              <tr><td style="color:#7a7269;">E-mail:</td><td>{$applicantEmail}</td></tr>
              <tr><td style="color:#7a7269;">Telefon:</td><td>{$applicantPhone}</td></tr>
              <tr><td style="color:#7a7269;">Város:</td><td>{$applicantCity}</td></tr>
            </table>
          </td></tr>
        </table>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
          <tr><td align="center">
            <a href="{$safeUrl}" style="display:inline-block;background:#29776F;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;padding:12px 32px;border-radius:8px;">
              Kérelem megtekintése és kezelése
            </a>
          </td></tr>
        </table>
      </td>
    </tr>
    <tr>
      <td style="background:#f5efe4;border-top:1px solid #ddd5c5;padding:16px 40px;text-align:center;">
        <p style="font-size:12px;color:#7a7269;margin:0;">{$safeApp} — automatikus rendszerüzenet</p>
      </td>
    </tr>
  </table>
  </td></tr>
</table>
</body>
</html>
AHTML;
            $adminSubject = 'Új tagfelvételi kérelem: ' . $lastname . ' ' . $firstname;
            foreach ($adminRows as $admin) {
                try {
                    $mailer->send($admin['email'], $admin['lastname'] . ' ' . $admin['firstname'], $adminSubject, $adminHtml);
                    logEmailEntry($pdo, null, $admin['email'], $admin['lastname'] . ' ' . $admin['firstname'], $adminSubject, $adminHtml, 'join_admin_notify', 'sent');
                } catch (Throwable $ex) {
                    error_log('Admin join notify email: ' . $ex->getMessage());
                }
            }
        }
    }
} catch (Throwable $ex) {
    error_log('Join confirmation email: ' . $ex->getMessage());
}

$successParam = $tourId > 0 ? '&membership_submitted=1' : '?submitted=1';
header('Location: ' . $redirectBack . $successParam);
exit;
