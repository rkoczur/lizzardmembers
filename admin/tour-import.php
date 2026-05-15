<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$flash_success = getFlash('success');
$flash_error   = getFlash('error');
$importResults = $_SESSION['tour_import_results'] ?? null;
unset($_SESSION['tour_import_results']);

$pageTitle  = 'Túrák importálása';
$activePage = 'tours';
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
    <a href="<?= BASE_URL ?>/admin/tours.php" class="btn btn-secondary btn-sm">← Vissza</a>
    <h1>Túrák importálása CSV-ből</h1>
  </div>
  <a href="<?= BASE_URL ?>/actions/tours-template.php" class="btn btn-ghost btn-sm">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="16" height="16">
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
      <polyline points="7 10 12 15 17 10"/>
      <line x1="12" y1="15" x2="12" y2="3"/>
    </svg>
    Sablon letöltése
  </a>
</div>

<?php if ($importResults): ?>
<div class="card" style="margin-bottom:16px;">
  <div class="card-header"><h2>Importálás eredménye</h2></div>
  <div class="card-body">
    <p><strong><?= $importResults['imported'] ?></strong> túra sikeresen importálva, <strong><?= count($importResults['errors']) ?></strong> sor kihagyva.</p>
    <?php if (!empty($importResults['errors'])): ?>
    <table style="margin-top:12px;width:100%;font-size:13px;">
      <thead><tr><th style="text-align:left;padding:4px 8px;">Sor</th><th style="text-align:left;padding:4px 8px;">Hiba</th></tr></thead>
      <tbody>
        <?php foreach ($importResults['errors'] as $err): ?>
        <tr>
          <td style="padding:4px 8px;color:var(--text-muted);"><?= (int)$err['row'] ?>. sor</td>
          <td style="padding:4px 8px;color:var(--danger,#c0392b);"><?= e($err['msg']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="card" style="max-width:600px;">
  <div class="card-body">
    <p style="color:var(--text-muted);margin-bottom:20px;font-size:14px;">
      Tölts fel egy CSV fájlt a sablon alapján. A fejlécsor kötelező. Az elválasztó karakter <strong>pontosvessző (;)</strong>.
      Kötelező mező: <strong>Országkód</strong> (pl. <code>HU</code>, <code>AT</code>, <code>SK</code>) — az érvényes kódokat a
      <a href="<?= BASE_URL ?>/admin/settings.php">Beállítások › Országok</a> oldalon kezelheted.
      A <strong>Kód</strong> mező üresen hagyható — ilyenkor automatikusan generálódik.
    </p>
    <form method="post" action="<?= BASE_URL ?>/actions/tour-import.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <div class="form-group" style="margin-bottom:20px;">
        <label>CSV fájl <span style="color:var(--danger)">*</span></label>
        <input type="file" name="csv_file" accept=".csv,text/csv" required>
      </div>
      <div class="flex gap-2">
        <button type="submit" class="btn btn-primary">Importálás indítása</button>
        <a href="<?= BASE_URL ?>/admin/tours.php" class="btn btn-secondary">Mégse</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
