<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/join-schema.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';

header('Content-Type: application/json; charset=utf-8');

$fail = function (string $msg): void {
    echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
};

verifyCsrf();

$pdo = getDb();
ensureJoinSchema($pdo);
ensureFutureToursSchema($pdo);

$tourId      = (int)($_POST['tour_id']    ?? 0);
$lastname    = trim($_POST['lastname']    ?? '');
$firstname   = trim($_POST['firstname']  ?? '');
$email       = trim($_POST['email']      ?? '');
$phone       = trim($_POST['phone']      ?? '');
$dateofbirth = trim($_POST['dateofbirth'] ?? '');
$zipcode     = trim($_POST['zipcode']    ?? '');
$city        = trim($_POST['city']       ?? '');
$address     = trim($_POST['address']    ?? '');
$message     = trim($_POST['message']    ?? '');
$consentEmail = isset($_POST['consent_email']) && $_POST['consent_email'] === '1' ? 1 : 0;
$consentPhoto = isset($_POST['consent_photo']) && $_POST['consent_photo'] === '1' ? 1 : 0;
$consentRules = isset($_POST['consent_rules']) && $_POST['consent_rules'] === '1' ? 1 : 0;

if (!$tourId)                                          $fail('Érvénytelen túra azonosító.');
if (!$lastname || !$firstname || !$email
    || !$dateofbirth || !$zipcode || !$city || !$address) {
    $fail('A csillaggal jelölt mezők kitöltése kötelező.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL))        $fail('Kérjük, érvényes e-mail-címet adj meg.');
if (!$consentRules)                                    $fail('Az adatvédelmi tájékoztató és az alapszabály elfogadása kötelező.');

// Validate tour: must exist, open and require membership
$tourStmt = $pdo->prepare("SELECT * FROM future_tours WHERE id = ? AND status = 'open' AND requires_membership = 1 LIMIT 1");
$tourStmt->execute([$tourId]);
$tour = $tourStmt->fetch();
if (!$tour) $fail('Ez a túra nem érhető el.');

// Check no existing active member with this e-mail
$existUser = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
$existUser->execute([$email]);
if ($existUser->fetch()) {
    $fail('Ezzel az e-mail-címmel már regisztrált tag létezik – lépj be a jelentkezéshez!');
}

// Check no duplicate pending/approved membership application
$dup = $pdo->prepare("SELECT id FROM member_applications WHERE email = ? AND status IN ('pending','approved') LIMIT 1");
$dup->execute([$email]);
if ($dup->fetch()) {
    $fail('Ezzel az e-mail-címmel már van folyamatban lévő tagságra jelentkezés.');
}

// Check no duplicate guest application for this tour
$dupTour = $pdo->prepare("
    SELECT id FROM future_tour_applications
    WHERE future_tour_id = ? AND guest_email = ? AND status != 'cancelled'
    LIMIT 1
");
$dupTour->execute([$tourId, $email]);
if ($dupTour->fetch()) {
    $fail('Ezzel az e-mail-címmel már van aktív jelentkezés erre a túrára.');
}

// ── Insert member application ─────────────────────────────────────────
$pdo->prepare("INSERT INTO member_applications
    (lastname, firstname, email, phone, dateofbirth, zipcode, city, address, message, consent_email, consent_photo, consent_rules)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
    ->execute([$lastname, $firstname, $email, $phone ?: null, $dateofbirth ?: null,
               $zipcode ?: null, $city ?: null, $address ?: null, $message ?: null,
               $consentEmail, $consentPhoto, $consentRules]);
$memberAppId = (int)$pdo->lastInsertId();

// ── Insert pending tour application ──────────────────────────────────
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

// Save custom field answers
$cfStmt = $pdo->prepare("SELECT id FROM future_tour_custom_fields WHERE future_tour_id = ?");
$cfStmt->execute([$tourId]);
$answerStmt = $pdo->prepare("INSERT INTO future_tour_application_answers (application_id, field_id, answer) VALUES (?, ?, ?)");
foreach ($cfStmt->fetchAll() as $cf) {
    $answer = isset($_POST['custom_field_' . $cf['id']]) ? trim($_POST['custom_field_' . $cf['id']]) : '';
    $answerStmt->execute([$tourAppId, $cf['id'], $answer]);
}

// A sikeres választ azonnal visszaküldjük, az e-maileket utána (háttérben) küldjük —
// így a lassú SMTP nem okoz a kliensnél téves „hálózati hiba" üzenetet.
respondAndContinue(json_encode(['success' => true], JSON_UNESCAPED_UNICODE));
@set_time_limit(60);

// ── Emails (optional, a válasz után) ─────────────────────────────────
try {
    require_once __DIR__ . '/../includes/app-settings-schema.php';
    require_once __DIR__ . '/../includes/mailer.php';
    require_once __DIR__ . '/../includes/email-log-schema.php';
    ensureAppSettingsSchema($pdo);
    ensureEmailLogSchema($pdo);
    $smtp = getSmtpConfig($pdo);

    if ($smtp['host'] !== '') {
        $tourDate    = $tour['start_date'] ? formatDate($tour['start_date']) : '—';
        $fullName    = $lastname . ' ' . $firstname;
        $guestSubject = 'Tagságra és túrára jelentkezés beérkezett – ' . $tour['name'];

        $guestHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f5efe4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px;">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
  <tr><td style="background:#1a3d39;padding:24px 32px;">
    <h1 style="color:#c8a84b;margin:0;font-size:22px;">' . APP_NAME . '</h1>
    <p style="color:#a8c5c2;margin:6px 0 0;font-size:14px;">Tagságra és túrára jelentkezés beérkezett</p>
  </td></tr>
  <tr><td style="padding:28px 32px;">
    <p style="font-size:15px;">Kedves ' . htmlspecialchars($fullName, ENT_QUOTES) . '!</p>
    <p style="font-size:14px;color:#555;">Tagságra és az alábbi túrára vonatkozó jelentkezésedet fogadtuk:</p>
    <table width="100%" style="background:#f5efe4;border-radius:8px;padding:16px;margin:16px 0;font-size:14px;">
      <tr><td style="padding:4px 0;color:#666;">Túra neve:</td><td style="font-weight:600;">' . htmlspecialchars($tour['name'], ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">Kezdés:</td><td>' . htmlspecialchars($tourDate, ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">Időtartam:</td><td>' . (int)$tour['num_days'] . ' nap</td></tr>
    </table>
    <div style="background:#fffbeb;border:1px solid #f59e0b;border-radius:6px;padding:12px 16px;font-size:13.5px;color:#b45309;">
      ⏳ Tagságra vonatkozó kérelmedet az adminisztrátor hamarosan elbírálja. Jóváhagyás után automatikusan megerősítjük a túrajelentkezésedet is, és e-mailben értesítünk.
    </div>
  </td></tr>
  <tr><td style="padding:16px 32px;background:#f5f5f5;text-align:center;font-size:12px;color:#999;">
    ' . APP_NAME . ' &bull; Automatikus értesítő
  </td></tr>
</table></td></tr></table></body></html>';

        $mailer = new SmtpMailer($smtp);
        $mailer->send($email, $fullName, $guestSubject, $guestHtml);
        logEmailEntry($pdo, null, $email, $fullName, $guestSubject, $guestHtml, 'join_tour_application', 'sent');

        // Admin notification
        $admins      = $pdo->query("SELECT id, email, firstname, lastname FROM users WHERE role = 'admin' AND active = 1")->fetchAll();
        $proto       = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $absBaseUrl  = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL;
        $adminSubject = 'Új tagságra+túrára jelentkezés – ' . $tour['name'];
        $appsUrl     = $absBaseUrl . '/admin/members.php?tab=applications';
        $adminHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f5efe4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px;">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
  <tr><td style="background:#1a3d39;padding:24px 32px;">
    <h1 style="color:#c8a84b;margin:0;font-size:22px;">' . APP_NAME . '</h1>
    <p style="color:#a8c5c2;margin:6px 0 0;font-size:14px;">Új tagságra+túrára jelentkezés – jóváhagyás szükséges</p>
  </td></tr>
  <tr><td style="padding:28px 32px;">
    <p style="font-size:15px;">Új kombinált tagság+túra-jelentkezés érkezett:</p>
    <table width="100%" style="background:#f5efe4;border-radius:8px;padding:16px;margin:16px 0;font-size:14px;">
      <tr><td style="padding:4px 0;color:#666;">Név:</td><td style="font-weight:600;">' . htmlspecialchars($fullName, ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">E-mail:</td><td>' . htmlspecialchars($email, ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">Túra:</td><td>' . htmlspecialchars($tour['name'], ENT_QUOTES) . '</td></tr>
    </table>
    <div style="text-align:center;margin-top:24px;">
      <a href="' . htmlspecialchars($appsUrl, ENT_QUOTES) . '" style="background:#29776F;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:14px;font-weight:600;">Tagkérelem elbírálása</a>
    </div>
  </td></tr>
  <tr><td style="padding:16px 32px;background:#f5f5f5;text-align:center;font-size:12px;color:#999;">
    ' . APP_NAME . ' &bull; Automatikus értesítő
  </td></tr>
</table></td></tr></table></body></html>';

        foreach ($admins as $admin) {
            $adminName = $admin['lastname'] . ' ' . $admin['firstname'];
            try {
                $mailer->send($admin['email'], $adminName, $adminSubject, $adminHtml);
                logEmailEntry($pdo, $admin['id'], $admin['email'], $adminName, $adminSubject, $adminHtml, 'join_tour_application_admin', 'sent');
            } catch (Throwable $ex) {
                error_log('join-tour-submit admin notify: ' . $ex->getMessage());
            }
        }
    }
} catch (Throwable $ex) {
    error_log('join-tour-submit email: ' . $ex->getMessage());
}
exit;
