<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tours-schema.php';
require_once __DIR__ . '/../includes/app-settings-schema.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/tour-notification-email.php';
require_once __DIR__ . '/../includes/email-log-schema.php';
requireLeader();
if (!canManageTours()) {
    flash('error', 'Nincs jogosultságod ehhez a művelethez.');
    header('Location: ' . BASE_URL . '/admin/tours.php');
    exit;
}

verifyCsrf();

$pdo = getDb();

$tourId = (int)($_POST['tour_id'] ?? 0);

$tourStmt = $pdo->prepare("SELECT * FROM tours WHERE id = ? AND status = 'pending' LIMIT 1");
$tourStmt->execute([$tourId]);
$tour = $tourStmt->fetch();

if (!$tour) {
    flash('error', 'A túra nem található vagy már jóvá lett hagyva.');
    header('Location: ' . BASE_URL . '/admin/tours.php');
    exit;
}

// Generate tour code and recalculate mtsz_points
$tourCode   = generateTourCode($pdo, $tour['tour_type']);

$tourData = [
    'tour_type'            => $tour['tour_type'],
    'sub_type'             => $tour['sub_type'],
    'days'                 => (int)$tour['days'],
    'total_km'             => $tour['total_km'],
    'total_elevation'      => $tour['total_elevation'],
    'alpine_km'            => $tour['alpine_km'],
    'alpine_elevation'     => $tour['alpine_elevation'],
    'tour_hours'           => $tour['tour_hours'],
    'multi_day_type'       => $tour['multi_day_type'],
    'accommodation'        => $tour['accommodation'],
    'camping_nights_fixed' => (int)$tour['camping_nights_fixed'],
    'camping_nights_mobile'=> (int)$tour['camping_nights_mobile'],
    'boat_portages'        => (int)$tour['boat_portages'],
    'tour_date'            => $tour['tour_date'],
];
$mtszPoints = calculateTourPoints($tourData);
// Admin kézi MTSZ-felülírás megőrzése jóváhagyáskor is
$mtszOverrideVal = $tour['mtsz_points_override'] ?? null;
if ($mtszOverrideVal !== null && $mtszOverrideVal !== '') {
    $mtszPoints = (int)$mtszOverrideVal;
}

$pdo->prepare("UPDATE tours SET status = 'approved', tour_code = ?, mtsz_points = ? WHERE id = ?")
    ->execute([$tourCode, $mtszPoints, $tourId]);

// Load tour members
$memberStmt = $pdo->prepare("SELECT user_id FROM tour_members WHERE tour_id = ?");
$memberStmt->execute([$tourId]);
$memberIds = $memberStmt->fetchAll(PDO::FETCH_COLUMN);

// Capture old stats for level-up detection
$statsOld = [];
if ($memberIds) {
    $ph   = rtrim(str_repeat('?,', count($memberIds)), ',');
    $sOld = $pdo->prepare("SELECT id, level, points FROM users WHERE id IN ($ph)");
    $sOld->execute(array_values($memberIds));
    foreach ($sOld->fetchAll() as $r) { $statsOld[$r['id']] = $r; }
}

recalcUserStats($pdo);

