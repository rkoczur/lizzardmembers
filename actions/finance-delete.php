<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLeader(); verifyCsrf();
if (!canManageFinances()) { flash('error', 'Nincs jogosultságod ehhez.'); header('Location: ' . BASE_URL . '/admin/index.php'); exit; }

$pdo = getDb();
$id  = (int)($_POST['id'] ?? 0);
if ($id) $pdo->prepare("DELETE FROM finances WHERE id = ?")->execute([$id]);

flash('success', 'Tétel törölve.');
header('Location: ' . BASE_URL . '/admin/finances.php');
exit;
