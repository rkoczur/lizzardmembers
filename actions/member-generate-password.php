<?php
/**
 * Admin: új jelszó generálása egy tagnak, és a belépési adatok (felhasználónév,
 * új jelszó, belépési link) kiküldése e-mailben.
 *
 * Biztonsági megfontolás: előbb elküldjük az e-mailt az új jelszóval, és CSAK
 * sikeres kézbesítés után írjuk felül a jelszót az adatbázisban — így egy
 * kézbesítési hiba nem zárja ki a tagot a fiókjából.
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/app-settings-schema.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/welcome-email.php';
require_once __DIR__ . '/../includes/email-log-schema.php';
requireAdmin();
verifyCsrf();

$pdo = getDb();

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    flash('error', 'Érvénytelen tag.');
    header('Location: ' . BASE_URL . '/admin/members.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, firstname, lastname, email, username FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    flash('error', 'A tag nem található.');
    header('Location: ' . BASE_URL . '/admin/members.php');
    exit;
}

$backUrl = BASE_URL . '/admin/member-detail.php?id=' . $id;

if (trim((string)$user['email']) === '') {
    flash('error', 'A tagnak nincs megadva e-mail címe, ezért nem küldhető ki új jelszó.');
    header('Location: ' . $backUrl);
    exit;
}

ensureAppSettingsSchema($pdo);
ensureEmailLogSchema($pdo);
$smtp = getSmtpConfig($pdo);
if ($smtp['host'] === '') {
    flash('error', 'Az SMTP szerver nincs beállítva, ezért nem küldhető ki új jelszó.');
    header('Location: ' . $backUrl);
    exit;
}

$password = generateMemberPassword();
$proto    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$loginUrl = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL . '/login.php';
$html     = buildWelcomeEmailHtml($user['firstname'], $user['username'], $password, $loginUrl, APP_NAME);
// A naplóba a jelszót kimaszkolva tároljuk — sose kerüljön plaintext jelszó az email_log-ba
$logHtml  = buildWelcomeEmailHtml($user['firstname'], $user['username'], '••••••••', $loginUrl, APP_NAME);
$subject  = 'Új belépési adatok — ' . APP_NAME;
$fullName = trim($user['lastname'] . ' ' . $user['firstname']);

$mailer = new SmtpMailer($smtp);
try {
    $response = $mailer->send($user['email'], $fullName, $subject, $html);
} catch (Throwable $e) {
    $err = $e->getMessage();
    logEmailEntry($pdo, $id, $user['email'], $fullName, $subject, $logHtml, 'password_reset_admin', 'failed', $err, $err);
    error_log('Admin generate-password email error for uid=' . $id . ': ' . $err);
    flash('error', 'Az e-mail kiküldése sikertelen volt, ezért a jelszó NEM változott: ' . $err);
    header('Location: ' . $backUrl);
    exit;
}

// Csak sikeres kézbesítés után írjuk felül a jelszót, és oldjuk a zárolást
$pdo->prepare("UPDATE users SET password = ?, login_attempts = 0, locked_at = NULL WHERE id = ?")
    ->execute([password_hash($password, PASSWORD_DEFAULT), $id]);

logEmailEntry($pdo, $id, $user['email'], $fullName, $subject, $logHtml, 'password_reset_admin', 'sent', '', $response);

flash('success', 'Új jelszó generálva és kiküldve a tag e-mail címére (' . $user['email'] . ').');
header('Location: ' . $backUrl);
exit;
