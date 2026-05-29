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
    SELECT fta.*, ft.name AS tour_name, ft.start_date, ft.num_days
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

$pdo->prepare("UPDATE future_tour_applications SET status = 'cancelled' WHERE id = ?")
    ->execute([$appId]);

// Email to guest
$smtp       = getSmtpConfig($pdo);
$guestName  = $app['guest_name'];
$guestEmail = $app['guest_email'];
$tourDate   = $app['start_date'] ? formatDate($app['start_date']) : '—';
$subject    = 'Jelentkezésed nem került elfogadásra – ' . $app['tour_name'];

$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f5efe4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px;">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
  <tr><td style="background:#1a3d39;padding:24px 32px;">
    <h1 style="color:#c8a84b;margin:0;font-size:22px;">' . APP_NAME . '</h1>
    <p style="color:#a8c5c2;margin:6px 0 0;font-size:14px;">' . htmlspecialchars($subject, ENT_QUOTES) . '</p>
  </td></tr>
  <tr><td style="padding:28px 32px;">
    <p style="font-size:15px;">Kedves ' . htmlspecialchars($guestName, ENT_QUOTES) . '!</p>
    <p style="font-size:14px;color:#555;">Sajnáljuk, de a következő túrára küldött jelentkezésedet nem tudtuk elfogadni:</p>
    <table width="100%" style="background:#f5efe4;border-radius:8px;padding:16px;margin:16px 0;font-size:14px;">
      <tr><td style="padding:4px 0;color:#666;">Túra neve:</td><td style="font-weight:600;">' . htmlspecialchars($app['tour_name'], ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">Kezdés:</td><td>' . htmlspecialchars($tourDate, ENT_QUOTES) . '</td></tr>
    </table>
    <p style="font-size:14px;color:#555;">Ha kérdésed van, kérjük, vedd fel velünk a kapcsolatot.</p>
  </td></tr>
  <tr><td style="padding:16px 32px;background:#f5f5f5;text-align:center;font-size:12px;color:#999;">
    ' . APP_NAME . ' &bull; Automatikus értesítő
  </td></tr>
</table></td></tr></table></body></html>';

try {
    $mailer = new SmtpMailer($smtp);
    $mailer->send($guestEmail, $guestName, $subject, $html);
    logEmailEntry($pdo, null, $guestEmail, $guestName, $subject, $html, 'future_tour_guest_rejected', 'sent');
} catch (Throwable $e) {
    logEmailEntry($pdo, null, $guestEmail, $guestName, $subject, $html, 'future_tour_guest_rejected', 'failed', $e->getMessage());
}

flash('success', e($guestName) . ' jelentkezése elutasítva.');
header('Location: ' . BASE_URL . '/admin/future-tour-detail.php?id=' . $tourId);
exit;
