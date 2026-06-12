<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

verifyCsrf();

$pdo = getDb();

$firstname   = trim($_POST['firstname']   ?? '');
$lastname    = trim($_POST['lastname']    ?? '');
$username    = trim($_POST['username']    ?? '');
$email       = trim($_POST['email']       ?? '');
$password    = $_POST['password']         ?? '';
$password2   = $_POST['password2']        ?? '';
$dateofbirth       = $_POST['dateofbirth']        ?? null;
$zipcode           = trim($_POST['zipcode']         ?? '');
$city              = trim($_POST['city']            ?? '');
$address           = trim($_POST['address']         ?? '');
$phone             = trim($_POST['phone']           ?? '');
$tshirtSize        = $_POST['tshirt_size']          ?? null;
$emergencyName     = trim($_POST['emergency_name']  ?? '');
$emergencyRelation = $_POST['emergency_relation']   ?? null;
$emergencyPhone    = trim($_POST['emergency_phone'] ?? '');
$memberSince       = $_POST['member_since']         ?? null;
// Az utolsó tagdíj fizetés a tranzakciós naplóból származtatott — nem adható meg felvételkor.

$redirectBack = BASE_URL . '/admin/member-add.php';

function redirectWithError(string $msg, string $url, array $old): never {
    flash('error', $msg);
    $_SESSION['form_old'] = $old;
    header('Location: ' . $url);
    exit;
}

$old = ['firstname' => $firstname, 'lastname' => $lastname, 'username' => $username,
        'email' => $email, 'dateofbirth' => $dateofbirth ?? '', 'zipcode' => $zipcode,
        'city' => $city, 'address' => $address, 'phone' => $phone,
        'tshirt_size' => $tshirtSize ?? '', 'emergency_name' => $emergencyName,
        'emergency_relation' => $emergencyRelation ?? '', 'emergency_phone' => $emergencyPhone,
        'member_since' => $memberSince ?? ''];

if (!$firstname || !$lastname || !$username || !$email || !$password) {
    redirectWithError('A keresztnév, a vezetéknév, a felhasználónév, az e-mail és a jelszó megadása kötelező.', $redirectBack, $old);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectWithError('Kérjük, adjon meg érvényes e-mail-címet.', $redirectBack, $old);
}
if (strlen($password) < 6) {
    redirectWithError('A jelszónak legalább 6 karakter hosszúnak kell lennie.', $redirectBack, $old);
}
if ($password !== $password2) {
    redirectWithError('A jelszavak nem egyeznek.', $redirectBack, $old);
}

$check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
$check->execute([$username, $email]);
if ($check->rowCount() > 0) {
    redirectWithError('A felhasználónév vagy az e-mail-cím már használatban van.', $redirectBack, $old);
}

$stmt = $pdo->prepare("INSERT INTO users
    (username, email, password, role, firstname, lastname, dateofbirth,
     zipcode, city, address, phone, tshirt_size,
     emergency_name, emergency_relation, emergency_phone,
     member_since)
    VALUES (?, ?, ?, 'user', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->execute([
    $username, $email, password_hash($password, PASSWORD_DEFAULT),
    $firstname, $lastname,
    $dateofbirth ?: null, $zipcode ?: null, $city ?: null,
    $address ?: null, $phone ?: null, $tshirtSize ?: null,
    $emergencyName ?: null, $emergencyRelation ?: null, $emergencyPhone ?: null,
    $memberSince ?: null,
]);

$newId = (int)$pdo->lastInsertId();
logAudit($pdo, 'create', 'member', $newId, $lastname . ' ' . $firstname, [
    ['k' => 'Felhasználónév', 'v' => $username],
    ['k' => 'E-mail',         'v' => $email],
    ['k' => 'Szerepkör',      'v' => 'Tag'],
]);

$baseMsg = $lastname . ' ' . $firstname . ' sikeresen regisztrálva.';

if (($_POST['send_welcome_email'] ?? '') === '1') {
    try {
        require_once __DIR__ . '/../includes/app-settings-schema.php';
        require_once __DIR__ . '/../includes/mailer.php';
        require_once __DIR__ . '/../includes/welcome-email.php';

        ensureAppSettingsSchema($pdo);
        $smtp = getSmtpConfig($pdo);

        if ($smtp['host'] !== '') {
            $proto    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $loginUrl = $proto . '://' . $_SERVER['HTTP_HOST'] . BASE_URL . '/login.php';
            $html     = buildWelcomeEmailHtml($firstname, $username, $password, $loginUrl, APP_NAME);
            $mailer   = new SmtpMailer($smtp);
            $mailer->send($email, $lastname . ' ' . $firstname, 'Üdvözlünk a Lizzard Outdoorban!', $html);
            flash('success', $baseMsg . ' Az üdvözlő e-mail elküldve.');
        } else {
            flash('success', $baseMsg . ' (SMTP nincs beállítva, e-mail nem lett elküldve.)');
        }
    } catch (Throwable $ex) {
        error_log('Welcome email error for ' . $username . ': ' . $ex->getMessage());
        flash('success', $baseMsg);
        flash('error', 'Az üdvözlő e-mail küldése sikertelen: ' . $ex->getMessage());
    }
} else {
    flash('success', $baseMsg);
}

header('Location: ' . BASE_URL . '/admin/member-detail.php?id=' . $newId);
exit;
