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
$lastPayment       = $_POST['last_payment']         ?? null;

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
        'member_since' => $memberSince ?? '', 'last_payment' => $lastPayment ?? ''];

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
     member_since, last_payment)
    VALUES (?, ?, ?, 'user', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$stmt->execute([
    $username, $email, password_hash($password, PASSWORD_DEFAULT),
    $firstname, $lastname,
    $dateofbirth ?: null, $zipcode ?: null, $city ?: null,
    $address ?: null, $phone ?: null, $tshirtSize ?: null,
    $emergencyName ?: null, $emergencyRelation ?: null, $emergencyPhone ?: null,
    $memberSince ?: null, $lastPayment ?: null,
]);

$newId = (int)$pdo->lastInsertId();
logAudit($pdo, 'create', 'member', $newId, $lastname . ' ' . $firstname, [
    ['k' => 'Felhasználónév', 'v' => $username],
    ['k' => 'E-mail',         'v' => $email],
    ['k' => 'Szerepkör',      'v' => 'Tag'],
]);
flash('success', $lastname . ' ' . $firstname . ' sikeresen regisztrálva.');
header('Location: ' . BASE_URL . '/admin/member-detail.php?id=' . $newId);
exit;
