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
requireAdmin();
verifyCsrf();

$pdo = getDb();
ensureFutureToursSchema($pdo);
ensureAppSettingsSchema($pdo);
ensureEmailLogSchema($pdo);

$appId  = (int)($_POST['application_id'] ?? 0);
$tourId = (int)($_POST['tour_id']        ?? 0);

if (!$appId || !$tourId) {
    flash('error', 'Hiányos adatok.');
    header('Location: ' . BASE_URL . '/admin/future-tour-detail.php?id=' . $tourId);
    exit;
}

$appStmt = $pdo->prepare("
    SELECT fta.*, ft.name AS tour_name, ft.start_date, ft.num_days, ft.max_attendees, ft.participation_fee
    FROM future_tour_applications fta
    JOIN future_tours ft ON ft.id = fta.future_tour_id
    WHERE fta.id = ? AND fta.future_tour_id = ? AND fta.status = 'pending' AND fta.user_id IS NULL
    LIMIT 1
");
$appStmt->execute([$appId, $tourId]);
$app = $appStmt->fetch();

if (!$app) {
    flash('error', 'A jelentkezés nem található vagy nem váró állapotban van.');
    header('Location: ' . BASE_URL . '/admin/future-tour-detail.php?id=' . $tourId);
    exit;
}

// Check capacity
$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM future_tour_applications WHERE future_tour_id = ? AND status = 'confirmed'");
$cntStmt->execute([$tourId]);
$confirmedCount = (int)$cntStmt->fetchColumn();
$newStatus = $confirmedCount < (int)$app['max_attendees'] ? 'confirmed' : 'waitlist';

$pdo->prepare("UPDATE future_tour_applications SET status = ? WHERE id = ?")
    ->execute([$newStatus, $appId]);

// Email to guest
$smtp       = getSmtpConfig($pdo);
$proto      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$absBaseUrl = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL;
$tourDate   = $app['start_date'] ? formatDate($app['start_date']) : '—';
$guestName  = $app['guest_name'];
$guestEmail = $app['guest_email'];

if ($newStatus === 'confirmed') {
    $subject    = 'Részvételed megerősítve – ' . $app['tour_name'];
    $statusHtml = '<strong style="color:#29776F;">Státusz: Megerősített</strong>';
    $noteHtml   = (float)($app['participation_fee'] ?? 0) > 0
      ? '<div style="background:#fffbeb;border:1px solid #f59e0b;border-radius:6px;padding:12px 16px;margin-top:16px;font-size:13.5px;color:#b45309;">
        ⚠ Kérjük, a részvételi díjat <strong>14 napon belül</strong> utald el. Ellenkező esetben a rendszer automatikusan törli a foglalásodat.
      </div>'
      : '';
} else {
    $subject    = 'Várólistán vagy – ' . $app['tour_name'];
    $statusHtml = '<strong style="color:#b45309;">Státusz: Várólistán</strong>';
    $noteHtml   = '<p style="color:#666;font-size:13.5px;">A túra jelenleg betelt, de várólistán vagy. Ha felszabadul egy hely, értesítünk.</p>';
}

$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f5efe4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px;">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
  <tr><td style="background:#1a3d39;padding:24px 32px;">
    <h1 style="color:#c8a84b;margin:0;font-size:22px;">' . APP_NAME . '</h1>
    <p style="color:#a8c5c2;margin:6px 0 0;font-size:14px;">' . htmlspecialchars($subject, ENT_QUOTES) . '</p>
  </td></tr>
  <tr><td style="padding:28px 32px;">
    <p style="font-size:15px;">Kedves ' . htmlspecialchars($guestName, ENT_QUOTES) . '!</p>
    <p style="font-size:14px;color:#555;">Jelentkezésed az alábbi túrára jóváhagyásra került:</p>
    <table width="100%" style="background:#f5efe4;border-radius:8px;padding:16px;margin:16px 0;font-size:14px;">
      <tr><td style="padding:4px 0;color:#666;">Túra neve:</td><td style="font-weight:600;">' . htmlspecialchars($app['tour_name'], ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">Kezdés:</td><td>' . htmlspecialchars($tourDate, ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">Időtartam:</td><td>' . (int)$app['num_days'] . ' nap</td></tr>
    </table>
    ' . $statusHtml . $noteHtml . '
  </td></tr>
  <tr><td style="padding:16px 32px;background:#f5f5f5;text-align:center;font-size:12px;color:#999;">
    ' . APP_NAME . ' &bull; Automatikus értesítő
  </td></tr>
</table></td></tr></table></body></html>';

try {
    $mailer = new SmtpMailer($smtp);
    $mailer->send($guestEmail, $guestName, $subject, $html);
    logEmailEntry($pdo, null, $guestEmail, $guestName, $subject, $html, 'future_tour_guest_approved', 'sent');
} catch (Throwable $e) {
    logEmailEntry($pdo, null, $guestEmail, $guestName, $subject, $html, 'future_tour_guest_approved', 'failed', $e->getMessage());
}

$label = $newStatus === 'confirmed' ? 'megerősítve' : 'várólistára helyezve';
flash('success', e($guestName) . ' jelentkezése ' . $label . '.');
header('Location: ' . BASE_URL . '/admin/future-tour-detail.php?id=' . $tourId);
exit;
