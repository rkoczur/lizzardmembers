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
requireUser();
verifyCsrf();

$pdo = getDb();
ensureFutureToursSchema($pdo);
ensureAppSettingsSchema($pdo);
ensureEmailLogSchema($pdo);

$userId = getCurrentUserId();
$tourId = (int)($_POST['tour_id'] ?? 0);

if (!$tourId) {
    header('Location: ' . BASE_URL . '/user/future-tours.php');
    exit;
}

// Load tour
$tourStmt = $pdo->prepare("SELECT * FROM future_tours WHERE id = ? AND status = 'open' LIMIT 1");
$tourStmt->execute([$tourId]);
$tour = $tourStmt->fetch();
if (!$tour) {
    flash('error', 'Ez a túra nem érhető el vagy a jelentkezés lezárult.');
    header('Location: ' . BASE_URL . '/user/future-tours.php');
    exit;
}

// Check if already applied
$existingStmt = $pdo->prepare("SELECT id, status FROM future_tour_applications WHERE future_tour_id = ? AND user_id = ? LIMIT 1");
$existingStmt->execute([$tourId, $userId]);
$existing = $existingStmt->fetch();
if ($existing && $existing['status'] !== 'cancelled') {
    flash('error', 'Már jelentkeztél erre a túrára.');
    header('Location: ' . BASE_URL . '/user/future-tour-detail.php?id=' . $tourId);
    exit;
}

// Count confirmed spots
$confirmedStmt = $pdo->prepare("SELECT COUNT(*) FROM future_tour_applications WHERE future_tour_id = ? AND status = 'confirmed'");
$confirmedStmt->execute([$tourId]);
$confirmedCount = (int)$confirmedStmt->fetchColumn();

$appStatus = $confirmedCount >= (int)$tour['max_attendees'] ? 'waitlist' : 'confirmed';

// Parse form fields
$carAvailable  = isset($_POST['car_available']) && $_POST['car_available'] === '1' ? 1 : 0;
$passengers    = $carAvailable ? max(0, (int)($_POST['passengers'] ?? 0)) : 0;
$sharingRoom   = in_array($_POST['sharing_room'] ?? '', ['same_gender','yes','no']) ? $_POST['sharing_room'] : 'same_gender';
$notes         = trim($_POST['notes'] ?? '') ?: null;
$departureCity = trim($_POST['departure_city'] ?? '') ?: null;

