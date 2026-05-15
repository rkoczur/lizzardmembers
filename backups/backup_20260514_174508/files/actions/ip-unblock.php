<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

verifyCsrf();

$pdo = getDb();
$ip  = $_POST['ip'] ?? '';
if ($ip === '') {
    header('Location: ' . BASE_URL . '/admin/security.php');
    exit;
}

$pdo->prepare("DELETE FROM ip_blocks WHERE ip = ?")
    ->execute([$ip]);

logAudit($pdo, 'delete', 'member', 0, 'IP: ' . $ip, [
    ['k' => 'IP-zárolás feloldva', 'v' => $ip],
]);

flash('success', 'A ' . $ip . ' IP-cím zárolása feloldva.');
header('Location: ' . BASE_URL . '/admin/security.php');
exit;
