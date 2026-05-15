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

if ($id === getCurrentUserId()) {
    flash('error', 'Saját fiókodat nem törölheted.');
    header('Location: ' . BASE_URL . '/admin/members.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, lastname, firstname FROM users WHERE id = ?");
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    flash('error', 'A tag nem található.');
    header('Location: ' . BASE_URL . '/admin/members.php');
    exit;
}

$label = $member['lastname'] . ' ' . $member['firstname'];
logAudit($pdo, 'delete', 'member', $id, $label);

$pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

flash('success', $label . ' sikeresen törölve.');
header('Location: ' . BASE_URL . '/admin/members.php');
exit;
