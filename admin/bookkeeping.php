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

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$activeTab = in_array($_GET['tab'] ?? '', ['presets', 'export', 'report', 'links'], true) ? $_GET['tab'] : 'transactions';

// Előre definiált értékek
$catPresets     = getTransactionPresets($pdo, 'category');
$partnerPresets = getTransactionPresets($pdo, 'partner');
$accountPresets = getTransactionPresets($pdo, 'account');

// Partner-választóhoz: aktív tagok neve (vezetéknév keresztnév)
$memberNames = $pdo->query("
    SELECT TRIM(CONCAT(COALESCE(lastname,''), ' ', COALESCE(firstname,''))) AS full_name
    FROM users
    WHERE active = 1
    HAVING full_name <> ''
    ORDER BY lastname ASC, firstname ASC
")->fetchAll(PDO::FETCH_COLUMN);

// Esemény-választó forrásai
$pastTours   = $pdo->query("SELECT id, COALESCE(NULLIF(name,''), CONCAT(country,' túra')) AS label, tour_date FROM tours ORDER BY tour_date DESC, id DESC")->fetchAll();
$futureTours = $pdo->query("SELECT id, name, start_date FROM future_tours ORDER BY start_date DESC, id DESC")->fetchAll();

// Összerendeletlen tételek száma (esemény-név van, de túra-azonosító nincs) — a tab jelzőjéhez
$unlinkedCount = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE event_label IS NOT NULL AND event_label <> '' AND event_id IS NULL")->fetchColumn();

// ── Tranzakciók szűrése ────────────────────────────────────────────
$fYear        = (int)($_GET['year'] ?? 0);
$fType        = in_array($_GET['type'] ?? '', ['income','expense'], true) ? $_GET['type'] : '';
$fCategory    = trim($_GET['category'] ?? '');
$fSearch      = trim($_GET['q'] ?? '');
$fHighlighted = !empty($_GET['hl']);

$where = []; $params = [];
if ($fYear > 0)        { $where[] = 'YEAR(tx_date) = ?';  $params[] = $fYear; }
if ($fType !== '')     { $where[] = 'tx_type = ?';        $params[] = $fType; }
if ($fCategory !== '') { $where[] = 'category = ?';       $params[] = $fCategory; }
if ($fHighlighted)     { $where[] = 'highlighted = 1'; }
if ($fSearch !== '')   {
    $where[] = '(description LIKE ? OR partner LIKE ? OR invoice_number LIKE ? OR account LIKE ?)';
    array_push($params, "%$fSearch%", "%$fSearch%", "%$fSearch%", "%$fSearch%");
}
$whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$txStmt = $pdo->prepare("SELECT * FROM transactions $whereClause ORDER BY tx_date DESC, id DESC");
$txStmt->execute($params);
$transactions = $txStmt->fetchAll();

$years = $pdo->query("SELECT DISTINCT YEAR(tx_date) AS y FROM transactions ORDER BY y DESC")->fetchAll(PDO::FETCH_COLUMN);

$hasFilter = $fYear || $fType !== '' || $fCategory !== '' || $fSearch !== '' || $fHighlighted;

$pageTitle  = 'Könyvelés';
$activePage = 'bookkeeping';
include __DIR__ . '/../includes/admin-header.php';
?>

<?php if ($flash_success): ?><div class="alert alert-success" data-auto-dismiss><?= e($flash_success) ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div><?php endif; ?>

<div class="page-header">
  <h1>Könyvelés</h1>
  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <a href="<?= BASE_URL ?>/admin/bookkeeping-import.php" class="btn btn-secondary btn-sm">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="15" height="15">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
      </svg>
      Importálás
    </a>
    <a href="<?= BASE_URL ?>/public/penzugyek.php" target="_blank" class="btn btn-ghost btn-sm">Pénzügyek (nyilvános) →</a>
  </div>
</div>

<div class="tab-nav tab-nav-flush">
  <a href="?tab=transactions" class="tab-link<?= $activeTab === 'transactions' ? ' active' : '' ?>">Tranzakciók</a>
  <a href="?tab=report" class="tab-link<?= $activeTab === 'report' ? ' active' : '' ?>">Kimutatás</a>
  <a href="?tab=links" class="tab-link<?= $activeTab === 'links' ? ' active' : '' ?>">Összerendelések<?php if ($unlinkedCount > 0): ?> <span class="badge badge-overdue" style="font-size:10px;"><?= (int)$unlinkedCount ?></span><?php endif; ?></a>
  <a href="?tab=export" class="tab-link<?= $activeTab === 'export' ? ' active' : '' ?>">Exportálás</a>
  <a href="?tab=presets" class="tab-link<?= $activeTab === 'presets' ? ' active' : '' ?>">Előre definiált értékek</a>
</div>

<?php if ($activeTab === 'transactions'): ?>
<!-- ══════════════════ TRANZAKCIÓK ══════════════════ -->

<!-- Rögzítő űrlap (alapból összecsukva) -->
<details class="card" style="margin-bottom:16px;">
  <summary style="cursor:pointer;padding:14px 20px;font-weight:600;list-style:none;display:flex;align-items:center;gap:8px;">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="16" height="16">
      <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
    </svg>
    Tranzakció rögzítése
  </summary>
  <div class="card-body" style="border-top:1px solid var(--border);">
    <form method="post" action="<?= BASE_URL ?>/actions/transaction-save.php">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;">
        <div class="form-group">
          <label>Dátum <span style="color:var(--danger)">*</span></label>
          <input type="date" name="tx_date" value="<?= e(date('Y-m-d')) ?>" required>
        </div>
        <div class="form-group">
          <label>Típus <span style="color:var(--danger)">*</span></label>
          <select name="tx_type" required>
            <option value="income">Bevétel</option>
            <option value="expense">Kiadás</option>
          </select>
        </div>
        <div class="form-group">
          <label>Kategória <span style="color:var(--danger)">*</span></label>
          <select name="category" required>
            <option value="">— válassz —</option>
            <?php foreach ($catPresets as $v): ?><option value="<?= e($v) ?>"><?= e($v) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Partner <span style="color:var(--danger)">*</span></label>
          <select name="partner" required>
            <option value="">— válassz —</option>
            <?php if ($memberNames): ?>
            <optgroup label="Tagok">
              <?php foreach ($memberNames as $v): ?><option value="<?= e($v) ?>"><?= e($v) ?></option><?php endforeach; ?>
            </optgroup>
            <?php endif; ?>
            <?php if ($partnerPresets): ?>
            <optgroup label="Rögzített partnerek">
              <?php foreach ($partnerPresets as $v): ?><option value="<?= e($v) ?>"><?= e($v) ?></option><?php endforeach; ?>
            </optgroup>
            <?php endif; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Számla <span style="color:var(--danger)">*</span></label>
          <select name="account" required>
            <option value="">— válassz —</option>
            <?php foreach ($accountPresets as $v): ?><option value="<?= e($v) ?>"><?= e($v) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Összeg (Ft) <span style="color:var(--danger)">*</span></label>
          <input type="number" name="amount" min="0" step="0.01" required placeholder="pl. 15000">
        </div>
        <div class="form-group">
          <label>Esemény</label>
          <select name="event">
            <option value="">— nincs —</option>
            <?php if ($futureTours): ?>
            <optgroup label="Meghirdetett túrák">
              <?php foreach ($futureTours as $t): ?>
                <option value="future_tour:<?= (int)$t['id'] ?>"><?= e($t['name']) ?><?= $t['start_date'] ? ' (' . e((new DateTime($t['start_date']))->format('Y.m.d')) . ')' : '' ?></option>
              <?php endforeach; ?>
            </optgroup>
            <?php endif; ?>
            <?php if ($pastTours): ?>
            <optgroup label="Korábbi túrák">
              <?php foreach ($pastTours as $t): ?>
                <option value="tour:<?= (int)$t['id'] ?>"><?= e($t['label']) ?><?= $t['tour_date'] ? ' (' . e((new DateTime($t['tour_date']))->format('Y.m.d')) . ')' : '' ?></option>
              <?php endforeach; ?>
            </optgroup>
            <?php endif; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Számlaszám</label>
          <input type="text" name="invoice_number" placeholder="pl. 2026/0042">
        </div>
      </div>
      <div class="form-group" style="margin-top:12px;">
        <label>Leírás <span style="color:var(--danger)">*</span></label>
        <input type="text" name="description" required placeholder="A tranzakció rövid leírása">
      </div>
      <label class="notif-row" style="margin-top:14px;border:1px solid var(--border);border-radius:8px;padding:12px 14px;cursor:pointer;display:flex;align-items:center;gap:12px;">
        <input type="checkbox" name="highlighted" value="1">
        <span class="notif-slider"></span>
        <span style="font-size:13px;">
          <strong>Kiemelés a listában</strong>
          <small style="display:block;color:var(--text-muted);font-weight:400;">Folyamatban lévő tételként megjelölve — a táblázatban kiemelve látszik.</small>
        </span>
      </label>
      <div style="margin-top:14px;">
        <button type="submit" class="btn btn-primary">Rögzítés</button>
      </div>
      <?php if (!$catPresets || !$partnerPresets || !$accountPresets): ?>
        <p style="margin-top:10px;font-size:13px;color:var(--text-muted);">
          Tipp: előbb adj hozzá kategóriákat, partnereket és számlákat az
          <a href="?tab=presets">Előre definiált értékek</a> fülön.
        </p>
      <?php endif; ?>
    </form>
  </div>
</details>

<!-- Szűrő -->
<div class="card" style="margin-bottom:16px;">
  <form method="get" class="filter-bar">
    <input type="hidden" name="tab" value="transactions">
    <div class="search-bar" style="flex:1;min-width:180px;">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" name="q" value="<?= e($fSearch) ?>" placeholder="Leírás, partner, számla, számlaszám…">
    </div>
    <select name="year" class="form-control" style="width:auto;min-width:120px;">
      <option value="0">Minden év</option>
      <?php foreach ($years as $y): ?><option value="<?= (int)$y ?>" <?= $fYear===(int)$y?'selected':'' ?>><?= (int)$y ?></option><?php endforeach; ?>
    </select>
    <select name="type" class="form-control" style="width:auto;min-width:130px;">
      <option value="">Minden típus</option>
      <option value="income"  <?= $fType==='income' ?'selected':'' ?>>Bevétel</option>
      <option value="expense" <?= $fType==='expense'?'selected':'' ?>>Kiadás</option>
    </select>
    <select name="category" class="form-control" style="width:auto;min-width:150px;">
      <option value="">Minden kategória</option>
      <?php foreach ($catPresets as $v): ?><option value="<?= e($v) ?>" <?= $fCategory===$v?'selected':'' ?>><?= e($v) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Szűrés</button>
    <?php
      // „Csak kiemeltek" egygombos kapcsoló — a többi aktív szűrőt megtartja
      $hlToggle = array_filter(['tab'=>'transactions','q'=>$fSearch,'year'=>$fYear ?: '','type'=>$fType,'category'=>$fCategory], fn($v) => $v !== '' && $v !== null);
      if (!$fHighlighted) $hlToggle['hl'] = 1;
    ?>
    <a href="?<?= e(http_build_query($hlToggle)) ?>" class="btn btn-sm <?= $fHighlighted ? 'btn-primary' : 'btn-ghost' ?>" title="Csak a kiemelt, folyamatban lévő tételek">
      ⏳ Csak kiemeltek
    </a>
    <?php if ($hasFilter): ?><a href="?tab=transactions" class="btn btn-ghost btn-sm">Visszaállítás</a><?php endif; ?>
  </form>
</div>

<div class="card" id="tx-table-card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Dátum</th><th>Típus</th><th>Kategória</th><th>Leírás</th><th>Esemény</th>
          <th>Partner</th><th>Számla</th><th>Számlaszám</th><th style="text-align:right;">Összeg</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($transactions)): ?>
        <tr><td colspan="10"><div class="empty-state"><div class="empty-icon">📒</div><p>Nincs tranzakció a megadott feltételek alapján.</p></div></td></tr>
        <?php else: foreach ($transactions as $tx): $hl = !empty($tx['highlighted']); ?>
        <tr style="<?= $hl ? 'background:#fffbeb;box-shadow:inset 4px 0 0 var(--warning,#f59e0b);' : '' ?>">
          <td style="white-space:nowrap;font-size:13px;"><?= e((new DateTime($tx['tx_date']))->format('Y.m.d')) ?></td>
          <td><span class="badge <?= $tx['tx_type']==='income' ? 'badge-active' : 'badge-inactive' ?>"><?= $tx['tx_type']==='income' ? 'Bevétel' : 'Kiadás' ?></span></td>
          <td style="font-size:13px;"><?= e($tx['category']) ?></td>
          <td style="font-size:13px;max-width:220px;">
            <?= e($tx['description']) ?>
            <?php if ($hl): ?><span class="badge badge-overdue" style="font-size:10px;margin-left:4px;white-space:nowrap;">⏳ Folyamatban</span><?php endif; ?>
          </td>
          <td style="font-size:13px;color:var(--text-muted);"><?= $tx['event_label'] ? e($tx['event_label']) : '—' ?></td>
          <td style="font-size:13px;"><?= e($tx['partner']) ?></td>
          <td style="font-size:13px;"><?= e($tx['account']) ?></td>
          <td style="font-size:13px;color:var(--text-muted);"><?= $tx['invoice_number'] ? e($tx['invoice_number']) : '—' ?></td>
          <td style="text-align:right;font-weight:600;white-space:nowrap;color:<?= $tx['tx_type']==='income' ? 'var(--primary)' : 'var(--danger)' ?>;">
            <?= $tx['tx_type']==='income' ? '+' : '−' ?><?= number_format((float)$tx['amount'], 0, ',', ' ') ?> Ft
          </td>
          <td class="td-actions" style="white-space:nowrap;">
            <a href="<?= BASE_URL ?>/admin/transaction-detail.php?id=<?= (int)$tx['id'] ?>" class="btn btn-secondary btn-sm tx-edit-btn"
               data-id="<?= (int)$tx['id'] ?>"
               data-date="<?= e($tx['tx_date']) ?>"
               data-type="<?= e($tx['tx_type']) ?>"
               data-category="<?= e($tx['category']) ?>"
               data-description="<?= e($tx['description']) ?>"
               data-partner="<?= e($tx['partner']) ?>"
               data-account="<?= e($tx['account']) ?>"
               data-amount="<?= e(rtrim(rtrim(number_format((float)$tx['amount'], 2, '.', ''), '0'), '.')) ?>"
               data-invoice="<?= e($tx['invoice_number'] ?? '') ?>"
               data-event="<?= $tx['event_type'] ? e($tx['event_type'] . ':' . (int)$tx['event_id']) : '' ?>"
               data-highlighted="<?= !empty($tx['highlighted']) ? '1' : '0' ?>">Szerkesztés</a>
            <form method="post" action="<?= BASE_URL ?>/actions/transaction-delete.php" style="margin:0;display:inline;" onsubmit="return confirm('Törlöd ezt a tranzakciót?')">
              <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
              <input type="hidden" name="id" value="<?= (int)$tx['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">Törlés</button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if (!empty($transactions)): ?>
  <div style="padding:12px 20px;font-size:12px;color:var(--text-muted);border-top:1px solid var(--border);">
    <?= count($transactions) ?> tranzakció látható <?= $hasFilter ? '(szűrve)' : '' ?>
  </div>
  <?php endif; ?>
