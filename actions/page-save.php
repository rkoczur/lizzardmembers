<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';
requireLeader(); verifyCsrf();

$pdo = getDb();
ensurePublicSchema($pdo);

$slug  = trim($_POST['slug']  ?? '');
if (!canManagePages($slug)) { flash('error', 'Nincs jogosultságod ezt a lapot szerkeszteni.'); header('Location: ' . BASE_URL . '/admin/pages.php'); exit; }
$title = trim($_POST['title'] ?? '');
$body  = $_POST['body'] ?? '';

if (!$slug || !$title) {
    flash('error', 'Hiányzó adatok.');
    header('Location: ' . BASE_URL . '/admin/pages.php?slug=' . urlencode($slug));
    exit;
}

$pdo->prepare("UPDATE pages SET title = ?, body = ? WHERE slug = ?")
    ->execute([$title, $body, $slug]);

flash('success', 'Lap sikeresen mentve.');
header('Location: ' . BASE_URL . '/admin/pages.php?slug=' . urlencode($slug));
exit;
