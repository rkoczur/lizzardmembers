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

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/user/future-tours.php');
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
    header('Location: ' . BASE_URL . '/user/future-tours.php');
    exit;
}

$daysStmt = $pdo->prepare("SELECT * FROM future_tour_days WHERE future_tour_id = ? ORDER BY day_number ASC");
$daysStmt->execute([$id]);
$days = $daysStmt->fetchAll();

$customFieldsStmt = $pdo->prepare("SELECT * FROM future_tour_custom_fields WHERE future_tour_id = ? ORDER BY sort_order ASC, id ASC");
$customFieldsStmt->execute([$id]);
$customFields = $customFieldsStmt->fetchAll();

$myAppStmt = $pdo->prepare("SELECT * FROM future_tour_applications WHERE future_tour_id = ? AND user_id = ? LIMIT 1");
$myAppStmt->execute([$id, $userId]);
$myApp = $myAppStmt->fetch();

$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM future_tour_applications WHERE future_tour_id = ? AND status = 'confirmed'");
$cntStmt->execute([$id]);
$confirmedCount = (int)$cntStmt->fetchColumn();
$spotsLeft = max(0, (int)$tour['max_attendees'] - $confirmedCount);

$userStmt = $pdo->prepare("SELECT level, role FROM users WHERE id = ? LIMIT 1");
$userStmt->execute([$userId]);
$userRow   = $userStmt->fetch() ?: [];
$userLevel = (int)($userRow['level'] ?? 1);
$feeDiscount = getTourFeeDiscount($userLevel, (string)($userRow['role'] ?? 'user'));

$gpxFilesStmt = $pdo->prepare("SELECT * FROM future_tour_gpx_files WHERE future_tour_id = ? ORDER BY sort_order ASC, uploaded_at ASC");
$gpxFilesStmt->execute([$id]);
$gpxFiles = $gpxFilesStmt->fetchAll();
$hasMap = !empty($gpxFiles);

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$pageTitle  = $tour['name'] ?? '';
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
  <div class="flex items-center gap-2">
    <a href="<?= BASE_URL ?>/user/future-tours.php" class="btn btn-secondary btn-sm">← Vissza</a>
    <h1><?= e($tour['name']) ?></h1>
  </div>
</div>

