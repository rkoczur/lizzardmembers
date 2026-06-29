<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';
requireLeader(); verifyCsrf();
if (!canManageFaq()) { flash('error', 'Nincs jogosultságod ehhez.'); header('Location: ' . BASE_URL . '/admin/index.php'); exit; }

$pdo = getDb();
ensurePublicSchema($pdo);

$id = (int)($_POST['id'] ?? 0);
if ($id) {
    // A kategóriához tartozó kérdések besorolatlanná válnak (az „Egyéb" csoportba kerülnek)
    $pdo->prepare("UPDATE faq SET category_id = NULL WHERE category_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM faq_categories WHERE id = ?")->execute([$id]);
}

flash('success', 'Kategória törölve.');
header('Location: ' . BASE_URL . '/admin/faq.php');
exit;
