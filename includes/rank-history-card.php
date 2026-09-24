<?php
/**
 * Ranglétra kártya — a tag elért rangjai, a legújabb felül.
 *
 * Bemenet:
 *   $ranks       — getRankHistory() eredménye
 *   $rankPoints  — a tag jelenlegi pontszáma
 *   $rankTourUrl — túra-link előtag (pl. BASE_URL . '/user/tour-detail.php?id=')
 */
$rankTop  = end($ranks)['level'];
$rankNext = $rankTop < 9 ? $rankTop + 1 : null;
?>
<div class="card rank-card" id="rangletra">
  <div class="card-header"><h2>Elért rangok</h2></div>
  <div class="card-body">
    <ol class="rank-ladder">
      <?php if ($rankNext): ?>
      <li class="rank-rung rank-rung-locked">
        <div class="rank-patch">
          <?php if (getLevelImageFilename($rankNext)): ?>
            <img src="<?= e(getLevelImageUrl($rankNext)) ?>" alt="<?= e(getLevelLabel($rankNext)) ?>">
          <?php else: ?>
            <span class="rank-patch-empty"><?= $rankNext ?></span>
          <?php endif; ?>
        </div>
        <div class="rank-info">
          <div class="rank-title"><?= e(getLevelLabel($rankNext)) ?></div>
          <div class="rank-meta">Még <?= getLevelMinPoints($rankNext) - (int)$rankPoints ?> pont hiányzik (<?= getLevelMinPoints($rankNext) ?> pont)</div>
        </div>
      </li>
      <?php endif; ?>

      <?php foreach (array_reverse($ranks) as $r): $tour = $r['tour']; ?>
      <li class="rank-rung<?= $r['level'] === $rankTop ? ' rank-rung-current' : '' ?>">
        <div class="rank-patch">
          <?php if (getLevelImageFilename($r['level'])): ?>
            <img src="<?= e(getLevelImageUrl($r['level'])) ?>" alt="<?= e(getLevelLabel($r['level'])) ?>">
          <?php else: ?>
            <span class="rank-patch-empty"><?= $r['level'] ?></span>
          <?php endif; ?>
        </div>
        <div class="rank-info">
          <div class="rank-title">
            <?= e(getLevelLabel($r['level'])) ?>
            <?php if ($r['level'] === $rankTop): ?><span class="rank-now">Jelenlegi rang</span><?php endif; ?>
          </div>
          <?php if ($tour): ?>
            <a class="rank-tour" href="<?= e($rankTourUrl . (int)$tour['id']) ?>"><?= e($tour['name'] ?: getTourPlace($tour)) ?></a>
            <div class="rank-meta">
              <?= e(getTourPlace($tour) ?: '—') ?>
              <span class="rank-sep">/</span>
              <?= formatDate($tour['tour_date']) ?>
              <span class="rank-sep">/</span>
              <?= (int)$r['total'] ?> pont
            </div>
          <?php else: ?>
            <div class="rank-meta">Kezdő rang — minden tag innen indul</div>
          <?php endif; ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ol>
  </div>
</div>
