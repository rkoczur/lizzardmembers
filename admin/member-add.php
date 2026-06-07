<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/user-schema.php';
requireLeader();
if (!isAdmin()) {
    flash('error', 'Nincs jogosultságod ehhez a művelethez.');
    header('Location: ' . BASE_URL . '/admin/members.php');
    exit;
}

$pdo = getDb();
ensureUserSchema($pdo);

$flash_error = getFlash('error');

$pageTitle  = 'Új tag regisztrálása';
$activePage = 'members';
include __DIR__ . '/../includes/admin-header.php';
?>

<?php if ($flash_error): ?>
  <div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="page-header">
  <div class="flex items-center gap-2">
    <a href="<?= BASE_URL ?>/admin/members.php" class="btn btn-secondary btn-sm">← Vissza</a>
    <h1>Új tag regisztrálása</h1>
  </div>
</div>

<div class="card" style="max-width:780px;">
  <div class="card-body">
    <form method="post" action="<?= BASE_URL ?>/actions/member-add.php" id="member-add-form">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

      <h3 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:14px;">Alapadatok</h3>
      <div class="form-grid">
        <div class="form-group">
          <label>Vezetéknév <span style="color:var(--danger)">*</span></label>
          <input type="text" name="lastname" value="<?= e($_SESSION['form_old']['lastname'] ?? '') ?>" required autofocus>
        </div>
        <div class="form-group">
          <label>Keresztnév <span style="color:var(--danger)">*</span></label>
          <input type="text" name="firstname" value="<?= e($_SESSION['form_old']['firstname'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Felhasználónév <span style="color:var(--danger)">*</span></label>
          <input type="text" name="username" value="<?= e($_SESSION['form_old']['username'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>E-mail <span style="color:var(--danger)">*</span></label>
          <input type="email" name="email" value="<?= e($_SESSION['form_old']['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Jelszó <span style="color:var(--danger)">*</span></label>
          <input type="password" name="password" required minlength="6">
        </div>
        <div class="form-group">
          <label>Jelszó megerősítése <span style="color:var(--danger)">*</span></label>
          <input type="password" name="password2" required>
        </div>
        <div class="form-group">
          <label>Születési dátum</label>
          <input type="date" name="dateofbirth" value="<?= e($_SESSION['form_old']['dateofbirth'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Pólóméret</label>
          <select name="tshirt_size">
            <option value="">— Válasszon —</option>
            <?php foreach (['XS','S','M','L','XL','XXL','XXXL'] as $sz): ?>
              <option value="<?= $sz ?>" <?= ($_SESSION['form_old']['tshirt_size'] ?? '') === $sz ? 'selected' : '' ?>><?= $sz ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Irányítószám</label>
          <input type="text" name="zipcode" value="<?= e($_SESSION['form_old']['zipcode'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Város</label>
          <input type="text" name="city" value="<?= e($_SESSION['form_old']['city'] ?? '') ?>">
        </div>
        <div class="form-group full">
          <label>Cím</label>
          <input type="text" name="address" value="<?= e($_SESSION['form_old']['address'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Telefonszám</label>
          <input type="tel" name="phone" value="<?= e($_SESSION['form_old']['phone'] ?? '') ?>">
        </div>
      </div>

      <h3 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin:24px 0 14px;padding-top:20px;border-top:1px solid var(--border);">Vészhelyzet esetén értesítendő</h3>
      <div class="form-grid">
        <div class="form-group">
          <label>Név</label>
          <input type="text" name="emergency_name" value="<?= e($_SESSION['form_old']['emergency_name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Kapcsolat</label>
          <select name="emergency_relation">
            <option value="">— Válasszon —</option>
            <?php foreach (['szülő','gyermek','testvér','egyéb'] as $rel): ?>
              <option value="<?= $rel ?>" <?= ($_SESSION['form_old']['emergency_relation'] ?? '') === $rel ? 'selected' : '' ?>><?= $rel ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Telefonszám</label>
          <input type="tel" name="emergency_phone" value="<?= e($_SESSION['form_old']['emergency_phone'] ?? '') ?>">
        </div>
      </div>

      <h3 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin:24px 0 14px;padding-top:20px;border-top:1px solid var(--border);">Tagság</h3>
      <div class="form-grid">
        <div class="form-group">
          <label>Tagság kezdete</label>
          <input type="date" name="member_since" value="<?= e($_SESSION['form_old']['member_since'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Utolsó fizetés</label>
          <input type="date" name="last_payment" value="<?= e($_SESSION['form_old']['last_payment'] ?? '') ?>">
        </div>
      </div>

      <?php unset($_SESSION['form_old']); ?>

      <input type="hidden" name="send_welcome_email" id="send_welcome_email" value="">

      <div class="flex gap-2" style="margin-top:24px;">
        <button type="button" id="btn-submit-member" class="btn btn-primary">Tag regisztrálása</button>
        <a href="<?= BASE_URL ?>/admin/members.php" class="btn btn-secondary">Mégse</a>
      </div>
    </form>
  </div>
</div>

