<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
requireAdminOrVezeto();
$ro = isVezeto();

$pdo = getDb();
ensureFutureToursSchema($pdo);

$isNew = isset($_GET['new']);
$id    = $isNew ? 0 : (int)($_GET['id'] ?? 0);

if (!$isNew && !$id) {
    header('Location: ' . BASE_URL . '/admin/future-tours.php');
    exit;
}

$tour           = null;
$days           = [];
$customFields   = [];
$applications   = [];

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

    $applications = $pdo->prepare("
        SELECT fta.*, u.firstname, u.lastname, u.email, u.phone
        FROM future_tour_applications fta
        LEFT JOIN users u ON u.id = fta.user_id
        WHERE fta.future_tour_id = ? AND fta.status IN ('confirmed','waitlist')
        ORDER BY fta.status ASC, fta.applied_at ASC
    ");
    $applications->execute([$id]);
    $applications = $applications->fetchAll();

    $pendingGuests = $pdo->prepare("
        SELECT * FROM future_tour_applications
        WHERE future_tour_id = ? AND status = 'pending'
        ORDER BY applied_at ASC
    ");
    $pendingGuests->execute([$id]);
    $pendingGuests = $pendingGuests->fetchAll();

    $addableMembers = $pdo->prepare("
        SELECT u.id, u.lastname, u.firstname
        FROM users u
        WHERE u.active = 1
          AND u.id NOT IN (
              SELECT user_id FROM future_tour_applications
              WHERE future_tour_id = ? AND status != 'cancelled' AND user_id IS NOT NULL
          )
        ORDER BY u.lastname ASC, u.firstname ASC
    ");
    $addableMembers->execute([$id]);
    $addableMembers = $addableMembers->fetchAll();
}

$countries = getCountries($pdo);

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$pageTitle  = $isNew ? 'Új meghirdetett túra' : e($tour['name'] ?? 'Meghirdetett túra');
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

<div style="display:grid;grid-template-columns:3fr 2fr;gap:20px;align-items:start;" class="future-tour-grid">

  <!-- LEFT: Tour form -->
  <div class="card">
    <div class="card-header">
      <h2><?= $isNew ? 'Túra adatai' : 'Túra szerkesztése' ?></h2>
    </div>
    <div class="card-body">
      <form method="post" action="<?= BASE_URL ?>/actions/<?= $isNew ? 'future-tour-add' : 'future-tour-update' ?>.php" id="future-tour-form">
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
            <label>Státusz</label>
            <select name="status" <?= $ro ? 'disabled' : '' ?>>
              <option value="open"      <?= ($tour['status'] ?? 'open') === 'open'      ? 'selected' : '' ?>>Nyitott – lehet jelentkezni</option>
              <option value="closed"    <?= ($tour['status'] ?? 'open') === 'closed'    ? 'selected' : '' ?>>Lezárt</option>
              <option value="cancelled" <?= ($tour['status'] ?? 'open') === 'cancelled' ? 'selected' : '' ?>>Törölve</option>
            </select>
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
            <div class="day-row" style="border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:10px;position:relative;">
              <?php if (!$ro): ?>
                <button type="button" class="remove-day-btn" style="position:absolute;top:8px;right:10px;background:none;border:none;color:var(--danger);font-size:16px;cursor:pointer;line-height:1;" title="Nap törlése">✕</button>
              <?php endif; ?>
              <input type="hidden" name="day_id[]" value="<?= (int)$day['id'] ?>">
              <div class="form-grid" style="grid-template-columns:auto 1fr 1fr 1fr;gap:10px;align-items:end;">
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
            <div class="day-row" style="border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:10px;position:relative;">
              <button type="button" class="remove-day-btn" style="position:absolute;top:8px;right:10px;background:none;border:none;color:var(--danger);font-size:16px;cursor:pointer;line-height:1;" title="Nap törlése">✕</button>
              <input type="hidden" name="day_id[]" value="0">
              <div class="form-grid" style="grid-template-columns:auto 1fr 1fr 1fr;gap:10px;align-items:end;">
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
            <div class="field-row" style="border:1px solid var(--border);border-radius:8px;padding:10px 12px;margin-bottom:8px;">
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
                  <button type="button" class="remove-field-btn" style="background:none;border:none;color:var(--danger);font-size:18px;cursor:pointer;padding:0 4px;line-height:1;flex-shrink:0;" title="Törlés">✕</button>
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

        <?php if (!$ro): ?>
        <div style="margin-top:28px;display:flex;gap:12px;">
          <button type="submit" class="btn btn-primary">Mentés</button>
          <a href="<?= BASE_URL ?>/admin/future-tours.php" class="btn btn-ghost">Mégse</a>
        </div>
        <?php endif; ?>
      </form>
    </div>
  </div>

  <!-- RIGHT: Applicants -->
  <?php if (!$isNew): ?>
  <div class="card" style="position:sticky;top:20px;">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
      <h2>Jelentkezők</h2>
      <div style="display:flex;gap:8px;align-items:center;">
        <span style="font-size:13px;color:var(--text-muted);">
          <?= array_sum(array_map(fn($a) => $a['status'] === 'confirmed' ? 1 : 0, $applications)) ?>
          / <?= (int)$tour['max_attendees'] ?> hely
        </span>
        <?php if (isAdmin()): ?>
        <a href="<?= BASE_URL ?>/actions/future-tour-export.php?id=<?= (int)$id ?>" class="btn btn-ghost btn-sm">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="14" height="14">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" y1="15" x2="12" y2="3"/>
          </svg>
          CSV
        </a>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-body" style="padding:0;">

      <?php if (!empty($pendingGuests)): ?>
      <!-- Pending guests -->
      <div style="background:#fffbeb;border-bottom:2px solid var(--warning,#f59e0b);padding:8px 14px 6px;">
        <span style="font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--warning,#b45309);">⏳ Jóváhagyásra vár (<?= count($pendingGuests) ?>)</span>
      </div>
      <table style="width:100%;border-collapse:collapse;font-size:13px;margin-bottom:2px;">
        <tbody>
          <?php foreach ($pendingGuests as $pg): ?>
          <tr style="border-bottom:1px solid var(--border);background:#fffdf5;">
            <td style="padding:9px 14px;">
              <div style="font-weight:600;"><?= e($pg['guest_name']) ?></div>
              <div style="font-size:11px;color:var(--text-muted);"><?= e($pg['guest_email']) ?><?= $pg['guest_phone'] ? ' · ' . e($pg['guest_phone']) : '' ?></div>
              <span style="background:#f3e8d0;color:#92400e;border-radius:4px;padding:1px 6px;font-size:10.5px;font-weight:600;border:1px solid #d97706;">Vendég</span>
            </td>
            <td style="padding:9px 8px;color:var(--text-muted);font-size:12px;white-space:nowrap;">
              <?= date('Y.m.d', strtotime($pg['applied_at'])) ?>
            </td>
            <td style="padding:9px 10px 9px 0;text-align:right;white-space:nowrap;">
              <form method="post" action="<?= BASE_URL ?>/actions/future-tour-approve-guest.php" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="application_id" value="<?= (int)$pg['id'] ?>">
                <input type="hidden" name="tour_id" value="<?= (int)$id ?>">
                <button type="submit" class="btn btn-primary btn-sm" style="padding:4px 8px;font-size:11px;">Jóváhagy</button>
              </form>
              <form method="post" action="<?= BASE_URL ?>/actions/future-tour-reject-guest.php" style="display:inline;margin-left:4px;"
                    onsubmit="return confirm('Biztosan elutasítja ezt a jelentkezést?')">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="application_id" value="<?= (int)$pg['id'] ?>">
                <input type="hidden" name="tour_id" value="<?= (int)$id ?>">
                <button type="submit" class="btn btn-danger btn-sm" style="padding:4px 8px;font-size:11px;">Elutasít</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>

      <?php if (empty($applications)): ?>
        <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:13px;">Még senki nem jelentkezett.</div>
      <?php else: ?>
        <div style="overflow-y:auto;max-height:460px;">
          <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
              <tr style="background:var(--card);border-bottom:1px solid var(--border);">
                <th style="padding:8px 14px;text-align:left;font-weight:600;color:var(--text-muted);font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;">Résztvevő</th>
                <th style="padding:8px 14px;text-align:left;font-weight:600;color:var(--text-muted);font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;">Dátum</th>
                <th style="padding:8px 6px;text-align:center;font-weight:600;color:var(--text-muted);font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;">Fizetés</th>
                <th style="padding:8px 6px;"></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($applications as $app): ?>
              <tr style="border-bottom:1px solid var(--border);<?= $app['status'] === 'waitlist' ? 'opacity:.7;' : '' ?>">
                <td style="padding:10px 14px;">
                  <?php if ($app['user_id']): ?>
                    <div style="font-weight:600;"><?= e($app['lastname'] . ' ' . $app['firstname']) ?></div>
                    <div style="font-size:11px;color:var(--text-muted);"><?= e($app['email']) ?></div>
                  <?php else: ?>
                    <div style="font-weight:600;"><?= e($app['guest_name']) ?></div>
                    <div style="font-size:11px;color:var(--text-muted);"><?= e($app['guest_email']) ?></div>
                    <span style="background:#f3e8d0;color:#92400e;border-radius:4px;padding:1px 6px;font-size:10.5px;font-weight:600;border:1px solid #d97706;">Vendég</span>
                  <?php endif; ?>
                  <?php if ($app['status'] === 'waitlist'): ?>
                    <span style="background:var(--warning-bg,#fffbeb);color:var(--warning,#b45309);border-radius:4px;padding:1px 6px;font-size:10.5px;font-weight:600;border:1px solid var(--warning,#f59e0b);">Várólistán</span>
                  <?php endif; ?>
                </td>
                <td style="padding:10px 14px;color:var(--text-muted);font-size:12px;white-space:nowrap;">
                  <?= date('Y.m.d', strtotime($app['applied_at'])) ?>
                </td>
                <td style="padding:10px 6px;text-align:center;">
                  <?php if ($app['paid_at']): ?>
                    <form method="post" action="<?= BASE_URL ?>/actions/future-tour-mark-paid.php" style="display:inline;">
                      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                      <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
                      <input type="hidden" name="tour_id" value="<?= (int)$id ?>">
                      <button type="submit" title="Fizetés visszavonása" style="background:none;border:none;cursor:pointer;font-size:16px;line-height:1;color:var(--primary);">✓</button>
                    </form>
                  <?php else: ?>
                    <form method="post" action="<?= BASE_URL ?>/actions/future-tour-mark-paid.php" style="display:inline;">
                      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                      <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
                      <input type="hidden" name="tour_id" value="<?= (int)$id ?>">
                      <button type="submit" title="Fizetés rögzítése"
                              style="background:none;border:none;cursor:pointer;font-size:16px;line-height:1;color:var(--danger);">⚠</button>
                    </form>
                  <?php endif; ?>
                </td>
                <td style="padding:10px 10px 10px 0;text-align:right;">
                  <?php if (isAdmin()): ?>
                  <form method="post" action="<?= BASE_URL ?>/actions/future-tour-remove-applicant.php"
                        onsubmit="return confirmDelete('Biztosan eltávolítja ezt a személyt a túráról?')">
                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                    <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
                    <input type="hidden" name="tour_id" value="<?= (int)$id ?>">
                    <button type="submit" class="btn btn-danger btn-sm" style="padding:4px 8px;font-size:11px;">Eltávolít</button>
                  </form>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
    <?php if (isAdmin() && !empty($addableMembers)): ?>
    <div style="padding:12px 16px;border-top:1px solid var(--border);">
      <form method="post" action="<?= BASE_URL ?>/actions/future-tour-add-applicant.php" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="tour_id" value="<?= (int)$id ?>">
        <select name="user_id" style="flex:1;min-width:140px;font-size:13px;">
          <option value="">— Tag kiválasztása —</option>
          <?php foreach ($addableMembers as $m): ?>
            <option value="<?= (int)$m['id'] ?>"><?= e($m['lastname'] . ' ' . $m['firstname']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="status" style="width:130px;font-size:13px;">
          <option value="confirmed">Megerősített</option>
          <option value="waitlist">Várólistán</option>
        </select>
        <button type="submit" class="btn btn-primary btn-sm">Hozzáadás</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div><!-- .future-tour-grid -->

<style>
  @media (max-width: 768px) {
    .future-tour-grid { grid-template-columns: 1fr !important; }
  }
</style>

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
  row.style.cssText = 'border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:10px;position:relative;';
  row.innerHTML = `
    <button type="button" class="remove-day-btn" style="position:absolute;top:8px;right:10px;background:none;border:none;color:var(--danger);font-size:16px;cursor:pointer;line-height:1;" title="Nap törlése">✕</button>
    <input type="hidden" name="day_id[]" value="0">
    <div class="form-grid" style="grid-template-columns:auto 1fr 1fr 1fr;gap:10px;align-items:end;">
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
  row.style.cssText = 'border:1px solid var(--border);border-radius:8px;padding:10px 12px;margin-bottom:8px;';
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
      <button type="button" class="remove-field-btn" style="background:none;border:none;color:var(--danger);font-size:18px;cursor:pointer;padding:0 4px;line-height:1;flex-shrink:0;" title="Törlés">✕</button>
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
