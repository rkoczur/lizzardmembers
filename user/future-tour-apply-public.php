<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';

$pdo = getDb();
ensureFutureToursSchema($pdo);

// Detect logged-in member (session set via api/member-auth.php or normal login)
$memberLoggedIn = isLoggedIn();
$memberUser     = null;
if ($memberLoggedIn) {
    $stmt = $pdo->prepare("SELECT id, firstname, lastname, email FROM users WHERE id = ? AND active = 1 LIMIT 1");
    $stmt->execute([getCurrentUserId()]);
    $memberUser = $stmt->fetch();
    if (!$memberUser) {
        $memberLoggedIn = false;
    }
}

$id   = (int)($_GET['id'] ?? 0);
$done = isset($_GET['done']);

if (!$id) {
    header('Location: ' . BASE_URL . '/login.php');
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
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$customFieldsStmt = $pdo->prepare("SELECT * FROM future_tour_custom_fields WHERE future_tour_id = ? ORDER BY sort_order ASC, id ASC");
$customFieldsStmt->execute([$id]);
$customFields = $customFieldsStmt->fetchAll();

$disabledFields  = json_decode($tour['disabled_standard_fields'] ?? '[]', true) ?: [];
$fieldEnabled    = fn(string $f): bool => !in_array($f, $disabledFields, true);

$flash_error = getFlash('error');
$embed = !empty($_GET['embed']); // beágyazott mód (WP plugin iframe)
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Jelentkezés – <?= e($tour['name']) ?> — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="<?= $embed ? 'public-page embed-mode' : 'public-page' ?>">
<div class="public-wrap">

  <?php if (!$embed): ?>
  <div class="public-header">
    <img src="<?= BASE_URL ?>/assets/img/logo-sotet.png" alt="<?= APP_NAME ?>">
    <div class="app-name"><?= APP_NAME ?></div>
    <div class="app-sub">Túrajelentkezés</div>
  </div>

  <!-- Tour info card (csak nem-beágyazott módban) -->
  <div class="card" style="margin-bottom:16px;background:#29776f;color:#fff;border:none;">
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
      <?php if (!empty($tour['description'])): ?>
        <div style="margin-top:14px;padding-top:14px;border-top:1px solid rgba(255,255,255,.3);color:rgba(255,255,255,.92);line-height:1.6;white-space:pre-wrap;font-size:14px;"><?= e($tour['description']) ?></div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($done): ?>
  <!-- Success state -->
  <div class="card">
    <div class="card-body card-body-center" style="padding:36px 24px;">
      <div style="font-size:48px;margin-bottom:16px;">✅</div>
      <h2 style="margin:0 0 10px;color:var(--primary);">Jelentkezés elküldve!</h2>
      <p style="color:var(--text-muted);font-size:14px;line-height:1.6;margin:0;">
        Köszönjük a jelentkezésedet! Hamarosan visszajelzünk e-mailben.<br>
        Az adminisztrátor jóváhagyása után véglegesítjük a részvételed.
      </p>
    </div>
  </div>

  <?php else: ?>
  <!-- Application form -->
  <div class="card">
    <div class="card-header"><h2>Jelentkezési adatok</h2></div>
    <div class="card-body">

      <?php if ($flash_error): ?>
        <div class="alert alert-error" style="margin-bottom:16px;"><?= e($flash_error) ?></div>
      <?php endif; ?>

      <?php if ($memberLoggedIn): ?>
      <!-- Logged-in member form -->
      <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
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

      <?php else: ?>
      <!-- Login panel -->
      <div id="public-login-panel" style="background:#f8fafc;border:1px solid var(--border);border-radius:8px;padding:14px 16px;margin-bottom:16px;">
        <div id="public-login-teaser" style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
          <span style="font-size:13px;color:var(--text-muted);">Ha már tag vagy, jelentkezz be:</span>
          <button type="button" id="public-login-toggle"
                  style="background:#29776f;color:#fff;border:none;border-radius:6px;padding:6px 14px;font-size:13px;cursor:pointer;font-weight:600;">
            Bejelentkezés
          </button>
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
            <button type="button" id="public-login-submit"
                    style="background:#29776f;color:#fff;border:none;border-radius:6px;padding:7px 18px;font-size:13px;cursor:pointer;font-weight:600;">
              Bejelentkezés
            </button>
            <button type="button" id="public-login-cancel"
                    style="background:none;border:none;font-size:13px;color:var(--text-muted);cursor:pointer;padding:4px 6px;">
              Mégse
            </button>
          </div>
          <div id="public-login-err" style="display:none;color:var(--danger,#dc2626);font-size:13px;margin-top:8px;"></div>
        </div>
      </div>

      <!-- Guest form -->
      <form method="post" action="<?= BASE_URL ?>/actions/future-tour-apply-guest.php" id="guest-apply-form">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="tour_id" value="<?= (int)$id ?>">
        <?php if ($embed): ?>
        <input type="hidden" name="embed" value="1">
        <?php endif; ?>

        <!-- Guest identity -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;" class="guest-id-grid">
          <div class="form-group">
            <label style="font-size:13px;font-weight:600;">Teljes név <span style="color:var(--danger)">*</span></label>
            <input type="text" name="guest_name" required placeholder="pl. Kovács János" style="margin-top:6px;" value="<?= e($_GET['name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label style="font-size:13px;font-weight:600;">E-mail cím <span style="color:var(--danger)">*</span></label>
            <input type="email" name="guest_email" required placeholder="pelda@email.hu" style="margin-top:6px;" value="<?= e($_GET['email'] ?? '') ?>">
          </div>
        </div>
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;">Telefonszám</label>
          <input type="tel" name="guest_phone" placeholder="+36 30 123 4567" style="margin-top:6px;">
        </div>

      <?php endif; ?>

        <hr style="border:none;border-top:1px solid var(--border);margin:20px 0;">

        <?php if ($fieldEnabled('departure_city')): ?>
        <!-- Departure city -->
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;">Honnan indulnál? <span style="color:var(--danger)">*</span></label>
          <input type="text" name="departure_city" required placeholder="pl. Budapest XIII. kerület" style="margin-top:6px;width:100%;">
          <small style="display:block;color:var(--text-muted);font-size:11.5px;margin-top:4px;">Budapest esetén a kerületet is add meg!</small>
        </div>
        <?php endif; ?>

        <?php if ($fieldEnabled('car_available')): ?>
        <!-- Car -->
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;">Tudsz autóval jönni?</label>
          <div style="display:flex;gap:16px;margin-top:6px;">
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;">
              <input type="radio" name="car_available" value="1" id="car-yes"> Igen
            </label>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;">
              <input type="radio" name="car_available" value="0" id="car-no" checked> Nem
            </label>
          </div>
        </div>
        <div id="passengers-row" style="margin-bottom:16px;display:none;">
          <div class="form-group">
            <label style="font-size:13px;font-weight:600;">Ha igen, hány hely van melletted?</label>
            <input type="number" name="passengers" min="0" max="10" value="0" style="width:80px;margin-top:6px;">
            <small style="display:block;color:var(--text-muted);font-size:11.5px;margin-top:4px;">
              Ha már megvan, hogy kivel utazol, akkor is a maximum számot írd be, és majd a megjegyzésnél jelezd, hogy ki az utasod.
            </small>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($fieldEnabled('sharing_room')): ?>
        <!-- Sharing room -->
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
        <!-- Notes -->
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;">Megjegyzések</label>
          <textarea name="notes" rows="3" placeholder="Egyéb megjegyzés, kérés…" style="margin-top:6px;"></textarea>
        </div>
        <?php endif; ?>

        <!-- Custom fields -->
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
                <?php if ($opt !== ''): ?>
                  <option value="<?= e($opt) ?>"><?= e($opt) ?></option>
                <?php endif; ?>
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
    </div>
  </div>
  <?php endif; ?>

</div>

<script>
document.querySelectorAll('input[name="car_available"]').forEach(r => {
  r.addEventListener('change', () => {
    var row = document.getElementById('passengers-row');
    if (row) row.style.display = r.value === '1' ? 'block' : 'none';
  });
});

// ---- Kijelentkezés (bejelentkezett állapotban) ----
(function () {
  var logoutBtn = document.getElementById('public-logout-btn');
  if (!logoutBtn) return;
  logoutBtn.addEventListener('click', function () {
    logoutBtn.disabled    = true;
    logoutBtn.textContent = 'Kijelentkezés…';
    fetch(<?= json_encode(BASE_URL . '/api/member-auth.php') ?> + '?action=logout', { credentials: 'include' })
      .then(function () { location.reload(); })
      .catch(function () { location.reload(); });
  });
})();

// ---- Bejelentkezés panel (vendég állapotban) ----
(function () {
  var panel      = document.getElementById('public-login-panel');
  if (!panel) return;

  var teaser     = document.getElementById('public-login-teaser');
  var formWrap   = document.getElementById('public-login-form-wrap');
  var toggleBtn  = document.getElementById('public-login-toggle');
  var cancelBtn  = document.getElementById('public-login-cancel');
  var submitBtn  = document.getElementById('public-login-submit');
  var userInput  = document.getElementById('public-login-user');
  var passInput  = document.getElementById('public-login-pass');
  var errBox     = document.getElementById('public-login-err');
  var authUrl    = <?= json_encode(BASE_URL . '/api/member-auth.php') ?>;

  toggleBtn.addEventListener('click', function () {
    teaser.style.display   = 'none';
    formWrap.style.display = 'block';
    userInput.focus();
  });

  cancelBtn.addEventListener('click', function () {
    teaser.style.display   = 'flex';
    formWrap.style.display = 'none';
    errBox.style.display   = 'none';
    userInput.value = '';
    passInput.value = '';
  });

  submitBtn.addEventListener('click', doLogin);
  [userInput, passInput].forEach(function (inp) {
    inp.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') doLogin();
    });
  });

  function doLogin() {
    errBox.style.display   = 'none';
    submitBtn.disabled     = true;
    submitBtn.textContent  = 'Bejelentkezés…';

    var body = new URLSearchParams({
      login:    userInput.value.trim(),
      password: passInput.value,
    });

    fetch(authUrl, { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.success) {
          location.reload();
        } else {
          errBox.textContent    = d.error || 'Hiba történt.';
          errBox.style.display  = 'block';
          submitBtn.disabled    = false;
          submitBtn.textContent = 'Bejelentkezés';
        }
      })
      .catch(function () {
        errBox.textContent    = 'Hálózati hiba. Kérjük próbáld újra.';
        errBox.style.display  = 'block';
        submitBtn.disabled    = false;
        submitBtn.textContent = 'Bejelentkezés';
      });
  }
})();

