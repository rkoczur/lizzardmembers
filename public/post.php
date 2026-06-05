<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';

$pdo = getDb();
ensurePublicSchema($pdo);

$slug = trim($_GET['slug'] ?? '');
if (!$slug) {
    header('Location: ' . BASE_URL . '/public/hirek.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM posts WHERE slug = ? AND published = 1 LIMIT 1");
$stmt->execute([$slug]);
$post = $stmt->fetch();
if (!$post) {
    header('Location: ' . BASE_URL . '/public/hirek.php');
    exit;
}

$backUrl       = $post['category'] === 'beszmolok'
    ? BASE_URL . '/public/beszmolok.php'
    : BASE_URL . '/public/hirek.php';
$backLabel     = $post['category'] === 'beszmolok' ? 'Élményblog' : 'Hírek';

$pageTitle     = $post['title'];
$activePubPage = $post['category'] === 'beszmolok' ? 'beszmolok' : 'hirek';
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap-narrow">
  <div style="margin-bottom:20px;">
    <a href="<?= $backUrl ?>" class="btn btn-secondary btn-sm">← <?= e($backLabel) ?></a>
  </div>

  <?php if (!empty($post['cover_img'])): ?>
    <img src="<?= BASE_URL ?>/assets/uploads/posts/<?= e($post['cover_img']) ?>"
         style="width:100%;max-height:420px;object-fit:cover;border-radius:var(--radius);margin-bottom:28px;display:block;"
         alt="<?= e($post['title']) ?>">
  <?php endif; ?>

  <div class="pub-post-meta" style="margin-bottom:12px;">
    <span class="pub-post-category-badge <?= e($post['category']) ?>">
      <?= $post['category'] === 'beszmolok' ? 'Élményblog' : 'Hírek' ?>
    </span>
    <span><?= date('Y. F j.', strtotime($post['created_at'])) ?></span>
  </div>

  <h1 style="font-size:clamp(22px,4vw,34px);font-weight:800;color:var(--sidebar-bg);letter-spacing:-.3px;margin-bottom:20px;line-height:1.3;">
    <?= e($post['title']) ?>
  </h1>

  <?php if (!empty($post['excerpt'])): ?>
    <p style="font-size:17px;color:var(--text-muted);line-height:1.7;margin-bottom:28px;border-left:4px solid var(--primary);padding-left:16px;">
      <?= e($post['excerpt']) ?>
    </p>
  <?php endif; ?>

  <div class="pub-post-body">
    <?= $post['body'] ?>
  </div>

  <div style="margin-top:40px;padding-top:24px;border-top:1px solid var(--border);">
    <a href="<?= $backUrl ?>" class="btn btn-ghost">← Vissza a <?= e(strtolower($backLabel)) ?>hez</a>
  </div>
</div>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
