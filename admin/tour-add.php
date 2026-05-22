<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tours-schema.php';
requireAdminOrVezeto();
if (isVezeto()) {
    flash('error', 'Nincs jogosultságod ehhez a művelethez.');
    header('Location: ' . BASE_URL . '/admin/tours.php');
    exit;
}

$pdo = getDb();
ensureToursSchema($pdo);

$allMembers = $pdo->query("SELECT id, firstname, lastname, email, role FROM users ORDER BY lastname, firstname")->fetchAll();
$countries  = getCountries($pdo);

$flash_error = getFlash('error');
$old = $_SESSION['form_old'] ?? [];
unset($_SESSION['form_old']);

$pageTitle  = 'Új túra hozzáadása';
$activePage = 'tours';
include __DIR__ . '/../includes/admin-header.php';
?>

<?php if ($flash_error): ?>
  <div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="page-header">
  <div class="flex items-center gap-2">
    <a href="<?= BASE_URL ?>/admin/tours.php" class="btn btn-secondary btn-sm">← Vissza</a>
    <h1>Új túra hozzáadása</h1>
  </div>
</div>

<div class="card" style="max-width:760px;">
  <div class="card-body">
    <form method="post" action="<?= BASE_URL ?>/actions/tour-add.php" id="tour-form" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

      <div class="form-section-title">Általános adatok</div>
      <div class="form-grid">
        <div class="form-group full">
          <label>Elnevezés</label>
          <input type="text" name="name" value="<?= e($old['name'] ?? '') ?>" placeholder="pl. Mátra körüljáró túra" autofocus>
        </div>
        <div class="form-group">
          <label>Ország <span style="color:var(--danger)">*</span></label>
          <div style="display:flex;align-items:center;gap:8px;">
            <select name="country" id="country_select" required style="flex:1;">
              <option value="">— Válasszon országot —</option>
              <?php foreach ($countries as $c): ?>
              <option value="<?= e($c['code']) ?>"
                      data-flag="<?= $c['flag_filename'] ? e(getFlagUrl($c['flag_filename'])) : '' ?>"
                      <?= ($old['country'] ?? '') === $c['code'] ? 'selected' : '' ?>>
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
          <input type="text" name="region" value="<?= e($old['region'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Dátum</label>
          <input type="date" name="tour_date" id="tour_date" value="<?= e($old['tour_date'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Napok száma <span style="color:var(--danger)">*</span></label>
          <input type="number" name="days" id="days" value="<?= (int)($old['days'] ?? 1) ?>" min="1" required>
        </div>
        <div class="form-group">
          <label>Szállás típusa</label>
          <select name="accommodation" id="accommodation">
            <option value="">— Nem megadott —</option>
            <option value="sator"      <?= ($old['accommodation'] ?? '') === 'sator'      ? 'selected' : '' ?>>Sátor</option>
            <option value="turistahaz" <?= ($old['accommodation'] ?? '') === 'turistahaz' ? 'selected' : '' ?>>Túristaház</option>
            <option value="apartman"   <?= ($old['accommodation'] ?? '') === 'apartman'   ? 'selected' : '' ?>>Apartman</option>
            <option value="hotel"      <?= ($old['accommodation'] ?? '') === 'hotel'      ? 'selected' : '' ?>>Hotel</option>
          </select>
        </div>
        <div class="form-group">
          <label>Vendég résztvevők</label>
          <input type="number" name="guest_count" min="0" value="<?= (int)($old['guest_count'] ?? 0) ?>" placeholder="0">
        </div>
        <div class="form-group full">
          <label>Túra útvonala</label>
          <textarea name="route" rows="4" placeholder="pl. Eger – Felsőtárkány – Bükk-fennsík – Miskolc"><?= e($old['route'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="form-section-title">Túramód</div>
      <div class="form-grid">
        <div class="form-group">
          <label>Túramód <span style="color:var(--danger)">*</span></label>
          <select name="tour_type" id="tour_type" required>
            <option value="gyalogos"   <?= ($old['tour_type'] ?? 'gyalogos') === 'gyalogos'   ? 'selected' : '' ?>>Gyalogos</option>
            <option value="kerekparos" <?= ($old['tour_type'] ?? '') === 'kerekparos' ? 'selected' : '' ?>>Kerékpáros</option>
            <option value="vizi"       <?= ($old['tour_type'] ?? '') === 'vizi'       ? 'selected' : '' ?>>Vízitúra</option>
            <option value="si"         <?= ($old['tour_type'] ?? '') === 'si'         ? 'selected' : '' ?>>Síelés</option>
            <option value="barlangi"   <?= ($old['tour_type'] ?? '') === 'barlangi'   ? 'selected' : '' ?>>Barlangi</option>
            <option value="munka"      <?= ($old['tour_type'] ?? '') === 'munka'      ? 'selected' : '' ?>>Munkatúra</option>
          </select>
        </div>
        <div class="form-group" id="sub_type_group">
          <label>Altípus <span style="color:var(--danger)">*</span></label>
          <select name="sub_type" id="sub_type"><!-- JS tölti fel --></select>
        </div>
      </div>

      <div class="form-section-title">Teljesítmény adatok</div>
      <div class="form-grid">
        <!-- Gyalogos / Kerékpáros: nem magashegyi + magashegyi külön -->
        <div class="form-group" id="normal_km_group">
          <label id="normal_km_label">Nem magashegyi km</label>
          <input type="number" name="total_km" id="total_km" step="0.1" min="0" value="<?= e($old['total_km'] ?? '') ?>" placeholder="0.0">
        </div>
        <div class="form-group" id="normal_elev_group">
          <label>Nem magashegyi szintemelkedés (m)</label>
          <input type="number" name="total_elevation" id="total_elevation" min="0" value="<?= e($old['total_elevation'] ?? '') ?>" placeholder="0">
        </div>
        <div class="form-group" id="alpine_km_group">
          <label>Magashegyi km (≥1500 m tszf.)</label>
          <input type="number" name="alpine_km" id="alpine_km" step="0.1" min="0" value="<?= e($old['alpine_km'] ?? '') ?>" placeholder="0.0">
        </div>
        <div class="form-group" id="alpine_elev_group">
          <label>Magashegyi szintemelkedés (m)</label>
          <input type="number" name="alpine_elevation" id="alpine_elevation" min="0" value="<?= e($old['alpine_elevation'] ?? '') ?>" placeholder="0">
        </div>
        <!-- Vízitúra: csak km -->
        <div class="form-group" id="vizi_km_group">
          <label>Megtett km</label>
          <input type="number" name="vizi_km" id="vizi_total_km" step="0.1" min="0" value="<?= e($old['total_km'] ?? '') ?>" placeholder="0.0">
        </div>
        <!-- Időalapú: si, barlangi, munka -->
        <div class="form-group" id="hours_group">
          <label id="hours_label">Túraidő (óra)</label>
          <input type="number" name="tour_hours" id="tour_hours" step="0.25" min="0" value="<?= e($old['tour_hours'] ?? '') ?>" placeholder="0.0">
        </div>
      </div>

      <div class="form-section-title">Pluszpontok</div>
      <div class="form-grid">
        <div class="form-group">
          <label>Többnapos típusa</label>
          <select name="multi_day_type" id="multi_day_type">
            <option value="">— Nem többnapos / Nem megadott —</option>
            <option value="csillag" <?= ($old['multi_day_type'] ?? '') === 'csillag' ? 'selected' : '' ?>>Csillagtúra (+1 pt/éj)</option>
            <option value="vandor"  <?= ($old['multi_day_type'] ?? '') === 'vandor'  ? 'selected' : '' ?>>Vándortúra (+3 pt/éj)</option>
          </select>
        </div>
        <div class="form-group" id="portages_group">
          <label>Hajóátemelések száma (+3 pt/alkalom)</label>
          <input type="number" name="boat_portages" id="boat_portages" min="0" value="<?= (int)($old['boat_portages'] ?? 0) ?>" placeholder="0">
        </div>
        <div class="form-group">
          <label>Eltöltött éjszakák</label>
          <input type="number" name="camping_nights_fixed" id="camping_nights_fixed" min="0" value="<?= (int)($old['camping_nights_fixed'] ?? 0) ?>" placeholder="0">
          <small style="color:var(--text-muted);">Csillagtúra: +1 pt/éj · Vándortúra: +3 pt/éj</small>
        </div>
      </div>

      <div id="points-preview" style="background:var(--bg-subtle,#f5f5f5);border:1px solid var(--border);border-radius:8px;padding:14px 18px;margin:16px 0;display:flex;align-items:center;gap:12px;">
        <span style="font-size:1.5rem;">🧮</span>
        <div>
          <div style="font-size:.75rem;color:var(--text-muted,#888);text-transform:uppercase;letter-spacing:.05em;">Számított MTSZ pontszám (tájékoztató)</div>
          <div id="points-display" style="font-size:1.5rem;font-weight:700;color:var(--primary,#2563eb);">0 pont</div>
          <div id="points-note" style="font-size:.75rem;color:var(--text-muted,#888);margin-top:2px;"></div>
        </div>
      </div>

      <div class="form-section-title">GPX térkép</div>
      <div class="form-grid">
        <div class="form-group full">
          <label>GPX fájl (opcionális)</label>
          <input type="file" name="gpx_file" accept=".gpx">
          <small style="color:var(--text-muted);">Csak .gpx formátum, max. 5 MB. A térkép a túra mentése után jelenik meg.</small>
        </div>
      </div>

      <div class="form-section-title">Pontszámok</div>
      <div class="form-grid">
        <div class="form-group">
          <label>Lizzardier pont <span style="color:var(--danger)">*</span></label>
          <input type="number" name="points" value="<?= (int)($old['points'] ?? 0) ?>" min="0" required>
          <small style="color:var(--text-muted,#888);">A klub belső rangsorához használt pont (kézzel adható meg).</small>
        </div>
      </div>

      <div class="form-section-title">Hozzárendelt tagok</div>
      <div class="member-picker">
        <div class="member-picker-controls">
          <select id="member-picker-select">
            <option value="">— Válasszon tagot —</option>
            <?php foreach ($allMembers as $m): ?>
              <option value="<?= $m['id'] ?>"><?= e($m['lastname'] . ' ' . $m['firstname']) ?><?= $m['role'] === 'admin' ? ' [Admin]' : '' ?> — <?= e($m['email']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" id="member-picker-add" class="btn btn-secondary btn-sm">Hozzáad</button>
        </div>
        <div id="member-picker-list" class="member-picker-list">
          <?php
          $preIds = array_filter(array_map('intval', $old['member_ids'] ?? []));
          foreach ($allMembers as $m):
            if (!in_array((int)$m['id'], $preIds)) continue;
          ?>
          <div class="member-picker-item" data-member-id="<?= $m['id'] ?>">
            <span><?= e($m['lastname'] . ' ' . $m['firstname']) ?><?= $m['role'] === 'admin' ? ' [Admin]' : '' ?> — <?= e($m['email']) ?></span>
            <input type="hidden" name="member_ids[]" value="<?= $m['id'] ?>">
            <button type="button" class="btn btn-danger btn-sm">Eltávolít</button>
          </div>
          <?php endforeach; ?>
        </div>
        <p id="member-picker-empty" class="member-picker-empty"
           <?= !empty($preIds) ? 'style="display:none"' : '' ?>>Még nincs hozzárendelt tag.</p>
      </div>

      <input type="hidden" name="send_tour_notification" id="send_tour_notification" value="">

      <div class="flex gap-2" style="margin-top:24px;">
        <button type="button" id="btn-submit-tour" class="btn btn-primary">Túra hozzáadása</button>
        <a href="<?= BASE_URL ?>/admin/tours.php" class="btn btn-secondary">Mégse</a>
      </div>
    </form>
  </div>
</div>

<?php
$jsInitSubType   = json_encode($old['sub_type']    ?? 'normal');
$jsInitTourType  = json_encode($old['tour_type']   ?? 'gyalogos');
$jsInitAccom     = json_encode($old['accommodation'] ?? '');
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

  // ---- Kalkulátor ----
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
    } else if (type === 'si')       { normalBase += hours * 6; }
    else if (type === 'barlangi')   { normalBase += hours * (sub === 'kiepitetlen' ? 10.0 : 4.0); }
    else if (type === 'munka')      { normalBase += hours * 7; }

    var bonus = 0, notes = [];

    // Síkvidéki
    if (type === 'gyalogos' && !hasAlpine && normalElev !== null && normalElev <= 100) {
      bonus += days * 3; notes.push('Síkvidéki +' + (days*3) + ' pt');
    }
    // Téli
    if ((type === 'gyalogos' || type === 'kerekparos') && date) {
      var m = new Date(date).getMonth() + 1;
      var winter = hasAlpine ? [11,12,1,2,3] : [12,1,2];
      if (winter.indexOf(m) !== -1) { bonus += days * 3; notes.push('Téli +' + (days*3) + ' pt'); }
    }
    // Többnapos: eltöltött éjszakák alapján
    if (campNights > 0 && multiDay === 'csillag') { bonus += campNights*1; notes.push('Csillagtúra +' + campNights + ' pt'); }
    else if (campNights > 0 && multiDay === 'vandor') { bonus += campNights*3; notes.push('Vándortúra +' + (campNights*3) + ' pt'); }
    // Hajóátemelés
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

  // ---- Mező láthatóság ----
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

    // Altípus
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

    // Km/szint mezők
    if (hasKmElev) { show('normal_km_group'); show('normal_elev_group'); show('alpine_km_group'); show('alpine_elev_group'); }
    else { hide('normal_km_group'); hide('normal_elev_group'); hide('alpine_km_group'); hide('alpine_elev_group'); }

    hasViziKm ? show('vizi_km_group') : hide('vizi_km_group');
    hasHours  ? show('hours_group')   : hide('hours_group');
    hasPorts  ? show('portages_group'): hide('portages_group');

    // Cimkék
    if (type === 'barlangi') v('hours_label').textContent = 'Bejárási idő (óra)';
    else if (type === 'si')  v('hours_label').textContent = 'Síelési idő – menetidő (óra)';
    else                     v('hours_label').textContent = 'Munka időtartama (óra)';

    if (type === 'vizi') v('normal_km_label') && (v('normal_km_label').textContent = 'Megtett km');
    else if (v('normal_km_label')) v('normal_km_label').textContent = 'Nem magashegyi km';

    calcPoints();
  }

  // ---- Eseménykötések ----
  ['tour_type','sub_type','days','total_km','total_elevation','alpine_km','alpine_elevation',
   'tour_hours','multi_day_type','camping_nights_fixed',
   'boat_portages','tour_date','vizi_total_km'].forEach(function(id) {
    var el = v(id);
    if (!el) return;
    el.addEventListener('change', id === 'tour_type' ? updateTypeUI : calcPoints);
    if (el.tagName === 'INPUT' && el.type !== 'checkbox') el.addEventListener('input', calcPoints);
  });
  v('accommodation').addEventListener('change', updateAccomUI);

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

  // Init
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
})();
</script>

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
        <strong>Értesítést kapnak:</strong>
        <ul id="tour-notification-recipients" style="margin:6px 0 0 0;padding-left:20px;line-height:1.8;"></ul>
      </div>
      <div id="tour-email-preview-body" style="max-height:440px;overflow-y:auto;"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" type="button" data-modal-close>Mégse</button>
      <button class="btn btn-secondary" type="button" id="btn-tour-no-email">Hozzáadás értesítő nélkül</button>
      <button class="btn btn-primary" type="button" id="btn-tour-send-email">Értesítők küldése és hozzáadás</button>
    </div>
  </div>
