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
$totalKm       = $_POST['total_km']           ?? '';
$totalElev     = $_POST['total_elevation']    ?? '';
$alpineKm      = $_POST['alpine_km']          ?? '';
$alpineElev    = $_POST['alpine_elevation']   ?? '';
$memberIds     = array_filter(array_map('intval', $_POST['member_ids'] ?? []));
$guestCount    = max(0, (int)($_POST['guest_count'] ?? 0));

$tourType     = in_array($_POST['tour_type'] ?? '', ['gyalogos','kerekparos','vizi','si','barlangi','munka'])
                  ? $_POST['tour_type'] : 'gyalogos';
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
