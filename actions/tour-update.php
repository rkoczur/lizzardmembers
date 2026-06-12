<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLeader();
if (!canManageTours()) {
    flash('error', 'Nincs jogosultságod ehhez a művelethez.');
    header('Location: ' . BASE_URL . '/admin/tours.php');
    exit;
}

verifyCsrf();

$pdo = getDb();
$id  = (int)($_POST['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/admin/tours.php');
    exit;
}

$redirectTo = BASE_URL . '/admin/tour-detail.php?id=' . $id;

$beforeStmt = $pdo->prepare("SELECT * FROM tours WHERE id = ?");
$beforeStmt->execute([$id]);
$tourBefore = $beforeStmt->fetch();

$oldMemberStmt = $pdo->prepare("SELECT user_id FROM tour_members WHERE tour_id = ?");
$oldMemberStmt->execute([$id]);
$oldMemberIds = $oldMemberStmt->fetchAll(PDO::FETCH_COLUMN);

$approvePending = !empty($_POST['approve_on_save']) && ($tourBefore['status'] ?? 'approved') === 'pending';

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

$campFixed  = max(0, (int)($_POST['camping_nights_fixed'] ?? 0));
$campMobile = 0;
$isAlpine   = (($alpineKm !== '' && (float)$alpineKm > 0) || ($alpineElev !== '' && (int)$alpineElev > 0)) ? 1 : 0;

if (!$country) {
    flash('error', 'Az ország megadása kötelező.');
    header('Location: ' . $redirectTo);
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

// Admin kézi felülírás az MTSZ pontra: ha be van kapcsolva és van érték, az kerül mentésre
$overrideEnabled = !empty($_POST['mtsz_override_enabled']);
$overrideRaw     = trim($_POST['mtsz_points_override'] ?? '');
$mtszOverride    = ($overrideEnabled && $overrideRaw !== '') ? max(0, (int)$overrideRaw) : null;
$mtszPoints      = $mtszOverride ?? $mtszPoints; // a tárolt (megjelenített) érték a felülírt, ha van

$pdo->prepare("UPDATE tours SET
    name=?, route=?, country=?, region=?, tour_date=?, days=?, accommodation=?,
    total_km=?, alpine_km=?, total_elevation=?, alpine_elevation=?,
    tour_type=?, sub_type=?, is_alpine=?, multi_day_type=?,
    camping_nights_fixed=?, camping_nights_mobile=?, tour_hours=?, boat_portages=?,
    guest_count=?, points=?, mtsz_points=?, mtsz_points_override=?
    WHERE id=?")->execute([
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
    $mtszOverride,
    $id,
]);

// GPX: meglévő fájlok feliratának mentése
foreach ($_POST['gpx_label'] ?? [] as $gfId => $label) {
    $gfId = (int)$gfId;
    if (!$gfId) continue;
    $pdo->prepare("UPDATE tour_gpx_files SET label = ? WHERE id = ? AND tour_id = ?")
        ->execute([trim($label) ?: null, $gfId, $id]);
}

// GPX fájlok kezelése — törlés
$deleteGpxIds = array_values(array_filter(array_map('intval', $_POST['delete_gpx_ids'] ?? [])));
if ($deleteGpxIds) {
    $ph   = implode(',', array_fill(0, count($deleteGpxIds), '?'));
    $rows = $pdo->prepare("SELECT filename FROM tour_gpx_files WHERE id IN ($ph) AND tour_id = ?");
    $rows->execute(array_merge($deleteGpxIds, [$id]));
    foreach ($rows->fetchAll(PDO::FETCH_COLUMN) as $fname) {
        if ($fname && file_exists(GPX_DIR . $fname)) @unlink(GPX_DIR . $fname);
    }
    $pdo->prepare("DELETE FROM tour_gpx_files WHERE id IN ($ph) AND tour_id = ?")
        ->execute(array_merge($deleteGpxIds, [$id]));
}

