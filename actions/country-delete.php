<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tours-schema.php';
requireAdmin();
verifyCsrf();

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/admin/settings.php');
    exit;
}

$pdo = getDb();
ensureToursSchema($pdo);

$stmt = $pdo->prepare("SELECT * FROM countries WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$country = $stmt->fetch();
if (!$country) {
    header('Location: ' . BASE_URL . '/admin/settings.php');
    exit;
}

$tcStmt = $pdo->prepare("SELECT COUNT(*) FROM tours WHERE country = ?");
$tcStmt->execute([$country['code']]);
$tourCount = (int)$tcStmt->fetchColumn();

if ($tourCount > 0) {
    flash('error', '"' . $country['name_hu'] . '" (' . $country['code'] . ') nem törölhető: ' . $tourCount . ' túra hivatkozik erre az országkódra.');
    header('Location: ' . BASE_URL . '/admin/country-detail.php?id=' . $id);
    exit;
}

if ($country['flag_filename'] && file_exists(FLAG_DIR . $country['flag_filename'])) {
    @unlink(FLAG_DIR . $country['flag_filename']);
}

$pdo->prepare("DELETE FROM countries WHERE id = ?")->execute([$id]);

flash('success', '"' . $country['name_hu'] . '" (' . $country['code'] . ') törölve.');
header('Location: ' . BASE_URL . '/admin/settings.php');
exit;
