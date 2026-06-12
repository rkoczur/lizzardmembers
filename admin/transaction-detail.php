<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/bookkeeping-schema.php';
requireLogin();
if (!canManageFinances()) { header('Location: ' . BASE_URL . '/admin/index.php'); exit; }

$pdo = getDb();
ensureBookkeepingSchema($pdo);

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
$stmt->execute([$id]);
$tx = $stmt->fetch();
if (!$tx) {
    header('Location: ' . BASE_URL . '/admin/bookkeeping.php');
    exit;
}

$flash_error = getFlash('error');

$catPresets     = getTransactionPresets($pdo, 'category');
$partnerPresets = getTransactionPresets($pdo, 'partner');
$accountPresets = getTransactionPresets($pdo, 'account');

// Partner-választóhoz: aktív tagok neve
$memberNames = $pdo->query("
    SELECT TRIM(CONCAT(COALESCE(lastname,''), ' ', COALESCE(firstname,''))) AS full_name
    FROM users
    WHERE active = 1
    HAVING full_name <> ''
    ORDER BY lastname ASC, firstname ASC
")->fetchAll(PDO::FETCH_COLUMN);

$pastTours   = $pdo->query("SELECT id, COALESCE(NULLIF(name,''), CONCAT(country,' túra')) AS label, tour_date FROM tours ORDER BY tour_date DESC, id DESC")->fetchAll();
$futureTours = $pdo->query("SELECT id, name, start_date FROM future_tours ORDER BY start_date DESC, id DESC")->fetchAll();

$currentEvent = $tx['event_type'] ? ($tx['event_type'] . ':' . (int)$tx['event_id']) : '';

$pageTitle  = 'Tranzakció szerkesztése';
$activePage = 'bookkeeping';
include __DIR__ . '/../includes/admin-header.php';

/** Egy select renderelése úgy, hogy az aktuális érték biztosan szerepeljen az opciók közt. */
function presetSelect(string $name, array $presets, string $current): void
{
    $options = $presets;
    if ($current !== '' && !in_array($current, $options, true)) {
        $options[] = $current; // a már mentett, de időközben törölt érték megőrzése
    }
    echo '<select name="' . e($name) . '" required>';
    echo '<option value="">— válassz —</option>';
    foreach ($options as $v) {
        $sel = $v === $current ? ' selected' : '';
        echo '<option value="' . e($v) . '"' . $sel . '>' . e($v) . '</option>';
    }
    echo '</select>';
}
?>

<?php if ($flash_error): ?><div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div><?php endif; ?>

<div class="page-header">
  <h1>Tranzakció szerkesztése</h1>
  <a href="<?= BASE_URL ?>/admin/bookkeeping.php" class="btn btn-ghost btn-sm">← Vissza</a>
</div>

<div class="card" style="max-width:760px;">
  <div class="card-body">
    <form method="post" action="<?= BASE_URL ?>/actions/transaction-update.php">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="id" value="<?= (int)$tx['id'] ?>">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
        <div class="form-group">
          <label>Dátum <span style="color:var(--danger)">*</span></label>
          <input type="date" name="tx_date" value="<?= e($tx['tx_date']) ?>" required>
        </div>
        <div class="form-group">
          <label>Típus <span style="color:var(--danger)">*</span></label>
          <select name="tx_type" required>
            <option value="income"  <?= $tx['tx_type']==='income' ?'selected':'' ?>>Bevétel</option>
            <option value="expense" <?= $tx['tx_type']==='expense'?'selected':'' ?>>Kiadás</option>
          </select>
        </div>
        <div class="form-group">
          <label>Kategória <span style="color:var(--danger)">*</span></label>
          <?php presetSelect('category', $catPresets, $tx['category']); ?>
        </div>
        <div class="form-group">
          <label>Partner <span style="color:var(--danger)">*</span></label>
          <?php $partnerCurrent = (string)$tx['partner']; $partnerKnown = in_array($partnerCurrent, $partnerPresets, true) || in_array($partnerCurrent, $memberNames, true); ?>
          <select name="partner" required>
            <option value="">— válassz —</option>
            <?php if (!$partnerKnown && $partnerCurrent !== ''): ?>
              <option value="<?= e($partnerCurrent) ?>" selected><?= e($partnerCurrent) ?></option>
            <?php endif; ?>
            <?php if ($partnerPresets): ?>
            <optgroup label="Rögzített partnerek">
              <?php foreach ($partnerPresets as $v): ?><option value="<?= e($v) ?>" <?= $partnerCurrent===$v?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
            </optgroup>
            <?php endif; ?>
            <?php if ($memberNames): ?>
            <optgroup label="Tagok">
              <?php foreach ($memberNames as $v): ?><option value="<?= e($v) ?>" <?= $partnerCurrent===$v?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
            </optgroup>
            <?php endif; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Számla <span style="color:var(--danger)">*</span></label>
          <?php presetSelect('account', $accountPresets, $tx['account']); ?>
        </div>
        <div class="form-group">
          <label>Összeg (Ft) <span style="color:var(--danger)">*</span></label>
          <input type="number" name="amount" min="0" step="0.01" value="<?= e(rtrim(rtrim(number_format((float)$tx['amount'], 2, '.', ''), '0'), '.')) ?>" required>
        </div>
        <div class="form-group">
          <label>Esemény</label>
          <select name="event">
            <option value="">— nincs —</option>
            <?php if ($futureTours): ?>
            <optgroup label="Meghirdetett túrák">
              <?php foreach ($futureTours as $t): $val = 'future_tour:' . (int)$t['id']; ?>
                <option value="<?= $val ?>" <?= $currentEvent===$val?'selected':'' ?>><?= e($t['name']) ?><?= $t['start_date'] ? ' (' . e((new DateTime($t['start_date']))->format('Y.m.d')) . ')' : '' ?></option>
              <?php endforeach; ?>
            </optgroup>
            <?php endif; ?>
            <?php if ($pastTours): ?>
            <optgroup label="Korábbi túrák">
              <?php foreach ($pastTours as $t): $val = 'tour:' . (int)$t['id']; ?>
                <option value="<?= $val ?>" <?= $currentEvent===$val?'selected':'' ?>><?= e($t['label']) ?><?= $t['tour_date'] ? ' (' . e((new DateTime($t['tour_date']))->format('Y.m.d')) . ')' : '' ?></option>
              <?php endforeach; ?>
            </optgroup>
            <?php endif; ?>
          </select>
          <?php if ($currentEvent === '' && $tx['event_label']): ?>
            <small style="color:var(--text-muted);">Korábbi esemény: <?= e($tx['event_label']) ?> (a forrás törölve)</small>
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label>Számlaszám</label>
          <input type="text" name="invoice_number" value="<?= e($tx['invoice_number'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group" style="margin-top:12px;">
        <label>Leírás <span style="color:var(--danger)">*</span></label>
        <input type="text" name="description" value="<?= e($tx['description']) ?>" required>
      </div>
      <div style="margin-top:16px;display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary">Mentés</button>
        <a href="<?= BASE_URL ?>/admin/bookkeeping.php" class="btn btn-ghost">Mégse</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
