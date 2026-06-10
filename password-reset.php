<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/app-settings-schema.php';
require_once __DIR__ . '/includes/user-schema.php';
require_once __DIR__ . '/includes/captcha.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . (isAdminOrVezeto() ? '/admin/index.php' : '/user/index.php'));
    exit;
}

$pdo = getDb();
ensureUserSchema($pdo);
ensureAppSettingsSchema($pdo);

$token     = trim($_GET['token'] ?? '');
$resetRow  = null;
$tokenErr  = '';
$pwError   = getFlash('pw_error');
$resetMsg  = getFlash('reset_msg');
$resetErr  = getFlash('reset_err');

if ($token !== '') {
    $tokenHash = hash('sha256', $token);
    $stmt = $pdo->prepare(
        "SELECT pr.id, pr.expires_at, pr.used_at FROM password_resets pr WHERE pr.token_hash = ? LIMIT 1"
    );
    $stmt->execute([$tokenHash]);
    $resetRow = $stmt->fetch();

    if (!$resetRow) {
        $tokenErr = 'Érvénytelen visszaállítási link.';
    } elseif ($resetRow['used_at'] !== null) {
        $tokenErr = 'Ez a link már fel lett használva.';
    } elseif (new DateTime() > new DateTime($resetRow['expires_at'])) {
        $tokenErr = 'A link lejárt. Kérj újat!';
    }
}

$showForm  = $token === '' || ($resetRow && !$tokenErr);
$showReset = $token !== '' && $resetRow && !$tokenErr;
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Jelszó visszaállítása — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <div class="login-logo">
      <div class="app-name"><?= APP_NAME ?></div>
      <div class="app-sub">Tagságkezelés</div>
    </div>

    <?php if ($tokenErr): ?>
      <h1>Érvénytelen link</h1>
      <div class="alert alert-error"><?= e($tokenErr) ?></div>
      <a href="<?= BASE_URL ?>/password-reset.php" class="btn btn-primary" style="width:100%;margin-top:8px;">Új visszaállítási link kérése</a>
      <p style="text-align:center;margin-top:16px;font-size:13px;">
        <a href="<?= BASE_URL ?>/login.php">← Vissza a bejelentkezéshez</a>
      </p>

    <?php elseif ($showReset): ?>
      <h1>Új jelszó megadása</h1>
      <?php if ($pwError): ?>
        <div class="alert alert-error"><?= e($pwError) ?></div>
      <?php endif; ?>
      <form method="post" action="<?= BASE_URL ?>/actions/password-reset-confirm.php">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="form-group mb-4">
          <label for="password">Új jelszó</label>
          <input type="password" id="password" name="password" minlength="8" required autofocus
                 placeholder="Legalább 8 karakter">
        </div>
        <div class="form-group mb-4">
          <label for="confirm">Jelszó megerősítése</label>
          <input type="password" id="confirm" name="confirm" minlength="8" required
                 placeholder="Ismételje meg a jelszót">
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px;">Jelszó megváltoztatása</button>
      </form>

    <?php else: ?>
      <h1>Elfelejtett jelszó</h1>
      <?php if ($resetMsg): ?>
        <div class="alert alert-success"><?= e($resetMsg) ?></div>
      <?php endif; ?>
      <?php if ($resetErr): ?>
        <div class="alert alert-error"><?= e($resetErr) ?></div>
      <?php endif; ?>
      <p style="color:var(--text-muted);font-size:13px;margin-bottom:16px;">
        Add meg a regisztrált e-mail címed. Ha megtaláljuk, küldünk egy visszaállítási linket.
      </p>
      <form method="post" action="<?= BASE_URL ?>/actions/password-reset-request.php">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="form-group mb-4">
          <label for="email">E-mail cím</label>
          <input type="email" id="email" name="email" required autofocus placeholder="pelda@email.hu">
        </div>
        <?= recaptchaField($pdo) ?>
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px;">Visszaállítási link küldése</button>
      </form>
    <?php endif; ?>

    <p style="text-align:center;margin-top:20px;font-size:13px;">
      <a href="<?= BASE_URL ?>/login.php">← Vissza a bejelentkezéshez</a>
    </p>
  </div>
</div>
<?= recaptchaScript($pdo) ?>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
