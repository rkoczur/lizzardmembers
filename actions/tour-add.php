<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

verifyCsrf();

$pdo = getDb();

$name          = trim($_POST['name']          ?? '');
$route         = trim($_POST['route']         ?? '');
$country       = trim($_POST['country']       ?? '');
$region        = trim($_POST['region']        ?? '');
$tourDate      = $_POST['tour_date']          ?? '';
$days          = max(1, (int)($_POST['days']  ?? 1));
$accommodation = in_array($_POST['accommodation'] ?? '', ['sator','turistahaz','apartman','hotel'])
                   ? $_POST['accommodation'] : '';
$memberIds     = array_filter(array_map('intval', $_POST['member_ids'] ?? []));
$guestCount    = max(0, (int)($_POST['guest_count'] ?? 0));

// MTSZ mezők
$tourType     = in_array($_POST['tour_type'] ?? '', ['gyalogos','kerekparos','vizi','si','barlangi','munka'])
                  ? $_POST['tour_type'] : 'gyalogos';
$totalKm       = $tourType === 'vizi' ? ($_POST['vizi_km'] ?? '') : ($_POST['total_km'] ?? '');
$totalElev     = $_POST['total_elevation']    ?? '';
$alpineKm      = $_POST['alpine_km']          ?? '';
$alpineElev    = $_POST['alpine_elevation']   ?? '';
$subType      = trim($_POST['sub_type'] ?? '') ?: null;
$multiDayType = in_array($_POST['multi_day_type'] ?? '', ['csillag','vandor'])
                  ? $_POST['multi_day_type'] : null;
$tourHours    = $_POST['tour_hours'] ?? '';
$boatPortages = max(0, (int)($_POST['boat_portages'] ?? 0));

// Sátorozás csak sátor szállásnál számít
$campFixed  = ($accommodation === 'sator') ? max(0, (int)($_POST['camping_nights_fixed']  ?? 0)) : 0;
$campMobile = ($accommodation === 'sator') ? max(0, (int)($_POST['camping_nights_mobile'] ?? 0)) : 0;

// is_alpine levezetett érték
$isAlpine = (($alpineKm !== '' && (float)$alpineKm > 0) || ($alpineElev !== '' && (int)$alpineElev > 0)) ? 1 : 0;

if (!$country) {
    flash('error', 'Az ország megadása kötelező.');
    $_SESSION['form_old'] = [
        'name' => $name, 'route' => $route, 'country' => $country, 'region' => $region,
        'tour_date' => $tourDate, 'days' => $days, 'accommodation' => $accommodation,
        'total_km' => $totalKm, 'total_elevation' => $totalElev,
        'alpine_km' => $alpineKm, 'alpine_elevation' => $alpineElev,
        'tour_type' => $tourType, 'sub_type' => $subType,
        'multi_day_type' => $multiDayType,
        'camping_nights_fixed' => $campFixed, 'camping_nights_mobile' => $campMobile,
        'tour_hours' => $tourHours, 'boat_portages' => $boatPortages,
        'guest_count' => $guestCount,
        'member_ids' => $memberIds,
    ];
    header('Location: ' . BASE_URL . '/admin/tour-add.php');
    exit;
}

$lizzardPoints = max(0, (int)($_POST['points'] ?? 0));

$tourData = [
    'tour_type'            => $tourType,
    'sub_type'             => $subType,
    'days'                 => $days,
    'total_km'             => $totalKm    !== '' ? $totalKm    : null,
    'total_elevation'      => $totalElev  !== '' ? $totalElev  : null,
    'alpine_km'            => $alpineKm   !== '' ? $alpineKm   : null,
    'alpine_elevation'     => $alpineElev !== '' ? $alpineElev : null,
    'tour_hours'           => $tourHours  !== '' ? $tourHours  : null,
    'multi_day_type'       => $multiDayType,
    'accommodation'        => $accommodation,
    'camping_nights_fixed' => $campFixed,
    'camping_nights_mobile'=> $campMobile,
    'boat_portages'        => $boatPortages,
    'tour_date'            => $tourDate ?: null,
];
$mtszPoints = calculateTourPoints($tourData);
$tourCode   = generateTourCode($pdo, $tourType);

