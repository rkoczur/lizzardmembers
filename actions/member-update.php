<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

verifyCsrf();

$pdo      = getDb();
$memberId = (int)($_POST['id'] ?? 0);
if (!$memberId) {
    header('Location: ' . BASE_URL . '/admin/members.php');
    exit;
}

$redirectTo = BASE_URL . '/admin/member-detail.php?id=' . $memberId;

$firstname   = trim($_POST['firstname']   ?? '');
$lastname    = trim($_POST['lastname']    ?? '');
$username    = trim($_POST['username']    ?? '');
$email       = trim($_POST['email']       ?? '');
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
$newPassword = $_POST['new_password']    ?? '';
$newPassword2= $_POST['new_password2']  ?? '';
$roleInput   = $_POST['role'] ?? 'user';
$role        = in_array($roleInput, ['admin', 'user'], true) ? $roleInput : 'user';

// Prevent self-demotion
if ($memberId === getCurrentUserId() && $role !== 'admin') {
    $role = 'admin';
}

if (!$firstname || !$lastname || !$username || !$email) {
    flash('error', 'A keresztnév, a vezetéknév, a felhasználónév és az e-mail-cím megadása kötelező.');
    header('Location: ' . $redirectTo);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('error', 'Kérjük, adjon meg érvényes e-mail-címet.');
    header('Location: ' . $redirectTo);
    exit;
}

$check = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
$check->execute([$username, $email, $memberId]);
if ($check->rowCount() > 0) {
    flash('error', 'A felhasználónév vagy az e-mail-cím már használatban van egy másik fiók által.');
    header('Location: ' . $redirectTo);
    exit;
}

if ($newPassword !== '' && $newPassword !== $newPassword2) {
    flash('error', 'A jelszavak nem egyeznek.');
    header('Location: ' . $redirectTo);
    exit;
}

// Avatar upload
$avatarFilename = null;
if (!empty($_FILES['avatar']['name'])) {
    $file     = $_FILES['avatar'];
    $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowed, true)) {
        flash('error', 'Érvénytelen képtípus.');
        header('Location: ' . $redirectTo);
        exit;
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        flash('error', 'A képnek 2 MB-nál kisebbnek kell lennie.');
        header('Location: ' . $redirectTo);
        exit;
    }

    $ext            = pathinfo($file['name'], PATHINFO_EXTENSION);
    $avatarFilename = 'avatar_' . $memberId . '_' . time() . '.' . strtolower($ext);

    if (!is_dir(AVATAR_DIR)) {
        mkdir(AVATAR_DIR, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], AVATAR_DIR . $avatarFilename)) {
        flash('error', 'A kép feltöltése sikertelen.');
        header('Location: ' . $redirectTo);
        exit;
    }

    $old = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $old->execute([$memberId]);
    $oldPic = $old->fetchColumn();
    if ($oldPic && file_exists(AVATAR_DIR . $oldPic)) {
        @unlink(AVATAR_DIR . $oldPic);
    }
}

$beforeStmt = $pdo->prepare("SELECT firstname, lastname, username, email, dateofbirth, zipcode, city, address, phone, tshirt_size, emergency_name, emergency_relation, emergency_phone, member_since, last_payment, role FROM users WHERE id = ?");
$beforeStmt->execute([$memberId]);
$before = $beforeStmt->fetch();

$fields = ['firstname=?','lastname=?','username=?','email=?',
           'dateofbirth=?','zipcode=?','city=?','address=?','phone=?',
           'tshirt_size=?','emergency_name=?','emergency_relation=?','emergency_phone=?',
           'member_since=?','last_payment=?','role=?'];
$params = [$firstname, $lastname, $username, $email,
           $dateofbirth ?: null, $zipcode ?: null, $city ?: null,
           $address ?: null, $phone ?: null, $tshirtSize ?: null,
           $emergencyName ?: null, $emergencyRelation ?: null, $emergencyPhone ?: null,
           $memberSince ?: null, $lastPayment ?: null,
           $role];

if ($avatarFilename) {
    $fields[] = 'profile_picture=?';
    $params[]  = $avatarFilename;
}
if ($newPassword !== '') {
    $fields[] = 'password=?';
    $params[]  = password_hash($newPassword, PASSWORD_DEFAULT);
}

$params[] = $memberId;
$pdo->prepare("UPDATE users SET " . implode(',', $fields) . " WHERE id=?")->execute($params);

$auditFieldLabels = [
    'firstname' => 'Keresztnév', 'lastname' => 'Vezetéknév',
    'username' => 'Felhasználónév', 'email' => 'E-mail',
    'dateofbirth' => 'Születési dátum', 'zipcode' => 'Irányítószám',
    'city' => 'Város', 'address' => 'Cím', 'phone' => 'Telefon',
    'tshirt_size' => 'Pólóméret', 'emergency_name' => 'Vészhelyzeti kapcsolattartó',
    'emergency_relation' => 'Kapcsolat típusa', 'emergency_phone' => 'Vészhelyzeti telefon',
    'member_since' => 'Tag azóta', 'last_payment' => 'Utolsó díjfizetés', 'role' => 'Szerepkör',
];
$auditNewValues = [
    'firstname' => $firstname, 'lastname' => $lastname,
    'username' => $username, 'email' => $email,
    'dateofbirth' => $dateofbirth ?: '', 'zipcode' => $zipcode,
    'city' => $city, 'address' => $address, 'phone' => $phone,
    'tshirt_size' => $tshirtSize ?: '', 'emergency_name' => $emergencyName,
    'emergency_relation' => $emergencyRelation ?: '', 'emergency_phone' => $emergencyPhone,
    'member_since' => $memberSince ?: '', 'last_payment' => $lastPayment ?: '', 'role' => $role,
];
$auditChanges = [];
foreach ($auditFieldLabels as $field => $label) {
    $oldVal = (string)($before[$field] ?? '');
    $newVal = (string)($auditNewValues[$field] ?? '');
    if ($oldVal !== $newVal) {
        $auditChanges[] = ['k' => $label, 'f' => $oldVal ?: '—', 't' => $newVal ?: '—'];
    }
}
if ($avatarFilename)  $auditChanges[] = ['k' => 'Profilkép', 'f' => '—', 't' => 'frissítve'];
if ($newPassword !== '') $auditChanges[] = ['k' => 'Jelszó',    'f' => '—', 't' => 'megváltoztatva'];
logAudit($pdo, 'update', 'member', $memberId, $lastname . ' ' . $firstname, $auditChanges ?: null);

flash('success', 'A tag sikeresen frissítve.');
header('Location: ' . $redirectTo);
exit;
