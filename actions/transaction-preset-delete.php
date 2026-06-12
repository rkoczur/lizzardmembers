<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/bookkeeping-schema.php';
requireLogin();
if (!canManageFinances()) { flash('error', 'Nincs jogosultságod ehhez.'); header('Location: ' . BASE_URL . '/admin/index.php'); exit; }
verifyCsrf();

$pdo = getDb();
ensureBookkeepingSchema($pdo);

$id = (int)($_POST['id'] ?? 0);
$pdo->prepare("DELETE FROM transaction_presets WHERE id = ?")->execute([$id]);

flash('success', 'Érték törölve.');
header('Location: ' . BASE_URL . '/admin/bookkeeping.php?tab=presets');
exit;
