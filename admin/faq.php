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

$editId = (int)($_GET['edit'] ?? 0);
$editRow = null;
if ($editId) {
    $s = $pdo->prepare("SELECT * FROM faq WHERE id = ? LIMIT 1");
    $s->execute([$editId]);
    $editRow = $s->fetch();
}

$items = $pdo->query("SELECT * FROM faq ORDER BY sort_order ASC, id ASC")->fetchAll();

$pageTitle  = 'Weboldal – GYIK';
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
  <h1>GYIK – Gyakran ismételt kérdések</h1>
  <a href="<?= BASE_URL ?>/public/gyik.php" target="_blank" class="btn btn-ghost btn-sm">Nyilvános nézet →</a>
</div>

<div style="display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start;">

  <!-- List -->
  <div class="card">
    <div class="card-header"><h2>Kérdések (<?= count($items) ?>)</h2></div>
    <?php if (empty($items)): ?>
      <div class="card-body" style="color:var(--text-muted);">Még nincs kérdés felvéve.</div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>#</th><th>Kérdés</th><th>Sorrend</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($items as $row): ?>
          <tr>
            <td><?= (int)$row['id'] ?></td>
            <td style="font-size:13.5px;"><?= e(mb_strimwidth($row['question'], 0, 80, '…')) ?></td>
            <td><?= (int)$row['sort_order'] ?></td>
            <td class="td-actions" style="white-space:nowrap;">
              <a href="?edit=<?= (int)$row['id'] ?>" class="btn btn-ghost btn-sm">Szerkesztés</a>
              <form method="post" action="<?= BASE_URL ?>/actions/faq-delete.php" style="display:inline;margin:0;"
                    onsubmit="return confirm('Törlöd ezt a kérdést?')">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Törlés</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Add / edit form -->
  <div class="card" style="position:sticky;top:80px;">
    <div class="card-header"><h2><?= $editRow ? 'Szerkesztés' : 'Új kérdés' ?></h2></div>
    <div class="card-body">
      <form method="post" action="<?= BASE_URL ?>/actions/faq-save.php">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <?php if ($editRow): ?>
          <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">
        <?php endif; ?>
        <div class="form-group" style="margin-bottom:12px;">
          <label>Kérdés <span style="color:var(--danger)">*</span></label>
          <input type="text" name="question" value="<?= e($editRow['question'] ?? '') ?>" required>
        </div>
        <div class="form-group" style="margin-bottom:12px;">
          <label>Válasz <span style="color:var(--danger)">*</span></label>
          <textarea name="answer" rows="6" required><?= e($editRow['answer'] ?? '') ?></textarea>
        </div>
        <div class="form-group" style="margin-bottom:20px;">
          <label>Sorrend</label>
          <input type="number" name="sort_order" value="<?= (int)($editRow['sort_order'] ?? 0) ?>" min="0">
        </div>
        <div style="display:flex;gap:10px;">
          <button type="submit" class="btn btn-primary"><?= $editRow ? 'Mentés' : 'Hozzáadás' ?></button>
          <?php if ($editRow): ?>
            <a href="<?= BASE_URL ?>/admin/faq.php" class="btn btn-ghost">Mégse</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
