<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';

$pdo = getDb();
ensureFutureToursSchema($pdo);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/public/turanyptar.php');
    exit;
}

$tourStmt = $pdo->prepare("
    SELECT ft.*, c.name_hu AS country_name, c.flag_filename AS country_flag
    FROM future_tours ft
    LEFT JOIN countries c ON c.code = ft.country
    WHERE ft.id = ? AND ft.status != 'cancelled'
    LIMIT 1
");
$tourStmt->execute([$id]);
$tour = $tourStmt->fetch();
if (!$tour) {
    header('Location: ' . BASE_URL . '/public/turanyptar.php');
    exit;
}

$days = $pdo->prepare("SELECT * FROM future_tour_days WHERE future_tour_id = ? ORDER BY day_number ASC");
$days->execute([$id]);
$days = $days->fetchAll();

$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM future_tour_applications WHERE future_tour_id = ? AND status = 'confirmed'");
$cntStmt->execute([$id]);
$confirmedCount = (int)$cntStmt->fetchColumn();
$spotsLeft = max(0, (int)$tour['max_attendees'] - $confirmedCount);

$gpxFilesStmt = $pdo->prepare("SELECT * FROM future_tour_gpx_files WHERE future_tour_id = ? ORDER BY sort_order ASC");
$gpxFilesStmt->execute([$id]);
$gpxFiles = $gpxFilesStmt->fetchAll();
$hasMap   = !empty($gpxFiles);

$pageTitle     = $tour['name'];
$activePubPage = 'turanyptar';
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap">
  <div style="margin-bottom:16px;">
    <a href="<?= BASE_URL ?>/public/turanyptar.php" class="btn btn-secondary btn-sm">← Vissza a naptárhoz</a>
  </div>

  <div class="tour-detail-grid">

    <!-- Fő adatok (bal oszlop, 1. sor) -->
    <div class="tour-main-info">
      <div class="card">
        <div class="card-body">
          <h1 style="font-size:clamp(20px,3.5vw,28px);font-weight:800;color:var(--sidebar-bg);margin-bottom:16px;"><?= e($tour['name']) ?></h1>

          <!-- Location -->
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
            <?php if (!empty($tour['country_flag'])): ?>
              <img src="<?= e(getFlagUrl($tour['country_flag'])) ?>" class="flag-img-lg" alt="">
            <?php endif; ?>
            <span style="font-size:15px;font-weight:700;"><?= e($tour['country_name'] ?? $tour['country'] ?? '—') ?></span>
            <?php if (!empty($tour['region'])): ?>
              <span style="color:var(--border);">|</span>
              <span style="color:var(--text-muted);font-size:14px;"><?= e($tour['region']) ?></span>
            <?php endif; ?>
          </div>

          <!-- Stats -->
          <div class="tour-stats-grid">
            <div class="tour-stat-cell">
              <div class="tour-stat-label">Kezdés</div>
              <div class="tour-stat-value"><?= $tour['start_date'] ? formatDate($tour['start_date']) : '—' ?></div>
            </div>
            <div class="tour-stat-cell">
              <div class="tour-stat-label">Időtartam</div>
              <div class="tour-stat-value"><?= (int)$tour['num_days'] ?> nap</div>
            </div>
            <div class="tour-stat-cell">
              <div class="tour-stat-label">Helyek</div>
              <div class="tour-stat-value" style="color:<?= $spotsLeft > 0 ? 'var(--primary)' : 'var(--danger)' ?>;">
                <?= $spotsLeft > 0 ? $spotsLeft . ' / ' . (int)$tour['max_attendees'] : 'Betelt' ?>
              </div>
            </div>
            <?php if ($tour['participation_fee'] !== null): ?>
            <div class="tour-stat-cell">
              <div class="tour-stat-label">Részvételi díj</div>
              <div class="tour-stat-value"><?= number_format((float)$tour['participation_fee'], 0, ',', ' ') ?> Ft</div>
            </div>
            <?php endif; ?>
            <?php if ($tour['lizzardier_points'] !== null): ?>
            <div class="tour-stat-cell">
              <div class="tour-stat-label">Lizzardier</div>
              <div class="tour-stat-value" style="color:var(--primary);"><?= (int)$tour['lizzardier_points'] ?></div>
            </div>
            <?php endif; ?>
          </div>

          <?php if (!empty($tour['description'])): ?>
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);color:var(--text);line-height:1.65;white-space:pre-wrap;"><?= e($tour['description']) ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Jobb oldal: CTA + térképek (2. oszlop, mindkét sor) -->
    <div class="tour-sidebar">
      <div class="card">
        <div class="card-body card-body-center" style="text-align:center;padding:28px 20px;">
          <?php if ($tour['status'] !== 'open'): ?>
            <div style="font-size:40px;margin-bottom:12px;">🔒</div>
            <div style="font-weight:700;font-size:17px;margin-bottom:6px;color:var(--text-muted);">A jelentkezés lezárva</div>

          <?php elseif ($spotsLeft > 0): ?>
            <div style="font-size:40px;margin-bottom:12px;">🎒</div>
            <div style="font-weight:700;font-size:17px;margin-bottom:4px;">Még <?= $spotsLeft ?> szabad hely</div>
            <div style="color:var(--text-muted);font-size:13px;margin-bottom:20px;"><?= (int)$tour['max_attendees'] ?> főre tervezett túra</div>

          <?php else: ?>
            <div style="font-size:40px;margin-bottom:12px;">📋</div>
            <div style="font-weight:700;font-size:17px;margin-bottom:4px;">A túra betelt</div>
            <div style="color:var(--text-muted);font-size:13px;margin-bottom:20px;">Várólistás feliratkozás lehetséges</div>
          <?php endif; ?>

          <?php if ($tour['status'] === 'open'): ?>
            <a href="<?= BASE_URL ?>/public/tour-apply.php?id=<?= (int)$id ?>" class="btn btn-primary" style="width:100%;padding:12px;font-size:15px;">
              <?= $spotsLeft > 0 ? 'Jelentkezés' : 'Várólistára feliratkozás' ?>
            </a>
          <?php endif; ?>
        </div>
      </div>

      <?php foreach ($gpxFiles as $gi => $gf): ?>
      <div class="card" style="margin-top:16px;">
        <div class="card-header">
          <h2><?= !empty($gf['label']) ? e($gf['label']) : ('Térkép' . (count($gpxFiles) > 1 ? ' ' . ($gi + 1) . '.' : '')) ?></h2>
        </div>
        <div id="tour-map-<?= $gi ?>" style="height:320px;border-radius:0 0 var(--radius,8px) var(--radius,8px);overflow:hidden;position:relative;z-index:0;isolation:isolate;"></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Másodlagos infók (bal oszlop, 2. sor) -->
    <div class="tour-secondary-info">
      <!-- Days -->
      <?php if (!empty($days)): ?>
      <div class="card" style="margin-bottom:16px;">
        <div class="card-header"><h2>Napok</h2></div>
        <div class="card-body" style="padding:0;overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;font-size:13.5px;">
            <thead>
              <tr style="background:var(--card);border-bottom:1px solid var(--border);">
                <th style="padding:8px 16px;text-align:left;color:var(--text-muted);font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;">Nap</th>
                <th style="padding:8px 16px;text-align:left;color:var(--text-muted);font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;">Típus</th>
                <th style="padding:8px 16px;text-align:right;color:var(--text-muted);font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;">Km</th>
                <th style="padding:8px 16px;text-align:right;color:var(--text-muted);font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;">Szint</th>
                <th style="padding:8px 16px;text-align:left;color:var(--text-muted);font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;">Leírás</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($days as $day): ?>
              <tr style="border-bottom:1px solid var(--border);">
                <td style="padding:10px 16px;font-weight:600;"><?= (int)$day['day_number'] ?>.</td>
                <td style="padding:10px 16px;"><?= e($day['tour_type'] ?? '—') ?></td>
                <td style="padding:10px 16px;text-align:right;"><?= $day['km'] !== null ? number_format((float)$day['km'], 1, ',', ' ') . ' km' : '—' ?></td>
                <td style="padding:10px 16px;text-align:right;"><?= $day['elevation'] !== null ? number_format((int)$day['elevation']) . ' m' : '—' ?></td>
                <td style="padding:10px 16px;color:var(--text-muted);font-size:12px;"><?= e($day['description'] ?? '') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <!-- Info blocks -->
      <?php foreach (['accommodation' => 'Szállás', 'travel' => 'Utazás', 'equipment' => 'Felszerelés', 'experience' => 'Szükséges tapasztalat és erőnlét'] as $col => $label): ?>
        <?php if (!empty($tour[$col])): ?>
        <div class="card" style="margin-bottom:16px;">
          <div class="card-header"><h2><?= $label ?></h2></div>
          <div class="card-body" style="white-space:pre-wrap;line-height:1.65;"><?= e($tour[$col]) ?></div>
        </div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

  </div>
