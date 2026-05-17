<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tours-schema.php';
requireAdminOrVezeto();
if (isVezeto()) {
    flash('error', 'Nincs jogosultságod ehhez a művelethez.');
    header('Location: ' . BASE_URL . '/admin/settings.php');
    exit;
}

$pdo = getDb();
ensureToursSchema($pdo);

$flash_error = getFlash('error');
$old = $_SESSION['form_old'] ?? [];
unset($_SESSION['form_old']);

$pageTitle  = 'Új ország hozzáadása';
$activePage = 'settings';
include __DIR__ . '/../includes/admin-header.php';
?>

<?php if ($flash_error): ?>
  <div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="page-header">
  <div class="flex items-center gap-2">
    <a href="<?= BASE_URL ?>/admin/settings.php" class="btn btn-secondary btn-sm">← Vissza</a>
    <h1>Új ország hozzáadása</h1>
  </div>
</div>

<div class="card" style="max-width:480px;">
  <div class="card-body">
    <form method="post" action="<?= BASE_URL ?>/actions/country-add.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

      <div class="form-group">
        <label>Kód <span style="color:var(--danger)">*</span></label>
        <input type="text" name="code" value="<?= e(strtoupper($old['code'] ?? '')) ?>"
               placeholder="pl. HU, AT, SK" maxlength="10" required autofocus
               style="text-transform:uppercase;font-family:monospace;font-weight:700;">
        <small style="color:var(--text-muted);">2–10 nagybetű. Ez jelenik meg az import CSV-ben (Országkód oszlop).</small>
      </div>

      <div class="form-group">
        <label>Magyar elnevezés <span style="color:var(--danger)">*</span></label>
        <input type="text" name="name_hu" value="<?= e($old['name_hu'] ?? '') ?>"
               placeholder="pl. Magyarország" maxlength="100" required>
      </div>

      <div class="form-group">
        <label>Zászló kép</label>
        <input type="file" name="flag_file" accept="image/jpeg,image/png,image/webp">
        <small style="color:var(--text-muted);">JPG, PNG vagy WebP, max. 500 KB. Ajánlott méret: kb. 40×28 px.</small>
      </div>

      <div class="form-grid" style="grid-template-columns:1fr 1fr;">
        <div class="form-group">
          <label>Rendezési sorrend</label>
          <input type="number" name="sort_order" value="<?= (int)($old['sort_order'] ?? 0) ?>" min="0">
          <small style="color:var(--text-muted);">Kisebb szám = előrébb kerül.</small>
        </div>
        <div class="form-group" style="display:flex;align-items:center;gap:8px;padding-top:28px;">
          <input type="checkbox" name="active" id="active" value="1"
                 <?= ($old['active'] ?? '1') ? 'checked' : '' ?> style="width:16px;height:16px;">
          <label for="active" style="margin:0;font-weight:400;">Aktív</label>
        </div>
      </div>

      <div class="flex gap-2" style="margin-top:8px;">
        <button type="submit" class="btn btn-primary">Ország hozzáadása</button>
        <a href="<?= BASE_URL ?>/admin/settings.php" class="btn btn-secondary">Mégse</a>
      </div>
    </form>
  </div>
</div>

<script>
document.querySelector('input[name="code"]').addEventListener('input', function() {
  this.value = this.value.toUpperCase();
});
</script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
