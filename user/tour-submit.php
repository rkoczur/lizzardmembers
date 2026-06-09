<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tours-schema.php';
requireUser();

$pdo = getDb();
ensureToursSchema($pdo);

$currentUserId = getCurrentUserId();
$allMembersStmt = $pdo->prepare("SELECT id, firstname, lastname FROM users WHERE id != ? ORDER BY lastname, firstname");
$allMembersStmt->execute([$currentUserId]);
$allMembers = $allMembersStmt->fetchAll();
$countries  = getCountries($pdo);

$flash_error = getFlash('error');
$old = $_SESSION['tour_submit_old'] ?? [];
unset($_SESSION['tour_submit_old']);

$pageTitle  = 'Túra beküldése';
$activePage = 'tours';
include __DIR__ . '/../includes/user-header.php';
?>

<?php if ($flash_error): ?>
  <div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="page-header">
  <div class="flex items-center gap-2">
    <a href="<?= BASE_URL ?>/user/tours.php" class="btn btn-secondary btn-sm">← Vissza</a>
    <h1>Egyéni túra beküldése</h1>
  </div>
</div>

<?php include __DIR__ . '/../includes/tour-submit-form.php'; ?>

<?php include __DIR__ . '/../includes/user-footer.php'; ?>
