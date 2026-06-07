<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
requireAdminOrVezeto();
$ro = false;

$pdo = getDb();
ensureFutureToursSchema($pdo);

$isNew = isset($_GET['new']);
$id    = $isNew ? 0 : (int)($_GET['id'] ?? 0);

if (!$isNew && !$id) {
    header('Location: ' . BASE_URL . '/admin/future-tours.php');
    exit;
}

$tour         = null;
$days         = [];
$customFields = [];

if (!$isNew) {
    $tour = $pdo->prepare("SELECT ft.*, c.name_hu AS country_name FROM future_tours ft LEFT JOIN countries c ON c.code = ft.country WHERE ft.id = ? LIMIT 1");
    $tour->execute([$id]);
    $tour = $tour->fetch();
    if (!$tour) {
        header('Location: ' . BASE_URL . '/admin/future-tours.php');
        exit;
    }

    $days = $pdo->prepare("SELECT * FROM future_tour_days WHERE future_tour_id = ? ORDER BY day_number ASC");
    $days->execute([$id]);
    $days = $days->fetchAll();

    $customFields = $pdo->prepare("SELECT * FROM future_tour_custom_fields WHERE future_tour_id = ? ORDER BY sort_order ASC, id ASC");
    $customFields->execute([$id]);
    $customFields = $customFields->fetchAll();
}

$countries = getCountries($pdo);

