<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';
requireLeader(); verifyCsrf();
if (!canManageFaq()) { flash('error', 'Nincs jogosultságod ehhez.'); header('Location: ' . BASE_URL . '/admin/index.php'); exit; }

$pdo = getDb();
ensurePublicSchema($pdo);

$id        = (int)($_POST['id'] ?? 0);
$name      = trim($_POST['name'] ?? '');
$sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));

if (!$name) {
    flash('error', 'A kategória neve kötelező.');
    header('Location: ' . BASE_URL . '/admin/faq.php'); exit;
}

if ($id) {
    $pdo->prepare("UPDATE faq_categories SET name=?, sort_order=? WHERE id=?")
        ->execute([$name, $sortOrder, $id]);
    flash('success', 'Kategória frissítve.');
} else {
    $pdo->prepare("INSERT INTO faq_categories (name, sort_order) VALUES (?,?)")
        ->execute([$name, $sortOrder]);
    flash('success', 'Kategória hozzáadva.');
}

header('Location: ' . BASE_URL . '/admin/faq.php');
exit;
