<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/visit-stats-schema.php';
requireAdminOrVezeto();

$pdo = getDb();
ensureVisitStatsSchema($pdo);

// ── Paraméterek ───────────────────────────────────────────────────────────
$period = in_array($_GET['period'] ?? '', ['daily', 'monthly', 'yearly'], true) ? $_GET['period'] : 'daily';

$years = $pdo->query("SELECT DISTINCT YEAR(view_date) y FROM page_views ORDER BY y DESC")->fetchAll(PDO::FETCH_COLUMN);
if (!$years) { $years = [(int)date('Y')]; }
$year = (int)($_GET['year'] ?? date('Y'));
if (!in_array($year, array_map('intval', $years), true)) { $year = (int)$years[0]; }

// ── Összesítő stat-kártyák ──────────────────────────────────────────────────
function visitStat(PDO $pdo, string $whereSql, array $params = []): array {
    $row = $pdo->prepare("SELECT COUNT(*) v, COUNT(DISTINCT visitor_hash) u FROM page_views" . ($whereSql ? " WHERE $whereSql" : ''));
    $row->execute($params);
    $r = $row->fetch();
    return ['v' => (int)($r['v'] ?? 0), 'u' => (int)($r['u'] ?? 0)];
}
$statToday = visitStat($pdo, "view_date = CURDATE()");
$statMonth = visitStat($pdo, "YEAR(view_date) = YEAR(CURDATE()) AND MONTH(view_date) = MONTH(CURDATE())");
$statYear  = visitStat($pdo, "YEAR(view_date) = YEAR(CURDATE())");
$statAll   = visitStat($pdo, "");

// ── Diagram-adatok a választott bontás szerint + időszak-tartomány ──────────
$labels = []; $vSeries = []; $uSeries = [];
$rangeStart = null; $rangeEnd = null; // tag/vendég + legnézettebb oldalak ehhez

