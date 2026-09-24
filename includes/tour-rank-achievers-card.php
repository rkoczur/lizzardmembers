<?php
/**
 * „Rangot szerzett tagok” kártya — ki melyik rangot érte el ezen a túrán.
 *
 * Bemenet:
 *   $achievers — getTourRankAchievers() eredménye
 */
?>
<div class="card achiever-card">
  <div class="card-header">
    <h2>Rangot szerzett tagok</h2>
    <span class="achiever-count"><?= count($achievers) ?> tag</span>
  </div>
  <?php if (!$achievers): ?>
    <div class="card-body achiever-empty">Ezen a túrán senki nem lépett új rangba.</div>
  <?php else: ?>
    <ul class="achiever-list">
      <?php foreach ($achievers as $a): $top = end($a['levels']); ?>
      <li class="achiever-item">
        <div class="achiever-patch">
          <?php if (getLevelImageFilename($top)): ?>
            <img src="<?= e(getLevelImageUrl($top)) ?>" alt="<?= e(getLevelLabel($top)) ?>">
          <?php endif; ?>
        </div>
        <div class="achiever-info">
          <a class="achiever-name" href="<?= BASE_URL ?>/admin/member-detail.php?id=<?= (int)$a['user']['id'] ?>#rangletra">
            <?= e(trim($a['user']['lastname'] . ' ' . $a['user']['firstname'])) ?>
          </a>
          <div class="achiever-ranks">
            <?php foreach ($a['levels'] as $lvl): ?>
              <span class="level-badge <?= getLevelClass($lvl) ?>"><?= e(getLevelLabel($lvl)) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
