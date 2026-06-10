<?php
/**
 * Mentés törlése. Csak fő admin. Szigorú fájlnév-validálás, csak a backups/-on belül.
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit-schema.php';
requireAdminOrVezeto();
if (!isRootAdmin()) { header('Location: ' . BASE_URL . '/user/index.php'); exit; }
verifyCsrf();

$backupPage = BASE_URL . '/admin/backup.php';
$name = basename((string)($_POST['name'] ?? ''));

if (!preg_match('/^backup_[0-9_\-]+\.zip$/', $name)) {
    flash('error', 'Érvénytelen fájlnév.');
    header('Location: ' . $backupPage);
    exit;
}

$backupDir = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups');
$path      = $backupDir !== false ? realpath($backupDir . DIRECTORY_SEPARATOR . $name) : false;

if ($backupDir === false || $path === false
    || strpos($path, $backupDir . DIRECTORY_SEPARATOR) !== 0
    || !is_file($path)) {
    flash('error', 'A mentés nem található.');
    header('Location: ' . $backupPage);
    exit;
}

if (@unlink($path)) {
    $pdo = getDb();
    ensureAuditSchema($pdo);
    logAudit($pdo, 'delete', 'backup', 0, 'Mentés törölve: ' . $name);
    flash('success', 'Mentés törölve: ' . $name);
} else {
    flash('error', 'A mentés törlése nem sikerült.');
}
header('Location: ' . $backupPage);
exit;
