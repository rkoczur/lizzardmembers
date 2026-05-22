<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    exit('Érvénytelen azonosító.');
}

$pdo  = getDb();
$stmt = $pdo->prepare("SELECT html_body FROM email_log WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$row  = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    exit('Az e-mail nem található.');
}

header('Content-Type: text/html; charset=UTF-8');
header('X-Frame-Options: SAMEORIGIN');
echo $row['html_body'];
