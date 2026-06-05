<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';
requireAdmin();
verifyCsrf();

$pdo       = getDb();
ensurePublicSchema($pdo);

$id        = (int)($_POST['id']         ?? 0);
$question  = trim($_POST['question']  ?? '');
$answer    = trim($_POST['answer']    ?? '');
$sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));

if (!$question || !$answer) {
    flash('error', 'Kérdés és válasz megadása kötelező.');
    $redir = $id ? BASE_URL . '/admin/faq.php?edit=' . $id : BASE_URL . '/admin/faq.php';
    header('Location: ' . $redir); exit;
}

if ($id) {
    $pdo->prepare("UPDATE faq SET question=?, answer=?, sort_order=? WHERE id=?")
        ->execute([$question, $answer, $sortOrder, $id]);
    flash('success', 'Kérdés frissítve.');
} else {
    $pdo->prepare("INSERT INTO faq (question, answer, sort_order) VALUES (?,?,?)")
        ->execute([$question, $answer, $sortOrder]);
    flash('success', 'Kérdés hozzáadva.');
}

header('Location: ' . BASE_URL . '/admin/faq.php');
exit;
