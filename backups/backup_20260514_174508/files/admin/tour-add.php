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

$allMembers = $pdo->query("SELECT id, firstname, lastname, email, role FROM users ORDER BY lastname, firstname")->fetchAll();

$flash_error = getFlash('error');
$old = $_SESSION['form_old'] ?? [];
unset($_SESSION['form_old']);

$pageTitle  = 'Új túra hozzáadása';
$activePage = 'tours';
include __DIR__ . '/../includes/admin-header.php';
?>

<?php if ($flash_error): ?>
  <div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="page-header">
  <div class="flex items-center gap-2">
    <a href="<?= BASE_URL ?>/admin/tours.php" class="btn btn-secondary btn-sm">← Vissza</a>
    <h1>Új túra hozzáadása</h1>
  </div>
</div>

<div class="card" style="max-width:720px;">
  <div class="card-body">
    <form method="post" action="<?= BASE_URL ?>/actions/tour-add.php">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

      <div class="form-grid">
        <div class="form-group full">
          <label>Elnevezés</label>
          <input type="text" name="name" value="<?= e($old['name'] ?? '') ?>" placeholder="pl. Mátra körüljáró túra" autofocus>
        </div>
        <div class="form-group">
          <label>Ország <span style="color:var(--danger)">*</span></label>
          <input type="text" name="country" value="<?= e($old['country'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Tájegység</label>
          <input type="text" name="region" value="<?= e($old['region'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Dátum</label>
          <input type="date" name="tour_date" value="<?= e($old['tour_date'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Napok száma <span style="color:var(--danger)">*</span></label>
          <input type="number" name="days" value="<?= (int)($old['days'] ?? 1) ?>" min="1" required>
        </div>
        <div class="form-group">
          <label>Szállás típusa</label>
          <input type="text" name="accommodation" value="<?= e($old['accommodation'] ?? '') ?>" placeholder="pl. Menedékház, Sátor…">
        </div>
        <div class="form-group">
          <label>Összes km</label>
          <input type="number" name="total_km" step="0.1" min="0" value="<?= e($old['total_km'] ?? '') ?>" placeholder="0.0">
        </div>
        <div class="form-group">
          <label>Összes szintemelkedés (m)</label>
          <input type="number" name="total_elevation" min="0" value="<?= e($old['total_elevation'] ?? '') ?>" placeholder="0">
        </div>
        <div class="form-group">
          <label>Pontérték <span style="color:var(--danger)">*</span></label>
          <input type="number" name="points" value="<?= (int)($old['points'] ?? 0) ?>" min="0" required>
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
          <?php
          $preIds = array_filter(array_map('intval', $old['member_ids'] ?? []));
          foreach ($allMembers as $m):
            if (!in_array((int)$m['id'], $preIds)) continue;
          ?>
          <div class="member-picker-item" data-member-id="<?= $m['id'] ?>">
            <span><?= e($m['lastname'] . ' ' . $m['firstname']) ?><?= $m['role'] === 'admin' ? ' [Admin]' : '' ?> — <?= e($m['email']) ?></span>
            <input type="hidden" name="member_ids[]" value="<?= $m['id'] ?>">
            <button type="button" class="btn btn-danger btn-sm">Eltávolít</button>
          </div>
          <?php endforeach; ?>
        </div>
        <p id="member-picker-empty" class="member-picker-empty"
           <?= !empty($preIds) ? 'style="display:none"' : '' ?>>Még nincs hozzárendelt tag.</p>
      </div>

      <div class="flex gap-2" style="margin-top:24px;">
        <button type="submit" class="btn btn-primary">Túra hozzáadása</button>
        <a href="<?= BASE_URL ?>/admin/tours.php" class="btn btn-secondary">Mégse</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
