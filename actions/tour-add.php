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
$totalKm       = $_POST['total_km']           ?? '';
$totalElev     = $_POST['total_elevation']    ?? '';
$alpineKm      = $_POST['alpine_km']          ?? '';
$alpineElev    = $_POST['alpine_elevation']   ?? '';
$memberIds     = array_filter(array_map('intval', $_POST['member_ids'] ?? []));
$guestCount    = max(0, (int)($_POST['guest_count'] ?? 0));

// MTSZ mezők
$tourType     = in_array($_POST['tour_type'] ?? '', ['gyalogos','kerekparos','vizi','si','barlangi','munka'])
                  ? $_POST['tour_type'] : 'gyalogos';
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

if ($memberIds) {
    $ins = $pdo->prepare("INSERT IGNORE INTO tour_members (tour_id, user_id) VALUES (?, ?)");
    foreach ($memberIds as $uid) {
        $ins->execute([$newId, $uid]);
    }
}

recalcUserStats($pdo);

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
