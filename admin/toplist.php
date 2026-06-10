<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminOrVezeto();

$pendingCount = 0;
try {
    $pendingCount = (int)getDb()->query("SELECT COUNT(*) FROM member_applications WHERE status='pending'")->fetchColumn();
} catch (Throwable) {}

$pageTitle  = 'Toplista';
$activePage = 'toplist';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="tab-nav">
  <a href="<?= BASE_URL ?>/admin/members.php" class="tab-link">Tagok</a>
  <?php if (isAdmin()): ?>
  <a href="<?= BASE_URL ?>/admin/members.php?tab=applications" class="tab-link" style="display:inline-flex;align-items:center;gap:6px;">
    Jelentkezések
    <?php if ($pendingCount > 0): ?>
      <span class="badge-counter badge-counter-danger"><?= $pendingCount ?></span>
    <?php endif; ?>
  </a>
  <?php endif; ?>
  <a href="<?= BASE_URL ?>/admin/toplist.php" class="tab-link active">Toplista</a>
</div>

<?php
$hideCandidates = false; // admin nézetben a jelölt tagok is látszanak
include __DIR__ . '/../includes/toplist-content.php';
include __DIR__ . '/../includes/admin-footer.php';
