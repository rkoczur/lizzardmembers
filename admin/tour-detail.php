<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tours-schema.php';
requireAdminOrVezeto();
$ro = !canManageTours();

$pdo = getDb();
ensureToursSchema($pdo);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/admin/tours.php');
    exit;
}

$tour = $pdo->prepare("SELECT * FROM tours WHERE id = ? LIMIT 1");
$tour->execute([$id]);
$tour = $tour->fetch();
if (!$tour) {
    header('Location: ' . BASE_URL . '/admin/tours.php');
    exit;
}

$isPending = ($tour['status'] ?? 'approved') === 'pending';

$allMembers = $pdo->query("SELECT id, firstname, lastname, email, role FROM users ORDER BY lastname, firstname")->fetchAll();
$countries  = getCountries($pdo);

$assignedStmt = $pdo->prepare("SELECT u.id, u.firstname, u.lastname, u.email, u.role FROM tour_members tm JOIN users u ON u.id = tm.user_id WHERE tm.tour_id = ? ORDER BY u.lastname, u.firstname");
$assignedStmt->execute([$id]);
$assignedMembers = $assignedStmt->fetchAll();

$gpxFilesStmt = $pdo->prepare("SELECT * FROM tour_gpx_files WHERE tour_id = ? ORDER BY sort_order ASC, uploaded_at ASC");
$gpxFilesStmt->execute([$id]);
$gpxFiles = $gpxFilesStmt->fetchAll();

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$pageTitle  = $tour['name'] ?: ($tour['country'] . ($tour['region'] ? ' – ' . $tour['region'] : ''));
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
  <div class="flex items-center gap-2">
    <a href="<?= BASE_URL ?>/admin/tours.php" class="btn btn-secondary btn-sm">← Vissza</a>
    <h1><?= $tour['name'] ? e($tour['name']) : e($tour['country'] . ($tour['region'] ? ' – ' . $tour['region'] : '')) ?></h1>
  </div>
  <?php if (!$ro): ?>
  <form method="post" action="<?= BASE_URL ?>/actions/tour-delete.php"
        onsubmit="return confirmDelete('Biztosan törli ezt a túrát? A művelet nem vonható vissza.')">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="id" value="<?= $tour['id'] ?>">
    <button type="submit" class="btn btn-danger btn-sm">Túra törlése</button>
  </form>
  <?php endif; ?>
</div>

