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

$beforeStmt = $pdo->prepare("SELECT name, country, region, tour_date, days, accommodation, total_km, total_elevation, points FROM tours WHERE id = ?");
$beforeStmt->execute([$id]);
$tourBefore = $beforeStmt->fetch();

$oldMemberStmt = $pdo->prepare("SELECT user_id FROM tour_members WHERE tour_id = ?");
$oldMemberStmt->execute([$id]);
$oldMemberIds = $oldMemberStmt->fetchAll(PDO::FETCH_COLUMN);

$name          = trim($_POST['name']              ?? '');
$country       = trim($_POST['country']           ?? '');
$region        = trim($_POST['region']            ?? '');
$tourDate      = $_POST['tour_date']              ?? '';
$days          = max(1, (int)($_POST['days']      ?? 1));
$accommodation = trim($_POST['accommodation']     ?? '');
$totalKm       = $_POST['total_km']               ?? '';
$totalElev     = $_POST['total_elevation']        ?? '';
$points        = max(0, (int)($_POST['points']    ?? 0));
$memberIds     = array_filter(array_map('intval', $_POST['member_ids'] ?? []));

if (!$country) {
    flash('error', 'Az ország megadása kötelező.');
    header('Location: ' . $redirectTo);
    exit;
}

$pdo->prepare("UPDATE tours SET
    name=?, country=?, region=?, tour_date=?, days=?, accommodation=?,
    total_km=?, total_elevation=?, points=?
    WHERE id=?")->execute([
    $name ?: null,
    $country,
    $region ?: null,
    $tourDate ?: null,
    $days,
    $accommodation ?: null,
    $totalKm !== '' ? (float)$totalKm : null,
    $totalElev !== '' ? (int)$totalElev : null,
    $points,
    $id,
]);

// Replace all member assignments
$pdo->prepare("DELETE FROM tour_members WHERE tour_id = ?")->execute([$id]);
if ($memberIds) {
    $ins = $pdo->prepare("INSERT INTO tour_members (tour_id, user_id) VALUES (?, ?)");
    foreach ($memberIds as $uid) {
        $ins->execute([$id, $uid]);
    }
}

recalcUserStats($pdo);

$tourFieldLabels = [
    'name' => 'Elnevezés', 'country' => 'Ország', 'region' => 'Régió',
    'tour_date' => 'Dátum', 'days' => 'Napok', 'accommodation' => 'Szállás',
    'total_km' => 'Távolság (km)', 'total_elevation' => 'Szintemelkedés (m)', 'points' => 'Pontérték',
];
$tourNewValues = [
    'name' => $name ?: '', 'country' => $country, 'region' => $region,
    'tour_date' => $tourDate, 'days' => (string)$days, 'accommodation' => $accommodation,
    'total_km' => $totalKm !== '' ? (string)(float)$totalKm : '',
    'total_elevation' => $totalElev !== '' ? (string)(int)$totalElev : '',
    'points' => (string)$points,
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

flash('success', 'A túra adatai sikeresen frissítve.');
header('Location: ' . $redirectTo);
exit;
