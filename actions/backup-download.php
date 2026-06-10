<?php
/**
 * Mentés letöltése — kizárólag PHP-n keresztül (a backups/ közvetlenül nem érhető el).
 * Csak fő admin. A fájlnevet szigorúan validáljuk (path traversal kizárva).
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminOrVezeto();
if (!isRootAdmin()) { header('Location: ' . BASE_URL . '/user/index.php'); exit; }
verifyCsrf();

$name = basename((string)($_POST['name'] ?? ''));
if (!preg_match('/^backup_[0-9_\-]+\.zip$/', $name)) {
    http_response_code(400);
    exit('Érvénytelen fájlnév.');
}

$backupDir = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups');
$path      = $backupDir !== false ? realpath($backupDir . DIRECTORY_SEPARATOR . $name) : false;

if ($backupDir === false || $path === false
    || strpos($path, $backupDir . DIRECTORY_SEPARATOR) !== 0
    || !is_file($path)) {
    http_response_code(404);
    exit('A mentés nem található.');
}

while (ob_get_level() > 0) { ob_end_clean(); }

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
readfile($path);
exit;