<div class="future-detail-grid">

  <!-- LEFT: Tour details -->
  <div>

    <!-- Header card -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-body">

        <!-- Location -->
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
          <?php if (!empty($tour['country_flag'])): ?>
            <img src="<?= e(getFlagUrl($tour['country_flag'])) ?>"
                 class="flag-img-lg" alt="">
          <?php endif; ?>
          <span style="font-size:15px;font-weight:700;"><?= e($tour['country_name'] ?? $tour['country'] ?? '—') ?></span>
          <?php if (!empty($tour['region'])): ?>
            <span style="color:var(--border);">|</span>
            <span style="color:var(--text-muted);font-size:14px;"><?= e($tour['region']) ?></span>
          <?php endif; ?>
        </div>

        <!-- Stats grid -->
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
          <?php if ($tour['lizzardier_points'] !== null): ?>
          <div class="tour-stat-cell">
            <div class="tour-stat-label">Lizzardier</div>
            <div class="tour-stat-value" style="color:var(--primary);"><?= (int)$tour['lizzardier_points'] ?> pont</div>
          </div>
          <?php endif; ?>
          <?php if ($tour['participation_fee'] !== null): ?>
          <div class="tour-stat-cell">
            <div class="tour-stat-label">Részvételi díj</div>
            <?php if ($feeDiscount > 0): ?>
              <div style="font-size:12px;color:var(--text-muted);text-decoration:line-through;line-height:1.3;"><?= number_format((float)$tour['participation_fee'], 0, ',', ' ') ?> Ft</div>
              <div class="tour-stat-value" style="color:var(--primary);">
                <?= number_format((float)$tour['participation_fee'] * (1 - $feeDiscount / 100), 0, ',', ' ') ?> Ft
                <span class="badge-discount" style="margin-left:2px;">-<?= $feeDiscount ?>%</span>
              </div>
            <?php else: ?>
              <div class="tour-stat-value"><?= number_format((float)$tour['participation_fee'], 0, ',', ' ') ?> Ft</div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- Description -->
        <?php if (!empty($tour['description'])): ?>
          <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);color:var(--text);line-height:1.65;white-space:pre-wrap;"><?= e($tour['description']) ?></div>
        <?php endif; ?>

      </div>
    </div>

    <!-- Days -->
    <?php if (!empty($days)): ?>
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header"><h2>Napok</h2></div>
      <div class="card-body" style="padding:0;">
        <table style="width:100%;border-collapse:collapse;font-size:13.5px;">
          <thead>
            <tr style="background:var(--card);border-bottom:1px solid var(--border);">
              <th style="padding:8px 16px;text-align:left;color:var(--text-muted);font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;font-weight:600;">Nap</th>
              <th style="padding:8px 16px;text-align:left;color:var(--text-muted);font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;font-weight:600;">Típus</th>
              <th style="padding:8px 16px;text-align:right;color:var(--text-muted);font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;font-weight:600;">Km</th>
              <th style="padding:8px 16px;text-align:right;color:var(--text-muted);font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;font-weight:600;">Szint</th>
              <th style="padding:8px 16px;text-align:left;color:var(--text-muted);font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;font-weight:600;">Leírás</th>
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

    <!-- Info blocks: Szállás, Utazás, Felszerelés, Tapasztalat -->
    <?php
    $infoBlocks = [
        'accommodation' => 'Szállás',
        'travel'        => 'Utazás',
        'equipment'     => 'Felszerelés',
        'experience'    => 'Szükséges tapasztalat és erőnlét',
    ];
    foreach ($infoBlocks as $col => $label):
        if (empty($tour[$col])) continue;
    ?>
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header"><h2><?= $label ?></h2></div>
      <div class="card-body">
        <div style="color:var(--text);line-height:1.65;white-space:pre-wrap;"><?= e($tour[$col]) ?></div>
      </div>
    </div>
    <?php endforeach; ?>


  </div>

  <!-- RIGHT: Application status / CTA + Térképek -->
  <div style="display:flex;flex-direction:column;gap:16px;">
    <div class="card sticky-panel">
    <div class="card-body card-body-center">
      <?php if ($myApp && $myApp['status'] === 'confirmed'): ?>
        <div style="font-size:40px;margin-bottom:12px;">✅</div>
        <div style="font-weight:700;font-size:17px;margin-bottom:6px;color:var(--primary);">Sikeresen jelentkeztél!</div>
        <div style="color:var(--text-muted);font-size:13px;margin-bottom:6px;">Jelentkezés ideje: <?= date('Y.m.d H:i', strtotime($myApp['applied_at'])) ?></div>
        <?php if ($myApp['paid_at']): ?>
          <div style="color:var(--primary);font-size:13px;font-weight:600;margin-bottom:16px;">✓ Részvételi díj befizetve</div>
        <?php else: ?>
          <div class="alert-warning-box" style="margin-bottom:16px;text-align:left;">
            ⚠ Részvételi díjad még nem érkezett meg. Kérjük, 14 napon belül utalj!
          </div>
        <?php endif; ?>
        <form method="post" action="<?= BASE_URL ?>/actions/future-tour-cancel.php"
              onsubmit="return confirm('Biztosan lemondod a jelentkezésedet?')">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="tour_id" value="<?= (int)$id ?>">
          <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);border-color:var(--danger);">Lemondás</button>
        </form>

      <?php elseif ($myApp && $myApp['status'] === 'waitlist'): ?>
        <div style="font-size:40px;margin-bottom:12px;">⏳</div>
        <div style="font-weight:700;font-size:17px;margin-bottom:6px;">Várólistán vagy</div>
        <div style="color:var(--text-muted);font-size:13px;margin-bottom:16px;">Feliratkoztál: <?= date('Y.m.d H:i', strtotime($myApp['applied_at'])) ?><br>Ha felszabadul egy hely, értesítést kapsz.</div>
        <form method="post" action="<?= BASE_URL ?>/actions/future-tour-cancel.php"
              onsubmit="return confirm('Biztosan eltávolítod magad a várólistáról?')">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="tour_id" value="<?= (int)$id ?>">
          <button type="submit" class="btn btn-ghost btn-sm" style="color:var(--danger);border-color:var(--danger);">Eltávolítás a várólistáról</button>
        </form>

      <?php elseif ($tour['status'] !== 'open'): ?>
        <div style="font-size:40px;margin-bottom:12px;">🔒</div>
        <div style="font-weight:700;font-size:17px;margin-bottom:6px;color:var(--text-muted);">A jelentkezés lezárva</div>

      <?php else: ?>
        <div style="font-size:40px;margin-bottom:12px;"><?= $spotsLeft > 0 ? '🎒' : '📋' ?></div>
        <?php if ($spotsLeft > 0): ?>
          <div style="font-weight:700;font-size:17px;margin-bottom:4px;">Még <?= $spotsLeft ?> szabad hely</div>
          <div style="color:var(--text-muted);font-size:13px;margin-bottom:20px;"><?= (int)$tour['max_attendees'] ?> főre van tervezve</div>
        <?php else: ?>
          <div style="font-weight:700;font-size:17px;margin-bottom:4px;">A túra betelt</div>
          <div style="color:var(--text-muted);font-size:13px;margin-bottom:20px;">Feliratkozhatsz a várólistára</div>
        <?php endif; ?>
        <button type="button" id="open-apply-modal" class="btn btn-primary" style="width:100%;padding:12px;font-size:15px;">
          <?= $spotsLeft > 0 ? 'Jelentkezés' : 'Várólistára feliratkozás' ?>
        </button>
      <?php endif; ?>
    </div>
    </div><!-- /sticky-panel -->

    <?php foreach ($gpxFiles as $gi => $gf): ?>
    <div class="card">
      <div class="card-header">
        <h2><?= !empty($gf['label']) ? e($gf['label']) : ('Térkép' . (count($gpxFiles) > 1 ? ' ' . ($gi + 1) . '.' : '')) ?></h2>
      </div>
      <div id="tour-map-<?= $gi ?>" style="height:400px;border-radius:0 0 var(--radius,8px) var(--radius,8px);overflow:hidden;"></div>
    </div>
    <?php endforeach; ?>

  </div><!-- /right col -->

