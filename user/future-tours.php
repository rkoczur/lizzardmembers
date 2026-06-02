<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
requireUser();

$pdo    = getDb();
ensureFutureToursSchema($pdo);
$userId = getCurrentUserId();

$tours = $pdo->query("
    SELECT ft.*,
           c.name_hu AS country_name, c.flag_filename AS country_flag,
           (SELECT COUNT(*) FROM future_tour_applications fta WHERE fta.future_tour_id = ft.id AND fta.status = 'confirmed') AS confirmed_count,
           (SELECT COUNT(*) FROM future_tour_applications fta WHERE fta.future_tour_id = ft.id AND fta.status = 'waitlist')  AS waitlist_count
    FROM future_tours ft
    LEFT JOIN countries c ON c.code = ft.country
    WHERE ft.status != 'cancelled'
    ORDER BY ft.start_date ASC, ft.created_at DESC
")->fetchAll();

$myApplicationStmt = $pdo->prepare("SELECT future_tour_id, status FROM future_tour_applications WHERE user_id = ? AND status != 'cancelled'");
$myApplicationStmt->execute([$userId]);
$myApps = array_column($myApplicationStmt->fetchAll(), 'status', 'future_tour_id');

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$pageTitle  = 'Meghirdetett Túrák';
$activePage = 'future-tours';
include __DIR__ . '/../includes/user-header.php';
?>

<?php if ($flash_success): ?>
  <div class="alert alert-success" data-auto-dismiss><?= e($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_error): ?>
  <div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="page-header">
  <h1>Meghirdetett Túrák</h1>
</div>

<div class="card">
  <div class="table-wrap">
    <table id="future-tours-table">
      <thead>
        <tr>
          <th>Túra neve</th>
          <th>Ország / Tájegység</th>
          <th>Időpont</th>
          <th>Napok</th>
          <th>Helyek</th>
          <th>Státusz</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tours as $t): ?>
        <?php
        $confirmed  = (int)$t['confirmed_count'];
        $maxSlots   = (int)$t['max_attendees'];
        $spotsLeft  = max(0, $maxSlots - $confirmed);
        $myStatus   = $myApps[$t['id']] ?? null;
        ?>
        <tr>
          <td>
            <div class="td-name"><?= e($t['name']) ?></div>
            <?php if ($myStatus === 'confirmed'): ?>
              <span class="badge-confirmed">Jelentkeztem</span>
            <?php elseif ($myStatus === 'waitlist'): ?>
              <span class="badge-waitlist">Várólistán</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!empty($t['country_flag'])): ?>
              <img src="<?= e(getFlagUrl($t['country_flag'])) ?>"
                   class="flag-img" style="margin-right:4px;" alt="">
            <?php endif; ?>
            <?= e($t['country_name'] ?? $t['country'] ?? '—') ?>
            <?php if (!empty($t['region'])): ?>
              <div style="font-size:11px;color:var(--text-muted);"><?= e($t['region']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= $t['start_date'] ? formatDate($t['start_date']) : '—' ?></td>
          <td><?= (int)$t['num_days'] ?> nap</td>
          <td>
            <?php if ($spotsLeft > 0): ?>
              <span style="color:var(--primary);font-weight:600;"><?= $spotsLeft ?></span>
              <span style="color:var(--text-muted);font-size:12px;">/ <?= $maxSlots ?> szabad</span>
            <?php else: ?>
              <span style="color:var(--danger);font-weight:600;">Betelt</span>
              <?php if ((int)$t['waitlist_count'] > 0): ?>
                <span style="color:var(--text-muted);font-size:11px;">(+<?= (int)$t['waitlist_count'] ?> várólistán)</span>
              <?php endif; ?>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($t['status'] === 'open'): ?>
              <span class="badge badge-active">Nyitott</span>
            <?php else: ?>
              <span class="badge badge-inactive">Lezárt</span>
            <?php endif; ?>
          </td>
          <td class="td-actions">
            <a href="<?= BASE_URL ?>/user/future-tour-detail.php?id=<?= (int)$t['id'] ?>" class="btn btn-ghost btn-sm">
              <?= $myStatus ? 'Részletek' : 'Megtekintés/Jelentkezés' ?>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($tours)): ?>
        <tr><td colspan="7">
          <div class="empty-state">
            <div class="empty-icon">🗓️</div>
            <p>Jelenleg nincs meghirdetett túra.</p>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/user-footer.php'; ?>
