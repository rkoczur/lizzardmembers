<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
require_once __DIR__ . '/../includes/future-tour-organizers.php';
require_once __DIR__ . '/../includes/future-tour-content.php';
require_once __DIR__ . '/../includes/app-settings-schema.php';
requireAdminOrVezeto();
$ro = !canCreateFutureTours();

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

// Publikus adatlap tartalmai: felszerelés lista, élménycímkék (+ ajánlás), díj megjegyzés alapszövege
$equipmentItems = tourTextLines($tour['equipment'] ?? '');
$tourTags       = tourTextLines($tour['experience_tags'] ?? '');
$usedTags       = getUsedExperienceTags($pdo);
$feeNoteDefault = tourFeeNoteDefault($pdo);
$coverLogo      = $tour['cover_logo'] ?? 'light';
$coverLogoPos   = $tour['cover_logo_pos'] ?? 'left';
$dayGpxCount    = [];

// Szervezők: meglévő túránál a beállított (vagy a létrehozó), új túránál a bejelentkezett felhasználó
$orgMembers = $pdo->query("SELECT id, firstname, lastname, email FROM users WHERE active = 1 ORDER BY lastname, firstname")->fetchAll();
if ($isNew) {
    $orgStmt = $pdo->prepare("SELECT id, firstname, lastname FROM users WHERE id = ?");
    $orgStmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
    $organizers = $orgStmt->fetchAll();
} else {
    $organizers = getFutureTourOrganizers($pdo, $id);
}

$gpxFiles = [];
$galleryImages = [];
if (!$isNew) {
    $gpxFilesStmt = $pdo->prepare("SELECT * FROM future_tour_gpx_files WHERE future_tour_id = ? ORDER BY sort_order ASC, uploaded_at ASC");
    $gpxFilesStmt->execute([$id]);
    $gpxFiles = $gpxFilesStmt->fetchAll();
    foreach ($gpxFiles as $gf) {
        if ($gf['day_number'] !== null) $dayGpxCount[(int)$gf['day_number']] = ($dayGpxCount[(int)$gf['day_number']] ?? 0) + 1;
    }

    $galleryStmt = $pdo->prepare("SELECT * FROM future_tour_gallery_images WHERE future_tour_id = ? ORDER BY sort_order ASC, uploaded_at ASC");
    $galleryStmt->execute([$id]);
    $galleryImages = $galleryStmt->fetchAll();
}

