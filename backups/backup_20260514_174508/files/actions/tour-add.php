<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

verifyCsrf();

$pdo = getDb();

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
    $_SESSION['form_old'] = [
        'name' => $name, 'country' => $country, 'region' => $region,
        'tour_date' => $tourDate, 'days' => $days, 'accommodation' => $accommodation,
        'total_km' => $totalKm, 'total_elevation' => $totalElev,
        'points' => $points,
        'member_ids' => $memberIds,
    ];
    header('Location: ' . BASE_URL . '/admin/tour-add.php');
    exit;
}

$stmt = $pdo->prepare("INSERT INTO tours
    (name, country, region, tour_date, days, accommodation, total_km, total_elevation, points)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->execute([
    $name ?: null,
    $country,
    $region ?: null,
    $tourDate ?: null,
    $days,
    $accommodation ?: null,
    $totalKm !== '' ? (float)$totalKm : null,
    $totalElev !== '' ? (int)$totalElev : null,
    $points,
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
$auditChanges = [['k' => 'Ország', 'v' => $country]];
if ($name)     $auditChanges[] = ['k' => 'Elnevezés', 'v' => $name];
if ($tourDate) $auditChanges[] = ['k' => 'Dátum',     'v' => $tourDate];
if ($points)   $auditChanges[] = ['k' => 'Pontérték', 'v' => (string)$points];
if ($memberIds) $auditChanges[] = ['k' => 'Résztvevők száma', 'v' => (string)count($memberIds)];
logAudit($pdo, 'create', 'tour', $newId, $tourLabel, $auditChanges);

flash('success', 'A túra sikeresen hozzáadva.');
header('Location: ' . BASE_URL . '/admin/tour-detail.php?id=' . $newId);
exit;
