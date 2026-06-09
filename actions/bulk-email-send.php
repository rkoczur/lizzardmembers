<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/app-settings-schema.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/email-log-schema.php';
require_once __DIR__ . '/../includes/bulk-email-template.php';
requireAdmin();
verifyCsrf();

$pdo = getDb();
ensureAppSettingsSchema($pdo);
ensureEmailLogSchema($pdo);

$ids     = array_values(array_filter(array_map('intval', $_POST['member_ids'] ?? [])));
$subject = trim($_POST['subject'] ?? '');
$body    = trim($_POST['body']    ?? '');

if (empty($ids) || $subject === '' || $body === '') {
    flash('error', 'Hiányos adatok. Töltse ki a tárgyat és a szöveget is.');
    $_SESSION['compose_ids'] = $ids;
    header('Location: ' . BASE_URL . '/admin/email-compose.php');
    exit;
}

$smtp = getSmtpConfig($pdo);
if ($smtp['host'] === '') {
    flash('error', 'Az SMTP szerver nincs beállítva. Kérjük, először konfigurálja a Beállítások oldalon.');
    $_SESSION['compose_ids'] = $ids;
    header('Location: ' . BASE_URL . '/admin/email-compose.php');
    exit;
}

$ph      = rtrim(str_repeat('?,', count($ids)), ',');
$members = $pdo->prepare(
    "SELECT id, firstname, lastname, email, username, level, points, city, member_since FROM users WHERE id IN ($ph)"
);
$members->execute($ids);
$members = $members->fetchAll();

$mailer  = new SmtpMailer($smtp);
$sent    = 0;
$failed  = [];

foreach ($members as $m) {
    $name = trim($m['lastname'] . ' ' . $m['firstname']);
    $subj = applyBulkEmailMerge($subject, $m);
    $html = buildBulkEmailHtml($subj, applyBulkEmailMerge($body, $m));

    try {
        $response = $mailer->send($m['email'], $name, $subj, $html);
        logEmailEntry($pdo, (int)$m['id'], $m['email'], $name, $subj, $html, 'bulk', 'sent', '', $response);
        $sent++;
    } catch (Throwable $e) {
        $err = $e->getMessage();
        logEmailEntry($pdo, (int)$m['id'], $m['email'], $name, $subj, $html, 'bulk', 'failed', $err, $err);
        $failed[] = $name . ' (' . $m['email'] . '): ' . $err;
        error_log('Bulk email error to ' . $m['email'] . ': ' . $err);
    }
}

unset($_SESSION['compose_ids']);

if (empty($failed)) {
    flash('success', "Az e-mail sikeresen elküldve {$sent} főnek.");
    header('Location: ' . BASE_URL . '/admin/members.php');
} else {
    $errList = implode(' | ', array_slice($failed, 0, 5));
    flash('error', "{$sent} sikeres küldés, " . count($failed) . " sikertelen: {$errList}");
    header('Location: ' . BASE_URL . '/admin/members.php');
}
exit;