// Kiküldött túraértesítők (kinek ment ki már értesítő erről a túráról)
$notifiedRecipients = [];
if (!$isNew) {
    $notifStmt = $pdo->prepare("SELECT name, email, sent_at FROM future_tour_notifications WHERE future_tour_id = ? ORDER BY name ASC, email ASC");
    $notifStmt->execute([$id]);
    $notifiedRecipients = $notifStmt->fetchAll();
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
            data-url="<?= e(((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL . '/public/tour-detail.php?id=' . (int)$id) ?>">
      Publikus link másolása
    </button>
    <?php if (canManageTours() && $tour['status'] !== 'draft'): ?>
    <button type="button" class="btn btn-primary btn-sm" id="announce-btn" data-tour-id="<?= (int)$id ?>">
      📣 Értesítő kiküldése
    </button>
    <?php endif; ?>
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

<?php if (!$isNew && !empty($notifiedRecipients)): ?>
<details class="card" style="max-width:780px;margin-bottom:16px;">
  <summary style="cursor:pointer;padding:14px 20px;font-weight:600;list-style:none;display:flex;align-items:center;gap:8px;">
    📣 Értesítőt kapott (<?= count($notifiedRecipients) ?>)
  </summary>
  <div class="card-body" style="border-top:1px solid var(--border);">
    <p style="margin:0 0 10px;font-size:12px;color:var(--text-muted);">Ezek a tagok már megkapták az értesítőt erről a túráról — újraküldésnél kimaradnak.</p>
    <ul style="list-style:none;margin:0;padding:0;columns:2;column-gap:24px;font-size:11.5px;line-height:1.65;">
      <?php foreach ($notifiedRecipients as $nr): ?>
        <li style="break-inside:avoid;margin-bottom:2px;">
          <span style="font-weight:600;"><?= e($nr['name'] ?: '—') ?></span>
          <span style="color:var(--text-muted);">&lt;<?= e($nr['email']) ?>&gt;</span>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</details>
<?php endif; ?>

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
            <input type="number" name="participation_fee" min="0" step="1" value="<?= ($tour['participation_fee'] ?? null) !== null ? (int)$tour['participation_fee'] : '' ?>" placeholder="pl. 15000" <?= $ro ? 'readonly' : '' ?>>
          </div>
          <div class="form-group full">
            <label>A részvételi díj tartalmazza</label>
            <textarea name="fee_includes" rows="4" placeholder="Egy sor = egy tétel, pl.:&#10;Szállás&#10;Reggeli&#10;Túravezető" <?= $ro ? 'readonly' : '' ?>><?= e($tour['fee_includes'] ?? '') ?></textarea>
            <small style="color:var(--text-muted);font-size:12px;">Minden sor egy tételnek számít a listán. Hagyd üresen, ha nem szükséges.</small>
          </div>
          <div class="form-group full">
            <label>Nem tartalmazza</label>
            <textarea name="fee_excludes" rows="4" placeholder="Egy sor = egy tétel, pl.:&#10;Utazási költség&#10;Ebéd" <?= $ro ? 'readonly' : '' ?>><?= e($tour['fee_excludes'] ?? '') ?></textarea>
            <small style="color:var(--text-muted);font-size:12px;">Minden sor egy tételnek számít a listán. Hagyd üresen, ha nem szükséges.</small>
          </div>
          <div class="form-group full">
            <label>Díj megjegyzés <small class="ft-hint-inline">(a jelentkezés gomb és az ár alatt)</small></label>
            <textarea name="fee_note" rows="3" placeholder="<?= e($feeNoteDefault) ?>" <?= $ro ? 'readonly' : '' ?>><?= e($tour['fee_note'] ?? '') ?></textarea>
            <small class="ft-hint">Üresen hagyva az egyesület alapértelmezett szövege jelenik meg (lásd a mezőben halványan).</small>
          </div>
          <div class="form-group">
            <label>Szerezhető Lizzardier pont</label>
            <input type="number" name="lizzardier_points" min="0" step="1" value="<?= ($tour['lizzardier_points'] ?? null) !== null ? (int)$tour['lizzardier_points'] : '' ?>" placeholder="pl. 5" <?= $ro ? 'readonly' : '' ?>>
            <small style="display:block;margin-top:4px;color:var(--text-muted);font-size:12px;">A pont csak a túra teljesítése után kerül jóváírásra.</small>
          </div>
          <div class="form-group">
            <label>Státusz</label>
            <select name="status" <?= $ro ? 'disabled' : '' ?>>
              <option value="draft"     <?= ($tour['status'] ?? 'open') === 'draft'     ? 'selected' : '' ?>>Piszkozat – nem jelenik meg publikusan</option>
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
            <label>Szállás és utazás</label>
            <textarea name="accommodation" rows="5" <?= $ro ? 'readonly' : '' ?>><?= e($tour['accommodation'] ?? '') ?></textarea>
          </div>
          <div class="form-group full ft-meeting" id="meeting-group">
            <label>Találkozó <small class="ft-hint-inline">(csak egynapos túránál, a jelentkezés kártya mellett)</small></label>
            <div class="ft-meeting-row">
              <input type="text" name="meeting_place" maxlength="255" value="<?= e($tour['meeting_place'] ?? '') ?>" placeholder="pl. Budapest, Széll Kálmán tér, metrókijárat" <?= $ro ? 'readonly' : '' ?>>
              <input type="time" name="meeting_time" value="<?= e(substr((string)($tour['meeting_time'] ?? ''), 0, 5)) ?>" <?= $ro ? 'readonly' : '' ?>>
            </div>
            <small class="ft-hint">A helyet a Google Térkép keresőjébe adjuk át, ezért pontos címet vagy közismert helynevet adj meg.</small>
          </div>
          <div class="form-group full">
            <label>Szükséges tapasztalat és erőnlét</label>
            <textarea name="experience" rows="3" <?= $ro ? 'readonly' : '' ?>><?= e($tour['experience'] ?? '') ?></textarea>
          </div>
          <div class="form-group full">
            <label>Felszerelés</label>
            <div class="ft-list-editor" data-ft-list="equipment_items[]">
              <ol class="ft-list-rows">
                <?php foreach ($equipmentItems as $item): ?>
                <li class="ft-list-row">
                  <input type="text" name="equipment_items[]" value="<?= e($item) ?>" maxlength="255" <?= $ro ? 'readonly' : '' ?>>
                  <?php if (!$ro): ?><button type="button" class="ft-list-del" data-ft-list-del title="Tétel törlése">&times;</button><?php endif; ?>
                </li>
                <?php endforeach; ?>
              </ol>
              <?php if (!$ro): ?>
              <button type="button" class="ft-list-add" data-ft-list-add>+ Tétel hozzáadása</button>
              <?php endif; ?>
            </div>
            <small class="ft-hint">Egy sor = egy felszerelési tétel. A publikus oldalon felsorolásként jelenik meg.</small>
          </div>
          <div class="form-group full">
            <label>Élménycímkék</label>
            <div class="ft-chips<?= $ro ? ' is-readonly' : '' ?>" data-ft-chips="experience_tags[]">
              <?php foreach ($tourTags as $tag): ?>
              <span class="ft-chip"><?= e($tag) ?><input type="hidden" name="experience_tags[]" value="<?= e($tag) ?>"><?php if (!$ro): ?><button type="button" class="ft-chip-del" title="Címke törlése">&times;</button><?php endif; ?></span>
              <?php endforeach; ?>
              <?php if (!$ro): ?>
              <input type="text" class="ft-chip-input" list="ft-tag-suggest" maxlength="40" placeholder="pl. Túrázás, Canyoning … (Enter)">
              <?php endif; ?>
            </div>
            <datalist id="ft-tag-suggest">
              <?php foreach ($usedTags as $tag): ?><option value="<?= e($tag) ?>"><?php endforeach; ?>
            </datalist>
            <small class="ft-hint">Írd be a címkét, majd Enter vagy vessző. A korábban használt címkéket gépelés közben felajánlja.</small>
          </div>
        </div>

        <!-- Szervezők -->
        <div class="form-section-title form-section-title--spaced">Szervezők</div>
        <div class="member-picker">
          <?php if (!$ro): ?>
          <div class="member-picker-controls">
            <select id="org-picker-select">
              <option value="">— Válasszon tagot —</option>
              <?php foreach ($orgMembers as $m): ?>
                <option value="<?= (int)$m['id'] ?>"><?= e($m['lastname'] . ' ' . $m['firstname']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" id="org-picker-add" class="btn btn-secondary btn-sm">Hozzáad</button>
          </div>
          <?php endif; ?>
          <div id="org-picker-list" class="member-picker-list">
            <?php foreach ($organizers as $m): ?>
            <div class="member-picker-item" data-member-id="<?= (int)$m['id'] ?>">
              <span><?= e($m['lastname'] . ' ' . $m['firstname']) ?></span>
              <input type="hidden" name="organizer_ids[]" value="<?= (int)$m['id'] ?>">
              <?php if (!$ro): ?>
              <button type="button" class="btn btn-danger btn-sm">Eltávolít</button>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
          <p id="org-picker-empty" class="member-picker-empty"<?= $organizers ? ' hidden' : '' ?>>Nincs megadva — a túra létrehozója jelenik meg szervezőként.</p>
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
        <div class="form-group">
          <label>Logo a borítóképen</label>
          <div class="ft-logo-pick" role="radiogroup">
            <?php foreach (TOUR_COVER_LOGOS as $logoKey => $logoLabel): ?>
            <label class="ft-logo-tile ft-logo-tile--<?= $logoKey ?>">
              <input type="radio" name="cover_logo" value="<?= $logoKey ?>" <?= $coverLogo === $logoKey ? 'checked' : '' ?> <?= $ro ? 'disabled' : '' ?>>
              <span class="ft-logo-swatch">
                <?php if ($logoKey !== 'none'): ?>
                <img src="<?= BASE_URL ?>/assets/img/logo_collision_<?= $logoKey === 'dark' ? 'b' : 'y' ?>_small.png" alt="">
                <?php else: ?>
                <span class="ft-logo-none">&#8856;</span>
                <?php endif; ?>
              </span>
              <span class="ft-logo-name"><?= e($logoLabel) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-group">
          <label>A logo helye</label>
          <div class="ft-logo-pick" role="radiogroup">
            <?php foreach (TOUR_COVER_LOGO_POS as $posKey => $posLabel): ?>
            <label class="ft-logo-tile">
              <input type="radio" name="cover_logo_pos" value="<?= $posKey ?>" <?= $coverLogoPos === $posKey ? 'checked' : '' ?> <?= $ro ? 'disabled' : '' ?>>
              <span class="ft-logo-swatch ft-pos-swatch ft-pos-swatch--<?= $posKey ?>"><span class="ft-pos-dot"></span></span>
              <span class="ft-logo-name"><?= e($posLabel) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
          <small class="ft-hint">A borítókép melyik felső sarkában jelenjen meg a logo a publikus adatlapon.</small>
        </div>

        <!-- DAYS -->
        <div class="form-section-title" style="margin-top:24px;">
          Napok
          <?php if (!$ro): ?>
            <button type="button" id="add-day-btn" class="btn btn-ghost btn-sm" style="margin-left:12px;font-size:12px;">+ Nap hozzáadása</button>
          <?php endif; ?>
        </div>
        <div id="days-container">
          <?php
          if (!empty($days)) {
              foreach ($days as $day) { $dayNum = (int)$day['day_number']; include __DIR__ . '/../includes/future-tour-day-row.php'; }
          } elseif ($isNew) {
              $day = null; $dayNum = 1; include __DIR__ . '/../includes/future-tour-day-row.php';
          }
          ?>
          <?php if (empty($days) && !$isNew): ?>
            <p style="color:var(--text-muted);font-size:13px;">Még nincs nap hozzáadva.</p>
          <?php endif; ?>
        </div>
        <?php if (!$ro): ?>
        <template id="day-row-template"><?php $day = null; $dayNum = 0; include __DIR__ . '/../includes/future-tour-day-row.php'; ?></template>
        <?php endif; ?>

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
            <select name="gpx_day[<?= (int)$gf['id'] ?>]" class="gpx-day-select" title="Melyik naphoz tartozik?">
              <option value="0">Egész túra</option>
              <?php foreach ($days as $d): ?>
                <option value="<?= (int)$d['day_number'] ?>" <?= (int)($gf['day_number'] ?? 0) === (int)$d['day_number'] ? 'selected' : '' ?>><?= (int)$d['day_number'] ?>. nap</option>
              <?php endforeach; ?>
            </select>
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
            <small style="display:block;margin-top:5px;color:var(--text-muted);font-size:12px;">Itt az egész túrához tartozó térképek tölthetők fel (több is kijelölhető). Naphoz kötött térképet a napoknál tölts fel. Max. 1 MB/fájl.</small>
          </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (!$isNew && canManageTours()): ?>
        <div class="form-section-title" style="margin-top:24px;">Fotógaléria</div>
        <p style="font-size:13px;color:var(--text-muted);margin:0 0 12px;">
          Húzd a képeket a kívánt sorrendbe. Legfeljebb <?= GALLERY_MAX_IMAGES ?> kép, képenként max. <?= (int)round(GALLERY_MAX_BYTES / 1024 / 1024) ?> MB (JPG, PNG, WebP).
        </p>
        <?php if (!empty($galleryImages)): ?>
        <div id="gallery-admin-grid" class="gallery-admin-grid">
          <?php foreach ($galleryImages as $img): ?>
          <div class="gallery-admin-item" draggable="true" data-id="<?= (int)$img['id'] ?>">
            <img src="<?= e(TOUR_GALLERY_URL . $img['filename']) ?>" alt="">
            <input type="hidden" name="gallery_order[]" value="<?= (int)$img['id'] ?>">
            <input type="text" name="gallery_label[<?= (int)$img['id'] ?>]" value="<?= e($img['label'] ?? '') ?>" placeholder="Képaláírás (opcionális)" class="gallery-admin-label">
            <label class="gallery-admin-del"><input type="checkbox" name="delete_gallery_ids[]" value="<?= (int)$img['id'] ?>"> Törlés</label>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div style="margin-top:10px;">
          <input type="file" name="gallery_files[]" accept="image/jpeg,image/png,image/webp" multiple>
          <small style="display:block;margin-top:5px;color:var(--text-muted);font-size:12px;">Több kép is kijelölhető egyszerre.</small>
        </div>
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
document.getElementById('add-day-btn')?.addEventListener('click', function() {
  // Új nap a szerveroldali sablonból (future-tour-day-row.php), a sorszámot a renumberDays() állítja
  const row = document.getElementById('day-row-template').content.firstElementChild.cloneNode(true);
  document.getElementById('days-container').appendChild(row);
  row.querySelector('.remove-day-btn').addEventListener('click', removeDay);
  renumberDays();
});

document.querySelectorAll('.remove-day-btn').forEach(btn => btn.addEventListener('click', removeDay));

function removeDay() {
  this.closest('.day-row').remove();
  renumberDays();
}

function renumberDays() {
  document.querySelectorAll('.day-row').forEach((row, i) => {
    const label = row.querySelector('label');
    const hidden = row.querySelector('input[name="day_number[]"]');
    if (label) label.textContent = (i + 1) + '. nap';
    if (hidden) hidden.value = i + 1;
  });
  toggleMeeting();
}

// Találkozó mező csak egynapos túránál
function toggleMeeting() {
  const group = document.getElementById('meeting-group');
  if (group) group.hidden = document.querySelectorAll('#days-container .day-row').length > 1;
}
toggleMeeting();

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

<?php if (!$isNew): ?>
<!-- Értesítő kiküldése modal -->
<div id="announce-overlay" class="ft-modal-overlay" hidden>
  <div class="ft-modal" role="dialog" aria-modal="true" aria-labelledby="announce-title">
    <div class="ft-modal-head">
      <h2 id="announce-title">📣 Értesítő kiküldése</h2>
      <button type="button" class="ft-modal-close" id="announce-close" aria-label="Bezárás">✕</button>
    </div>
    <div class="ft-modal-body">
      <p id="announce-intro" class="ft-modal-intro">Címzettek betöltése…</p>
      <p style="margin:12px 0 0;font-size:13px;">
        <a href="<?= BASE_URL ?>/actions/future-tour-announce-preview.php?tour_id=<?= (int)$id ?>" target="_blank" rel="noopener">📄 Minta e-mail megtekintése új lapon</a>
      </p>
      <div id="announce-progress" class="send-progress" hidden>
        <div class="send-progress-head">
          <span id="announce-progress-label" class="send-progress-label">Küldés folyamatban…</span>
          <span id="announce-progress-count" class="send-progress-count">0/0</span>
        </div>
        <div class="send-progress-track">
          <div id="announce-progress-fill" class="send-progress-fill"></div>
        </div>
        <div id="announce-progress-note" class="send-progress-note"></div>
        <ul id="announce-failed-list" class="send-failed-list" hidden></ul>
      </div>
    </div>
    <div class="ft-modal-actions">
      <button type="button" class="btn btn-primary" id="announce-send" disabled>Küldés most</button>
      <button type="button" class="btn btn-secondary" id="announce-cancel">Bezárás</button>
    </div>
  </div>
</div>

<script>
(function () {
  var BASE = <?= json_encode(BASE_URL) ?>;
  var CSRF = <?= json_encode(csrfToken()) ?>;
  var TOUR_ID = <?= (int)$id ?>;
  var BATCH_SIZE = 3, RETRY_WAIT = 30;

  var btn      = document.getElementById('announce-btn');
  var overlay  = document.getElementById('announce-overlay');
  var closeBtn = document.getElementById('announce-close');
  var cancelBtn= document.getElementById('announce-cancel');
  var sendBtn  = document.getElementById('announce-send');
  var intro    = document.getElementById('announce-intro');
  var panel    = document.getElementById('announce-progress');
  var label    = document.getElementById('announce-progress-label');
  var count    = document.getElementById('announce-progress-count');
  var fill     = document.getElementById('announce-progress-fill');
  var note     = document.getElementById('announce-progress-note');
  var failUl   = document.getElementById('announce-failed-list');
  if (!btn) return;

  var token = null, ids = [], total = 0, processed = 0, sentOk = 0, failedItems = [], sending = false, aborted = false, abortError = '';

  function postJson(url, params) {
    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    Object.keys(params).forEach(function (k) {
      var v = params[k];
      if (Array.isArray(v)) v.forEach(function (x) { fd.append(k + '[]', x); });
      else fd.append(k, v);
    });
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }

  function setProgress() {
    count.textContent = processed + '/' + total;
    fill.style.width = (total ? Math.round(processed / total * 100) : 0) + '%';
  }

  function renderFailures() {
    if (!failedItems.length) { failUl.hidden = true; return; }
    failUl.hidden = false; failUl.innerHTML = '';
    failedItems.forEach(function (f) {
      var li = document.createElement('li');
      li.textContent = (f.name || ('#' + f.id)) + ' — ' + (f.error || 'sikertelen');
      failUl.appendChild(li);
    });
  }

  function sendBatches(list, countProgress) {
    var i = 0;
    function next() {
      if (i >= list.length || aborted) return Promise.resolve();
      var slice = list.slice(i, i + BATCH_SIZE); i += BATCH_SIZE;
      return postJson(BASE + '/actions/future-tour-announce-batch.php', { token: token, batch_ids: slice }).then(function (res) {
        if (!res.ok) throw new Error(res.error || 'Ismeretlen szerverhiba.');
        res.results.forEach(function (r) { if (countProgress) processed++; if (r.ok) sentOk++; else failedItems.push(r); });
        if (countProgress) setProgress();
        // Első SMTP-hiba → leállítjuk a kiküldést, nincs újrapróbálkozás
        if (res.stopped) { aborted = true; abortError = res.stopError || 'SMTP hiba'; return Promise.resolve(); }
        return next();
      });
    }
    return next();
  }

  function openModal() {
    overlay.hidden = false;
    sending = false; sendBtn.disabled = true; panel.hidden = true;
    fill.classList.remove('is-done', 'is-warn'); failUl.hidden = true;
    intro.textContent = 'Címzettek betöltése…';
    token = null; ids = []; total = 0; processed = 0; sentOk = 0; failedItems = [];
    cancelBtn.textContent = 'Mégse';

    postJson(BASE + '/actions/future-tour-announce-prepare.php', { tour_id: TOUR_ID }).then(function (res) {
      if (!res.ok) { intro.textContent = res.error || 'Hiba történt.'; return; }
      token = res.token; ids = res.ids; total = res.total;
      intro.textContent = 'Az értesítőt ' + total + ' feliratkozott tagnak küldjük ki.';
      sendBtn.disabled = false;
    }).catch(function () { intro.textContent = 'Hiba történt a címzettek betöltésekor.'; });
  }

  function closeModal() {
    if (sending) return; // küldés közben nem zárható
    overlay.hidden = true;
  }

  btn.addEventListener('click', openModal);
  closeBtn.addEventListener('click', closeModal);
  cancelBtn.addEventListener('click', closeModal);
  overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });

  sendBtn.addEventListener('click', function () {
    if (!token || sending) return;
    sending = true; sendBtn.disabled = true; panel.hidden = false;
    processed = 0; sentOk = 0; failedItems = []; aborted = false; abortError = '';
    label.textContent = 'Küldés folyamatban…'; note.textContent = '';
    setProgress();
    sendBatches(ids, true).then(function () {
      renderFailures();
      sending = false;
      cancelBtn.textContent = 'Bezárás';
      if (aborted) {
        label.textContent = 'Megszakadt';
        fill.classList.add('is-warn');
        note.textContent = 'A küldés leállt egy SMTP-hiba miatt: ' + abortError + '. Eddig ' + sentOk + ' értesítő ment ki. A hiba javítása után újraindítva csak a még nem értesített tagok kapják meg.';
      } else {
        label.textContent = 'Kész';
        fill.classList.add(failedItems.length ? 'is-warn' : 'is-done');
        note.textContent = sentOk + ' sikeres küldés' + (failedItems.length ? (', ' + failedItems.length + ' sikertelen.') : '.');
      }
    }).catch(function (err) {
      sending = false;
      label.textContent = 'Hiba';
      note.textContent = err.message || 'Váratlan hiba történt.';
      sendBtn.disabled = false;
    });
  });
})();
</script>
<?php endif; ?>

<script>
// Fotógaléria — natív drag & drop átrendezés (a gallery_order[] hidden inputok a kártyával együtt mozognak)
(function () {
  const grid = document.getElementById('gallery-admin-grid');
  if (!grid) return;
  let dragEl = null;
  grid.addEventListener('dragstart', function (e) {
    dragEl = e.target.closest('.gallery-admin-item');
    if (dragEl) e.dataTransfer.effectAllowed = 'move';
  });
  grid.addEventListener('dragover', function (e) {
    e.preventDefault();
    const over = e.target.closest('.gallery-admin-item');
    if (!over || !dragEl || over === dragEl) return;
    const rect = over.getBoundingClientRect();
    const after = (e.clientY - rect.top) > rect.height / 2 || (e.clientX - rect.left) > rect.width / 2;
    grid.insertBefore(dragEl, after ? over.nextSibling : over);
  });
  grid.addEventListener('dragend', function () { dragEl = null; });
})();
</script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