</div>

<!-- ══════════════════ SZERKESZTŐ OVERLAY ══════════════════ -->
<div id="tx-edit-overlay" style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.5);align-items:flex-start;justify-content:center;overflow:auto;padding:40px 16px;">
  <div class="card" style="width:min(760px,100%);margin:0;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
      <h2>Tranzakció szerkesztése</h2>
      <button type="button" id="tx-edit-close" class="btn btn-ghost btn-sm" aria-label="Bezárás">✕</button>
    </div>
    <div class="card-body">
      <div id="tx-edit-error" class="alert alert-error" style="display:none;"></div>
      <form id="tx-edit-form" method="post" action="<?= BASE_URL ?>/actions/transaction-update.php">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="id" value="">
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
          <div class="form-group">
            <label>Dátum <span style="color:var(--danger)">*</span></label>
            <input type="date" name="tx_date" required>
          </div>
          <div class="form-group">
            <label>Típus <span style="color:var(--danger)">*</span></label>
            <select name="tx_type" required>
              <option value="income">Bevétel</option>
              <option value="expense">Kiadás</option>
            </select>
          </div>
          <div class="form-group">
            <label>Kategória <span style="color:var(--danger)">*</span></label>
            <select name="category" required>
              <option value="">— válassz —</option>
              <?php foreach ($catPresets as $v): ?><option value="<?= e($v) ?>"><?= e($v) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Partner <span style="color:var(--danger)">*</span></label>
            <select name="partner" required>
              <option value="">— válassz —</option>
              <?php if ($memberNames): ?><optgroup label="Tagok"><?php foreach ($memberNames as $v): ?><option value="<?= e($v) ?>"><?= e($v) ?></option><?php endforeach; ?></optgroup><?php endif; ?>
              <?php if ($partnerPresets): ?><optgroup label="Rögzített partnerek"><?php foreach ($partnerPresets as $v): ?><option value="<?= e($v) ?>"><?= e($v) ?></option><?php endforeach; ?></optgroup><?php endif; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Számla <span style="color:var(--danger)">*</span></label>
            <select name="account" required>
              <option value="">— válassz —</option>
              <?php foreach ($accountPresets as $v): ?><option value="<?= e($v) ?>"><?= e($v) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Összeg (Ft) <span style="color:var(--danger)">*</span></label>
            <input type="number" name="amount" min="0" step="0.01" required>
          </div>
          <div class="form-group">
            <label>Esemény</label>
            <select name="event">
              <option value="">— nincs —</option>
              <?php if ($futureTours): ?>
              <optgroup label="Meghirdetett túrák">
                <?php foreach ($futureTours as $t): ?><option value="future_tour:<?= (int)$t['id'] ?>"><?= e($t['name']) ?><?= $t['start_date'] ? ' (' . e((new DateTime($t['start_date']))->format('Y.m.d')) . ')' : '' ?></option><?php endforeach; ?>
              </optgroup>
              <?php endif; ?>
              <?php if ($pastTours): ?>
              <optgroup label="Korábbi túrák">
                <?php foreach ($pastTours as $t): ?><option value="tour:<?= (int)$t['id'] ?>"><?= e($t['label']) ?><?= $t['tour_date'] ? ' (' . e((new DateTime($t['tour_date']))->format('Y.m.d')) . ')' : '' ?></option><?php endforeach; ?>
              </optgroup>
              <?php endif; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Számlaszám</label>
            <input type="text" name="invoice_number">
          </div>
        </div>
        <div class="form-group" style="margin-top:12px;">
          <label>Leírás <span style="color:var(--danger)">*</span></label>
          <input type="text" name="description" required>
        </div>
        <label class="notif-row" style="margin-top:14px;border:1px solid var(--border);border-radius:8px;padding:12px 14px;cursor:pointer;display:flex;align-items:center;gap:12px;">
          <input type="checkbox" name="highlighted" value="1">
          <span class="notif-slider"></span>
          <span style="font-size:13px;"><strong>Kiemelés a listában</strong><small style="display:block;color:var(--text-muted);font-weight:400;">Folyamatban lévő tételként megjelölve.</small></span>
        </label>
        <div style="margin-top:16px;display:flex;gap:10px;">
          <button type="submit" class="btn btn-primary">Mentés</button>
          <button type="button" id="tx-edit-cancel" class="btn btn-ghost">Mégse</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function () {
  var overlay = document.getElementById('tx-edit-overlay');
  var form    = document.getElementById('tx-edit-form');
  var errBox  = document.getElementById('tx-edit-error');
  if (!overlay || !form) return;
  var field = function (n) { return form.querySelector('[name="' + n + '"]'); };

  function setVal(name, val) {
    var el = field(name);
    if (!el) return;
    if (el.tagName === 'SELECT') {
      var has = Array.prototype.some.call(el.options, function (o) { return o.value === val; });
      if (!has && val !== '') { var o = document.createElement('option'); o.value = val; o.textContent = val; el.appendChild(o); }
    }
    el.value = val;
  }

  function openEdit(d) {
    errBox.style.display = 'none'; errBox.textContent = '';
    field('id').value = d.id || '';
    setVal('tx_date', d.date || '');
    setVal('tx_type', d.type || 'income');
    setVal('category', d.category || '');
    setVal('partner', d.partner || '');
    setVal('account', d.account || '');
    setVal('amount', d.amount || '');
    setVal('event', d.event || '');
    setVal('invoice_number', d.invoice || '');
    setVal('description', d.description || '');
    field('highlighted').checked = (d.highlighted === '1');
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
  function closeOverlay() { overlay.style.display = 'none'; document.body.style.overflow = ''; }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('.tx-edit-btn');
    if (btn) {
      e.preventDefault();
      openEdit(btn.dataset);
      return;
    }
    if (e.target === overlay || e.target.closest('#tx-edit-close') || e.target.closest('#tx-edit-cancel')) {
      closeOverlay();
    }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && overlay.style.display === 'flex') closeOverlay(); });

  function refreshTable() {
    fetch(window.location.href, { credentials: 'same-origin' })
      .then(function (r) { return r.text(); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var fresh = doc.getElementById('tx-table-card');
        var cur   = document.getElementById('tx-table-card');
        if (fresh && cur) cur.innerHTML = fresh.innerHTML;
      })
      .catch(function () { window.location.reload(); });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var submitBtn = form.querySelector('[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;
    errBox.style.display = 'none';
    fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j && j.success) { closeOverlay(); refreshTable(); }
        else { errBox.textContent = (j && j.error) ? j.error : 'A mentés sikertelen.'; errBox.style.display = 'block'; }
      })
      .catch(function () { errBox.textContent = 'Hálózati hiba a mentés közben.'; errBox.style.display = 'block'; })
      .finally(function () { if (submitBtn) submitBtn.disabled = false; });
  });
})();
</script>

