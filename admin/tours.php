<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tours-schema.php';
requireAdmin();

$pdo = getDb();
ensureToursSchema($pdo);

$tours = $pdo->query("
    SELECT t.*, COUNT(tm.user_id) AS member_count
    FROM tours t
    LEFT JOIN tour_members tm ON tm.tour_id = t.id
    GROUP BY t.id
    ORDER BY t.tour_date DESC, t.created_at DESC
")->fetchAll();

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

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
  <div class="flex items-center gap-2">
    <div class="search-bar">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input type="text" id="tour-search" placeholder="Túrák keresése…">
    </div>
    <a href="<?= BASE_URL ?>/actions/tours-export.php" class="btn btn-ghost btn-sm">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="7 10 12 15 17 10"/>
        <line x1="12" y1="15" x2="12" y2="3"/>
      </svg>
      Exportálás (CSV)
    </a>
    <a href="<?= BASE_URL ?>/actions/tours-template.php" class="btn btn-ghost btn-sm">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/>
        <line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="9" x2="9" y2="21"/>
      </svg>
      Sablon (CSV)
    </a>
    <a href="<?= BASE_URL ?>/admin/tour-import.php" class="btn btn-ghost btn-sm">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="7 10 12 15 17 10"/>
        <line x1="12" y1="3" x2="12" y2="15"/>
        <polyline points="17 8 12 3 7 8"/>
      </svg>
      Importálás (CSV)
    </a>
    <a href="<?= BASE_URL ?>/admin/tour-add.php" class="btn btn-primary btn-sm">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      Túra hozzáadása
    </a>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table id="tour-table">
      <thead>
        <tr>
          <th>Kód</th>
          <th>Elnevezés / Ország</th>
          <th>Túramód</th>
          <th>Dátum</th>
          <th>Napok</th>
          <th>Km</th>
          <th>Szintemelkedés</th>
          <th>Résztvevők</th>
          <th>Lizzardier</th>
          <th>MTSZ pont</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tours as $t): ?>
        <tr>
          <td><code style="font-size:.85em;white-space:nowrap;"><?= e($t['tour_code'] ?? '—') ?></code></td>
          <td>
            <div class="td-name"><?= $t['name'] ? e($t['name']) : e($t['country']) ?></div>
            <div class="td-sub">
              <?= e($t['country']) ?><?= $t['region'] ? ' – ' . e($t['region']) : '' ?>
            </div>
          </td>
          <td><?= e(getTourTypeLabel($t['tour_type'] ?? 'gyalogos')) ?></td>
          <td><?= $t['tour_date'] ? formatDate($t['tour_date']) : '—' ?></td>
          <td><?= (int)$t['days'] ?> nap</td>
          <td>
            <?php
            $totalKmAll = ($t['total_km'] ?? null) !== null ? (float)$t['total_km'] : 0;
            $alpineKmAll = ($t['alpine_km'] ?? null) !== null ? (float)$t['alpine_km'] : 0;
            $fullKm = $totalKmAll + $alpineKmAll;
            if ($fullKm > 0):
                echo number_format($fullKm, 1, ',', ' ') . ' km';
                if ($alpineKmAll > 0): ?><br><small style="color:var(--text-muted,#888);">(<?= number_format($alpineKmAll,1,',','') ?> km magashegyi)</small><?php endif;
            elseif ($t['tour_hours'] !== null):
                echo number_format((float)$t['tour_hours'], 1, ',', ' ') . ' óra';
            else: echo '—'; endif; ?>
          </td>
          <td><?= $t['total_elevation'] !== null ? number_format((int)$t['total_elevation']) . ' m' : '—' ?></td>
          <td><?= (int)$t['member_count'] ?> tag<?= ($t['guest_count'] ?? 0) > 0 ? ', ' . (int)$t['guest_count'] . ' vendég' : '' ?></td>
          <td><strong><?= number_format((int)$t['points']) ?></strong></td>
          <td><?= number_format((int)($t['mtsz_points'] ?? 0)) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/admin/tour-detail.php?id=<?= $t['id'] ?>" class="btn btn-ghost btn-sm">Módosítás</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($tours)): ?>
        <tr><td colspan="11">
          <div class="empty-state">
            <div class="empty-icon">🗺️</div>
            <p>Még nem rögzítettél túrát. Kattints a „Túra hozzáadása" gombra az első hozzáadásához.</p>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
