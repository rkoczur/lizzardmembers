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

$id       = (int)($_POST['id'] ?? 0);
$isNew    = ($id === 0);
$title    = trim($_POST['title']    ?? '');
$slug     = trim($_POST['slug']     ?? '');
$category = in_array($_POST['category'] ?? '', ['hirek','beszmolok']) ? $_POST['category'] : 'hirek';
$excerpt  = trim($_POST['excerpt']  ?? '');
$body     = trim($_POST['body']     ?? '');
$published = !empty($_POST['published']) ? 1 : 0;

// Validate slug
$slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));

if (!$title || !$slug || !$body) {
    flash('error', 'Cím, slug és tartalom megadása kötelező.');
    $redir = $isNew ? BASE_URL . '/admin/post-detail.php?new=1' : BASE_URL . '/admin/post-detail.php?id=' . $id;
    header('Location: ' . $redir); exit;
}

// Cover image upload
$POST_UPLOAD_DIR = __DIR__ . '/../assets/uploads/posts/';
if (!is_dir($POST_UPLOAD_DIR)) mkdir($POST_UPLOAD_DIR, 0755, true);

$coverImg = $isNew ? null : null;
if (!$isNew) {
    $old = $pdo->prepare("SELECT cover_img FROM posts WHERE id = ? LIMIT 1");
    $old->execute([$id]);
    $coverImg = ($old->fetchColumn() ?: null);
}

if (!empty($_POST['delete_cover']) && $coverImg) {
    @unlink($POST_UPLOAD_DIR . $coverImg);
    $coverImg = null;
}

if (!empty($_FILES['cover_img']['tmp_name']) && $_FILES['cover_img']['error'] === UPLOAD_ERR_OK) {
    $allowedMimes = ['image/jpeg','image/png','image/webp'];
    $size         = (int)($_FILES['cover_img']['size'] ?? 0);
    $mime         = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['cover_img']['tmp_name']);
    $ext          = match($mime) { 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', default => '' };
    if ($ext && $size <= 4 * 1024 * 1024 && in_array($mime, $allowedMimes, true)) {
        $newFile = 'post_' . ($isNew ? 'new' : $id) . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['cover_img']['tmp_name'], $POST_UPLOAD_DIR . $newFile)) {
            if ($coverImg) @unlink($POST_UPLOAD_DIR . $coverImg);
            $coverImg = $newFile;
        }
    }
}

if ($isNew) {
    // Check slug uniqueness
    $exists = $pdo->prepare("SELECT id FROM posts WHERE slug = ? LIMIT 1");
    $exists->execute([$slug]);
    if ($exists->fetch()) {
        flash('error', 'Ez a slug már foglalt, válassz másikat.');
        header('Location: ' . BASE_URL . '/admin/post-detail.php?new=1'); exit;
    }
    $pdo->prepare("INSERT INTO posts (title, slug, category, excerpt, body, cover_img, published, created_by) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$title, $slug, $category, $excerpt ?: null, $body, $coverImg, $published, getCurrentUserId()]);
    $newId = (int)$pdo->lastInsertId();
    // Fix cover filename with real id
    if ($coverImg && strpos($coverImg, '_new_') !== false) {
        $fixed = str_replace('_new_', '_' . $newId . '_', $coverImg);
        rename($POST_UPLOAD_DIR . $coverImg, $POST_UPLOAD_DIR . $fixed);
        $pdo->prepare("UPDATE posts SET cover_img = ? WHERE id = ?")->execute([$fixed, $newId]);
    }
    flash('success', 'Poszt sikeresen létrehozva.');
    header('Location: ' . BASE_URL . '/admin/post-detail.php?id=' . $newId);
} else {
    $pdo->prepare("UPDATE posts SET title=?, slug=?, category=?, excerpt=?, body=?, cover_img=?, published=? WHERE id=?")
        ->execute([$title, $slug, $category, $excerpt ?: null, $body, $coverImg, $published, $id]);
    flash('success', 'Poszt sikeresen mentve.');
    header('Location: ' . BASE_URL . '/admin/post-detail.php?id=' . $id);
}
exit;
