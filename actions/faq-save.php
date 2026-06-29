<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';
requireLeader(); verifyCsrf();
if (!canManageFaq()) { flash('error', 'Nincs jogosultságod ehhez.'); header('Location: ' . BASE_URL . '/admin/index.php'); exit; }

$pdo       = getDb();
ensurePublicSchema($pdo);

$id         = (int)($_POST['id']         ?? 0);
$question   = trim($_POST['question']  ?? '');
$answer     = trim($_POST['answer']    ?? '');
$sortOrder  = max(0, (int)($_POST['sort_order'] ?? 0));
$categoryId = (int)($_POST['category_id'] ?? 0) ?: null;

if (!$question || !$answer) {
    flash('error', 'Kérdés és válasz megadása kötelező.');
    $redir = $id ? BASE_URL . '/admin/faq.php?edit=' . $id : BASE_URL . '/admin/faq.php';
    header('Location: ' . $redir); exit;
}

if ($id) {
    $pdo->prepare("UPDATE faq SET question=?, answer=?, sort_order=?, category_id=? WHERE id=?")
        ->execute([$question, $answer, $sortOrder, $categoryId, $id]);
    flash('success', 'Kérdés frissítve.');
} else {
    $pdo->prepare("INSERT INTO faq (question, answer, sort_order, category_id) VALUES (?,?,?,?)")
        ->execute([$question, $answer, $sortOrder, $categoryId]);
    flash('success', 'Kérdés hozzáadva.');
}

header('Location: ' . BASE_URL . '/admin/faq.php');
exit;
