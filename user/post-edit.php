<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';
requireUser();
if (!canCreatePosts()) { header('Location: ' . BASE_URL . '/user/index.php'); exit; }
if (canManagePosts()) { header('Location: ' . BASE_URL . '/admin/posts.php'); exit; }

$pdo = getDb();
ensurePublicSchema($pdo);

$isNew = isset($_GET['new']);
$id    = $isNew ? 0 : (int)($_GET['id'] ?? 0);

$post = null;
if (!$isNew && $id) {
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $post = $stmt->fetch();
    // Csak a saját bejegyzését szerkesztheti (a teljes posztkezelők az admin felületen)
    if (!$post || (int)$post['created_by'] !== getCurrentUserId()) {
        header('Location: ' . BASE_URL . '/user/posts.php');
        exit;
    }
}

$flash_error = getFlash('error');

$pageTitle  = $isNew ? 'Új bejegyzés' : 'Bejegyzés szerkesztése';
$activePage = 'posts';
include __DIR__ . '/../includes/user-header.php';
?>

<?php if ($flash_error): ?><div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div><?php endif; ?>

<div class="page-header">
  <h1><?= $isNew ? 'Új bejegyzés' : 'Bejegyzés szerkesztése' ?></h1>
  <a href="<?= BASE_URL ?>/user/posts.php" class="btn btn-ghost btn-sm">← Vissza</a>
</div>

<?php if (!$isNew):
  $st = $post['approval_status'] ?? 'draft';
  if ($st === 'pending') { $stLabel = '⏳ Jóváhagyásra vár'; $stCls = 'badge-overdue'; }
  elseif ($st === 'approved' && !empty($post['published'])) { $stLabel = '✓ Publikálva'; $stCls = 'badge-active'; }
  elseif ($st === 'approved') { $stLabel = '✓ Jóváhagyva'; $stCls = 'badge-active'; }
  else { $stLabel = 'Vázlat'; $stCls = 'badge-inactive'; }
?>
<p style="margin:0 0 16px;">Állapot: <span class="badge <?= $stCls ?>"><?= $stLabel ?></span></p>
<?php endif; ?>

<div class="card" style="max-width:900px;">
  <div class="card-body">
    <form method="post" action="<?= BASE_URL ?>/actions/post-save.php" enctype="multipart/form-data" id="post-form">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="ctx" value="user">
      <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>

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
          <label>Kivonat / összefoglaló</label>
          <textarea name="excerpt" rows="2" maxlength="500"><?= e($post['excerpt'] ?? '') ?></textarea>
        </div>
        <div class="form-group full">
          <label>Tartalom <span style="color:var(--danger)">*</span></label>
          <textarea name="body" id="post-body-editor" rows="20" required><?= e($post['body'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
          <label>Borítókép</label>
          <?php if (!empty($post['cover_img'])): ?>
            <div style="margin-bottom:8px;">
              <img src="<?= BASE_URL ?>/assets/uploads/posts/<?= e($post['cover_img']) ?>" style="height:80px;border-radius:6px;object-fit:cover;">
              <label style="display:flex;align-items:center;gap:6px;margin-top:6px;font-size:12px;font-weight:normal;cursor:pointer;">
                <input type="checkbox" name="delete_cover" value="1"> Töröld a borítóképet
              </label>
            </div>
          <?php endif; ?>
          <input type="file" name="cover_img" accept="image/jpeg,image/png,image/webp">
          <small style="color:var(--text-muted);font-size:12px;">JPG, PNG vagy WebP; max. 4 MB.</small>
          <label style="margin-top:10px;">Borítókép alt szövege</label>
          <input type="text" name="cover_alt" value="<?= e($post['cover_alt'] ?? '') ?>" maxlength="255">
        </div>
        <div class="form-group">
          <label>Dátum</label>
          <?php $dtVal = !empty($post['created_at']) ? date('Y-m-d\TH:i', strtotime($post['created_at'])) : date('Y-m-d\TH:i'); ?>
          <input type="datetime-local" name="created_at" value="<?= e($dtVal) ?>">
        </div>
      </div>

      <div style="margin-top:24px;display:flex;gap:12px;flex-wrap:wrap;">
        <button type="submit" class="btn btn-secondary">Mentés piszkozatként</button>
        <button type="submit" name="submit_approval" value="1" class="btn btn-primary">Szerkesztés befejezése és beküldés jóváhagyásra</button>
        <a href="<?= BASE_URL ?>/user/posts.php" class="btn btn-ghost">Mégse</a>
      </div>
      <p style="margin:12px 0 0;font-size:12px;color:var(--text-muted);">A bejegyzésed az admin jóváhagyása után jelenik meg a weboldalon. Amíg jóváhagyásra vár vagy piszkozat, nem publikus.</p>
    </form>
  </div>
</div>

<script>
var titleEl = document.getElementById('post-title');
var slugEl  = document.getElementById('post-slug');
if (titleEl && slugEl && !slugEl.value) {
  titleEl.addEventListener('input', function () {
    slugEl.value = this.value.toLowerCase()
      .normalize('NFD').replace(/[̀-ͯ]/g, '')
      .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
  });
}
</script>
<script src="https://cdn.tiny.cloud/1/xrszxkdcc33rt9txt2b16unxeaz24r8985c8wdnq31zhaery/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
  selector: '#post-body-editor',
  language: 'hu_HU',
  height: 480,
  menubar: 'edit view insert format',
  plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen media table help wordcount',
  toolbar: 'undo redo | blocks | bold italic underline forecolor | alignleft aligncenter alignright | bullist numlist | link image | removeformat code | help',
  content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 15px; line-height: 1.7; color: #1a2e2b; }',
  image_advtab: true,
  relative_urls: false,
  remove_script_host: false,
  convert_urls: false,
  automatic_uploads: true,
  images_file_types: 'jpg,jpeg,png,webp,gif',
  file_picker_types: 'image',
  images_upload_handler: function (blobInfo, progress) {
    return new Promise(function (resolve, reject) {
      var fd = new FormData();
      fd.append('file', blobInfo.blob(), blobInfo.filename());
      fd.append('csrf_token', '<?= csrfToken() ?>');
      fetch('<?= BASE_URL ?>/actions/post-image-upload.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (res) { return res.json().then(function (j) { return { ok: res.ok, j: j }; }); })
        .then(function (r) {
          if (r.ok && r.j.location) { resolve(r.j.location); }
          else { reject({ message: r.j.error || 'A képfeltöltés sikertelen.', remove: true }); }
        })
        .catch(function () { reject({ message: 'Hálózati hiba a képfeltöltés közben.', remove: true }); });
    });
  },
  setup: function(editor) { editor.on('change', function() { editor.save(); }); }
});
</script>

<?php include __DIR__ . '/../includes/user-footer.php'; ?>
