<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tours-schema.php';
requireAdminOrVezeto();

$pdo = getDb();
ensureToursSchema($pdo);

$validSorts = ['date_desc', 'date_asc', 'km_desc', 'km_asc', 'code', 'pts_desc', 'pts_asc', 'members_desc', 'members_asc'];
$sortBy     = in_array($_GET['sort'] ?? '', $validSorts, true) ? $_GET['sort'] : 'date_desc';

$orderBy = match($sortBy) {
    'date_asc'    => 't.tour_date ASC,  t.created_at ASC',
    'km_desc'     => '(COALESCE(t.total_km,0)+COALESCE(t.alpine_km,0)) DESC, t.tour_date DESC',
    'km_asc'      => '(COALESCE(t.total_km,0)+COALESCE(t.alpine_km,0)) ASC,  t.tour_date DESC',
    'code'        => 'CAST(t.tour_code AS UNSIGNED) ASC, t.tour_code ASC',
    'pts_desc'    => 't.points DESC, t.tour_date DESC',
    'pts_asc'     => 't.points ASC,  t.tour_date DESC',
    'members_desc' => 'member_count DESC, t.tour_date DESC',
    'members_asc'  => 'member_count ASC,  t.tour_date DESC',
    default       => 't.tour_date DESC, t.created_at DESC',
};

$tours = $pdo->query("
    SELECT t.*, COUNT(tm.user_id) AS member_count,
           c.name_hu AS country_name, c.flag_filename AS country_flag
    FROM tours t
    LEFT JOIN tour_members tm ON tm.tour_id = t.id
    LEFT JOIN countries c ON c.code = t.country
    GROUP BY t.id
    ORDER BY $orderBy
")->fetchAll();

// Csak a ténylegesen szereplő országok a szűrőmenühöz
$tourCountries = $pdo->query("
    SELECT DISTINCT t.country, c.name_hu
    FROM tours t
    LEFT JOIN countries c ON c.code = t.country
    WHERE t.country IS NOT NULL AND t.country != ''
    ORDER BY COALESCE(c.name_hu, t.country) ASC
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
    <select id="tour-sort-select" onchange="location.href='?sort='+this.value"
            style="height:32px;padding:0 8px;border:1px solid var(--border);border-radius:6px;background:var(--bg);font-size:13px;color:inherit;cursor:pointer;width: 200px;">
      <option value="date_desc" <?= $sortBy === 'date_desc' ? 'selected' : '' ?>>Dátum (újabb elől)</option>
      <option value="date_asc"  <?= $sortBy === 'date_asc'  ? 'selected' : '' ?>>Dátum (régebbi elől)</option>
      <option value="km_desc"   <?= $sortBy === 'km_desc'   ? 'selected' : '' ?>>Km (több elől)</option>
      <option value="km_asc"    <?= $sortBy === 'km_asc'    ? 'selected' : '' ?>>Km (kevesebb elől)</option>
      <option value="code"      <?= $sortBy === 'code'      ? 'selected' : '' ?>>Sorszám szerint</option>
      <option value="pts_desc"     <?= $sortBy === 'pts_desc'     ? 'selected' : '' ?>>Lizzardier pont (több elől)</option>
      <option value="pts_asc"      <?= $sortBy === 'pts_asc'      ? 'selected' : '' ?>>Lizzardier pont (kevesebb elől)</option>
      <option value="members_desc" <?= $sortBy === 'members_desc' ? 'selected' : '' ?>>Résztvevők (több elől)</option>
      <option value="members_asc"  <?= $sortBy === 'members_asc'  ? 'selected' : '' ?>>Résztvevők (kevesebb elől)</option>
    </select>
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
    <?php if (isAdmin()): ?>
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
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table id="tour-table">
      <thead>
        <tr>
          <th>Kód</th>
          <th>Elnevezés / Ország<span class="col-filter-wrap"><button class="col-filter-btn" data-filter="country" title="Szűrés ország szerint">▾</button><ul class="col-filter-menu"><li class="selected"><button data-value="">Mind</button></li><?php foreach ($tourCountries as $tc): ?><li><button data-value="<?= e($tc['country']) ?>"><?= e($tc['country_name'] ?? $tc['country']) ?></button></li><?php endforeach; ?></ul></span></th>
          <th>Túramód<span class="col-filter-wrap"><button class="col-filter-btn" data-filter="type" title="Szűrés túramód szerint">▾</button><ul class="col-filter-menu"><li class="selected"><button data-value="">Mind</button></li><li><button data-value="gyalogos">Gyalogos</button></li><li><button data-value="kerekparos">Kerékpáros</button></li><li><button data-value="vizi">Vízitúra</button></li><li><button data-value="si">Síelés</button></li><li><button data-value="barlangi">Barlangi</button></li><li><button data-value="munka">Munkatúra</button></li></ul></span></th>
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
        <tr data-type="<?= e($t['tour_type'] ?? 'gyalogos') ?>" data-country="<?= e($t['country'] ?? '') ?>">
          <td><code style="font-size:.85em;white-space:nowrap;"><?= e($t['tour_code'] ?? '—') ?></code></td>
          <td>
            <div class="td-name"><?= $t['name'] ? e($t['name']) : e($t['country_name'] ?? $t['country']) ?></div>
            <div class="td-sub">
              <?php if (!empty($t['country_flag'])): ?>
                <img src="<?= e(getFlagUrl($t['country_flag'])) ?>"
                     style="width:18px;height:13px;object-fit:cover;vertical-align:middle;border:1px solid var(--border);border-radius:1px;margin-right:3px;" alt="">
              <?php endif; ?>
              <?= e($t['country_name'] ?? $t['country']) ?><?= $t['region'] ? ' – ' . e($t['region']) : '' ?>
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
                if ($alpineKmAll > 0): ?><br><small style="color:var(--text-muted,#888);">(<?= number_format($alpineKmAll,1,',','') ?> km magashegyi)</small><?php endif;
            elseif ($t['tour_hours'] !== null):
                echo number_format((float)$t['tour_hours'], 1, ',', ' ') . ' óra';
            else: echo '—'; endif; ?>
          </td>
          <td><?= $t['total_elevation'] !== null ? number_format((int)$t['total_elevation']) . ' m' : '—' ?></td>
          <td><?= (int)$t['member_count'] ?> tag<?= ($t['guest_count'] ?? 0) > 0 ? ', ' . (int)$t['guest_count'] . ' vendég' : '' ?></td>
          <td><?= (int)$t['points'] > 0 ? '<strong>' . number_format((int)$t['points']) . '</strong>' : '' ?></td>
          <td><?= number_format((int)($t['mtsz_points'] ?? 0)) ?></td>
          <td>
            <a href="<?= BASE_URL ?>/admin/tour-detail.php?id=<?= $t['id'] ?>" class="btn btn-ghost btn-sm"><?= isAdmin() ? 'Módosítás' : 'Megtekintés' ?></a>
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
