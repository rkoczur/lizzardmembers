<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';
requireLeader();
$ro = !canManageFinances();

$pdo = getDb();
ensurePublicSchema($pdo);

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$rows = $pdo->query("SELECT * FROM finances ORDER BY year DESC, category ASC, sort_order ASC, id ASC")->fetchAll();

$filterYear = (int)($_GET['year'] ?? 0);
$years = array_unique(array_column($rows, 'year'));
rsort($years);

$pageTitle  = 'Weboldal – Pénzügyek';
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
  <h1>Pénzügyek</h1>
  <a href="<?= BASE_URL ?>/public/penzugyek.php" target="_blank" class="btn btn-ghost btn-sm">Nyilvános nézet →</a>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start;">

  <!-- Rows grouped by year -->
  <div>
    <?php
    $grouped = [];
    foreach ($rows as $r) { $grouped[$r['year']][$r['category']][] = $r; }
    krsort($grouped);
    foreach ($grouped as $yr => $cats):
    ?>
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header">
        <h2><?= (int)$yr ?>. évi adatok</h2>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Típus</th>
              <th>Megnevezés</th>
              <th style="text-align:right;">Összeg (Ft)</th>
              <th>Sorrend</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (['income' => 'Bevétel', 'expense' => 'Kiadás'] as $catKey => $catLabel): ?>
              <?php foreach ($cats[$catKey] ?? [] as $row): ?>
              <tr>
                <td><span class="badge <?= $catKey === 'income' ? 'badge-active' : 'badge-inactive' ?>"><?= $catLabel ?></span></td>
                <td><?= e($row['label']) ?></td>
                <td style="text-align:right;font-weight:600;"><?= number_format((int)$row['amount'], 0, ',', ' ') ?> Ft</td>
                <td><?= (int)$row['sort_order'] ?></td>
                <td class="td-actions">
                  <?php if (!$ro): ?>
                  <form method="post" action="<?= BASE_URL ?>/actions/finance-delete.php" style="margin:0;"
                        onsubmit="return confirm('Törlöd ezt a tételt?')">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Törlés</button>
                  </form>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($grouped)): ?>
      <div class="card"><div class="card-body" style="color:var(--text-muted);text-align:center;">Még nincs pénzügyi adat.</div></div>
    <?php endif; ?>
  </div>

  <!-- Add row -->
  <?php if (!$ro): ?>
  <div class="card" style="position:sticky;top:80px;">
    <div class="card-header"><h2>Tétel hozzáadása</h2></div>
    <div class="card-body">
      <form method="post" action="<?= BASE_URL ?>/actions/finance-save.php">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="form-group" style="margin-bottom:12px;">
          <label>Év <span style="color:var(--danger)">*</span></label>
          <input type="number" name="year" value="<?= date('Y') ?>" min="2000" max="2100" required>
        </div>
        <div class="form-group" style="margin-bottom:12px;">
          <label>Típus</label>
          <select name="category">
            <option value="income">Bevétel</option>
            <option value="expense">Kiadás</option>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:12px;">
          <label>Megnevezés <span style="color:var(--danger)">*</span></label>
          <input type="text" name="label" required placeholder="pl. Tagdíjak">
        </div>
        <div class="form-group" style="margin-bottom:12px;">
          <label>Összeg (Ft) <span style="color:var(--danger)">*</span></label>
          <input type="number" name="amount" min="0" required placeholder="pl. 150000">
        </div>
        <div class="form-group" style="margin-bottom:20px;">
          <label>Sorrend</label>
          <input type="number" name="sort_order" value="0" min="0">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;">Hozzáadás</button>
      </form>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
