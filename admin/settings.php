<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tours-schema.php';
requireAdminOrVezeto();

$pdo = getDb();
ensureToursSchema($pdo);

$countries = getCountries($pdo, false);

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$pageTitle  = 'Beállítások';
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
  <h1>Beállítások</h1>
</div>

<div class="card">
  <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
    <h2>Országok</h2>
    <?php if (isAdmin()): ?>
    <a href="<?= BASE_URL ?>/admin/country-add.php" class="btn btn-primary btn-sm">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" width="14" height="14">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      Új ország
    </a>
    <?php endif; ?>
  </div>
  <div class="card-body" style="padding-bottom:0;">
    <p style="color:var(--text-muted);font-size:13px;margin-bottom:16px;">
      Az itt megadott országkódokat kell használni a túrák CSV-importjában (<strong>Országkód</strong> oszlop, pl. <code>HU</code>, <code>AT</code>).
      Az inaktív országok nem jelennek meg a túra-szerkesztő legördülőjében.
    </p>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th style="width:48px;">Zászló</th>
          <th style="width:70px;">Kód</th>
          <th>Magyar elnevezés</th>
          <th style="width:90px;">Sorrend</th>
          <th style="width:80px;">Aktív</th>
          <th style="width:110px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($countries as $c): ?>
        <tr <?= $c['active'] ? '' : 'style="opacity:.5;"' ?>>
          <td>
            <?php if ($c['flag_filename']): ?>
              <img src="<?= e(getFlagUrl($c['flag_filename'])) ?>"
                   style="width:32px;height:22px;object-fit:cover;border:1px solid var(--border);border-radius:2px;" alt="">
            <?php else: ?>
              <span style="display:inline-block;width:32px;height:22px;background:var(--bg-subtle);border:1px solid var(--border);border-radius:2px;"></span>
            <?php endif; ?>
          </td>
          <td><code style="font-size:.9em;"><?= e($c['code']) ?></code></td>
          <td><?= e($c['name_hu']) ?></td>
          <td style="color:var(--text-muted);font-size:.9em;"><?= (int)$c['sort_order'] ?></td>
          <td>
            <?php if ($c['active']): ?>
              <span class="badge badge-active">Aktív</span>
            <?php else: ?>
              <span class="badge badge-inactive">Inaktív</span>
            <?php endif; ?>
          </td>
          <td>
            <a href="<?= BASE_URL ?>/admin/country-detail.php?id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm"><?= isAdmin() ? 'Szerkesztés' : 'Megtekintés' ?></a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($countries)): ?>
        <tr><td colspan="6">
          <div class="empty-state"><p>Még nincs egyetlen ország sem rögzítve.</p></div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