// Send tour notification emails
if ($memberIds) {
    try {
        ensureAppSettingsSchema($pdo);
        ensureEmailLogSchema($pdo);
        $smtp = getSmtpConfig($pdo);

        if ($smtp['host'] !== '') {
            $ph   = rtrim(str_repeat('?,', count($memberIds)), ',');
            $sNew = $pdo->prepare("SELECT id, firstname, lastname, email, level, points, notification_prefs FROM users WHERE id IN ($ph)");
            $sNew->execute(array_values($memberIds));
            $statsNew = [];
            foreach ($sNew->fetchAll() as $r) { $statsNew[$r['id']] = $r; }

            $countryStmt = $pdo->prepare("SELECT name_hu FROM countries WHERE code = ? LIMIT 1");
            $countryStmt->execute([$tour['country']]);
            $countryName = $countryStmt->fetchColumn() ?: $tour['country'];

            $proto      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $absBaseUrl = $proto . '://' . $_SERVER['HTTP_HOST'] . BASE_URL;
            $tourUrl    = $absBaseUrl . '/user/tour-detail.php?id=' . $tourId;

            $tourType    = $tour['tour_type'];
            $totalKm     = $tour['total_km'];
            $alpineKm    = $tour['alpine_km'];
            $totalElev   = $tour['total_elevation'];
            $alpineElev  = $tour['alpine_elevation'];
            $tourHours   = $tour['tour_hours'];
            $tourDate    = $tour['tour_date'];
            $name        = $tour['name'];
            $region      = $tour['region'];

            // Build km and elev display strings
            $kmText = '—';
            if (in_array($tourType, ['gyalogos', 'kerekparos'], true)) {
                $sumKm = ((float)($totalKm ?: 0)) + ((float)($alpineKm !== null ? $alpineKm : 0));
                if ($sumKm > 0) $kmText = number_format($sumKm, 1, ',', ' ') . ' km';
            } elseif ($tourType === 'vizi') {
                if ((float)($totalKm ?: 0) > 0) $kmText = number_format((float)$totalKm, 1, ',', ' ') . ' km';
            } elseif ($tourHours !== null) {
                $kmText = number_format((float)$tourHours, 1, ',', ' ') . ' óra';
            }

            $elevText = '—';
            if (in_array($tourType, ['gyalogos', 'kerekparos'], true)) {
                $sumElev = ((int)($totalElev ?: 0)) + ((int)($alpineElev !== null ? $alpineElev : 0));
                if ($sumElev > 0) $elevText = number_format($sumElev, 0, ',', ' ') . ' m';
            }

            $formattedDate = $tourDate ? (new DateTime($tourDate))->format('Y.m.d') : '—';
            $tourDisplay   = $name ?: ($countryName . ($region ? ' – ' . $region : ''));
            $emailSubject  = 'Új túrához adtak hozzá: ' . ($name ?: $countryName);
            $mailer        = new SmtpMailer($smtp);
            $errCount      = 0;

            foreach ($memberIds as $uid) {
                if (!isset($statsNew[$uid])) continue;
                $m = $statsNew[$uid];
                $prefs = json_decode($m['notification_prefs'] ?? '{}', true) ?? [];
                if (($prefs['tour_added'] ?? 1) == 0) continue;
                $html          = '';
                $recipientName = $m['lastname'] . ' ' . $m['firstname'];
                try {
                    $html = buildTourNotificationEmailHtml(
                        $m['firstname'],
                        $tourDisplay,
                        $countryName,
                        $formattedDate,
                        getTourTypeLabel($tourType),
                        $kmText,
                        $elevText,
                        (int)$tour['points'],
                        $mtszPoints,
                        $tourCode,
                        (int)$m['level'],
                        (int)($statsOld[$uid]['level'] ?? 1),
                        $tourUrl,
                        $absBaseUrl,
                        APP_NAME
                    );
                    $mailer->send($m['email'], $recipientName, $emailSubject, $html);
                    logEmailEntry($pdo, (int)$uid, $m['email'], $recipientName, $emailSubject, $html, 'tour_added', 'sent');
                } catch (Throwable $ex) {
                    error_log('Tour approve notification email uid=' . $uid . ': ' . $ex->getMessage());
                    logEmailEntry($pdo, (int)$uid, $m['email'], $recipientName, $emailSubject, $html, 'tour_added', 'failed', $ex->getMessage());
                    $errCount++;
                }
            }
            if ($errCount > 0) {
                flash('error', $errCount . ' értesítő e-mail küldése sikertelen volt.');
            }
        }
    } catch (Throwable $ex) {
        error_log('Tour approve notification setup error: ' . $ex->getMessage());
        flash('error', 'Az értesítők küldése sikertelen: ' . $ex->getMessage());
    }
}

flash('success', 'A túra jóváhagyva és a tagok értesítve.');
header('Location: ' . BASE_URL . '/admin/tour-detail.php?id=' . $tourId);
exit;
