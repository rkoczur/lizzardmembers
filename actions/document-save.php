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

$category   = trim($_POST['category']   ?? '');
$title      = trim($_POST['title']      ?? '');
$sortOrder  = max(0, (int)($_POST['sort_order'] ?? 0));

if (!$category || !$title) {
    flash('error', 'Kategória és cím megadása kötelező.');
    header('Location: ' . BASE_URL . '/admin/documents.php'); exit;
}

$DOC_DIR = __DIR__ . '/../assets/uploads/docs/';
if (!is_dir($DOC_DIR)) mkdir($DOC_DIR, 0755, true);

if (empty($_FILES['document']['tmp_name']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    flash('error', 'Nem sikerült a fájl fogadása.');
    header('Location: ' . BASE_URL . '/admin/documents.php'); exit;
}

$size = (int)($_FILES['document']['size'] ?? 0);
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['document']['tmp_name']);
$ext  = strtolower(pathinfo($_FILES['document']['name'] ?? '', PATHINFO_EXTENSION));

if ($ext !== 'pdf' || $size > 20 * 1024 * 1024 || !in_array($mime, ['application/pdf','application/x-pdf'], true)) {
    flash('error', 'Csak PDF fájl tölthető fel, maximum 20 MB.');
    header('Location: ' . BASE_URL . '/admin/documents.php'); exit;
}

$safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($_FILES['document']['name'], PATHINFO_FILENAME));
$newFile = 'doc_' . time() . '_' . $safe . '.pdf';
if (!move_uploaded_file($_FILES['document']['tmp_name'], $DOC_DIR . $newFile)) {
    flash('error', 'Nem sikerült a fájl mentése.');
    header('Location: ' . BASE_URL . '/admin/documents.php'); exit;
}

$pdo->prepare("INSERT INTO documents (category, title, filename, sort_order) VALUES (?,?,?,?)")
    ->execute([$category, $title, $newFile, $sortOrder]);

flash('success', 'Dokumentum sikeresen feltöltve.');
header('Location: ' . BASE_URL . '/admin/documents.php');
exit;
