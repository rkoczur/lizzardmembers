<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tours-schema.php';

$pdo = getDb();
ensureToursSchema($pdo);

// Csak a jóváhagyott (publikus) túrák kerülnek az egyesületi túranaplóba
$tours = $pdo->query("
    SELECT t.tour_code, t.name, t.country, t.tour_date, t.days,
           t.total_km, t.alpine_km, t.total_elevation, t.alpine_elevation,
           t.tour_hours, t.mtsz_points, t.guest_count,
           c.name_hu AS country_name, c.flag_filename AS country_flag,
           COUNT(tm.user_id) AS member_count
    FROM tours t
    LEFT JOIN tour_members tm ON tm.tour_id = t.id
    LEFT JOIN countries c ON c.code = t.country
    WHERE t.status = 'approved'
    GROUP BY t.id
    ORDER BY t.tour_date DESC, t.id DESC
")->fetchAll();

// Szűrőmenük forrásai (ország, év) a betöltött túrákból
$logCountries = [];
$logYears     = [];
foreach ($tours as $t) {
    $cn = $t['country_name'] ?? $t['country'];
    if ($cn) { $logCountries[$cn] = true; }
    if (!empty($t['tour_date'])) { $logYears[date('Y', strtotime($t['tour_date']))] = true; }
}
ksort($logCountries);
krsort($logYears);

$pageTitle     = 'Egyesületi túranapló';
$activePubPage = 'mtsz-turanaplo';
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap">
  <div class="pub-page-header">
    <h1>Egyesületi túranapló</h1>
    <p>Egyesületünk teljesített túráinak nyilvántartása.</p>
  </div>

  <div style="margin-bottom:22px;">
    <a href="<?= BASE_URL ?>/public/mtsz-turanaplo.php" class="btn btn-ghost btn-sm">← Vissza az MTSZ túranaplóhoz</a>
  </div>

  <?php if (empty($tours)): ?>
    <div class="pub-empty-state">
      <div style="font-size:48px;margin-bottom:12px;">🗺️</div>
      <p>Még nincs rögzített túra az egyesületi túranaplóban.</p>
    </div>
  <?php else: ?>
    <div class="pub-log-controls">
      <div class="pub-log-search">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="log-search" placeholder="Keresés kód, elnevezés vagy ország szerint…">
      </div>
      <select id="log-country">
        <option value="">Minden ország</option>
        <?php foreach (array_keys($logCountries) as $cn): ?>
          <option value="<?= e($cn) ?>"><?= e($cn) ?></option>
        <?php endforeach; ?>
      </select>
      <select id="log-year">
        <option value="">Minden év</option>
        <?php foreach (array_keys($logYears) as $yr): ?>
          <option value="<?= e($yr) ?>"><?= e($yr) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="pub-log-wrap">
      <table class="pub-log-table">
        <thead>
          <tr>
            <th class="col-code">Kód</th>
            <th>Elnevezés / Ország</th>
            <th>Dátum</th>
            <th class="right">Napok</th>
            <th class="right">Táv</th>
            <th class="right">Szintemelkedés</th>
            <th class="right">Résztvevők</th>
            <th class="right">MTSZ pont</th>
          </tr>
        </thead>
        <tbody id="log-tbody">
          <?php foreach ($tours as $t): ?>
          <?php
            $fullKm   = (float)($t['total_km'] ?? 0) + (float)($t['alpine_km'] ?? 0);
            $fullElev = (int)($t['total_elevation'] ?? 0) + (int)($t['alpine_elevation'] ?? 0);
            $attendees = (int)$t['member_count'] + (int)($t['guest_count'] ?? 0);
            $rowCountry = $t['country_name'] ?? $t['country'] ?? '';
            $rowYear    = !empty($t['tour_date']) ? date('Y', strtotime($t['tour_date'])) : '';
            $rowSearch  = mb_strtolower(trim(($t['tour_code'] ?? '') . ' ' . ($t['name'] ?? '') . ' ' . $rowCountry));
          ?>
          <tr data-country="<?= e($rowCountry) ?>" data-year="<?= e($rowYear) ?>" data-search="<?= e($rowSearch) ?>">
            <td class="col-code"><span class="pub-log-code"><?= e($t['tour_code'] ?? '—') ?></span></td>
            <td>
              <div class="pub-log-name"><?= $t['name'] ? e($t['name']) : e($t['country_name'] ?? $t['country']) ?></div>
              <div class="pub-log-country">
                <?php if (!empty($t['country_flag'])): ?>
                  <img src="<?= e(getFlagUrl($t['country_flag'])) ?>" alt="">
                <?php endif; ?>
                <?= e($t['country_name'] ?? $t['country'] ?? '—') ?>
              </div>
            </td>
            <td style="white-space:nowrap;"><?= $t['tour_date'] ? formatDate($t['tour_date']) : '—' ?></td>
            <td class="right"><?= (int)$t['days'] ?></td>
            <td class="right">
              <?php
              if ($fullKm > 0) {
                  echo number_format($fullKm, 1, ',', ' ') . ' km';
              } elseif ($t['tour_hours'] !== null) {
                  echo number_format((float)$t['tour_hours'], 1, ',', ' ') . ' óra';
              } else {
                  echo '—';
              }
              ?>
            </td>
            <td class="right"><?= $fullElev > 0 ? number_format($fullElev, 0, ',', ' ') . ' m' : '—' ?></td>
            <td class="right"><?= $attendees > 0 ? $attendees . ' fő' : '—' ?></td>
            <td class="right"><span class="pub-log-pts"><?= number_format((int)($t['mtsz_points'] ?? 0), 0, ',', ' ') ?></span></td>
          </tr>
          <?php endforeach; ?>
          <tr id="log-noresult" style="display:none;"><td colspan="8" class="pub-log-empty">Nincs a szűrésnek megfelelő túra.</td></tr>
        </tbody>
      </table>
    </div>
    <p style="margin-top:14px;font-size:13px;color:var(--text-muted);text-align:right;">
      <span id="log-count"><?= count($tours) ?></span> / <?= count($tours) ?> túra
    </p>

    <script>
    (function () {
      var search  = document.getElementById('log-search');
      var country = document.getElementById('log-country');
      var year    = document.getElementById('log-year');
      var tbody   = document.getElementById('log-tbody');
      var noRes   = document.getElementById('log-noresult');
      var countEl = document.getElementById('log-count');
      var rows    = Array.prototype.slice.call(tbody.querySelectorAll('tr[data-search]'));

      function apply() {
        var q  = (search.value || '').trim().toLowerCase();
        var c  = country.value;
        var y  = year.value;
        var shown = 0;
        rows.forEach(function (r) {
          var ok = (!q || r.getAttribute('data-search').indexOf(q) !== -1)
                && (!c || r.getAttribute('data-country') === c)
                && (!y || r.getAttribute('data-year') === y);
          r.style.display = ok ? '' : 'none';
          if (ok) shown++;
        });
        noRes.style.display = shown === 0 ? '' : 'none';
        countEl.textContent = shown;
      }

      search.addEventListener('input', apply);
      country.addEventListener('change', apply);
      year.addEventListener('change', apply);
    })();
    </script>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
