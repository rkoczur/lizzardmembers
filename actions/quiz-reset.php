<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

verifyCsrf();

$pdo    = getDb();
$userId = getCurrentUserId();

unset($_SESSION['quiz_q']);

$stmt = $pdo->prepare("DELETE FROM quiz_scores WHERE user_id = ?");
$stmt->execute([$userId]);

$stmt = $pdo->prepare("DELETE FROM quiz_games WHERE user_id = ?");
$stmt->execute([$userId]);

flash('success', 'A kvíz pontjaid és köreid törölve lettek, újra válaszolhatsz minden kérdésre.');
header('Location: ' . BASE_URL . '/user/quiz.php');
exit;
