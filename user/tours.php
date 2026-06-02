<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tours-schema.php';
requireUser();

$pdo = getDb();
ensureToursSchema($pdo);

$userId = getCurrentUserId();

$toursStmt = $pdo->prepare("
    SELECT t.*, COUNT(tm.user_id) AS member_count,
           c.name_hu AS country_name, c.flag_filename AS country_flag
    FROM tours t
    LEFT JOIN tour_members tm ON tm.tour_id = t.id
    LEFT JOIN countries c ON c.code = t.country
    WHERE t.status = 'approved' OR (t.status = 'pending' AND t.submitted_by = ?)
    GROUP BY t.id
    ORDER BY t.tour_date DESC, t.created_at DESC
");
$toursStmt->execute([$userId]);
$tours = $toursStmt->fetchAll();

$myTourStmt = $pdo->prepare("SELECT tour_id FROM tour_members WHERE user_id = ?");
$myTourStmt->execute([$userId]);
$myTourIds = array_fill_keys($myTourStmt->fetchAll(PDO::FETCH_COLUMN), true);

$pageTitle  = 'Túranapló';
$activePage = 'tours';
include __DIR__ . '/../includes/user-header.php';
?>

<div class="page-header">
  <h1>Túranapló</h1>
  <div class="flex items-center gap-2">
    <div class="search-bar">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input type="text" id="tour-search" placeholder="Túrák keresése…">
    </div>
    <button id="tour-mine-filter" class="btn btn-ghost btn-sm">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="15" height="15">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
      </svg>
      Csak amin részt vettem
    </button>
    <a href="<?= BASE_URL ?>/user/tour-submit.php" class="btn btn-primary btn-sm">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" width="15" height="15">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      Túra beküldése
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
        <?php foreach ($tours as $t):
            $isMine = isset($myTourIds[$t['id']]);
        ?>
        <tr data-mine="<?= $isMine ? '1' : '0' ?>">
          <td><code style="font-size:.85em;white-space:nowrap;"><?= e($t['tour_code'] ?? '—') ?></code></td>
          <td>
            <div class="td-name"><?= $t['name'] ? e($t['name']) : e($t['country_name'] ?? $t['country']) ?></div>
            <div class="td-sub">
              <?php if (!empty($t['country_flag'])): ?>
                <img src="<?= e(getFlagUrl($t['country_flag'])) ?>"
                     class="flag-img" style="margin-right:3px;" alt="">
              <?php endif; ?>
              <?= e($t['country_name'] ?? $t['country']) ?>
            </div>
          </td>
          <td><?= e(getTourTypeLabel($t['tour_type'] ?? 'gyalogos')) ?></td>
          <td><?= $t['tour_date'] ? formatDate($t['tour_date']) : '—' ?></td>
          <td><?= (int)$t['days'] ?> nap</td>
          <td>
            <?php
            $totalKmAll  = ($t['total_km']  ?? null) !== null ? (float)$t['total_km']  : 0;
            $alpineKmAll = ($t['alpine_km'] ?? null) !== null ? (float)$t['alpine_km'] : 0;
            $fullKm = $totalKmAll + $alpineKmAll;
            if ($fullKm > 0):
                echo number_format($fullKm, 1, ',', ' ') . ' km';
            elseif ($t['tour_hours'] !== null):
                echo number_format((float)$t['tour_hours'], 1, ',', ' ') . ' óra';
            else: echo '—'; endif; ?>
          </td>
          <td><?= $t['total_elevation'] !== null ? number_format((int)$t['total_elevation']) . ' m' : '—' ?></td>
          <td><?= (int)$t['member_count'] ?> tag<?= ($t['guest_count'] ?? 0) > 0 ? ', ' . (int)$t['guest_count'] . ' vendég' : '' ?></td>
          <td><?= (int)$t['points'] > 0 ? '<strong>' . number_format((int)$t['points']) . '</strong>' : '' ?></td>
          <td><?= number_format((int)($t['mtsz_points'] ?? 0)) ?></td>
          <td class="td-actions" style="white-space:nowrap;">
            <div style="display:flex;align-items:center;justify-content:flex-end;gap:6px;">
              <?php if ($isMine): ?>
                <span class="badge badge-active">Részt vettem</span>
              <?php endif; ?>
              <?php if (($t['status'] ?? 'approved') === 'pending'): ?>
                <span style="background:var(--warning,#f59e0b);color:#fff;border-radius:4px;padding:1px 7px;font-size:11px;font-weight:700;">Jóváhagyásra vár</span>
              <?php endif; ?>
              <a href="<?= BASE_URL ?>/user/tour-detail.php?id=<?= $t['id'] ?>" class="btn btn-ghost btn-sm">Megtekintés</a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($tours)): ?>
        <tr><td colspan="11">
          <div class="empty-state">
            <div class="empty-icon">🗺️</div>
            <p>Még nem rögzítettek túrát.</p>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/user-footer.php'; ?>
