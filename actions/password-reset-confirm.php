<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/app-settings-schema.php';
require_once __DIR__ . '/../includes/user-schema.php';
require_once __DIR__ . '/../includes/login-log-schema.php';

verifyCsrf();

$pdo = getDb();
ensureUserSchema($pdo);
ensureAppSettingsSchema($pdo);

$token    = trim($_POST['token'] ?? '');
$password = $_POST['password']  ?? '';
$confirm  = $_POST['confirm']   ?? '';
$back     = BASE_URL . '/password-reset.php?token=' . rawurlencode($token);

if ($token === '') {
    flash('error', 'Érvénytelen kérés.');
    header('Location: ' . BASE_URL . '/password-reset.php');
    exit;
}

$tokenHash = hash('sha256', $token);
$row = $pdo->prepare(
    "SELECT pr.id, pr.user_id, pr.expires_at, pr.used_at, u.email, u.firstname, u.lastname, u.username
     FROM password_resets pr
     JOIN users u ON u.id = pr.user_id
     WHERE pr.token_hash = ? LIMIT 1"
);
$row->execute([$tokenHash]);
$reset = $row->fetch();

if (!$reset) {
    flash('error', 'Érvénytelen vagy már felhasznált visszaállítási link.');
    header('Location: ' . BASE_URL . '/password-reset.php');
    exit;
}
if ($reset['used_at'] !== null) {
    flash('error', 'Ez a visszaállítási link már fel lett használva.');
    header('Location: ' . BASE_URL . '/password-reset.php');
    exit;
}
if (new DateTime() > new DateTime($reset['expires_at'])) {
    flash('error', 'A visszaállítási link lejárt. Kérjen újat!');
    header('Location: ' . BASE_URL . '/password-reset.php');
    exit;
}

if (strlen($password) < 8) {
    flash('pw_error', 'A jelszónak legalább 8 karakter hosszúnak kell lennie.');
    header('Location: ' . $back);
    exit;
}
if ($password !== $confirm) {
    flash('pw_error', 'A két jelszó nem egyezik meg.');
    header('Location: ' . $back);
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);

// Update password + reset login attempts/locked state
$pdo->prepare("UPDATE users SET password = ?, login_attempts = 0, locked_at = NULL WHERE id = ?")
    ->execute([$hash, $reset['user_id']]);

// Mark token as used + invalidate all other tokens for this user
$pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")
    ->execute([$reset['id']]);
$pdo->prepare("DELETE FROM password_resets WHERE user_id = ? AND id != ?")
    ->execute([$reset['user_id'], $reset['id']]);

$ip        = $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
$resetName = trim($reset['lastname'] . ' ' . $reset['firstname']);
ensureLoginLogSchema($pdo);
$pdo->prepare("INSERT INTO login_log (user_id, name, username, ip, user_agent, event_type, status) VALUES (?,?,?,?,?,?,?)")
    ->execute([$reset['user_id'], $resetName, $reset['username'], $ip, $userAgent, 'password_reset_complete', 'success']);

flash('success', 'A jelszó sikeresen megváltozott. Most már bejelentkezhet az új jelszavával.');
header('Location: ' . BASE_URL . '/login.php');
exit;