$stmt = $pdo->prepare("INSERT INTO tours
    (tour_code, name, route, country, region, tour_date, days, accommodation,
     total_km, alpine_km, total_elevation, alpine_elevation,
     tour_type, sub_type, is_alpine, multi_day_type,
     camping_nights_fixed, camping_nights_mobile, tour_hours, boat_portages,
     guest_count, points, mtsz_points)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->execute([
    $tourCode,
    $name ?: null,
    $route ?: null,
    $country,
    $region ?: null,
    $tourDate ?: null,
    $days,
    $accommodation ?: null,
    $totalKm    !== '' ? (float)$totalKm    : null,
    $alpineKm   !== '' ? (float)$alpineKm   : null,
    $totalElev  !== '' ? (int)$totalElev    : null,
    $alpineElev !== '' ? (int)$alpineElev   : null,
    $tourType,
    $subType,
    $isAlpine,
    $multiDayType,
    $campFixed,
    $campMobile,
    $tourHours !== '' ? (float)$tourHours : null,
    $boatPortages,
    $guestCount,
    $lizzardPoints,
    $mtszPoints,
]);

$newId = (int)$pdo->lastInsertId();

// GPX fájl feldolgozása
$gpxFile = null;
if (isset($_FILES['gpx_file']) && $_FILES['gpx_file']['error'] === UPLOAD_ERR_OK) {
    $gpxTmp  = $_FILES['gpx_file']['tmp_name'];
    $gpxOrig = $_FILES['gpx_file']['name'];
    $gpxExt  = strtolower(pathinfo($gpxOrig, PATHINFO_EXTENSION));
    $gpxSize = $_FILES['gpx_file']['size'];

    if ($gpxExt !== 'gpx') {
        flash('error', 'Csak .gpx kiterjesztésű fájl tölthető fel.');
        header('Location: ' . BASE_URL . '/admin/tour-detail.php?id=' . $newId);
        exit;
    }
    if ($gpxSize > 5 * 1024 * 1024) {
        flash('error', 'A GPX fájl mérete nem haladhatja meg az 5 MB-ot.');
        header('Location: ' . BASE_URL . '/admin/tour-detail.php?id=' . $newId);
        exit;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($gpxTmp);
    $allowedMimes = ['text/xml', 'application/xml', 'application/gpx+xml', 'text/plain', 'application/octet-stream'];
    if (!in_array($mime, $allowedMimes, true)) {
        flash('error', 'Érvénytelen GPX fájl formátum.');
        header('Location: ' . BASE_URL . '/admin/tour-detail.php?id=' . $newId);
        exit;
    }
    if (!is_dir(GPX_DIR)) {
        mkdir(GPX_DIR, 0755, true);
    }
    $gpxFile = 'gpx_' . $newId . '_' . time() . '.gpx';
    if (!move_uploaded_file($gpxTmp, GPX_DIR . $gpxFile)) {
        flash('error', 'A GPX fájl feltöltése sikertelen.');
        header('Location: ' . BASE_URL . '/admin/tour-detail.php?id=' . $newId);
        exit;
    }
    $pdo->prepare("UPDATE tours SET gpx_file = ? WHERE id = ?")->execute([$gpxFile, $newId]);
}

if ($memberIds) {
    $ins = $pdo->prepare("INSERT IGNORE INTO tour_members (tour_id, user_id) VALUES (?, ?)");
    foreach ($memberIds as $uid) {
        $ins->execute([$newId, $uid]);
    }
}

// Capture stats before recalc so we can detect level-ups for notifications
$statsOld = [];
if ($memberIds) {
    $ph = rtrim(str_repeat('?,', count($memberIds)), ',');
    $sOld = $pdo->prepare("SELECT id, level, points FROM users WHERE id IN ($ph)");
    $sOld->execute(array_values($memberIds));
    foreach ($sOld->fetchAll() as $r) { $statsOld[$r['id']] = $r; }
}

recalcUserStats($pdo);

// Send tour notifications to newly added members
if (($memberIds) && ($_POST['send_tour_notification'] ?? '') === '1') {
    try {
        require_once __DIR__ . '/../includes/app-settings-schema.php';
        require_once __DIR__ . '/../includes/mailer.php';
        require_once __DIR__ . '/../includes/tour-notification-email.php';
        require_once __DIR__ . '/../includes/email-log-schema.php';

        ensureAppSettingsSchema($pdo);
        ensureEmailLogSchema($pdo);
        $smtp = getSmtpConfig($pdo);

        if ($smtp['host'] !== '') {
            $ph      = rtrim(str_repeat('?,', count($memberIds)), ',');
            $sNew    = $pdo->prepare("SELECT id, firstname, lastname, email, level, points, notification_prefs FROM users WHERE id IN ($ph)");
            $sNew->execute(array_values($memberIds));
            $statsNew = [];
            foreach ($sNew->fetchAll() as $r) { $statsNew[$r['id']] = $r; }

            $countryStmt = $pdo->prepare("SELECT name_hu FROM countries WHERE code = ? LIMIT 1");
            $countryStmt->execute([$country]);
            $countryName = $countryStmt->fetchColumn() ?: $country;

            $proto      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $absBaseUrl = $proto . '://' . $_SERVER['HTTP_HOST'] . BASE_URL;
            $tourUrl    = $absBaseUrl . '/user/tour-detail.php?id=' . $newId;

            // Build km and elev display strings
            $kmText = '—';
            if (in_array($tourType, ['gyalogos', 'kerekparos'], true)) {
                $sumKm = ((float)($totalKm ?: 0)) + ((float)($alpineKm !== '' ? $alpineKm : 0));
                if ($sumKm > 0) $kmText = number_format($sumKm, 1, ',', ' ') . ' km';
            } elseif ($tourType === 'vizi') {
                if ((float)($totalKm ?: 0) > 0) $kmText = number_format((float)$totalKm, 1, ',', ' ') . ' km';
            } elseif ($tourHours !== '') {
                $kmText = number_format((float)$tourHours, 1, ',', ' ') . ' óra';
            }

            $elevText = '—';
            if (in_array($tourType, ['gyalogos', 'kerekparos'], true)) {
                $sumElev = ((int)($totalElev ?: 0)) + ((int)($alpineElev !== '' ? $alpineElev : 0));
                if ($sumElev > 0) $elevText = number_format($sumElev, 0, ',', ' ') . ' m';
            }

            $formattedDate = $tourDate ? (new DateTime($tourDate))->format('Y.m.d') : '—';
            $tourDisplay   = $name ?: ($countryName . ($region ? ' – ' . $region : ''));
            $mailer        = new SmtpMailer($smtp);
            $errCount      = 0;

            foreach ($memberIds as $uid) {
                if (!isset($statsNew[$uid])) continue;
                $m = $statsNew[$uid];
                $prefs = json_decode($m['notification_prefs'] ?? '{}', true) ?? [];
                if (($prefs['tour_added'] ?? 1) == 0) continue;
                $html          = '';
                $emailSubject  = 'Új túrához adtak hozzá: ' . ($name ?: $countryName);
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
                        $lizzardPoints,
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
                    error_log('Tour notification email uid=' . $uid . ': ' . $ex->getMessage());
                    logEmailEntry($pdo, (int)$uid, $m['email'], $recipientName, $emailSubject, $html, 'tour_added', 'failed', $ex->getMessage());
                    $errCount++;
                }
            }
            if ($errCount > 0) {
                flash('error', $errCount . ' értesítő e-mail küldése sikertelen volt.');
            }
        }
    } catch (Throwable $ex) {
        error_log('Tour notification setup error: ' . $ex->getMessage());
        flash('error', 'Az értesítők küldése sikertelen: ' . $ex->getMessage());
    }
}

$tourLabel    = $name ? $name . ' — ' . $country : $country;
$auditChanges = [['k' => 'Ország', 'v' => $country], ['k' => 'Túramód', 'v' => $tourType]];
if ($name)     $auditChanges[] = ['k' => 'Elnevezés', 'v' => $name];
if ($tourDate) $auditChanges[] = ['k' => 'Dátum',     'v' => $tourDate];
$auditChanges[] = ['k' => 'Lizzardier pont', 'v' => (string)$lizzardPoints];
$auditChanges[] = ['k' => 'MTSZ pont',      'v' => (string)$mtszPoints];
if ($memberIds) $auditChanges[] = ['k' => 'Résztvevők száma', 'v' => (string)count($memberIds)];
logAudit($pdo, 'create', 'tour', $newId, $tourLabel, $auditChanges);

flash('success', 'A túra sikeresen hozzáadva (Lizzardier: ' . $lizzardPoints . ' pt, MTSZ: ' . $mtszPoints . ' pt).');
header('Location: ' . BASE_URL . '/admin/tour-detail.php?id=' . $newId);
exit;
