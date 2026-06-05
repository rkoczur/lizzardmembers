<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';
requireAdmin();
verifyCsrf();

$pdo = getDb();
ensurePublicSchema($pdo);

$HERO_DIR = __DIR__ . '/../assets/uploads/hero/';
if (!is_dir($HERO_DIR)) mkdir($HERO_DIR, 0755, true);

// Load current filename
$stmt = $pdo->prepare("SELECT body FROM pages WHERE slug = 'hero-image' LIMIT 1");
$stmt->execute();
$currentFile = trim($stmt->fetchColumn() ?: '');

// Delete existing if requested
if (!empty($_POST['delete_hero'])) {
    if ($currentFile && file_exists($HERO_DIR . $currentFile)) {
        @unlink($HERO_DIR . $currentFile);
    }
    $pdo->prepare("UPDATE pages SET body = '' WHERE slug = 'hero-image'")->execute();
    flash('success', 'Háttérkép törölve.');
    header('Location: ' . BASE_URL . '/admin/pages.php?slug=hero-image'); exit;
}

// Upload new image
if (empty($_FILES['hero_image']['tmp_name']) || $_FILES['hero_image']['error'] !== UPLOAD_ERR_OK) {
    flash('error', 'Nem érkezett fájl.');
    header('Location: ' . BASE_URL . '/admin/pages.php?slug=hero-image'); exit;
}

$allowedMimes = ['image/jpeg','image/png','image/webp'];
$size = (int)($_FILES['hero_image']['size'] ?? 0);
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['hero_image']['tmp_name']);
$ext  = match($mime) { 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', default => '' };

if (!$ext || $size > 8 * 1024 * 1024 || !in_array($mime, $allowedMimes, true)) {
    flash('error', 'Csak JPG, PNG vagy WebP kép tölthető fel, maximum 8 MB.');
    header('Location: ' . BASE_URL . '/admin/pages.php?slug=hero-image'); exit;
}

$newFile = 'hero_' . time() . '.' . $ext;
if (!move_uploaded_file($_FILES['hero_image']['tmp_name'], $HERO_DIR . $newFile)) {
    flash('error', 'Nem sikerült a kép mentése.');
    header('Location: ' . BASE_URL . '/admin/pages.php?slug=hero-image'); exit;
}

// Delete old file after successful upload
if ($currentFile && $currentFile !== $newFile && file_exists($HERO_DIR . $currentFile)) {
    @unlink($HERO_DIR . $currentFile);
}

$pdo->prepare("UPDATE pages SET body = ? WHERE slug = 'hero-image'")->execute([$newFile]);

flash('success', 'Háttérkép sikeresen feltöltve.');
header('Location: ' . BASE_URL . '/admin/pages.php?slug=hero-image');
exit;
