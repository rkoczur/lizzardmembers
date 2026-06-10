<?php
/**
 * Nyilvános kapcsolati űrlap feldolgozása.
 * A beérkező üzenetet e-mailben elküldi az aktív adminoknak (Reply-To a feladó),
 * naplózza, majd visszairányít a kapcsolat oldalra.
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/app-settings-schema.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/email-log-schema.php';
require_once __DIR__ . '/../includes/captcha.php';
verifyCsrf();

$backUrl = BASE_URL . '/public/kapcsolat.php#kapcsolat-form';

$name    = trim($_POST['name']    ?? '');
$email   = trim($_POST['email']   ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');
$honey   = trim($_POST['website'] ?? ''); // spam-csapda — embernek rejtett

// Bot: ha a rejtett mező ki van töltve, csendben "sikeres" választ adunk
if ($honey !== '') {
    flash('contact_success', 'Köszönjük az üzeneted! Hamarosan válaszolunk.');
    header('Location: ' . $backUrl);
    exit;
}

$rememberOld = function () use ($name, $email, $subject, $message) {
    $_SESSION['contact_old'] = ['name' => $name, 'email' => $email, 'subject' => $subject, 'message' => $message];
};

$pdo = getDb();
if (recaptchaEnabled($pdo) && !verifyRecaptcha($pdo, $_POST['g-recaptcha-response'] ?? '', $_SERVER['REMOTE_ADDR'] ?? null)) {
    flash('contact_error', 'Kérjük, igazold, hogy nem vagy robot.');
    $rememberOld();
    header('Location: ' . $backUrl);
    exit;
}

if ($name === '' || $email === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('contact_error', 'Kérjük, töltsd ki helyesen a kötelező mezőket: név, érvényes e-mail cím és üzenet.');
    $rememberOld();
    header('Location: ' . $backUrl);
    exit;
}

if (mb_strlen($message) > 5000 || mb_strlen($name) > 150 || mb_strlen($subject) > 200) {
    flash('contact_error', 'A megadott szöveg túl hosszú.');
    $rememberOld();
    header('Location: ' . $backUrl);
    exit;
}

ensureAppSettingsSchema($pdo);
ensureEmailLogSchema($pdo);
$smtp = getSmtpConfig($pdo);

if ($smtp['host'] === '') {
    flash('contact_error', 'Az üzenetküldés jelenleg nem elérhető. Kérjük, írj közvetlenül: info@lizzard.hu');
    $rememberOld();
    header('Location: ' . $backUrl);
    exit;
}

$admins = $pdo->query("SELECT email, firstname, lastname FROM users WHERE role = 'admin' AND active = 1 AND email <> ''")->fetchAll();
if (!$admins) {
    // Tartalék: ha nincs aktív admin e-mail, az alapértelmezett egyesületi címre küldjük
    $admins = [['email' => 'info@lizzard.hu', 'firstname' => '', 'lastname' => 'Lizzard Outdoor']];
}

$mailSubject = 'Kapcsolati űrlap: ' . ($subject !== '' ? $subject : 'Új üzenet a weboldalról');

$nameEsc = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$mailEsc = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$subjEsc = htmlspecialchars($subject !== '' ? $subject : '—', ENT_QUOTES, 'UTF-8');
$msgEsc  = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

$html = '<!DOCTYPE html><html lang="hu"><head><meta charset="UTF-8"></head>'
  . '<body style="margin:0;padding:0;background:#f0ebe0;font-family:system-ui,-apple-system,\'Segoe UI\',Helvetica,Arial,sans-serif;">'
  . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f0ebe0;padding:32px 16px;"><tr><td align="center">'
  . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.12);">'
  . '<tr><td style="background:#1a3d39;padding:26px 40px;text-align:center;"><div style="font-size:22px;font-weight:800;color:#F4E7CF;letter-spacing:.05em;">Lizzard Outdoor</div>'
  . '<div style="font-size:11px;color:#8fb5b2;margin-top:5px;letter-spacing:.14em;text-transform:uppercase;">Új kapcsolati üzenet</div></td></tr>'
  . '<tr><td style="padding:30px 40px 26px;">'
  . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5efe4;border:1px solid #ddd5c5;border-radius:8px;margin-bottom:20px;"><tr><td style="padding:18px 22px;">'
  . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;border-collapse:collapse;">'
  . '<tr><td style="color:#7a7269;padding:0 14px 6px 0;white-space:nowrap;vertical-align:top;">Feladó</td><td style="color:#333;font-weight:600;padding-bottom:6px;">' . $nameEsc . '</td></tr>'
  . '<tr><td style="color:#7a7269;padding:0 14px 6px 0;white-space:nowrap;vertical-align:top;">E-mail</td><td style="color:#333;font-weight:600;padding-bottom:6px;"><a href="mailto:' . $mailEsc . '" style="color:#29776F;">' . $mailEsc . '</a></td></tr>'
  . '<tr><td style="color:#7a7269;padding:0 14px 0 0;white-space:nowrap;vertical-align:top;">Tárgy</td><td style="color:#333;font-weight:600;">' . $subjEsc . '</td></tr>'
  . '</table></td></tr></table>'
  . '<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#7a7269;margin-bottom:8px;">Üzenet</div>'
  . '<div style="font-size:14px;color:#444;line-height:1.7;">' . $msgEsc . '</div>'
  . '</td></tr>'
  . '<tr><td style="background:#f5efe4;border-top:1px solid #ddd5c5;padding:16px 40px;text-align:center;">'
  . '<p style="font-size:12px;color:#7a7269;margin:0;line-height:1.6;">Válaszhoz egyszerűen válaszolj erre az e-mailre — a feladó címére megy.</p>'
  . '</td></tr></table></td></tr></table></body></html>';

$mailer = new SmtpMailer($smtp);
$sentAny = false;
$lastErr = '';

foreach ($admins as $a) {
    $rcptName = trim(($a['lastname'] ?? '') . ' ' . ($a['firstname'] ?? ''));
    try {
        $response = $mailer->send($a['email'], $rcptName, $mailSubject, $html, '', $email);
        logEmailEntry($pdo, null, $a['email'], $rcptName, $mailSubject, $html, 'contact_form', 'sent', '', $response);
        $sentAny = true;
    } catch (Throwable $e) {
        $lastErr = $e->getMessage();
        logEmailEntry($pdo, null, $a['email'], $rcptName, $mailSubject, $html, 'contact_form', 'failed', $lastErr, $lastErr);
        error_log('Contact form email error to ' . $a['email'] . ': ' . $lastErr);
    }
}

if ($sentAny) {
    flash('contact_success', 'Köszönjük az üzeneted! Hamarosan válaszolunk a megadott e-mail címre.');
} else {
    flash('contact_error', 'Az üzenet küldése sikertelen volt. Kérjük, próbáld újra később, vagy írj közvetlenül: info@lizzard.hu');
    $rememberOld();
}

header('Location: ' . $backUrl);
exit;
