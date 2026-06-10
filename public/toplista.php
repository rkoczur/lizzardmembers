<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/user-schema.php';

$pdo = getDb();
ensureUserSchema($pdo);

$allTime = $pdo->query("
    SELECT u.firstname, u.lastname, u.level, u.role, COALESCE(SUM(t.points), 0) AS total_points
    FROM users u
    LEFT JOIN tour_members tm ON tm.user_id = u.id
    LEFT JOIN tours t ON t.id = tm.tour_id
    WHERE u.role != 'admin'
      AND COALESCE(u.is_candidate, 0) = 0
      AND u.last_payment IS NOT NULL
      AND u.last_payment != '0000-00-00'
      AND YEAR(u.last_payment) >= YEAR(CURDATE()) - 1
    GROUP BY u.id, u.firstname, u.lastname, u.level, u.role
    HAVING total_points >= 3
    ORDER BY total_points DESC
")->fetchAll();

$pageTitle     = 'Örökös toplista – Lizzardier pontverseny';
$activePubPage = 'toplista';
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap-narrow">
  <div class="pub-page-header">
    <h1>Örökös toplista</h1>
    <p>A Lizzardier pontverseny összes pontot összesítő ranglistája.</p>
  </div>

  <div class="card">
    <div class="card-header">
      <h2>Örökös toplista</h2>
      <span class="badge badge-active" style="font-size:11px;">Összes pont</span>
    </div>
    <div class="table-wrap">
      <table class="pub-toplist-table">
        <thead>
          <tr>
            <th style="width:36px;">#</th>
            <th style="width:52px;"></th>
            <th>Név / Rang</th>
            <th style="text-align:right;">Pontok</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($allTime)): ?>
            <tr><td colspan="4" class="empty-state"><p>Még nincs adat.</p></td></tr>
          <?php else: ?>
            <?php foreach ($allTime as $i => $row): ?>
            <?php $lvl = (int)$row['level']; ?>
            <tr>
              <td class="tl-rank-num"><?= $i + 1 ?></td>
              <td class="td-rank-img tl-rank-img">
                <?php $lvlImg = getLevelImageFilename($lvl); if ($lvlImg): ?>
                <img src="<?= BASE_URL ?>/assets/img/<?= $lvlImg ?>" alt="<?= getLevelLabel($lvl) ?>">
                <?php endif; ?>
              </td>
              <td class="tl-info">
                <span class="tl-name"><?= e($row['lastname'] . ' ' . $row['firstname']) ?></span>
                <span class="tl-badge"><span class="level-badge <?= getLevelClass($lvl) ?>"><?= getLevelLabel($lvl) ?></span></span>
              </td>
              <td class="tl-pts" style="text-align:right;"><strong><?= number_format((int)$row['total_points']) ?></strong></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
