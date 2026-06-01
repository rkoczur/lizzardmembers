<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . (isAdminOrVezeto() ? '/admin/index.php' : '/user/index.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';

    if ($identifier === '' || $password === '') {
        $error = 'Kérjük, adja meg felhasználónevét és jelszavát.';
    } else {
        $pdo = getDb();
        if (!$pdo) {
            $error = 'Nem sikerült csatlakozni az adatbázishoz. Kérjük, ellenőrizze a beállításokat.';
        } else {
            require_once __DIR__ . '/includes/user-schema.php';
            require_once __DIR__ . '/includes/ip-block-schema.php';
            require_once __DIR__ . '/includes/login-log-schema.php';
            ensureUserSchema($pdo);
            ensureIpBlockSchema($pdo);
            ensureLoginLogSchema($pdo);

            $ip        = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

            $writeLog = function (string $status, ?int $userId, string $name, string $uname, ?string $reason) use ($pdo, $ip, $userAgent): void {
                $pdo->prepare("INSERT INTO login_log (user_id, name, username, ip, user_agent, status, fail_reason) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$userId ?: null, $name, $uname, $ip, $userAgent, $status, $reason]);
            };

            // 1. Check if this IP is already blocked
            $ipRow = $pdo->prepare("SELECT attempts, blocked FROM ip_blocks WHERE ip = ?");
            $ipRow->execute([$ip]);
            $ipBlock = $ipRow->fetch();

            if ($ipBlock && $ipBlock['blocked']) {
                $writeLog('failed', null, '', $identifier, 'ip_blocked');
                $error = 'Az Ön IP-címe zárolva lett ismételt sikertelen bejelentkezési kísérlet miatt. Kérje az adminisztrátor segítségét.';
            } else {
                // 2. Find user (no active filter — we check states explicitly)
                $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) LIMIT 1");
                $stmt->execute([$identifier, $identifier]);
                $user = $stmt->fetch();

                if (!$user) {
                    $writeLog('failed', null, '', $identifier, 'unknown_user');
                    // Unknown identifier → track IP attempts
                    if ($ipBlock) {
                        $newIpAttempts = (int)$ipBlock['attempts'] + 1;
                        $nowBlocked    = $newIpAttempts >= 3 ? 1 : 0;
                        $pdo->prepare("UPDATE ip_blocks SET attempts = ?, blocked = ? WHERE ip = ?")
                            ->execute([$newIpAttempts, $nowBlocked, $ip]);
                    } else {
                        $pdo->prepare("INSERT INTO ip_blocks (ip, attempts, blocked) VALUES (?, 1, 0)")
                            ->execute([$ip]);
                        $newIpAttempts = 1;
                    }
                    if ($newIpAttempts >= 3) {
                        $error = 'Az Ön IP-címe zárolva lett ismételt sikertelen bejelentkezési kísérlet miatt. Kérje az adminisztrátor segítségét.';
                    } else {
                        $error = 'Érvénytelen felhasználónév vagy jelszó. (' . $newIpAttempts . '/3 IP-kísérlet)';
                    }

                } elseif (!$user['active']) {
                    $writeLog('failed', (int)$user['id'], $user['lastname'] . ' ' . $user['firstname'], $user['username'], 'account_inactive');
                    // Deactivated account — generic message to avoid enumeration
                    $error = 'Érvénytelen felhasználónév vagy jelszó.';

                } elseif (!empty($user['locked_at'])) {
                    $writeLog('failed', (int)$user['id'], $user['lastname'] . ' ' . $user['firstname'], $user['username'], 'account_locked');
                    // Account locked by failed attempts
                    $error = 'A fiókja zárolva lett ismételt hibás bejelentkezési kísérlet miatt. Kérje az adminisztrátor segítségét.';

                } elseif (!password_verify($password, $user['password'])) {
                    $writeLog('failed', (int)$user['id'], $user['lastname'] . ' ' . $user['firstname'], $user['username'], 'wrong_password');
                    // Wrong password → track per-user attempts
                    $newAttempts = (int)($user['login_attempts'] ?? 0) + 1;
                    if ($newAttempts >= 3) {
                        $pdo->prepare("UPDATE users SET login_attempts = ?, locked_at = NOW() WHERE id = ?")
                            ->execute([$newAttempts, $user['id']]);
                        $error = 'A fiókja zárolva lett ismételt hibás bejelentkezési kísérlet miatt. Kérje az adminisztrátor segítségét.';
                    } else {
                        $pdo->prepare("UPDATE users SET login_attempts = ? WHERE id = ?")
                            ->execute([$newAttempts, $user['id']]);
                        $remaining = 3 - $newAttempts;
                        $error = 'Érvénytelen jelszó. Még ' . $remaining . ' kísérlet a fiók zárolása előtt.';
                    }

                } else {
                    // Successful login — reset counter
                    $pdo->prepare("UPDATE users SET login_attempts = 0, locked_at = NULL WHERE id = ?")
                        ->execute([$user['id']]);
                    $writeLog('success', (int)$user['id'], $user['lastname'] . ' ' . $user['firstname'], $user['username'], null);
                    setUserSession($user);
                    $_SESSION['user_avatar'] = $user['profile_picture'];
                    header('Location: ' . BASE_URL . (in_array($user['role'], ['admin', 'vezeto'], true) ? '/admin/index.php' : '/user/index.php'));
                    exit;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bejelentkezés — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <div class="login-logo">
      <div class="app-name"><?= APP_NAME ?></div>
      <div class="app-sub">Tagságkezelés</div>
    </div>

    <h1>Bejelentkezés</h1>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['setup'])): ?>
      <div class="alert alert-success">A beállítás kész! Most már bejelentkezhet az admin fiókjával.</div>
    <?php endif; ?>

    <form method="post" autocomplete="on">
      <div class="form-group mb-4">
        <label for="identifier">Felhasználónév vagy E-mail</label>
        <input type="text" id="identifier" name="identifier"
               value="<?= e($_POST['identifier'] ?? '') ?>"
               autocomplete="username" autofocus required>
      </div>
      <div class="form-group mb-4">
        <label for="password">Jelszó</label>
        <input type="password" id="password" name="password"
               autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px;">Bejelentkezés</button>
    </form>
    <p style="text-align:center;margin-top:16px;font-size:13px;">
      <a href="<?= BASE_URL ?>/password-reset.php">Elfelejtetted a jelszavad?</a>
    </p>

    <div style="display:flex;align-items:center;gap:12px;margin:24px 0 18px;">
      <div style="flex:1;height:1px;background:var(--border);"></div>
      <span style="font-size:12px;color:var(--text-muted);white-space:nowrap;">vagy</span>
      <div style="flex:1;height:1px;background:var(--border);"></div>
    </div>

    <a href="<?= BASE_URL ?>/join.php"
       style="display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:13px 20px;font-size:15px;font-weight:700;color:var(--primary);background:transparent;border:2px solid var(--primary);border-radius:8px;text-decoration:none;transition:background .18s,color .18s;box-sizing:border-box;"
       onmouseover="this.style.background='var(--primary)';this.style.color='#fff';"
       onmouseout="this.style.background='transparent';this.style.color='var(--primary)';">
      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <line x1="19" y1="8" x2="19" y2="14"/>
        <line x1="22" y1="11" x2="16" y2="11"/>
      </svg>
      Belépés az egyesületbe
    </a>
    <p style="text-align:center;margin-top:10px;font-size:12px;color:var(--text-muted);">Még nem vagy tag? Küldd el belépési kérelmedet!</p>
    <p style="text-align:center;margin-top:14px;font-size:12px;color:var(--text-muted);">
      <a href="<?= BASE_URL ?>/help.php" style="color:var(--text-muted);">📖 Felhasználói útmutató</a>
    </p>

  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
