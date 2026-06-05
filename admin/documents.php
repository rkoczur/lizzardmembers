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

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$docs = $pdo->query("SELECT * FROM documents ORDER BY category ASC, sort_order ASC, id ASC")->fetchAll();
$byCategory = [];
foreach ($docs as $d) {
    $byCategory[$d['category']][] = $d;
}

$pageTitle  = 'Weboldal – Dokumentumtár';
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
  <h1>Dokumentumtár</h1>
  <a href="<?= BASE_URL ?>/public/irattar.php" target="_blank" class="btn btn-ghost btn-sm">Nyilvános nézet →</a>
</div>

<div style="display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start;">

  <!-- Document list -->
  <div class="card">
    <div class="card-header"><h2>Feltöltött dokumentumok</h2></div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Kategória</th>
            <th>Cím</th>
            <th>Fájl</th>
            <th>Sorrend</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($docs as $d): ?>
          <tr>
            <td><?= e($d['category']) ?></td>
            <td><?= e($d['title']) ?></td>
            <td style="font-family:monospace;font-size:12px;color:var(--text-muted);"><?= e($d['filename']) ?></td>
            <td><?= (int)$d['sort_order'] ?></td>
            <td class="td-actions">
              <form method="post" action="<?= BASE_URL ?>/actions/document-delete.php" style="margin:0;"
                    onsubmit="return confirm('Biztosan törlöd ezt a dokumentumot?')">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Törlés</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($docs)): ?>
          <tr><td colspan="5" class="empty-state"><p>Nincs feltöltött dokumentum.</p></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Upload form -->
  <div class="card">
    <div class="card-header"><h2>PDF feltöltése</h2></div>
    <div class="card-body">
      <form method="post" enctype="multipart/form-data" action="<?= BASE_URL ?>/actions/document-save.php">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="form-group" style="margin-bottom:14px;">
          <label>Kategória <span style="color:var(--danger)">*</span></label>
          <input type="text" name="category" list="cat-list" required placeholder="pl. Alapszabály">
          <datalist id="cat-list">
            <?php foreach (array_keys($byCategory) as $cat): ?>
              <option value="<?= e($cat) ?>">
            <?php endforeach; ?>
            <option value="Alapszabály">
            <option value="Közgyűlési jegyzőkönyv">
            <option value="Éves beszámoló">
            <option value="Közhasznúsági jelentés">
            <option value="Egyéb">
          </datalist>
        </div>
        <div class="form-group" style="margin-bottom:14px;">
          <label>Dokumentum neve <span style="color:var(--danger)">*</span></label>
          <input type="text" name="title" required placeholder="pl. Alapszabály 2022">
        </div>
        <div class="form-group" style="margin-bottom:14px;">
          <label>Sorrend (kisebb szám = előrébb)</label>
          <input type="number" name="sort_order" value="0" min="0">
        </div>
        <div class="form-group" style="margin-bottom:20px;">
          <label>PDF fájl <span style="color:var(--danger)">*</span></label>
          <input type="file" name="document" accept=".pdf,application/pdf" required>
          <small style="color:var(--text-muted);font-size:12px;">Csak PDF; max. 20 MB.</small>
        </div>
        <button type="submit" class="btn btn-primary">Feltöltés</button>
      </form>
    </div>
  </div>

</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
