<?php
/**
 * „Jelentkezés elfogadása” — az admin/szervező megerősíti a (megerősített, helyet kapott) tag
 * jelentkezését, és ekkor kapja meg a jelentkező a visszaigazoló e-mailt.
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/app-settings-schema.php';
require_once __DIR__ . '/../includes/email-log-schema.php';
requireAdminOrVezeto();
verifyCsrf();

$pdo = getDb();
ensureFutureToursSchema($pdo);
ensureAppSettingsSchema($pdo);
ensureEmailLogSchema($pdo);

$appId  = (int)($_POST['application_id'] ?? 0);
$tourId = (int)($_POST['tour_id'] ?? 0);
$backUrl = BASE_URL . '/admin/future-tour-applicants.php?id=' . $tourId;

if (!$appId || !$tourId) {
    flash('error', 'Hiányos adatok.');
    header('Location: ' . $backUrl);
    exit;
}

$appStmt = $pdo->prepare("
    SELECT fta.*, u.firstname, u.lastname, u.email, COALESCE(u.level,1) AS user_level, COALESCE(u.role,'user') AS user_role,
           ft.name AS tour_name, ft.start_date, ft.num_days, ft.participation_fee
    FROM future_tour_applications fta
    JOIN future_tours ft ON ft.id = fta.future_tour_id
    JOIN users u ON u.id = fta.user_id
    WHERE fta.id = ? AND fta.future_tour_id = ? AND fta.status = 'confirmed' AND fta.user_id IS NOT NULL
    LIMIT 1
");
$appStmt->execute([$appId, $tourId]);
$app = $appStmt->fetch();

if (!$app) {
    flash('error', 'A jelentkezés nem található vagy nem fogadható el (csak helyet kapott tag jelentkezése fogadható el).');
    header('Location: ' . $backUrl);
    exit;
}

// Elfogadás rögzítése
$pdo->prepare("UPDATE future_tour_applications SET accepted_at = NOW() WHERE id = ?")->execute([$appId]);

// Effektív részvételi díj (tagi kedvezménnyel)
$fee     = (float)($app['participation_fee'] ?? 0);
$discount = $fee > 0 ? getTourFeeDiscount((int)$app['user_level'], (string)$app['user_role']) : 0;
$effFee  = $fee * (1 - $discount / 100);

$smtp       = getSmtpConfig($pdo);
$proto      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$absBaseUrl = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL;
$tourDate   = $app['start_date'] ? formatDate($app['start_date']) : '—';
$tourUrl    = $absBaseUrl . '/user/future-tour-detail.php?id=' . $tourId;
$fullName   = trim($app['lastname'] . ' ' . $app['firstname']);
$subject    = 'Jelentkezésed elfogadva – ' . $app['tour_name'];

$payHtml = '';
if ($fee > 0) {
    $payHtml = '<div style="background:#fffbeb;border:1px solid #f59e0b;border-radius:6px;padding:14px 16px;margin-top:18px;font-size:13.5px;color:#b45309;line-height:1.7;">
        A részvételi díj <strong>' . number_format($effFee, 0, ',', '&nbsp;') . '&nbsp;Ft</strong>'
        . ($discount > 0 ? ' <span style="color:#92400e;">(' . $discount . '% tagi kedvezménnyel)</span>' : '') . '.<br>
        Kérünk, ezt <strong>14 napon belül</strong> utald el — eddig a helyedet fenntartjuk neked.
        Ha a befizetés a határidőig nem érkezik meg, a helyedet sajnos a várólistán következő jelentkezőnek kell továbbadnunk.
      </div>';
}

$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f5efe4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px;">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
  <tr><td style="background:#1a3d39;padding:24px 32px;">
    <h1 style="color:#c8a84b;margin:0;font-size:22px;">' . APP_NAME . '</h1>
    <p style="color:#a8c5c2;margin:6px 0 0;font-size:14px;">' . htmlspecialchars($subject, ENT_QUOTES) . '</p>
  </td></tr>
  <tr><td style="padding:28px 32px;">
    <p style="font-size:15px;margin:0 0 4px;">Kedves ' . htmlspecialchars($app['firstname'], ENT_QUOTES) . '!</p>
    <p style="font-size:14px;color:#444;line-height:1.7;margin:0 0 4px;">
      Jó hírünk van: a túra szervezője <strong>elfogadta a jelentkezésed</strong>, így a helyedet fenntartjuk.
      Nagyon örülünk, hogy velünk tartasz!
    </p>
    <table width="100%" style="background:#f5efe4;border-radius:8px;padding:16px;margin:16px 0;font-size:14px;">
      <tr><td style="padding:4px 0;color:#666;">Túra neve:</td><td style="font-weight:600;">' . htmlspecialchars($app['tour_name'], ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">Kezdés:</td><td>' . htmlspecialchars($tourDate, ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">Időtartam:</td><td>' . (int)$app['num_days'] . ' nap</td></tr>
    </table>
    ' . $payHtml . '
    <div style="text-align:center;margin-top:24px;">
      <a href="' . $tourUrl . '" style="background:#29776F;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:14px;font-weight:600;">Túra részletei</a>
    </div>
    <p style="font-size:13px;color:#666;line-height:1.7;margin:22px 0 0;">Ha bármi kérdésed van, csak válaszolj erre az e-mailre. Találkozunk a túrán!</p>
  </td></tr>
  <tr><td style="padding:16px 32px;background:#f5f5f5;text-align:center;font-size:12px;color:#999;">
    ' . APP_NAME . ' &bull; Automatikus értesítő
  </td></tr>
</table></td></tr></table></body></html>';

try {
    (new SmtpMailer($smtp))->send($app['email'], $fullName, $subject, $html);
    logEmailEntry($pdo, (int)$app['user_id'], $app['email'], $fullName, $subject, $html, 'future_tour_accepted', 'sent');
    flash('success', e($fullName) . ' jelentkezése elfogadva, az értesítő e-mail elküldve.');
} catch (Throwable $e) {
    logEmailEntry($pdo, (int)$app['user_id'], $app['email'], $fullName, $subject, $html, 'future_tour_accepted', 'failed', $e->getMessage());
    flash('error', 'A jelentkezés elfogadva, de az e-mail küldése nem sikerült: ' . $e->getMessage());
}

header('Location: ' . $backUrl);
exit;
