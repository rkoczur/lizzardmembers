<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/rank-history.php';
requireUser();

$pdo    = getDb();
$userId = getCurrentUserId();

$stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
$stmt->execute([$userId]);

$ranks       = getRankHistory($pdo, $userId);
$rankPoints  = (int)$stmt->fetchColumn();
$rankTourUrl = BASE_URL . '/user/tour-detail.php?id=';

$pageTitle  = 'Ranglétra';
$activePage = 'ranks';
include __DIR__ . '/../includes/user-header.php';
?>

<div class="page-header">
  <h1>Ranglétra</h1>
</div>

<?php include __DIR__ . '/../includes/rank-history-card.php'; ?>

<?php include __DIR__ . '/../includes/user-footer.php'; ?>