<!-- Email preview modal -->
<div class="modal-backdrop" id="email-preview-modal">
  <div class="modal" style="max-width:640px;">
    <div class="modal-header">
      <h2>E-mail előnézet</h2>
      <button class="modal-close" type="button" data-modal-close aria-label="Bezárás">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
    <div class="modal-body" style="padding:0;">
      <div style="padding:12px 20px;background:var(--card);border-bottom:1px solid var(--border);font-size:13px;display:flex;flex-direction:column;gap:5px;">
        <div><strong>Címzett:</strong> <span id="email-preview-to" style="color:var(--primary);"></span></div>
        <div><strong>Tárgy:</strong> Üdvözlünk a <?= e(APP_NAME) ?>-ban!</div>
      </div>
      <div id="email-preview-body" style="max-height:440px;overflow-y:auto;"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" type="button" data-modal-close>Mégse</button>
      <button class="btn btn-secondary" type="button" id="btn-register-no-email">Regisztrálás e-mail nélkül</button>
      <button class="btn btn-primary" type="button" id="btn-send-and-register">E-mail küldése és regisztrálás</button>
    </div>
  </div>
</div>

<script>
(function () {
  function esc(s) {
    return String(s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function buildPreview(firstname, username, password) {
    return '<div style="background:#f0ebe0;padding:20px;">'
      + '<div style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 3px 16px rgba(0,0,0,.1);">'
        + '<div style="background:#1a3d39;padding:24px 32px;text-align:center;">'
          + '<div style="font-size:22px;font-weight:800;color:#F4E7CF;letter-spacing:.05em;">LIZZARD</div>'
          + '<div style="font-size:11px;color:#8fb5b2;margin-top:4px;letter-spacing:.14em;text-transform:uppercase;">Természetjáró Egyesület</div>'
        + '</div>'
        + '<div style="padding:28px 32px 22px;">'
          + '<p style="font-size:15px;color:#333;margin:0 0 12px;">Kedves <strong>' + esc(firstname) + '</strong>!</p>'
          + '<p style="font-size:13px;color:#555;line-height:1.7;margin:0 0 18px;">Örömmel értesítünk, hogy sikeresen regisztráltak <strong>Lizzard Természetjáró Egyesületünkbe</strong>. Az alábbiakban találod a tagsági rendszer bejelentkezési adataidat.</p>'
          + '<div style="background:#f5efe4;border:1px solid #ddd5c5;border-radius:7px;padding:16px 20px;margin:0 0 20px;">'
            + '<div style="margin-bottom:12px;">'
              + '<div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#7a7269;margin-bottom:4px;">Felhasználónév</div>'
              + '<div style="font-size:16px;font-weight:700;color:#1a3d39;font-family:monospace;">' + esc(username) + '</div>'
            + '</div>'
            + '<div style="border-top:1px solid #ddd5c5;padding-top:12px;">'
              + '<div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#7a7269;margin-bottom:4px;">Jelszó</div>'
              + '<div style="font-size:16px;font-weight:700;color:#1a3d39;font-family:monospace;">' + esc(password) + '</div>'
            + '</div>'
          + '</div>'
          + '<div style="text-align:center;margin:0 0 18px;">'
            + '<span style="display:inline-block;background:#29776F;color:#fff;font-size:13px;font-weight:700;padding:11px 28px;border-radius:7px;">Belépés a tagsági rendszerbe</span>'
          + '</div>'
          + '<p style="font-size:12px;color:#7a7269;line-height:1.7;margin:0;">Reméljük, hamarosan viszontlátunk egy túránkon!</p>'
        + '</div>'
        + '<div style="background:#f5efe4;border-top:1px solid #ddd5c5;padding:16px 32px;text-align:center;">'
          + '<p style="font-size:11px;color:#7a7269;margin:0;">Üdvözlettel,<br><strong style="color:#1a3d39;">Lizzard Természetjáró Egyesület Vezetősége</strong></p>'
        + '</div>'
      + '</div>'
    + '</div>';
  }

  var form        = document.getElementById('member-add-form');
  var submitBtn   = document.getElementById('btn-submit-member');
  var modal       = document.getElementById('email-preview-modal');
  var flagInput   = document.getElementById('send_welcome_email');
  var previewTo   = document.getElementById('email-preview-to');
  var previewBody = document.getElementById('email-preview-body');

  submitBtn.addEventListener('click', function () {
    if (!form.checkValidity()) { form.reportValidity(); return; }

    var firstname = (form.querySelector('[name="firstname"]').value || '').trim();
    var username  = (form.querySelector('[name="username"]').value  || '').trim();
    var password  = form.querySelector('[name="password"]').value   || '';
    var email     = (form.querySelector('[name="email"]').value     || '').trim();

    previewTo.textContent = email;
    previewBody.innerHTML = buildPreview(firstname, username, password);
    modal.classList.add('open');
  });

  document.getElementById('btn-send-and-register').addEventListener('click', function () {
    flagInput.value = '1';
    modal.classList.remove('open');
    form.submit();
  });

  document.getElementById('btn-register-no-email').addEventListener('click', function () {
    flagInput.value = '0';
    modal.classList.remove('open');
    form.submit();
  });
})();
</script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
