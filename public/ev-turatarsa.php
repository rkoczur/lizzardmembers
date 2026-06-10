<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/user-schema.php';

$pdo = getDb();
ensureUserSchema($pdo);

// Compute year-winner list (same logic as toplist-content.php byYearTop)
$allYearRows = $pdo->query("
    SELECT YEAR(t.tour_date) AS yr, u.id, u.firstname, u.lastname, u.role, u.level,
           SUM(t.points) AS pts
    FROM tour_members tm
    JOIN tours t ON t.id = tm.tour_id
    JOIN users u ON u.id = tm.user_id
    WHERE t.tour_date IS NOT NULL AND u.role != 'admin'
          AND COALESCE(u.is_candidate, 0) = 0
          AND YEAR(t.tour_date) < YEAR(CURDATE())
    GROUP BY YEAR(t.tour_date), u.id, u.firstname, u.lastname, u.role
    ORDER BY yr ASC
")->fetchAll();

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
        if ($prevChampionId !== null && $uid !== $prevChampionId) {
            $prevPts = (float)($prevYearUsers[$uid]['pts'] ?? 0);
            $bonus   = $prevPts * 0.20;
            $score  += $bonus;
        }
        $adjusted[$uid] = ['score' => $score, 'raw' => (float)$udata['pts'], 'bonus' => $bonus, 'data' => $udata];
    }
    uasort($adjusted, fn($a, $b) => $b['score'] <=> $a['score']);
    $winnerId = array_key_first($adjusted);
    $w        = $adjusted[$winnerId];
    $lvl      = (int)($w['data']['level'] ?? 1);
    $byYearTop[$yr] = [
        'yr'           => $yr,
        'firstname'    => $w['data']['firstname'],
        'lastname'     => $w['data']['lastname'],
        'level'        => $lvl,
        'total_points' => round($w['score'], 1),
        'raw_points'   => (int)$w['raw'],
        'bonus'        => round($w['bonus'], 1),
    ];
    $prevChampionId = $winnerId;
}
krsort($byYearTop);

$pageTitle     = 'Az év túratársa';
$activePubPage = 'ev-turatarsa';
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap-narrow">
  <div class="pub-page-header">
    <h1>Az év túratársa</h1>
    <p>A legelkötelezetteb tagjaink</p>
  </div>

  <div class="pub-info-box">
    <strong>A verseny szabályai:</strong> A pontok számlálása január 1-jétől december 31-ig tart. Csak részvételi pontok számítanak (szervezői bónuszok nem). Az előző év nyertesén kívl minden más tag az előző évi pontjainak 20%-át bónuszként megkapja az idei összegéhez.
  </div>

  <?php if (empty($byYearTop)): ?>
    <div style="text-align:center;padding:48px;color:var(--text-muted);">Még nincs elegendő adat az eredmény kiszámításához.</div>
  <?php else: ?>
  <div class="card">
    <div class="table-wrap">
      <table class="pub-toplist-table">
        <thead>
          <tr>
            <th style="width:60px;">Év</th>
            <th style="width:80px;"></th>
            <th>Nyertes</th>
            <th style="text-align:right;">Pontok</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($byYearTop as $yr => $row): ?>
          <?php $img = getLevelImageFilename($row['level']); ?>
          <tr>
            <td class="tl-rank-num tl-yr"><?= (int)$yr ?></td>
            <td class="td-rank-img tl-rank-img">
              <?php if ($img): ?>
                <img src="<?= BASE_URL ?>/assets/img/<?= $img ?>" alt="<?= getLevelLabel($row['level']) ?>">
              <?php endif; ?>
            </td>
            <td class="tl-info">
              <span class="tl-yr-mobile"><?= (int)$yr ?></span>
              <span class="tl-name"><?= e($row['lastname'] . ' ' . $row['firstname']) ?></span>
              <span class="tl-badge"><span class="level-badge <?= getLevelClass($row['level']) ?>"><?= getLevelLabel($row['level']) ?></span></span>
            </td>
            <td class="tl-pts">
              <strong><?= number_format($row['total_points'], 1) ?></strong>
              <?php if ($row['bonus'] > 0): ?>
                <span class="tl-pts-bonus"><?= number_format($row['raw_points']) ?> + <?= number_format($row['bonus'], 1) ?> bónusz</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <div style="margin-top:24px;">
    <a href="<?= BASE_URL ?>/public/toplista.php" class="btn btn-ghost">← Örökös toplista</a>
  </div>
</div>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
