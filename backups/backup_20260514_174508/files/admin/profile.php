<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/user-schema.php';
requireAdmin();

$pdo    = getDb();
ensureUserSchema($pdo);
$userId = getCurrentUserId();
$stmt   = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$user   = $stmt->fetch();

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$pageTitle  = 'Saját profilom';
$activePage = 'profile';
include __DIR__ . '/../includes/admin-header.php';
?>

<?php if ($flash_success): ?>
  <div class="alert alert-success" data-auto-dismiss><?= e($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_error): ?>
  <div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="page-header"><h1>Saját profilom</h1></div>

<div class="profile-layout">
  <!-- Avatar card -->
  <div class="profile-avatar-card">
    <div class="avatar-upload-wrap">
      <img id="avatar-preview"
           src="<?= getAvatarUrl($user['profile_picture']) ?>"
           alt="Profilkép">
      <div class="avatar-overlay" id="avatar-upload-overlay" title="Fotó módosítása">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
          <circle cx="12" cy="13" r="4"/>
        </svg>
      </div>
    </div>
    <div class="member-name"><?= e($user['lastname'] . ' ' . $user['firstname']) ?></div>
    <div class="member-username">@<?= e($user['username']) ?></div>
    <div class="divider"></div>
    <div class="points-display">
      <div class="points-value"><?= number_format($user['points']) ?></div>
      <div class="points-label">Pontok</div>
    </div>
    <span class="level-badge <?= getLevelClass($user['level']) ?>" style="font-size:13px;padding:5px 14px;">
      <?= getLevelLabel($user['level']) ?> — Szint <?= $user['level'] ?>
    </span>
    <div class="divider"></div>
    <small class="text-muted">Tag azóta: <?= formatDate($user['member_since']) ?></small>
    <small class="text-muted" style="font-size:11px;margin-top:6px;background:var(--primary-light);color:var(--primary-dark);padding:3px 10px;border-radius:99px;font-weight:600;">Rendszergazda</small>
  </div>

  <!-- Edit form -->
  <div class="card">
    <div class="card-header"><h2>Profil adatai</h2></div>
    <div class="card-body">
      <form method="post" action="<?= BASE_URL ?>/actions/profile-update.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="redirect_to" value="<?= BASE_URL ?>/admin/profile.php">
        <input type="file" id="avatar-file-input" name="avatar" accept="image/*" style="display:none;">

        <div class="form-grid">
          <div class="form-group">
            <label>Vezetéknév</label>
            <input type="text" name="lastname" value="<?= e($user['lastname'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label>Keresztnév</label>
            <input type="text" name="firstname" value="<?= e($user['firstname'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label>Felhasználónév</label>
            <input type="text" name="username" value="<?= e($user['username']) ?>" required>
          </div>
          <div class="form-group">
            <label>E-mail</label>
            <input type="email" name="email" value="<?= e($user['email']) ?>" required>
          </div>
          <div class="form-group">
            <label>Születési dátum</label>
            <input type="date" name="dateofbirth" value="<?= e($user['dateofbirth'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Pólóméret</label>
            <select name="tshirt_size">
              <option value="">— Válasszon —</option>
              <?php foreach (['XS','S','M','L','XL','XXL','XXXL'] as $sz): ?>
                <option value="<?= $sz ?>" <?= ($user['tshirt_size'] ?? '') === $sz ? 'selected' : '' ?>><?= $sz ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Irányítószám</label>
            <input type="text" name="zipcode" value="<?= e($user['zipcode'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Város</label>
            <input type="text" name="city" value="<?= e($user['city'] ?? '') ?>">
          </div>
          <div class="form-group full">
            <label>Cím</label>
            <input type="text" name="address" value="<?= e($user['address'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Telefonszám</label>
            <input type="tel" name="phone" value="<?= e($user['phone'] ?? '') ?>">
          </div>
        </div>

        <h3 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin:24px 0 14px;padding-top:20px;border-top:1px solid var(--border);">Vészhelyzet esetén értesítendő</h3>
        <div class="form-grid">
          <div class="form-group">
            <label>Név</label>
            <input type="text" name="emergency_name" value="<?= e($user['emergency_name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Kapcsolat</label>
            <select name="emergency_relation">
              <option value="">— Válasszon —</option>
              <?php foreach (['szülő','gyermek','testvér','egyéb'] as $rel): ?>
                <option value="<?= $rel ?>" <?= ($user['emergency_relation'] ?? '') === $rel ? 'selected' : '' ?>><?= $rel ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Telefonszám</label>
            <input type="tel" name="emergency_phone" value="<?= e($user['emergency_phone'] ?? '') ?>">
          </div>
        </div>

        <div class="pass-section">
          <h3>Jelszó módosítása (hagyja üresen a jelenlegi megtartásához)</h3>
          <div class="form-grid">
            <div class="form-group">
              <label>Új jelszó</label>
              <input type="password" name="new_password" minlength="6">
            </div>
            <div class="form-group">
              <label>Jelszó megerősítése</label>
              <input type="password" name="new_password2">
            </div>
          </div>
        </div>

        <div class="flex gap-2" style="margin-top:20px;">
          <button type="submit" class="btn btn-primary">Változások mentése</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
