<?php
require_once __DIR__ . '/user-schema.php';
$pdo = getDb();
ensureUserSchema($pdo);

// "Jelölt" tagok elrejtése — alapból igen (tag/nyilvános nézet); admin nézetben $hideCandidates=false.
$hideCandidates = $hideCandidates ?? true;
$candFilter = $hideCandidates ? "AND COALESCE(u.is_candidate, 0) = 0" : "";

// 1. All-time toplist
$allTime = $pdo->query("
    SELECT u.firstname, u.lastname, u.level, u.role, COALESCE(SUM(t.points), 0) AS total_points
    FROM users u
    LEFT JOIN tour_members tm ON tm.user_id = u.id
    LEFT JOIN tours t ON t.id = tm.tour_id
    WHERE u.role != 'admin' $candFilter
      AND u.last_payment IS NOT NULL
      AND u.last_payment != '0000-00-00'
      AND YEAR(u.last_payment) >= YEAR(CURDATE()) - 1
    GROUP BY u.id, u.firstname, u.lastname, u.level, u.role
    HAVING total_points >= 3
    ORDER BY total_points DESC
")->fetchAll();

// 2. Current year toplist
$stmtYear = $pdo->prepare("
    SELECT u.firstname, u.lastname, u.role, u.level, SUM(t.points) AS total_points
    FROM tour_members tm
    JOIN tours t ON t.id = tm.tour_id AND YEAR(t.tour_date) = :yr
    JOIN users u ON u.id = tm.user_id
    WHERE u.role != 'admin' $candFilter
    GROUP BY u.id, u.firstname, u.lastname, u.role, u.level
    ORDER BY total_points DESC
    LIMIT 20
");
$stmtYear->execute([':yr' => (int)date('Y')]);
$currentYearList = $stmtYear->fetchAll();

// 3. Év túratársa — korrigált pontszámmal
// Szabály: idei pont + HA NEM ő volt az előző éves bajnok → előző évi pontjainak 20%-a
$allYearRows = $pdo->query("
    SELECT YEAR(t.tour_date) AS yr, u.id, u.firstname, u.lastname, u.role, u.level,
           SUM(t.points) AS pts
    FROM tour_members tm
    JOIN tours t ON t.id = tm.tour_id
    JOIN users u ON u.id = tm.user_id
    WHERE t.tour_date IS NOT NULL AND u.role != 'admin' $candFilter
          AND YEAR(t.tour_date) < YEAR(CURDATE())
    GROUP BY YEAR(t.tour_date), u.id, u.firstname, u.lastname, u.role
    ORDER BY yr ASC
")->fetchAll();

// [év][user_id] => row
$pointsByYear = [];
foreach ($allYearRows as $row) {
    $pointsByYear[(int)$row['yr']][(int)$row['id']] = $row;
}

$byYearTop      = [];
$prevChampionId = null;

foreach ($pointsByYear as $yr => $users) {
    $prevYearUsers = $pointsByYear[$yr - 1] ?? [];
    $adjusted      = [];

    foreach ($users as $uid => $udata) {
        $score = (float)$udata['pts'];
        $bonus = 0.0;

        // Nem védi bajnok → kap 20%-ot az előző évi saját pontjaiból
        if ($prevChampionId !== null && $uid !== $prevChampionId) {
            $prevPts = (float)($prevYearUsers[$uid]['pts'] ?? 0);
            $bonus   = $prevPts * 0.20;
            $score  += $bonus;
        }

        $adjusted[$uid] = [
            'score'     => $score,
            'raw'       => (float)$udata['pts'],
            'bonus'     => $bonus,
            'data'      => $udata,
        ];
    }

    // Csökkenő sorrend a korrigált pont alapján
    uasort($adjusted, fn($a, $b) => $b['score'] <=> $a['score']);
    $winnerId = array_key_first($adjusted);
    $w        = $adjusted[$winnerId];

    $byYearTop[$yr] = [
        'yr'           => $yr,
        'id'           => $winnerId,
        'firstname'    => $w['data']['firstname'],
        'lastname'     => $w['data']['lastname'],
        'role'         => $w['data']['role'],
        'level'        => (int)($w['data']['level'] ?? 1),
        'total_points' => $w['score'],
        'raw_points'   => $w['raw'],
        'bonus'        => $w['bonus'],
    ];

    $prevChampionId = $winnerId;
}

krsort($byYearTop); // újabb évek elöl

?>

<div class="toplist-mobile-tabs">
  <button class="tl-tab active" data-target="toplist-alltime">Örökös</button>
  <button class="tl-tab" data-target="toplist-year"><?= date('Y') ?></button>
  <button class="tl-tab" data-target="toplist-yearwinner">Év túratársa</button>
</div>

<div class="rg-3">

  <!-- All-time -->
  <div class="card" id="toplist-alltime">
    <div class="card-header">
      <h2>Örökös toplista</h2>
      <span class="badge badge-active" style="font-size:11px;">Összes pont</span>
    </div>
    <div class="table-wrap">
      <table class="pub-toplist-table">
        <thead>
          <tr>
            <th style="width:36px;">#</th>
            <th style="width:80px;"></th>
            <th>Név / Rang</th>
            <th style="text-align:right;">Pontok</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($allTime)): ?>
            <tr><td colspan="4" class="empty-state"><p>Még nincs adat.</p></td></tr>
          <?php else: ?>
            <?php foreach ($allTime as $i => $row): ?>
            <?php $lvl = (int)$row['level']; $lvlImg = getLevelImageFilename($lvl); ?>
            <tr>
              <td class="tl-rank-num"><?= $i + 1 ?></td>
              <td class="td-rank-img tl-rank-img">
                <?php if ($lvlImg): ?>
                <img src="<?= BASE_URL ?>/assets/img/<?= $lvlImg ?>" alt="<?= e(getLevelLabel($lvl)) ?>">
                <?php endif; ?>
              </td>
              <td class="tl-info">
                <span class="tl-name"><?= e($row['lastname'] . ' ' . $row['firstname']) ?></span>
                <span class="tl-badge"><span class="level-badge <?= getLevelClass($lvl) ?>"><?= getLevelLabel($lvl) ?></span></span>
              </td>
              <td class="tl-pts"><strong><?= number_format((int)$row['total_points']) ?></strong></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Current year -->
  <div class="card" id="toplist-year">
    <div class="card-header">
      <h2><?= date('Y') ?>. évi toplista</h2>
      <span class="badge badge-active" style="font-size:11px;">Idei pont</span>
    </div>
    <div class="table-wrap">
      <table class="pub-toplist-table">
        <thead>
          <tr>
            <th style="width:36px;">#</th>
            <th style="width:80px;"></th>
            <th>Név / Rang</th>
            <th style="text-align:right;">Pontok</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($currentYearList)): ?>
            <tr><td colspan="4" class="empty-state"><p>Még nincs idei túra.</p></td></tr>
          <?php else: ?>
            <?php foreach ($currentYearList as $i => $row): ?>
            <?php $lvl = (int)($row['level'] ?? 1); $lvlImg = getLevelImageFilename($lvl); ?>
            <tr>
              <td class="tl-rank-num"><?= $i + 1 ?></td>
              <td class="td-rank-img tl-rank-img">
                <?php if ($lvlImg): ?>
                <img src="<?= BASE_URL ?>/assets/img/<?= $lvlImg ?>" alt="<?= e(getLevelLabel($lvl)) ?>">
                <?php endif; ?>
              </td>
              <td class="tl-info">
                <span class="tl-name"><?= e($row['lastname'] . ' ' . $row['firstname']) ?></span>
                <span class="tl-badge"><span class="level-badge <?= getLevelClass($lvl) ?>"><?= getLevelLabel($lvl) ?></span></span>
              </td>
              <td class="tl-pts"><strong><?= number_format((int)$row['total_points']) ?></strong></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Top scorer per year -->
  <div class="card" id="toplist-yearwinner">
    <div class="card-header">
      <h2>Év túratársa</h2>
      <span class="badge badge-active" style="font-size:11px;">Korrigált pont</span>
    </div>
    <div class="table-wrap">
      <table class="pub-toplist-table">
        <thead>
          <tr>
            <th style="width:60px;">Év</th>
            <th style="width:80px;"></th>
            <th>Név / Rang</th>
            <th style="text-align:right;">Pontok</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($byYearTop)): ?>
            <tr><td colspan="4" class="empty-state"><p>Még nincs adat.</p></td></tr>
          <?php else: ?>
            <?php foreach ($byYearTop as $yr => $row): ?>
            <?php $lvl = (int)($row['level'] ?? 1); $lvlImg = getLevelImageFilename($lvl); ?>
            <tr>
              <td class="tl-rank-num tl-yr"><?= (int)$yr ?></td>
              <td class="td-rank-img tl-rank-img">
                <?php if ($lvlImg): ?>
                <img src="<?= BASE_URL ?>/assets/img/<?= $lvlImg ?>" alt="<?= e(getLevelLabel($lvl)) ?>">
                <?php endif; ?>
              </td>
              <td class="tl-info">
                <span class="tl-yr-mobile"><?= (int)$yr ?></span>
                <span class="tl-name"><?= e($row['lastname'] . ' ' . $row['firstname']) ?></span>
                <span class="tl-badge"><span class="level-badge <?= getLevelClass($lvl) ?>"><?= getLevelLabel($lvl) ?></span></span>
              </td>
              <td class="tl-pts">
                <strong><?= number_format($row['total_points'], 1) ?></strong>
                <?php if ($row['bonus'] > 0): ?>
                  <span class="tl-pts-bonus"><?= number_format($row['raw_points'], 0) ?> + <?= number_format($row['bonus'], 1) ?> bónusz</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>
(function () {
  var BREAKPOINT = 768;
  var tabs  = document.querySelectorAll('.tl-tab');
  var cards = document.querySelectorAll('.rg-3 .card[id^="toplist-"]');

  function isMobile() { return window.innerWidth <= BREAKPOINT; }

  function showPanel(targetId) {
    cards.forEach(function (c) { c.classList.add('tl-hidden'); });
    var el = document.getElementById(targetId);
    if (el) el.classList.remove('tl-hidden');
  }

  function resetPanels() {
    cards.forEach(function (c) { c.classList.remove('tl-hidden'); });
  }

  function initTabs() {
    if (isMobile()) {
      var active = document.querySelector('.tl-tab.active');
      showPanel(active ? active.dataset.target : 'toplist-alltime');
    } else {
      resetPanels();
    }
  }

  tabs.forEach(function (btn) {
    btn.addEventListener('click', function () {
      tabs.forEach(function (b) { b.classList.remove('active'); });
      this.classList.add('active');
      if (isMobile()) showPanel(this.dataset.target);
    });
  });

  window.addEventListener('resize', initTabs);
  initTabs();
})();
</script>
