<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/app-settings-schema.php';
requireAdmin();
verifyCsrf();

$pdo = getDb();
ensureAppSettingsSchema($pdo);

$host       = trim($_POST['smtp_host']       ?? '');
$port       = max(1, min(65535, (int)($_POST['smtp_port'] ?? 587)));
$user       = trim($_POST['smtp_user']       ?? '');
$fromEmail  = trim($_POST['smtp_from_email'] ?? '');
$fromName   = trim($_POST['smtp_from_name']  ?? '');
$encryption = in_array($_POST['smtp_encryption'] ?? '', ['', 'tls', 'ssl']) ? $_POST['smtp_encryption'] : 'tls';

// Only update password if a new one was entered
$newPass = $_POST['smtp_pass'] ?? '';

saveSetting($pdo, 'smtp_host',       $host);
saveSetting($pdo, 'smtp_port',       (string)$port);
saveSetting($pdo, 'smtp_user',       $user);
saveSetting($pdo, 'smtp_from_email', $fromEmail ?: $user);
saveSetting($pdo, 'smtp_from_name',  $fromName);
saveSetting($pdo, 'smtp_encryption', $encryption);

if ($newPass !== '') {
    saveSetting($pdo, 'smtp_pass', $newPass);
}

flash('success', 'SMTP beállítások mentve.');
header('Location: ' . BASE_URL . '/admin/settings.php');
exit;
