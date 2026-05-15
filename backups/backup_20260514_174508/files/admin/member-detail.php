<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/user-schema.php';
requireAdmin();

$pdo = getDb();
ensureUserSchema($pdo);
$id  = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/admin/members.php');
    exit;
}

$member = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$member->execute([$id]);
$member = $member->fetch();
if (!$member) {
    header('Location: ' . BASE_URL . '/admin/members.php');
    exit;
}

$isSelf = (getCurrentUserId() === (int)$member['id']);

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$pageTitle  = e($member['lastname'] . ' ' . $member['firstname']);
$activePage = 'members';
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
    <a href="<?= BASE_URL ?>/admin/members.php" class="btn btn-secondary btn-sm">← Vissza</a>
    <h1><?= e($member['lastname'] . ' ' . $member['firstname']) ?></h1>
  </div>
  <div class="flex items-center gap-2">
    <?php $ms = getMemberStatus($member['last_payment']); ?>
    <span class="badge <?= getMemberStatusClass($ms) ?>"><?= getMemberStatusLabel($ms) ?></span>
    <?php if (!empty($member['locked_at'])): ?>
      <span class="badge badge-inactive">🔒 Fiók zárolva</span>
      <form method="post" action="<?= BASE_URL ?>/actions/member-unlock.php" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="id" value="<?= $member['id'] ?>">
        <button type="submit" class="btn btn-primary btn-sm">Fiók feloldása</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="profile-layout">
  <!-- Avatar card -->
  <div class="profile-avatar-card">
    <div class="avatar-upload-wrap">
      <img id="avatar-preview"
           src="<?= getAvatarUrl($member['profile_picture']) ?>"
           alt="Profilkép">
      <div class="avatar-overlay" id="avatar-upload-overlay" title="Fotó módosítása">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
          <circle cx="12" cy="13" r="4"/>
        </svg>
      </div>
    </div>
    <div class="member-name"><?= e($member['lastname'] . ' ' . $member['firstname']) ?></div>
    <div class="member-username">@<?= e($member['username']) ?></div>
    <div class="divider"></div>
    <div class="points-display">
      <div class="points-value"><?= number_format($member['points']) ?></div>
      <div class="points-label">Pontok</div>
    </div>
    <?php $lvlImg = getLevelImageFilename((int)$member['level']); if ($lvlImg): ?>
    <img src="<?= BASE_URL ?>/assets/img/<?= $lvlImg ?>" alt="<?= getLevelLabel((int)$member['level']) ?>" style="width:auto;height:auto;max-width:100%;border-radius:0;box-shadow:none;">
    <?php endif; ?>
    <span class="level-badge <?= getLevelClass($member['level']) ?>" style="font-size:13px;padding:5px 14px;">
      <?= getLevelLabel($member['level']) ?> — Szint <?= $member['level'] ?>
    </span>
    <div class="divider"></div>
    <small class="text-muted">Tag azóta: <?= formatDate($member['member_since']) ?></small>
    <small class="text-muted">Utolsó fizetés: <?= formatDate($member['last_payment']) ?></small>
    <?php if (!empty($member['locked_at'])): ?>
      <div style="margin-top:10px;padding:8px 12px;background:var(--danger-bg,#fff1f0);border-radius:8px;text-align:center;">
        <div style="font-size:18px;">🔒</div>
        <div style="font-size:12px;font-weight:600;color:var(--danger,#c0392b);">Fiók zárolva</div>
        <div style="font-size:11px;color:var(--text-muted);margin-top:2px;"><?= e((new DateTime($member['locked_at']))->format('Y.m.d H:i')) ?></div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Edit form -->
  <div class="card">
    <div class="card-header">
      <h2>Tag adatai</h2>
    </div>
    <div class="card-body">
      <form method="post" action="<?= BASE_URL ?>/actions/member-update.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="id" value="<?= $member['id'] ?>">
        <input type="file" id="avatar-file-input" name="avatar" accept="image/*" style="display:none;">

        <div class="form-grid">
          <div class="form-group">
            <label>Vezetéknév</label>
            <input type="text" name="lastname" value="<?= e($member['lastname'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label>Keresztnév</label>
            <input type="text" name="firstname" value="<?= e($member['firstname'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label>Felhasználónév</label>
            <input type="text" name="username" value="<?= e($member['username']) ?>" required>
          </div>
          <div class="form-group">
            <label>E-mail</label>
            <input type="email" name="email" value="<?= e($member['email']) ?>" required>
          </div>
          <div class="form-group">
            <label>Születési dátum</label>
            <input type="date" name="dateofbirth" value="<?= e($member['dateofbirth'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Pólóméret</label>
            <select name="tshirt_size">
              <option value="">— Válasszon —</option>
              <?php foreach (['XS','S','M','L','XL','XXL','XXXL'] as $sz): ?>
                <option value="<?= $sz ?>" <?= ($member['tshirt_size'] ?? '') === $sz ? 'selected' : '' ?>><?= $sz ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Irányítószám</label>
            <input type="text" name="zipcode" value="<?= e($member['zipcode'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Város</label>
            <input type="text" name="city" value="<?= e($member['city'] ?? '') ?>">
          </div>
          <div class="form-group full">
            <label>Cím</label>
            <input type="text" name="address" value="<?= e($member['address'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Telefonszám</label>
            <input type="tel" name="phone" value="<?= e($member['phone'] ?? '') ?>">
          </div>
        </div>

        <h3 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin:24px 0 14px;padding-top:20px;border-top:1px solid var(--border);">Vészhelyzet esetén értesítendő</h3>
        <div class="form-grid">
          <div class="form-group">
            <label>Név</label>
            <input type="text" name="emergency_name" value="<?= e($member['emergency_name'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Kapcsolat</label>
            <select name="emergency_relation">
              <option value="">— Válasszon —</option>
              <?php foreach (['szülő','gyermek','testvér','egyéb'] as $rel): ?>
                <option value="<?= $rel ?>" <?= ($member['emergency_relation'] ?? '') === $rel ? 'selected' : '' ?>><?= $rel ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Telefonszám</label>
            <input type="tel" name="emergency_phone" value="<?= e($member['emergency_phone'] ?? '') ?>">
          </div>
        </div>

        <h3 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin:24px 0 14px;padding-top:20px;border-top:1px solid var(--border);">Tagság</h3>
        <div class="form-grid">
          <div class="form-group">
            <label>Tagság kezdete</label>
            <input type="date" name="member_since" value="<?= e($member['member_since'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Utolsó fizetés</label>
            <input type="date" name="last_payment" value="<?= e($member['last_payment'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label>Szerepkör</label>
            <?php if ($isSelf): ?>
              <input type="text" value="Admin (saját fiók)" readonly>
              <input type="hidden" name="role" value="admin">
              <span class="form-hint">Saját szerepkörödet nem módosíthatod.</span>
            <?php else: ?>
              <select name="role">
                <option value="user"  <?= $member['role'] === 'user'  ? 'selected' : '' ?>>Tag</option>
                <option value="admin" <?= $member['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
              </select>
            <?php endif; ?>
          </div>
        </div>

        <div class="pass-section">
          <h3>Jelszó visszaállítása (hagyja üresen a jelenlegi megtartásához)</h3>
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
          <a href="<?= BASE_URL ?>/admin/members.php" class="btn btn-secondary">Mégse</a>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
