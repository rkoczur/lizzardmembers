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

$type  = in_array($_POST['preset_type'] ?? '', ['category','partner','account'], true) ? $_POST['preset_type'] : '';
$value = trim($_POST['value'] ?? '');

if ($type === '' || $value === '') {
    flash('error', 'Hiányzó érték.');
    header('Location: ' . BASE_URL . '/admin/bookkeeping.php?tab=presets');
    exit;
}

$pdo->prepare("INSERT IGNORE INTO transaction_presets (preset_type, value) VALUES (?, ?)")
    ->execute([$type, $value]);

flash('success', 'Érték hozzáadva.');
header('Location: ' . BASE_URL . '/admin/bookkeeping.php?tab=presets');
exit;
