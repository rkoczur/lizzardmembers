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
if (!$id) {
    header('Location: ' . BASE_URL . '/admin/members.php');
    exit;
}

$nameStmt = $pdo->prepare("SELECT CONCAT(lastname, ' ', firstname) AS name FROM users WHERE id = ?");
$nameStmt->execute([$id]);
$name = $nameStmt->fetchColumn() ?: 'Ismeretlen tag';

$pdo->prepare("UPDATE users SET login_attempts = 0, locked_at = NULL WHERE id = ?")
    ->execute([$id]);

logAudit($pdo, 'update', 'member', $id, $name, [
    ['k' => 'Fiókzárolás', 'f' => 'Zárolt', 't' => 'Feloldva'],
]);

flash('success', $name . ' fiókjának zárolása feloldva.');
header('Location: ' . BASE_URL . '/admin/member-detail.php?id=' . $id);
exit;
