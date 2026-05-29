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
  <style>
    body { background: var(--bg-outer, #f5efe4); min-height: 100vh; padding: 32px 16px; margin: 0; }
    .public-wrap { max-width: 620px; margin: 0 auto; }
    .public-header { text-align: center; margin-bottom: 28px; }
    .public-header img { width: 56px; height: 56px; object-fit: contain; margin-bottom: 10px; }
    .public-header .app-name { font-size: 20px; font-weight: 700; color: var(--primary); }
    .public-header .app-sub  { font-size: 13px; color: var(--text-muted); margin-top: 2px; }
    <?php if ($embed): ?>
    body { background: transparent !important; padding: 4px 0 0 !important; min-height: 0 !important; }
    .public-wrap { max-width: 100% !important; }
    .card { box-shadow: none !important; border-radius: 0 !important; border: none !important; }
    .card-header { padding: 10px 20px !important; }
    .card-body { padding-top: 12px !important; }
    <?php endif; ?>
  </style>
</head>
<body>
<div class="public-wrap">

  <?php if (!$embed): ?>
  <div class="public-header">
    <img src="<?= BASE_URL ?>/assets/img/lizzard_logo.png" alt="<?= APP_NAME ?>">
    <div class="app-name"><?= APP_NAME ?></div>
    <div class="app-sub">Túrajelentkezés</div>
  </div>

  <!-- Tour info card (csak nem-beágyazott módban) -->
  <div class="card" style="margin-bottom:16px;">
    <div class="card-body">
      <h2 style="margin:0 0 14px;"><?= e($tour['name']) ?></h2>
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:1px;background:var(--border);border:1px solid var(--border);border-radius:8px;overflow:hidden;">
        <div style="padding:12px 14px;background:var(--bg,#fff);">
          <div style="font-size:11px;text-transform:uppercase;color:var(--text-muted);letter-spacing:.05em;margin-bottom:4px;">Kezdés</div>
          <div style="font-weight:600;"><?= $tour['start_date'] ? formatDate($tour['start_date']) : '—' ?></div>
        </div>
        <div style="padding:12px 14px;background:var(--bg,#fff);">
          <div style="font-size:11px;text-transform:uppercase;color:var(--text-muted);letter-spacing:.05em;margin-bottom:4px;">Időtartam</div>
          <div style="font-weight:600;"><?= (int)$tour['num_days'] ?> nap</div>
        </div>
        <?php if (!empty($tour['country_name'])): ?>
        <div style="padding:12px 14px;background:var(--bg,#fff);">
          <div style="font-size:11px;text-transform:uppercase;color:var(--text-muted);letter-spacing:.05em;margin-bottom:4px;">Helyszín</div>
          <div style="font-weight:600;"><?= e($tour['country_name']) ?><?= !empty($tour['region']) ? ' · ' . e($tour['region']) : '' ?></div>
        </div>
        <?php endif; ?>
        <?php if ($tour['participation_fee'] !== null): ?>
        <div style="padding:12px 14px;background:var(--bg,#fff);">
          <div style="font-size:11px;text-transform:uppercase;color:var(--text-muted);letter-spacing:.05em;margin-bottom:4px;">Részvételi díj</div>
          <div style="font-weight:600;"><?= number_format((float)$tour['participation_fee'], 0, ',', ' ') ?> Ft</div>
        </div>
        <?php endif; ?>
      </div>
      <?php if (!empty($tour['description'])): ?>
        <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border);color:var(--text);line-height:1.6;white-space:pre-wrap;font-size:14px;"><?= e($tour['description']) ?></div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($done): ?>
  <!-- Success state -->
  <div class="card">
    <div class="card-body" style="text-align:center;padding:36px 24px;">
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
      <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:12px;display:flex;align-items:center;gap:10px;">
        <span style="font-size:18px;">✅</span>
        <div>
          <strong>Bejelentkezve: <?= e($memberUser['lastname'] . ' ' . $memberUser['firstname']) ?></strong>
          <div style="color:var(--text-muted);font-size:12px;margin-top:2px;"><?= e($memberUser['email']) ?></div>
        </div>
      </div>
      <form method="post" action="<?= BASE_URL ?>/actions/future-tour-apply.php">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="tour_id" value="<?= (int)$id ?>">
        <input type="hidden" name="public_redirect" value="1">

      <?php else: ?>
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

        <!-- Sharing room -->
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;">Szükség esetén aludnál egy helyen mással?</label>
          <select name="sharing_room" style="margin-top:6px;width:100%;">
            <option value="same_gender">Igen, de csak azonos neművel</option>
            <option value="yes">Igen</option>
            <option value="no">Nem</option>
          </select>
        </div>

        <!-- Notes -->
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;">Megjegyzések</label>
          <textarea name="notes" rows="3" placeholder="Egyéb megjegyzés, kérés…" style="margin-top:6px;"></textarea>
        </div>

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

<style>
  @media (max-width: 480px) {
    .guest-id-grid { grid-template-columns: 1fr !important; }
  }
</style>
<script>
document.querySelectorAll('input[name="car_available"]').forEach(r => {
  r.addEventListener('change', () => {
    document.getElementById('passengers-row').style.display = r.value === '1' ? 'block' : 'none';
  });
});

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