</div>

<script>
(function () {
  var TOUR_TYPE_LABELS = {
    gyalogos: 'Gyalogos', kerekparos: 'Kerékpáros', vizi: 'Vízitúra',
    si: 'Síelés', barlangi: 'Barlangi', munka: 'Munkatúra'
  };

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

  var form       = document.getElementById('tour-form');
  var submitBtn  = document.getElementById('btn-submit-tour');
  var modal      = document.getElementById('tour-notification-modal');
  var flagInput  = document.getElementById('send_tour_notification');
  var recList    = document.getElementById('tour-notification-recipients');
  var prevBody   = document.getElementById('tour-email-preview-body');

  submitBtn.addEventListener('click', function () {
    if (!form.checkValidity()) { form.reportValidity(); return; }

    var items = document.querySelectorAll('#member-picker-list .member-picker-item');
    if (items.length === 0) {
      flagInput.value = '0';
      form.submit();
      return;
    }

    // Collect recipients
    var recipients = [];
    items.forEach(function (item) {
      var span = item.querySelector('span');
      var raw  = span ? span.textContent : '';
      var name = raw.split(' — ')[0].trim();
      recipients.push(name);
    });

    // Get first member's firstname for preview greeting
    var firstFullName = recipients[0] || 'Tag';
    var nameParts = firstFullName.split(' ');
    var firstname = nameParts.length > 1 ? nameParts[nameParts.length - 1] : nameParts[0];

    // Collect tour data for preview
    var tourName = (form.querySelector('[name="name"]').value || '').trim();
    var countryEl = document.getElementById('country_select');
    var countryText = countryEl ? (countryEl.options[countryEl.selectedIndex] ? countryEl.options[countryEl.selectedIndex].text : '') : '';
    var countryName = countryText.replace(/\s*\([A-Z]+\)\s*$/, '').trim() || '—';
    if (!tourName) tourName = countryName;

    var rawDate = (form.querySelector('[name="tour_date"]').value || '').trim();
    var tourDate = rawDate ? rawDate.split('-').reverse().join('.') : '—';

    var tourTypeEl = form.querySelector('[name="tour_type"]');
    var tourType = tourTypeEl ? tourTypeEl.value : 'gyalogos';
    var typeLabel = TOUR_TYPE_LABELS[tourType] || tourType;

    var kmText   = getKmText(tourType);
    var elevText = getElevText(tourType);

    var lizzardPts = parseInt(form.querySelector('[name="points"]') ? form.querySelector('[name="points"]').value : 0) || 0;
    var ptsDom = document.getElementById('points-display');
    var mtszPts = ptsDom ? (parseInt(ptsDom.textContent) || 0) : 0;

    // Build recipient list in modal
    recList.innerHTML = '';
    recipients.forEach(function (name) {
      var li = document.createElement('li');
      li.textContent = name;
      recList.appendChild(li);
    });

    prevBody.innerHTML = buildTourNotificationPreview(
      firstname, tourName, countryName, tourDate, typeLabel,
      kmText, elevText, lizzardPts, mtszPts, '—'
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

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
