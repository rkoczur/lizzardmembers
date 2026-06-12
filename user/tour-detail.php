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

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/user/tours.php');
    exit;
}

$tourStmt = $pdo->prepare("SELECT * FROM tours WHERE id = ? LIMIT 1");
$tourStmt->execute([$id]);
$tour = $tourStmt->fetch();
if (!$tour) {
    header('Location: ' . BASE_URL . '/user/tours.php');
    exit;
}

$countryStmt = $pdo->prepare("SELECT name_hu, flag_filename FROM countries WHERE code = ? LIMIT 1");
$countryStmt->execute([$tour['country']]);
$countryRow = $countryStmt->fetch();

$membersStmt = $pdo->prepare("
    SELECT u.lastname, u.firstname, u.level, u.profile_picture
    FROM tour_members tm
    JOIN users u ON u.id = tm.user_id
    WHERE tm.tour_id = ?
    ORDER BY u.lastname, u.firstname
");
$membersStmt->execute([$id]);
$members = $membersStmt->fetchAll();

$userId = getCurrentUserId();
$isMine = $pdo->prepare("SELECT 1 FROM tour_members WHERE tour_id = ? AND user_id = ? LIMIT 1");
$isMine->execute([$id, $userId]);
$isMine = (bool)$isMine->fetchColumn();

$gpxFilesStmt = $pdo->prepare("SELECT * FROM tour_gpx_files WHERE tour_id = ? ORDER BY sort_order ASC, uploaded_at ASC");
$gpxFilesStmt->execute([$id]);
$gpxFiles = $gpxFilesStmt->fetchAll();
$hasMap = !empty($gpxFiles);

$title = $tour['name'] ?: ($tour['country'] . ($tour['region'] ? ' – ' . $tour['region'] : ''));
$pageTitle  = $title;
$activePage = 'tours';
include __DIR__ . '/../includes/user-header.php';

function mtszBreakdownLines(array $t): array {
    $type       = $t['tour_type'] ?? 'gyalogos';
    $sub        = $t['sub_type'] ?? 'normal';
    $days       = max(1, (int)($t['days'] ?? 1));
    $date       = $t['tour_date'] ?? null;
    $multiDay   = $t['multi_day_type'] ?? null;
    $portages   = (int)($t['boat_portages'] ?? 0);
    $normalKm   = ($t['total_km'] !== null && $t['total_km'] !== '')               ? (float)$t['total_km']        : null;
    $normalElev = ($t['total_elevation'] !== null && $t['total_elevation'] !== '')  ? (int)$t['total_elevation']   : null;
    $alpineKm   = ($t['alpine_km'] !== null && $t['alpine_km'] !== '')             ? (float)$t['alpine_km']        : null;
    $alpineElev = ($t['alpine_elevation'] !== null && $t['alpine_elevation'] !== '') ? (int)$t['alpine_elevation']  : null;
    $hours      = ($t['tour_hours'] !== null && $t['tour_hours'] !== '')            ? (float)$t['tour_hours']       : null;
    $campNights = (int)($t['camping_nights_fixed'] ?? 0);
    $accom      = $t['accommodation'] ?? '';
    $hasAlpine  = ($alpineKm !== null && $alpineKm > 0) || ($alpineElev !== null && $alpineElev > 0);

    $fn = function(float $n): string {
        if ($n == (int)$n) return (string)(int)$n;
        return number_format($n, 1, ',', '');
    };

    $lines = [];
    $normalBase = 0.0;
    $alpineBase = 0.0;

    if ($type === 'gyalogos' || $type === 'kerekparos') {
        if ($type === 'gyalogos') { $kr = ($sub === 'tajekozodasi') ? 3.0 : 1.5; $er = 2.0; }
        else { $kr = ($sub === 'terep') ? 1.0 : 0.5; $er = ($sub === 'terep') ? 2.0 : 1.0; }

        if ($normalKm !== null && $normalKm > 0) {
            $v = $normalKm * $kr; $normalBase += $v;
            $lines[] = "Nem magashegyi km: {$fn($normalKm)} × {$fn($kr)} = " . (int)round($v) . ' pont';
        }
        if ($normalElev !== null && $normalElev > 0) {
            $v = ($normalElev / 100) * $er; $normalBase += $v;
            $lines[] = "Nem magashegyi szint: {$normalElev} / 100 × {$fn($er)} = " . (int)round($v) . ' pont';
        }
        if ($alpineKm !== null && $alpineKm > 0) {
            $v = $alpineKm * $kr; $alpineBase += $v;
            $lines[] = "Magashegyi km: {$fn($alpineKm)} × {$fn($kr * 2)} = " . (int)round($v * 2) . ' pont';
        }
        if ($alpineElev !== null && $alpineElev > 0) {
            $v = ($alpineElev / 100) * $er; $alpineBase += $v;
            $lines[] = "Magashegyi szint: {$alpineElev} / 100 × {$fn($er * 2)} = " . (int)round($v * 2) . ' pont';
        }
    } elseif ($type === 'vizi') {
        $rate = match($sub) { 'szemben' => 2.0, 'allovi' => 1.5, default => 1.0 };
        if ($normalKm !== null && $normalKm > 0) {
            $v = $normalKm * $rate; $normalBase += $v;
            $lines[] = "Megtett km: {$fn($normalKm)} × {$fn($rate)} = " . (int)round($v) . ' pont';
        }
    } elseif ($type === 'si') {
        if ($hours !== null && $hours > 0) {
            $v = $hours * 6; $normalBase += $v;
            $lines[] = "Síelési idő: {$fn($hours)} ó × 6 = " . (int)round($v) . ' pont';
        }
    } elseif ($type === 'barlangi') {
        if ($hours !== null && $hours > 0) {
            $rate = ($sub === 'kiepitetlen') ? 10 : 4;
            $v = $hours * $rate; $normalBase += $v;
            $lines[] = "Bejárási idő: {$fn($hours)} ó × {$rate} = " . (int)round($v) . ' pont';
        }
    } elseif ($type === 'munka') {
        if ($hours !== null && $hours > 0) {
            $v = $hours * 7; $normalBase += $v;
            $lines[] = "Munka ideje: {$fn($hours)} ó × 7 = " . (int)round($v) . ' pont';
        }
    }

    $bonusMult = ($hasAlpine && in_array($type, ['gyalogos', 'kerekparos'], true) && $alpineBase >= $normalBase) ? 2 : 1;

    if ($type === 'gyalogos' && !$hasAlpine && $normalElev !== null && $normalElev <= 100) {
        $eff = 3 * $bonusMult; $b = $days * $eff;
        $lines[] = $days > 1 ? "Síkvidéki pluszpont: {$days} nap × {$eff} = {$b} pont" : "Síkvidéki pluszpont: {$b} pont";
    }
    if (in_array($type, ['gyalogos', 'kerekparos'], true) && $date) {
        $month  = (int)date('n', strtotime($date));
        $winter = $hasAlpine ? [11, 12, 1, 2, 3] : [12, 1, 2];
        if (in_array($month, $winter, true)) {
            $eff = 3 * $bonusMult; $b = $days * $eff;
            $lines[] = $days > 1 ? "Téli pluszpont: {$days} nap × {$eff} = {$b} pont" : "Téli pluszpont: {$b} pont";
        }
    }
    $campMult = ($accom === 'sator') ? 2 : 1; // sátras szállásnál a tábor pontjai duplázódnak
    if ($campNights > 0 && $multiDay === 'csillag') {
        $eff = 1 * $bonusMult * $campMult; $b = $campNights * $eff;
        $lines[] = "Állótábor: {$campNights} éj × {$eff} = {$b} pont" . ($campMult > 1 ? ' (sátor ×2)' : '');
    } elseif ($campNights > 0 && $multiDay === 'vandor') {
        $eff = 3 * $bonusMult * $campMult; $b = $campNights * $eff;
        $lines[] = "Mozgótábor: {$campNights} éj × {$eff} = {$b} pont" . ($campMult > 1 ? ' (sátor ×2)' : '');
    }
    if ($type === 'vizi' && $portages > 0) {
        $eff = 3 * $bonusMult; $b = $portages * $eff;
        $lines[] = "Hajóátemelés: {$portages} alkalom × {$eff} = {$b} pont";
    }

    return $lines;
}

function detailRow(string $label, $value): void {
    if ($value === null || $value === '' || $value === '—') return;
    echo '<tr><td style="width:40%;padding:8px 12px;color:var(--text-muted);font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">' . htmlspecialchars($label) . '</td>';
    echo '<td style="padding:8px 12px;">' . $value . '</td></tr>';
}
?>

<div class="page-header">
  <div class="flex items-center gap-2">
    <a href="<?= BASE_URL ?>/user/tours.php" class="btn btn-secondary btn-sm">← Vissza</a>
    <h1><?= e($title) ?></h1>
    <?php if ($isMine): ?>
      <span class="badge badge-active">Részt vettem</span>
    <?php endif; ?>
  </div>
</div>


<div id="tour-layout" style="display:grid;grid-template-columns:<?= $hasMap ? '1fr 1.6fr' : '1fr' ?>;gap:20px;align-items:start;">

  <!-- Bal oszlop: Adatok -->
  <div style="display:flex;flex-direction:column;gap:20px;">

    <!-- 1. Általános adatok -->
    <div class="card">
      <div class="card-header"><h2>Általános adatok</h2></div>
      <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
          <tbody>
            <?php
            detailRow('Túra kód',   $tour['tour_code'] ? '<code>' . e($tour['tour_code']) . '</code>' : null);
            $countryDisplay = '';
            if ($countryRow) {
                $flagUrl = getFlagUrl($countryRow['flag_filename']);
                if ($flagUrl) {
                    $countryDisplay .= '<img src="' . e($flagUrl) . '" alt="" style="width:22px;height:15px;object-fit:cover;border:1px solid var(--border);border-radius:2px;vertical-align:middle;margin-right:6px;">';
                }
                $countryDisplay .= e($countryRow['name_hu']);
            } else {
                $countryDisplay = e($tour['country']);
            }
            detailRow('Ország', $countryDisplay);
            detailRow('Tájegység',   $tour['region'] ? e($tour['region']) : null);
            detailRow('Dátum',       $tour['tour_date'] ? formatDate($tour['tour_date']) : null);
            detailRow('Napok száma', (int)$tour['days'] . ' nap');
            detailRow('Szállás',     $tour['accommodation'] ? e(match($tour['accommodation']) {
                'sator'      => 'Sátor',
                'turistahaz' => 'Túristaház',
                'apartman'   => 'Apartman',
                'hotel'      => 'Hotel',
                default      => $tour['accommodation'],
            }) : null);
            detailRow('Vendég résztvevők', ($tour['guest_count'] ?? 0) > 0 ? (int)$tour['guest_count'] . ' fő' : null);
            ?>
          </tbody>
        </table>
      </div>
      <?php if (!empty($tour['route'])): ?>
      <div style="padding:4px 12px 16px;border-top:1px solid var(--border);margin-top:4px;">
        <div style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);padding:10px 0 6px;">Útvonal</div>
        <div style="font-size:14px;line-height:1.7;white-space:pre-line;"><?= e($tour['route']) ?></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- 2. Teljesítmény -->
    <div class="card">
      <div class="card-header"><h2>Teljesítmény</h2></div>
      <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
          <tbody>
            <?php
            detailRow('Túramód', e(getTourTypeLabel($tour['tour_type'] ?? 'gyalogos')));

            $subLabels = [
                'normal'        => 'Normál gyalogtúra',
                'tajekozodasi'  => 'Tájékozódási verseny',
                'mout'          => 'Műúton / kerékpárúton',
                'terep'         => 'Terepen (földúton)',
                'folyasirany'   => 'Folyóvízen folyásirányban',
                'allovi'        => 'Állóvízen',
                'szemben'       => 'Folyóvízen szemben',
                'kiepitett'     => 'Kiépített barlang',
                'kiepitetlen'   => 'Nem kiépített barlang',
            ];
            if (!empty($tour['sub_type']) && isset($subLabels[$tour['sub_type']])) {
                detailRow('Altípus', e($subLabels[$tour['sub_type']]));
            }

            $totalKm  = ($tour['total_km']  ?? null) !== null ? (float)$tour['total_km']  : 0;
            $alpineKm = ($tour['alpine_km'] ?? null) !== null ? (float)$tour['alpine_km'] : 0;
            $fullKm   = $totalKm + $alpineKm;
            if ($fullKm > 0) {
                $kmVal = number_format($fullKm, 1, ',', ' ') . ' km';
                if ($alpineKm > 0) $kmVal .= ' <small style="color:var(--text-muted);">(' . number_format($alpineKm,1,',','') . ' km magashegyi)</small>';
                detailRow('Megtett km', $kmVal);
            }

            $totalElev  = ($tour['total_elevation']  ?? null) !== null ? (int)$tour['total_elevation']  : 0;
            $alpineElev = ($tour['alpine_elevation'] ?? null) !== null ? (int)$tour['alpine_elevation'] : 0;
            if ($totalElev + $alpineElev > 0) {
                $elevVal = number_format($totalElev + $alpineElev) . ' m';
                if ($alpineElev > 0) $elevVal .= ' <small style="color:var(--text-muted);">(' . number_format($alpineElev) . ' m magashegyi)</small>';
                detailRow('Szintemelkedés', $elevVal);
            }

            if ($tour['tour_hours'] !== null) {
                detailRow('Időtartam', number_format((float)$tour['tour_hours'], 1, ',', ' ') . ' óra');
            }
            if (!empty($tour['multi_day_type'])) {
                detailRow('Többnapos típusa', $tour['multi_day_type'] === 'csillag' ? 'Csillagtúra' : 'Vándortúra');
            }
            if ((int)($tour['camping_nights_fixed'] ?? 0) > 0) {
                detailRow('Eltöltött éjszakák', (int)$tour['camping_nights_fixed'] . ' éj');
            }
            if ($tour['tour_type'] === 'vizi' && (int)($tour['boat_portages'] ?? 0) > 0) {
                detailRow('Hajóátemelések', (int)$tour['boat_portages'] . ' alkalom');
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 3. Pontszámok -->
    <div class="card">
      <div class="card-header"><h2>Pontszámok</h2></div>
      <div id="pts-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:0;">
        <div style="padding:20px;text-align:center;border-right:1px solid var(--border);">
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:6px;">Lizzardier pont</div>
          <div style="font-size:28px;font-weight:700;color:var(--warning);"><?= number_format((int)$tour['points']) ?></div>
        </div>
        <div style="padding:20px;text-align:center;">
          <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:6px;">MTSZ pont</div>
          <div style="font-size:28px;font-weight:700;color:var(--primary);"><?= number_format((int)($tour['mtsz_points'] ?? 0)) ?></div>
        </div>
      </div>
      <?php $mtszLines = mtszBreakdownLines($tour); if (!empty($mtszLines)): ?>
      <div style="padding:10px 16px 14px;border-top:1px solid var(--border); text-align:right;">
        <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--text-muted);margin-bottom:6px;">MTSZ pont számítása</div>
        <?php foreach ($mtszLines as $line): ?>
        <div style="font-size:12px;color:var(--text-muted);line-height:1.9;font-family:monospace;"><?= e($line) ?></div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- 4. Résztvevők -->
    <div class="card">
      <div class="card-header">
        <h2>Résztvevők</h2>
        <span style="font-size:13px;color:var(--text-muted);"><?= count($members) ?> tag<?= ($tour['guest_count'] ?? 0) > 0 ? ', ' . (int)$tour['guest_count'] . ' vendég' : '' ?></span>
      </div>
      <?php if (!empty($members)): ?>
      <div style="padding:12px 16px;display:flex;flex-wrap:wrap;gap:10px;">
        <?php foreach ($members as $m): ?>
        <div style="display:flex;align-items:center;gap:8px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:6px 10px;">
          <img src="<?= getLevelImageUrl((int)$m['level']) ?>" alt="<?= e(getLevelLabel((int)$m['level'])) ?>"
               style="width:auto;height:48px;object-fit:contain;flex-shrink:0;">
          <div>
            <div style="font-size:13px;font-weight:600;"><?= e($m['lastname'] . ' ' . $m['firstname']) ?></div>
            <div style="font-size:11px;"><span class="level-badge <?= getLevelClass((int)$m['level']) ?>" style="font-size:10px;padding:1px 7px;"><?= getLevelLabel((int)$m['level']) ?></span></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="card-body" style="color:var(--text-muted);font-size:13px;">Nincs rögzített résztvevő.</div>
      <?php endif; ?>
    </div>

  </div><!-- /bal oszlop -->

  <!-- Jobb oszlop: Térképek (ha van GPX) -->
  <?php if ($hasMap): ?>
  <div style="display:flex;flex-direction:column;gap:20px;">
    <?php foreach ($gpxFiles as $gi => $gf): ?>
    <div class="card">
      <div class="card-header">
        <h2><?= !empty($gf['label']) ? e($gf['label']) : ('Térkép' . (count($gpxFiles) > 1 ? ' ' . ($gi + 1) . '.' : '')) ?></h2>
      </div>
      <div id="tour-map-<?= $gi ?>" style="height:500px;border-radius:0 0 var(--radius,8px) var(--radius,8px);overflow:hidden;"></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

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
      polyline_options: { color: '#ff0000', weight: 7, opacity: 1 },
      marker_options: { startIconUrl: null, endIconUrl: null, shadowUrl: null }
    }).on('loaded', function(e) {
      map.fitBounds(e.target.getBounds(), { padding: [20, 20] });
    }).on('error', function() {
      document.getElementById(cfg.id).innerHTML =
        '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted,#888);">Nem sikerült betölteni a GPX fájlt.</div>';
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

<?php include __DIR__ . '/../includes/user-footer.php'; ?>
