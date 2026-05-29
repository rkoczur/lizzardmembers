<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
requireAdminOrVezeto();

$pdo = getDb();
ensureFutureToursSchema($pdo);

$tours = $pdo->query("
    SELECT ft.*,
           c.name_hu AS country_name, c.flag_filename AS country_flag,
           (SELECT COUNT(*) FROM future_tour_applications fta WHERE fta.future_tour_id = ft.id AND fta.status = 'confirmed') AS confirmed_count,
           (SELECT COUNT(*) FROM future_tour_applications fta WHERE fta.future_tour_id = ft.id AND fta.status = 'waitlist')  AS waitlist_count,
           (SELECT COUNT(*) FROM future_tour_applications fta WHERE fta.future_tour_id = ft.id AND fta.status != 'cancelled' AND fta.paid_at IS NULL) AS unpaid_count
    FROM future_tours ft
    LEFT JOIN countries c ON c.code = ft.country
    ORDER BY ft.start_date ASC, ft.created_at DESC
")->fetchAll();

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$newAppsCount = (int)$pdo->query("SELECT COUNT(*) FROM future_tour_applications WHERE status != 'cancelled' AND paid_at IS NULL")->fetchColumn();

$pageTitle  = 'Túrák';
$activePage = 'tours';
include __DIR__ . '/../includes/admin-header.php';
?>

<?php if ($flash_success): ?>
  <div class="alert alert-success" data-auto-dismiss><?= e($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_error): ?>
  <div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="page-header">
  <h1>Túrák</h1>
  <?php if (isAdmin()): ?>
  <a href="<?= BASE_URL ?>/admin/future-tour-detail.php?new=1" class="btn btn-primary btn-sm">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" width="15" height="15">
      <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
    </svg>
    Új Esemény
  </a>
  <?php endif; ?>
</div>

<!-- Tabs -->
<div style="display:flex;gap:4px;margin-bottom:20px;border-bottom:2px solid var(--border);padding-bottom:0;">
  <a href="<?= BASE_URL ?>/admin/tours.php"
     style="padding:8px 18px;text-decoration:none;font-size:13.5px;font-weight:500;color:var(--text-muted);border-bottom:2px solid transparent;margin-bottom:-2px;transition:color .15s;">
    Túranapló
  </a>
  <a href="<?= BASE_URL ?>/admin/future-tours.php"
     style="padding:8px 18px;text-decoration:none;font-size:13.5px;font-weight:600;color:var(--primary);border-bottom:2px solid var(--primary);margin-bottom:-2px;display:inline-flex;align-items:center;gap:6px;">
    Meghirdetett Túrák
    <?php if ($newAppsCount > 0): ?>
      <span style="background:var(--danger,#dc2626);color:#fff;border-radius:99px;padding:1px 7px;font-size:11px;font-weight:700;line-height:1.6;"><?= $newAppsCount ?></span>
    <?php endif; ?>
  </a>
</div>

<div class="card">
  <div class="table-wrap">
    <table id="future-tour-table">
      <thead>
        <tr>
          <th>Túra neve</th>
          <th>Ország / Tájegység</th>
          <th>Időpont</th>
          <th>Napok</th>
          <th>Részvételi díj</th>
          <th>Résztvevők</th>
          <th>Státusz</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tours as $t): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:7px;">
              <div class="td-name"><?= e($t['name']) ?></div>
              <?php if ((int)$t['unpaid_count'] > 0): ?>
                <span style="background:var(--danger,#dc2626);color:#fff;border-radius:99px;padding:1px 7px;font-size:11px;font-weight:700;line-height:1.6;flex-shrink:0;" title="<?= (int)$t['unpaid_count'] ?> nem fizetett jelentkező"><?= (int)$t['unpaid_count'] ?></span>
              <?php endif; ?>
            </div>
          </td>
          <td>
            <?php if (!empty($t['country_flag'])): ?>
              <img src="<?= e(getFlagUrl($t['country_flag'])) ?>"
                   style="width:18px;height:13px;object-fit:cover;vertical-align:middle;border:1px solid var(--border);border-radius:1px;margin-right:4px;" alt="">
            <?php endif; ?>
            <?= e($t['country_name'] ?? $t['country'] ?? '—') ?>
            <?php if (!empty($t['region'])): ?>
              <div style="font-size:11px;color:var(--text-muted);"><?= e($t['region']) ?></div>
            <?php endif; ?>
          </td>
          <td><?= $t['start_date'] ? formatDate($t['start_date']) : '—' ?></td>
          <td><?= (int)$t['num_days'] ?> nap</td>
          <td>
            <?php if ($t['participation_fee'] !== null): ?>
              <span style="white-space:nowrap;"><?= number_format((float)$t['participation_fee'], 0, ',', '&nbsp;') ?> Ft</span>
            <?php else: ?>
              <span style="color:var(--text-muted);">—</span>
            <?php endif; ?>
          </td>
          <td>
            <span style="font-weight:600;color:var(--primary);"><?= (int)$t['confirmed_count'] ?></span>
            / <?= (int)$t['max_attendees'] ?>
            <?php if ((int)$t['waitlist_count'] > 0): ?>
              <span style="color:var(--warning,#f59e0b);font-size:11px;margin-left:4px;">(+<?= (int)$t['waitlist_count'] ?> várólistán)</span>
            <?php endif; ?>
          </td>
          <td>
            <?php
            $statusLabels = ['open' => 'Nyitott', 'closed' => 'Lezárt', 'cancelled' => 'Törölve'];
            $statusColors = ['open' => 'badge-active', 'closed' => 'badge-inactive', 'cancelled' => 'badge-overdue'];
            $st = $t['status'] ?? 'open';
            ?>
            <span class="badge <?= $statusColors[$st] ?? 'badge-inactive' ?>"><?= $statusLabels[$st] ?? e($st) ?></span>
          </td>
          <td>
            <a href="<?= BASE_URL ?>/admin/future-tour-detail.php?id=<?= (int)$t['id'] ?>" class="btn btn-ghost btn-sm">
              <?= isAdmin() ? 'Szerkesztés' : 'Megtekintés' ?>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($tours)): ?>
        <tr><td colspan="8">
          <div class="empty-state">
            <div class="empty-icon">🗓️</div>
            <p>Még nincs meghirdetett túra. Kattints az „Új Esemény" gombra az első létrehozásához.</p>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
