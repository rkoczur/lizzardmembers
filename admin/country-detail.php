<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tours-schema.php';
requireAdminOrVezeto();
$ro = isVezeto();

$pdo = getDb();
ensureToursSchema($pdo);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/admin/settings.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM countries WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$country = $stmt->fetch();
if (!$country) {
    header('Location: ' . BASE_URL . '/admin/settings.php');
    exit;
}

$tcStmt = $pdo->prepare("SELECT COUNT(*) FROM tours WHERE country = ?");
$tcStmt->execute([$country['code']]);
$tourCount = (int)$tcStmt->fetchColumn();

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$pageTitle  = ($country['name_hu'] ?? '') . ' (' . ($country['code'] ?? '') . ')';
$activePage = 'settings';
include __DIR__ . '/../includes/admin-header.php';
?>

<?php if ($flash_success): ?>
  <div class="alert alert-success" data-auto-dismiss><?= e($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_error): ?>
  <div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="page-header">
  <div class="flex items-center gap-2">
    <a href="<?= BASE_URL ?>/admin/settings.php" class="btn btn-secondary btn-sm">← Vissza</a>
    <h1><?= e($country['name_hu']) ?> <code style="font-size:.7em;font-weight:400;">(<?= e($country['code']) ?>)</code></h1>
  </div>
  <?php if (!$ro): ?>
  <form method="post" action="<?= BASE_URL ?>/actions/country-delete.php"
        onsubmit="return confirmDelete('Biztosan törli ezt az országot? A művelet nem vonható vissza.')">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="id" value="<?= $country['id'] ?>">
    <button type="submit" class="btn btn-danger btn-sm"
            <?= $tourCount > 0 ? 'disabled title="Nem törölhető: ' . $tourCount . ' túra hivatkozik rá."' : '' ?>>
      Ország törlése
    </button>
  </form>
  <?php endif; ?>
</div>

<?php if ($tourCount > 0): ?>
<div class="alert alert-error" style="margin-bottom:16px;">
  <strong><?= $tourCount ?> túra</strong> hivatkozik erre az országkódra — törlés nem lehetséges.
  Módosítsd a túrákat, majd próbáld újra.
</div>
<?php endif; ?>

<div class="card" style="max-width:480px;">
  <?php if ($ro): ?>
    <div class="card-header">
      <h2>Ország adatai</h2>
      <span class="badge badge-vezeto" style="font-size:11px;">Csak megtekintés</span>
    </div>
  <?php endif; ?>
  <div class="card-body">
    <form method="post" action="<?= BASE_URL ?>/actions/country-update.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="id" value="<?= $country['id'] ?>">

      <div class="form-group">
        <label>Kód</label>
        <input type="text" value="<?= e($country['code']) ?>" readonly
               style="background:var(--bg-subtle);font-family:monospace;font-weight:700;cursor:default;">
        <small style="color:var(--text-muted);">A kód a létrehozás után nem módosítható.</small>
      </div>

      <div class="form-group">
        <label>Magyar elnevezés <?= $ro ? '' : '<span style="color:var(--danger)">*</span>' ?></label>
        <input type="text" name="name_hu" value="<?= e($country['name_hu']) ?>"
               maxlength="100" <?= $ro ? 'readonly' : 'required' ?>>
      </div>

      <div class="form-group">
        <label>Zászló kép</label>
        <?php if ($country['flag_filename']): ?>
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
            <img src="<?= e(getFlagUrl($country['flag_filename'])) ?>"
                 style="width:48px;height:33px;object-fit:cover;border:1px solid var(--border);border-radius:3px;" alt="">
            <?php if (!$ro): ?>
            <label style="display:flex;align-items:center;gap:6px;font-weight:400;font-size:13px;cursor:pointer;">
              <input type="checkbox" name="remove_flag" value="1">
              Zászló törlése
            </label>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if (!$ro): ?>
        <input type="file" name="flag_file" accept="image/jpeg,image/png,image/webp">
        <small style="color:var(--text-muted);">JPG, PNG vagy WebP, max. 500 KB. Ajánlott méret: kb. 40×28 px.</small>
        <?php endif; ?>
      </div>

      <div class="form-grid" style="grid-template-columns:1fr 1fr;">
        <div class="form-group">
          <label>Rendezési sorrend</label>
          <input type="number" name="sort_order" value="<?= (int)$country['sort_order'] ?>" min="0" <?= $ro ? 'readonly' : '' ?>>
        </div>
        <div class="form-group" style="display:flex;align-items:center;gap:8px;padding-top:28px;">
          <input type="checkbox" name="active" id="active" value="1"
                 <?= $country['active'] ? 'checked' : '' ?> style="width:16px;height:16px;" <?= $ro ? 'disabled' : '' ?>>
          <label for="active" style="margin:0;font-weight:400;">Aktív</label>
        </div>
      </div>

      <?php if (!$ro): ?>
      <div class="flex gap-2" style="margin-top:8px;">
        <button type="submit" class="btn btn-primary">Mentés</button>
        <a href="<?= BASE_URL ?>/admin/settings.php" class="btn btn-secondary">Mégse</a>
      </div>
      <?php else: ?>
      <div style="margin-top:8px;">
        <a href="<?= BASE_URL ?>/admin/settings.php" class="btn btn-secondary">← Vissza a beállításokhoz</a>
      </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
