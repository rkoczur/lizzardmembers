<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/app-settings-schema.php';
require_once __DIR__ . '/../includes/email-log-schema.php';
verifyCsrf();

$pdo = getDb();
ensureFutureToursSchema($pdo);
ensureAppSettingsSchema($pdo);
ensureEmailLogSchema($pdo);

$tourId     = (int)($_POST['tour_id']     ?? 0);
$guestName  = trim($_POST['guest_name']   ?? '');
$guestEmail = trim($_POST['guest_email']  ?? '');
$guestPhone = trim($_POST['guest_phone']  ?? '') ?: null;

$embed    = !empty($_POST['embed']);
$redirect = BASE_URL . '/user/future-tour-apply-public.php?id=' . $tourId . ($embed ? '&embed=1' : '');

$fail = function (string $msg) use ($embed, $redirect): void {
    if ($embed) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }
    flash('error', $msg);
    header('Location: ' . $redirect);
    exit;
};

if (!$tourId) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

if (!$guestName) {
    $fail('A név megadása kötelező.');
}

if (!$guestEmail || !filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
    $fail('Érvényes e-mail cím megadása kötelező.');
}

// Ellenőrzés: az e-mail cím regisztrált taghoz tartozik-e
$memberStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND active = 1 LIMIT 1");
$memberStmt->execute([$guestEmail]);
if ($memberStmt->fetch()) {
    $fail('Ezzel az e-mail címmel már van regisztrált felhasználó – lépj be a jelentkezéshez!');
}

$tourStmt = $pdo->prepare("SELECT * FROM future_tours WHERE id = ? AND status = 'open' LIMIT 1");
$tourStmt->execute([$tourId]);
$tour = $tourStmt->fetch();
if (!$tour) {
    $fail('Ez a túra nem érhető el vagy a jelentkezés lezárult.');
}