// GPX fájlok kezelése — feltöltés
if (!empty($_FILES['gpx_files']['tmp_name'])) {
    if (!is_dir(GPX_DIR)) mkdir(GPX_DIR, 0755, true);
    $allowedMimes = ['text/xml','application/xml','application/gpx+xml','text/plain','application/octet-stream'];
    $insGpx = $pdo->prepare("INSERT IGNORE INTO tour_gpx_files (tour_id, filename, sort_order) VALUES (?, ?, ?)");
    $sortBase = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM tour_gpx_files WHERE tour_id = $id")->fetchColumn();
    foreach ($_FILES['gpx_files']['tmp_name'] as $i => $tmp) {
        if (($_FILES['gpx_files']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        $ext  = strtolower(pathinfo($_FILES['gpx_files']['name'][$i] ?? '', PATHINFO_EXTENSION));
        $size = (int)($_FILES['gpx_files']['size'][$i] ?? 0);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if ($ext !== 'gpx' || $size > 1 * 1024 * 1024 || !in_array($mime, $allowedMimes, true)) continue;
        $newFile = 'gpx_' . $id . '_' . time() . '_' . $i . '.gpx';
        if (move_uploaded_file($tmp, GPX_DIR . $newFile)) {
            $insGpx->execute([$id, $newFile, $sortBase + $i + 1]);
        }
    }
}

$addedIds   = array_values(array_diff($memberIds, $oldMemberIds));
$removedIds = array_values(array_diff($oldMemberIds, $memberIds));

$pdo->prepare("DELETE FROM tour_members WHERE tour_id = ?")->execute([$id]);
if ($memberIds) {
    $ins = $pdo->prepare("INSERT INTO tour_members (tour_id, user_id) VALUES (?, ?)");
    foreach ($memberIds as $uid) {
        $ins->execute([$id, $uid]);
    }
}

$captureIds = $approvePending ? $memberIds : $addedIds;
$statsOld = [];
if ($captureIds) {
    $ph = rtrim(str_repeat('?,', count($captureIds)), ',');
    $sOld = $pdo->prepare("SELECT id, level, points FROM users WHERE id IN ($ph)");
    $sOld->execute(array_values($captureIds));
    foreach ($sOld->fetchAll() as $r) { $statsOld[$r['id']] = $r; }
}

recalcUserStats($pdo);

if ($approvePending) {
    require_once __DIR__ . '/../includes/tours-schema.php';
    $newTourCode = generateTourCode($pdo, $tourType);
    $pdo->prepare("UPDATE tours SET status = 'approved', tour_code = ? WHERE id = ?")
        ->execute([$newTourCode, $id]);
} else {
    $newTourCode = $tourBefore['tour_code'] ?? null;
}

$notifyIds = $approvePending ? $memberIds : $addedIds;
if ($notifyIds && ($approvePending || ($_POST['send_tour_notification'] ?? '') === '1')) {
    try {
        require_once __DIR__ . '/../includes/app-settings-schema.php';
        require_once __DIR__ . '/../includes/mailer.php';
        require_once __DIR__ . '/../includes/tour-notification-email.php';
        require_once __DIR__ . '/../includes/email-log-schema.php';

        ensureAppSettingsSchema($pdo);
        ensureEmailLogSchema($pdo);
        $smtp = getSmtpConfig($pdo);

        if ($smtp['host'] !== '') {
            $ph   = rtrim(str_repeat('?,', count($notifyIds)), ',');
            $sNew = $pdo->prepare("SELECT id, firstname, lastname, email, level, points, notification_prefs FROM users WHERE id IN ($ph)");
            $sNew->execute(array_values($notifyIds));
            $statsNew = [];
            foreach ($sNew->fetchAll() as $r) { $statsNew[$r['id']] = $r; }

            $countryStmt = $pdo->prepare("SELECT name_hu FROM countries WHERE code = ? LIMIT 1");
            $countryStmt->execute([$country]);
            $countryName = $countryStmt->fetchColumn() ?: $country;

            $proto      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $absBaseUrl = $proto . '://' . $_SERVER['HTTP_HOST'] . BASE_URL;
            $tourUrl    = $absBaseUrl . '/user/tour-detail.php?id=' . $id;

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

            $tourRow     = $pdo->prepare("SELECT name, country, region FROM tours WHERE id = ? LIMIT 1");
            $tourRow->execute([$id]);
            $tourRow     = $tourRow->fetch();
            $formattedDate = $tourDate ? (new DateTime($tourDate))->format('Y.m.d') : '—';
            $tourDisplay   = $name ?: ($countryName . ($region ? ' – ' . $region : ''));
            $mailer        = new SmtpMailer($smtp);
            $errCount      = 0;

            foreach ($notifyIds as $uid) {
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
                        $newTourCode ?? '—',
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

$tourFieldLabels = [
    'name' => 'Elnevezés', 'route' => 'Útvonal', 'country' => 'Ország', 'region' => 'Régió',
    'tour_date' => 'Dátum', 'days' => 'Napok', 'accommodation' => 'Szállás',
    'total_km' => 'Nem magashegyi km', 'alpine_km' => 'Magashegyi km',
    'total_elevation' => 'Nem magashegyi szint (m)', 'alpine_elevation' => 'Magashegyi szint (m)',
    'tour_type' => 'Túramód', 'sub_type' => 'Altípus',
    'multi_day_type' => 'Többnapos típus',
    'camping_nights_fixed' => 'Állótábor éjszakák', 'camping_nights_mobile' => 'Mozgótábor éjszakák',
    'tour_hours' => 'Túraidő (óra)', 'boat_portages' => 'Hajóátemelések',
    'guest_count' => 'Vendég résztvevők',
    'points' => 'Lizzardier pont', 'mtsz_points' => 'MTSZ pont',
];
$tourNewValues = [
    'name' => $name ?: '', 'route' => $route, 'country' => $country, 'region' => $region,
    'tour_date' => $tourDate, 'days' => (string)$days, 'accommodation' => $accommodation,
    'total_km'         => $totalKm    !== '' ? (string)(float)$totalKm    : '',
    'alpine_km'        => $alpineKm   !== '' ? (string)(float)$alpineKm   : '',
    'total_elevation'  => $totalElev  !== '' ? (string)(int)$totalElev    : '',
    'alpine_elevation' => $alpineElev !== '' ? (string)(int)$alpineElev   : '',
    'tour_type' => $tourType, 'sub_type' => $subType ?? '',
    'multi_day_type' => $multiDayType ?? '',
    'camping_nights_fixed' => (string)$campFixed, 'camping_nights_mobile' => (string)$campMobile,
    'tour_hours' => $tourHours !== '' ? (string)(float)$tourHours : '',
    'boat_portages' => (string)$boatPortages,
    'guest_count' => (string)$guestCount,
    'points' => (string)$lizzardPoints, 'mtsz_points' => (string)$mtszPoints,
];
$auditChanges = [];
foreach ($tourFieldLabels as $field => $label) {
    $oldVal = (string)($tourBefore[$field] ?? '');
    $newVal = (string)($tourNewValues[$field] ?? '');
    if ($oldVal !== $newVal) {
        $auditChanges[] = ['k' => $label, 'f' => $oldVal ?: '—', 't' => $newVal ?: '—'];
    }
}
if ($addedIds) {
    $ph   = rtrim(str_repeat('?,', count($addedIds)), ',');
    $stmt = $pdo->prepare("SELECT CONCAT(lastname, ' ', firstname) FROM users WHERE id IN ($ph)");
    $stmt->execute($addedIds);
    $auditChanges[] = ['k' => 'Hozzáadott résztvevők', 'f' => '—', 't' => implode(', ', $stmt->fetchAll(PDO::FETCH_COLUMN))];
}
if ($removedIds) {
    $ph   = rtrim(str_repeat('?,', count($removedIds)), ',');
    $stmt = $pdo->prepare("SELECT CONCAT(lastname, ' ', firstname) FROM users WHERE id IN ($ph)");
    $stmt->execute($removedIds);
    $auditChanges[] = ['k' => 'Eltávolított résztvevők', 'f' => '—', 't' => implode(', ', $stmt->fetchAll(PDO::FETCH_COLUMN))];
}
$tourLabel = $name ? $name . ' — ' . $country : $country;
logAudit($pdo, 'update', 'tour', $id, $tourLabel, $auditChanges ?: null);

if ($approvePending) {
    flash('success', 'A túra jóváhagyva és a tagok értesítve.');
    header('Location: ' . BASE_URL . '/admin/tours.php');
} else {
    flash('success', 'A túra adatai sikeresen frissítve (Lizzardier: ' . $lizzardPoints . ' pt, MTSZ: ' . $mtszPoints . ' pt).');
    header('Location: ' . $redirectTo);
}
exit;
