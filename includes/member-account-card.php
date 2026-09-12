<?php
/**
 * Folyószámla kártya törzse — közös az admin tag-adatlap és a tag saját felülete között.
 * Csak a túra-részvételi díjakat mutatja; a tagdíj nem része a folyószámlának.
 *
 * Bemenet:
 *   $account             — getMemberAccount() eredménye
 *   $acctShowUnassigned  — jelenjen-e meg a „nem beazonosított befizetések” blokk (admin: igen)
 */
$acctBalance = (float)$account['balance'];
$acctState   = abs($acctBalance) < 1 ? 'ok' : ($acctBalance < 0 ? 'debt' : 'over');
$acctNote    = match ($acctState) {
    'debt' => 'Ennyivel tartozik',
    'over' => 'Ennyivel fizetett többet',
    default => 'Az egyenleg rendezett',
};
$acctShowUnassigned = $acctShowUnassigned ?? false;
$ft = fn(float $v): string => number_format($v, 0, ',', '&nbsp;') . '&nbsp;Ft';
?>
<div class="acct-summary acct-<?= $acctState ?>">
  <div class="acct-summary-cell">
    <div class="acct-summary-label">Fizetendő volt</div>
    <div class="acct-summary-value"><?= $ft($account['charged']) ?></div>
  </div>
  <div class="acct-summary-cell">
    <div class="acct-summary-label">Befizetve</div>
    <div class="acct-summary-value"><?= $ft($account['paid']) ?></div>
  </div>
  <div class="acct-summary-cell acct-balance">
    <div class="acct-summary-label">Egyenleg</div>
    <div class="acct-balance-value"><?= ($acctBalance > 0 ? '+' : '') . $ft($acctBalance) ?></div>
    <div class="acct-balance-note"><?= e($acctNote) ?></div>
  </div>
</div>

<?php if (empty($account['rows'])): ?>
  <div class="acct-empty">Nincs díjas túra-jelentkezés, amihez részvételi díj tartozna.</div>
<?php else: ?>
<table class="acct-table">
  <thead>
    <tr>
      <th>Tétel</th>
      <th class="acct-num">Fizetendő</th>
      <th class="acct-num">Befizetve</th>
      <th class="acct-num">Eltérés</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($account['rows'] as $row): ?>
    <?php $chip = abs($row['diff']) < 1 ? 'zero' : ($row['diff'] < 0 ? 'under' : 'over'); ?>
    <tr>
      <td>
        <div class="acct-row-label"><span class="acct-kind acct-kind-<?= e($row['kind']) ?>"></span><?= e($row['label']) ?></div>
        <?php if ($row['date']): ?>
          <div class="acct-row-sub"><?= date('Y.m.d', strtotime($row['date'])) ?></div>
        <?php endif; ?>
      </td>
      <td class="acct-num"><?= $ft($row['charged']) ?></td>
      <td class="acct-num"><?= $ft($row['paid']) ?></td>
      <td class="acct-num">
        <span class="acct-chip acct-chip-<?= $chip ?>">
          <?= $chip === 'zero' ? 'rendben' : (($row['diff'] > 0 ? '+' : '') . $ft($row['diff'])) ?>
        </span>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php if ($acctShowUnassigned && !empty($account['unassigned'])): ?>
<div class="acct-unassigned">
  <div class="acct-unassigned-head">Nem beazonosított befizetések (<?= $ft($account['unassigned_total']) ?>)</div>
  <div class="acct-unassigned-hint">
    Ezekhez a tételekhez nem tartozik előírás, ezért nem számítanak bele az egyenlegbe.
    Ha egy tétel egy túra részvételi díja, rendeld hozzá a túrát a tranzakció „Esemény” mezőjében.
  </div>
  <table class="acct-table">
    <tbody>
      <?php foreach ($account['unassigned'] as $u): ?>
      <tr>
        <td>
          <div class="acct-row-label"><?= e($u['label']) ?></div>
          <div class="acct-row-sub"><?= date('Y.m.d', strtotime($u['date'])) ?> &middot; <?= e($u['category']) ?></div>
        </td>
        <td class="acct-num"><?= $ft($u['amount']) ?></td>
        <td class="acct-num">
          <a href="<?= BASE_URL ?>/admin/transaction-detail.php?id=<?= (int)$u['tx_id'] ?>" class="btn btn-ghost btn-sm">Tranzakció</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