</div>

<?php if ($hasMap): ?>
<?php
$_lazyMaps = [];
foreach ($gpxFiles as $gi => $gf) {
    $_lazyMaps[] = ['id' => 'tour-map-' . $gi, 'gpx' => GPX_URL . $gf['filename']];
}
?>
<script>
(function() {
  var _maps = <?= json_encode($_lazyMaps) ?>;
  var _loaded = false, _pending = [];

  function _initMap(cfg) {
    var map = L.map(cfg.id);
    L.tileLayer('https://api.mapy.cz/v1/maptiles/outdoor/256/{z}/{x}/{y}?apikey=nS0JOXTt2SpKYOi_URYlCjoXhR5tUiXnXIDijo4MY0Q', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a> &copy; <a href="https://mapy.cz/" target="_blank">Mapy.cz</a>'
    }).addTo(map);
    new L.GPX(cfg.gpx, {
      async: true,
      polyline_options: { color: '#ff0000', weight: 5, opacity: 1 },
      marker_options: { startIconUrl: null, endIconUrl: null, shadowUrl: null }
    }).on('loaded', function(e) {
      map.fitBounds(e.target.getBounds(), { padding: [20, 20] });
    }).on('error', function() {
      document.getElementById(cfg.id).innerHTML =
        '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted);">Nem sikerült betölteni a GPX fájlt.</div>';
    }).addTo(map);
  }

  function _load(cfg) {
    if (_loaded) { _initMap(cfg); return; }
    _pending.push(cfg);
    if (_pending.length > 1) return;
    var css = document.createElement('link'); css.rel = 'stylesheet';
    css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
    document.head.appendChild(css);
    var js = document.createElement('script');
    js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    js.onload = function() {
      var gpxJs = document.createElement('script');
      gpxJs.src = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/1.7.0/gpx.min.js';
      gpxJs.onload = function() { _loaded = true; _pending.forEach(_initMap); _pending = []; };
      document.head.appendChild(gpxJs);
    };
    document.head.appendChild(js);
  }

  var obs = new IntersectionObserver(function(entries) {
    entries.forEach(function(e) {
      if (!e.isIntersecting) return;
      obs.unobserve(e.target);
      var cfg = _maps.find(function(m) { return m.id === e.target.id; });
      if (cfg) _load(cfg);
    });
  }, { rootMargin: '200px 0px' });

  _maps.forEach(function(m) { var el = document.getElementById(m.id); if (el) obs.observe(el); });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
