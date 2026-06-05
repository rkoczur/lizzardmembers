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
if (!$id) { header('Location: ' . BASE_URL . '/admin/posts.php'); exit; }

$stmt = $pdo->prepare("SELECT cover_img FROM posts WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$coverImg = $stmt->fetchColumn();
if ($coverImg) {
    @unlink(__DIR__ . '/../assets/uploads/posts/' . $coverImg);
}

$pdo->prepare("DELETE FROM posts WHERE id = ?")->execute([$id]);

flash('success', 'Poszt törölve.');
header('Location: ' . BASE_URL . '/admin/posts.php');
exit;
