<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mtsz-schema.php';
requireLeader();
verifyCsrf();

if (!canManageMtsz()) {
    flash('error', 'Nincs jogosultságod MTSZ minősítés törléséhez.');
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

$pdo = getDb();
ensureMtszSchema($pdo);

$id = (int)($_POST['id'] ?? 0);

$row = $pdo->prepare("
    SELECT q.*, u.lastname, u.firstname
    FROM mtsz_qualifications q
    JOIN users u ON u.id = q.user_id
    WHERE q.id = ? LIMIT 1
");
$row->execute([$id]);
$row = $row->fetch();

if (!$row) {
    flash('error', 'A minősítés nem található.');
    header('Location: ' . BASE_URL . '/admin/members.php');
    exit;
}

$pdo->prepare("DELETE FROM mtsz_qualifications WHERE id = ?")->execute([$id]);

logAudit($pdo, 'delete', 'member', (int)$row['user_id'],
    trim($row['lastname'] . ' ' . $row['firstname']) . ' — MTSZ minősítés', [
        'Törölt fokozat'           => mtszGradeLabel($row['grade']),
        'MTSZ nyilvántartási szám' => $row['reg_number'],
        'Megszerzés dátuma'        => $row['awarded_on'],
    ]);

flash('success', 'MTSZ minősítés törölve.');
header('Location: ' . BASE_URL . '/admin/member-detail.php?id=' . (int)$row['user_id'] . '#mtsz');
exit;
