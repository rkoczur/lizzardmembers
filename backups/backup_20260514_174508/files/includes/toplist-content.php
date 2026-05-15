<?php
$pdo = getDb();

// 1. All-time toplist
$allTime = $pdo->query("
    SELECT u.firstname, u.lastname, u.level, u.role, COALESCE(SUM(t.points), 0) AS total_points
    FROM users u
    LEFT JOIN tour_members tm ON tm.user_id = u.id
    LEFT JOIN tours t ON t.id = tm.tour_id
    GROUP BY u.id, u.firstname, u.lastname, u.level, u.role
    ORDER BY total_points DESC
    LIMIT 20
")->fetchAll();

// 2. Current year toplist
$stmtYear = $pdo->prepare("
    SELECT u.firstname, u.lastname, u.role, SUM(t.points) AS total_points
    FROM tour_members tm
    JOIN tours t ON t.id = tm.tour_id AND YEAR(t.tour_date) = :yr
    JOIN users u ON u.id = tm.user_id
    GROUP BY u.id, u.firstname, u.lastname, u.role
    ORDER BY total_points DESC
    LIMIT 20
");
$stmtYear->execute([':yr' => (int)date('Y')]);
$currentYearList = $stmtYear->fetchAll();

// 3. Top scorer per year
$allYearRows = $pdo->query("
    SELECT YEAR(t.tour_date) AS yr, u.firstname, u.lastname, u.role, SUM(t.points) AS total_points
    FROM tour_members tm
    JOIN tours t ON t.id = tm.tour_id
    JOIN users u ON u.id = tm.user_id
    WHERE t.tour_date IS NOT NULL
    GROUP BY YEAR(t.tour_date), u.id, u.firstname, u.lastname, u.role
    ORDER BY yr DESC, total_points DESC
")->fetchAll();

$byYearTop = [];
foreach ($allYearRows as $row) {
    if (!isset($byYearTop[$row['yr']])) {
        $byYearTop[$row['yr']] = $row;
    }
}

?>

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;align-items:start;">

  <!-- All-time -->
  <div class="card">
    <div class="card-header">
      <h2>Örökös toplista</h2>
      <span class="badge badge-active" style="font-size:11px;">Összes pont</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:32px;">#</th>
            <th></th>
            <th>Név</th>
            <th>Rang</th>
            <th style="text-align:right;">Pontok</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($allTime)): ?>
            <tr><td colspan="5" class="empty-state"><p>Még nincs adat.</p></td></tr>
          <?php else: ?>
            <?php foreach ($allTime as $i => $row): ?>
            <?php $lvl = (int)$row['level']; ?>
            <tr>
              <td style="color:var(--text-muted);font-size:13px;"><?= $i + 1 ?></td>
              <td>
                <?php $lvlImg = getLevelImageFilename($lvl); if ($lvlImg): ?>
                <img src="<?= BASE_URL ?>/assets/img/<?= $lvlImg ?>" alt="<?= getLevelLabel($lvl) ?>">
                <?php endif; ?>
              </td>
              <td>
                <?= e($row['lastname'] . ' ' . $row['firstname']) ?>
                <?php if (($row['role'] ?? '') === 'admin'): ?>
                  <span class="badge badge-admin" style="font-size:10px;padding:2px 6px;vertical-align:middle;margin-left:4px;">Admin</span>
                <?php endif; ?>
              </td>
              <td><span class="level-badge <?= getLevelClass($lvl) ?>"><?= getLevelLabel($lvl) ?></span></td>
              <td style="text-align:right;"><strong><?= number_format((int)$row['total_points']) ?></strong></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Current year -->
  <div class="card">
    <div class="card-header">
      <h2><?= date('Y') ?>. évi toplista</h2>
      <span class="badge badge-active" style="font-size:11px;">Idei pont</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:32px;">#</th>
            <th>Név</th>
            <th style="text-align:right;">Pontok</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($currentYearList)): ?>
            <tr><td colspan="3" class="empty-state"><p>Még nincs idei túra.</p></td></tr>
          <?php else: ?>
            <?php foreach ($currentYearList as $i => $row): ?>
            <tr>
              <td style="color:var(--text-muted);font-size:13px;"><?= $i + 1 ?></td>
              <td>
                <?= e($row['lastname'] . ' ' . $row['firstname']) ?>
                <?php if (($row['role'] ?? '') === 'admin'): ?>
                  <span class="badge badge-admin" style="font-size:10px;padding:2px 6px;vertical-align:middle;margin-left:4px;">Admin</span>
                <?php endif; ?>
              </td>
              <td style="text-align:right;"><strong><?= number_format((int)$row['total_points']) ?></strong></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Top scorer per year -->
  <div class="card">
    <div class="card-header">
      <h2>Éves bajnokok</h2>
      <span class="badge badge-active" style="font-size:11px;">Évenként legtöbb pont</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:48px;">Év</th>
            <th>Név</th>
            <th style="text-align:right;">Pontok</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($byYearTop)): ?>
            <tr><td colspan="3" class="empty-state"><p>Még nincs adat.</p></td></tr>
          <?php else: ?>
            <?php foreach ($byYearTop as $yr => $row): ?>
            <tr>
              <td><strong><?= (int)$yr ?></strong></td>
              <td>
                <?= e($row['lastname'] . ' ' . $row['firstname']) ?>
                <?php if (($row['role'] ?? '') === 'admin'): ?>
                  <span class="badge badge-admin" style="font-size:10px;padding:2px 6px;vertical-align:middle;margin-left:4px;">Admin</span>
                <?php endif; ?>
              </td>
              <td style="text-align:right;"><strong><?= number_format((int)$row['total_points']) ?></strong></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
