<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminOrVezeto();
if (isVezeto()) {
    flash('error', 'Nincs jogosultságod ehhez a művelethez.');
    header('Location: ' . BASE_URL . '/admin/members.php');
    exit;
}

if (isset($_GET['cancel'])) {
    unset($_SESSION['member_import_preview']);
    header('Location: ' . BASE_URL . '/admin/member-import.php');
    exit;
}

$flash_success = getFlash('success');
$flash_error   = getFlash('error');
$importResults = $_SESSION['import_results'] ?? null;
unset($_SESSION['import_results']);

$preview = $_SESSION['member_import_preview'] ?? null;

$pageTitle  = 'Tagok importálása';
$activePage = 'members';
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
    <a href="<?= BASE_URL ?>/admin/members.php" class="btn btn-secondary btn-sm">← Vissza</a>
    <h1>Tagok importálása CSV-ből</h1>
  </div>
  <a href="<?= BASE_URL ?>/actions/members-template.php" class="btn btn-ghost btn-sm">
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
    <p><strong><?= $importResults['imported'] ?></strong> tag sikeresen importálva, <strong><?= count($importResults['errors']) ?></strong> sor kihagyva.</p>
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

<?php if ($preview): ?>

<div class="card">
  <div class="card-header">
    <h2>Ellenőrzés eredménye</h2>
    <span class="badge badge-active" style="font-size:11px;"><?= $preview['total_data_rows'] ?> sor beolvasva</span>
  </div>
  <div class="card-body">

    <div style="display:flex;gap:12px;margin-bottom:20px;">
      <div style="flex:1;padding:14px 16px;border-radius:8px;background:#f0faf4;border:1px solid rgba(39,174,96,.25);">
        <div style="font-size:24px;font-weight:700;color:var(--success,#27ae60);"><?= count($preview['rows']) ?></div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">importálható sor</div>
      </div>
      <div style="flex:1;padding:14px 16px;border-radius:8px;background:<?= !empty($preview['errors']) ? '#fff5f5' : 'var(--bg-card,#fff)' ?>;border:1px solid <?= !empty($preview['errors']) ? 'rgba(192,57,43,.25)' : 'var(--border)' ?>;">
        <div style="font-size:24px;font-weight:700;color:<?= !empty($preview['errors']) ? 'var(--danger,#c0392b)' : 'var(--text-muted)' ?>;"><?= count($preview['errors']) ?></div>
        <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">hibás sor (kihagyva)</div>
      </div>
    </div>

    <?php if (!empty($preview['errors'])): ?>
    <details open style="margin-bottom:20px;">
      <summary style="font-size:13px;font-weight:600;cursor:pointer;margin-bottom:10px;color:var(--danger,#c0392b);">
        Hibák (<?= count($preview['errors']) ?> db)
      </summary>
      <div class="table-wrap">
        <table style="font-size:13px;">
          <thead>
            <tr>
              <th style="text-align:left;width:60px;">Sor</th>
              <th style="text-align:left;">Hiba leírása</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($preview['errors'] as $err): ?>
            <tr>
              <td style="color:var(--text-muted);"><?= (int)$err['row'] ?>.</td>
              <td style="color:var(--danger,#c0392b);"><?= e($err['msg']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </details>
    <?php endif; ?>

    <?php if (!empty($preview['rows'])): ?>
    <h3 style="font-size:13px;font-weight:600;margin-bottom:8px;">Importálandó tagok (<?= count($preview['rows']) ?> db)</h3>
    <div class="table-wrap" style="max-height:340px;overflow-y:auto;margin-bottom:20px;">
      <table>
        <thead>
          <tr>
            <th style="width:42px;">Sor</th>
            <th>Név</th>
            <th>Felhasználónév</th>
            <th>E-mail</th>
            <th>Tagság kezdete</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($preview['rows'] as $r): ?>
          <tr>
            <td style="color:var(--text-muted);font-size:12px;"><?= (int)$r['_row'] ?>.</td>
            <td><?= e($r['lastname'] . ' ' . $r['firstname']) ?></td>
            <td><code style="font-size:.85em;white-space:nowrap;"><?= e($r['username']) ?></code></td>
            <td><?= e($r['email']) ?></td>
            <td><?= e($r['member_since'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <form method="post" action="<?= BASE_URL ?>/actions/member-import.php">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="step" value="confirm">
      <div class="flex gap-2">
        <button type="submit" class="btn btn-primary">
          Importálás jóváhagyása (<?= count($preview['rows']) ?> tag)
        </button>
        <a href="<?= BASE_URL ?>/admin/member-import.php?cancel=1" class="btn btn-secondary">Mégse</a>
      </div>
    </form>

    <?php else: ?>
    <p style="color:var(--text-muted);margin-bottom:16px;">Nincs importálható sor. Ellenőrizd a CSV fájlt és próbáld újra.</p>
    <a href="<?= BASE_URL ?>/admin/member-import.php?cancel=1" class="btn btn-secondary">← Új fájl feltöltése</a>
    <?php endif; ?>

  </div>
</div>

<?php else: ?>

<div class="card" style="max-width:600px;">
  <div class="card-body">
    <p style="color:var(--text-muted);margin-bottom:20px;font-size:14px;">
      Tölts fel egy CSV fájlt a sablon alapján. A fejlécsor kötelező. Az elválasztó karakter <strong>pontosvessző (;)</strong>.
      Kötelező mezők: <strong>Vezetéknév, Keresztnév, Felhasználónév, E-mail, Jelszó</strong>.
    </p>
    <form method="post" action="<?= BASE_URL ?>/actions/member-import.php" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="step" value="validate">
      <div class="form-group" style="margin-bottom:20px;">
        <label>CSV fájl <span style="color:var(--danger)">*</span></label>
        <input type="file" name="csv_file" accept=".csv,text/csv" required>
      </div>
      <div class="flex gap-2">
        <button type="submit" class="btn btn-primary">Fájl ellenőrzése</button>
        <a href="<?= BASE_URL ?>/admin/members.php" class="btn btn-secondary">Mégse</a>
      </div>
    </form>
  </div>
</div>

<?php endif; ?>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
