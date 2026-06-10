<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';
requireLeader();

$pdo = getDb();
ensurePublicSchema($pdo);

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

// Load requested page for editing
$editSlug = $_GET['slug'] ?? null;
$editPage = null;
if ($editSlug) {
    $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = ? LIMIT 1");
    $stmt->execute([$editSlug]);
    $editPage = $stmt->fetch();
}
$ro = $editPage ? !canManagePages($editSlug) : false;
$tinyMceSlugs = ['ado1', 'penzugyek', 'rolunk', 'kapcsolat', 'klubelet', 'lizzardier', 'mtsz-turanaplo', 'reszveteli-feltetelek', 'tagsag'];
$useTinyMce   = $editSlug && in_array($editSlug, $tinyMceSlugs, true) && !$ro;

$allPages = $pdo->query("SELECT * FROM pages ORDER BY slug ASC")->fetchAll();

$pageTitle  = 'Weboldal – Lapok';
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
  <h1>Statikus lapok</h1>
</div>

<div style="display:grid;grid-template-columns:260px 1fr;gap:20px;align-items:start;">

  <!-- Page list -->
  <div class="card">
    <div class="card-header"><h2>Lapok</h2></div>
    <div class="card-body" style="padding:0;">
      <?php foreach ($allPages as $p): ?>
      <a href="?slug=<?= urlencode($p['slug']) ?>"
         style="display:block;padding:11px 16px;font-size:13.5px;color:var(--text);border-bottom:1px solid var(--border);text-decoration:none;<?= ($editSlug === $p['slug']) ? 'background:var(--primary-light);font-weight:700;' : '' ?>">
        <?= e($p['title']) ?>
        <div style="font-size:11px;color:var(--text-muted);">/<?= e($p['slug']) ?></div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Editor -->
  <?php if ($editPage): ?>
  <div class="card">
    <div class="card-header">
      <h2><?= e($editPage['title']) ?></h2>
      <?php if ($editPage['slug'] !== 'hero-image'): ?>
        <a href="<?= BASE_URL ?>/public/<?= e($editPage['slug']) ?>.php" target="_blank" class="btn btn-ghost btn-sm">Megtekintés →</a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/public/index.php" target="_blank" class="btn btn-ghost btn-sm">Főoldal megtekintése →</a>
      <?php endif; ?>
    </div>
    <div class="card-body">

      <?php if ($editPage['slug'] === 'hero-image'): ?>
        <!-- Special: hero image upload -->
        <?php $currentHero = trim($editPage['body'] ?? ''); ?>
        <?php if ($currentHero): ?>
          <div style="margin-bottom:16px;">
            <p style="font-size:13px;color:var(--text-muted);margin-bottom:8px;">Jelenlegi kép:</p>
            <img src="<?= BASE_URL ?>/assets/uploads/hero/<?= e($currentHero) ?>"
                 style="max-width:100%;max-height:200px;object-fit:cover;border-radius:8px;display:block;">
          </div>
        <?php endif; ?>
        <?php if (!$ro): ?>
        <form method="post" enctype="multipart/form-data" action="<?= BASE_URL ?>/actions/hero-image-save.php">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <div class="form-group" style="margin-bottom:16px;">
            <label>Új háttérkép feltöltése</label>
            <input type="file" name="hero_image" accept="image/jpeg,image/png,image/webp">
            <small style="color:var(--text-muted);font-size:12px;">JPG, PNG vagy WebP; max. 8 MB. A kép kitölti a teljes hero területet (object-fit: cover).</small>
          </div>
          <?php if ($currentHero): ?>
          <div class="form-group" style="margin-bottom:16px;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;text-transform:none;letter-spacing:0;font-weight:600;">
              <input type="checkbox" name="delete_hero" value="1">
              Háttérkép törlése (nincs háttérkép a hero-ban)
            </label>
          </div>
          <?php endif; ?>
          <button type="submit" class="btn btn-primary">Mentés</button>
        </form>
        <?php else: ?>
          <p style="color:var(--text-muted);font-size:13px;">Nincs szerkesztési jogosultságod ehhez az oldalhoz.</p>
        <?php endif; ?>

      <?php else: ?>
        <?php if (!$ro): ?>
        <form method="post" action="<?= BASE_URL ?>/actions/page-save.php">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="slug" value="<?= e($editPage['slug']) ?>">
          <div class="form-group" style="margin-bottom:16px;">
            <label>Lap neve</label>
            <input type="text" name="title" value="<?= e($editPage['title']) ?>" required>
          </div>
          <div class="form-group" style="margin-bottom:20px;">
            <label>Tartalom <?= $useTinyMce ? '' : '(HTML megengedett)' ?></label>
            <textarea name="body" id="page-body-editor" rows="20" style="<?= $useTinyMce ? '' : 'font-family:monospace;font-size:13px;' ?>"><?= e($editPage['body'] ?? '') ?></textarea>
            <?php if (!$useTinyMce): ?>
            <small style="color:var(--text-muted);font-size:12px;">Formázáshoz használhatsz egyszerű HTML-t: &lt;h2&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;li&gt;, &lt;strong&gt;, &lt;a href=""&gt;</small>
            <?php endif; ?>
          </div>
          <div class="form-group" style="margin-bottom:14px;">
            <label>SEO meta-leírás <span style="font-weight:normal;text-transform:none;letter-spacing:0;color:var(--text-muted);font-size:12px;">(a keresőkben megjelenő rövid összefoglaló, ~160 karakter)</span></label>
            <textarea name="meta_description" rows="2" maxlength="500"><?= e($editPage['meta_description'] ?? '') ?></textarea>
          </div>
          <div class="form-group" style="margin-bottom:20px;">
            <label>SEO kulcsszavak <span style="font-weight:normal;text-transform:none;letter-spacing:0;color:var(--text-muted);font-size:12px;">(vesszővel elválasztva)</span></label>
            <input type="text" name="meta_keywords" value="<?= e($editPage['meta_keywords'] ?? '') ?>" maxlength="500" placeholder="pl. tagság, természetjárás, egyesület">
          </div>
          <button type="submit" class="btn btn-primary">Mentés</button>
        </form>
        <?php else: ?>
          <p style="color:var(--text-muted);font-size:13px;">Nincs szerkesztési jogosultságod ehhez az oldalhoz.</p>
        <?php endif; ?>
      <?php endif; ?>

    </div>
  </div>
  <?php else: ?>
  <div class="card">
    <div class="card-body" style="text-align:center;padding:48px 20px;color:var(--text-muted);">
      ← Válassz egy lapot a szerkesztéshez
    </div>
  </div>
  <?php endif; ?>

</div>

<?php if ($useTinyMce): ?>
<script src="https://cdn.tiny.cloud/1/xrszxkdcc33rt9txt2b16unxeaz24r8985c8wdnq31zhaery/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
  selector: '#page-body-editor',
  language: 'hu_HU',
  height: 520,
  menubar: 'file edit view insert format tools',
  plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount',
  toolbar: 'undo redo | blocks | bold italic underline forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image | removeformat code | help',
  content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; font-size: 15px; line-height: 1.7; color: #1a2e2b; }',
  image_advtab: true,
  relative_urls: false,
  remove_script_host: false,
  convert_urls: false,
  setup: function(editor) {
    editor.on('change', function() { editor.save(); });
  }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