if ($period === 'daily') {
    $rangeStart = date('Y-m-d', strtotime('-29 day'));
    $rangeEnd   = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT view_date, COUNT(*) v, COUNT(DISTINCT visitor_hash) u
                           FROM page_views WHERE view_date BETWEEN ? AND ? GROUP BY view_date");
    $stmt->execute([$rangeStart, $rangeEnd]);
    $map = [];
    foreach ($stmt->fetchAll() as $r) { $map[$r['view_date']] = [(int)$r['v'], (int)$r['u']]; }
    for ($i = 29; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $labels[]  = date('m.d.', strtotime($d));
        $vSeries[] = $map[$d][0] ?? 0;
        $uSeries[] = $map[$d][1] ?? 0;
    }
    $chartTitle = 'Utolsó 30 nap';
} elseif ($period === 'monthly') {
    $rangeStart = sprintf('%04d-01-01', $year);
    $rangeEnd   = sprintf('%04d-12-31', $year);
    $stmt = $pdo->prepare("SELECT MONTH(view_date) m, COUNT(*) v, COUNT(DISTINCT visitor_hash) u
                           FROM page_views WHERE YEAR(view_date) = ? GROUP BY m");
    $stmt->execute([$year]);
    $map = [];
    foreach ($stmt->fetchAll() as $r) { $map[(int)$r['m']] = [(int)$r['v'], (int)$r['u']]; }
    $huMon = ['jan.','feb.','márc.','ápr.','máj.','jún.','júl.','aug.','szept.','okt.','nov.','dec.'];
    for ($m = 1; $m <= 12; $m++) {
        $labels[]  = $huMon[$m - 1];
        $vSeries[] = $map[$m][0] ?? 0;
        $uSeries[] = $map[$m][1] ?? 0;
    }
    $chartTitle = $year . '. évi havi bontás';
} else { // yearly
    $stmt = $pdo->query("SELECT YEAR(view_date) y, COUNT(*) v, COUNT(DISTINCT visitor_hash) u
                         FROM page_views GROUP BY y ORDER BY y");
    foreach ($stmt->fetchAll() as $r) {
        $labels[]  = (int)$r['y'];
        $vSeries[] = (int)$r['v'];
        $uSeries[] = (int)$r['u'];
    }
    $chartTitle = 'Éves bontás';
}

// ── Tag vs. vendég + legnézettebb oldalak az időszakra ──────────────────────
if ($period === 'yearly') {
    $rangeCond = '1=1'; $rangeParams = [];
} else {
    $rangeCond = 'view_date BETWEEN ? AND ?'; $rangeParams = [$rangeStart, $rangeEnd];
}

$mg = ['member' => ['v' => 0, 'u' => 0], 'guest' => ['v' => 0, 'u' => 0]];
$stmt = $pdo->prepare("SELECT is_member, COUNT(*) v, COUNT(DISTINCT visitor_hash) u
                       FROM page_views WHERE $rangeCond GROUP BY is_member");
$stmt->execute($rangeParams);
foreach ($stmt->fetchAll() as $r) {
    $mg[(int)$r['is_member'] === 1 ? 'member' : 'guest'] = ['v' => (int)$r['v'], 'u' => (int)$r['u']];
}

$stmt = $pdo->prepare("SELECT page_path, COUNT(*) v, COUNT(DISTINCT visitor_hash) u
                       FROM page_views WHERE $rangeCond GROUP BY page_path ORDER BY v DESC LIMIT 10");
$stmt->execute($rangeParams);
$topPages = $stmt->fetchAll();

// Útvonal → emberbarát magyar név
$pageNames = [
    '/public/index.php'                 => 'Főoldal',
    '/index.php'                        => 'Főoldal',
    '/'                                 => 'Főoldal',
    '/public/hirek.php'                 => 'Hírek',
    '/public/beszmolok.php'             => 'Élménybeszámolók',
    '/public/turanyptar.php'            => 'Túranaptár',
    '/public/tour-detail.php'           => 'Túra részletek',
    '/public/tour-apply.php'            => 'Túrajelentkezés',
    '/public/egyesuleti-turanaplo.php'  => 'Egyesületi túranapló',
    '/public/mtsz-turanaplo.php'        => 'MTSZ túranapló',
    '/public/rolunk.php'                => 'Rólunk',
    '/public/kapcsolat.php'             => 'Kapcsolat',
    '/public/gyik.php'                  => 'GYIK',
    '/public/tagsag.php'                => 'Tagság',
    '/public/klubelet.php'              => 'Klubélet',
    '/public/lizzardier.php'            => 'Lizzardier',
    '/public/toplista.php'              => 'Toplista',
    '/public/ev-turatarsa.php'          => 'Év túratársa',
    '/public/irattar.php'               => 'Irattár',
    '/public/penzugyek.php'             => 'Pénzügyek',
    '/public/reszveteli-feltetelek.php' => 'Részvételi feltételek',
    '/public/ado1.php'                  => '1% felajánlás',
    '/public/post.php'                  => 'Bejegyzés',
];
function pageLabel(string $path, array $map): string {
    return $map[$path] ?? $path;
}

$pageTitle  = 'Látogatottság';
$activePage = 'visitors';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="page-header">
  <h1>Látogatottság</h1>
</div>

<!-- Összesítő kártyák -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">📅</div>
    <div class="stat-label">Ma</div>
    <div class="stat-value"><?= number_format($statToday['v'], 0, ',', ' ') ?></div>
    <div style="font-size:12px;color:var(--text-muted);margin-top:2px;"><?= number_format($statToday['u'], 0, ',', ' ') ?> egyedi látogató</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🗓️</div>
    <div class="stat-label">Aktuális hónap</div>
    <div class="stat-value"><?= number_format($statMonth['v'], 0, ',', ' ') ?></div>
    <div style="font-size:12px;color:var(--text-muted);margin-top:2px;"><?= number_format($statMonth['u'], 0, ',', ' ') ?> egyedi látogató</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">📈</div>
    <div class="stat-label">Aktuális év</div>
    <div class="stat-value"><?= number_format($statYear['v'], 0, ',', ' ') ?></div>
    <div style="font-size:12px;color:var(--text-muted);margin-top:2px;"><?= number_format($statYear['u'], 0, ',', ' ') ?> egyedi látogató</div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🌐</div>
    <div class="stat-label">Összes idő</div>
    <div class="stat-value"><?= number_format($statAll['v'], 0, ',', ' ') ?></div>
    <div style="font-size:12px;color:var(--text-muted);margin-top:2px;"><?= number_format($statAll['u'], 0, ',', ' ') ?> egyedi látogató</div>
  </div>
</div>

<!-- Bontás-választó -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-body" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;justify-content:space-between;">
    <div class="tab-nav" style="margin:0;border:none;">
      <a href="?period=daily"   class="tab-link<?= $period === 'daily'   ? ' active' : '' ?>">Napi</a>
      <a href="?period=monthly&year=<?= $year ?>" class="tab-link<?= $period === 'monthly' ? ' active' : '' ?>">Havi</a>
      <a href="?period=yearly"  class="tab-link<?= $period === 'yearly'  ? ' active' : '' ?>">Éves</a>
    </div>
    <?php if ($period === 'monthly'): ?>
      <form method="get" style="margin:0;">
        <input type="hidden" name="period" value="monthly">
        <select name="year" onchange="this.form.submit()"
                style="height:34px;padding:0 10px;border:1px solid var(--border);border-radius:6px;background:var(--bg);color:inherit;cursor:pointer;">
          <?php foreach ($years as $y): ?>
            <option value="<?= (int)$y ?>" <?= (int)$y === $year ? 'selected' : '' ?>><?= (int)$y ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    <?php endif; ?>
  </div>
</div>

<!-- Diagram -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-header"><h2 style="font-size:16px;margin:0;"><?= e($chartTitle) ?> — megtekintések és egyedi látogatók</h2></div>
  <div class="card-body">
    <?php if (array_sum($vSeries) === 0): ?>
      <div class="empty-state"><div class="empty-icon">📊</div><p>Ehhez az időszakhoz még nincs adat.</p></div>
    <?php else: ?>
      <div style="position:relative;height:320px;"><canvas id="chartVisits"></canvas></div>
    <?php endif; ?>
  </div>
</div>

<div class="rg-2" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
  <!-- Tag vs. vendég -->
  <div class="card">
    <div class="card-header"><h2 style="font-size:16px;margin:0;">Tag vs. vendég<span style="font-weight:400;color:var(--text-muted);font-size:13px;"> — <?= e($chartTitle) ?></span></h2></div>
    <div class="card-body">
      <?php $mgTotal = $mg['member']['v'] + $mg['guest']['v']; ?>
      <?php if ($mgTotal === 0): ?>
        <div class="empty-state"><p>Nincs adat ehhez az időszakhoz.</p></div>
      <?php else: ?>
        <table style="width:100%;border-collapse:collapse;">
          <thead><tr>
            <th style="text-align:left;padding:8px;border-bottom:1px solid var(--border);"></th>
            <th style="text-align:right;padding:8px;border-bottom:1px solid var(--border);">Megtekintés</th>
            <th style="text-align:right;padding:8px;border-bottom:1px solid var(--border);">Egyedi látogató</th>
          </tr></thead>
          <tbody>
            <tr>
              <td style="padding:8px;"><span class="badge badge-active">Tagok</span></td>
              <td style="text-align:right;padding:8px;font-weight:700;"><?= number_format($mg['member']['v'], 0, ',', ' ') ?></td>
              <td style="text-align:right;padding:8px;"><?= number_format($mg['member']['u'], 0, ',', ' ') ?></td>
            </tr>
            <tr>
              <td style="padding:8px;"><span class="badge badge-inactive">Vendégek</span></td>
              <td style="text-align:right;padding:8px;font-weight:700;"><?= number_format($mg['guest']['v'], 0, ',', ' ') ?></td>
              <td style="text-align:right;padding:8px;"><?= number_format($mg['guest']['u'], 0, ',', ' ') ?></td>
            </tr>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Legnézettebb oldalak -->
  <div class="card">
    <div class="card-header"><h2 style="font-size:16px;margin:0;">Legnézettebb oldalak<span style="font-weight:400;color:var(--text-muted);font-size:13px;"> — <?= e($chartTitle) ?></span></h2></div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($topPages)): ?>
        <div class="empty-state" style="padding:24px;"><p>Nincs adat ehhez az időszakhoz.</p></div>
      <?php else: ?>
        <table style="width:100%;border-collapse:collapse;">
          <thead><tr>
            <th style="text-align:left;padding:10px 14px;border-bottom:1px solid var(--border);">Oldal</th>
            <th style="text-align:right;padding:10px 14px;border-bottom:1px solid var(--border);">Megtekintés</th>
          </tr></thead>
          <tbody>
            <?php foreach ($topPages as $tp): ?>
            <tr>
              <td style="padding:10px 14px;border-bottom:1px solid var(--border);">
                <div style="font-weight:600;"><?= e(pageLabel($tp['page_path'], $pageNames)) ?></div>
                <div style="font-size:11px;color:var(--text-muted);"><?= e($tp['page_path']) ?></div>
              </td>
              <td style="text-align:right;padding:10px 14px;border-bottom:1px solid var(--border);font-weight:700;"><?= number_format((int)$tp['v'], 0, ',', ' ') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (array_sum($vSeries) > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = 'system-ui, -apple-system, "Segoe UI", sans-serif';
Chart.defaults.font.size   = 12;
Chart.defaults.color       = '#6b7280';
const GRID_COLOR = 'rgba(0,0,0,0.07)';

new Chart(document.getElementById('chartVisits'), {
  data: {
    labels: <?= json_encode($labels) ?>,
    datasets: [
      { type: 'bar',  label: 'Megtekintések', data: <?= json_encode($vSeries) ?>,
        backgroundColor: 'rgba(41,119,111,0.75)', borderRadius: 4 },
      { type: 'line', label: 'Egyedi látogatók', data: <?= json_encode($uSeries) ?>,
        borderColor: '#DD9933', backgroundColor: 'rgba(221,153,51,0.10)',
        tension: 0.3, fill: true, pointRadius: 3, pointBackgroundColor: '#DD9933' }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'top', labels: { boxWidth: 12, padding: 16 } } },
    scales: {
      x: { grid: { display: false } },
      y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: GRID_COLOR } }
    }
  }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
