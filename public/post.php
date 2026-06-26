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

$stmt = $pdo->prepare("
    SELECT p.*, TRIM(CONCAT(COALESCE(au.lastname, ''), ' ', COALESCE(au.firstname, ''))) AS author_name,
           au.profile_picture AS author_avatar
    FROM posts p
    LEFT JOIN users au ON au.id = COALESCE(p.author_id, p.created_by)
    WHERE p.slug = ? AND p.published = 1 LIMIT 1
");
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

// SEO meta a poszthoz
$metaDescription = trim((string)($post['excerpt'] ?? ''));
if ($metaDescription === '') {
    $metaDescription = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($post['body'] ?? ''))), 0, 160);
}
$metaKeywords = trim((string)($post['meta_keywords'] ?? ''));
$ogType       = 'article';
if (!empty($post['cover_img'])) {
    $ogImage = BASE_URL . '/assets/uploads/posts/' . $post['cover_img'];
}

include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap-narrow">
  <div style="margin-bottom:20px;">
    <a href="<?= $backUrl ?>" class="btn btn-secondary btn-sm">← <?= e($backLabel) ?></a>
  </div>

  <?php if (!empty($post['cover_img'])): ?>
    <img src="<?= BASE_URL ?>/assets/uploads/posts/<?= e($post['cover_img']) ?>"
         style="width:100%;max-height:420px;object-fit:cover;border-radius:var(--radius);margin-bottom:28px;display:block;"
         alt="<?= e($post['cover_alt'] ?: $post['title']) ?>">
  <?php endif; ?>

  <div class="pub-post-meta" style="margin-bottom:12px;">
    <span class="pub-post-category-badge <?= e($post['category']) ?>">
      <?= $post['category'] === 'beszmolok' ? 'Élményblog' : 'Hírek' ?>
    </span>
    <span><?= date('Y. F j.', strtotime($post['created_at'])) ?></span>
  </div>

  <?php $hasAuthor = !empty(trim($post['author_name'] ?? '')); ?>
  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:20px;flex-wrap:wrap;margin-bottom:24px;">
    <h1 style="flex:1 1 300px;min-width:0;font-size:clamp(22px,4vw,34px);font-weight:800;color:var(--sidebar-bg);letter-spacing:-.3px;margin:0;line-height:1.3;overflow-wrap:break-word;">
      <?= e($post['title']) ?>
    </h1>
    <?php if ($hasAuthor): ?>
    <div style="flex:0 0 auto;display:flex;align-items:center;gap:12px;">
      <div style="line-height:1.45;text-align:right;">
        <div style="font-size:11px;color:var(--text-muted,#7a7269);text-transform:uppercase;letter-spacing:.06em;font-weight:600;">Szerző</div>
        <div style="font-weight:800;font-size:16px;color:var(--sidebar-bg,#1a3d39);white-space:nowrap;"><?= e(trim($post['author_name'])) ?></div>
      </div>
      <img src="<?= e(getAvatarUrl($post['author_avatar'] ?? null)) ?>" alt="" style="width:46px;height:46px;border-radius:50%;object-fit:cover;flex-shrink:0;">
    </div>
    <?php endif; ?>
  </div>

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
