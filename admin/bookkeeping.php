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

$activeTab = in_array($_GET['tab'] ?? '', ['presets', 'export'], true) ? $_GET['tab'] : 'transactions';

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

// ── Tranzakciók szűrése ────────────────────────────────────────────
$fYear     = (int)($_GET['year'] ?? 0);
$fType     = in_array($_GET['type'] ?? '', ['income','expense'], true) ? $_GET['type'] : '';
$fCategory = trim($_GET['category'] ?? '');
$fSearch   = trim($_GET['q'] ?? '');

$where = []; $params = [];
if ($fYear > 0)        { $where[] = 'YEAR(tx_date) = ?';  $params[] = $fYear; }
if ($fType !== '')     { $where[] = 'tx_type = ?';        $params[] = $fType; }
if ($fCategory !== '') { $where[] = 'category = ?';       $params[] = $fCategory; }
if ($fSearch !== '')   {
    $where[] = '(description LIKE ? OR partner LIKE ? OR invoice_number LIKE ? OR account LIKE ?)';
    array_push($params, "%$fSearch%", "%$fSearch%", "%$fSearch%", "%$fSearch%");
}
$whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$txStmt = $pdo->prepare("SELECT * FROM transactions $whereClause ORDER BY tx_date DESC, id DESC");
$txStmt->execute($params);
$transactions = $txStmt->fetchAll();

$years = $pdo->query("SELECT DISTINCT YEAR(tx_date) AS y FROM transactions ORDER BY y DESC")->fetchAll(PDO::FETCH_COLUMN);

$hasFilter = $fYear || $fType !== '' || $fCategory !== '' || $fSearch !== '';

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
            <?php if ($partnerPresets): ?>
            <optgroup label="Rögzített partnerek">
              <?php foreach ($partnerPresets as $v): ?><option value="<?= e($v) ?>"><?= e($v) ?></option><?php endforeach; ?>
            </optgroup>
            <?php endif; ?>
            <?php if ($memberNames): ?>
            <optgroup label="Tagok">
              <?php foreach ($memberNames as $v): ?><option value="<?= e($v) ?>"><?= e($v) ?></option><?php endforeach; ?>
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
    <?php if ($hasFilter): ?><a href="?tab=transactions" class="btn btn-ghost btn-sm">Visszaállítás</a><?php endif; ?>
  </form>
</div>

<div class="card">
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
        <?php else: foreach ($transactions as $tx): ?>
        <tr>
          <td style="white-space:nowrap;font-size:13px;"><?= e((new DateTime($tx['tx_date']))->format('Y.m.d')) ?></td>
          <td><span class="badge <?= $tx['tx_type']==='income' ? 'badge-active' : 'badge-inactive' ?>"><?= $tx['tx_type']==='income' ? 'Bevétel' : 'Kiadás' ?></span></td>
          <td style="font-size:13px;"><?= e($tx['category']) ?></td>
          <td style="font-size:13px;max-width:220px;"><?= e($tx['description']) ?></td>
          <td style="font-size:13px;color:var(--text-muted);"><?= $tx['event_label'] ? e($tx['event_label']) : '—' ?></td>
          <td style="font-size:13px;"><?= e($tx['partner']) ?></td>
          <td style="font-size:13px;"><?= e($tx['account']) ?></td>
          <td style="font-size:13px;color:var(--text-muted);"><?= $tx['invoice_number'] ? e($tx['invoice_number']) : '—' ?></td>
          <td style="text-align:right;font-weight:600;white-space:nowrap;color:<?= $tx['tx_type']==='income' ? 'var(--primary)' : 'var(--danger)' ?>;">
            <?= $tx['tx_type']==='income' ? '+' : '−' ?><?= number_format((float)$tx['amount'], 0, ',', ' ') ?> Ft
          </td>
          <td class="td-actions" style="white-space:nowrap;">
            <a href="<?= BASE_URL ?>/admin/transaction-detail.php?id=<?= (int)$tx['id'] ?>" class="btn btn-secondary btn-sm">Szerkesztés</a>
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
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php endif; ?>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
