<?php
/**
 * A telekocsi-szervező linkjének kiküldése a túra visszaigazolt résztvevőinek.
 * Egyetlen e-mail megy ki, minden résztvevő BCC-ben (nem látják egymás címét).
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
require_once __DIR__ . '/../includes/carpool.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/app-settings-schema.php';
require_once __DIR__ . '/../includes/email-log-schema.php';
requireAdminOrVezeto();
verifyCsrf();

$pdo = getDb();
ensureFutureToursSchema($pdo);
ensureCarpoolSchema($pdo);
ensureAppSettingsSchema($pdo);
ensureEmailLogSchema($pdo);

$tourId = (int)($_POST['tour_id'] ?? 0);
$back   = BASE_URL . '/admin/future-tour-applicants.php?id=' . $tourId . '#telekocsi';

$stmt = $pdo->prepare("SELECT id, name, start_date FROM future_tours WHERE id = ? LIMIT 1");
$stmt->execute([$tourId]);
$tour    = $stmt->fetch();
$carpool = $tour ? getCarpoolByTour($pdo, $tourId) : null;
if (!$carpool) {
    flash('error', 'Ehhez a túrához még nincs telekocsi-szervező.');
    header('Location: ' . $back);
    exit;
}

// Címzettek: visszaigazolt jelentkezők (tagok és vendégek), címenként egyszer
$stmt = $pdo->prepare("
    SELECT DISTINCT LOWER(TRIM(IF(fta.user_id IS NULL, fta.guest_email, u.email))) AS email
    FROM future_tour_applications fta
    LEFT JOIN users u ON u.id = fta.user_id
    WHERE fta.future_tour_id = ? AND fta.status = 'confirmed'
");
$stmt->execute([$tourId]);
$bcc = array_values(array_filter($stmt->fetchAll(PDO::FETCH_COLUMN),
    fn($m) => filter_var($m, FILTER_VALIDATE_EMAIL)));

if (!$bcc) {
    flash('error', 'Nincs visszaigazolt résztvevő érvényes e-mail címmel.');
    header('Location: ' . $back);
    exit;
}

// Válaszcím: a küldő szervező e-mail címe
$stmt = $pdo->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
$stmt->execute([getCurrentUserId()]);
$replyTo = (string)$stmt->fetchColumn();

$smtp    = getSmtpConfig($pdo);
$subject = 'Telekocsi – ' . $tour['name'];
$html    = carpoolMailHtml($tour, carpoolUrl($carpool), $subject);
$toEmail = $smtp['from_email'] ?: $smtp['user'];
$toName  = $smtp['from_name'];
$logTo   = $toEmail . ' (BCC: ' . count($bcc) . ' címzett)';

try {
    $response = (new SmtpMailer($smtp))->send($toEmail, $toName, $subject, $html, '', $replyTo, $bcc);
    logEmailEntry($pdo, null, $logTo, $toName, $subject, $html, 'carpool_link', 'sent', '', $response);
    $pdo->prepare("UPDATE carpools SET mailed_at = NOW() WHERE id = ?")->execute([(int)$carpool['id']]);
    flash('success', 'A telekocsi link kiküldve ' . count($bcc) . ' résztvevőnek.');
} catch (Throwable $ex) {
    logEmailEntry($pdo, null, $logTo, $toName, $subject, $html, 'carpool_link', 'failed', $ex->getMessage());
    flash('error', 'Az e-mail küldése nem sikerült: ' . $ex->getMessage());
}

header('Location: ' . $back);
exit;

function carpoolMailHtml(array $tour, string $url, string $subject): string {
    $name = htmlspecialchars($tour['name'], ENT_QUOTES);
    $date = $tour['start_date'] ? ' (' . htmlspecialchars(formatDate($tour['start_date']), ENT_QUOTES) . ')' : '';
    $link = htmlspecialchars($url, ENT_QUOTES);
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f5efe4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px;">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
  <tr><td style="background:#1a3d39;padding:24px 32px;">
    <h1 style="color:#c8a84b;margin:0;font-size:22px;">' . APP_NAME . '</h1>
    <p style="color:#a8c5c2;margin:6px 0 0;font-size:14px;">' . htmlspecialchars($subject, ENT_QUOTES) . '</p>
  </td></tr>
  <tr><td style="padding:28px 32px;">
    <p style="font-size:15px;margin:0 0 12px;">Szia!</p>
    <p style="font-size:14px;color:#444;line-height:1.7;margin:0 0 12px;">
      A(z) <strong>' . $name . '</strong>' . $date . ' túrára telekocsikkal utazunk. Ennek megszervezéséhez
      kérlek, töltsd ki az alábbi linken, hogy sofőrként helyet kínálsz, vagy utasként helyet foglalnál.
    </p>
    <div style="text-align:center;margin:22px 0;">
      <a href="' . $link . '" style="background:#29776F;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:14px;font-weight:600;">Telekocsi-szervező megnyitása</a>
    </div>
    <p style="font-size:12.5px;color:#666;word-break:break-all;margin:0 0 18px;">' . $link . '</p>
    <div style="background:#f5efe4;border-radius:6px;padding:14px 16px;font-size:13.5px;color:#444;line-height:1.7;">
      Ha utas lennél és épp nincs szabad hely, nézz vissza később — lehet, hogy egy sofőr még regisztrálni fog.<br>
      A belépéshez csak az e-mail címedet kell megadnod ezen a linken, nem szükséges teljesen bejelentkezned.
    </div>
    <div style="background:#fffbeb;border:1px solid #f59e0b;border-radius:6px;padding:14px 16px;margin-top:14px;font-size:13.5px;color:#b45309;line-height:1.7;">
      Ha mégsem vennél részt a túrán, kérlek, jelezd a szervezőnek!
    </div>
  </td></tr>
  <tr><td style="padding:16px 32px;background:#f5f5f5;text-align:center;font-size:12px;color:#999;">
    ' . APP_NAME . ' &bull; Automatikus értesítő
  </td></tr>
</table></td></tr></table></body></html>';
}