<?php elseif ($activeTab === 'report'): ?>
<!-- ══════════════════ KIMUTATÁS ══════════════════ -->
<?php
// A Kimutatás az ESEMÉNY-AZONOSÍTÓ (event_type + event_id) szerint csoportosít, a nevet/dátumot a
// túrából kérdezve le — így az importált és a kézzel kiválasztott (eltérő event_label) tételek is
// EGY eseményként számolódnak. Az össze nem rendelt (event_id nélküli) tételek kényszerűségből még
// a név (event_label) alapján csoportosulnak.
$selectedEvent = trim($_GET['event'] ?? '');  // gkey: "tour:ID" / "future_tour:ID" / "L:<név>"

// Túra-nevek és -dátumok azonosító szerint
$tourById = [];
foreach ($pdo->query("SELECT id, COALESCE(NULLIF(name,''), CONCAT(country, COALESCE(CONCAT(' – ', region), ''))) AS nm, tour_date FROM tours") as $r) {
    $tourById[(int)$r['id']] = ['name' => $r['nm'], 'date' => $r['tour_date']];
}
$futById = [];
foreach ($pdo->query("SELECT id, name AS nm, start_date AS dt FROM future_tours") as $r) {
    $futById[(int)$r['id']] = ['name' => $r['nm'], 'date' => $r['dt']];
}