// Check for duplicate guest email on this tour (non-cancelled)
$dupStmt = $pdo->prepare("
    SELECT id FROM future_tour_applications
    WHERE future_tour_id = ? AND guest_email = ? AND status != 'cancelled'
    LIMIT 1
");
$dupStmt->execute([$tourId, $guestEmail]);
if ($dupStmt->fetch()) {
    $fail('Ezzel az e-mail címmel már van aktív jelentkezés erre a túrára.');
}

$carAvailable = isset($_POST['car_available']) && $_POST['car_available'] === '1' ? 1 : 0;
$passengers   = $carAvailable ? max(0, (int)($_POST['passengers'] ?? 0)) : 0;
$sharingRoom  = in_array($_POST['sharing_room'] ?? '', ['same_gender','yes','no']) ? $_POST['sharing_room'] : 'same_gender';
$notes        = trim($_POST['notes'] ?? '') ?: null;

$pdo->prepare("
    INSERT INTO future_tour_applications
        (future_tour_id, user_id, guest_name, guest_email, guest_phone, status, car_available, passengers, sharing_room, notes)
    VALUES (?, NULL, ?, ?, ?, 'pending', ?, ?, ?, ?)
")->execute([$tourId, $guestName, $guestEmail, $guestPhone, $carAvailable, $passengers, $sharingRoom, $notes]);
$appId = (int)$pdo->lastInsertId();

// Save custom field answers
$customFieldsStmt = $pdo->prepare("SELECT id FROM future_tour_custom_fields WHERE future_tour_id = ?");
$customFieldsStmt->execute([$tourId]);
$customFields = $customFieldsStmt->fetchAll();
$answerStmt = $pdo->prepare("INSERT INTO future_tour_application_answers (application_id, field_id, answer) VALUES (?, ?, ?)");
foreach ($customFields as $cf) {
    $key    = 'custom_field_' . $cf['id'];
    $answer = isset($_POST[$key]) ? trim($_POST[$key]) : '';
    $answerStmt->execute([$appId, $cf['id'], $answer]);
}

// --- Emails ---
$smtp        = getSmtpConfig($pdo);
$proto       = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$absBaseUrl  = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL;
$tourDate    = $tour['start_date'] ? formatDate($tour['start_date']) : '—';

// Email to guest
$guestSubject = 'Jelentkezés beérkezett – ' . $tour['name'];
$guestHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f5efe4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px;">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
  <tr><td style="background:#1a3d39;padding:24px 32px;">
    <h1 style="color:#c8a84b;margin:0;font-size:22px;">' . APP_NAME . '</h1>
    <p style="color:#a8c5c2;margin:6px 0 0;font-size:14px;">Jelentkezés beérkezett</p>
  </td></tr>
  <tr><td style="padding:28px 32px;">
    <p style="font-size:15px;">Kedves ' . htmlspecialchars($guestName, ENT_QUOTES) . '!</p>
    <p style="font-size:14px;color:#555;">Jelentkezésedet fogadtuk a következő túrára:</p>
    <table width="100%" style="background:#f5efe4;border-radius:8px;padding:16px;margin:16px 0;font-size:14px;">
      <tr><td style="padding:4px 0;color:#666;">Túra neve:</td><td style="font-weight:600;">' . htmlspecialchars($tour['name'], ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">Kezdés:</td><td>' . htmlspecialchars($tourDate, ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">Időtartam:</td><td>' . (int)$tour['num_days'] . ' nap</td></tr>
    </table>
    <div style="background:#fffbeb;border:1px solid #f59e0b;border-radius:6px;padding:12px 16px;font-size:13.5px;color:#b45309;">
      ⏳ Jelentkezésed <strong>jóváhagyásra vár.</strong> Az adminisztrátor hamarosan visszajelez e-mailben, és véglegesíti a részvételed.
    </div>
  </td></tr>
  <tr><td style="padding:16px 32px;background:#f5f5f5;text-align:center;font-size:12px;color:#999;">
    ' . APP_NAME . ' &bull; Automatikus értesítő
  </td></tr>
</table></td></tr></table></body></html>';

try {
    $mailer = new SmtpMailer($smtp);
    $mailer->send($guestEmail, $guestName, $guestSubject, $guestHtml);
    logEmailEntry($pdo, null, $guestEmail, $guestName, $guestSubject, $guestHtml, 'future_tour_guest_application', 'sent');
} catch (Throwable $e) {
    logEmailEntry($pdo, null, $guestEmail, $guestName, $guestSubject, $guestHtml, 'future_tour_guest_application', 'failed', $e->getMessage());
}

// Email to all active admins
$admins       = $pdo->query("SELECT id, email, firstname, lastname FROM users WHERE role = 'admin' AND active = 1")->fetchAll();
$adminSubject = 'Új vendég túrajelentkezés – ' . $tour['name'];
$adminUrl     = $absBaseUrl . '/admin/future-tour-detail.php?id=' . $tourId;
$adminHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f5efe4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px;">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
  <tr><td style="background:#1a3d39;padding:24px 32px;">
    <h1 style="color:#c8a84b;margin:0;font-size:22px;">' . APP_NAME . '</h1>
    <p style="color:#a8c5c2;margin:6px 0 0;font-size:14px;">Új vendég jelentkezés – jóváhagyás szükséges</p>
  </td></tr>
  <tr><td style="padding:28px 32px;">
    <p style="font-size:15px;">Új vendég jelentkezés érkezett a <strong>' . htmlspecialchars($tour['name'], ENT_QUOTES) . '</strong> túrára:</p>
    <table width="100%" style="background:#f5efe4;border-radius:8px;padding:16px;margin:16px 0;font-size:14px;">
      <tr><td style="padding:4px 0;color:#666;">Név:</td><td style="font-weight:600;">' . htmlspecialchars($guestName, ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">E-mail:</td><td>' . htmlspecialchars($guestEmail, ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">Telefon:</td><td>' . htmlspecialchars($guestPhone ?? '—', ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">Beérkezés:</td><td>' . date('Y.m.d H:i') . '</td></tr>
    </table>
    <div style="text-align:center;margin-top:24px;">
      <a href="' . $adminUrl . '" style="background:#29776F;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:14px;font-weight:600;">Jóváhagyás az adminfelületen</a>
    </div>
  </td></tr>
  <tr><td style="padding:16px 32px;background:#f5f5f5;text-align:center;font-size:12px;color:#999;">
    ' . APP_NAME . ' &bull; Automatikus értesítő
  </td></tr>
</table></td></tr></table></body></html>';

foreach ($admins as $admin) {
    $adminName = $admin['lastname'] . ' ' . $admin['firstname'];
    try {
        $mailer = new SmtpMailer($smtp);
        $mailer->send($admin['email'], $adminName, $adminSubject, $adminHtml);
        logEmailEntry($pdo, $admin['id'], $admin['email'], $adminName, $adminSubject, $adminHtml, 'future_tour_guest_application_admin', 'sent');
    } catch (Throwable $e) {
        logEmailEntry($pdo, $admin['id'], $admin['email'], $adminName, $adminSubject, $adminHtml, 'future_tour_guest_application_admin', 'failed', $e->getMessage());
    }
}

if ($embed) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}
header('Location: ' . $redirect . '&done=1');
exit;