if ($existing && $existing['status'] === 'cancelled') {
    // Re-apply
    $pdo->prepare("UPDATE future_tour_applications SET status=?, car_available=?, passengers=?, sharing_room=?, notes=?, departure_city=?, paid_at=NULL, applied_at=NOW() WHERE id=?")
        ->execute([$appStatus, $carAvailable, $passengers, $sharingRoom, $notes, $departureCity, $existing['id']]);
    $appId = $existing['id'];
    // Clear old answers
    $pdo->prepare("DELETE FROM future_tour_application_answers WHERE application_id = ?")->execute([$appId]);
} else {
    $pdo->prepare("INSERT INTO future_tour_applications (future_tour_id, user_id, status, car_available, passengers, sharing_room, notes, departure_city) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$tourId, $userId, $appStatus, $carAvailable, $passengers, $sharingRoom, $notes, $departureCity]);
    $appId = (int)$pdo->lastInsertId();
}

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
$userStmt = $pdo->prepare("SELECT firstname, lastname, email FROM users WHERE id = ? LIMIT 1");
$userStmt->execute([$userId]);
$user = $userStmt->fetch();

$smtp = getSmtpConfig($pdo);

// Build absolute URL for email links (BASE_URL is path-only)
$proto       = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$absBaseUrl  = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL;

// Email to applicant
$tourDate   = $tour['start_date'] ? formatDate($tour['start_date']) : '—';
$tourUrl    = $absBaseUrl . '/user/future-tour-detail.php?id=' . $tourId;
$fullName   = ($user['lastname'] ?? '') . ' ' . ($user['firstname'] ?? '');

if ($appStatus === 'confirmed') {
    $subject     = 'Sikeres jelentkezés – ' . $tour['name'];
    $statusText  = '<strong style="color:#29776F;">Sikeresen jelentkeztél – a helyedet fenntartjuk.</strong>';
    $paymentText = (float)($tour['participation_fee'] ?? 0) > 0
      ? '<div style="background:#fffbeb;border:1px solid #f59e0b;border-radius:6px;padding:12px 16px;margin-top:16px;font-size:13.5px;color:#b45309;">
        ⚠ A részvételi díjat kérjük, <strong>14 napon belül</strong> utald el — eddig a helyedet fenntartjuk. Ha a befizetés a határidőig nem érkezik meg, a helyedet a várólistán következő jelentkező kapja meg.
      </div>'
      : '';
} else {
    $subject     = 'Várólistára kerültél – ' . $tour['name'];
    $statusText  = '<strong style="color:#b45309;">Státusz: Várólistán</strong>';
    $paymentText = '<p style="color:#666;font-size:13.5px;">Ha felszabadul egy hely, értesítést kapsz, és akkor kell a részvételi díjat befizetni.</p>';
}

$applicantHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f5efe4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px;">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
  <tr><td style="background:#1a3d39;padding:24px 32px;">
    <h1 style="color:#c8a84b;margin:0;font-size:22px;">' . APP_NAME . '</h1>
    <p style="color:#a8c5c2;margin:6px 0 0;font-size:14px;">' . htmlspecialchars($subject, ENT_QUOTES) . '</p>
  </td></tr>
  <tr><td style="padding:28px 32px;">
    <p style="font-size:15px;">Kedves ' . htmlspecialchars($fullName, ENT_QUOTES) . '!</p>
    <p style="font-size:14px;color:#555;">Jelentkezésed megérkezett a következő túrára:</p>
    <table width="100%" style="background:#f5efe4;border-radius:8px;padding:16px;margin:16px 0;font-size:14px;">
      <tr><td style="padding:4px 0;color:#666;">Túra neve:</td><td style="font-weight:600;">' . htmlspecialchars($tour['name'], ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">Kezdés:</td><td>' . htmlspecialchars($tourDate, ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">Napok:</td><td>' . (int)$tour['num_days'] . ' nap</td></tr>
    </table>
    ' . $statusText . $paymentText . '
    <div style="text-align:center;margin-top:24px;">
      <a href="' . $tourUrl . '" style="background:#29776F;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:14px;font-weight:600;">Túra megtekintése</a>
    </div>
  </td></tr>
  <tr><td style="padding:16px 32px;background:#f5f5f5;text-align:center;font-size:12px;color:#999;">
    ' . APP_NAME . ' &bull; Automatikus értesítő
  </td></tr>
</table></td></tr></table></body></html>';

// A megerősített (helyet kapott) jelentkező csak az admin „Jelentkezés elfogadása” gombja után
// kap e-mailt. A várólistás jelentkező továbbra is automatikusan értesül.
if ($appStatus === 'waitlist') {
    try {
        $mailer = new SmtpMailer($smtp);
        $mailer->send($user['email'], $fullName, $subject, $applicantHtml);
        logEmailEntry($pdo, $userId, $user['email'], $fullName, $subject, $applicantHtml, 'future_tour_application', 'sent');
    } catch (Throwable $e) {
        logEmailEntry($pdo, $userId, $user['email'], $fullName, $subject, $applicantHtml, 'future_tour_application', 'failed', $e->getMessage());
    }
}

// Email to all active admins
$admins = $pdo->query("SELECT id, email, firstname, lastname FROM users WHERE role = 'admin' AND active = 1")->fetchAll();
$adminSubject = 'Új túrajelentkezés – ' . $tour['name'];
$adminDetailUrl = $absBaseUrl . '/admin/future-tour-detail.php?id=' . $tourId;
$adminHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f5efe4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px;">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
  <tr><td style="background:#1a3d39;padding:24px 32px;">
    <h1 style="color:#c8a84b;margin:0;font-size:22px;">' . APP_NAME . '</h1>
    <p style="color:#a8c5c2;margin:6px 0 0;font-size:14px;">Új túrajelentkezés érkezett</p>
  </td></tr>
  <tr><td style="padding:28px 32px;">
    <p style="font-size:15px;">Új jelentkezés érkezett a <strong>' . htmlspecialchars($tour['name'], ENT_QUOTES) . '</strong> túrára:</p>
    <table width="100%" style="background:#f5efe4;border-radius:8px;padding:16px;margin:16px 0;font-size:14px;">
      <tr><td style="padding:4px 0;color:#666;">Jelentkező:</td><td style="font-weight:600;">' . htmlspecialchars($fullName, ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">E-mail:</td><td>' . htmlspecialchars($user['email'], ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">Státusz:</td><td>' . ($appStatus === 'confirmed' ? 'Megerősített' : 'Várólistán') . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">Időpont:</td><td>' . date('Y.m.d H:i') . '</td></tr>
    </table>
    <div style="text-align:center;margin-top:24px;">
      <a href="' . $adminDetailUrl . '" style="background:#29776F;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:14px;font-weight:600;">Megtekintés adminfelületen</a>
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
        logEmailEntry($pdo, $admin['id'], $admin['email'], $adminName, $adminSubject, $adminHtml, 'future_tour_new_application_admin', 'sent');
    } catch (Throwable $e) {
        logEmailEntry($pdo, $admin['id'], $admin['email'], $adminName, $adminSubject, $adminHtml, 'future_tour_new_application_admin', 'failed', $e->getMessage());
    }
}

if ($appStatus === 'confirmed') {
    flash('success', 'Sikeresen jelentkeztél a túrára! A szervező jóváhagyása után e-mailben értesítünk.');
} else {
    flash('success', 'Feliratkoztál a várólistára! Ha felszabadul egy hely, értesítünk.');
}

// Ha publikus oldalról érkezett (pl. WP plugin iframe), visszairányítjuk a done állapotra
if (!empty($_POST['public_redirect'])) {
    header('Location: ' . BASE_URL . '/public/tour-apply.php?id=' . $tourId . '&done');
} else {
    header('Location: ' . BASE_URL . '/user/future-tour-detail.php?id=' . $tourId);
}
exit;
