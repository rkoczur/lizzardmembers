<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';

$pdo = getDb();
ensurePublicSchema($pdo);

$rows = $pdo->query("SELECT * FROM finances ORDER BY year DESC, category ASC, sort_order ASC, id ASC")->fetchAll();
$grouped = [];
foreach ($rows as $r) {
    $grouped[(int)$r['year']][$r['category']][] = $r;
}
krsort($grouped);

$pageTitle     = 'Pénzügyek';
$activePubPage = 'penzugyek';
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap-narrow">
  <div class="pub-page-header">
    <h1>Pénzügyek</h1>
    <p>Az egyesület éves pénzügyi összesítői.</p>
  </div>

  <?php if (empty($grouped)): ?>
    <div style="text-align:center;padding:60px 20px;color:var(--text-muted);">
      <div style="font-size:48px;margin-bottom:12px;">📊</div>
      <p>Hamarosan elérhetők lesznek a pénzügyi adatok.</p>
    </div>
  <?php else: ?>
    <?php foreach ($grouped as $yr => $cats): ?>
    <?php
    $totalIncome  = array_sum(array_column($cats['income']  ?? [], 'amount'));
    $totalExpense = array_sum(array_column($cats['expense'] ?? [], 'amount'));
    $result       = $totalIncome - $totalExpense;
    ?>
    <details class="pub-finance-year">
      <summary>
        <span class="pub-finance-yr-label"><?= (int)$yr ?></span>
        <div class="pub-finance-summary-right">
          <div class="pub-finance-result-block">
            <span class="pub-finance-result-label">Eredmény</span>
            <span class="pub-finance-result-value <?= $result >= 0 ? 'positive' : 'negative' ?>">
              <?= $result >= 0 ? '+' : '' ?><?= number_format((int)$result, 0, ',', ' ') ?> Ft
            </span>
          </div>
          <span class="pub-finance-toggle-btn">
            <span class="pub-finance-toggle-text">Részletek</span>
            <svg class="pub-finance-chevron" width="14" height="14" viewBox="0 0 14 14" fill="none">
              <path d="M3 5l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </div>
      </summary>
      <div class="pub-finance-grid">
        <div class="pub-finance-col">
          <h3>Bevételek</h3>
          <?php foreach ($cats['income'] ?? [] as $row): ?>
          <div class="pub-finance-row">
            <span><?= e($row['label']) ?></span>
            <span><?= number_format((int)$row['amount'], 0, ',', ' ') ?> Ft</span>
          </div>
          <?php endforeach; ?>
          <div class="pub-finance-total">
            <span>Összesen</span>
            <span style="color:var(--success);"><?= number_format((int)$totalIncome, 0, ',', ' ') ?> Ft</span>
          </div>
        </div>
        <div class="pub-finance-col">
          <h3>Kiadások</h3>
          <?php foreach ($cats['expense'] ?? [] as $row): ?>
          <div class="pub-finance-row">
            <span><?= e($row['label']) ?></span>
            <span><?= number_format((int)$row['amount'], 0, ',', ' ') ?> Ft</span>
          </div>
          <?php endforeach; ?>
          <div class="pub-finance-total">
            <span>Összesen</span>
            <span style="color:var(--danger);"><?= number_format((int)$totalExpense, 0, ',', ' ') ?> Ft</span>
          </div>
        </div>
      </div>
    </details>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
