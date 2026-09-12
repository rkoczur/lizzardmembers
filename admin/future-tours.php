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

// Nézet: aktív (nem lezárt) túrák, vagy az archívum (lezárt túrák)
$view = ($_GET['view'] ?? 'active') === 'archive' ? 'archive' : 'active';
$statusFilter = $view === 'archive' ? "ft.status = 'closed'" : "ft.status != 'closed'";

$tours = $pdo->query("
    SELECT ft.*,
           c.name_hu AS country_name, c.flag_filename AS country_flag,
           (SELECT COUNT(*) FROM future_tour_applications fta WHERE fta.future_tour_id = ft.id AND fta.status = 'confirmed') AS confirmed_count,
           (SELECT COUNT(*) FROM future_tour_applications fta WHERE fta.future_tour_id = ft.id AND fta.status = 'waitlist')  AS waitlist_count,
           (SELECT COUNT(*) FROM future_tour_applications fta WHERE fta.future_tour_id = ft.id AND fta.status != 'cancelled' AND fta.paid_at IS NULL AND ft.participation_fee > 0) AS unpaid_count,
           (SELECT COUNT(*) FROM future_tour_applications fta WHERE fta.future_tour_id = ft.id AND fta.status = 'pending') AS pending_count
    FROM future_tours ft
    LEFT JOIN countries c ON c.code = ft.country
    WHERE $statusFilter
    ORDER BY ft.start_date ASC, ft.created_at DESC
")->fetchAll();

$activeCount  = (int)$pdo->query("SELECT COUNT(*) FROM future_tours WHERE status != 'closed'")->fetchColumn();
$archiveCount = (int)$pdo->query("SELECT COUNT(*) FROM future_tours WHERE status = 'closed'")->fetchColumn();

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$newAppsCount = (int)$pdo->query("SELECT COUNT(*) FROM future_tour_applications fta JOIN future_tours ft ON ft.id = fta.future_tour_id WHERE fta.status != 'cancelled' AND fta.paid_at IS NULL AND ft.participation_fee > 0")->fetchColumn();

// Kintlévőségek: megerősített, még ki nem fizetett részvételi díjak összege (tagi kedvezménnyel)
$outstandingTotal = 0.0;
$outstandingCount = 0;
$outRows = $pdo->query("
    SELECT fta.user_id, ft.participation_fee, u.level AS user_level, u.role AS user_role
    FROM future_tour_applications fta
    JOIN future_tours ft ON ft.id = fta.future_tour_id
    LEFT JOIN users u ON u.id = fta.user_id
    WHERE fta.status = 'confirmed'
      AND fta.paid_at IS NULL
      AND ft.status != 'cancelled'
      AND ft.participation_fee > 0
")->fetchAll();
foreach ($outRows as $r) {
    $discount = $r['user_id'] ? getTourFeeDiscount((int)$r['user_level'], (string)($r['user_role'] ?? 'user')) : 0;
    $fee = (float)$r['participation_fee'] * (1 - $discount / 100);
    if ($fee > 0) { $outstandingTotal += $fee; $outstandingCount++; }
}

// Elfogadásra váró jelentkezések: helyet kapott tagok és vendégek, akiknek a jelentkezését még nem fogadták el
$pendingAccept = $pdo->query("
    SELECT fta.id, fta.future_tour_id, fta.applied_at, fta.paid_at, fta.user_id,
           COALESCE(fta.guest_name, CONCAT(u.lastname, ' ', u.firstname)) AS applicant_name,
           COALESCE(fta.guest_email, u.email) AS applicant_email,
           ft.name AS tour_name, ft.start_date, ft.participation_fee
    FROM future_tour_applications fta
    JOIN future_tours ft ON ft.id = fta.future_tour_id
    LEFT JOIN users u ON u.id = fta.user_id
    WHERE fta.status = 'confirmed'
      AND fta.accepted_at IS NULL
      AND ft.status NOT IN ('closed','cancelled')
    ORDER BY ft.start_date ASC, fta.applied_at ASC
")->fetchAll();

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
  <?php if (canCreateFutureTours()): ?>
  <a href="<?= BASE_URL ?>/admin/future-tour-detail.php?new=1" class="btn btn-primary btn-sm">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" width="15" height="15">
      <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
    </svg>
    Új Esemény
  </a>
  <?php endif; ?>
</div>

<!-- Tabs -->
<div class="tab-nav">
  <a href="<?= BASE_URL ?>/admin/tours.php" class="tab-link">Túranapló</a>
  <a href="<?= BASE_URL ?>/admin/future-tours.php" class="tab-link active">
    Meghirdetett Túrák
    <?php if ($newAppsCount > 0): ?>
      <span class="badge-counter badge-counter-danger"><?= $newAppsCount ?></span>
    <?php endif; ?>
  </a>
</div>

<!-- Aktív / Archív váltó -->
<div class="tab-nav tab-nav-sub" style="margin-bottom:16px;">
  <a href="<?= BASE_URL ?>/admin/future-tours.php?view=active" class="tab-link<?= $view === 'active' ? ' active' : '' ?>">
    Aktív túrák
    <?php if ($activeCount > 0): ?><span class="badge-counter badge-counter-primary"><?= $activeCount ?></span><?php endif; ?>
  </a>
  <a href="<?= BASE_URL ?>/admin/future-tours.php?view=archive" class="tab-link<?= $view === 'archive' ? ' active' : '' ?>">
    Archívum (lezárt)
    <?php if ($archiveCount > 0): ?><span class="badge-counter"><?= $archiveCount ?></span><?php endif; ?>
  </a>
</div>

<!-- Kintlévőségek összesítő -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div style="display:flex;align-items:center;gap:14px;">
      <div style="font-size:1.9rem;line-height:1;">💸</div>
      <div>
        <div class="stat-label">Kintlévőségek (be nem fizetett részvételi díjak)</div>
        <div style="font-size:24px;font-weight:800;color:<?= $outstandingTotal > 0 ? 'var(--danger)' : 'var(--success)' ?>;margin-top:4px;">
          <?= number_format($outstandingTotal, 0, ',', ' ') ?> Ft
        </div>
      </div>
    </div>
    <div style="font-size:13px;color:var(--text-muted);text-align:right;line-height:1.5;">
      <?= (int)$outstandingCount ?> megerősített, rendezetlen jelentkezés<br>
      <span style="font-size:12px;">(a tagi szint/szerep szerinti kedvezménnyel számolva)</span>
    </div>
  </div>
</div>

<?php if (!empty($pendingAccept)): ?>
<!-- Elfogadásra váró jelentkezések -->
<div class="card pending-accept-card">
  <div class="card-header flex-between">
    <h2>Elfogadásra váró jelentkezések <span class="badge-counter badge-counter-warning"><?= count($pendingAccept) ?></span></h2>
    <span class="pending-accept-note">A jelentkező az elfogadáskor kapja meg a visszaigazoló e-mailt.</span>
  </div>
  <div class="card-body pending-accept-body">
    <?php foreach ($pendingAccept as $pa): ?>
    <div class="pending-accept-row">
      <div class="pending-accept-who">
        <div class="pending-accept-name">
          <?= e($pa['applicant_name']) ?>
          <?php if (!$pa['user_id']): ?><span class="badge-guest">Vendég</span><?php endif; ?>
        </div>
        <div class="pending-accept-meta"><?= e($pa['applicant_email']) ?></div>
      </div>
      <div class="pending-accept-tour">
        <a href="<?= BASE_URL ?>/admin/future-tour-applicants.php?id=<?= (int)$pa['future_tour_id'] ?>"><?= e($pa['tour_name']) ?></a>
        <div class="pending-accept-meta"><?= $pa['start_date'] ? formatDate($pa['start_date']) : '—' ?></div>
      </div>
      <div class="pending-accept-meta pending-accept-when">
        Jelentkezett: <?= date('Y.m.d', strtotime($pa['applied_at'])) ?>
      </div>
      <div class="pending-accept-pay">
        <?php if ($pa['participation_fee'] !== null && (float)$pa['participation_fee'] > 0): ?>
          <?php if ($pa['paid_at']): ?>
            <span class="badge badge-active">Fizetve</span>
          <?php else: ?>
            <span class="badge badge-overdue">Nem fizetett</span>
          <?php endif; ?>
        <?php endif; ?>
      </div>
      <form method="post" action="<?= BASE_URL ?>/actions/future-tour-accept.php"
            onsubmit="return confirm('Elfogadja <?= e($pa['applicant_name']) ?> jelentkezését? A jelentkező visszaigazoló e-mailt kap.')">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="application_id" value="<?= (int)$pa['id'] ?>">
        <input type="hidden" name="tour_id" value="<?= (int)$pa['future_tour_id'] ?>">
        <input type="hidden" name="back" value="list">
        <button type="submit" class="btn btn-primary btn-sm">Elfogadás</button>
      </form>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

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
                <span class="badge-counter badge-counter-danger" title="<?= (int)$t['unpaid_count'] ?> nem fizetett jelentkező"><?= (int)$t['unpaid_count'] ?></span>
              <?php endif; ?>
            </div>
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
            <?php $appTotal = (int)$t['confirmed_count'] + (int)$t['waitlist_count']; ?>
            <div style="display:flex;gap:4px;align-items:stretch;white-space:nowrap;">
              <a href="<?= BASE_URL ?>/admin/future-tour-applicants.php?id=<?= (int)$t['id'] ?>" class="btn btn-ghost btn-sm">
                Jelentkezők
                <?php if ((int)$t['pending_count'] > 0): ?>
                  <span class="badge-counter badge-counter-warning" title="Jóváhagyásra vár"><?= (int)$t['pending_count'] ?></span>
                <?php elseif ($appTotal > 0): ?>
                  <span class="badge-counter badge-counter-primary"><?= $appTotal ?></span>
                <?php endif; ?>
              </a>
              <a href="<?= BASE_URL ?>/admin/future-tour-detail.php?id=<?= (int)$t['id'] ?>" class="btn btn-ghost btn-sm">
                <?= canCreateFutureTours() ? 'Szerkesztés' : 'Megtekintés' ?>
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($tours)): ?>
        <tr><td colspan="8">
          <div class="empty-state">
            <div class="empty-icon">🗓️</div>
            <?php if ($view === 'archive'): ?>
              <p>Nincs lezárt túra az archívumban.</p>
            <?php else: ?>
              <p>Még nincs meghirdetett túra. Kattints az „Új Esemény" gombra az első létrehozásához.</p>
            <?php endif; ?>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
