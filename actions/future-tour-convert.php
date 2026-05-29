<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
verifyCsrf();

$pdo = getDb();

$sourceFutureId = (int)($_POST['source_future_tour_id'] ?? 0);
if (!$sourceFutureId) {
    flash('error', 'Hiányzó forrásazonosító.');
    header('Location: ' . BASE_URL . '/admin/future-tours.php');
    exit;
}

$ftStmt = $pdo->prepare("SELECT * FROM future_tours WHERE id = ? LIMIT 1");
$ftStmt->execute([$sourceFutureId]);
$ft = $ftStmt->fetch();

if (!$ft) {
    flash('error', 'A forrás meghirdetett túra nem található.');
    header('Location: ' . BASE_URL . '/admin/future-tours.php');
    exit;
}
if ($ft['start_date'] >= date('Y-m-d')) {
    flash('error', 'Ez a túra még nem ért véget – konverzió nem lehetséges.');
    header('Location: ' . BASE_URL . '/admin/future-tour-detail.php?id=' . $sourceFutureId);
    exit;
}

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
$totalKm      = $tourType === 'vizi' ? ($_POST['vizi_km'] ?? '') : ($_POST['total_km'] ?? '');
$totalElev    = $_POST['total_elevation']   ?? '';
$alpineKm     = $_POST['alpine_km']         ?? '';
$alpineElev   = $_POST['alpine_elevation']  ?? '';
$subType      = trim($_POST['sub_type'] ?? '') ?: null;
$multiDayType = in_array($_POST['multi_day_type'] ?? '', ['csillag','vandor'])
                  ? $_POST['multi_day_type'] : null;
$tourHours    = $_POST['tour_hours'] ?? '';
$boatPortages = max(0, (int)($_POST['boat_portages'] ?? 0));
$campFixed    = ($accommodation === 'sator') ? max(0, (int)($_POST['camping_nights_fixed'] ?? 0)) : 0;
$isAlpine     = (($alpineKm !== '' && (float)$alpineKm > 0) || ($alpineElev !== '' && (int)$alpineElev > 0)) ? 1 : 0;
$lizzardPoints   = max(0, (int)($_POST['points'] ?? 0));
$closeFutureTour = !empty($_POST['close_future_tour']);

if (!$country) {
    flash('error', 'Az ország megadása kötelező.');
    header('Location: ' . BASE_URL . '/admin/future-tour-convert.php?id=' . $sourceFutureId);
    exit;
}

$tourData = [
    'tour_type'             => $tourType,
    'sub_type'              => $subType,
    'days'                  => $days,
    'total_km'              => $totalKm   !== '' ? $totalKm   : null,
    'total_elevation'       => $totalElev !== '' ? $totalElev : null,
    'alpine_km'             => $alpineKm  !== '' ? $alpineKm  : null,
    'alpine_elevation'      => $alpineElev !== '' ? $alpineElev : null,
    'tour_hours'            => $tourHours !== '' ? $tourHours : null,
    'multi_day_type'        => $multiDayType,
    'accommodation'         => $accommodation,
    'camping_nights_fixed'  => $campFixed,
    'camping_nights_mobile' => 0,
    'boat_portages'         => $boatPortages,
    'tour_date'             => $tourDate ?: null,
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
    $totalKm   !== '' ? (float)$totalKm   : null,
    $alpineKm  !== '' ? (float)$alpineKm  : null,
    $totalElev !== '' ? (int)$totalElev   : null,
    $alpineElev !== '' ? (int)$alpineElev : null,
    $tourType,
    $subType,
    $isAlpine,
    $multiDayType,
    $campFixed,
    0,
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

if ($closeFutureTour) {
    $pdo->prepare("UPDATE future_tours SET status = 'closed' WHERE id = ?")->execute([$sourceFutureId]);
}

$tourLabel    = $name ? $name . ' — ' . $country : $country;
$auditChanges = [
    ['k' => 'Forrás', 'v' => 'Meghirdetett túra #' . $sourceFutureId],
    ['k' => 'Ország', 'v' => $country],
    ['k' => 'Túramód', 'v' => $tourType],
];
if ($name)      $auditChanges[] = ['k' => 'Elnevezés',        'v' => $name];
if ($tourDate)  $auditChanges[] = ['k' => 'Dátum',            'v' => $tourDate];
$auditChanges[] = ['k' => 'Lizzardier pont', 'v' => (string)$lizzardPoints];
$auditChanges[] = ['k' => 'MTSZ pont',       'v' => (string)$mtszPoints];
if ($memberIds) $auditChanges[] = ['k' => 'Résztvevők száma', 'v' => (string)count($memberIds)];
logAudit($pdo, 'create', 'tour', $newId, $tourLabel, $auditChanges);

flash('success', 'A túra sikeresen rögzítve (Lizzardier: ' . $lizzardPoints . ' pt, MTSZ: ' . $mtszPoints . ' pt). Kódja: ' . $tourCode . '.');
header('Location: ' . BASE_URL . '/admin/tour-detail.php?id=' . $newId);
exit;
