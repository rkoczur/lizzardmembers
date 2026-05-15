<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tours-schema.php';
requireAdmin();

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

$allMembers = $pdo->query("SELECT id, firstname, lastname, email, role FROM users ORDER BY lastname, firstname")->fetchAll();

$assignedStmt = $pdo->prepare("SELECT u.id, u.firstname, u.lastname, u.email, u.role FROM tour_members tm JOIN users u ON u.id = tm.user_id WHERE tm.tour_id = ? ORDER BY u.lastname, u.firstname");
$assignedStmt->execute([$id]);
$assignedMembers = $assignedStmt->fetchAll();

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$pageTitle  = $tour['name'] ? e($tour['name']) : e($tour['country'] . ($tour['region'] ? ' – ' . $tour['region'] : ''));
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
  <form method="post" action="<?= BASE_URL ?>/actions/tour-delete.php"
        onsubmit="return confirmDelete('Biztosan törli ezt a túrát? A művelet nem vonható vissza.')">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="id" value="<?= $tour['id'] ?>">
    <button type="submit" class="btn btn-danger btn-sm">Túra törlése</button>
  </form>
</div>

<div class="card" style="max-width:760px;">
  <div class="card-header">
    <h2>Túra adatai</h2>
  </div>
  <div class="card-body">
    <form method="post" action="<?= BASE_URL ?>/actions/tour-update.php" id="tour-form">
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
          <input type="text" name="name" value="<?= e($tour['name'] ?? '') ?>" placeholder="pl. Mátra körüljáró túra">
        </div>
        <div class="form-group">
          <label>Ország <span style="color:var(--danger)">*</span></label>
          <input type="text" name="country" value="<?= e($tour['country']) ?>" required>
        </div>
        <div class="form-group">
          <label>Tájegység</label>
          <input type="text" name="region" value="<?= e($tour['region'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Dátum</label>
          <input type="date" name="tour_date" id="tour_date" value="<?= e($tour['tour_date'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Napok száma <span style="color:var(--danger)">*</span></label>
          <input type="number" name="days" id="days" value="<?= (int)$tour['days'] ?>" min="1" required>
        </div>
        <div class="form-group">
          <label>Szállás típusa</label>
          <select name="accommodation" id="accommodation">
            <option value="">— Nem megadott —</option>
            <option value="sator"      <?= ($tour['accommodation'] ?? '') === 'sator'      ? 'selected' : '' ?>>Sátor</option>
            <option value="turistahaz" <?= ($tour['accommodation'] ?? '') === 'turistahaz' ? 'selected' : '' ?>>Túristaház</option>
            <option value="apartman"   <?= ($tour['accommodation'] ?? '') === 'apartman'   ? 'selected' : '' ?>>Apartman</option>
            <option value="hotel"      <?= ($tour['accommodation'] ?? '') === 'hotel'      ? 'selected' : '' ?>>Hotel</option>
          </select>
        </div>
        <div class="form-group">
          <label>Vendég résztvevők</label>
          <input type="number" name="guest_count" min="0" value="<?= (int)($tour['guest_count'] ?? 0) ?>" placeholder="0">
        </div>
        <div class="form-group full">
          <label>Túra útvonala</label>
          <textarea name="route" rows="4" placeholder="pl. Eger – Felsőtárkány – Bükk-fennsík – Miskolc"><?= e($tour['route'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="form-section-title">Túramód</div>
      <div class="form-grid">
        <div class="form-group">
          <label>Túramód <span style="color:var(--danger)">*</span></label>
          <select name="tour_type" id="tour_type" required>
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
          <select name="sub_type" id="sub_type"><!-- JS tölti fel --></select>
        </div>
      </div>

      <div class="form-section-title">Teljesítmény adatok</div>
      <div class="form-grid">
        <div class="form-group" id="normal_km_group">
          <label id="normal_km_label">Nem magashegyi km</label>
          <input type="number" name="total_km" id="total_km" step="0.1" min="0"
                 value="<?= $tour['total_km'] !== null ? number_format((float)$tour['total_km'], 1, '.', '') : '' ?>">
        </div>
        <div class="form-group" id="normal_elev_group">
          <label>Nem magashegyi szintemelkedés (m)</label>
          <input type="number" name="total_elevation" id="total_elevation" min="0"
                 value="<?= $tour['total_elevation'] !== null ? (int)$tour['total_elevation'] : '' ?>">
        </div>
        <div class="form-group" id="alpine_km_group">
          <label>Magashegyi km (≥1500 m tszf.)</label>
          <input type="number" name="alpine_km" id="alpine_km" step="0.1" min="0"
                 value="<?= $tour['alpine_km'] !== null ? number_format((float)$tour['alpine_km'], 1, '.', '') : '' ?>">
        </div>
        <div class="form-group" id="alpine_elev_group">
          <label>Magashegyi szintemelkedés (m)</label>
          <input type="number" name="alpine_elevation" id="alpine_elevation" min="0"
                 value="<?= $tour['alpine_elevation'] !== null ? (int)$tour['alpine_elevation'] : '' ?>">
        </div>
        <div class="form-group" id="vizi_km_group">
          <label>Megtett km</label>
          <input type="number" name="total_km" id="vizi_total_km" step="0.1" min="0"
                 value="<?= $tour['total_km'] !== null ? number_format((float)$tour['total_km'], 1, '.', '') : '' ?>">
        </div>
        <div class="form-group" id="hours_group">
          <label id="hours_label">Túraidő (óra)</label>
          <input type="number" name="tour_hours" id="tour_hours" step="0.25" min="0"
                 value="<?= $tour['tour_hours'] !== null ? number_format((float)$tour['tour_hours'], 2, '.', '') : '' ?>">
        </div>
      </div>

      <div class="form-section-title">Pluszpontok</div>
      <div class="form-grid">
        <div class="form-group">
          <label>Többnapos típusa</label>
          <select name="multi_day_type" id="multi_day_type">
            <option value="">— Nem többnapos / Nem megadott —</option>
            <option value="csillag" <?= ($tour['multi_day_type'] ?? '') === 'csillag' ? 'selected' : '' ?>>Csillagtúra (+1 pt/éj)</option>
            <option value="vandor"  <?= ($tour['multi_day_type'] ?? '') === 'vandor'  ? 'selected' : '' ?>>Vándortúra (+3 pt/éj)</option>
          </select>
        </div>
        <div class="form-group" id="portages_group">
          <label>Hajóátemelések száma (+3 pt/alkalom)</label>
          <input type="number" name="boat_portages" id="boat_portages" min="0" value="<?= (int)($tour['boat_portages'] ?? 0) ?>">
        </div>
        <div class="form-group">
          <label>Eltöltött éjszakák</label>
          <input type="number" name="camping_nights_fixed" id="camping_nights_fixed" min="0" value="<?= (int)($tour['camping_nights_fixed'] ?? 0) ?>">
          <small style="color:var(--text-muted);">Csillagtúra: +1 pt/éj · Vándortúra: +3 pt/éj</small>
        </div>
      </div>

      <div id="points-preview" style="background:var(--bg-subtle,#f5f5f5);border:1px solid var(--border);border-radius:8px;padding:14px 18px;margin:16px 0;display:flex;align-items:center;gap:12px;">
        <span style="font-size:1.5rem;">🧮</span>
        <div>
          <div style="font-size:.75rem;color:var(--text-muted,#888);text-transform:uppercase;letter-spacing:.05em;">Számított MTSZ pontszám (tájékoztató)</div>
          <div id="points-display" style="font-size:1.5rem;font-weight:700;color:var(--primary,#2563eb);"><?= (int)$tour['mtsz_points'] ?> pont</div>
          <div id="points-note" style="font-size:.75rem;color:var(--text-muted,#888);margin-top:2px;"></div>
        </div>
      </div>

      <div class="form-section-title">Pontszámok</div>
      <div class="form-grid">
        <div class="form-group">
          <label>Lizzardier pont <span style="color:var(--danger)">*</span></label>
          <input type="number" name="points" value="<?= (int)$tour['points'] ?>" min="0" required>
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
          <?php foreach ($assignedMembers as $m): ?>
          <div class="member-picker-item" data-member-id="<?= $m['id'] ?>">
            <span><?= e($m['lastname'] . ' ' . $m['firstname']) ?><?= $m['role'] === 'admin' ? ' [Admin]' : '' ?> — <?= e($m['email']) ?></span>
            <input type="hidden" name="member_ids[]" value="<?= $m['id'] ?>">
            <button type="button" class="btn btn-danger btn-sm">Eltávolít</button>
          </div>
          <?php endforeach; ?>
        </div>
        <p id="member-picker-empty" class="member-picker-empty"
           <?= !empty($assignedMembers) ? 'style="display:none"' : '' ?>>Még nincs hozzárendelt tag.</p>
      </div>

      <div class="flex gap-2" style="margin-top:24px;">
        <button type="submit" class="btn btn-primary">Változások mentése</button>
        <a href="<?= BASE_URL ?>/admin/tours.php" class="btn btn-secondary">Mégse</a>
      </div>
    </form>
  </div>
</div>

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
})();
</script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
