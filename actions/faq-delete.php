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
if ($id) $pdo->prepare("DELETE FROM faq WHERE id = ?")->execute([$id]);

flash('success', 'Kérdés törölve.');
header('Location: ' . BASE_URL . '/admin/faq.php');
exit;
