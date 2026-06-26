<?php
/**
 * Tag által beküldött bejegyzés jóváhagyása — a posztkezelő (vezető/admin) publikálja.
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';
requireLeader(); verifyCsrf();
if (!canManagePosts()) { flash('error', 'Nincs jogosultságod ehhez.'); header('Location: ' . BASE_URL . '/admin/index.php'); exit; }

$pdo = getDb();
ensurePublicSchema($pdo);

$id = (int)($_POST['id'] ?? 0);
if ($id) {
    $pdo->prepare("UPDATE posts SET approval_status = 'approved', published = 1 WHERE id = ?")->execute([$id]);
    flash('success', 'Bejegyzés jóváhagyva és publikálva.');
} else {
    flash('error', 'Érvénytelen bejegyzés.');
}
header('Location: ' . BASE_URL . '/admin/posts.php');
exit;
