<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';
requireAdmin();

$pdo = getDb();
ensurePublicSchema($pdo);

$isNew = isset($_GET['new']);
$id    = $isNew ? 0 : (int)($_GET['id'] ?? 0);

$post = null;
if (!$isNew && $id) {
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $post = $stmt->fetch();
    if (!$post) {
        header('Location: ' . BASE_URL . '/admin/posts.php');
        exit;
    }
}

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$pageTitle  = $isNew ? 'Új poszt' : e($post['title'] ?? 'Poszt szerkesztése');
$activePage = 'website';
include __DIR__ . '/../includes/admin-header.php';
?>

<?php if ($flash_success): ?>
  <div class="alert alert-success" data-auto-dismiss><?= e($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_error): ?>
  <div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/admin-website-nav.php'; ?>

<div class="page-header">
  <div class="flex items-center gap-2">
    <a href="<?= BASE_URL ?>/admin/posts.php" class="btn btn-secondary btn-sm">← Vissza</a>
    <h1><?= $isNew ? 'Új poszt' : e($post['title'] ?? '') ?></h1>
  </div>
  <?php if (!$isNew && !empty($post['published'])): ?>
    <a href="<?= BASE_URL ?>/public/post.php?slug=<?= urlencode($post['slug'] ?? '') ?>" target="_blank" class="btn btn-ghost btn-sm">Megtekintés →</a>
  <?php endif; ?>
</div>

<div class="card" style="max-width:900px;">
  <div class="card-body">
    <form method="post" enctype="multipart/form-data" action="<?= BASE_URL ?>/actions/post-save.php">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <?php if (!$isNew): ?>
        <input type="hidden" name="id" value="<?= (int)$id ?>">
      <?php endif; ?>

      <div class="form-grid">
        <div class="form-group full">
          <label>Cím <span style="color:var(--danger)">*</span></label>
          <input type="text" name="title" value="<?= e($post['title'] ?? '') ?>" required id="post-title">
        </div>
        <div class="form-group">
          <label>URL-slug <span style="color:var(--danger)">*</span></label>
          <input type="text" name="slug" value="<?= e($post['slug'] ?? '') ?>" required id="post-slug" pattern="[a-z0-9\-]+" placeholder="pl. kirando-egy-hegyre">
          <small style="color:var(--text-muted);font-size:12px;">Csak kisbetű, szám és kötőjel! Létrehozás után ne módosítsd.</small>
        </div>
        <div class="form-group">
          <label>Kategória</label>
          <select name="category">
            <option value="hirek"     <?= ($post['category'] ?? 'hirek') === 'hirek'     ? 'selected' : '' ?>>Hírek</option>
            <option value="beszmolok" <?= ($post['category'] ?? '')       === 'beszmolok' ? 'selected' : '' ?>>Élményblog</option>
          </select>
        </div>
        <div class="form-group full">
          <label>Kivonat (rövid leírás a kártyán)</label>
          <textarea name="excerpt" rows="2"><?= e($post['excerpt'] ?? '') ?></textarea>
        </div>
        <div class="form-group full">
          <label>Tartalom (HTML megengedett) <span style="color:var(--danger)">*</span></label>
          <textarea name="body" rows="22" style="font-family:monospace;font-size:13px;" required><?= e($post['body'] ?? '') ?></textarea>
          <small style="color:var(--text-muted);font-size:12px;">Formázáshoz HTML: &lt;h2&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;li&gt;, &lt;strong&gt;, &lt;img src=""&gt;, &lt;a href=""&gt;</small>
        </div>
        <div class="form-group">
          <label>Borítókép</label>
          <?php if (!empty($post['cover_img'])): ?>
            <div style="margin-bottom:8px;">
              <img src="<?= BASE_URL ?>/assets/uploads/posts/<?= e($post['cover_img']) ?>" style="height:80px;border-radius:6px;object-fit:cover;">
              <label style="display:flex;align-items:center;gap:6px;margin-top:6px;font-size:12px;font-weight:normal;text-transform:none;letter-spacing:0;cursor:pointer;">
                <input type="checkbox" name="delete_cover" value="1"> Töröld a borítóképet
              </label>
            </div>
          <?php endif; ?>
          <input type="file" name="cover_img" accept="image/jpeg,image/png,image/webp">
          <small style="color:var(--text-muted);font-size:12px;">JPG, PNG vagy WebP; max. 4 MB.</small>
        </div>
        <div class="form-group">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;text-transform:none;letter-spacing:0;font-weight:600;">
            <input type="checkbox" name="published" value="1" <?= !empty($post['published']) ? 'checked' : '' ?>>
            Publikált (látható a weboldalon)
          </label>
        </div>
      </div>

      <div style="margin-top:24px;display:flex;gap:12px;">
        <button type="submit" class="btn btn-primary">Mentés</button>
        <?php if (!$isNew): ?>
          <form method="post" action="<?= BASE_URL ?>/actions/post-delete.php" style="margin:0;"
                onsubmit="return confirm('Biztosan törölni szeretnéd ezt a posztot?')">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="id" value="<?= (int)$id ?>">
            <button type="submit" class="btn btn-danger">Törlés</button>
          </form>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/admin/posts.php" class="btn btn-ghost">Mégse</a>
      </div>
    </form>
  </div>
</div>

<script>
// Auto-generate slug from title for new posts
var titleEl = document.getElementById('post-title');
var slugEl  = document.getElementById('post-slug');
if (titleEl && slugEl && !slugEl.value) {
  titleEl.addEventListener('input', function () {
    slugEl.value = this.value
      .toLowerCase()
      .normalize('NFD').replace(/[̀-ͯ]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');
  });
}
</script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
