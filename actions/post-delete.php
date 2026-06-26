<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin(); verifyCsrf();
if (!canCreatePosts()) { flash('error', 'Nincs jogosultságod ehhez.'); header('Location: ' . BASE_URL . '/user/index.php'); exit; }

$pdo = getDb();
$isUserCtx = (($_POST['ctx'] ?? '') === 'user');
$listUrl   = $isUserCtx ? BASE_URL . '/user/posts.php' : BASE_URL . '/admin/posts.php';

$id  = (int)($_POST['id'] ?? 0);
if (!$id) { header('Location: ' . $listUrl); exit; }

$stmt = $pdo->prepare("SELECT cover_img, created_by FROM posts WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) { header('Location: ' . $listUrl); exit; }

// Ownership: aki nem teljes posztkezelő, csak a SAJÁT bejegyzését törölheti
if (!canManagePosts() && (int)($row['created_by'] ?? 0) !== getCurrentUserId()) {
    flash('error', 'Csak a saját bejegyzésedet törölheted.');
    header('Location: ' . $listUrl); exit;
}

if (!empty($row['cover_img'])) {
    @unlink(__DIR__ . '/../assets/uploads/posts/' . $row['cover_img']);
}

$pdo->prepare("DELETE FROM posts WHERE id = ?")->execute([$id]);

flash('success', 'Poszt törölve.');
header('Location: ' . $listUrl);
exit;
