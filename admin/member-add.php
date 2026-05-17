<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/user-schema.php';
requireAdminOrVezeto();
if (isVezeto()) {
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
    <form method="post" action="<?= BASE_URL ?>/actions/member-add.php">
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

      <div class="flex gap-2" style="margin-top:24px;">
        <button type="submit" class="btn btn-primary">Tag regisztrálása</button>
        <a href="<?= BASE_URL ?>/admin/members.php" class="btn btn-secondary">Mégse</a>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
