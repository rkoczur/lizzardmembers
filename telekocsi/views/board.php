<?php /** Egy szervező oldala. Vár: $board, $me, $people, $drivers, $role, $freeTotal, $post. */ ?>
<header class="tk-board-head">
  <a href="<?= e(tkBase()) ?>/" class="tk-brand">Telekocsi</a>
  <p class="tk-plate-label">A szervező azonosítója</p>
  <div class="tk-plate" title="A szervező egyedi kódja">
    <span class="tk-plate-band" aria-hidden="true">TK</span>
    <span class="tk-plate-code"><?= e(tkFormatCode($board['code'])) ?></span>
  </div>
  <h1 class="tk-board-title"><?= e($board['title'] ?: 'Közös utazás') ?></h1>
  <div class="tk-share">
    <input type="text" class="tk-share-url" readonly value="<?= e(tkBoardUrl($board['code'], true)) ?>" data-tk-url>
    <button type="button" class="tk-btn tk-btn-ghost" data-tk-copy>Link másolása</button>
  </div>
  <p class="tk-share-hint">Mentsd el ezt a linket, és küldd el az útitársaidnak — ezzel bármikor visszatalálsz.</p>
  <div class="tk-stats">
    <div><strong><?= count($people) ?></strong><span>résztvevő</span></div>
    <div><strong><?= count($drivers) ?></strong><span>autó</span></div>
    <div><strong><?= $freeTotal ?></strong><span>szabad hely</span></div>
  </div>
</header>

<?php if (!$me): ?>
  <?php include __DIR__ . '/join.php'; ?>
<?php else: ?>
  <?php include __DIR__ . '/me.php'; ?>
  <section class="tk-board">
    <h2 class="tk-h2 tk-h2-light">Autók</h2>
    <?php if (!$drivers): ?>
      <div class="tk-empty">Még nincs sofőr. Ha autóval jössz, légy te az első!</div>
    <?php endif; ?>
    <div class="tk-cars">
      <?php foreach ($drivers as $car) include __DIR__ . '/car.php'; ?>
    </div>
  </section>
  <?php include __DIR__ . '/people.php'; ?>
<?php endif; ?>
