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
    $name       = trim($m['lastname'] . ' ' . $m['firstname']);
    $mergeMap   = [
        '{{nev}}'           => $name,
        '{{vezeteknev}}'    => $m['lastname'],
        '{{keresztnev}}'   => $m['firstname'],
        '{{email}}'         => $m['email'],
        '{{felhasznalonev}}' => $m['username'],
        '{{szint}}'         => getLevelLabel((int)$m['level']),
        '{{pontok}}'        => number_format((int)$m['points']),
        '{{varos}}'         => $m['city'] ?? '',
        '{{tagsag_kezdete}}' => formatDate($m['member_since']),
    ];

    $subj = str_replace(array_keys($mergeMap), array_values($mergeMap), $subject);
    $html = str_replace(array_keys($mergeMap), array_values($mergeMap), $body);

    // Wrap plain text body in minimal HTML if it doesn't contain HTML tags
    if (!preg_match('/<[a-z][\s\S]*>/i', $html)) {
        $html = '<div style="font-family:sans-serif;font-size:14px;line-height:1.6;">'
              . nl2br(htmlspecialchars($html))
              . '</div>';
    }

    try {
        $mailer->send($m['email'], $name, $subj, $html);
        $sent++;
    } catch (Throwable $e) {
        $failed[] = $name . ' (' . $m['email'] . '): ' . $e->getMessage();
        error_log('Bulk email error to ' . $m['email'] . ': ' . $e->getMessage());
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
