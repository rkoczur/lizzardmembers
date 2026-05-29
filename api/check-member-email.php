<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$email = trim($_GET['email'] ?? '');
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['registered' => false]);
    exit;
}

$pdo  = getDb();
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND active = 1 LIMIT 1");
$stmt->execute([$email]);
echo json_encode(['registered' => (bool)$stmt->fetch()]);
