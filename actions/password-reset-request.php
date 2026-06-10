<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/app-settings-schema.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/user-schema.php';
require_once __DIR__ . '/../includes/login-log-schema.php';
require_once __DIR__ . '/../includes/captcha.php';

verifyCsrf();

$pdo = getDb();
ensureUserSchema($pdo);
ensureAppSettingsSchema($pdo);

if (recaptchaEnabled($pdo) && !verifyRecaptcha($pdo, $_POST['g-recaptcha-response'] ?? '', $_SERVER['REMOTE_ADDR'] ?? null)) {
    flash('reset_err', 'Kérjük, igazold, hogy nem vagy robot.');
    header('Location: ' . BASE_URL . '/password-reset.php');
    exit;
}

$email = trim($_POST['email'] ?? '');

// Always redirect with the same generic message — never reveal if email exists
$generic = 'Ha az e-mail cím regisztrálva van a rendszerben, hamarosan megérkezik a visszaállítási link. Ellenőrizze a spam mappáját is!';

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('reset_msg', $generic);
    header('Location: ' . BASE_URL . '/password-reset.php');
    exit;
}

$user = $pdo->prepare("SELECT id, firstname, lastname, email, username, active FROM users WHERE email = ? LIMIT 1");
$user->execute([$email]);
$user = $user->fetch();

if (!$user || !$user['active']) {
    flash('reset_msg', $generic);
    header('Location: ' . BASE_URL . '/password-reset.php');
    exit;
}

// Rate limit: max 3 requests per user per hour
$recentCount = $pdo->prepare(
    "SELECT COUNT(*) FROM password_resets
     WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) AND used_at IS NULL AND expires_at > NOW()"
);
$recentCount->execute([$user['id']]);
if ((int)$recentCount->fetchColumn() >= 3) {
    flash('reset_msg', $generic);
    header('Location: ' . BASE_URL . '/password-reset.php');
    exit;
}

// Clean up expired tokens for this user
$pdo->prepare("DELETE FROM password_resets WHERE user_id = ? AND (expires_at < NOW() OR used_at IS NOT NULL)")
    ->execute([$user['id']]);

// Generate token — 32 random bytes = 64 hex chars, unguessable
$token     = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);
$expiresAt = date('Y-m-d H:i:s', time() + 3600);

$pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)")
    ->execute([$user['id'], $tokenHash, $expiresAt]);

// Build absolute URL — BASE_URL is path-only, email links need scheme + host
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$siteBase = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL;
$resetUrl = $siteBase . '/password-reset.php?token=' . rawurlencode($token);

$name = trim($user['lastname'] . ' ' . $user['firstname']);
$smtp = getSmtpConfig($pdo);

$html = '<!DOCTYPE html><html><body style="font-family:sans-serif;max-width:520px;margin:40px auto;color:#222;">'
      . '<h2 style="color:#1a4a46;">Jelszó visszaállítása</h2>'
      . '<p>Szia ' . htmlspecialchars($name) . '!</p>'
      . '<p>Jelszó-visszaállítást kértek ehhez a fiókhoz. A link <strong>1 óráig</strong> érvényes.</p>'
      . '<p style="margin:24px 0;"><a href="' . htmlspecialchars($resetUrl) . '" '
      . 'style="display:inline-block;background:#1a4a46;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;font-weight:600;">Jelszó visszaállítása</a></p>'
      . '<p style="font-size:12px;color:#555;">Ha a gomb nem működik, másolja be ezt a linket a böngészőbe:</p>'
      . '<p style="font-size:12px;word-break:break-all;"><a href="' . htmlspecialchars($resetUrl) . '" style="color:#1a4a46;">' . htmlspecialchars($resetUrl) . '</a></p>'
      . '<p style="font-size:12px;color:#888;">Ha nem te kérted, hagyd figyelmen kívül ezt az e-mailt. A link 1 óra után automatikusan érvénytelenné válik.</p>'
      . '<hr style="border:none;border-top:1px solid #eee;margin:24px 0;">'
      . '<p style="font-size:11px;color:#aaa;">' . htmlspecialchars(APP_NAME) . '</p>'
      . '</body></html>';

$text = 'Kedves ' . $name . "!\r\n\r\n"
      . "Jelszó-visszaállítást kértek ehhez a fiókhoz. A link 1 óráig érvényes.\r\n\r\n"
      . "Jelszó visszaállítása:\r\n" . $resetUrl . "\r\n\r\n"
      . "Ha nem te kérted, hagyd figyelmen kívül ezt az e-mailt.\r\n\r\n"
      . APP_NAME;

$ip        = $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
ensureLoginLogSchema($pdo);

try {
    $mailer = new SmtpMailer($smtp);
    $mailer->send($user['email'], $name, APP_NAME . ' – Jelszó visszaállítása', $html, $text);
    $pdo->prepare("INSERT INTO login_log (user_id, name, username, ip, user_agent, event_type, status) VALUES (?,?,?,?,?,?,?)")
        ->execute([$user['id'], $name, $user['username'], $ip, $userAgent, 'password_reset_request', 'success']);
} catch (Throwable $e) {
    error_log('Password reset mail error: ' . $e->getMessage());
}

flash('reset_msg', $generic);
header('Location: ' . BASE_URL . '/password-reset.php');
exit;
