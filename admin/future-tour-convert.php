<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tours-schema.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
requireAdmin();

$pdo = getDb();
ensureToursSchema($pdo);
ensureFutureToursSchema($pdo);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/admin/future-tours.php');
    exit;
}

$ftStmt = $pdo->prepare("
    SELECT ft.*, c.name_hu AS country_name
    FROM future_tours ft
    LEFT JOIN countries c ON c.code = ft.country
    WHERE ft.id = ? LIMIT 1
");
$ftStmt->execute([$id]);
$ft = $ftStmt->fetch();

if (!$ft) {
    header('Location: ' . BASE_URL . '/admin/future-tours.php');
    exit;
}

if ($ft['start_date'] >= date('Y-m-d')) {
    flash('error', 'Ez a túra még nem ért véget – konverzió csak múlt dátumú túráknál lehetséges.');
    header('Location: ' . BASE_URL . '/admin/future-tour-detail.php?id=' . $id);
    exit;
}

if ($ft['status'] === 'cancelled') {
    flash('error', 'Törölt meghirdetett túra nem konvertálható.');
    header('Location: ' . BASE_URL . '/admin/future-tour-detail.php?id=' . $id);
    exit;
}

$daysStmt = $pdo->prepare("SELECT * FROM future_tour_days WHERE future_tour_id = ? ORDER BY day_number ASC");
$daysStmt->execute([$id]);
$ftDays = $daysStmt->fetchAll();

$confirmedStmt = $pdo->prepare("
    SELECT fta.user_id, u.firstname, u.lastname, u.email
    FROM future_tour_applications fta
    JOIN users u ON u.id = fta.user_id
    WHERE fta.future_tour_id = ? AND fta.status = 'confirmed' AND fta.user_id IS NOT NULL
    ORDER BY u.lastname ASC, u.firstname ASC
");
$confirmedStmt->execute([$id]);
$confirmedMembers = $confirmedStmt->fetchAll();

$guestCountStmt = $pdo->prepare("
    SELECT COUNT(*) FROM future_tour_applications
    WHERE future_tour_id = ? AND status = 'confirmed' AND user_id IS NULL
");
$guestCountStmt->execute([$id]);
$guestCount = (int)$guestCountStmt->fetchColumn();

// Detect tour type from most common day type
$typeMap = [
    'Gyalogtúra'    => 'gyalogos',
    'Vízitúra'      => 'vizi',
    'Kerékpártúra'  => 'kerekparos',
    'Síelés'        => 'si',
    'Barlangi túra' => 'barlangi',
    'Munkavégzés'   => 'munka',
];
$typeCounts = [];
foreach ($ftDays as $day) {
    if ($day['tour_type'] && isset($typeMap[$day['tour_type']])) {
        $key = $typeMap[$day['tour_type']];
        $typeCounts[$key] = ($typeCounts[$key] ?? 0) + 1;
    }
}
$detectedType = 'gyalogos';
if ($typeCounts) {
    arsort($typeCounts);
    $detectedType = (string)array_key_first($typeCounts);
}

// Sum km/elevation from days as default
$preKm   = 0.0;
$preElev = 0;
foreach ($ftDays as $day) {
    $preKm   += (float)($day['km'] ?? 0);
    $preElev += (int)($day['elevation'] ?? 0);
}

$countries   = getCountries($pdo);
$flash_error = getFlash('error');

$pageTitle  = 'Konvertálás kész túrává';
$activePage = 'tours';
include __DIR__ . '/../includes/admin-header.php';
?>

<?php if ($flash_error): ?>
  <div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="page-header">
  <div class="flex items-center gap-2">
    <a href="<?= BASE_URL ?>/admin/future-tour-detail.php?id=<?= $id ?>" class="btn btn-secondary btn-sm">← Vissza</a>
    <h1>Konvertálás kész túrává</h1>
  </div>
</div>

<div style="background:#fffbeb;border:1px solid #f59e0b;border-radius:8px;padding:14px 20px;margin-bottom:20px;display:flex;align-items:center;gap:14px;">
  <span style="font-size:22px;flex-shrink:0;">📋</span>
  <div>
    <div style="font-weight:700;color:#92400e;font-size:15px;"><?= e($ft['name']) ?></div>
    <div style="font-size:13px;color:#b45309;margin-top:2px;">
      <?= e($ft['country_name'] ?? $ft['country']) ?>
      <?= $ft['region'] ? ' – ' . e($ft['region']) : '' ?>
      · <?= formatDate($ft['start_date']) ?>
      · <?= (int)$ft['num_days'] ?> nap
      · <?= count($confirmedMembers) ?> megerősített tag
      <?= $guestCount > 0 ? ' · ' . $guestCount . ' vendég' : '' ?>
    </div>
  </div>
</div>

<div class="card" style="max-width:760px;">
  <div class="card-body">
    <form method="post" action="<?= BASE_URL ?>/actions/future-tour-convert.php" id="tour-form">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="source_future_tour_id" value="<?= $id ?>">

      <div class="form-section-title">Általános adatok</div>
      <div class="form-grid">
        <div class="form-group full">
          <label>Elnevezés</label>
          <input type="text" name="name" value="<?= e($ft['name']) ?>" placeholder="pl. Mátra körüljáró túra" autofocus>
        </div>
        <div class="form-group">
          <label>Ország <span style="color:var(--danger)">*</span></label>
          <div style="display:flex;align-items:center;gap:8px;">
            <select name="country" id="country_select" required style="flex:1;">
              <option value="">— Válasszon országot —</option>
              <?php foreach ($countries as $c): ?>
              <option value="<?= e($c['code']) ?>"
                      data-flag="<?= $c['flag_filename'] ? e(getFlagUrl($c['flag_filename'])) : '' ?>"
                      <?= $ft['country'] === $c['code'] ? 'selected' : '' ?>>
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
          <input type="text" name="region" value="<?= e($ft['region'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Dátum</label>
          <input type="date" name="tour_date" id="tour_date" value="<?= e($ft['start_date']) ?>">
        </div>
        <div class="form-group">
          <label>Napok száma <span style="color:var(--danger)">*</span></label>
          <input type="number" name="days" id="days" value="<?= (int)$ft['num_days'] ?>" min="1" required>
        </div>
        <div class="form-group">
          <label>Szállás típusa</label>
          <select name="accommodation" id="accommodation">
            <option value="">— Nem megadott —</option>
            <option value="sator">Sátor</option>
            <option value="turistahaz">Túristaház</option>
            <option value="apartman">Apartman</option>
            <option value="hotel">Hotel</option>
          </select>
          <?php if ($ft['accommodation']): ?>
            <small style="color:var(--text-muted);">Meghirdetett túrán: <?= e(mb_strimwidth($ft['accommodation'], 0, 80, '…')) ?></small>
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label>Vendég résztvevők</label>
          <input type="number" name="guest_count" min="0" value="<?= $guestCount ?>" placeholder="0">
        </div>
        <div class="form-group full">
          <label>Túra útvonala / leírás</label>
          <textarea name="route" rows="4" placeholder="pl. Eger – Felsőtárkány – Bükk-fennsík – Miskolc"><?= e($ft['description'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="form-section-title">Túramód</div>
      <div class="form-grid">
        <div class="form-group">
          <label>Túramód <span style="color:var(--danger)">*</span></label>
          <select name="tour_type" id="tour_type" required>
            <option value="gyalogos"   <?= $detectedType === 'gyalogos'   ? 'selected' : '' ?>>Gyalogos</option>
            <option value="kerekparos" <?= $detectedType === 'kerekparos' ? 'selected' : '' ?>>Kerékpáros</option>
            <option value="vizi"       <?= $detectedType === 'vizi'       ? 'selected' : '' ?>>Vízitúra</option>
            <option value="si"         <?= $detectedType === 'si'         ? 'selected' : '' ?>>Síelés</option>
            <option value="barlangi"   <?= $detectedType === 'barlangi'   ? 'selected' : '' ?>>Barlangi</option>
            <option value="munka"      <?= $detectedType === 'munka'      ? 'selected' : '' ?>>Munkatúra</option>
          </select>
        </div>
        <div class="form-group" id="sub_type_group">
          <label>Altípus <span style="color:var(--danger)">*</span></label>
          <select name="sub_type" id="sub_type"><!-- JS fills --></select>
        </div>
      </div>

      <div class="form-section-title">Teljesítmény adatok</div>
      <div class="form-grid">
        <div class="form-group" id="normal_km_group">
          <label id="normal_km_label">Nem magashegyi km</label>
          <input type="number" name="total_km" id="total_km" step="0.1" min="0"
                 value="<?= $preKm > 0 ? number_format($preKm, 1, '.', '') : '' ?>" placeholder="0.0">
        </div>
        <div class="form-group" id="normal_elev_group">
          <label>Nem magashegyi szintemelkedés (m)</label>
          <input type="number" name="total_elevation" id="total_elevation" min="0"
                 value="<?= $preElev > 0 ? $preElev : '' ?>" placeholder="0">
        </div>
        <div class="form-group" id="alpine_km_group">
          <label>Magashegyi km (≥1500 m tszf.)</label>
          <input type="number" name="alpine_km" id="alpine_km" step="0.1" min="0" value="" placeholder="0.0">
        </div>
        <div class="form-group" id="alpine_elev_group">
          <label>Magashegyi szintemelkedés (m)</label>
          <input type="number" name="alpine_elevation" id="alpine_elevation" min="0" value="" placeholder="0">
        </div>
        <div class="form-group" id="vizi_km_group">
          <label>Megtett km</label>
          <input type="number" name="vizi_km" id="vizi_total_km" step="0.1" min="0"
                 value="<?= $preKm > 0 ? number_format($preKm, 1, '.', '') : '' ?>" placeholder="0.0">
        </div>
        <div class="form-group" id="hours_group">
          <label id="hours_label">Túraidő (óra)</label>
          <input type="number" name="tour_hours" id="tour_hours" step="0.25" min="0" value="" placeholder="0.0">
        </div>
      </div>

      <div class="form-section-title">Pluszpontok</div>
      <div class="form-grid">
        <div class="form-group">
          <label>Többnapos típusa</label>
          <select name="multi_day_type" id="multi_day_type">
            <option value="">— Nem többnapos / Nem megadott —</option>
            <option value="csillag">Csillagtúra (+1 pt/éj)</option>
            <option value="vandor">Vándortúra (+3 pt/éj)</option>
          </select>
        </div>
        <div class="form-group" id="portages_group">
          <label>Hajóátemelések száma (+3 pt/alkalom)</label>
          <input type="number" name="boat_portages" id="boat_portages" min="0" value="0" placeholder="0">
        </div>
        <div class="form-group">
          <label>Eltöltött éjszakák</label>
          <input type="number" name="camping_nights_fixed" id="camping_nights_fixed" min="0" value="0" placeholder="0">
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

      <div class="form-section-title">Pontszámok</div>
      <div class="form-grid">
        <div class="form-group">
          <label>Lizzardier pont <span style="color:var(--danger)">*</span></label>
          <input type="number" name="points" value="0" min="0" required>
          <small style="color:var(--text-muted);">A klub belső rangsorához használt pont (kézzel adható meg).</small>
        </div>
      </div>

      <div class="form-section-title">Résztvevők (megerősített tagok)</div>
      <?php if (empty($confirmedMembers)): ?>
        <p style="color:var(--text-muted);font-size:13px;margin-bottom:16px;">Nincs megerősített tag a meghirdetett túrán.</p>
      <?php else: ?>
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:10px;">
          Az alábbi tagok megerősített státuszban vannak a meghirdetett túrán. Jelöld be, kiket adj hozzá a kész túrához:
        </p>
        <div style="border:1px solid var(--border);border-radius:8px;overflow:hidden;margin-bottom:16px;">
          <div style="padding:7px 12px;background:var(--bg-subtle,#f5f5f5);border-bottom:1px solid var(--border);display:flex;gap:8px;">
            <button type="button" onclick="toggleAllMembers(true)" class="btn btn-ghost btn-sm" style="font-size:11px;padding:2px 8px;">Összes kijelöl</button>
            <button type="button" onclick="toggleAllMembers(false)" class="btn btn-ghost btn-sm" style="font-size:11px;padding:2px 8px;">Összes töröl</button>
          </div>
          <?php foreach ($confirmedMembers as $m): ?>
          <label style="display:flex;align-items:center;gap:10px;padding:9px 14px;border-bottom:1px solid var(--border);cursor:pointer;">
            <input type="checkbox" name="member_ids[]" value="<?= (int)$m['user_id'] ?>" checked style="width:16px;height:16px;flex-shrink:0;">
            <span style="font-weight:600;"><?= e($m['lastname'] . ' ' . $m['firstname']) ?></span>
            <span style="font-size:12px;color:var(--text-muted);"><?= e($m['email']) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div style="background:var(--bg-subtle,#f5f5f5);border:1px solid var(--border);border-radius:8px;padding:14px 18px;margin-bottom:24px;">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
          <input type="checkbox" name="close_future_tour" value="1" checked style="width:16px;height:16px;flex-shrink:0;">
          <div>
            <div style="font-weight:600;font-size:14px;">Meghirdetett túra lezárása</div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">A meghirdetett túra státuszát „Lezárt"-ra állítja a konverzió után.</div>
          </div>
        </label>
      </div>

      <div class="flex gap-2">
        <button type="submit" class="btn btn-primary">Kész túraként rögzítés</button>
        <a href="<?= BASE_URL ?>/admin/future-tour-detail.php?id=<?= $id ?>" class="btn btn-secondary">Mégse</a>
      </div>
    </form>
  </div>
</div>

<?php
$jsInitTourType = json_encode($detectedType);
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
  var initTourType = <?= $jsInitTourType ?>;

  function v(id) { return document.getElementById(id); }

  function calcPoints() {
    var type       = v('tour_type').value;
    var sub        = v('sub_type').value;
    var days       = Math.max(1, parseInt(v('days').value) || 1);
    var date       = v('tour_date').value;
    var multiDay   = v('multi_day_type').value;
    var campNights = parseInt(v('camping_nights_fixed').value) || 0;
    var port       = parseInt(v('boat_portages').value) || 0;

    var normalKm   = (type === 'vizi' ? v('vizi_total_km').value : v('total_km').value);
    normalKm       = normalKm !== '' ? parseFloat(normalKm) : 0;
    var normalElev = v('total_elevation').value !== '' ? parseInt(v('total_elevation').value) : null;
    var alpineKm   = v('alpine_km').value !== '' ? parseFloat(v('alpine_km').value) : 0;
    var alpineElev = v('alpine_elevation').value !== '' ? parseInt(v('alpine_elevation').value) : null;
    var hours      = v('tour_hours').value !== '' ? parseFloat(v('tour_hours').value) : 0;

    var hasAlpine  = alpineKm > 0 || (alpineElev !== null && alpineElev > 0);
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
    } else if (type === 'si')     { normalBase += hours * 6; }
    else if (type === 'barlangi') { normalBase += hours * (sub === 'kiepitetlen' ? 10.0 : 4.0); }
    else if (type === 'munka')    { normalBase += hours * 7; }

    var bonus = 0, notes = [];
    if (type === 'gyalogos' && !hasAlpine && normalElev !== null && normalElev <= 100) {
      bonus += days * 3; notes.push('Síkvidéki +' + (days * 3) + ' pt');
    }
    if ((type === 'gyalogos' || type === 'kerekparos') && date) {
      var m = new Date(date).getMonth() + 1;
      var winter = hasAlpine ? [11,12,1,2,3] : [12,1,2];
      if (winter.indexOf(m) !== -1) { bonus += days * 3; notes.push('Téli +' + (days * 3) + ' pt'); }
    }
    var campMult = (v('accommodation').value === 'sator') ? 2 : 1;
    if (campNights > 0 && multiDay === 'csillag') { bonus += campNights*campMult; notes.push('Csillagtúra +' + (campNights*campMult) + ' pt'); }
    else if (campNights > 0 && multiDay === 'vandor') { bonus += campNights*3*campMult; notes.push('Vándortúra +' + (campNights*3*campMult) + ' pt'); }
    if (type === 'vizi' && port > 0) { bonus += port * 3; notes.push('Hajóátemelés +' + (port * 3) + ' pt'); }

    var bonusMult = (hasAlpine && (type === 'gyalogos' || type === 'kerekparos') && alpineBase >= normalBase) ? 2 : 1;
    if (hasAlpine) notes.push('Magashegyi: ' + Math.round(alpineBase) + ' pt ×2');

    var total = Math.round(normalBase + alpineBase * 2 + bonus * bonusMult);
    v('points-display').textContent = total + ' pont';

    var warn = '';
    if (days === 1 && total < 20) warn = '⚠ Egynapos túra 20 pt alatt nem minősíthető MTSZ-célra.';
    else if (days > 1 && total > 0 && Math.round(total / days) < 20) warn = '⚠ Napi átlag (' + Math.round(total / days) + ' pt/nap) nem éri el a 20 pt/nap értéket.';
    v('points-note').textContent = (notes.length ? notes.join(' · ') : '') + (warn ? (notes.length ? ' — ' : '') + warn : '');
  }

  function show(id) { var e = v(id); if (e) e.style.display = ''; }
  function hide(id) { var e = v(id); if (e) e.style.display = 'none'; }

  function updateTypeUI() {
    var type      = v('tour_type').value;
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
    else           { hide('normal_km_group'); hide('normal_elev_group'); hide('alpine_km_group'); hide('alpine_elev_group'); }
    hasViziKm ? show('vizi_km_group')  : hide('vizi_km_group');
    hasHours  ? show('hours_group')    : hide('hours_group');
    hasPorts  ? show('portages_group') : hide('portages_group');

    if (type === 'barlangi')      v('hours_label').textContent = 'Bejárási idő (óra)';
    else if (type === 'si')       v('hours_label').textContent = 'Síelési idő – menetidő (óra)';
    else                          v('hours_label').textContent = 'Munka időtartama (óra)';
    if (v('normal_km_label'))     v('normal_km_label').textContent = type === 'vizi' ? 'Megtett km' : 'Nem magashegyi km';

    calcPoints();
  }

  ['tour_type','sub_type','days','total_km','total_elevation','alpine_km','alpine_elevation',
   'tour_hours','multi_day_type','camping_nights_fixed','boat_portages','tour_date','vizi_total_km'].forEach(function(id) {
    var el = v(id);
    if (!el) return;
    el.addEventListener('change', id === 'tour_type' ? updateTypeUI : calcPoints);
    if (el.tagName === 'INPUT' && el.type !== 'checkbox') el.addEventListener('input', calcPoints);
  });
  v('accommodation').addEventListener('change', calcPoints);

  // Flag preview
  (function() {
    var sel = document.getElementById('country_select');
    var img = document.getElementById('country_flag_preview');
    if (!sel || !img) return;
    function updateFlag() {
      var opt  = sel.options[sel.selectedIndex];
      var flag = opt ? opt.getAttribute('data-flag') : '';
      if (flag) { img.src = flag; img.style.display = ''; }
      else      { img.src = ''; img.style.display = 'none'; }
    }
    sel.addEventListener('change', updateFlag);
    updateFlag();
  })();

  // Init subtypes
  (function() {
    var subSel = v('sub_type'), opts = SUBTYPES[initTourType] || [];
    subSel.innerHTML = '';
    opts.forEach(function(o) {
      var el = document.createElement('option');
      el.value = o.v; el.textContent = o.l;
      subSel.appendChild(el);
    });
  })();
  updateTypeUI();
})();

function toggleAllMembers(checked) {
  document.querySelectorAll('input[name="member_ids[]"]').forEach(function(cb) { cb.checked = checked; });
}
</script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
