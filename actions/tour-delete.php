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

$tourStmt = $pdo->prepare("SELECT name, country, tour_date, points FROM tours WHERE id = ?");
$tourStmt->execute([$id]);
$tour = $tourStmt->fetch();
if (!$tour) {
    header('Location: ' . BASE_URL . '/admin/tours.php');
    exit;
}
$tourLabel    = ($tour['name'] ? $tour['name'] . ' — ' : '') . $tour['country'];
$auditChanges = [['k' => 'Ország', 'v' => $tour['country']]];
if ($tour['tour_date']) $auditChanges[] = ['k' => 'Dátum',     'v' => $tour['tour_date']];
if ($tour['points'])    $auditChanges[] = ['k' => 'Pontérték', 'v' => (string)$tour['points']];

$pdo->prepare("DELETE FROM tours WHERE id=?")->execute([$id]);
recalcUserStats($pdo);
logAudit($pdo, 'delete', 'tour', $id, $tourLabel, $auditChanges);

flash('success', 'A túra törölve.');
header('Location: ' . BASE_URL . '/admin/tours.php');
exit;
