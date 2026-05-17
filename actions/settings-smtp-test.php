<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/app-settings-schema.php';
require_once __DIR__ . '/../includes/mailer.php';
requireAdmin();
verifyCsrf();

$pdo = getDb();
ensureAppSettingsSchema($pdo);

$smtp = getSmtpConfig($pdo);

if ($smtp['host'] === '') {
    flash('error', 'Az SMTP szerver nincs beállítva. Először mentse el a beállításokat.');
    header('Location: ' . BASE_URL . '/admin/settings.php');
    exit;
}

$adminStmt = $pdo->prepare("SELECT email, firstname, lastname FROM users WHERE id = ? LIMIT 1");
$adminStmt->execute([getCurrentUserId()]);
$admin = $adminStmt->fetch();

if (!$admin || !$admin['email']) {
    flash('error', 'Nem sikerült lekérni az Ön e-mail címét a fiókjából.');
    header('Location: ' . BASE_URL . '/admin/settings.php');
    exit;
}

$name = trim($admin['lastname'] . ' ' . $admin['firstname']);
$html = '<!DOCTYPE html><html><body style="font-family:sans-serif;max-width:520px;margin:40px auto;color:#222;">'
      . '<h2 style="color:#1a4a46;">SMTP teszt sikeres</h2>'
      . '<p>Kedves ' . htmlspecialchars($name) . '!</p>'
      . '<p>Ez egy teszt e-mail a <strong>' . htmlspecialchars(APP_NAME) . '</strong> rendszerből.</p>'
      . '<p>Ha ezt az üzenetet látja, az SMTP beállítások helyesek.</p>'
      . '<table style="font-size:13px;border-collapse:collapse;margin-top:20px;width:100%;">'
      . '<tr><td style="padding:6px 10px;background:#f5f5f5;font-weight:600;width:40%;">Szerver</td><td style="padding:6px 10px;">' . htmlspecialchars($smtp['host'] . ':' . $smtp['port']) . '</td></tr>'
      . '<tr><td style="padding:6px 10px;background:#f5f5f5;font-weight:600;">Titkosítás</td><td style="padding:6px 10px;">' . htmlspecialchars($smtp['encryption'] ?: 'nincs') . '</td></tr>'
      . '<tr><td style="padding:6px 10px;background:#f5f5f5;font-weight:600;">Feladó</td><td style="padding:6px 10px;">' . htmlspecialchars($smtp['from_email']) . '</td></tr>'
      . '</table>'
      . '<hr style="border:none;border-top:1px solid #eee;margin:24px 0;">'
      . '<p style="font-size:11px;color:#aaa;">' . htmlspecialchars(APP_NAME) . ' – automatikus üzenet</p>'
      . '</body></html>';

try {
    $mailer = new SmtpMailer($smtp);
    $mailer->send($admin['email'], $name, APP_NAME . ' – SMTP teszt', $html);
    flash('success', 'Teszt e-mail sikeresen elküldve: ' . $admin['email']);
} catch (Throwable $e) {
    flash('error', 'SMTP hiba: ' . $e->getMessage());
}

header('Location: ' . BASE_URL . '/admin/settings.php');
exit;
