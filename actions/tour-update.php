<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

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

$pdo->prepare("UPDATE tours SET
    name=?, route=?, country=?, region=?, tour_date=?, days=?, accommodation=?,
    total_km=?, alpine_km=?, total_elevation=?, alpine_elevation=?,
    tour_type=?, sub_type=?, is_alpine=?, multi_day_type=?,
    camping_nights_fixed=?, camping_nights_mobile=?, tour_hours=?, boat_portages=?,
    guest_count=?, points=?, mtsz_points=?
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
    $id,
]);

// GPX fájl kezelése
$gpxCurrent = $tourBefore['gpx_file'] ?? null;
if (!empty($_POST['delete_gpx']) && $gpxCurrent) {
    $oldPath = GPX_DIR . $gpxCurrent;
    if (file_exists($oldPath)) {
        @unlink($oldPath);
    }
    $pdo->prepare("UPDATE tours SET gpx_file = NULL WHERE id = ?")->execute([$id]);
    $gpxCurrent = null;
} elseif (isset($_FILES['gpx_file']) && $_FILES['gpx_file']['error'] === UPLOAD_ERR_OK) {
    $gpxTmp  = $_FILES['gpx_file']['tmp_name'];
    $gpxOrig = $_FILES['gpx_file']['name'];
    $gpxExt  = strtolower(pathinfo($gpxOrig, PATHINFO_EXTENSION));
    $gpxSize = $_FILES['gpx_file']['size'];

    if ($gpxExt !== 'gpx') {
        flash('error', 'Csak .gpx kiterjesztésű fájl tölthető fel.');
        header('Location: ' . $redirectTo);
        exit;
    }
    if ($gpxSize > 5 * 1024 * 1024) {
        flash('error', 'A GPX fájl mérete nem haladhatja meg az 5 MB-ot.');
        header('Location: ' . $redirectTo);
        exit;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($gpxTmp);
    $allowedMimes = ['text/xml', 'application/xml', 'application/gpx+xml', 'text/plain', 'application/octet-stream'];
    if (!in_array($mime, $allowedMimes, true)) {
        flash('error', 'Érvénytelen GPX fájl formátum.');
        header('Location: ' . $redirectTo);
        exit;
    }
    if (!is_dir(GPX_DIR)) {
        mkdir(GPX_DIR, 0755, true);
    }
    if ($gpxCurrent && file_exists(GPX_DIR . $gpxCurrent)) {
        @unlink(GPX_DIR . $gpxCurrent);
    }
    $gpxFile = 'gpx_' . $id . '_' . time() . '.gpx';
    if (!move_uploaded_file($gpxTmp, GPX_DIR . $gpxFile)) {
        flash('error', 'A GPX fájl feltöltése sikertelen.');
        header('Location: ' . $redirectTo);
        exit;
    }
    $pdo->prepare("UPDATE tours SET gpx_file = ? WHERE id = ?")->execute([$gpxFile, $id]);
}

$pdo->prepare("DELETE FROM tour_members WHERE tour_id = ?")->execute([$id]);
if ($memberIds) {
    $ins = $pdo->prepare("INSERT INTO tour_members (tour_id, user_id) VALUES (?, ?)");
    foreach ($memberIds as $uid) {
        $ins->execute([$id, $uid]);
    }
}

recalcUserStats($pdo);

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
$addedIds   = array_values(array_diff($memberIds, $oldMemberIds));
$removedIds = array_values(array_diff($oldMemberIds, $memberIds));
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

flash('success', 'A túra adatai sikeresen frissítve (Lizzardier: ' . $lizzardPoints . ' pt, MTSZ: ' . $mtszPoints . ' pt).');
header('Location: ' . $redirectTo);
exit;
