<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
requireAdmin();
verifyCsrf();

$pdo = getDb();
ensureFutureToursSchema($pdo);

$tourId = (int)($_POST['tour_id'] ?? 0);
$userId = (int)($_POST['user_id'] ?? 0);
$status = in_array($_POST['status'] ?? '', ['confirmed','waitlist']) ? $_POST['status'] : 'confirmed';

if (!$tourId || !$userId) {
    flash('error', 'Hiányos adatok.');
    header('Location: ' . BASE_URL . '/admin/future-tour-detail.php?id=' . $tourId);
    exit;
}

$tourStmt = $pdo->prepare("SELECT id FROM future_tours WHERE id = ? LIMIT 1");
$tourStmt->execute([$tourId]);
if (!$tourStmt->fetch()) {
    flash('error', 'A túra nem található.');
    header('Location: ' . BASE_URL . '/admin/future-tours.php');
    exit;
}

$existingStmt = $pdo->prepare("SELECT id, status FROM future_tour_applications WHERE future_tour_id = ? AND user_id = ? LIMIT 1");
$existingStmt->execute([$tourId, $userId]);
$existing = $existingStmt->fetch();

if ($existing && $existing['status'] !== 'cancelled') {
    flash('error', 'Ez a tag már szerepel a túra jelentkezői között.');
    header('Location: ' . BASE_URL . '/admin/future-tour-detail.php?id=' . $tourId);
    exit;
}

if ($existing && $existing['status'] === 'cancelled') {
    $pdo->prepare("UPDATE future_tour_applications SET status=?, car_available=0, passengers=0, sharing_room='same_gender', notes=NULL, paid_at=NULL, applied_at=NOW() WHERE id=?")
        ->execute([$status, $existing['id']]);
} else {
    $pdo->prepare("INSERT INTO future_tour_applications (future_tour_id, user_id, status, car_available, passengers, sharing_room) VALUES (?, ?, ?, 0, 0, 'same_gender')")
        ->execute([$tourId, $userId, $status]);
}

flash('success', 'Tag sikeresen hozzáadva a túrához.');
header('Location: ' . BASE_URL . '/admin/future-tour-detail.php?id=' . $tourId);
exit;
