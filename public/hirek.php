<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';

$pdo = getDb();
ensurePublicSchema($pdo);

$posts = $pdo->query("SELECT * FROM posts WHERE category = 'hirek' AND published = 1 ORDER BY created_at DESC")->fetchAll();

$pageTitle     = 'Egyesületi hírek';
$activePubPage = 'hirek';
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap">
  <div class="pub-page-header">
    <h1>Egyesületi hírek</h1>
    <p>Az egyesületi élet legfrissebb hírei és közleményei.</p>
  </div>

  <?php if (empty($posts)): ?>
    <div class="pub-empty-state">
      <div style="font-size:48px;margin-bottom:12px;">📰</div>
      <p>Hamarosan lesznek híreink. Nézz vissza később!</p>
    </div>
  <?php else: ?>
    <div class="pub-post-grid">
      <?php foreach ($posts as $p): ?>
      <article class="pub-post-card">
        <?php if (!empty($p['cover_img'])): ?>
          <img src="<?= BASE_URL ?>/assets/uploads/posts/<?= e($p['cover_img']) ?>" class="pub-post-card-img" alt="<?= e($p['title']) ?>">
        <?php else: ?>
          <div class="pub-post-card-img-placeholder">📰</div>
        <?php endif; ?>
        <div class="pub-post-card-body">
          <div class="pub-post-meta">
            <span class="pub-post-category-badge hirek">Hírek</span>
            <span><?= date('Y.m.d', strtotime($p['created_at'])) ?></span>
          </div>
          <h3><a href="<?= BASE_URL ?>/public/post.php?slug=<?= urlencode($p['slug']) ?>"><?= e($p['title']) ?></a></h3>
          <?php if (!empty($p['excerpt'])): ?>
            <p><?= e($p['excerpt']) ?></p>
          <?php endif; ?>
          <a href="<?= BASE_URL ?>/public/post.php?slug=<?= urlencode($p['slug']) ?>" class="btn btn-ghost btn-sm" style="margin-top:auto;">Olvasd tovább →</a>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
