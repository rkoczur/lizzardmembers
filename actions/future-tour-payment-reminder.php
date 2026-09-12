<?php
/**
 * Fizetési emlékeztető küldése azoknak a jelentkezőknek, akik még nem fizettek.
 * application_id megadásával egy jelentkezőnek, nélküle az összes nem fizető jelentkezőnek.
 * Ha a túrán van várólistás jelentkező, az e-mail 1 hetes határidőt és helyátadást is közöl.
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

$tourId  = (int)($_POST['tour_id'] ?? 0);
$appId   = (int)($_POST['application_id'] ?? 0);
$backUrl = BASE_URL . '/admin/future-tour-applicants.php?id=' . $tourId;

if (!$tourId) {
    flash('error', 'Hiányos adatok.');
    header('Location: ' . BASE_URL . '/admin/future-tours.php');
    exit;
}

$tourStmt = $pdo->prepare("SELECT id, name, start_date, num_days, participation_fee FROM future_tours WHERE id = ? LIMIT 1");
$tourStmt->execute([$tourId]);
$tour = $tourStmt->fetch();

if (!$tour) {
    flash('error', 'A túra nem található.');
    header('Location: ' . BASE_URL . '/admin/future-tours.php');
    exit;
}

$fee = (float)($tour['participation_fee'] ?? 0);
if ($fee <= 0) {
    flash('error', 'Ehhez a túrához nincs részvételi díj, így nincs mire emlékeztetni.');
    header('Location: ' . $backUrl);
    exit;
}

// Címzettek: helyet kapott, még nem fizetett jelentkezők (tagok és vendégek egyaránt)
$sql = "
    SELECT fta.id, fta.user_id, fta.guest_name, fta.guest_email, fta.fee_override,
           u.firstname, u.lastname, u.email,
           COALESCE(u.level, 1) AS user_level, COALESCE(u.role, 'user') AS user_role
    FROM future_tour_applications fta
    LEFT JOIN users u ON u.id = fta.user_id
    WHERE fta.future_tour_id = ? AND fta.status = 'confirmed' AND fta.paid_at IS NULL
";
$params = [$tourId];
if ($appId) {
    $sql .= " AND fta.id = ?";
    $params[] = $appId;
}
$sql .= " ORDER BY fta.applied_at ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$recipients = $stmt->fetchAll();

if (empty($recipients)) {
    flash('error', 'Nincs olyan jelentkező, akinek emlékeztetőt kellene küldeni.');
    header('Location: ' . $backUrl);
    exit;
}

// Van-e várólistás jelentkező? Ha igen, az e-mail 1 hetes határidőt közöl.
$waitStmt = $pdo->prepare("SELECT COUNT(*) FROM future_tour_applications WHERE future_tour_id = ? AND status = 'waitlist'");
$waitStmt->execute([$tourId]);
$waitlistCount = (int)$waitStmt->fetchColumn();

// Határidő: 1 hét — de nem későbbi, mint a túra kezdete (múltbeli kezdésnél marad az 1 hét)
$deadline = new DateTime('+7 days');
if (!empty($tour['start_date'])) {
    $startDate = new DateTime($tour['start_date']);
    if ($startDate > new DateTime('today') && $startDate < $deadline) $deadline = $startDate;
}
$deadlineText = $deadline->format('Y.m.d');

$smtp       = getSmtpConfig($pdo);
$proto      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$absBaseUrl = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL;
$tourUrl    = $absBaseUrl . '/user/future-tour-detail.php?id=' . $tourId;
$tourDate   = $tour['start_date'] ? formatDate($tour['start_date']) : '—';
$subject    = 'Fizetési emlékeztető – ' . $tour['name'];
$mailer     = new SmtpMailer($smtp);

$sent = 0;
$failed = 0;
$updateStmt = $pdo->prepare("UPDATE future_tour_applications SET payment_reminder_at = NOW() WHERE id = ?");

foreach ($recipients as $r) {
    $email = $r['user_id'] ? (string)$r['email'] : (string)$r['guest_email'];
    if (!$email) { $failed++; continue; }

    $fullName  = $r['user_id'] ? trim($r['lastname'] . ' ' . $r['firstname']) : (string)$r['guest_name'];
    $firstName = $r['user_id'] ? (string)$r['firstname'] : (string)$r['guest_name'];
    $discount  = $r['user_id'] ? getTourFeeDiscount((int)$r['user_level'], (string)$r['user_role']) : 0;
    $effFee    = getApplicationFee($fee, $discount, $r['fee_override']);

    $waitlistHtml = '';
    if ($waitlistCount > 0) {
        $waitlistHtml = '<div style="background:#fef2f2;border:1px solid #dc2626;border-radius:6px;padding:14px 16px;margin-top:14px;font-size:13.5px;color:#b91c1c;line-height:1.7;">
            Erre a túrára már <strong>várólistás jelentkező</strong> is van. Kérünk, a részvételi díjat
            <strong>1 héten belül, ' . $deadlineText . '-ig</strong> utald el.
            Ha a befizetés eddig nem érkezik meg, a helyedet a várólistán következő jelentkező kapja meg.
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
    <p style="font-size:15px;margin:0 0 4px;">Kedves ' . htmlspecialchars($firstName, ENT_QUOTES) . '!</p>
    <p style="font-size:14px;color:#444;line-height:1.7;margin:0 0 4px;">
      Emlékeztetni szeretnénk, hogy az alábbi túra részvételi díja még nem érkezett meg hozzánk.
    </p>
    <table width="100%" style="background:#f5efe4;border-radius:8px;padding:16px;margin:16px 0;font-size:14px;">
      <tr><td style="padding:4px 0;color:#666;">Túra neve:</td><td style="font-weight:600;">' . htmlspecialchars($tour['name'], ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">Kezdés:</td><td>' . htmlspecialchars($tourDate, ENT_QUOTES) . '</td></tr>
      <tr><td style="padding:4px 0;color:#666;">Időtartam:</td><td>' . (int)$tour['num_days'] . ' nap</td></tr>
    </table>
    <div style="background:#fffbeb;border:1px solid #f59e0b;border-radius:6px;padding:14px 16px;font-size:13.5px;color:#b45309;line-height:1.7;">
      A fizetendő részvételi díj <strong>' . number_format($effFee, 0, ',', '&nbsp;') . '&nbsp;Ft</strong>'
      . ($discount > 0 ? ' <span style="color:#92400e;">(' . $discount . '% tagi kedvezménnyel)</span>' : '') . '.
    </div>
    ' . $waitlistHtml . bankInfoEmailHtml() . '
    <div style="text-align:center;margin-top:24px;">
      <a href="' . $tourUrl . '" style="background:#29776F;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:14px;font-weight:600;">Túra részletei</a>
    </div>
    <p style="font-size:13px;color:#666;line-height:1.7;margin:22px 0 0;">
      Ha már elutaltad a díjat, vagy bármi kérdésed van, csak válaszolj erre az e-mailre.
    </p>
  </td></tr>
  <tr><td style="padding:16px 32px;background:#f5f5f5;text-align:center;font-size:12px;color:#999;">
    ' . APP_NAME . ' &bull; Automatikus értesítő
  </td></tr>
</table></td></tr></table></body></html>';

    try {
        $mailer->send($email, $fullName, $subject, $html);
        logEmailEntry($pdo, $r['user_id'] ? (int)$r['user_id'] : null, $email, $fullName, $subject, $html, 'future_tour_payment_reminder', 'sent');
        $updateStmt->execute([(int)$r['id']]);
        $sent++;
    } catch (Throwable $e) {
        logEmailEntry($pdo, $r['user_id'] ? (int)$r['user_id'] : null, $email, $fullName, $subject, $html, 'future_tour_payment_reminder', 'failed', $e->getMessage());
        $failed++;
    }
}

if ($sent > 0) {
    flash('success', 'Fizetési emlékeztető elküldve ' . $sent . ' jelentkezőnek.' . ($failed > 0 ? ' ' . $failed . ' e-mail küldése nem sikerült.' : ''));
} else {
    flash('error', 'Egyetlen fizetési emlékeztetőt sem sikerült elküldeni.');
}

header('Location: ' . $backUrl);
exit;
