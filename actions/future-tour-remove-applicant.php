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
    header('Location: ' . BASE_URL . '/admin/future-tours.php');
    exit;
}

$appStmt = $pdo->prepare("SELECT * FROM future_tour_applications WHERE id = ? AND future_tour_id = ? LIMIT 1");
$appStmt->execute([$appId, $tourId]);
$app = $appStmt->fetch();

if (!$app) {
    flash('error', 'Nem található a jelentkező.');
    header('Location: ' . BASE_URL . '/admin/future-tour-detail.php?id=' . $tourId);
    exit;
}

$wasConfirmed = $app['status'] === 'confirmed';

$pdo->prepare("UPDATE future_tour_applications SET status = 'cancelled' WHERE id = ?")->execute([$appId]);

// Promote first waitlist entry if a confirmed spot was freed
if ($wasConfirmed) {
    $waitlistStmt = $pdo->prepare("SELECT fta.*, u.email, u.firstname, u.lastname FROM future_tour_applications fta JOIN users u ON u.id = fta.user_id WHERE fta.future_tour_id = ? AND fta.status = 'waitlist' ORDER BY fta.applied_at ASC LIMIT 1");
    $waitlistStmt->execute([$tourId]);
    $next = $waitlistStmt->fetch();
    if ($next) {
        $pdo->prepare("UPDATE future_tour_applications SET status = 'confirmed' WHERE id = ?")->execute([$next['id']]);

        // Notify promoted member
        try {
            $smtp    = getSmtpConfig($pdo);
            $tourRow = $pdo->prepare("SELECT name FROM future_tours WHERE id = ? LIMIT 1");
            $tourRow->execute([$tourId]);
            $tourName = $tourRow->fetchColumn() ?: '';
            $fullName = $next['lastname'] . ' ' . $next['firstname'];
            $subject  = 'Hely felszabadult – ' . $tourName;
            $proto    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $tourUrl  = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL . '/user/future-tour-detail.php?id=' . $tourId;
            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f5efe4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px;">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;">
  <tr><td style="background:#1a3d39;padding:24px 32px;">
    <h1 style="color:#c8a84b;margin:0;font-size:22px;">' . APP_NAME . '</h1>
    <p style="color:#a8c5c2;margin:6px 0 0;">Hely felszabadult!</p>
  </td></tr>
  <tr><td style="padding:28px 32px;">
    <p>Kedves ' . htmlspecialchars($fullName, ENT_QUOTES) . '!</p>
    <p>Felszabadult egy hely a <strong>' . htmlspecialchars($tourName, ENT_QUOTES) . '</strong> túrán. Jelentkezésed megerősítésre került.</p>
    <div style="background:#fffbeb;border:1px solid #f59e0b;border-radius:6px;padding:12px 16px;margin:16px 0;font-size:13.5px;color:#b45309;">
      ⚠ Kérjük, a részvételi díjat <strong>14 napon belül</strong> utald el, különben a foglalásod automatikusan feloldásra kerül.
    </div>
    <div style="text-align:center;margin-top:24px;">
      <a href="' . $tourUrl . '" style="background:#29776F;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:14px;font-weight:600;">Túra megtekintése</a>
    </div>
  </td></tr>
</table></td></tr></table></body></html>';

            $mailer = new SmtpMailer($smtp);
            $mailer->send($next['email'], $fullName, $subject, $html);
            logEmailEntry($pdo, (int)$next['user_id'], $next['email'], $fullName, $subject, $html, 'future_tour_waitlist_promoted', 'sent');
        } catch (Throwable) {}
    }
}

flash('success', 'Jelentkező sikeresen eltávolítva.');
header('Location: ' . BASE_URL . '/admin/future-tour-detail.php?id=' . $tourId);
exit;
