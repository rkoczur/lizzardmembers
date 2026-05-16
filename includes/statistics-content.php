<?php
// ── Nézet meghatározása ───────────────────────────────────────────────────
$view          = (isset($_GET['view']) && $_GET['view'] === 'personal') ? 'personal' : 'club';
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

// ── EGYESÜLET adatok ──────────────────────────────────────────────────────
if ($view === 'club') {

    $summary = $pdo->query("
        SELECT
            COUNT(*)                                                                  AS total_tours,
            COALESCE(ROUND(SUM(COALESCE(total_km,0)+COALESCE(alpine_km,0)),1), 0)   AS total_km,
            COALESCE(SUM(COALESCE(total_elevation,0)+COALESCE(alpine_elevation,0)),0) AS total_elev,
            COALESCE(SUM(COALESCE(days,1)),0)                                         AS total_days
        FROM tours
    ")->fetch();

    $activeMembers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE active = 1")->fetchColumn();

    $byYear = $pdo->query("
        SELECT YEAR(tour_date) AS yr,
               COUNT(*) AS cnt,
               ROUND(SUM(COALESCE(total_km,0)+COALESCE(alpine_km,0)), 0) AS km
        FROM tours WHERE tour_date IS NOT NULL
        GROUP BY yr ORDER BY yr
    ")->fetchAll();

    $rawCountriesPerYear = $pdo->query("
        SELECT YEAR(tour_date) AS yr, country
        FROM tours
        WHERE tour_date IS NOT NULL AND country IS NOT NULL AND country != ''
        GROUP BY YEAR(tour_date), country ORDER BY yr
    ")->fetchAll();

    $yearCountriesMap = [];
    foreach ($rawCountriesPerYear as $row) { $yearCountriesMap[$row['yr']][] = $row['country']; }
    $seenCountries = $countriesPerYear = [];
    foreach ($yearCountriesMap as $yr => $list) {
        $newOnes          = array_diff($list, $seenCountries);
        $seenCountries    = array_unique(array_merge($seenCountries, $list));
        $countriesPerYear[] = ['yr' => $yr, 'distinct' => count($list),
                               'new' => count($newOnes), 'cumulative' => count($seenCountries)];
    }

    $byCountry = $pdo->query("
        SELECT t.country, COALESCE(c.name_hu, t.country) AS name, COUNT(*) AS cnt
        FROM tours t LEFT JOIN countries c ON c.code = t.country
        WHERE t.country IS NOT NULL AND t.country != ''
        GROUP BY t.country ORDER BY cnt DESC LIMIT 10
    ")->fetchAll();

    $byType = $pdo->query("
        SELECT tour_type, COUNT(*) AS cnt
        FROM tours GROUP BY tour_type ORDER BY cnt DESC
    ")->fetchAll();

    $byMonth = $pdo->query("
        SELECT MONTH(tour_date) AS mo, COUNT(*) AS cnt
        FROM tours WHERE tour_date IS NOT NULL
        GROUP BY mo ORDER BY mo
    ")->fetchAll();

    $byLevel = $pdo->query("
        SELECT level, COUNT(*) AS cnt
        FROM users WHERE active = 1 AND level > 0
        GROUP BY level ORDER BY level
    ")->fetchAll();

// ── SZEMÉLYES adatok ──────────────────────────────────────────────────────
} else {

    $stmtSum = $pdo->prepare("
        SELECT
            COUNT(DISTINCT tm.tour_id)                                                      AS total_tours,
            COALESCE(ROUND(SUM(COALESCE(t.total_km,0)+COALESCE(t.alpine_km,0)),1), 0)      AS total_km,
            COALESCE(SUM(COALESCE(t.total_elevation,0)+COALESCE(t.alpine_elevation,0)), 0)  AS total_elev,
            COALESCE(SUM(COALESCE(t.days,1)), 0)                                            AS total_days
        FROM tour_members tm
        JOIN tours t ON t.id = tm.tour_id
        WHERE tm.user_id = :uid
    ");
    $stmtSum->execute([':uid' => $currentUserId]);
    $summary = $stmtSum->fetch();

    $stmtCnt = $pdo->prepare("
        SELECT COUNT(DISTINCT t.country) AS cnt
        FROM tour_members tm
        JOIN tours t ON t.id = tm.tour_id
        WHERE tm.user_id = :uid AND t.country IS NOT NULL AND t.country != ''
    ");
    $stmtCnt->execute([':uid' => $currentUserId]);
    $personalCountryCount = (int)$stmtCnt->fetchColumn();

    $stmtYear = $pdo->prepare("
        SELECT YEAR(t.tour_date) AS yr,
               COUNT(*) AS cnt,
               ROUND(SUM(COALESCE(t.total_km,0)+COALESCE(t.alpine_km,0)), 0) AS km
        FROM tour_members tm
        JOIN tours t ON t.id = tm.tour_id
        WHERE tm.user_id = :uid AND t.tour_date IS NOT NULL
        GROUP BY yr ORDER BY yr
    ");
    $stmtYear->execute([':uid' => $currentUserId]);
    $byYear = $stmtYear->fetchAll();

    $stmtCtry = $pdo->prepare("
        SELECT t.country, COALESCE(c.name_hu, t.country) AS name, COUNT(*) AS cnt
        FROM tour_members tm
        JOIN tours t ON t.id = tm.tour_id
        LEFT JOIN countries c ON c.code = t.country
        WHERE tm.user_id = :uid AND t.country IS NOT NULL AND t.country != ''
        GROUP BY t.country ORDER BY cnt DESC LIMIT 10
    ");
    $stmtCtry->execute([':uid' => $currentUserId]);
    $byCountry = $stmtCtry->fetchAll();

    $stmtType = $pdo->prepare("
        SELECT t.tour_type, COUNT(*) AS cnt
        FROM tour_members tm
        JOIN tours t ON t.id = tm.tour_id
        WHERE tm.user_id = :uid
        GROUP BY t.tour_type ORDER BY cnt DESC
    ");
    $stmtType->execute([':uid' => $currentUserId]);
    $byType = $stmtType->fetchAll();

    $stmtMo = $pdo->prepare("
        SELECT MONTH(t.tour_date) AS mo, COUNT(*) AS cnt
        FROM tour_members tm
        JOIN tours t ON t.id = tm.tour_id
        WHERE tm.user_id = :uid AND t.tour_date IS NOT NULL
        GROUP BY mo ORDER BY mo
    ");
    $stmtMo->execute([':uid' => $currentUserId]);
    $byMonth = $stmtMo->fetchAll();
}

// ── JS-adatok előkészítése ────────────────────────────────────────────────
$jsYears = $jsYearlyCnt = $jsYearlyKm = [];
foreach ($byYear as $r) {
    $jsYears[]     = (int)$r['yr'];
    $jsYearlyCnt[] = (int)$r['cnt'];
    $jsYearlyKm[]  = (float)$r['km'];
}

if ($view === 'club') {
    $jsCYears = $jsCDistinct = $jsCNew = $jsCCumul = [];
    foreach ($countriesPerYear as $r) {
        $jsCYears[]    = (int)$r['yr'];
        $jsCDistinct[] = (int)$r['distinct'];
        $jsCNew[]      = (int)$r['new'];
        $jsCCumul[]    = (int)$r['cumulative'];
    }
}

$jsCtryNames = $jsCtryCnt = [];
foreach ($byCountry as $r) {
    $jsCtryNames[] = $r['name'];
    $jsCtryCnt[]   = (int)$r['cnt'];
}

$typeLabel  = ['gyalogos'=>'Gyalogos','kerekparos'=>'Kerékpáros','vizi'=>'Vízitúra',
               'si'=>'Síelés','barlangi'=>'Barlangi','munka'=>'Munkatúra'];
$typeColor  = ['gyalogos'=>'#3b82f6','kerekparos'=>'#10b981','vizi'=>'#06b6d4',
               'si'=>'#8b5cf6','barlangi'=>'#f59e0b','munka'=>'#64748b'];
$jsTypeLabels = $jsTypeCnt = $jsTypeColors = [];
foreach ($byType as $r) {
    $jsTypeLabels[] = $typeLabel[$r['tour_type']] ?? $r['tour_type'];
    $jsTypeCnt[]    = (int)$r['cnt'];
    $jsTypeColors[] = $typeColor[$r['tour_type']] ?? '#94a3b8';
}

$monthNames = ['Jan','Feb','Már','Ápr','Máj','Jún','Júl','Aug','Szep','Okt','Nov','Dec'];
$monthData  = array_fill(0, 12, 0);
foreach ($byMonth as $r) { $monthData[(int)$r['mo'] - 1] = (int)$r['cnt']; }

if ($view === 'club') {
    $levelLabel = [1=>'Újonc',2=>'Közlegény',3=>'Tizedes',4=>'Őrmester',5=>'Hadnagy',
                   6=>'Százados',7=>'Őrnagy',8=>'Alezredes',9=>'Ezredes'];
    $levelColor = ['#94a3b8','#78716c','#84cc16','#22c55e','#3b82f6','#8b5cf6','#f59e0b','#f97316','#ef4444'];
    $jsLvlLabels = $jsLvlCnt = $jsLvlColors = [];
    foreach ($byLevel as $r) {
        $l = (int)$r['level'];
        $jsLvlLabels[] = $levelLabel[$l] ?? "Szint $l";
        $jsLvlCnt[]    = (int)$r['cnt'];
        $jsLvlColors[] = $levelColor[$l - 1] ?? '#94a3b8';
    }
}
?>

<!-- ── Nézet váltó ─────────────────────────────────────────────────────── -->
<div class="stats-view-toggle">
  <a href="?view=club"     class="stats-view-btn <?= $view === 'club'     ? 'active' : '' ?>">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
      <circle cx="9" cy="7" r="4"/>
      <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
    </svg>
    Egyesület statisztikái
  </a>
  <a href="?view=personal" class="stats-view-btn <?= $view === 'personal' ? 'active' : '' ?>">
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
      <circle cx="12" cy="7" r="4"/>
    </svg>
    Saját statisztikáim
  </a>
</div>

<!-- ── Csempék ────────────────────────────────────────────────────────────── -->
<div class="rg-4">

  <div class="card" style="padding:20px 24px;">
    <div style="display:flex;align-items:center;gap:12px;">
      <div style="width:44px;height:44px;border-radius:10px;background:rgba(59,130,246,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#3b82f6" stroke-width="2">
          <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/>
          <line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/>
        </svg>
      </div>
      <div>
        <div style="font-size:1.9rem;font-weight:700;line-height:1;"><?= number_format((int)$summary['total_tours']) ?></div>
        <div style="font-size:.8rem;color:var(--text-muted);margin-top:2px;"><?= $view === 'club' ? 'Összes túra' : 'Saját túráim' ?></div>
      </div>
    </div>
  </div>

  <div class="card" style="padding:20px 24px;">
    <div style="display:flex;align-items:center;gap:12px;">
      <div style="width:44px;height:44px;border-radius:10px;background:rgba(16,185,129,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#10b981" stroke-width="2">
          <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/>
          <polyline points="17 6 23 6 23 12"/>
        </svg>
      </div>
      <div>
        <div style="font-size:1.9rem;font-weight:700;line-height:1;"><?= number_format((float)$summary['total_km'], 0, ',', ' ') ?></div>
        <div style="font-size:.8rem;color:var(--text-muted);margin-top:2px;">Megtett km összesen</div>
      </div>
    </div>
  </div>

  <div class="card" style="padding:20px 24px;">
    <div style="display:flex;align-items:center;gap:12px;">
      <div style="width:44px;height:44px;border-radius:10px;background:rgba(249,115,22,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#f97316" stroke-width="2">
          <path d="M8 3 L12 1 L16 3 L21 12 L12 22 L3 12 Z"/>
        </svg>
      </div>
      <div>
        <div style="font-size:1.9rem;font-weight:700;line-height:1;"><?= number_format((int)$summary['total_elev'], 0, ',', ' ') ?></div>
        <div style="font-size:.8rem;color:var(--text-muted);margin-top:2px;">Szintemelkedés (m)</div>
      </div>
    </div>
  </div>

  <div class="card" style="padding:20px 24px;">
    <div style="display:flex;align-items:center;gap:12px;">
      <div style="width:44px;height:44px;border-radius:10px;background:rgba(139,92,246,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <?php if ($view === 'club'): ?>
          <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#8b5cf6" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
            <circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
        <?php else: ?>
          <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#8b5cf6" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="2" y1="12" x2="22" y2="12"/>
            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
          </svg>
        <?php endif; ?>
      </div>
      <div>
        <div style="font-size:1.9rem;font-weight:700;line-height:1;">
          <?= $view === 'club' ? $activeMembers : $personalCountryCount ?>
        </div>
        <div style="font-size:.8rem;color:var(--text-muted);margin-top:2px;">
          <?= $view === 'club' ? 'Aktív tag' : 'Meglátogatott ország' ?>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- ── Személyes: nincs adat üzenet ──────────────────────────────────────── -->
<?php if ($view === 'personal' && (int)$summary['total_tours'] === 0): ?>
<div class="card card-body" style="text-align:center;padding:60px 20px;color:var(--text-muted);">
  <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 16px;display:block;opacity:.4;">
    <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/>
    <line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/>
  </svg>
  <div style="font-size:15px;font-weight:600;margin-bottom:6px;">Még nem veszel részt egyetlen túrán sem</div>
  <div style="font-size:13px;">Amint hozzárendelnek túrákhoz, itt megjelennek a személyes statisztikáid.</div>
</div>
<?php else: ?>

<!-- ── 1. sor ────────────────────────────────────────────────────────────── -->
<div class="rg-3-2">

  <div class="card">
    <div class="card-header"><h2>Évenkénti aktivitás</h2></div>
    <div class="card-body" style="padding-bottom:16px;">
      <?php if (empty($byYear)): ?>
        <p style="color:var(--text-muted);text-align:center;padding:40px 0;">Nincs adat.</p>
      <?php else: ?>
        <div style="position:relative;height:260px;"><canvas id="chartYearly"></canvas></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2><?= $view === 'club' ? 'Meglátogatott országok' : 'Túramód megoszlás' ?></h2>
    </div>
    <div class="card-body" style="padding-bottom:16px;">
      <?php if ($view === 'club' && empty($countriesPerYear)): ?>
        <p style="color:var(--text-muted);text-align:center;padding:40px 0;">Nincs adat.</p>
      <?php elseif ($view === 'personal' && empty($byType)): ?>
        <p style="color:var(--text-muted);text-align:center;padding:40px 0;">Nincs adat.</p>
      <?php else: ?>
        <div style="position:relative;height:260px;">
          <canvas id="<?= $view === 'club' ? 'chartCountriesYear' : 'chartByType' ?>"></canvas>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- ── 2. sor ────────────────────────────────────────────────────────────── -->
<div class="rg-3-2">

  <div class="card">
    <div class="card-header"><h2>Túrák száma országonként</h2></div>
    <div class="card-body" style="padding-bottom:16px;">
      <?php if (empty($byCountry)): ?>
        <p style="color:var(--text-muted);text-align:center;padding:40px 0;">Nincs adat.</p>
      <?php else: ?>
        <div style="position:relative;height:<?= min(40 + count($byCountry) * 32, 340) ?>px;">
          <canvas id="chartByCountry"></canvas>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h2><?= $view === 'club' ? 'Túramód megoszlás' : 'Havi aktivitás' ?></h2>
    </div>
    <div class="card-body" style="padding-bottom:16px;">
      <?php if ($view === 'club' && empty($byType)): ?>
        <p style="color:var(--text-muted);text-align:center;padding:40px 0;">Nincs adat.</p>
      <?php else: ?>
        <div style="position:relative;height:260px;">
          <canvas id="<?= $view === 'club' ? 'chartByType' : 'chartMonthly' ?>"></canvas>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- ── 3. sor (csak egyesület nézetben) ──────────────────────────────────── -->
<?php if ($view === 'club'): ?>
<div class="rg-2">

  <div class="card">
    <div class="card-header"><h2>Havi aktivitás</h2></div>
    <div class="card-body" style="padding-bottom:16px;">
      <div style="position:relative;height:240px;"><canvas id="chartMonthly"></canvas></div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h2>Tagok szintenkénti megoszlása</h2></div>
    <div class="card-body" style="padding-bottom:16px;">
      <?php if (empty($byLevel)): ?>
        <p style="color:var(--text-muted);text-align:center;padding:40px 0;">Nincs adat.</p>
      <?php else: ?>
        <div style="position:relative;height:240px;"><canvas id="chartLevels"></canvas></div>
      <?php endif; ?>
    </div>
  </div>

</div>
<?php endif; ?>

<?php endif; // end nincs-adat check ?>

<!-- ── Chart.js ──────────────────────────────────────────────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = 'system-ui, -apple-system, "Segoe UI", sans-serif';
Chart.defaults.font.size   = 12;
Chart.defaults.color       = '#6b7280';

const GRID_COLOR  = 'rgba(0,0,0,0.07)';
const baseBarOpts = { responsive: true, maintainAspectRatio: false,
                      plugins: { legend: { display: false } },
                      scales: { x: { grid: { display: false } },
                                y: { grid: { color: GRID_COLOR }, beginAtZero: true, ticks: { precision: 0 } } } };

// ── Évenkénti aktivitás ─────────────────────────────────────────────────
<?php if (!empty($byYear)): ?>
new Chart(document.getElementById('chartYearly'), {
  data: {
    labels: <?= json_encode($jsYears) ?>,
    datasets: [
      { type: 'bar',  label: 'Túrák száma', data: <?= json_encode($jsYearlyCnt) ?>,
        backgroundColor: 'rgba(59,130,246,0.75)', borderRadius: 4, yAxisID: 'y' },
      { type: 'line', label: 'Megtett km',  data: <?= json_encode($jsYearlyKm) ?>,
        borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,0.08)',
        tension: 0.35, fill: true, pointRadius: 4, pointBackgroundColor: '#10b981', yAxisID: 'y1' }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'top', labels: { boxWidth: 12, padding: 16 } } },
    scales: {
      x:  { grid: { display: false } },
      y:  { position: 'left',  beginAtZero: true, ticks: { precision: 0 }, grid: { color: GRID_COLOR },
             title: { display: true, text: 'Túrák', font: { size: 11 } } },
      y1: { position: 'right', beginAtZero: true, ticks: { precision: 0 }, grid: { drawOnChartArea: false },
             title: { display: true, text: 'Km', font: { size: 11 } } }
    }
  }
});
<?php endif; ?>

<?php if ($view === 'club'): ?>

// ── Meglátogatott országok évenként ───────────────────────────────────────
<?php if (!empty($countriesPerYear)): ?>
new Chart(document.getElementById('chartCountriesYear'), {
  data: {
    labels: <?= json_encode($jsCYears) ?>,
    datasets: [
      { type: 'bar',  label: 'Különböző ország', data: <?= json_encode($jsCDistinct) ?>,
        backgroundColor: 'rgba(249,115,22,0.7)', borderRadius: 4, yAxisID: 'y' },
      { type: 'line', label: 'Összesen valaha',  data: <?= json_encode($jsCCumul) ?>,
        borderColor: '#8b5cf6', backgroundColor: 'transparent', tension: 0.3,
        pointRadius: 4, pointBackgroundColor: '#8b5cf6', borderDash: [5,3], yAxisID: 'y' }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'top', labels: { boxWidth: 12, padding: 14 } },
               tooltip: { callbacks: { footer: function(items) {
                 if (items[0].datasetIndex === 0) {
                   const idx = <?= json_encode($jsCYears) ?>.indexOf(parseInt(items[0].label));
                   const n = <?= json_encode($jsCNew) ?>[idx] ?? 0;
                   return n > 0 ? '(' + n + ' új ország)' : '';
                 }
                 return '';
               }}}
    },
    scales: { x: { grid: { display: false } },
              y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: GRID_COLOR } } }
  }
});
<?php endif; ?>

// ── Túramód megoszlás (egyesület) ─────────────────────────────────────────
<?php if (!empty($byType)): ?>
new Chart(document.getElementById('chartByType'), {
  type: 'doughnut',
  data: { labels: <?= json_encode($jsTypeLabels) ?>,
          datasets: [{ data: <?= json_encode($jsTypeCnt) ?>,
                       backgroundColor: <?= json_encode($jsTypeColors) ?>,
                       borderWidth: 2, borderColor: '#fff', hoverOffset: 6 }] },
  options: { responsive: true, maintainAspectRatio: false, cutout: '60%',
             plugins: { legend: { position: 'right', labels: { boxWidth: 13, padding: 14, font: { size: 12 } } } } }
});
<?php endif; ?>

<?php else: // personal view ?>

// ── Túramód megoszlás (személyes) ─────────────────────────────────────────
<?php if (!empty($byType)): ?>
new Chart(document.getElementById('chartByType'), {
  type: 'doughnut',
  data: { labels: <?= json_encode($jsTypeLabels) ?>,
          datasets: [{ data: <?= json_encode($jsTypeCnt) ?>,
                       backgroundColor: <?= json_encode($jsTypeColors) ?>,
                       borderWidth: 2, borderColor: '#fff', hoverOffset: 6 }] },
  options: { responsive: true, maintainAspectRatio: false, cutout: '60%',
             plugins: { legend: { position: 'right', labels: { boxWidth: 13, padding: 14, font: { size: 12 } } } } }
});
<?php endif; ?>

// ── Havi aktivitás (személyes) ────────────────────────────────────────────
new Chart(document.getElementById('chartMonthly'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($monthNames) ?>,
    datasets: [{ label: 'Túrák száma',
                 data: <?= json_encode(array_values($monthData)) ?>,
                 backgroundColor: function(ctx) {
                   const max = Math.max(...ctx.chart.data.datasets[0].data);
                   const v   = ctx.raw / (max || 1);
                   return 'rgba(59,130,246,' + (0.3 + v * 0.65).toFixed(2) + ')';
                 },
                 borderRadius: 4 }]
  },
  options: { ...baseBarOpts,
             plugins: { legend: { display: false },
                        tooltip: { callbacks: { label: c => ' ' + c.raw + ' túra' } } } }
});

<?php endif; ?>

// ── Túrák száma országonként ───────────────────────────────────────────────
<?php if (!empty($byCountry)): ?>
new Chart(document.getElementById('chartByCountry'), {
  type: 'bar',
  data: { labels: <?= json_encode($jsCtryNames) ?>,
          datasets: [{ label: 'Túrák száma', data: <?= json_encode($jsCtryCnt) ?>,
                       backgroundColor: 'rgba(6,182,212,0.75)', borderRadius: 3 }] },
  options: {
    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: GRID_COLOR } },
              y: { grid: { display: false }, ticks: { font: { size: 12 } } } }
  }
});
<?php endif; ?>

<?php if ($view === 'club'): ?>

// ── Havi aktivitás (egyesület) ────────────────────────────────────────────
new Chart(document.getElementById('chartMonthly'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($monthNames) ?>,
    datasets: [{ label: 'Túrák száma',
                 data: <?= json_encode(array_values($monthData)) ?>,
                 backgroundColor: function(ctx) {
                   const max = Math.max(...ctx.chart.data.datasets[0].data);
                   const v   = ctx.raw / (max || 1);
                   return 'rgba(59,130,246,' + (0.3 + v * 0.65).toFixed(2) + ')';
                 },
                 borderRadius: 4 }]
  },
  options: { ...baseBarOpts,
             plugins: { legend: { display: false },
                        tooltip: { callbacks: { label: c => ' ' + c.raw + ' túra' } } } }
});

// ── Szintenkénti tagmegoszlás ─────────────────────────────────────────────
<?php if (!empty($byLevel)): ?>
new Chart(document.getElementById('chartLevels'), {
  type: 'bar',
  data: { labels: <?= json_encode($jsLvlLabels) ?>,
          datasets: [{ label: 'Tagok', data: <?= json_encode($jsLvlCnt) ?>,
                       backgroundColor: <?= json_encode($jsLvlColors) ?>, borderRadius: 4 }] },
  options: {
    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { x: { beginAtZero: true, ticks: { precision: 0, stepSize: 1 }, grid: { color: GRID_COLOR } },
              y: { grid: { display: false } } }
  }
});
<?php endif; ?>

<?php endif; ?>
</script>