<div class="card" style="max-width:760px;">
  <div class="card-header">
    <h2>Túra adatai</h2>
    <?php if ($ro): ?>
      <span class="badge badge-vezeto" style="font-size:11px;">Csak megtekintés</span>
    <?php elseif ($isPending): ?>
      <span style="background:var(--warning,#f59e0b);color:#fff;border-radius:4px;padding:2px 10px;font-size:12px;font-weight:700;">Jóváhagyásra vár</span>
    <?php endif; ?>
  </div>
  <div class="card-body">
  <?php if ($isPending): ?>
    <?php
    $submitterStmt = $pdo->prepare("SELECT CONCAT(lastname, ' ', firstname) AS name FROM users WHERE id = ? LIMIT 1");
    $submitterStmt->execute([$tour['submitted_by'] ?? 0]);
    $submitterName = $submitterStmt->fetchColumn() ?: null;
    ?>
    <div style="background:var(--warning-bg,#fffbeb);border:1px solid var(--warning,#f59e0b);border-radius:8px;padding:14px 18px;margin-bottom:18px;">
      <div style="font-size:13px;font-weight:600;color:var(--warning,#b45309);margin-bottom:4px;">
        Beküldött túra – jóváhagyás szükséges
        <?php if ($submitterName): ?>
          <span style="font-weight:400;color:var(--text-muted);">— beküldő: <?= e($submitterName) ?></span>
        <?php endif; ?>
      </div>
      <?php if (!empty($tour['submission_notes'])): ?>
        <div style="font-size:13px;color:var(--text);margin-top:6px;white-space:pre-wrap;"><?= e($tour['submission_notes']) ?></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
    <form method="post" action="<?= BASE_URL ?>/actions/tour-update.php" id="tour-form" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="id" value="<?= $tour['id'] ?>">

      <div class="form-section-title">Általános adatok</div>
      <div class="form-grid">
        <div class="form-group">
          <label>Túra sorszáma</label>
          <input type="text" value="<?= e($tour['tour_code'] ?? '—') ?>" readonly
                 style="background:var(--bg-subtle,#f5f5f5);font-family:monospace;font-weight:700;cursor:default;">
        </div>
        <div class="form-group"></div>
        <div class="form-group full">
          <label>Elnevezés</label>
          <input type="text" name="name" value="<?= e($tour['name'] ?? '') ?>" placeholder="pl. Mátra körüljáró túra" <?= $ro ? 'readonly' : '' ?>>
        </div>
        <div class="form-group">
          <label>Ország <span style="color:var(--danger)">*</span></label>
          <div style="display:flex;align-items:center;gap:8px;">
            <select name="country" id="country_select" <?= $ro ? 'disabled' : 'required' ?> style="flex:1;">
              <option value="">— Válasszon országot —</option>
              <?php foreach ($countries as $c): ?>
              <option value="<?= e($c['code']) ?>"
                      data-flag="<?= $c['flag_filename'] ? e(getFlagUrl($c['flag_filename'])) : '' ?>"
                      <?= $tour['country'] === $c['code'] ? 'selected' : '' ?>>
                <?= e($c['name_hu']) ?> (<?= e($c['code']) ?>)
              </option>
              <?php endforeach; ?>
            </select>
            <img id="country_flag_preview" src="" alt=""
                 style="width:36px;height:24px;object-fit:cover;border:1px solid var(--border);border-radius:3px;display:none;flex-shrink:0;">
          </div>
        </div>
        <div class="form-group">
          <label>Tájegység</label>
          <input type="text" name="region" value="<?= e($tour['region'] ?? '') ?>" <?= $ro ? 'readonly' : '' ?>>
        </div>
        <div class="form-group">
          <label>Dátum</label>
          <input type="date" name="tour_date" id="tour_date" value="<?= e($tour['tour_date'] ?? '') ?>" <?= $ro ? 'readonly' : '' ?>>
        </div>
        <div class="form-group">
          <label>Napok száma <span style="color:var(--danger)">*</span></label>
          <input type="number" name="days" id="days" value="<?= (int)$tour['days'] ?>" min="1" <?= $ro ? 'readonly' : 'required' ?>>
        </div>
        <div class="form-group">
          <label>Szállás típusa</label>
          <select name="accommodation" id="accommodation" <?= $ro ? 'disabled' : '' ?>>
            <option value="">— Nem megadott —</option>
            <option value="sator"      <?= ($tour['accommodation'] ?? '') === 'sator'      ? 'selected' : '' ?>>Sátor</option>
            <option value="turistahaz" <?= ($tour['accommodation'] ?? '') === 'turistahaz' ? 'selected' : '' ?>>Túristaház</option>
            <option value="apartman"   <?= ($tour['accommodation'] ?? '') === 'apartman'   ? 'selected' : '' ?>>Apartman</option>
            <option value="hotel"      <?= ($tour['accommodation'] ?? '') === 'hotel'      ? 'selected' : '' ?>>Hotel</option>
          </select>
        </div>
        <div class="form-group">
          <label>Vendég résztvevők</label>
          <input type="number" name="guest_count" min="0" value="<?= (int)($tour['guest_count'] ?? 0) ?>" placeholder="0" <?= $ro ? 'readonly' : '' ?>>
        </div>
        <div class="form-group full">
          <label>Túra útvonala</label>
          <textarea name="route" rows="4" placeholder="pl. Eger – Felsőtárkány – Bükk-fennsík – Miskolc" <?= $ro ? 'readonly' : '' ?>><?= e($tour['route'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="form-section-title">Túramód</div>
      <div class="form-grid">
        <div class="form-group">
          <label>Túramód <span style="color:var(--danger)">*</span></label>
          <select name="tour_type" id="tour_type" <?= $ro ? 'disabled' : 'required' ?>>
            <option value="gyalogos"   <?= ($tour['tour_type'] ?? 'gyalogos') === 'gyalogos'   ? 'selected' : '' ?>>Gyalogos</option>
            <option value="kerekparos" <?= ($tour['tour_type'] ?? '') === 'kerekparos' ? 'selected' : '' ?>>Kerékpáros</option>
            <option value="vizi"       <?= ($tour['tour_type'] ?? '') === 'vizi'       ? 'selected' : '' ?>>Vízitúra</option>
            <option value="si"         <?= ($tour['tour_type'] ?? '') === 'si'         ? 'selected' : '' ?>>Síelés</option>
            <option value="barlangi"   <?= ($tour['tour_type'] ?? '') === 'barlangi'   ? 'selected' : '' ?>>Barlangi</option>
            <option value="munka"      <?= ($tour['tour_type'] ?? '') === 'munka'      ? 'selected' : '' ?>>Munkatúra</option>
          </select>
        </div>
        <div class="form-group" id="sub_type_group">
          <label>Altípus <span style="color:var(--danger)">*</span></label>
          <select name="sub_type" id="sub_type" <?= $ro ? 'disabled' : '' ?>><!-- JS tölti fel --></select>
        </div>
      </div>

      <div class="form-section-title">Teljesítmény adatok</div>
      <div class="form-grid">
        <div class="form-group" id="normal_km_group">
          <label id="normal_km_label">Nem magashegyi km</label>
          <input type="number" name="total_km" id="total_km" step="0.1" min="0" <?= $ro ? 'readonly' : '' ?>
                 value="<?= $tour['total_km'] !== null ? number_format((float)$tour['total_km'], 1, '.', '') : '' ?>">
        </div>
        <div class="form-group" id="normal_elev_group">
          <label>Nem magashegyi szintemelkedés (m)</label>
          <input type="number" name="total_elevation" id="total_elevation" min="0" <?= $ro ? 'readonly' : '' ?>
                 value="<?= $tour['total_elevation'] !== null ? (int)$tour['total_elevation'] : '' ?>">
        </div>
        <div class="form-group" id="alpine_km_group">
          <label>Magashegyi km (≥1500 m tszf.)</label>
          <input type="number" name="alpine_km" id="alpine_km" step="0.1" min="0" <?= $ro ? 'readonly' : '' ?>
                 value="<?= $tour['alpine_km'] !== null ? number_format((float)$tour['alpine_km'], 1, '.', '') : '' ?>">
        </div>
        <div class="form-group" id="alpine_elev_group">
          <label>Magashegyi szintemelkedés (m)</label>
          <input type="number" name="alpine_elevation" id="alpine_elevation" min="0" <?= $ro ? 'readonly' : '' ?>
                 value="<?= $tour['alpine_elevation'] !== null ? (int)$tour['alpine_elevation'] : '' ?>">
        </div>
        <div class="form-group" id="vizi_km_group">
          <label>Megtett km</label>
          <input type="number" name="vizi_km" id="vizi_total_km" step="0.1" min="0" <?= $ro ? 'readonly' : '' ?>
                 value="<?= $tour['total_km'] !== null ? number_format((float)$tour['total_km'], 1, '.', '') : '' ?>">
        </div>
        <div class="form-group" id="hours_group">
          <label id="hours_label">Túraidő (óra)</label>
          <input type="number" name="tour_hours" id="tour_hours" step="0.25" min="0" <?= $ro ? 'readonly' : '' ?>
                 value="<?= $tour['tour_hours'] !== null ? number_format((float)$tour['tour_hours'], 2, '.', '') : '' ?>">
        </div>
      </div>

      <div class="form-section-title">Pluszpontok</div>
      <div class="form-grid">
        <div class="form-group">
          <label>Többnapos típusa</label>
          <select name="multi_day_type" id="multi_day_type" <?= $ro ? 'disabled' : '' ?>>
            <option value="">— Nem többnapos / Nem megadott —</option>
            <option value="csillag" <?= ($tour['multi_day_type'] ?? '') === 'csillag' ? 'selected' : '' ?>>Csillagtúra (+1 pt/éj)</option>
            <option value="vandor"  <?= ($tour['multi_day_type'] ?? '') === 'vandor'  ? 'selected' : '' ?>>Vándortúra (+3 pt/éj)</option>
          </select>
        </div>
        <div class="form-group" id="portages_group">
          <label>Hajóátemelések száma (+3 pt/alkalom)</label>
          <input type="number" name="boat_portages" id="boat_portages" min="0" value="<?= (int)($tour['boat_portages'] ?? 0) ?>" <?= $ro ? 'readonly' : '' ?>>
        </div>
        <div class="form-group">
          <label>Eltöltött éjszakák</label>
          <input type="number" name="camping_nights_fixed" id="camping_nights_fixed" min="0" value="<?= (int)($tour['camping_nights_fixed'] ?? 0) ?>" <?= $ro ? 'readonly' : '' ?>>
          <small style="color:var(--text-muted);">Csillagtúra: +1 pt/éj · Vándortúra: +3 pt/éj</small>
        </div>
      </div>

      <div id="points-preview" style="background:var(--bg-subtle,#f5f5f5);border:1px solid var(--border);border-radius:8px;padding:14px 18px;margin:16px 0;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <span style="font-size:1.5rem;">🧮</span>
        <div>
          <div style="font-size:.75rem;color:var(--text-muted,#888);text-transform:uppercase;letter-spacing:.05em;">Számított MTSZ pontszám (tájékoztató)</div>
          <div id="points-display" style="font-size:1.5rem;font-weight:700;color:var(--primary,#2563eb);"><?= (int)$tour['mtsz_points'] ?> pont</div>
          <div id="points-note" style="font-size:.75rem;color:var(--text-muted,#888);margin-top:2px;"></div>
        </div>
        <div class="mtsz-override-box">
          <label class="mtsz-toggle">
            <input type="checkbox" name="mtsz_override_enabled" id="mtsz_override_enabled" value="1" <?= $tour['mtsz_points_override'] !== null ? 'checked' : '' ?> <?= $ro ? 'disabled' : '' ?>>
            <span>Számított érték felülírása</span>
          </label>
          <input type="number" name="mtsz_points_override" id="mtsz_points_override" class="mtsz-value" min="0"
                 value="<?= $tour['mtsz_points_override'] !== null ? (int)$tour['mtsz_points_override'] : '' ?>"
                 placeholder="Számított: <?= (int)$tour['mtsz_points'] ?> pont" <?= $ro ? 'readonly' : '' ?>>
        </div>
      </div>

      <div class="form-section-title">Pontszámok</div>
      <div class="form-grid">
        <div class="form-group">
          <label>Lizzardier pont <span style="color:var(--danger)">*</span></label>
          <input type="number" name="points" value="<?= (int)$tour['points'] ?>" min="0" <?= $ro ? 'readonly' : 'required' ?>>
          <small style="color:var(--text-muted,#888);">A klub belső rangsorához használt pont (kézzel adható meg).</small>
        </div>
      </div>

      <div class="form-section-title">GPX térképek</div>
      <?php if (!empty($gpxFiles)): ?>
        <?php foreach ($gpxFiles as $gf): ?>
        <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--bg-subtle,#f5f5f5);border:1px solid var(--border);border-radius:6px;margin-bottom:8px;font-size:13px;">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="var(--success,#16a34a)" stroke-width="2.5" style="flex-shrink:0;"><polyline points="20 6 9 17 4 12"/></svg>
          <span style="font-family:monospace;color:var(--text-muted);font-size:11px;white-space:nowrap;"><?= e($gf['filename']) ?></span>
          <?php if (!$ro): ?>
          <input type="text" name="gpx_label[<?= (int)$gf['id'] ?>]" value="<?= e($gf['label'] ?? '') ?>" placeholder="Térkép neve (pl. 1. nap útvonala)" style="flex:1;font-size:13px;">
          <label style="display:flex;align-items:center;gap:6px;cursor:pointer;color:var(--danger,#dc2626);white-space:nowrap;">
            <input type="checkbox" name="delete_gpx_ids[]" value="<?= (int)$gf['id'] ?>"> Törlés
          </label>
          <?php else: ?>
          <span style="flex:1;color:var(--text-muted);"><?= e($gf['label'] ?? '') ?></span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      <?php elseif ($ro): ?>
        <p style="color:var(--text-muted);font-size:13px;">Nincs feltöltött GPX fájl.</p>
      <?php endif; ?>
      <?php if (!$ro): ?>
      <div>
        <input type="file" name="gpx_files[]" accept=".gpx" multiple>
        <small style="display:block;margin-top:5px;color:var(--text-muted);font-size:12px;">Több .gpx fájl is kijelölhető egyszerre. Max. 1 MB/fájl.</small>
      </div>
      <?php endif; ?>

      <div class="form-section-title">Hozzárendelt tagok</div>
      <div class="member-picker">
        <?php if (!$ro): ?>
        <div class="member-picker-controls">
          <select id="member-picker-select">
            <option value="">— Válasszon tagot —</option>
            <?php foreach ($allMembers as $m): ?>
              <option value="<?= $m['id'] ?>"><?= e($m['lastname'] . ' ' . $m['firstname']) ?><?= $m['role'] === 'admin' ? ' [Admin]' : '' ?> — <?= e($m['email']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" id="member-picker-add" class="btn btn-secondary btn-sm">Hozzáad</button>
        </div>
        <?php endif; ?>
        <div id="member-picker-list" class="member-picker-list">
          <?php foreach ($assignedMembers as $m): ?>
          <div class="member-picker-item" data-member-id="<?= $m['id'] ?>">
            <span><?= e($m['lastname'] . ' ' . $m['firstname']) ?><?= $m['role'] === 'admin' ? ' [Admin]' : '' ?> — <?= e($m['email']) ?></span>
            <input type="hidden" name="member_ids[]" value="<?= $m['id'] ?>">
            <?php if (!$ro): ?>
            <button type="button" class="btn btn-danger btn-sm">Eltávolít</button>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <p id="member-picker-empty" class="member-picker-empty"
           <?= !empty($assignedMembers) ? 'style="display:none"' : '' ?>>Még nincs hozzárendelt tag.</p>
      </div>

      <?php if (!$ro): ?>
      <input type="hidden" name="send_tour_notification" id="send_tour_notification" value="">
      <?php if ($isPending): ?>
      <input type="hidden" name="approve_on_save" id="approve_on_save" value="">
      <?php endif; ?>

      <div class="flex gap-2" style="margin-top:24px;">
        <?php if ($isPending): ?>
          <button type="button" id="btn-approve-tour" class="btn btn-primary">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="width:16px;height:16px;">
              <polyline points="20 6 9 17 4 12"/>
            </svg>
            Elfogadás
          </button>
          <button type="button" id="btn-reject-tour" class="btn btn-danger">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="width:16px;height:16px;">
              <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
            Elutasítás
          </button>
          <a href="<?= BASE_URL ?>/admin/tours.php" class="btn btn-secondary">Mégse</a>
        <?php else: ?>
          <button type="button" id="btn-submit-tour" class="btn btn-primary">Változások mentése</button>
          <a href="<?= BASE_URL ?>/admin/tours.php" class="btn btn-secondary">Mégse</a>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div style="margin-top:24px;">
        <a href="<?= BASE_URL ?>/admin/tours.php" class="btn btn-secondary">← Vissza a listához</a>
      </div>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php if (!$ro && $isPending): ?>
<form id="tour-reject-form" method="post" action="<?= BASE_URL ?>/actions/tour-reject.php" style="display:none;">
  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
  <input type="hidden" name="tour_id" value="<?= $tour['id'] ?>">
</form>
<?php endif; ?>

<?php
$jsInitSubType  = json_encode($tour['sub_type']     ?? 'normal');
$jsInitTourType = json_encode($tour['tour_type']    ?? 'gyalogos');
$jsInitAccom    = json_encode($tour['accommodation'] ?? '');
?>
<script>
(function () {
  var SUBTYPES = {
    gyalogos:   [{v:'normal',       l:'Normál gyalogtúra'},
                 {v:'tajekozodasi', l:'Természetjáró tájékozódási verseny (×2 km-ráta)'}],
    kerekparos: [{v:'mout',  l:'Műúton / Kerékpárúton'},
                 {v:'terep', l:'Terepen (földúton)'}],
    vizi:       [{v:'folyasirany', l:'Folyóvízen folyásirányban'},
                 {v:'allovi',     l:'Állóvízen'},
                 {v:'szemben',    l:'Folyóvízen folyásiránnyal szemben'}],
    barlangi:   [{v:'kiepitett',   l:'Kiépített barlang (nyilvános)'},
                 {v:'kiepitetlen', l:'Nem kiépített barlang'}],
    si: [], munka: [],
  };
  var initSubType  = <?= $jsInitSubType ?>;
  var initTourType = <?= $jsInitTourType ?>;

  function v(id) { return document.getElementById(id); }

  function calcPoints() {
    var type   = v('tour_type').value;
    var sub    = v('sub_type').value;
    var days   = Math.max(1, parseInt(v('days').value) || 1);
    var date   = v('tour_date').value;
    var accom  = v('accommodation').value;
    var multiDay = v('multi_day_type').value;
    var campNights = parseInt(v('camping_nights_fixed').value) || 0;
    var port   = parseInt(v('boat_portages').value) || 0;

    var normalKmEl   = type === 'vizi' ? v('vizi_total_km').value : v('total_km').value;
    var normalElevEl = v('total_elevation').value;
    var alpineKmEl   = v('alpine_km').value;
    var alpineElevEl = v('alpine_elevation').value;
    var hoursEl      = v('tour_hours').value;

    var normalKm   = normalKmEl   !== '' ? parseFloat(normalKmEl)   : 0;
    var normalElev = normalElevEl !== '' ? parseInt(normalElevEl)   : null;
    var alpineKm   = alpineKmEl   !== '' ? parseFloat(alpineKmEl)   : 0;
    var alpineElev = alpineElevEl !== '' ? parseInt(alpineElevEl)   : null;
    var hours      = hoursEl      !== '' ? parseFloat(hoursEl)      : 0;

    var hasAlpine = alpineKm > 0 || (alpineElev !== null && alpineElev > 0);

    var normalBase = 0, alpineBase = 0;
    if (type === 'gyalogos') {
      var kr = sub === 'tajekozodasi' ? 3.0 : 1.5, er = 2.0;
      normalBase += normalKm * kr + ((normalElev !== null ? normalElev : 0) / 100) * er;
      alpineBase += alpineKm * kr + ((alpineElev !== null ? alpineElev : 0) / 100) * er;
    } else if (type === 'kerekparos') {
      var kr = sub === 'terep' ? 1.0 : 0.5, er = sub === 'terep' ? 2.0 : 1.0;
      normalBase += normalKm * kr + ((normalElev !== null ? normalElev : 0) / 100) * er;
      alpineBase += alpineKm * kr + ((alpineElev !== null ? alpineElev : 0) / 100) * er;
    } else if (type === 'vizi') {
      var vr = sub === 'szemben' ? 2.0 : (sub === 'allovi' ? 1.5 : 1.0);
      normalBase += normalKm * vr;
    } else if (type === 'si')      { normalBase += hours * 6; }
    else if (type === 'barlangi')  { normalBase += hours * (sub === 'kiepitetlen' ? 10.0 : 4.0); }
    else if (type === 'munka')     { normalBase += hours * 7; }

    var bonus = 0, notes = [];

    if (type === 'gyalogos' && !hasAlpine && normalElev !== null && normalElev <= 100) {
      bonus += days * 3; notes.push('Síkvidéki +' + (days*3) + ' pt');
    }
    if ((type === 'gyalogos' || type === 'kerekparos') && date) {
      var m = new Date(date).getMonth() + 1;
      var winter = hasAlpine ? [11,12,1,2,3] : [12,1,2];
      if (winter.indexOf(m) !== -1) { bonus += days * 3; notes.push('Téli +' + (days*3) + ' pt'); }
    }
    // Többnapos: eltöltött éjszakák alapján
    if (campNights > 0 && multiDay === 'csillag') { bonus += campNights*1; notes.push('Csillagtúra +' + campNights + ' pt'); }
    else if (campNights > 0 && multiDay === 'vandor') { bonus += campNights*3; notes.push('Vándortúra +' + (campNights*3) + ' pt'); }
    if (type === 'vizi' && port > 0) { bonus += port*3; notes.push('Hajóátemelés +' + (port*3) + ' pt'); }

    var bonusMult = (hasAlpine && (type === 'gyalogos' || type === 'kerekparos') && alpineBase >= normalBase) ? 2 : 1;
    if (hasAlpine) notes.push('Magashegyi: ' + Math.round(alpineBase) + ' pt ×2');

    var total = Math.round(normalBase + alpineBase * 2 + bonus * bonusMult);
    v('points-display').textContent = total + ' pont';

    var warn = '';
    if (days === 1 && total < 20) warn = '⚠ Egynapos túra 20 pt alatt nem minősíthető MTSZ-célra.';
    else if (days > 1 && total > 0 && Math.round(total / days) < 20) warn = '⚠ Napi átlag (' + Math.round(total/days) + ' pt/nap) nem éri el a 20 pt/nap értéket.';
    v('points-note').textContent = (notes.length ? notes.join(' · ') : '') + (warn ? (notes.length ? ' — ' : '') + warn : '');
  }

  function show(id) { var e = v(id); if (e) e.style.display = ''; }
  function hide(id) { var e = v(id); if (e) e.style.display = 'none'; }

  function updateAccomUI() {
    calcPoints();
  }

  function updateTypeUI() {
    var type = v('tour_type').value;
    var hasSub    = ['gyalogos','kerekparos','vizi','barlangi'].indexOf(type) !== -1;
    var hasKmElev = type === 'gyalogos' || type === 'kerekparos';
    var hasViziKm = type === 'vizi';
    var hasHours  = type === 'si' || type === 'barlangi' || type === 'munka';
    var hasPorts  = type === 'vizi';

    var subGroup = v('sub_type_group'), subSel = v('sub_type');
    if (hasSub) {
      subGroup.style.display = '';
      var prev = subSel.value;
      subSel.innerHTML = '';
      SUBTYPES[type].forEach(function(o) {
        var el = document.createElement('option');
        el.value = o.v; el.textContent = o.l;
        if (o.v === prev) el.selected = true;
        subSel.appendChild(el);
      });
    } else {
      subGroup.style.display = 'none';
      subSel.innerHTML = '<option value="">—</option>';
    }

    if (hasKmElev) { show('normal_km_group'); show('normal_elev_group'); show('alpine_km_group'); show('alpine_elev_group'); }
    else { hide('normal_km_group'); hide('normal_elev_group'); hide('alpine_km_group'); hide('alpine_elev_group'); }

    hasViziKm ? show('vizi_km_group') : hide('vizi_km_group');
    hasHours  ? show('hours_group')   : hide('hours_group');
    hasPorts  ? show('portages_group'): hide('portages_group');

    if (type === 'barlangi') v('hours_label').textContent = 'Bejárási idő (óra)';
    else if (type === 'si')  v('hours_label').textContent = 'Síelési idő – menetidő (óra)';
    else                     v('hours_label').textContent = 'Munka időtartama (óra)';

    calcPoints();
  }

  ['tour_type','sub_type','days','total_km','total_elevation','alpine_km','alpine_elevation',
   'tour_hours','multi_day_type','camping_nights_fixed',
   'boat_portages','tour_date','vizi_total_km'].forEach(function(id) {
    var el = v(id);
    if (!el) return;
    el.addEventListener('change', id === 'tour_type' ? updateTypeUI : calcPoints);
    if (el.tagName === 'INPUT' && el.type !== 'checkbox') el.addEventListener('input', calcPoints);
  });
  v('accommodation').addEventListener('change', updateAccomUI);

  (function() {
    var subSel = v('sub_type'), opts = SUBTYPES[initTourType] || [];
    subSel.innerHTML = '';
    opts.forEach(function(o) {
      var el = document.createElement('option');
      el.value = o.v; el.textContent = o.l;
      if (o.v === initSubType) el.selected = true;
      subSel.appendChild(el);
    });
  })();
  updateTypeUI();
  updateAccomUI();

  // Zászló előnézet
  (function() {
    var sel = document.getElementById('country_select');
    var img = document.getElementById('country_flag_preview');
    if (!sel || !img) return;
    function updateFlag() {
      var opt = sel.options[sel.selectedIndex];
      var flag = opt ? opt.getAttribute('data-flag') : '';
      if (flag) { img.src = flag; img.style.display = ''; }
      else { img.src = ''; img.style.display = 'none'; }
    }
    sel.addEventListener('change', updateFlag);
    updateFlag();
  })();
})();
</script>

<?php if (!$ro && $isPending): ?>
<script>
(function () {
  var form       = document.getElementById('tour-form');
  var approveBtn = document.getElementById('btn-approve-tour');
  var rejectBtn  = document.getElementById('btn-reject-tour');
  var rejectForm = document.getElementById('tour-reject-form');

  if (approveBtn) {
    approveBtn.addEventListener('click', function () {
      if (!form.checkValidity()) { form.reportValidity(); return; }
      document.getElementById('approve_on_save').value = '1';
      form.submit();
    });
  }

  if (rejectBtn && rejectForm) {
    rejectBtn.addEventListener('click', function () {
      if (confirm('Biztosan elutasítja ezt a túrát? A beküldés törlésre kerül.')) {
        rejectForm.submit();
      }
    });
  }
})();
</script>
<?php endif; ?>

<?php if (!$ro && !$isPending): ?>
<!-- Tour notification preview modal -->
<div class="modal-backdrop" id="tour-notification-modal">
  <div class="modal" style="max-width:660px;">
    <div class="modal-header">
      <h2>E-mail értesítő előnézete</h2>
      <button class="modal-close" type="button" data-modal-close aria-label="Bezárás">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
    <div class="modal-body" style="padding:0;">
      <div id="tour-notification-recipients-wrap" style="padding:12px 20px;background:var(--card);border-bottom:1px solid var(--border);font-size:13px;">
        <strong>Értesítést kapnak (újonnan hozzáadott tagok):</strong>
        <ul id="tour-notification-recipients" style="margin:6px 0 0 0;padding-left:20px;line-height:1.8;"></ul>
      </div>
      <div id="tour-email-preview-body" style="max-height:440px;overflow-y:auto;"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" type="button" data-modal-close>Mégse</button>
      <button class="btn btn-secondary" type="button" id="btn-tour-no-email">Mentés értesítő nélkül</button>
      <button class="btn btn-primary" type="button" id="btn-tour-send-email">Értesítők küldése és mentés</button>
    </div>
  </div>
</div>

<?php
$jsOriginalMemberIds = json_encode(array_column($assignedMembers, 'id'));
?>
<script>
(function () {
  var TOUR_TYPE_LABELS = {
    gyalogos: 'Gyalogos', kerekparos: 'Kerékpáros', vizi: 'Vízitúra',
    si: 'Síelés', barlangi: 'Barlangi', munka: 'Munkatúra'
  };

  var originalMemberIds = <?= $jsOriginalMemberIds ?>.map(String);

  function esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function buildTourNotificationPreview(firstname, tourName, countryName, tourDate, typeLabel, kmText, elevText, lizzardPts, mtszPts, tourCode) {
    return '<div style="background:#f0ebe0;padding:20px;">'
      + '<div style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 3px 16px rgba(0,0,0,.1);">'
        + '<div style="background:#1a3d39;padding:24px 32px;text-align:center;">'
          + '<div style="font-size:22px;font-weight:800;color:#F4E7CF;letter-spacing:.05em;">LIZZARD</div>'
          + '<div style="font-size:11px;color:#8fb5b2;margin-top:4px;letter-spacing:.14em;text-transform:uppercase;">Természetjáró Egyesület</div>'
        + '</div>'
        + '<div style="padding:28px 32px 20px;">'
          + '<p style="font-size:15px;color:#333;margin:0 0 8px 0;">Kedves <strong>' + esc(firstname) + '</strong>!</p>'
          + '<p style="font-size:13px;color:#555;line-height:1.7;margin:0 0 16px;">Új túrához adtak hozzá a Lizzard rendszerében!</p>'
          + '<div style="background:#f5efe4;border:1px solid #ddd5c5;border-radius:7px;padding:16px 20px;margin:0 0 14px;">'
            + '<div style="font-size:13px;font-weight:700;color:#1a3d39;margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid #ddd5c5;">' + esc(tourName) + '</div>'
            + '<table style="font-size:12px;width:100%;border-collapse:collapse;">'
              + '<tr><td style="color:#7a7269;padding:0 12px 6px 0;white-space:nowrap;">Ország</td><td style="color:#333;font-weight:600;padding-bottom:6px;">' + esc(countryName) + '</td></tr>'
              + '<tr><td style="color:#7a7269;padding:0 12px 6px 0;white-space:nowrap;">Dátum</td><td style="color:#333;font-weight:600;padding-bottom:6px;">' + esc(tourDate) + '</td></tr>'
              + '<tr><td style="color:#7a7269;padding:0 12px 6px 0;white-space:nowrap;">Típus</td><td style="color:#333;font-weight:600;padding-bottom:6px;">' + esc(typeLabel) + '</td></tr>'
              + '<tr><td style="color:#7a7269;padding:0 12px 6px 0;white-space:nowrap;">Távolság</td><td style="color:#333;font-weight:600;padding-bottom:6px;">' + esc(kmText) + '</td></tr>'
              + '<tr><td style="color:#7a7269;padding:0 12px 6px 0;white-space:nowrap;">Szintemelkedés</td><td style="color:#333;font-weight:600;padding-bottom:6px;">' + esc(elevText) + '</td></tr>'
              + '<tr><td style="color:#7a7269;padding:0 12px 0 0;white-space:nowrap;">Azonosító</td><td style="color:#333;font-family:monospace;font-weight:700;">' + esc(tourCode) + '</td></tr>'
            + '</table>'
          + '</div>'
          + '<table style="width:100%;border-collapse:collapse;margin:0 0 14px;"><tr>'
            + (lizzardPts > 0
                ? '<td style="width:50%;padding-right:5px;vertical-align:top;">'
                    + '<div style="background:#eaf3f2;border:1px solid #b8d8d5;border-radius:7px;padding:13px 16px;text-align:center;">'
                      + '<div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#5a8a87;margin-bottom:8px;">Lizzardier pont</div>'
                      + '<div style="font-size:26px;font-weight:800;color:#29776F;line-height:1;">' + lizzardPts + '</div>'
                      + '<div style="font-size:11px;font-weight:600;color:#29776F;margin-top:3px;">pont</div>'
                    + '</div>'
                  + '</td>'
                    + '<td style="width:50%;padding-left:5px;vertical-align:top;">'
                : '<td style="padding:0 70px;vertical-align:top;">'
              )
            + '<div style="background:#fef3e2;border:1px solid #fcd99a;border-radius:7px;padding:13px 16px;text-align:center;">'
              + '<div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#d97706;margin-bottom:8px;">MTSZ pont</div>'
              + '<div style="font-size:26px;font-weight:800;color:#d97706;line-height:1;">' + mtszPts + '</div>'
              + '<div style="font-size:11px;font-weight:600;color:#d97706;margin-top:3px;">pont</div>'
            + '</div>'
            + '</td>'
          + '</tr></table>'
          + '<div style="font-size:11px;color:#8a7a69;font-style:italic;margin:0 0 14px;padding:8px 12px;background:#fefcf5;border-radius:5px;border-left:3px solid #f0d060;">'
            + '&#127894; Szintlépés esetén a tag erről is értesítést kap.'
          + '</div>'
          + '<div style="text-align:center;">'
            + '<span style="display:inline-block;background:#29776F;color:#fff;font-size:12px;font-weight:700;padding:10px 24px;border-radius:7px;">Túra megtekintése a rendszerben</span>'
          + '</div>'
        + '</div>'
        + '<div style="background:#f5efe4;border-top:1px solid #ddd5c5;padding:14px 32px;text-align:center;">'
          + '<p style="font-size:11px;color:#7a7269;margin:0;">Üdvözlettel,<br><strong style="color:#1a3d39;">Lizzard Outdoor Vezetősége</strong></p>'
        + '</div>'
      + '</div>'
    + '</div>';
  }

  function getKmText(tourType) {
    if (tourType === 'vizi') {
      var km = parseFloat(document.getElementById('vizi_total_km') ? document.getElementById('vizi_total_km').value : 0) || 0;
      return km > 0 ? km.toFixed(1).replace('.', ',') + ' km' : '—';
    } else if (tourType === 'si' || tourType === 'barlangi' || tourType === 'munka') {
      var h = parseFloat(document.getElementById('tour_hours') ? document.getElementById('tour_hours').value : 0) || 0;
      return h > 0 ? h.toFixed(1).replace('.', ',') + ' óra' : '—';
    } else {
      var t = parseFloat(document.getElementById('total_km') ? document.getElementById('total_km').value : 0) || 0;
      var al = parseFloat(document.getElementById('alpine_km') ? document.getElementById('alpine_km').value : 0) || 0;
      var sum = t + al;
      return sum > 0 ? sum.toFixed(1).replace('.', ',') + ' km' : '—';
    }
  }

  function getElevText(tourType) {
    if (['si','barlangi','munka','vizi'].indexOf(tourType) !== -1) return '—';
    var e1 = parseInt(document.getElementById('total_elevation') ? document.getElementById('total_elevation').value : 0) || 0;
    var e2 = parseInt(document.getElementById('alpine_elevation') ? document.getElementById('alpine_elevation').value : 0) || 0;
    var s = e1 + e2;
    return s > 0 ? s.toLocaleString('hu-HU') + ' m' : '—';
  }

  var form      = document.getElementById('tour-form');
  var submitBtn = document.getElementById('btn-submit-tour');
  var modal     = document.getElementById('tour-notification-modal');
  var flagInput = document.getElementById('send_tour_notification');
  var recList   = document.getElementById('tour-notification-recipients');
  var prevBody  = document.getElementById('tour-email-preview-body');

  submitBtn.addEventListener('click', function () {
    if (!form.checkValidity()) { form.reportValidity(); return; }

    // Find newly added members
    var allItems = document.querySelectorAll('#member-picker-list .member-picker-item');
    var newItems = [];
    allItems.forEach(function (item) {
      if (originalMemberIds.indexOf(String(item.dataset.memberId)) === -1) {
        var span = item.querySelector('span');
        var raw  = span ? span.textContent : '';
        newItems.push(raw.split(' — ')[0].trim());
      }
    });

    if (newItems.length === 0) {
      flagInput.value = '0';
      form.submit();
      return;
    }

    var nameParts = newItems[0].split(' ');
    var firstname = nameParts.length > 1 ? nameParts[nameParts.length - 1] : nameParts[0];

    var tourName = (form.querySelector('[name="name"]') ? form.querySelector('[name="name"]').value : '').trim();
    var countryEl = document.getElementById('country_select');
    var countryText = countryEl ? (countryEl.options[countryEl.selectedIndex] ? countryEl.options[countryEl.selectedIndex].text : '') : '';
    var countryName = countryText.replace(/\s*\([A-Z]+\)\s*$/, '').trim() || '—';
    if (!tourName) tourName = countryName;

    var rawDate = form.querySelector('[name="tour_date"]') ? (form.querySelector('[name="tour_date"]').value || '') : '';
    var tourDate = rawDate ? rawDate.split('-').reverse().join('.') : '—';

    var tourTypeEl = form.querySelector('[name="tour_type"]');
    var tourType = tourTypeEl ? tourTypeEl.value : 'gyalogos';
    var typeLabel = TOUR_TYPE_LABELS[tourType] || tourType;

    var kmText   = getKmText(tourType);
    var elevText = getElevText(tourType);

    var lizzardPts = parseInt(form.querySelector('[name="points"]') ? form.querySelector('[name="points"]').value : 0) || 0;
    var ptsDom = document.getElementById('points-display');
    var mtszPts = ptsDom ? (parseInt(ptsDom.textContent) || 0) : 0;

    // Tour code from the readonly code input
    var codeVal = '<?= e($tour['tour_code'] ?? '—') ?>';

    recList.innerHTML = '';
    newItems.forEach(function (name) {
      var li = document.createElement('li');
      li.textContent = name;
      recList.appendChild(li);
    });

    prevBody.innerHTML = buildTourNotificationPreview(
      firstname, tourName, countryName, tourDate, typeLabel,
      kmText, elevText, lizzardPts, mtszPts, codeVal
    );

    modal.classList.add('open');
  });

  document.getElementById('btn-tour-send-email').addEventListener('click', function () {
    flagInput.value = '1';
    modal.classList.remove('open');
    form.submit();
  });

  document.getElementById('btn-tour-no-email').addEventListener('click', function () {
    flagInput.value = '0';
    modal.classList.remove('open');
    form.submit();
  });
})();
</script>
<?php endif; ?>

<?php if (!empty($tour['gpx_file'])): ?>
<div class="card" style="max-width:760px;margin-top:20px;">
  <div class="card-header"><h2>GPX térkép</h2></div>
  <div id="tour-map" style="height:480px;border-radius:0 0 var(--radius,8px) var(--radius,8px);overflow:hidden;"></div>
</div>
<script>
(function() {
  var _gpxUrl = <?= json_encode(GPX_URL . $tour['gpx_file']) ?>;
  var _loaded = false;

  function _initMap() {
    var map = L.map('tour-map');
    L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
      maxZoom: 17,
      attribution: 'Adatok: &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> közreműködők, <a href="http://viewfinderpanoramas.org">SRTM</a> | Térkép: &copy; <a href="https://opentopomap.org">OpenTopoMap</a> (<a href="https://creativecommons.org/licenses/by-sa/3.0/">CC-BY-SA</a>)'
    }).addTo(map);
    new L.GPX(_gpxUrl, {
      async: true,
      polyline_options: { color: '#e03030', weight: 3, opacity: 0.85 },
      marker_options: { startIconUrl: null, endIconUrl: null, shadowUrl: null }
    }).on('loaded', function(e) {
      map.fitBounds(e.target.getBounds(), { padding: [20, 20] });
    }).on('error', function() {
      document.getElementById('tour-map').innerHTML =
        '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-muted,#888);">Nem sikerült betölteni a GPX fájlt.</div>';
    }).addTo(map);
  }

  function _load() {
    if (_loaded) return;
    _loaded = true;
    var css = document.createElement('link'); css.rel = 'stylesheet';
    css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
    document.head.appendChild(css);
    var js = document.createElement('script');
    js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    js.onload = function() {
      var gpxJs = document.createElement('script');
      gpxJs.src = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet-gpx/1.7.0/gpx.min.js';
      gpxJs.onload = _initMap;
      document.head.appendChild(gpxJs);
    };
    document.head.appendChild(js);
  }

  var obs = new IntersectionObserver(function(entries) {
    if (entries[0].isIntersecting) { obs.disconnect(); _load(); }
  }, { rootMargin: '200px 0px' });

  var el = document.getElementById('tour-map');
  if (el) obs.observe(el);
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
