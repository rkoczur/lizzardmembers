<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tours-schema.php';
requireAdmin();

$pdo = getDb();
ensureToursSchema($pdo);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/admin/tours.php');
    exit;
}

$tour = $pdo->prepare("SELECT * FROM tours WHERE id = ? LIMIT 1");
$tour->execute([$id]);
$tour = $tour->fetch();
if (!$tour) {
    header('Location: ' . BASE_URL . '/admin/tours.php');
    exit;
}

$allMembers = $pdo->query("SELECT id, firstname, lastname, email, role FROM users ORDER BY lastname, firstname")->fetchAll();

$assignedStmt = $pdo->prepare("SELECT u.id, u.firstname, u.lastname, u.email, u.role FROM tour_members tm JOIN users u ON u.id = tm.user_id WHERE tm.tour_id = ? ORDER BY u.lastname, u.firstname");
$assignedStmt->execute([$id]);
$assignedMembers = $assignedStmt->fetchAll();
$assignedIds = array_column($assignedMembers, 'id');

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$pageTitle  = $tour['name'] ? e($tour['name']) : e($tour['country'] . ($tour['region'] ? ' – ' . $tour['region'] : ''));
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
    <h1><?= $tour['name'] ? e($tour['name']) : e($tour['country'] . ($tour['region'] ? ' – ' . $tour['region'] : '')) ?></h1>
  </div>
  <form method="post" action="<?= BASE_URL ?>/actions/tour-delete.php"
        onsubmit="return confirmDelete('Biztosan törli ezt a túrát? A művelet nem vonható vissza.')">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="id" value="<?= $tour['id'] ?>">
    <button type="submit" class="btn btn-danger btn-sm">Túra törlése</button>
  </form>
</div>

<div class="card" style="max-width:720px;">
  <div class="card-header">
    <h2>Túra adatai</h2>
  </div>
  <div class="card-body">
    <form method="post" action="<?= BASE_URL ?>/actions/tour-update.php">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="id" value="<?= $tour['id'] ?>">

      <div class="form-grid">
        <div class="form-group full">
          <label>Elnevezés</label>
          <input type="text" name="name" value="<?= e($tour['name'] ?? '') ?>" placeholder="pl. Mátra körüljáró túra">
        </div>
        <div class="form-group">
          <label>Ország <span style="color:var(--danger)">*</span></label>
          <input type="text" name="country" value="<?= e($tour['country']) ?>" required>
        </div>
        <div class="form-group">
          <label>Tájegység</label>
          <input type="text" name="region" value="<?= e($tour['region'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Dátum</label>
          <input type="date" name="tour_date" value="<?= e($tour['tour_date'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Napok száma <span style="color:var(--danger)">*</span></label>
          <input type="number" name="days" value="<?= (int)$tour['days'] ?>" min="1" required>
        </div>
        <div class="form-group">
          <label>Szállás típusa</label>
          <input type="text" name="accommodation" value="<?= e($tour['accommodation'] ?? '') ?>" placeholder="pl. Menedékház, Sátor…">
        </div>
        <div class="form-group">
          <label>Összes km</label>
          <input type="number" name="total_km" step="0.1" min="0"
                 value="<?= $tour['total_km'] !== null ? number_format((float)$tour['total_km'], 1, '.', '') : '' ?>">
        </div>
        <div class="form-group">
          <label>Összes szintemelkedés (m)</label>
          <input type="number" name="total_elevation" min="0"
                 value="<?= $tour['total_elevation'] !== null ? (int)$tour['total_elevation'] : '' ?>">
        </div>
        <div class="form-group">
          <label>Pontérték <span style="color:var(--danger)">*</span></label>
          <input type="number" name="points" value="<?= (int)$tour['points'] ?>" min="0" required>
        </div>
      </div>

      <div class="form-section-title">Hozzárendelt tagok</div>
      <div class="member-picker">
        <div class="member-picker-controls">
          <select id="member-picker-select">
            <option value="">— Válasszon tagot —</option>
            <?php foreach ($allMembers as $m): ?>
              <option value="<?= $m['id'] ?>"><?= e($m['lastname'] . ' ' . $m['firstname']) ?><?= $m['role'] === 'admin' ? ' [Admin]' : '' ?> — <?= e($m['email']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" id="member-picker-add" class="btn btn-secondary btn-sm">Hozzáad</button>
        </div>
        <div id="member-picker-list" class="member-picker-list">
          <?php foreach ($assignedMembers as $m): ?>
          <div class="member-picker-item" data-member-id="<?= $m['id'] ?>">
            <span><?= e($m['lastname'] . ' ' . $m['firstname']) ?><?= $m['role'] === 'admin' ? ' [Admin]' : '' ?> — <?= e($m['email']) ?></span>
            <input type="hidden" name="member_ids[]" value="<?= $m['id'] ?>">
            <button type="button" class="btn btn-danger btn-sm">Eltávolít</button>
          </div>
          <?php endforeach; ?>
        </div>
        <p id="member-picker-empty" class="member-picker-empty"
           <?= !empty($assignedMembers) ? 'style="display:none"' : '' ?>>Még nincs hozzárendelt tag.</p>
      </div>

      <div class="flex gap-2" style="margin-top:24px;">
        <button type="submit" class="btn btn-primary">Változások mentése</button>
        <a href="<?= BASE_URL ?>/admin/tours.php" class="btn btn-secondary">Mégse</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
