<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tour-organizers.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
require_once __DIR__ . '/../includes/app-settings-schema.php';
require_once __DIR__ . '/../includes/future-tour-content.php';

$pdo = getDb();
ensureFutureToursSchema($pdo);
ensureAppSettingsSchema($pdo);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/public/turanyptar.php');
    exit;
}

$tourStmt = $pdo->prepare("
    SELECT ft.*, c.name_hu AS country_name, c.flag_filename AS country_flag
    FROM future_tours ft
    LEFT JOIN countries c ON c.code = ft.country
    WHERE ft.id = ? AND ft.status != 'cancelled' AND (ft.status != 'draft' OR ?)
    LIMIT 1
");
$tourStmt->execute([$id, isAnyLeader() ? 1 : 0]);
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

// Belépett tag saját jelentkezése erre a túrára — a lemondott jelentkezés nem számít
$memberApp = null;
if (isLoggedIn()) {
    $appStmt = $pdo->prepare("
        SELECT status, paid_at
        FROM future_tour_applications
        WHERE future_tour_id = ? AND user_id = ? AND status != 'cancelled'
        LIMIT 1
    ");
    $appStmt->execute([$id, getCurrentUserId()]);
    $memberApp = $appStmt->fetch() ?: null;
}

$gpxFilesStmt = $pdo->prepare("SELECT * FROM future_tour_gpx_files WHERE future_tour_id = ? ORDER BY sort_order ASC");
$gpxFilesStmt->execute([$id]);
$gpxFiles = $gpxFilesStmt->fetchAll();
$hasMap   = !empty($gpxFiles);

$galStmt = $pdo->prepare("SELECT * FROM future_tour_gallery_images WHERE future_tour_id = ? ORDER BY sort_order ASC, uploaded_at ASC");
$galStmt->execute([$id]);
$gallery = $galStmt->fetchAll();

// Térképek: naphoz kötöttek a nap alatt, a többi a program végén („Útvonal”)
$dayGpx  = [];
$tourGpx = [];
foreach ($gpxFiles as $gi => $gf) {
    $gf['map_id'] = 'tour-map-' . $gi;
    if ($gf['day_number'] !== null) $dayGpx[(int)$gf['day_number']][] = $gf;
    else $tourGpx[] = $gf;
}

$coverUrl  = !empty($tour['cover_img']) ? BASE_URL . '/assets/uploads/tour-covers/' . $tour['cover_img'] : '';
$hasCover  = $coverUrl !== '';
$coverLogo = $tour['cover_logo'] ?? 'light';
$fee       = (float)($tour['participation_fee'] ?? 0);
$feeNote   = trim((string)($tour['fee_note'] ?? '')) ?: tourFeeNoteDefault($pdo);
// Találkozó: csak egynapos túránál, ha a hely ki van töltve
$meetPlace = (int)$tour['num_days'] === 1 ? trim((string)($tour['meeting_place'] ?? '')) : '';
$meetTime  = substr((string)($tour['meeting_time'] ?? ''), 0, 5);
$feeIncl   = tourTextLines($tour['fee_includes'] ?? '');
$feeExcl   = tourTextLines($tour['fee_excludes'] ?? '');
$tags      = tourTextLines($tour['experience_tags'] ?? '');
$equipment = tourTextLines($tour['equipment'] ?? '');
$organizers = getFutureTourOrganizers($pdo, (int)$tour['id']);

$pageTitle     = $tour['name'];
$activePubPage = 'turanyptar';
$ogType        = 'article';
$ogImage       = $hasCover ? $coverUrl : defaultSocialImage($pdo);
$metaDescription = trim((string)($tour['short_intro'] ?? '')) ?: mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string)($tour['description'] ?? '')))), 0, 160);
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap ftd">
  <a href="<?= BASE_URL ?>/public/turanyptar.php" class="ftd-back">← Vissza a naptárhoz</a>
  <?php if ($tour['status'] === 'draft'): ?>
    <div class="ftd-draft">Piszkozat — ezt az oldalt csak a vezetők látják, publikusan nem jelenik meg.</div>
  <?php endif; ?>

  <!-- Cím + helyszín -->
  <header class="ftd-head">
    <h1 class="ftd-title"><?= e($tour['name']) ?></h1>
    <?php if (!empty($tour['country_name']) || !empty($tour['region'])): ?>
    <div class="ftd-place">
      <?php if (!empty($tour['country_flag'])): ?>
        <img src="<?= e(getFlagUrl($tour['country_flag'])) ?>" class="ftd-flag" alt="">
      <?php endif; ?>
      <?php if (!empty($tour['country_name'])): ?><span class="ftd-country"><?= e($tour['country_name']) ?></span><?php endif; ?>
      <?php if (!empty($tour['region'])): ?>
        <span class="ftd-place-sep" aria-hidden="true"></span>
        <span class="ftd-region">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
          <?= e($tour['region']) ?>
        </span>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </header>

  <!-- Borítókép + összegző sáv -->
  <div class="tour-hero ftd-hero<?= $hasCover ? '' : ' tour-hero--nocover' ?>"<?= $hasCover ? ' style="background-image:url(\'' . e($coverUrl) . '\');"' : '' ?>>
    <?php if ($coverLogo !== 'none'): ?>
      <img class="ftd-hero-logo<?= ($tour['cover_logo_pos'] ?? 'left') === 'right' ? ' ftd-hero-logo--right' : '' ?>" src="<?= BASE_URL ?>/assets/img/logo_collision_<?= $coverLogo === 'dark' ? 'b' : 'y' ?>_small.png" alt="<?= e(APP_NAME) ?>">
    <?php endif; ?>
    <div class="tour-hero-scrim">
      <div class="tour-hero-band">
        <div class="tour-hero-stat">
          <span class="ths-label">Kezdés</span>
          <span class="ths-value"><?= $tour['start_date'] ? formatDate($tour['start_date']) : '—' ?></span>
        </div>
        <div class="tour-hero-stat">
          <span class="ths-label">Időtartam</span>
          <span class="ths-value"><?= (int)$tour['num_days'] ?> nap</span>
        </div>
        <div class="tour-hero-stat">
          <span class="ths-label">Helyek</span>
          <span class="ths-value"><?= $spotsLeft > 0 ? $spotsLeft . ' / ' . (int)$tour['max_attendees'] : 'Várólista' ?></span>
        </div>
      </div>
    </div>
    <?php if ($tour['lizzardier_points'] !== null): ?>
      <div class="ftd-crest" title="Szerezhető Lizzardier pont">
        <span class="ftd-crest-inner">
          <span class="ftd-crest-num"><?= (int)$tour['lizzardier_points'] ?></span>
          <span class="ftd-crest-label">pont</span>
        </span>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!empty($tour['description'])): ?>
    <div class="ftd-lead ftd-text"><?= e($tour['description']) ?></div>
  <?php endif; ?>

  <!-- Találkozó | Jelentkezés + díj -->
  <?php if ($meetPlace !== ''): ?>
  <div class="ftd-apply-row">
    <section class="ftd-meet">
      <h2 class="ftd-h">Találkozó</h2>
      <?php if ($meetTime !== ''): ?><div class="ftd-meet-time"><?= e($meetTime) ?></div><?php endif; ?>
      <div class="ftd-meet-place"><?= e($meetPlace) ?></div>
      <a class="ftd-meet-btn" href="https://www.google.com/maps/search/?api=1&amp;query=<?= e(rawurlencode($meetPlace)) ?>" target="_blank" rel="noopener">Térkép megnyitása</a>
    </section>
  <?php endif; ?>
  <section class="ftd-apply<?= ($feeIncl || $feeExcl) ? '' : ' ftd-apply--single' ?>">
    <div class="ftd-apply-main">
      <?php if ($memberApp): ?>
        <?php
          $needsPay = $memberApp['status'] === 'confirmed' && $fee > 0 && !$memberApp['paid_at'];
          if ($memberApp['status'] === 'waitlist') {
              $appBadge = '<span class="badge-waitlist">Várólista</span>';
              $appNote  = 'A túra betelt. Ha felszabadul egy hely, e-mailben értesítünk.';
          } elseif ($memberApp['status'] === 'pending') {
              $appBadge = '<span class="badge badge-pending">Elfogadásra vár</span>';
              $appNote  = 'Jelentkezésedet rögzítettük, a szervező hamarosan elfogadja.';
          } elseif ($needsPay) {
              $appBadge = '<span class="badge badge-overdue">Befizetésre vár</span>';
              $appNote  = 'A helyedet fenntartjuk, a részvételi díj befizetése még hátravan.';
          } else {
              $appBadge = '<span class="badge badge-active">Elfogadva</span>';
              $appNote  = 'A részvételed véglegesítve.';
          }
        ?>
        <div class="ftd-myapp">
          <div class="ftd-myapp-title">Már jelentkeztél erre a túrára <?= $appBadge ?></div>
          <div class="ftd-myapp-note"><?= e($appNote) ?></div>
          <?php if ($needsPay): ?><?= bankInfoBox() ?><?php endif; ?>
          <a href="<?= BASE_URL ?>/user/future-tour-detail.php?id=<?= (int)$id ?>" class="ftd-apply-btn ftd-apply-btn--ghost">Jelentkezésem kezelése</a>
        </div>
      <?php elseif ($tour['status'] !== 'open'): ?>
        <span class="ftd-apply-btn ftd-apply-btn--closed">A jelentkezés lezárva</span>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/public/tour-apply.php?id=<?= (int)$id ?>" class="ftd-apply-btn">
          <?= $spotsLeft > 0 ? 'Jelentkezés' : 'Várólistára feliratkozás' ?>
        </a>
        <div class="ftd-spots"><?= $spotsLeft > 0 ? 'Még ' . $spotsLeft . ' szabad hely' : 'A túra betelt — várólistára feliratkozhatsz' ?></div>
      <?php endif; ?>

      <?php if ($fee > 0): ?>
        <div class="ftd-fee-label">Részvételi díj</div>
        <div class="ftd-fee-value"><?= number_format($fee, 0, ',', '.') ?> Ft</div>
        <p class="ftd-fee-note"><?= e($feeNote) ?></p>
      <?php else: ?>
        <div class="ftd-fee-value ftd-fee-free">A részvétel ingyenes</div>
      <?php endif; ?>
    </div>

    <?php if ($feeIncl || $feeExcl): ?>
    <div class="ftd-apply-side">
      <?php if ($feeIncl): ?>
        <div class="ftd-ie-title">Tartalmazza</div>
        <ul class="ftd-ie ftd-ie--in"><?php foreach ($feeIncl as $line): ?><li><?= e($line) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
      <?php if ($feeExcl): ?>
        <div class="ftd-ie-title">Nem tartalmazza</div>
        <ul class="ftd-ie ftd-ie--out"><?php foreach ($feeExcl as $line): ?><li><?= e($line) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </section>
  <?php if ($meetPlace !== ''): ?></div><?php endif; ?>

  <!-- Szervező | Tapasztalat + élménycímkék -->
  <section class="ftd-split ftd-split--org">
    <?php if ($organizers): ?>
    <div class="ftd-org">
      <h2 class="ftd-h"><?= count($organizers) > 1 ? 'Szervezők' : 'Szervező' ?></h2>
      <?php foreach ($organizers as $org):
        $orgEmail = trim((string)($org['email'] ?? ''));
        $orgPhone = trim((string)($org['phone'] ?? ''));
      ?>
      <div class="ftd-org-person">
        <img class="to-avatar ftd-org-avatar" src="<?= e(getAvatarThumbUrl($org['profile_picture'] ?? null)) ?>" alt="" loading="lazy">
        <span class="to-name"><?= e($org['lastname'] . ' ' . $org['firstname']) ?></span>
        <?php if ($orgEmail !== ''): ?><a class="to-contact" href="mailto:<?= e($orgEmail) ?>"><?= e($orgEmail) ?></a><?php endif; ?>
        <?php if ($orgPhone !== ''): ?><a class="to-contact" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $orgPhone)) ?>"><?= e($orgPhone) ?></a><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="ftd-org-side">
      <?php if (!empty($tour['experience'])): ?>
        <h2 class="ftd-h">Szükséges tapasztalat és erőnlét</h2>
        <div class="ftd-text"><?= e($tour['experience']) ?></div>
      <?php endif; ?>
      <?php if ($tags): ?>
        <h2 class="ftd-h">Élménycímkék</h2>
        <ul class="ftd-tags"><?php foreach ($tags as $tag): ?><li><?= e($tag) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
  </section>

  <?php if (!empty($gallery)): ?>
  <section class="ftd-section">
    <h2 class="ftd-h">Wow faktor</h2>
    <div class="ftd-gallery" id="tour-gallery">
      <?php foreach ($gallery as $img): ?>
      <button type="button" class="tour-gallery-thumb" data-full="<?= e(TOUR_GALLERY_URL . $img['filename']) ?>" data-caption="<?= e($img['label'] ?? '') ?>">
        <img src="<?= e(TOUR_GALLERY_URL . $img['filename']) ?>" alt="<?= e($img['label'] ?? $tour['name']) ?>" loading="lazy">
      </button>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($tour['accommodation'])): ?>
  <section class="ftd-section">
    <h2 class="ftd-h"><?= (int)$tour['num_days'] === 1 ? 'Utazás' : 'Szállás és utazás' ?></h2>
    <div class="ftd-text"><?= e($tour['accommodation']) ?></div>
  </section>
  <?php endif; ?>

  <!-- Felszerelés | Fontos -->
  <section class="ftd-split ftd-split--even<?= $equipment ? '' : ' ftd-split--solo' ?>">
    <?php if ($equipment): ?>
    <div>
      <h2 class="ftd-h">Felszerelés</h2>
      <ul class="ftd-gear"><?php foreach ($equipment as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>
    <div class="ftd-important">
      <h2 class="ftd-h">Fontos!</h2>
      <div class="ftd-text-html"><?= tourImportantHtml(tourImportantText($pdo)) ?></div>
    </div>
  </section>

  <?php if (!empty($days) || $tourGpx): ?>
  <section class="ftd-section">
    <h2 class="ftd-h">Részletes program</h2>
    <?php foreach ($days as $day): ?>
    <article class="ftd-day">
      <div class="ftd-day-head">
        <span class="ftd-day-num"><?= (int)$day['day_number'] ?>. nap</span>
        <?php if (!empty($day['title'])): ?><span class="ftd-day-title"><?= e($day['title']) ?></span><?php endif; ?>
        <span class="ftd-day-meta">
          <?php if (!empty($day['tour_type'])): ?><span><?= e($day['tour_type']) ?></span><?php endif; ?>
          <?php if ($day['km'] !== null): ?><span><?= number_format((float)$day['km'], 1, ',', ' ') ?> km</span><?php endif; ?>
          <?php if ($day['elevation'] !== null): ?><span>&uarr; <?= number_format((int)$day['elevation'], 0, ',', ' ') ?> m</span><?php endif; ?>
        </span>
      </div>
      <?php if (!empty($day['description'])): ?>
        <div class="ftd-text ftd-day-text"><?= e($day['description']) ?></div>
      <?php endif; ?>
      <?php foreach ($dayGpx[(int)$day['day_number']] ?? [] as $gf): ?>
        <?php if (!empty($gf['label'])): ?><div class="ftd-map-label"><?= e($gf['label']) ?></div><?php endif; ?>
        <div id="<?= e($gf['map_id']) ?>" class="ftd-map"></div>
      <?php endforeach; ?>
    </article>
    <?php endforeach; ?>

    <?php if ($tourGpx): ?>
    <article class="ftd-day">
      <div class="ftd-day-head"><span class="ftd-day-num">Útvonal</span></div>
      <?php foreach ($tourGpx as $gf): ?>
        <?php if (!empty($gf['label'])): ?><div class="ftd-map-label"><?= e($gf['label']) ?></div><?php endif; ?>
        <div id="<?= e($gf['map_id']) ?>" class="ftd-map"></div>
      <?php endforeach; ?>
    </article>
    <?php endif; ?>
  </section>
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