</div><!-- .future-detail-grid -->

<!-- Application Modal -->
<?php
$modalDisabledFields = json_decode($tour['disabled_standard_fields'] ?? '[]', true) ?: [];
$modalFieldOn = fn(string $f): bool => !in_array($f, $modalDisabledFields, true);
?>
<?php if ((!$myApp || $myApp['status'] === 'cancelled') && $tour['status'] === 'open'): ?>
<div id="apply-modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:200;align-items:center;justify-content:center;padding:16px;">
  <div style="background:var(--bg,#fff);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.18);width:100%;max-width:560px;max-height:92vh;overflow-y:auto;">
    <div class="flex-between" style="padding:20px 24px;border-bottom:1px solid var(--border);">
      <h2 style="margin:0;font-size:18px;">Jelentkezés – <?= e($tour['name']) ?></h2>
      <button id="close-apply-modal" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-muted);line-height:1;">✕</button>
    </div>
    <form method="post" action="<?= BASE_URL ?>/actions/future-tour-apply.php">
      <div style="padding:20px 24px;">
        <div style="background:var(--card,#f5efe4);border-radius:8px;padding:14px 16px;font-size:13px;color:var(--text);margin-bottom:20px;line-height:1.55;">
          Az alábbi űrlapon jelentkezhetsz a túrára. A jelentkezéssel kijelented, hogy 14 napon belül befizeted a részvételi díjad, ellenkező esetben a rendszer automatikusan feloldja a foglalásodat, és amennyiben van várólistán jelentkező, neki adja tovább.
        </div>

        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="tour_id" value="<?= (int)$id ?>">

        <?php if ($modalFieldOn('departure_city')): ?>
        <!-- Departure city -->
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;">Honnan indulnál? <span style="color:var(--danger)">*</span></label>
          <input type="text" name="departure_city" required placeholder="pl. Budapest XIII. kerület" style="margin-top:6px;width:100%;">
          <small style="display:block;color:var(--text-muted);font-size:11.5px;margin-top:4px;">Budapest esetén a kerületet is add meg!</small>
        </div>
        <?php endif; ?>

        <?php if ($modalFieldOn('car_available')): ?>
        <!-- Car -->
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;">Tudsz autóval jönni?</label>
          <div style="display:flex;gap:16px;margin-top:6px;">
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;">
              <input type="radio" name="car_available" value="1" id="car-yes"> Igen
            </label>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;">
              <input type="radio" name="car_available" value="0" id="car-no" checked> Nem
            </label>
          </div>
        </div>
        <div id="passengers-row" style="margin-bottom:16px;display:none;">
          <div class="form-group">
            <label style="font-size:13px;font-weight:600;">Ha igen, hány hely van melletted?</label>
            <input type="number" name="passengers" min="0" max="10" value="0" style="width:80px;margin-top:6px;">
            <small style="display:block;color:var(--text-muted);font-size:11.5px;margin-top:4px;">
              Ha már megvan, hogy kivel utazol, akkor is a maximum számot írd be, és majd a megjegyzésnél jelezd, hogy ki az utasod.
            </small>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($modalFieldOn('sharing_room')): ?>
        <!-- Sharing room -->
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;">Szükség esetén aludnál egy helyen mással?</label>
          <select name="sharing_room" style="margin-top:6px;width:100%;">
            <option value="same_gender">Igen, de csak azonos neművel</option>
            <option value="yes">Igen</option>
            <option value="no">Nem</option>
          </select>
        </div>
        <?php endif; ?>

        <?php if ($modalFieldOn('notes')): ?>
        <!-- Notes -->
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;">Megjegyzések</label>
          <textarea name="notes" rows="3" placeholder="Egyéb megjegyzés, kérés…" style="margin-top:6px;"></textarea>
        </div>
        <?php endif; ?>

        <!-- Custom fields -->
        <?php foreach ($customFields as $cf): ?>
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;"><?= e($cf['field_name']) ?></label>
          <?php if ($cf['field_type'] === 'textarea'): ?>
            <textarea name="custom_field_<?= (int)$cf['id'] ?>" rows="2" style="margin-top:6px;"></textarea>
          <?php elseif ($cf['field_type'] === 'checkbox'): ?>
            <label style="display:flex;align-items:center;gap:8px;margin-top:8px;font-weight:normal;cursor:pointer;">
              <input type="checkbox" name="custom_field_<?= (int)$cf['id'] ?>" value="1"> Igen
            </label>
          <?php elseif ($cf['field_type'] === 'select' && !empty($cf['field_options'])): ?>
            <select name="custom_field_<?= (int)$cf['id'] ?>" style="margin-top:6px;width:100%;">
              <option value="">— válassz —</option>
              <?php foreach (array_map('trim', explode(',', $cf['field_options'])) as $opt): ?>
                <?php if ($opt !== ''): ?>
                  <option value="<?= e($opt) ?>"><?= e($opt) ?></option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
          <?php elseif ($cf['field_type'] === 'number'): ?>
            <input type="number" name="custom_field_<?= (int)$cf['id'] ?>" style="margin-top:6px;">
          <?php else: ?>
            <input type="text" name="custom_field_<?= (int)$cf['id'] ?>" style="margin-top:6px;">
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="padding:16px 24px 20px;border-top:1px solid var(--border);display:flex;gap:12px;justify-content:flex-end;">
        <button type="button" id="close-apply-modal-2" class="btn btn-ghost">Mégse</button>
        <button type="submit" class="btn btn-primary">
          <?= $spotsLeft > 0 ? 'Jelentkezés elküldése' : 'Feliratkozás várólistára' ?>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
const backdrop = document.getElementById('apply-modal-backdrop');

document.getElementById('open-apply-modal')?.addEventListener('click', () => {
  backdrop.style.display = 'flex';
  document.body.style.overflow = 'hidden';
});
['close-apply-modal','close-apply-modal-2'].forEach(id => {
  document.getElementById(id)?.addEventListener('click', closeModal);
});
backdrop?.addEventListener('click', e => { if (e.target === backdrop) closeModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

function closeModal() {
  backdrop.style.display = 'none';
  document.body.style.overflow = '';
}

// Show/hide passengers field
document.querySelectorAll('input[name="car_available"]').forEach(r => {
  r.addEventListener('change', () => {
    document.getElementById('passengers-row').style.display = r.value === '1' ? 'block' : 'none';
  });
});
</script>
<?php endif; ?>

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