// Csoportosítás: ha van event_id → "type:id"; különben "L:<label>"
$groups = $pdo->query("
    SELECT
        CASE WHEN event_id IS NOT NULL THEN CONCAT(event_type, ':', event_id)
             ELSE CONCAT('L:', COALESCE(event_label, '')) END AS gkey,
        MAX(event_type) AS event_type, MAX(event_id) AS event_id, MAX(event_label) AS any_label,
        SUM(CASE WHEN tx_type='income'  THEN amount ELSE 0 END) AS income,
        SUM(CASE WHEN tx_type='expense' THEN amount ELSE 0 END) AS expense,
        COUNT(*) AS cnt, MIN(tx_date) AS first_tx
    FROM transactions
    WHERE event_id IS NOT NULL OR (event_label IS NOT NULL AND event_label <> '')
    GROUP BY gkey
")->fetchAll();

$eventOverview = [];
foreach ($groups as $g) {
    $name = null; $date = null;
    if ($g['event_id']) {
        $info = $g['event_type'] === 'future_tour' ? ($futById[(int)$g['event_id']] ?? null) : ($tourById[(int)$g['event_id']] ?? null);
        $name = $info['name'] ?? $g['any_label'];   // ha a túra törölve, marad a címke
        $date = $info['date'] ?? null;
    } else {
        $name = $g['any_label'];
        $rv = resolveEventByLabel($pdo, (string)$g['any_label']);
        if ($rv['type'] === 'tour')            $date = $tourById[$rv['id']]['date'] ?? null;
        elseif ($rv['type'] === 'future_tour') $date = $futById[$rv['id']]['date']  ?? null;
    }
    $eventOverview[] = [
        'gkey'    => $g['gkey'],
        'name'    => ($name !== null && $name !== '') ? $name : '(névtelen esemény)',
        'date'    => $date ?: $g['first_tx'],
        'income'  => (float)$g['income'],
        'expense' => (float)$g['expense'],
        'cnt'     => (int)$g['cnt'],
    ];
}
// Áttekintés: esemény dátuma szerint, legújabb felül
usort($eventOverview, fn($a, $b) => strcmp((string)$b['date'], (string)$a['date']));
// Választó legördülő: név szerint
$eventOptions = $eventOverview;
usort($eventOptions, fn($a, $b) => strcasecmp((string)$a['name'], (string)$b['name']));

// Kiválasztott esemény részletei (gkey alapján)
$evRows = []; $evIncome = 0.0; $evExpense = 0.0; $selectedName = '';
if ($selectedEvent !== '') {
    if (str_starts_with($selectedEvent, 'L:')) {
        $lbl = substr($selectedEvent, 2);
        $st = $pdo->prepare("SELECT * FROM transactions WHERE event_id IS NULL AND event_label = ? ORDER BY tx_date ASC, id ASC");
        $st->execute([$lbl]);
        $evRows = $st->fetchAll();
        $selectedName = $lbl;
    } elseif (str_contains($selectedEvent, ':')) {
        [$ty, $idStr] = explode(':', $selectedEvent, 2);
        $idn = (int)$idStr;
        if (in_array($ty, ['tour', 'future_tour'], true) && $idn > 0) {
            $st = $pdo->prepare("SELECT * FROM transactions WHERE event_type = ? AND event_id = ? ORDER BY tx_date ASC, id ASC");
            $st->execute([$ty, $idn]);
            $evRows = $st->fetchAll();
            $info = $ty === 'future_tour' ? ($futById[$idn] ?? null) : ($tourById[$idn] ?? null);
            $selectedName = $info['name'] ?? '';
        }
    }
    foreach ($evRows as $r) {
        if ($r['tx_type'] === 'income') $evIncome += (float)$r['amount'];
        else                            $evExpense += (float)$r['amount'];
    }
}
$evResult = $evIncome - $evExpense;

?>

<div class="card" style="margin-bottom:16px;">
  <div class="card-header"><h2>Esemény kimutatás</h2></div>
  <div class="card-body">
    <form method="get" class="filter-bar">
      <input type="hidden" name="tab" value="report">
      <div class="form-group" style="margin:0;flex:1;min-width:240px;">
        <label style="font-size:12px;">Esemény</label>
        <select name="event" class="form-control" onchange="this.form.submit()">
          <option value="">— válassz eseményt —</option>
          <?php foreach ($eventOptions as $opt): ?>
            <option value="<?= e($opt['gkey']) ?>" <?= $selectedEvent === $opt['gkey'] ? 'selected' : '' ?>>
              <?= e($opt['name']) ?> (<?= (int)$opt['cnt'] ?> tétel)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-sm" style="align-self:flex-end;">Mutat</button>
    </form>
  </div>
</div>

<?php if ($selectedEvent !== ''): ?>
  <div class="rg-4" style="margin-bottom:16px;">
    <div class="card"><div class="card-body" style="display:flex;align-items:center;gap:16px;">
      <div style="font-size:24px;font-weight:700;color:var(--primary);"><?= number_format((int)$evIncome, 0, ',', ' ') ?> Ft</div>
      <div><div style="font-weight:600;font-size:14px;">Bevétel</div></div>
    </div></div>
    <div class="card"><div class="card-body" style="display:flex;align-items:center;gap:16px;">
      <div style="font-size:24px;font-weight:700;color:var(--danger);"><?= number_format((int)$evExpense, 0, ',', ' ') ?> Ft</div>
      <div><div style="font-weight:600;font-size:14px;">Kiadás</div></div>
    </div></div>
    <div class="card"><div class="card-body" style="display:flex;align-items:center;gap:16px;">
      <div style="font-size:24px;font-weight:700;color:<?= $evResult >= 0 ? 'var(--primary)' : 'var(--danger)' ?>;">
        <?= $evResult >= 0 ? '+' : '' ?><?= number_format((int)$evResult, 0, ',', ' ') ?> Ft
      </div>
      <div><div style="font-weight:600;font-size:14px;"><?= $evResult >= 0 ? 'Nyereség' : 'Veszteség' ?></div>
           <div style="font-size:12px;color:var(--text-muted);"><?= count($evRows) ?> tranzakció</div></div>
    </div></div>
    <div class="card"><div class="card-body" style="display:flex;align-items:center;gap:16px;">
      <div style="font-size:15px;font-weight:600;"><?= e($selectedName !== '' ? $selectedName : '—') ?></div>
    </div></div>
  </div>

  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Dátum</th><th>Típus</th><th>Kategória</th><th>Leírás</th><th>Partner</th><th>Számla</th><th style="text-align:right;">Összeg</th></tr>
        </thead>
        <tbody>
          <?php foreach ($evRows as $tx): ?>
          <tr>
            <td style="white-space:nowrap;font-size:13px;"><?= e((new DateTime($tx['tx_date']))->format('Y.m.d')) ?></td>
            <td><span class="badge <?= $tx['tx_type']==='income' ? 'badge-active' : 'badge-inactive' ?>"><?= $tx['tx_type']==='income' ? 'Bevétel' : 'Kiadás' ?></span></td>
            <td style="font-size:13px;"><?= e($tx['category']) ?></td>
            <td style="font-size:13px;max-width:240px;"><?= e($tx['description']) ?></td>
            <td style="font-size:13px;"><?= e($tx['partner']) ?></td>
            <td style="font-size:13px;"><?= e($tx['account']) ?></td>
            <td style="text-align:right;font-weight:600;white-space:nowrap;color:<?= $tx['tx_type']==='income' ? 'var(--primary)' : 'var(--danger)' ?>;">
              <?= $tx['tx_type']==='income' ? '+' : '−' ?><?= number_format((float)$tx['amount'], 0, ',', ' ') ?> Ft
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<div class="card" style="margin-top:20px;">
  <div class="card-header"><h2>Összes esemény áttekintés</h2></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Dátum</th><th>Esemény</th><th style="text-align:center;">Tételek</th>
          <th style="text-align:right;">Bevétel</th><th style="text-align:right;">Kiadás</th><th style="text-align:right;">Eredmény</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($eventOverview)): ?>
        <tr><td colspan="6"><div class="empty-state"><div class="empty-icon">📊</div><p>Még nincs eseményhez kötött tranzakció.</p></div></td></tr>
        <?php else: foreach ($eventOverview as $ev): $res = (float)$ev['income'] - (float)$ev['expense']; ?>
        <tr>
          <td style="white-space:nowrap;font-size:13px;color:var(--text-muted);"><?= $ev['date'] ? e((new DateTime($ev['date']))->format('Y.m.d')) : '—' ?></td>
          <td style="font-size:13px;font-weight:500;"><a href="?tab=report&event=<?= urlencode($ev['gkey']) ?>"><?= e($ev['name']) ?></a></td>
          <td style="text-align:center;font-size:13px;color:var(--text-muted);"><?= (int)$ev['cnt'] ?></td>
          <td style="text-align:right;font-size:13px;color:var(--primary);"><?= number_format((int)$ev['income'], 0, ',', ' ') ?> Ft</td>
          <td style="text-align:right;font-size:13px;color:var(--danger);"><?= number_format((int)$ev['expense'], 0, ',', ' ') ?> Ft</td>
          <td style="text-align:right;font-weight:700;white-space:nowrap;color:<?= $res >= 0 ? 'var(--primary)' : 'var(--danger)' ?>;">
            <?= $res >= 0 ? '+' : '' ?><?= number_format((int)$res, 0, ',', ' ') ?> Ft
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($activeTab === 'links'): ?>
<!-- ══════════════════ ÖSSZERENDELÉSEK ══════════════════ -->
<?php
// Összerendeletlen esemény-nevek (van event_label, de nincs event_id) — kézi párosításhoz
$unlinkedLabels = $pdo->query("
    SELECT event_label, COUNT(*) AS cnt
    FROM transactions
    WHERE event_label IS NOT NULL AND event_label <> '' AND event_id IS NULL
    GROUP BY event_label
    ORDER BY event_label ASC
")->fetchAll();

// Túra-választó forrásai (csak nevesített túrák, mert a címke egy névvel egyezik)
$dropTours = $pdo->query("SELECT id, name, tour_date FROM tours WHERE name IS NOT NULL AND name <> '' ORDER BY tour_date DESC, id DESC")->fetchAll();

function renderEventOptions(array $dropTours, array $dropFuture, string $sel): string {
    $h = '<option value="">— nincs / hagyd —</option>';
    if ($dropFuture) {
        $h .= '<optgroup label="Meghirdetett túrák">';
        foreach ($dropFuture as $t) {
            $v = 'future_tour:' . (int)$t['id'];
            $d = !empty($t['start_date']) ? ' (' . (new DateTime($t['start_date']))->format('Y.m.d') . ')' : '';
            $h .= '<option value="' . e($v) . '"' . ($sel === $v ? ' selected' : '') . '>' . e($t['name'] . $d) . '</option>';
        }
        $h .= '</optgroup>';
    }
    if ($dropTours) {
        $h .= '<optgroup label="Korábbi túrák">';
        foreach ($dropTours as $t) {
            $v = 'tour:' . (int)$t['id'];
            $d = !empty($t['tour_date']) ? ' (' . (new DateTime($t['tour_date']))->format('Y.m.d') . ')' : '';
            $h .= '<option value="' . e($v) . '"' . ($sel === $v ? ' selected' : '') . '>' . e($t['name'] . $d) . '</option>';
        }
        $h .= '</optgroup>';
    }
    return $h;
}
?>

<div class="card">
  <div class="card-header">
    <h2>Esemény-összerendelések</h2>
    <?php if (!empty($unlinkedLabels)): ?>
      <span class="badge badge-overdue" style="font-size:11px;"><?= count($unlinkedLabels) ?> név · <?= (int)$unlinkedCount ?> tétel</span>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <?php if (empty($unlinkedLabels)): ?>
      <div class="empty-state"><div class="empty-icon">✅</div><p>Minden eseményhez kötött tranzakció össze van rendelve konkrét túrával.</p></div>
    <?php else: ?>
      <p style="margin:0 0 14px;font-size:13px;color:var(--text-muted);">
        Az alábbi (importált) esemény-nevek még nincsenek konkrét túrához kötve. Válaszd ki soronként a megfelelő túrát,
        majd <strong>Összerendelések mentése</strong> — a párosítás az adott névvel rendelkező összes tranzakcióra érvényes lesz.
        (A kimutatások a név alapján enélkül is működnek.)
      </p>
      <form method="post" action="<?= BASE_URL ?>/actions/transaction-link-events.php">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="table-wrap" style="max-height:560px;overflow:auto;">
          <table>
            <thead>
              <tr><th>Esemény neve (importált)</th><th style="text-align:center;width:90px;">Tételek</th><th style="width:360px;">Hozzárendelt túra</th></tr>
            </thead>
            <tbody>
              <?php foreach ($unlinkedLabels as $i => $ul):
                $rv  = resolveEventByLabel($pdo, $ul['event_label']);
                $sel = $rv['type'] !== null ? ($rv['type'] . ':' . $rv['id']) : '';
              ?>
              <tr>
                <td style="font-size:13px;font-weight:500;">
                  <?= e($ul['event_label']) ?>
                  <input type="hidden" name="label[<?= $i ?>]" value="<?= e($ul['event_label']) ?>">
                </td>
                <td style="text-align:center;font-size:13px;color:var(--text-muted);"><?= (int)$ul['cnt'] ?></td>
                <td>
                  <select name="event[<?= $i ?>]" class="form-control" style="width:100%;">
                    <?= renderEventOptions($dropTours, $futureTours, $sel) ?>
                  </select>
                  <?php if ($sel !== ''): ?><small style="color:var(--text-muted);font-size:11px;">Javasolt egyezés a név alapján előre kiválasztva.</small><?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div style="margin-top:14px;">
          <button type="submit" class="btn btn-primary">Összerendelések mentése</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($activeTab === 'export'): ?>
<!-- ══════════════════ EXPORTÁLÁS ══════════════════ -->

<div class="card" style="max-width:620px;">
  <div class="card-header"><h2>Exportálás CSV-be</h2></div>
  <div class="card-body">
    <form method="get" action="<?= BASE_URL ?>/actions/transactions-export.php">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;">
        <div class="form-group">
          <label>Dátumtól</label>
          <input type="date" name="date_from">
        </div>
        <div class="form-group">
          <label>Dátumig</label>
          <input type="date" name="date_to">
        </div>
        <div class="form-group">
          <label>Típus</label>
          <select name="type">
            <option value="">Minden típus</option>
            <option value="income">Bevétel</option>
            <option value="expense">Kiadás</option>
          </select>
        </div>
      </div>
      <div style="margin-top:16px;">
        <button type="submit" class="btn btn-primary">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="15" height="15" style="vertical-align:-2px;margin-right:4px;">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
          </svg>
          Exportálás
        </button>
      </div>
      <p style="margin:14px 0 0;font-size:13px;color:var(--text-muted);">
        Üresen hagyott dátum = nincs alsó/felső határ. Az exportált fájl ugyanabban a formátumban van,
        mint az import sablon, így később visszaimportálható. A fájl végén összesítő sorok (bevétel / kiadás / eredmény) szerepelnek.
      </p>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ══════════════════ ELŐRE DEFINIÁLT ÉRTÉKEK ══════════════════ -->

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;align-items:start;">
  <?php
  $presetGroups = [
      'category' => ['Kategóriák', $catPresets],
      'partner'  => ['Partnerek',  $partnerPresets],
      'account'  => ['Számlák',    $accountPresets],
  ];
  foreach ($presetGroups as $type => [$title, $values]):
  ?>
  <div class="card">
    <div class="card-header"><h2><?= $title ?></h2></div>
    <div class="card-body">
      <form method="post" action="<?= BASE_URL ?>/actions/transaction-preset-save.php" style="display:flex;gap:8px;margin-bottom:14px;">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="preset_type" value="<?= $type ?>">
        <input type="text" name="value" required placeholder="Új érték…" style="flex:1;">
        <button type="submit" class="btn btn-primary btn-sm">Hozzáadás</button>
      </form>
      <?php if (empty($values)): ?>
        <p style="color:var(--text-muted);font-size:13px;">Még nincs érték.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <tbody>
              <?php
              // id-k újralekérése a törlés gombhoz
              $rowsStmt = $pdo->prepare("SELECT id, value FROM transaction_presets WHERE preset_type = ? ORDER BY sort_order ASC, value ASC");
              $rowsStmt->execute([$type]);
              foreach ($rowsStmt->fetchAll() as $row):
              ?>
              <tr>
                <td style="font-size:14px;"><?= e($row['value']) ?></td>
                <td class="td-actions" style="text-align:right;width:1%;">
                  <form method="post" action="<?= BASE_URL ?>/actions/transaction-preset-delete.php" style="margin:0;" onsubmit="return confirm('Törlöd ezt az értéket?')">
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

      <?php
        // Összevonás (tisztítás): a transactions oszlopában ÉS a presetek közt előforduló értékek uniója
        $col = $type; // category / partner / account — megegyezik az oszlopnévvel
        $mergeStmt = $pdo->prepare("
            SELECT v, MAX(cnt) AS cnt FROM (
                SELECT `$col` AS v, COUNT(*) AS cnt FROM transactions WHERE `$col` IS NOT NULL AND `$col` <> '' GROUP BY `$col`
                UNION ALL
                SELECT value AS v, 0 AS cnt FROM transaction_presets WHERE preset_type = ?
            ) u GROUP BY v ORDER BY v ASC
        ");
        $mergeStmt->execute([$type]);
        $mergeVals = $mergeStmt->fetchAll();
      ?>
      <?php if (count($mergeVals) > 1): ?>
      <details style="margin-top:14px;border-top:1px solid var(--border);padding-top:12px;">
        <summary style="cursor:pointer;font-size:13px;font-weight:600;">Összevonás / tisztítás</summary>
        <form method="post" action="<?= BASE_URL ?>/actions/transaction-preset-merge.php" style="margin-top:10px;"
              onsubmit="return confirm('Biztosan összevonod a kijelölt értékeket? Az érintett tranzakciók a cél értékre kerülnek át.')">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="preset_type" value="<?= $type ?>">
          <label style="font-size:12px;">Beolvasztandó értékek (ezek megszűnnek) — Ctrl/Cmd-mal több is</label>
          <select name="sources[]" multiple size="6" class="form-control" style="width:100%;margin-bottom:8px;" required>
            <?php foreach ($mergeVals as $mv): ?>
              <option value="<?= e($mv['v']) ?>"><?= e($mv['v']) ?> (<?= (int)$mv['cnt'] ?>)</option>
            <?php endforeach; ?>
          </select>
          <label style="font-size:12px;">Cél érték (ez marad meg)</label>
          <select name="target" class="form-control" style="width:100%;margin-bottom:8px;" required>
            <?php foreach ($mergeVals as $mv): ?>
              <option value="<?= e($mv['v']) ?>"><?= e($mv['v']) ?> (<?= (int)$mv['cnt'] ?>)</option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-secondary btn-sm">Összevonás</button>
          <p style="margin:8px 0 0;font-size:11px;color:var(--text-muted);">A beolvasztott értékkel rendelkező tranzakciók a cél értékre kerülnek; az így kiürült érték törlődik az előre definiáltak közül.</p>
        </form>
      </details>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php endif; ?>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
