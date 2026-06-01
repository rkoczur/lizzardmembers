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

    $subj    = str_replace(array_keys($mergeMap), array_values($mergeMap), $subject);
    $content = str_replace(array_keys($mergeMap), array_values($mergeMap), $body);

    // If plain text, convert newlines and escape; if HTML, use as-is
    if (!preg_match('/<[a-z][\s\S]*>/i', $content)) {
        $inner = '<p style="font-size:14px;color:#555555;line-height:1.75;margin:0;">'
               . nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'))
               . '</p>';
    } else {
        $inner = '<div style="font-size:14px;color:#555555;line-height:1.75;">' . $content . '</div>';
    }

    $appEsc = htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8');
    $subjEsc = htmlspecialchars($subj, ENT_QUOTES, 'UTF-8');
    $html = <<<MAIL
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>{$subjEsc}</title>
</head>
<body style="margin:0;padding:0;background:#f0ebe0;font-family:system-ui,-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f0ebe0;padding:32px 16px;">
  <tr><td align="center">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.12);">
    <tr>
      <td style="background:#1a3d39;padding:32px 40px;text-align:center;">
        <div style="font-size:26px;font-weight:800;color:#F4E7CF;letter-spacing:.05em;">Lizzard Outdoor</div>
      </td>
    </tr>
    <tr>
      <td style="padding:36px 40px 32px;">
        {$inner}
      </td>
    </tr>
    <tr>
      <td style="background:#f5efe4;border-top:1px solid #ddd5c5;padding:20px 40px;text-align:center;">
        <p style="font-size:12px;color:#7a7269;margin:0;line-height:1.6;">
          Üdvözlettel,<br>
          <strong style="color:#1a3d39;">{$appEsc} Vezetősége</strong>
        </p>
      </td>
    </tr>
  </table>
  </td></tr>
</table>
</body>
</html>
MAIL;

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
