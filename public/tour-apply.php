<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';

$pdo = getDb();
ensureFutureToursSchema($pdo);

$memberLoggedIn = isLoggedIn();
$memberUser     = null;
if ($memberLoggedIn) {
    $stmt = $pdo->prepare("SELECT id, firstname, lastname, email FROM users WHERE id = ? AND active = 1 LIMIT 1");
    $stmt->execute([getCurrentUserId()]);
    $memberUser = $stmt->fetch();
    if (!$memberUser) $memberLoggedIn = false;
}

$id                  = (int)($_GET['id'] ?? 0);
$done                = isset($_GET['done']);
$membershipSubmitted = isset($_GET['membership_submitted']);
$joinOld             = $_SESSION['join_old'] ?? [];
unset($_SESSION['join_old']);

if (!$id) {
    header('Location: ' . BASE_URL . '/public/turanyptar.php');
    exit;
}

$tourStmt = $pdo->prepare("
    SELECT ft.*, c.name_hu AS country_name
    FROM future_tours ft
    LEFT JOIN countries c ON c.code = ft.country
    WHERE ft.id = ? AND ft.status = 'open'
    LIMIT 1
");
$tourStmt->execute([$id]);
$tour = $tourStmt->fetch();
if (!$tour) {
    header('Location: ' . BASE_URL . '/public/turanyptar.php');
    exit;
}

$customFieldsStmt = $pdo->prepare("SELECT * FROM future_tour_custom_fields WHERE future_tour_id = ? ORDER BY sort_order ASC, id ASC");
$customFieldsStmt->execute([$id]);
$customFields = $customFieldsStmt->fetchAll();

$disabledFields = json_decode($tour['disabled_standard_fields'] ?? '[]', true) ?: [];
$fieldEnabled   = fn(string $f): bool => !in_array($f, $disabledFields, true);

$flash_error = getFlash('error');

$pageTitle     = 'Jelentkezés – ' . ($tour['name'] ?? '');
$activePubPage = 'turanyptar';
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap-narrow">
  <div style="margin-bottom:16px;">
    <a href="<?= BASE_URL ?>/public/tour-detail.php?id=<?= $id ?>" class="btn btn-secondary btn-sm">← Vissza a túra részleteihez</a>
  </div>

  <!-- Tour info -->
  <div class="card" style="margin-bottom:20px;background:#29776f;color:#fff;border:none;">
    <div class="card-body">
      <h2 style="margin:0 0 14px;color:#fff;"><?= e($tour['name']) ?></h2>
      <div class="tour-stats-grid" style="background:rgba(255,255,255,.25);border-color:rgba(255,255,255,.2);">
        <div class="tour-stat-cell" style="padding:12px 14px;background:rgba(255,255,255,.92);">
          <div class="tour-stat-label" style="color:#29776f;">Kezdés</div>
          <div class="tour-stat-value" style="color:#1a3d39;"><?= $tour['start_date'] ? formatDate($tour['start_date']) : '—' ?></div>
        </div>
        <div class="tour-stat-cell" style="padding:12px 14px;background:rgba(255,255,255,.92);">
          <div class="tour-stat-label" style="color:#29776f;">Időtartam</div>
          <div class="tour-stat-value" style="color:#1a3d39;"><?= (int)$tour['num_days'] ?> nap</div>
        </div>
        <?php if (!empty($tour['country_name'])): ?>
        <div class="tour-stat-cell" style="padding:12px 14px;background:rgba(255,255,255,.92);">
          <div class="tour-stat-label" style="color:#29776f;">Helyszín</div>
          <div class="tour-stat-value" style="color:#1a3d39;"><?= e($tour['country_name']) ?><?= !empty($tour['region']) ? ' · ' . e($tour['region']) : '' ?></div>
        </div>
        <?php endif; ?>
        <?php if ($tour['participation_fee'] !== null): ?>
        <div class="tour-stat-cell" style="padding:12px 14px;background:rgba(255,255,255,.92);">
          <div class="tour-stat-label" style="color:#29776f;">Részvételi díj</div>
          <div class="tour-stat-value" style="color:#1a3d39;"><?= number_format((float)$tour['participation_fee'], 0, ',', ' ') ?> Ft</div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($done): ?>
  <div class="card">
    <div class="card-body card-body-center" style="padding:36px 24px;text-align:center;">
      <div style="font-size:48px;margin-bottom:16px;">✅</div>
      <h2 style="margin:0 0 10px;color:var(--primary);">Jelentkezés elküldve!</h2>
      <p style="color:var(--text-muted);font-size:14px;line-height:1.6;margin:0 0 20px;">
        Köszönjük a jelentkezésedet! Hamarosan visszajelzünk e-mailben.<br>
        Az adminisztrátor jóváhagyása után véglegesítjük a részvételed.
      </p>
      <a href="<?= BASE_URL ?>/public/turanyptar.php" class="btn btn-ghost">← Vissza a túranaptárhoz</a>
    </div>
  </div>

  <?php elseif ($membershipSubmitted): ?>
  <div class="card">
    <div class="card-body card-body-center" style="padding:36px 24px;text-align:center;">
      <div style="font-size:48px;margin-bottom:16px;">✅</div>
      <h2 style="margin:0 0 10px;color:var(--primary);">Tagságra jelentkezés elküldve!</h2>
      <p style="color:var(--text-muted);font-size:14px;line-height:1.6;margin:0 0 20px;">
        Köszönjük a jelentkezésedet! Az egyesület képviselői hamarosan átnézik, és e-mailben értesítünk a döntésről.<br>
        Amint taggá váltál, visszajöhetsz és feliratkozhatsz a túrára.
      </p>
      <a href="<?= BASE_URL ?>/public/turanyptar.php" class="btn btn-ghost">← Vissza a túranaptárhoz</a>
    </div>
  </div>

  <?php else: ?>
  <div class="card">
    <div class="card-header"><h2>Jelentkezési adatok</h2></div>
    <div class="card-body">

      <?php if ($flash_error): ?>
        <div class="alert alert-error" style="margin-bottom:16px;"><?= e($flash_error) ?></div>
      <?php endif; ?>

      <?php if ($memberLoggedIn): ?>
      <!-- Bejelentkezett tag -->
      <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:10px;">
          <span style="font-size:18px;">✅</span>
          <div>
            <strong>Bejelentkezve: <?= e($memberUser['lastname'] . ' ' . $memberUser['firstname']) ?></strong>
            <div style="color:var(--text-muted);font-size:12px;margin-top:2px;"><?= e($memberUser['email']) ?></div>
          </div>
        </div>
        <button type="button" id="public-logout-btn"
                style="background:none;border:1px solid #bbf7d0;border-radius:6px;padding:5px 12px;font-size:12px;cursor:pointer;color:#15803d;white-space:nowrap;">
          Kijelentkezés
        </button>
      </div>
      <form method="post" action="<?= BASE_URL ?>/actions/future-tour-apply.php">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="tour_id" value="<?= (int)$id ?>">
        <input type="hidden" name="public_redirect" value="1">

        <hr style="border:none;border-top:1px solid var(--border);margin:20px 0;">

        <?php if ($fieldEnabled('departure_city')): ?>
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;">Honnan indulnál? <span style="color:var(--danger)">*</span></label>
          <input type="text" name="departure_city" required placeholder="pl. Budapest XIII. kerület" style="margin-top:6px;width:100%;">
          <small style="display:block;color:var(--text-muted);font-size:11.5px;margin-top:4px;">Budapest esetén a kerületet is add meg!</small>
        </div>
        <?php endif; ?>

        <?php if ($fieldEnabled('car_available')): ?>
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;">Tudsz autóval jönni?</label>
          <div style="display:flex;gap:16px;margin-top:6px;">
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;"><input type="radio" name="car_available" value="1" id="car-yes"> Igen</label>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;"><input type="radio" name="car_available" value="0" id="car-no" checked> Nem</label>
          </div>
        </div>
        <div id="passengers-row" style="margin-bottom:16px;display:none;">
          <div class="form-group">
            <label style="font-size:13px;font-weight:600;">Ha igen, hány hely van melletted?</label>
            <input type="number" name="passengers" min="0" max="10" value="0" style="width:80px;margin-top:6px;">
            <small style="display:block;color:var(--text-muted);font-size:11.5px;margin-top:4px;">Ha már megvan, hogy kivel utazol, akkor is a maximum számot írd be, és majd a megjegyzésnél jelezd, hogy ki az utasod.</small>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($fieldEnabled('sharing_room')): ?>
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;">Szükség esetén aludnál egy helyen mással?</label>
          <select name="sharing_room" style="margin-top:6px;width:100%;">
            <option value="same_gender">Igen, de csak azonos neművel</option>
            <option value="yes">Igen</option>
            <option value="no">Nem</option>
          </select>
        </div>
        <?php endif; ?>

        <?php if ($fieldEnabled('notes')): ?>
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;">Megjegyzések</label>
          <textarea name="notes" rows="3" placeholder="Egyéb megjegyzés, kérés…" style="margin-top:6px;"></textarea>
        </div>
        <?php endif; ?>

        <?php foreach ($customFields as $cf): ?>
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;"><?= e($cf['field_name']) ?></label>
          <?php if ($cf['field_type'] === 'textarea'): ?>
            <textarea name="custom_field_<?= (int)$cf['id'] ?>" rows="2" style="margin-top:6px;"></textarea>
          <?php elseif ($cf['field_type'] === 'checkbox'): ?>
            <label style="display:flex;align-items:center;gap:8px;margin-top:8px;font-weight:normal;cursor:pointer;">
              <input type="checkbox" name="custom_field_<?= (int)$cf['id'] ?>" value="1"> Igen
            </label>
          <?php elseif ($cf['field_type'] === 'select' && !empty($cf['field_options'])): ?>
            <select name="custom_field_<?= (int)$cf['id'] ?>" style="margin-top:6px;width:100%;">
              <option value="">— válassz —</option>
              <?php foreach (array_map('trim', explode(',', $cf['field_options'])) as $opt): ?>
                <?php if ($opt !== ''): ?><option value="<?= e($opt) ?>"><?= e($opt) ?></option><?php endif; ?>
              <?php endforeach; ?>
            </select>
          <?php elseif ($cf['field_type'] === 'number'): ?>
            <input type="number" name="custom_field_<?= (int)$cf['id'] ?>" style="margin-top:6px;">
          <?php else: ?>
            <input type="text" name="custom_field_<?= (int)$cf['id'] ?>" style="margin-top:6px;">
          <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div style="margin-top:24px;">
          <button type="submit" class="btn btn-primary" style="width:100%;padding:13px;font-size:15px;">Jelentkezés elküldése</button>
        </div>
      </form>

      <?php elseif (!empty($tour['requires_membership'])): ?>
      <!-- Tagság szükséges: bejelentkezés + tagság kérelem -->
      <div style="background:#fffbeb;border:1px solid #f59e0b;border-radius:8px;padding:14px 16px;margin-bottom:16px;font-size:13.5px;color:#92400e;line-height:1.55;">
        <strong>Ez a túra csak az egyesület tagjai számára érhető el.</strong><br>
        <span style="color:#b45309;">Ha már tag vagy, lépj be. Ha még nem vagy tag, itt kérheted felvételedet az egyesületbe.</span>
        <div style="margin-top:10px;padding-top:10px;border-top:1px solid #f59e0b;font-size:13px;">
          ⚠ <strong>Fontos:</strong> A tagság csak az éves tagdíj befizetésével válik érvényessé. Az éves tagdíj összege: <strong>5 000 Ft</strong>.
        </div>
      </div>

      <!-- Bejelentkezés panel -->
      <div id="public-login-panel" style="background:#f8fafc;border:1px solid var(--border);border-radius:8px;padding:14px 16px;margin-bottom:16px;">
        <div id="public-login-teaser" style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
          <span style="font-size:13px;color:var(--text-muted);">Ha már tag vagy, jelentkezz be:</span>
          <button type="button" id="public-login-toggle" style="background:#29776f;color:#fff;border:none;border-radius:6px;padding:6px 14px;font-size:13px;cursor:pointer;font-weight:600;">Bejelentkezés</button>
        </div>
        <div id="public-login-form-wrap" style="display:none;margin-top:12px;">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;" class="login-fields-grid">
            <div class="form-group">
              <label style="font-size:12.5px;font-weight:600;">Felhasználónév vagy e-mail</label>
              <input type="text" id="public-login-user" autocomplete="username" placeholder="felhasznalonev" style="margin-top:4px;">
            </div>
            <div class="form-group">
              <label style="font-size:12.5px;font-weight:600;">Jelszó</label>
              <input type="password" id="public-login-pass" autocomplete="current-password" placeholder="••••••••" style="margin-top:4px;">
            </div>
          </div>
          <div style="display:flex;gap:8px;align-items:center;">
            <button type="button" id="public-login-submit" style="background:#29776f;color:#fff;border:none;border-radius:6px;padding:7px 18px;font-size:13px;cursor:pointer;font-weight:600;">Bejelentkezés</button>
            <button type="button" id="public-login-cancel" style="background:none;border:none;font-size:13px;color:var(--text-muted);cursor:pointer;padding:4px 6px;">Mégse</button>
          </div>
          <div id="public-login-err" style="display:none;color:var(--danger,#dc2626);font-size:13px;margin-top:8px;"></div>
        </div>
      </div>

      <div style="display:flex;align-items:center;gap:10px;margin:20px 0;">
        <hr style="flex:1;border:none;border-top:1px solid var(--border);margin:0;">
        <span style="font-size:12.5px;color:var(--text-muted);white-space:nowrap;">Ha még nem vagy tag:</span>
        <hr style="flex:1;border:none;border-top:1px solid var(--border);margin:0;">
      </div>

      <form method="post" action="<?= BASE_URL ?>/actions/join-submit.php" id="join-apply-form">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="tour_id" value="<?= (int)$id ?>">


        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;" class="guest-id-grid">
          <div class="form-group"><label style="font-size:13px;font-weight:600;">Vezetéknév <span style="color:var(--danger)">*</span></label><input type="text" name="lastname" required style="margin-top:6px;" value="<?= e($joinOld['lastname'] ?? '') ?>"></div>
          <div class="form-group"><label style="font-size:13px;font-weight:600;">Keresztnév <span style="color:var(--danger)">*</span></label><input type="text" name="firstname" required style="margin-top:6px;" value="<?= e($joinOld['firstname'] ?? '') ?>"></div>
          <div class="form-group"><label style="font-size:13px;font-weight:600;">E-mail cím <span style="color:var(--danger)">*</span></label><input type="email" name="email" required style="margin-top:6px;" value="<?= e($joinOld['email'] ?? '') ?>"></div>
          <div class="form-group"><label style="font-size:13px;font-weight:600;">Telefonszám</label><input type="tel" name="phone" style="margin-top:6px;" value="<?= e($joinOld['phone'] ?? '') ?>"></div>
          <div class="form-group"><label style="font-size:13px;font-weight:600;">Születési dátum <span style="color:var(--danger)">*</span></label><input type="date" name="dateofbirth" required style="margin-top:6px;" value="<?= e($joinOld['dateofbirth'] ?? '') ?>"></div>
          <div class="form-group"><label style="font-size:13px;font-weight:600;">Irányítószám <span style="color:var(--danger)">*</span></label><input type="text" name="zipcode" required style="margin-top:6px;" value="<?= e($joinOld['zipcode'] ?? '') ?>"></div>
          <div class="form-group"><label style="font-size:13px;font-weight:600;">Város <span style="color:var(--danger)">*</span></label><input type="text" name="city" required style="margin-top:6px;" value="<?= e($joinOld['city'] ?? '') ?>"></div>
          <div class="form-group"><label style="font-size:13px;font-weight:600;">Lakcím <span style="color:var(--danger)">*</span></label><input type="text" name="address" required style="margin-top:6px;" value="<?= e($joinOld['address'] ?? '') ?>"></div>
        </div>
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;">Megjegyzés / Motiváció</label>
          <textarea name="message" rows="2" style="margin-top:6px;resize:vertical;"><?= e($joinOld['message'] ?? '') ?></textarea>
        </div>
        <div style="margin-bottom:20px;">
          <label style="display:flex;align-items:flex-start;gap:8px;font-size:12.5px;cursor:pointer;margin-bottom:8px;font-weight:normal;">
            <input type="checkbox" name="consent_email" value="1" style="margin-top:2px;">
            <span>Hozzájárulok, hogy e-mail-címem az események szervezésekor a levelezésekben nyilvánosan megjelenjen.</span>
          </label>
          <label style="display:flex;align-items:flex-start;gap:8px;font-size:12.5px;cursor:pointer;margin-bottom:8px;font-weight:normal;">
            <input type="checkbox" name="consent_photo" value="1" style="margin-top:2px;">
            <span>Hozzájárulok, hogy az egyesület eseményein rólam készült fotók a L.O.T.E. weboldalán és social-media felületeken megjelenjenek.</span>
          </label>
          <label style="display:flex;align-items:flex-start;gap:8px;font-size:12.5px;cursor:pointer;font-weight:normal;">
            <input type="checkbox" name="consent_rules" value="1" required style="margin-top:2px;">
            <span>Elolvastam és elfogadom az <a href="https://www.lizzard.hu/wp-content/uploads/2018/05/gdpr_adatvedelem_lote_20150521.pdf" target="_blank" rel="noopener noreferrer">Adatvédelmi Tájékoztatóban</a>, az Alapszabályban és a Részvételi feltételekben foglaltakat. <strong style="color:#d97706;">— Kötelező</strong></span>
          </label>
        </div>
        <div style="margin-top:24px;">
          <button type="submit" class="btn btn-primary" style="width:100%;padding:13px;font-size:15px;">Tagságra jelentkezés elküldése</button>
        </div>
      </form>

      <?php else: ?>
      <!-- Vendég: bejelentkezés panel + guest form -->
      <div id="public-login-panel" style="background:#f8fafc;border:1px solid var(--border);border-radius:8px;padding:14px 16px;margin-bottom:20px;">
        <div id="public-login-teaser" style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
          <span style="font-size:13px;color:var(--text-muted);">Ha tag vagy, jelentkezz be a kedvezményekért:</span>
          <button type="button" id="public-login-toggle" style="background:#29776f;color:#fff;border:none;border-radius:6px;padding:6px 14px;font-size:13px;cursor:pointer;font-weight:600;">Bejelentkezés</button>
        </div>
        <div id="public-login-form-wrap" style="display:none;margin-top:12px;">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;" class="login-fields-grid">
            <div class="form-group">
              <label style="font-size:12.5px;font-weight:600;">Felhasználónév vagy e-mail</label>
              <input type="text" id="public-login-user" autocomplete="username" placeholder="felhasznalonev" style="margin-top:4px;">
            </div>
            <div class="form-group">
              <label style="font-size:12.5px;font-weight:600;">Jelszó</label>
              <input type="password" id="public-login-pass" autocomplete="current-password" placeholder="••••••••" style="margin-top:4px;">
            </div>
          </div>
          <div style="display:flex;gap:8px;align-items:center;">
            <button type="button" id="public-login-submit" style="background:#29776f;color:#fff;border:none;border-radius:6px;padding:7px 18px;font-size:13px;cursor:pointer;font-weight:600;">Bejelentkezés</button>
            <button type="button" id="public-login-cancel" style="background:none;border:none;font-size:13px;color:var(--text-muted);cursor:pointer;padding:4px 6px;">Mégse</button>
          </div>
          <div id="public-login-err" style="display:none;color:var(--danger,#dc2626);font-size:13px;margin-top:8px;"></div>
        </div>
      </div>

      <form method="post" action="<?= BASE_URL ?>/actions/future-tour-apply-guest.php" id="guest-apply-form">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="tour_id" value="<?= (int)$id ?>">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;" class="guest-id-grid">
          <div class="form-group"><label style="font-size:13px;font-weight:600;">Teljes név <span style="color:var(--danger)">*</span></label><input type="text" name="guest_name" required placeholder="pl. Kovács János" style="margin-top:6px;" value="<?= e($_GET['name'] ?? '') ?>"></div>
          <div class="form-group"><label style="font-size:13px;font-weight:600;">E-mail cím <span style="color:var(--danger)">*</span></label><input type="email" name="guest_email" required placeholder="pelda@email.hu" style="margin-top:6px;" value="<?= e($_GET['email'] ?? '') ?>"></div>
        </div>
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;">Telefonszám</label>
          <input type="tel" name="guest_phone" placeholder="+36 30 123 4567" style="margin-top:6px;">
        </div>

        <hr style="border:none;border-top:1px solid var(--border);margin:20px 0;">

        <?php if ($fieldEnabled('departure_city')): ?>
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;">Honnan indulnál? <span style="color:var(--danger)">*</span></label>
          <input type="text" name="departure_city" required placeholder="pl. Budapest XIII. kerület" style="margin-top:6px;width:100%;">
          <small style="display:block;color:var(--text-muted);font-size:11.5px;margin-top:4px;">Budapest esetén a kerületet is add meg!</small>
        </div>
        <?php endif; ?>

        <?php if ($fieldEnabled('car_available')): ?>
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;">Tudsz autóval jönni?</label>
          <div style="display:flex;gap:16px;margin-top:6px;">
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;"><input type="radio" name="car_available" value="1" id="car-yes"> Igen</label>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;"><input type="radio" name="car_available" value="0" id="car-no" checked> Nem</label>
          </div>
        </div>
        <div id="passengers-row" style="margin-bottom:16px;display:none;">
          <div class="form-group">
            <label style="font-size:13px;font-weight:600;">Ha igen, hány hely van melletted?</label>
            <input type="number" name="passengers" min="0" max="10" value="0" style="width:80px;margin-top:6px;">
            <small style="display:block;color:var(--text-muted);font-size:11.5px;margin-top:4px;">Ha már megvan, hogy kivel utazol, akkor is a maximum számot írd be.</small>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($fieldEnabled('sharing_room')): ?>
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;">Szükség esetén aludnál egy helyen mással?</label>
          <select name="sharing_room" style="margin-top:6px;width:100%;">
            <option value="same_gender">Igen, de csak azonos neművel</option>
            <option value="yes">Igen</option>
            <option value="no">Nem</option>
          </select>
        </div>
        <?php endif; ?>

        <?php if ($fieldEnabled('notes')): ?>
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;">Megjegyzések</label>
          <textarea name="notes" rows="3" placeholder="Egyéb megjegyzés, kérés…" style="margin-top:6px;"></textarea>
        </div>
        <?php endif; ?>

        <?php foreach ($customFields as $cf): ?>
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;"><?= e($cf['field_name']) ?></label>
          <?php if ($cf['field_type'] === 'textarea'): ?>
            <textarea name="custom_field_<?= (int)$cf['id'] ?>" rows="2" style="margin-top:6px;"></textarea>
          <?php elseif ($cf['field_type'] === 'checkbox'): ?>
            <label style="display:flex;align-items:center;gap:8px;margin-top:8px;font-weight:normal;cursor:pointer;"><input type="checkbox" name="custom_field_<?= (int)$cf['id'] ?>" value="1"> Igen</label>
          <?php elseif ($cf['field_type'] === 'select' && !empty($cf['field_options'])): ?>
            <select name="custom_field_<?= (int)$cf['id'] ?>" style="margin-top:6px;width:100%;">
              <option value="">— válassz —</option>
              <?php foreach (array_map('trim', explode(',', $cf['field_options'])) as $opt): ?>
                <?php if ($opt !== ''): ?><option value="<?= e($opt) ?>"><?= e($opt) ?></option><?php endif; ?>
              <?php endforeach; ?>
            </select>
          <?php elseif ($cf['field_type'] === 'number'): ?>
            <input type="number" name="custom_field_<?= (int)$cf['id'] ?>" style="margin-top:6px;">
          <?php else: ?>
            <input type="text" name="custom_field_<?= (int)$cf['id'] ?>" style="margin-top:6px;">
          <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div style="margin-top:24px;">
          <button type="submit" class="btn btn-primary" style="width:100%;padding:13px;font-size:15px;">Jelentkezés elküldése</button>
        </div>
      </form>
      <?php endif; ?>

    </div>
  </div>
  <?php endif; ?>
</div>

<script>
document.querySelectorAll('input[name="car_available"]').forEach(function(r) {
  r.addEventListener('change', function() {
    var row = document.getElementById('passengers-row');
    if (row) row.style.display = r.value === '1' ? 'block' : 'none';
  });
});

(function() {
  var logoutBtn = document.getElementById('public-logout-btn');
  if (!logoutBtn) return;
  logoutBtn.addEventListener('click', function() {
    logoutBtn.disabled    = true;
    logoutBtn.textContent = 'Kijelentkezés…';
    fetch(<?= json_encode(BASE_URL . '/api/member-auth.php') ?> + '?action=logout', { credentials: 'include' })
      .then(function() { location.reload(); })
      .catch(function() { location.reload(); });
  });
})();

(function() {
  var panel     = document.getElementById('public-login-panel');
  if (!panel) return;
  var teaser    = document.getElementById('public-login-teaser');
  var formWrap  = document.getElementById('public-login-form-wrap');
  var toggleBtn = document.getElementById('public-login-toggle');
  var cancelBtn = document.getElementById('public-login-cancel');
  var submitBtn = document.getElementById('public-login-submit');
  var userInput = document.getElementById('public-login-user');
  var passInput = document.getElementById('public-login-pass');
  var errBox    = document.getElementById('public-login-err');
  var authUrl   = <?= json_encode(BASE_URL . '/api/member-auth.php') ?>;

  toggleBtn.addEventListener('click', function() {
    teaser.style.display   = 'none';
    formWrap.style.display = 'block';
    userInput.focus();
  });
  cancelBtn.addEventListener('click', function() {
    teaser.style.display   = 'flex';
    formWrap.style.display = 'none';
    errBox.style.display   = 'none';
    userInput.value = ''; passInput.value = '';
  });
  submitBtn.addEventListener('click', doLogin);
  [userInput, passInput].forEach(function(inp) {
    inp.addEventListener('keydown', function(e) { if (e.key === 'Enter') doLogin(); });
  });

  function doLogin() {
    errBox.style.display  = 'none';
    submitBtn.disabled    = true;
    submitBtn.textContent = 'Bejelentkezés…';
    var body = new URLSearchParams({ login: userInput.value.trim(), password: passInput.value });
    fetch(authUrl, { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.success) { location.reload(); }
        else {
          errBox.textContent   = d.error || 'Hiba történt.';
          errBox.style.display = 'block';
          submitBtn.disabled   = false; submitBtn.textContent = 'Bejelentkezés';
        }
      })
      .catch(function() {
        errBox.textContent   = 'Hálózati hiba. Kérjük próbáld újra.';
        errBox.style.display = 'block';
        submitBtn.disabled   = false; submitBtn.textContent = 'Bejelentkezés';
      });
  }
})();

