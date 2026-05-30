<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/app-settings-schema.php';
require_once __DIR__ . '/../includes/email-log-schema.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Érvénytelen kérés.']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!$token || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Érvénytelen biztonsági token. Töltsd újra az oldalt.']);
    exit;
}

$pdo = getDb();
ensureFutureToursSchema($pdo);
ensureAppSettingsSchema($pdo);
ensureEmailLogSchema($pdo);

$tourId = (int)($_POST['tour_id'] ?? 0);
if (!$tourId) {
    echo json_encode(['success' => false, 'error' => 'Érvénytelen túra azonosító.']);
    exit;
}

$memberLoggedIn = isLoggedIn();
$memberUser     = null;
if ($memberLoggedIn) {
    $stmt = $pdo->prepare("SELECT id, firstname, lastname, email FROM users WHERE id = ? AND active = 1 LIMIT 1");
    $stmt->execute([getCurrentUserId()]);
    $memberUser = $stmt->fetch();
    if (!$memberUser) $memberLoggedIn = false;
}

if ($memberLoggedIn) {
    $userId       = (int)$memberUser['id'];
    $guestName    = null;
    $guestEmail   = null;
    $guestPhone   = null;
    $displayName  = trim($memberUser['lastname'] . ' ' . $memberUser['firstname']);
    $displayEmail = $memberUser['email'];
} else {
    $userId       = null;
    $guestName    = trim($_POST['guest_name']  ?? '');
    $guestEmail   = trim($_POST['guest_email'] ?? '');
    $guestPhone   = trim($_POST['guest_phone'] ?? '') ?: null;
    $displayName  = $guestName;
    $displayEmail = $guestEmail;

    if (!$guestName) {
        echo json_encode(['success' => false, 'error' => 'A név megadása kötelező.']);
        exit;
    }
    if (!$guestEmail || !filter_var($guestEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Érvényes e-mail cím megadása kötelező.']);
        exit;
    }

    $memberCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND active = 1 LIMIT 1");
    $memberCheck->execute([$guestEmail]);
    if ($memberCheck->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Ezzel az e-mail címmel már van regisztrált felhasználó – lépj be a jelentkezéshez!']);
        exit;
    }
}

$tourStmt = $pdo->prepare("SELECT * FROM future_tours WHERE id = ? AND status = 'open' LIMIT 1");
$tourStmt->execute([$tourId]);
$tour = $tourStmt->fetch();
if (!$tour) {
    echo json_encode(['success' => false, 'error' => 'Ez a túra nem érhető el vagy a jelentkezés lezárult.']);
    exit;
}

$existing = null;
if ($memberLoggedIn) {
    $dupStmt = $pdo->prepare("SELECT id, status FROM future_tour_applications WHERE future_tour_id = ? AND user_id = ? LIMIT 1");
    $dupStmt->execute([$tourId, $userId]);
    $existing = $dupStmt->fetch();
    if ($existing && $existing['status'] !== 'cancelled') {
        echo json_encode(['success' => false, 'error' => 'Már jelentkeztél erre a túrára.']);
        exit;
    }
} else {
    $dupStmt = $pdo->prepare("SELECT id FROM future_tour_applications WHERE future_tour_id = ? AND guest_email = ? AND status != 'cancelled' LIMIT 1");
    $dupStmt->execute([$tourId, $guestEmail]);
    if ($dupStmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Ezzel az e-mail címmel már van aktív jelentkezés erre a túrára.']);
        exit;
    }
}

$carAvailable  = isset($_POST['car_available']) && $_POST['car_available'] === '1' ? 1 : 0;
$passengers    = $carAvailable ? max(0, (int)($_POST['passengers'] ?? 0)) : 0;
$sharingRoom   = in_array($_POST['sharing_room'] ?? '', ['same_gender', 'yes', 'no']) ? $_POST['sharing_room'] : 'same_gender';
$notes         = trim($_POST['notes'] ?? '') ?: null;
$departureCity = trim($_POST['departure_city'] ?? '') ?: null;

if ($memberLoggedIn) {
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM future_tour_applications WHERE future_tour_id = ? AND status = 'confirmed'");
    $cntStmt->execute([$tourId]);
    $appStatus = (int)$cntStmt->fetchColumn() >= (int)$tour['max_attendees'] ? 'waitlist' : 'confirmed';
} else {
    $appStatus = 'pending';
}

if ($memberLoggedIn && $existing && $existing['status'] === 'cancelled') {
    $pdo->prepare("UPDATE future_tour_applications SET status=?, car_available=?, passengers=?, sharing_room=?, notes=?, departure_city=?, paid_at=NULL, applied_at=NOW() WHERE id=?")
        ->execute([$appStatus, $carAvailable, $passengers, $sharingRoom, $notes, $departureCity, $existing['id']]);
    $appId = $existing['id'];
    $pdo->prepare("DELETE FROM future_tour_application_answers WHERE application_id = ?")->execute([$appId]);
} elseif ($memberLoggedIn) {
    $pdo->prepare("INSERT INTO future_tour_applications (future_tour_id, user_id, status, car_available, passengers, sharing_room, notes, departure_city) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$tourId, $userId, $appStatus, $carAvailable, $passengers, $sharingRoom, $notes, $departureCity]);
    $appId = (int)$pdo->lastInsertId();
} else {
    $pdo->prepare("INSERT INTO future_tour_applications (future_tour_id, user_id, guest_name, guest_email, guest_phone, status, car_available, passengers, sharing_room, notes, departure_city) VALUES (?, NULL, ?, ?, ?, 'pending', ?, ?, ?, ?, ?)")
        ->execute([$tourId, $guestName, $guestEmail, $guestPhone, $carAvailable, $passengers, $sharingRoom, $notes, $departureCity]);
    $appId = (int)$pdo->lastInsertId();
}

$cfStmt = $pdo->prepare("SELECT id FROM future_tour_custom_fields WHERE future_tour_id = ?");
$cfStmt->execute([$tourId]);
$answerStmt = $pdo->prepare("INSERT INTO future_tour_application_answers (application_id, field_id, answer) VALUES (?, ?, ?)");
foreach ($cfStmt->fetchAll() as $cf) {
    $key    = 'custom_field_' . $cf['id'];
    $answer = isset($_POST[$key]) ? trim($_POST[$key]) : '';
    $answerStmt->execute([$appId, $cf['id'], $answer]);
}

// --- Emails ---
$smtp       = getSmtpConfig($pdo);
$proto      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$absBaseUrl = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL;
$tourDate   = $tour['start_date'] ? formatDate($tour['start_date']) : '—';

if ($memberLoggedIn) {
    $tourUrl = $absBaseUrl . '/user/future-tour-detail.php?id=' . $tourId;
    if ($appStatus === 'confirmed') {
        $subject     = 'Sikeres jelentkezés – ' . $tour['name'];
        $statusBlock = '<strong style="color:#29776F;">Státusz: Megerősített</strong>
          <div style="background:#fffbeb;border:1px solid #f59e0b;border-radius:6px;padding:12px 16px;margin-top:16px;font-size:13.5px;color:#b45309;">
            ⚠ Kérjük, a részvételi díjat <strong>14 napon belül</strong> utald el.
          </div>';
    } else {
        $subject     = 'Várólistára kerültél – ' . $tour['name'];
        $statusBlock = '<strong style="color:#b45309;">Státusz: Várólistán</strong>
          <p style="color:#666;font-size:13.5px;">Ha felszabadul egy hely, értesítést kapsz, és akkor kell a részvételi díjat befizetni.</p>';
    }
} else {
    $tourUrl     = null;
    $subject     = 'Jelentkezés beérkezett – ' . $tour['name'];
    $statusBlock = '<div style="background:#fffbeb;border:1px solid #f59e0b;border-radius:6px;padding:12px 16px;font-size:13.5px;color:#b45309;">
        ⏳ Jelentkezésed <strong>jóváhagyásra vár.</strong> Az adminisztrátor hamarosan visszajelez e-mailben, és véglegesíti a részvételed.
      </div>';
}

$tourUrl     = $tourUrl ?? '';
$applicantHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f5efe4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px;">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
  <tr><td style="background:#1a3d39;padding:24px 32px;">
    <h1 style="color:#c8a84b;margin:0;font-size:22px;">' . APP_NAME . '</h1>
    <p style="color:#a8c5c2;margin:6px 0 0;font-size:14px;">' . htmlspecialchars($subject, ENT_QUOTES) . '</p>
  </td></tr>
  <tr><td style="padding:28px 32px;">
    <p style="font-size:15px;">Kedves ' . htmlspecialchars($displayName, ENT_QUOTES) . '!</p>
    <p style="font-size:14px;color:#555;">Jelentkezésedet fogadtuk a következő túrára:</p>
    <table width="100%" style="background:#f5efe4;border-radius:8px;padding:16px;margin:16px 0;font-size:14px;">
      <tr><td style="padding:4px 0;color:#666;">Túra neve:</td><td style="font-weight:600;">' . htmlspecialchars($tour['name'], ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">Kezdés:</td><td>' . htmlspecialchars($tourDate, ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">Időtartam:</td><td>' . (int)$tour['num_days'] . ' nap</td></tr>
    </table>
    ' . $statusBlock . '
    ' . ($tourUrl ? '<div style="text-align:center;margin-top:24px;"><a href="' . $tourUrl . '" style="background:#29776F;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:14px;font-weight:600;">Túra megtekintése</a></div>' : '') . '
  </td></tr>
  <tr><td style="padding:16px 32px;background:#f5f5f5;text-align:center;font-size:12px;color:#999;">
    ' . APP_NAME . ' &bull; Automatikus értesítő
  </td></tr>
</table></td></tr></table></body></html>';

$emailTo  = $memberLoggedIn ? $memberUser['email'] : $guestEmail;
$logType  = $memberLoggedIn ? 'future_tour_application' : 'future_tour_guest_application';
try {
    (new SmtpMailer($smtp))->send($emailTo, $displayName, $subject, $applicantHtml);
    logEmailEntry($pdo, $memberLoggedIn ? $userId : null, $emailTo, $displayName, $subject, $applicantHtml, $logType, 'sent');
} catch (Throwable $ex) {
    logEmailEntry($pdo, $memberLoggedIn ? $userId : null, $emailTo, $displayName, $subject, $applicantHtml, $logType, 'failed', $ex->getMessage());
}

$adminSubject = ($memberLoggedIn ? 'Új túrajelentkezés' : 'Új vendég túrajelentkezés') . ' – ' . $tour['name'];
$adminUrl     = $absBaseUrl . '/admin/future-tour-detail.php?id=' . $tourId;
$adminHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f5efe4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px;">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
  <tr><td style="background:#1a3d39;padding:24px 32px;">
    <h1 style="color:#c8a84b;margin:0;font-size:22px;">' . APP_NAME . '</h1>
    <p style="color:#a8c5c2;margin:6px 0 0;font-size:14px;">' . ($memberLoggedIn ? 'Új tag jelentkezés' : 'Új vendég jelentkezés – jóváhagyás szükséges') . '</p>
  </td></tr>
  <tr><td style="padding:28px 32px;">
    <p style="font-size:15px;">Új jelentkezés érkezett a <strong>' . htmlspecialchars($tour['name'], ENT_QUOTES) . '</strong> túrára:</p>
    <table width="100%" style="background:#f5efe4;border-radius:8px;padding:16px;margin:16px 0;font-size:14px;">
      <tr><td style="padding:4px 0;color:#666;">Név:</td><td style="font-weight:600;">' . htmlspecialchars($displayName, ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">E-mail:</td><td>' . htmlspecialchars($displayEmail, ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">Státusz:</td><td>' . ($appStatus === 'confirmed' ? 'Megerősített' : ($appStatus === 'waitlist' ? 'Várólistán' : 'Jóváhagyásra vár')) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">Beérkezés:</td><td>' . date('Y.m.d H:i') . '</td></tr>
    </table>
    <div style="text-align:center;margin-top:24px;">
      <a href="' . $adminUrl . '" style="background:#29776F;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:14px;font-weight:600;">Megtekintés adminfelületen</a>
    </div>
  </td></tr>
  <tr><td style="padding:16px 32px;background:#f5f5f5;text-align:center;font-size:12px;color:#999;">
    ' . APP_NAME . ' &bull; Automatikus értesítő
  </td></tr>
</table></td></tr></table></body></html>';

$adminLogType = $memberLoggedIn ? 'future_tour_new_application_admin' : 'future_tour_guest_application_admin';
foreach ($pdo->query("SELECT id, email, firstname, lastname FROM users WHERE role = 'admin' AND active = 1")->fetchAll() as $admin) {
    $adminName = $admin['lastname'] . ' ' . $admin['firstname'];
    try {
        (new SmtpMailer($smtp))->send($admin['email'], $adminName, $adminSubject, $adminHtml);
        logEmailEntry($pdo, $admin['id'], $admin['email'], $adminName, $adminSubject, $adminHtml, $adminLogType, 'sent');
    } catch (Throwable $ex) {
        logEmailEntry($pdo, $admin['id'], $admin['email'], $adminName, $adminSubject, $adminHtml, $adminLogType, 'failed', $ex->getMessage());
    }
}

echo json_encode(['success' => true, 'status' => $appStatus], JSON_UNESCAPED_UNICODE);
