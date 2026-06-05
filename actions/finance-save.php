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

$year      = max(2000, (int)($_POST['year']      ?? date('Y')));
$category  = in_array($_POST['category'] ?? '', ['income','expense']) ? $_POST['category'] : 'income';
$label     = trim($_POST['label']     ?? '');
$amount    = max(0, (int)($_POST['amount']    ?? 0));
$sortOrder = max(0, (int)($_POST['sort_order'] ?? 0));

if (!$label) {
    flash('error', 'Megnevezés megadása kötelező.');
    header('Location: ' . BASE_URL . '/admin/finances.php'); exit;
}

$pdo->prepare("INSERT INTO finances (year, category, label, amount, sort_order) VALUES (?,?,?,?,?)")
    ->execute([$year, $category, $label, $amount, $sortOrder]);

flash('success', 'Tétel hozzáadva.');
header('Location: ' . BASE_URL . '/admin/finances.php');
exit;