(function() {
  var form = document.getElementById('guest-apply-form');
  if (!form) return;
  var emailInput = form.querySelector('input[name="guest_email"]');
  var submitBtn  = form.querySelector('button[type="submit"]');
  var baseUrl    = <?= json_encode(BASE_URL) ?>;
  var inlineErr  = null;

  function removeInlineErr() { if (inlineErr) { inlineErr.remove(); inlineErr = null; } }
  function showInlineErr(msg, anchor) {
    removeInlineErr();
    inlineErr = document.createElement('p');
    inlineErr.style.cssText = 'color:var(--danger,#dc2626);font-size:13px;margin:6px 0 0;';
    inlineErr.textContent = msg;
    anchor.parentNode.appendChild(inlineErr);
  }
  function showSuccess() {
    var card = form.closest('.card');
    if (!card) return;
    card.innerHTML = '<div class="card-body" style="text-align:center;padding:36px 24px;">' +
      '<div style="font-size:48px;margin-bottom:16px;">✅</div>' +
      '<h2 style="margin:0 0 10px;color:var(--primary);">Jelentkezés elküldve!</h2>' +
      '<p style="color:var(--text-muted);font-size:14px;line-height:1.6;margin:0 0 20px;">Köszönjük a jelentkezésedet! Hamarosan visszajelzünk e-mailben.</p>' +
      '<a href="<?= BASE_URL ?>/public/turanyptar.php" class="btn btn-ghost">← Vissza a túranaptárhoz</a></div>';
  }
  function submitForm() {
    submitBtn.disabled    = true;
    submitBtn.textContent = 'Küldés…';
    fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'include' })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.success) { showSuccess(); }
        else { submitBtn.disabled = false; submitBtn.textContent = 'Jelentkezés elküldése'; showInlineErr(d.error || 'Hiba történt.', submitBtn); }
      })
      .catch(function() { submitBtn.disabled = false; submitBtn.textContent = 'Jelentkezés elküldése'; showInlineErr('Hálózati hiba.', submitBtn); });
  }

  if (emailInput) emailInput.addEventListener('input', removeInlineErr);
  form.addEventListener('submit', function(e) {
    e.preventDefault(); removeInlineErr();
    var email = emailInput ? emailInput.value.trim() : '';
    if (!email) { submitForm(); return; }
    fetch(baseUrl + '/api/check-member-email.php?email=' + encodeURIComponent(email), { credentials: 'include' })
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.registered) { showInlineErr('Ezzel az e-mail címmel már van regisztrált felhasználó – lépj be a jelentkezéshez!', emailInput); emailInput.focus(); }
        else { submitForm(); }
      })
      .catch(function() { submitForm(); });
  });
})();
</script>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