$gpxFiles = [];
if (!$isNew) {
    $gpxFilesStmt = $pdo->prepare("SELECT * FROM future_tour_gpx_files WHERE future_tour_id = ? ORDER BY sort_order ASC, uploaded_at ASC");
    $gpxFilesStmt->execute([$id]);
    $gpxFiles = $gpxFilesStmt->fetchAll();
}

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$pageTitle  = $isNew ? 'Új meghirdetett túra' : ($tour['name'] ?? 'Meghirdetett túra');
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
    <a href="<?= BASE_URL ?>/admin/future-tours.php" class="btn btn-secondary btn-sm">← Vissza</a>
    <h1><?= $isNew ? 'Új Meghirdetett Túra' : e($tour['name']) ?></h1>
  </div>
  <div style="display:flex;gap:8px;align-items:center;">
    <?php if (!$isNew): ?>
    <a href="<?= BASE_URL ?>/admin/future-tour-applicants.php?id=<?= (int)$id ?>" class="btn btn-secondary btn-sm">Jelentkezők</a>
    <button type="button" class="btn btn-secondary btn-sm" id="copy-public-link-btn"
            data-url="<?= e(((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL . '/user/future-tour-apply-public.php?id=' . (int)$id) ?>">
      Publikus link másolása
    </button>
    <?php endif; ?>
    <?php if (!$isNew && isAdmin() && $tour['start_date'] < date('Y-m-d') && $tour['status'] !== 'cancelled'): ?>
    <a href="<?= BASE_URL ?>/admin/future-tour-convert.php?id=<?= (int)$id ?>" class="btn btn-primary btn-sm">
      Konvertálás kész túrává
    </a>
    <?php endif; ?>
    <?php if (!$isNew && !$ro && isAdmin()): ?>
    <form method="post" action="<?= BASE_URL ?>/actions/future-tour-delete.php"
          onsubmit="return confirmDelete('Biztosan törli ezt a meghirdetett túrát? A művelet nem vonható vissza.')"
          style="display:flex;margin:0;">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <button type="submit" class="btn btn-danger btn-sm">Törlés</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<div class="card" style="max-width:780px;">
    <div class="card-header">
      <h2><?= $isNew ? 'Túra adatai' : 'Túra szerkesztése' ?></h2>
    </div>
    <div class="card-body">
      <form method="post" enctype="multipart/form-data" action="<?= BASE_URL ?>/actions/<?= $isNew ? 'future-tour-add' : 'future-tour-update' ?>.php" id="future-tour-form">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <?php if (!$isNew): ?>
          <input type="hidden" name="id" value="<?= (int)$id ?>">
        <?php endif; ?>

        <div class="form-section-title">Általános adatok</div>
        <div class="form-grid">
          <div class="form-group full">
            <label>Túra neve <span style="color:var(--danger)">*</span></label>
            <input type="text" name="name" value="<?= e($tour['name'] ?? '') ?>" required <?= $ro ? 'readonly' : '' ?>>
          </div>
          <div class="form-group full">
            <label>Leírás</label>
            <textarea name="description" rows="4" <?= $ro ? 'readonly' : '' ?>><?= e($tour['description'] ?? '') ?></textarea>
          </div>
          <div class="form-group full">
            <label>Rövid leírás <small style="font-weight:400;color:var(--text-muted);">(túrakártyán látható)</small></label>
            <input type="text" name="short_intro" maxlength="200" value="<?= e($tour['short_intro'] ?? '') ?>" placeholder="pl. Háromnapos gyalogtúra a Bükkben, közepes nehézséggel." <?= $ro ? 'readonly' : '' ?>>
          </div>
          <div class="form-group">
            <label>Kezdés dátuma <span style="color:var(--danger)">*</span></label>
            <input type="date" name="start_date" value="<?= e($tour['start_date'] ?? '') ?>" required <?= $ro ? 'readonly' : '' ?>>
          </div>
          <div class="form-group">
            <label>Max. résztvevők <span style="color:var(--danger)">*</span></label>
            <input type="number" name="max_attendees" min="1" value="<?= (int)($tour['max_attendees'] ?? 10) ?>" required <?= $ro ? 'readonly' : '' ?>>
          </div>
          <div class="form-group">
            <label>Részvételi díj (Ft)</label>
            <input type="number" name="participation_fee" min="0" step="1" value="<?= $tour['participation_fee'] !== null ? (int)$tour['participation_fee'] : '' ?>" placeholder="pl. 15000" <?= $ro ? 'readonly' : '' ?>>
          </div>
          <div class="form-group">
            <label>Szerezhető Lizzardier pont</label>
            <input type="number" name="lizzardier_points" min="0" step="1" value="<?= $tour['lizzardier_points'] !== null ? (int)$tour['lizzardier_points'] : '' ?>" placeholder="pl. 5" <?= $ro ? 'readonly' : '' ?>>
            <small style="display:block;margin-top:4px;color:var(--text-muted);font-size:12px;">A pont csak a túra teljesítése után kerül jóváírásra.</small>
          </div>
          <div class="form-group">
            <label>Státusz</label>
            <select name="status" <?= $ro ? 'disabled' : '' ?>>
              <option value="open"      <?= ($tour['status'] ?? 'open') === 'open'      ? 'selected' : '' ?>>Nyitott – lehet jelentkezni</option>
              <option value="closed"    <?= ($tour['status'] ?? 'open') === 'closed'    ? 'selected' : '' ?>>Lezárt</option>
              <option value="cancelled" <?= ($tour['status'] ?? 'open') === 'cancelled' ? 'selected' : '' ?>>Törölve</option>
            </select>
          </div>
          <div class="form-group full">
            <label style="display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:normal;<?= $ro ? 'opacity:.6;pointer-events:none;' : '' ?>">
              <input type="checkbox" name="requires_membership" value="1"
                     <?= !empty($tour['requires_membership']) ? 'checked' : '' ?>
                     <?= $ro ? 'disabled' : '' ?>>
              <span>
                <strong>Csak tagoknak szóló túra</strong>
                <small style="display:block;font-weight:normal;color:var(--text-muted);margin-top:3px;">Ha aktív, nem bejelentkezett látogatóknak a vendégjelentkezés helyett tagfelvételi kérelem jelenik meg.</small>
              </span>
            </label>
          </div>
          <div class="form-group">
            <label>Ország</label>
            <select name="country" <?= $ro ? 'disabled' : '' ?>>
              <option value="">— válassz —</option>
              <?php foreach ($countries as $c): ?>
                <option value="<?= e($c['code']) ?>" <?= ($tour['country'] ?? '') === $c['code'] ? 'selected' : '' ?>>
                  <?= e($c['name_hu']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Tájegység</label>
            <input type="text" name="region" value="<?= e($tour['region'] ?? '') ?>" <?= $ro ? 'readonly' : '' ?>>
          </div>
          <div class="form-group full">
            <label>Szállás</label>
            <textarea name="accommodation" rows="3" <?= $ro ? 'readonly' : '' ?>><?= e($tour['accommodation'] ?? '') ?></textarea>
          </div>
          <div class="form-group full">
            <label>Utazás</label>
            <textarea name="travel" rows="3" <?= $ro ? 'readonly' : '' ?>><?= e($tour['travel'] ?? '') ?></textarea>
          </div>
          <div class="form-group full">
            <label>Felszerelés</label>
            <textarea name="equipment" rows="3" <?= $ro ? 'readonly' : '' ?>><?= e($tour['equipment'] ?? '') ?></textarea>
          </div>
          <div class="form-group full">
            <label>Szükséges tapasztalat és erőnlét</label>
            <textarea name="experience" rows="3" <?= $ro ? 'readonly' : '' ?>><?= e($tour['experience'] ?? '') ?></textarea>
          </div>
        </div>

        <!-- Cover image -->
        <div class="form-section-title" style="margin-top:24px;">Borítókép</div>
        <div class="form-group">
          <?php if (!$isNew && !empty($tour['cover_img'])): ?>
            <div style="margin-bottom:12px;">
              <img src="<?= BASE_URL ?>/assets/uploads/tour-covers/<?= e($tour['cover_img']) ?>" style="max-width:300px;height:200px;object-fit:cover;border-radius:8px;display:block;">
            </div>
            <?php if (!$ro): ?>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;text-transform:none;letter-spacing:0;font-weight:600;margin-bottom:10px;">
              <input type="checkbox" name="delete_cover_img" value="1">
              Borítókép törlése
            </label>
            <?php endif; ?>
          <?php endif; ?>
          <?php if (!$ro): ?>
            <label>Kép feltöltése</label>
            <input type="file" name="cover_img" accept="image/jpeg,image/png,image/webp">
            <small style="color:var(--text-muted);font-size:12px;">JPG, PNG vagy WebP; max. 5 MB. Megjelenítve: 300×200 px (automatikus vágás).</small>
          <?php endif; ?>
        </div>

        <!-- DAYS -->
        <div class="form-section-title" style="margin-top:24px;">
          Napok
          <?php if (!$ro): ?>
            <button type="button" id="add-day-btn" class="btn btn-ghost btn-sm" style="margin-left:12px;font-size:12px;">+ Nap hozzáadása</button>
          <?php endif; ?>
        </div>
        <div id="days-container">
          <?php if (!empty($days)): ?>
            <?php foreach ($days as $i => $day): ?>
            <div class="day-row">
              <?php if (!$ro): ?>
                <button type="button" class="remove-day-btn btn-remove" style="position:absolute;top:8px;right:10px;" title="Nap törlése">✕</button>
              <?php endif; ?>
              <input type="hidden" name="day_id[]" value="<?= (int)$day['id'] ?>">
              <div class="day-row-grid">
                <div class="form-group">
                  <label><?= (int)$day['day_number'] ?>. nap</label>
                  <input type="hidden" name="day_number[]" value="<?= (int)$day['day_number'] ?>">
                </div>
                <div class="form-group">
                  <label>Túratípus</label>
                  <select name="day_tour_type[]" <?= $ro ? 'disabled' : '' ?>>
                    <option value="">—</option>
                    <?php foreach (['Gyalogtúra','Vízitúra','Kerékpártúra','Síelés','Barlangi túra','Munkavégzés'] as $tt): ?>
                      <option value="<?= e($tt) ?>" <?= ($day['tour_type'] ?? '') === $tt ? 'selected' : '' ?>><?= e($tt) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label>Km</label>
                  <input type="number" step="0.1" min="0" name="day_km[]" value="<?= e($day['km'] ?? '') ?>" <?= $ro ? 'readonly' : '' ?>>
                </div>
                <div class="form-group">
                  <label>Szintemelkedés (m)</label>
                  <input type="number" min="0" name="day_elevation[]" value="<?= e($day['elevation'] ?? '') ?>" <?= $ro ? 'readonly' : '' ?>>
                </div>
                <div class="form-group full" style="grid-column:1/-1;">
                  <label>Leírás</label>
                  <input type="text" name="day_description[]" value="<?= e($day['description'] ?? '') ?>" <?= $ro ? 'readonly' : '' ?>>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php elseif ($isNew): ?>
            <!-- one empty day row for new tours -->
            <div class="day-row">
              <button type="button" class="remove-day-btn btn-remove" style="position:absolute;top:8px;right:10px;" title="Nap törlése">✕</button>
              <input type="hidden" name="day_id[]" value="0">
              <div class="day-row-grid">
                <div class="form-group">
                  <label>1. nap</label>
                  <input type="hidden" name="day_number[]" value="1">
                </div>
                <div class="form-group">
                  <label>Túratípus</label>
                  <select name="day_tour_type[]">
                    <option value="">—</option>
                    <?php foreach (['Gyalogtúra','Vízitúra','Kerékpártúra','Síelés','Barlangi túra','Munkavégzés'] as $tt): ?>
                      <option value="<?= e($tt) ?>"><?= e($tt) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label>Km</label>
                  <input type="number" step="0.1" min="0" name="day_km[]">
                </div>
                <div class="form-group">
                  <label>Szintemelkedés (m)</label>
                  <input type="number" min="0" name="day_elevation[]">
                </div>
                <div class="form-group full" style="grid-column:1/-1;">
                  <label>Leírás</label>
                  <input type="text" name="day_description[]">
                </div>
              </div>
            </div>
          <?php endif; ?>
          <?php if (empty($days) && !$isNew): ?>
            <p style="color:var(--text-muted);font-size:13px;">Még nincs nap hozzáadva.</p>
          <?php endif; ?>
        </div>

        <?php
        $disabledFields  = json_decode((!$isNew && isset($tour['disabled_standard_fields']) ? $tour['disabled_standard_fields'] : null) ?? '[]', true) ?: [];
        $isFieldDisabled = fn(string $f): bool => in_array($f, $disabledFields, true);
        ?>

        <!-- STANDARD FIELD VISIBILITY -->
        <div class="form-section-title" style="margin-top:24px;">Jelentkezési mezők</div>
        <p style="font-size:13px;color:var(--text-muted);margin:0 0 12px;">Kapcsold ki azokat a mezőket, amelyek ennél a túránál nem relevánsak.</p>
        <?php
        $stdFields = [
          'departure_city' => ['label' => 'Honnan indulnál?',          'desc' => 'Indulási hely — közlekedés megszervezéséhez'],
          'car_available'  => ['label' => 'Autóval jönni?',             'desc' => 'Megkérdi az autót és a szabad ülőhelyeket'],
          'sharing_room'   => ['label' => 'Szobamegosztás',             'desc' => 'Szobatárs-preferencia kérdése szállás esetén'],
          'notes'          => ['label' => 'Megjegyzések',               'desc' => 'Szabad szöveges megjegyzés a jelentkezőtől'],
        ];
        ?>
        <div class="notif-list" style="border:1px solid var(--border);border-radius:var(--radius,8px);overflow:hidden;<?= $ro ? 'opacity:.6;pointer-events:none;' : '' ?>">
          <?php foreach ($stdFields as $fKey => $field): ?>
          <label class="notif-row" style="padding:12px 16px;cursor:pointer;margin:0;">
            <input type="checkbox" name="visible_fields[]" value="<?= e($fKey) ?>"
                   <?= !$isFieldDisabled($fKey) ? 'checked' : '' ?>
                   <?= $ro ? 'disabled' : '' ?>>
            <span class="notif-slider"></span>
            <span class="notif-info">
              <strong><?= e($field['label']) ?></strong>
              <small><?= e($field['desc']) ?></small>
            </span>
          </label>
          <?php endforeach; ?>
        </div>

        <!-- CUSTOM FIELDS -->
        <div class="form-section-title" style="margin-top:24px;">
          Egyedi jelentkezési mezők
          <?php if (!$ro): ?>
            <button type="button" id="add-field-btn" class="btn btn-ghost btn-sm" style="margin-left:12px;font-size:12px;">+ Mező hozzáadása</button>
          <?php endif; ?>
        </div>
        <div id="fields-container">
          <?php if (!empty($customFields)): ?>
            <?php foreach ($customFields as $cf): ?>
            <div class="field-row">
              <input type="hidden" name="field_id[]" value="<?= (int)$cf['id'] ?>">
              <div style="display:flex;gap:10px;align-items:center;">
                <input type="text" name="field_name[]" value="<?= e($cf['field_name']) ?>" placeholder="Mező neve" style="flex:1;" <?= $ro ? 'readonly' : '' ?>>
                <select name="field_type[]" style="width:160px;" onchange="toggleFieldOptions(this)" <?= $ro ? 'disabled' : '' ?>>
                  <option value="text"     <?= $cf['field_type'] === 'text'     ? 'selected' : '' ?>>Szöveg</option>
                  <option value="number"   <?= $cf['field_type'] === 'number'   ? 'selected' : '' ?>>Szám</option>
                  <option value="checkbox" <?= $cf['field_type'] === 'checkbox' ? 'selected' : '' ?>>Jelölőnégyzet</option>
                  <option value="textarea" <?= $cf['field_type'] === 'textarea' ? 'selected' : '' ?>>Hosszú szöveg</option>
                  <option value="select"   <?= $cf['field_type'] === 'select'   ? 'selected' : '' ?>>Legördülő lista</option>
                </select>
                <?php if (!$ro): ?>
                  <button type="button" class="remove-field-btn btn-remove btn-remove-lg" title="Törlés">✕</button>
                <?php endif; ?>
              </div>
              <div class="field-options-wrap" style="margin-top:8px;<?= $cf['field_type'] !== 'select' ? 'display:none;' : '' ?>">
                <label style="font-size:11.5px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Lehetséges válaszok (vesszővel elválasztva)</label>
                <input type="text" name="field_options[]" value="<?= e($cf['field_options'] ?? '') ?>" placeholder="pl. Igen, Nem, Talán" style="margin-top:4px;" <?= $ro ? 'readonly' : '' ?>>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <p id="fields-empty-msg" style="color:var(--text-muted);font-size:13px;<?= !empty($customFields) ? 'display:none;' : '' ?>">
          Nincsenek egyedi mezők — csak az alap jelentkezési kérdések jelennek meg.
        </p>

        <?php if (!$isNew): ?>
        <div class="form-section-title" style="margin-top:24px;">GPX térképek</div>
        <?php if (!empty($gpxFiles)): ?>
          <?php foreach ($gpxFiles as $gf): ?>
          <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--bg,#f8fafc);border:1px solid var(--border);border-radius:8px;margin-bottom:8px;font-size:13px;">
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
            <small style="display:block;margin-top:5px;color:var(--text-muted);font-size:12px;">Több .gpx fájl is kijelölhető egyszerre. Max. 5 MB/fájl.</small>
          </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (!$ro): ?>
        <div style="margin-top:28px;display:flex;gap:12px;">
          <button type="submit" class="btn btn-primary">Mentés</button>
          <a href="<?= BASE_URL ?>/admin/future-tours.php" class="btn btn-ghost">Mégse</a>
        </div>
        <?php endif; ?>
      </form>
    </div>
</div>

<script>
// Day rows management
let dayCount = <?= $isNew ? 1 : count($days) ?>;

document.getElementById('add-day-btn')?.addEventListener('click', function() {
  dayCount++;
  const container = document.getElementById('days-container');
  const emptyMsg = document.querySelector('#days-container + p, #days-container ~ p');
  const typeOptions = <?= json_encode(['Gyalogtúra','Vízitúra','Kerékpártúra','Síelés','Barlangi túra','Munkavégzés']) ?>;

  const row = document.createElement('div');
  row.className = 'day-row';
  row.innerHTML = `
    <button type="button" class="remove-day-btn btn-remove" style="position:absolute;top:8px;right:10px;" title="Nap törlése">✕</button>
    <input type="hidden" name="day_id[]" value="0">
    <div class="day-row-grid">
      <div class="form-group"><label>${dayCount}. nap</label><input type="hidden" name="day_number[]" value="${dayCount}"></div>
      <div class="form-group"><label>Túratípus</label>
        <select name="day_tour_type[]">
          <option value="">—</option>
          ${typeOptions.map(t => `<option value="${t}">${t}</option>`).join('')}
        </select>
      </div>
      <div class="form-group"><label>Km</label><input type="number" step="0.1" min="0" name="day_km[]"></div>
      <div class="form-group"><label>Szintemelkedés (m)</label><input type="number" min="0" name="day_elevation[]"></div>
      <div class="form-group full" style="grid-column:1/-1;"><label>Leírás</label><input type="text" name="day_description[]"></div>
    </div>`;
  container.appendChild(row);
  row.querySelector('.remove-day-btn').addEventListener('click', removeDay);
  updateEmptyMsg();
});

document.querySelectorAll('.remove-day-btn').forEach(btn => btn.addEventListener('click', removeDay));

function removeDay() {
  this.closest('.day-row').remove();
  renumberDays();
  updateEmptyMsg();
}

function renumberDays() {
  document.querySelectorAll('.day-row').forEach((row, i) => {
    const label = row.querySelector('label');
    const hidden = row.querySelector('input[name="day_number[]"]');
    if (label) label.textContent = (i + 1) + '. nap';
    if (hidden) hidden.value = i + 1;
    dayCount = i + 1;
  });
}

function updateEmptyMsg() {
  // no-op; the empty message is handled server-side
}

// Custom fields management
document.getElementById('add-field-btn')?.addEventListener('click', function() {
  const container = document.getElementById('fields-container');
  const emptyMsg  = document.getElementById('fields-empty-msg');

  const row = document.createElement('div');
  row.className = 'field-row';
  row.innerHTML = `
    <input type="hidden" name="field_id[]" value="0">
    <div style="display:flex;gap:10px;align-items:center;">
      <input type="text" name="field_name[]" placeholder="Mező neve" style="flex:1;">
      <select name="field_type[]" style="width:160px;" onchange="toggleFieldOptions(this)">
        <option value="text">Szöveg</option>
        <option value="number">Szám</option>
        <option value="checkbox">Jelölőnégyzet</option>
        <option value="textarea">Hosszú szöveg</option>
        <option value="select">Legördülő lista</option>
      </select>
      <button type="button" class="remove-field-btn btn-remove btn-remove-lg" title="Törlés">✕</button>
    </div>
    <div class="field-options-wrap" style="margin-top:8px;display:none;">
      <label style="font-size:11.5px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Lehetséges válaszok (vesszővel elválasztva)</label>
      <input type="text" name="field_options[]" placeholder="pl. Igen, Nem, Talán" style="margin-top:4px;">
    </div>`;
  container.appendChild(row);
  if (emptyMsg) emptyMsg.style.display = 'none';
  row.querySelector('.remove-field-btn').addEventListener('click', removeField);
});

function toggleFieldOptions(selectEl) {
  const wrap = selectEl.closest('.field-row').querySelector('.field-options-wrap');
  if (wrap) wrap.style.display = selectEl.value === 'select' ? 'block' : 'none';
}

document.querySelectorAll('.remove-field-btn').forEach(btn => btn.addEventListener('click', removeField));

function removeField() {
  this.closest('.field-row').remove();
  const emptyMsg = document.getElementById('fields-empty-msg');
  if (emptyMsg && document.querySelectorAll('.field-row').length === 0) {
    emptyMsg.style.display = '';
  }
}

function confirmDelete(msg) { return confirm(msg); }

// Copy public link to clipboard
document.getElementById('copy-public-link-btn')?.addEventListener('click', function() {
  const url = this.dataset.url;
  navigator.clipboard.writeText(url).then(() => {
    const orig = this.textContent;
    this.textContent = 'Másolva!';
    setTimeout(() => { this.textContent = orig; }, 2000);
  });
});
</script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
