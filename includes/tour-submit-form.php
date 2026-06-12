<?php
/**
 * Megosztott "Túra beküldése" űrlap (MTSZ túranapló).
 *
 * Várt változók a beillesztő oldalon:
 *   $old        — korábbi (hibás) beküldés visszatöltendő mezői (array, lehet üres)
 *   $countries  — getCountries($pdo) eredménye
 *   $allMembers — a beküldőn kívüli tagok listája (id, firstname, lastname)
 *
 * Használja: BASE_URL, e(), csrfToken(), getFlagUrl()
 */
?>
<div class="card" style="max-width:760px;">
  <div class="card-body">
    <form method="post" action="<?= BASE_URL ?>/actions/tour-submit.php" id="tour-form">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

      <div class="form-section-title">Általános adatok</div>
      <div class="form-grid">
        <div class="form-group full">
          <label>Elnevezés</label>
          <input type="text" name="name" value="<?= e($old['name'] ?? '') ?>" placeholder="pl. Mátra körüljáró túra" autofocus required>
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
          <label>Tájegység <span style="color:var(--danger)">*</span></label>
          <input type="text" name="region" value="<?= e($old['region'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Dátum <span style="color:var(--danger)">*</span></label>
          <input type="date" name="tour_date" id="tour_date" value="<?= e($old['tour_date'] ?? '') ?>" required>
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

      <div class="form-section-title">További tagok hozzáadása <small style="font-weight:normal;color:var(--text-muted);">(rajtad kívül)</small></div>
      <div class="member-picker">
        <div class="member-picker-controls">
          <select id="member-picker-select">
            <option value="">— Válasszon tagot —</option>
            <?php foreach ($allMembers as $m): ?>
              <option value="<?= $m['id'] ?>"><?= e($m['lastname'] . ' ' . $m['firstname']) ?></option>
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
            <span><?= e($m['lastname'] . ' ' . $m['firstname']) ?></span>
            <input type="hidden" name="member_ids[]" value="<?= $m['id'] ?>">
            <button type="button" class="btn btn-danger btn-sm">Eltávolít</button>
          </div>
          <?php endforeach; ?>
        </div>
        <p id="member-picker-empty" class="member-picker-empty"
           <?= !empty($preIds) ? 'style="display:none"' : '' ?>>Még nincs további tag hozzáadva.</p>
      </div>
      <div class="form-grid" style="margin-top:12px;">
        <div class="form-group">
          <label>Vendég résztvevők</label>
          <input type="number" name="guest_count" min="0" value="<?= (int)($old['guest_count'] ?? 0) ?>" placeholder="0">
          <small style="color:var(--text-muted);">Nem regisztrált kísérők száma</small>
        </div>
      </div>

      <div class="form-section-title">Egyéb megjegyzés</div>
      <div class="form-grid">
        <div class="form-group full">
          <textarea name="submission_notes" rows="4" placeholder="Pl. egyéb körülmények, különleges információk..."><?= e($old['submission_notes'] ?? '') ?></textarea>
        </div>
      </div>

      <div class="flex gap-2" style="margin-top:24px;">
        <button type="submit" class="btn btn-primary">Túra beküldése jóváhagyásra</button>
        <a href="<?= BASE_URL ?>/user/tours.php" class="btn btn-secondary">Mégse</a>
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
    var campMult = (accom === 'sator') ? 2 : 1;
    if (campNights > 0 && multiDay === 'csillag') { bonus += campNights*campMult; notes.push('Csillagtúra +' + (campNights*campMult) + ' pt'); }
    else if (campNights > 0 && multiDay === 'vandor') { bonus += campNights*3*campMult; notes.push('Vándortúra +' + (campNights*3*campMult) + ' pt'); }
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

  // Member picker
  (function() {
    var sel    = document.getElementById('member-picker-select');
    var addBtn = document.getElementById('member-picker-add');
    var list   = document.getElementById('member-picker-list');
    var empty  = document.getElementById('member-picker-empty');

    function updateEmpty() {
      if (empty) empty.style.display = list.querySelectorAll('.member-picker-item').length === 0 ? '' : 'none';
    }

    addBtn.addEventListener('click', function() {
      var id   = sel.value;
      var name = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].text : '';
      if (!id) return;
      if (list.querySelector('[data-member-id="' + id + '"]')) return;

      var div = document.createElement('div');
      div.className = 'member-picker-item';
      div.setAttribute('data-member-id', id);
      div.innerHTML = '<span>' + name.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</span>'
        + '<input type="hidden" name="member_ids[]" value="' + id + '">'
        + '<button type="button" class="btn btn-danger btn-sm">Eltávolít</button>';
      div.querySelector('button').addEventListener('click', function() {
        div.remove(); updateEmpty();
      });
      list.appendChild(div);
      updateEmpty();
      sel.value = '';
    });

    list.querySelectorAll('.member-picker-item button').forEach(function(btn) {
      btn.addEventListener('click', function() {
        btn.closest('.member-picker-item').remove(); updateEmpty();
      });
    });

    updateEmpty();
  })();
})();
</script>