(function () {
  var form = document.getElementById('guest-apply-form');
  if (!form) return;

  var emailInput = form.querySelector('input[name="guest_email"]');
  var submitBtn  = form.querySelector('button[type="submit"]');
  var baseUrl    = <?= json_encode(BASE_URL) ?>;
  var inlineErr  = null;

  function notifyResize() {
    if (window.parent !== window) {
      requestAnimationFrame(function() {
        window.parent.postMessage({ type: 'lote-ft-resize', height: document.documentElement.scrollHeight }, '*');
      });
    }
  }

  function removeInlineErr() {
    if (inlineErr) { inlineErr.remove(); inlineErr = null; }
  }

  function showInlineErr(msg, anchor) {
    removeInlineErr();
    inlineErr = document.createElement('p');
    inlineErr.style.cssText = 'color:var(--danger,#dc2626);font-size:13px;margin:6px 0 0;line-height:1.4;';
    inlineErr.textContent = msg;
    anchor.parentNode.appendChild(inlineErr);
    notifyResize();
  }

  function showSuccess() {
    var card = form.closest('.card');
    if (!card) return;
    card.innerHTML =
      '<div class="card-body" style="text-align:center;padding:36px 24px;">' +
      '<div style="font-size:48px;margin-bottom:16px;">✅</div>' +
      '<h2 style="margin:0 0 10px;color:var(--primary);">Jelentkezés elküldve!</h2>' +
      '<p style="color:var(--text-muted);font-size:14px;line-height:1.6;margin:0;">' +
      'Köszönjük a jelentkezésedet! Hamarosan visszajelzünk e-mailben.<br>' +
      'Az adminisztrátor jóváhagyása után véglegesítjük a részvételed.' +
      '</p></div>';
    notifyResize();
  }

  function submitForm() {
    submitBtn.disabled    = true;
    submitBtn.textContent = 'Küldés…';

    fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.success) {
          showSuccess();
        } else {
          submitBtn.disabled    = false;
          submitBtn.textContent = 'Jelentkezés elküldése';
          showInlineErr(d.error || 'Hiba történt. Kérjük próbáld újra.', submitBtn);
        }
      })
      .catch(function () {
        submitBtn.disabled    = false;
        submitBtn.textContent = 'Jelentkezés elküldése';
        showInlineErr('Hálózati hiba. Kérjük próbáld újra.', submitBtn);
      });
  }

  if (emailInput) emailInput.addEventListener('input', removeInlineErr);

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    removeInlineErr();

    var email = emailInput ? emailInput.value.trim() : '';
    if (!email) { submitForm(); return; }

    fetch(baseUrl + '/api/check-member-email.php?email=' + encodeURIComponent(email), { credentials: 'include' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.registered) {
          showInlineErr('Ezzel az e-mail címmel már van regisztrált felhasználó – lépj be a jelentkezéshez!', emailInput);
          emailInput.focus();
        } else {
          submitForm();
        }
      })
      .catch(function () { submitForm(); });
  });
})();
</script>
<?php if ($embed): ?>
<script>
// Magasság kommunikálása a szülő oldalnak (WP plugin iframe resize)
function loteNotifyHeight() {
  requestAnimationFrame(function() {
    var h = document.documentElement.scrollHeight;
    window.parent.postMessage({ type: 'lote-ft-resize', height: h }, '*');
  });
}
document.addEventListener('DOMContentLoaded', loteNotifyHeight);
new MutationObserver(loteNotifyHeight).observe(document.body, { childList: true, subtree: true, attributes: true });
window.addEventListener('load', loteNotifyHeight);
</script>
<?php endif; ?>
</body>
</html>
