<?php
/**
 * „Tartozásaim” kártya — a tag rendezetlen tételei.
 * Két forrás: a folyószámla túra-részvételi díjai + az esetleges tagdíj elmaradás
 * (a tagdíj nem része a folyószámlának, ezért külön tételként jelenik meg).
 *
 * Bemenet:
 *   $account — getMemberAccount() eredménye
 *   $user    — a tag sora (a last_payment mező kell belőle)
 */
$debts     = array_values(array_filter($account['rows'], fn($r) => $r['diff'] <= -1));
$debtTotal = array_sum(array_map(fn($r) => -$r['diff'], $debts));
$feeDebt   = getMembershipDebt($user['last_payment'] ?? null);
if ($feeDebt) {
    $debtTotal += $feeDebt['amount'];
}
$debtCount = count($debts) + ($feeDebt ? 1 : 0);
?>
<div class="card dash-card-debts">
  <div class="card-header"><h2>Tartozásaim</h2></div>
  <div class="card-body debt-body">
    <?php if ($debtCount === 0): ?>
      <div class="debt-empty"><span class="debt-empty-icon">✅</span> Nincs rendezetlen tartozásod.</div>
    <?php else: ?>
      <table class="debt-table">
        <tbody>
          <?php if ($feeDebt): ?>
          <tr>
            <td>
              <?= e($feeDebt['label']) ?>
              <span class="badge badge-overdue debt-badge">Tagdíj elmaradás</span>
              <div class="debt-note"><?= e($feeDebt['note']) ?></div>
            </td>
            <td class="debt-amount"><?= number_format($feeDebt['amount'], 0, ',', ' ') ?> Ft</td>
          </tr>
          <?php endif; ?>
          <?php foreach ($debts as $d): ?>
          <tr>
            <td>
              <?= e($d['label']) ?>
              <?php if (!empty($d['tour_id'])): ?>
                <a class="debt-link" href="<?= BASE_URL ?>/user/future-tour-detail.php?id=<?= (int)$d['tour_id'] ?>">részletek</a>
              <?php endif; ?>
              <?php if ($d['paid'] > 0): ?>
                <div class="debt-note">
                  Fizetendő: <?= number_format($d['charged'], 0, ',', ' ') ?> Ft &middot;
                  befizetve: <?= number_format($d['paid'], 0, ',', ' ') ?> Ft
                </div>
              <?php endif; ?>
            </td>
            <td class="debt-amount"><?= number_format(-$d['diff'], 0, ',', ' ') ?> Ft</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <?php if ($debtCount > 1): ?>
        <tfoot>
          <tr class="debt-total">
            <td>Összesen</td>
            <td class="debt-amount"><?= number_format($debtTotal, 0, ',', ' ') ?> Ft</td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
      <div class="debt-bank"><?= bankInfoBox('strong') ?></div>
    <?php endif; ?>
  </div>
</div>
