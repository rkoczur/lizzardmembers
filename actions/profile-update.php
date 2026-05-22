<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

verifyCsrf();

$pdo        = getDb();
$userId     = getCurrentUserId();
$redirectTo = in_array($_POST['redirect_to'] ?? '', [
    BASE_URL . '/user/profile.php',
    BASE_URL . '/admin/profile.php',
], true) ? $_POST['redirect_to'] : BASE_URL . '/user/profile.php';

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
$newPassword = $_POST['new_password']     ?? '';
$newPassword2= $_POST['new_password2']   ?? '';

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

// Check username / email uniqueness
$check = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
$check->execute([$username, $email, $userId]);
if ($check->rowCount() > 0) {
    flash('error', 'A felhasználónév vagy az e-mail-cím már foglalt egy másik fiók által.');
    header('Location: ' . $redirectTo);
    exit;
}

if ($newPassword !== '' && $newPassword !== $newPassword2) {
    flash('error', 'A jelszavak nem egyeznek.');
    header('Location: ' . $redirectTo);
    exit;
}
if ($newPassword !== '' && strlen($newPassword) < 6) {
    flash('error', 'A jelszónak legalább 6 karakter hosszúnak kell lennie.');
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
        flash('error', 'Érvénytelen képtípus. Megengedett: JPG, PNG, GIF, WEBP.');
        header('Location: ' . $redirectTo);
        exit;
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        flash('error', 'A képnek 2 MB-nál kisebbnek kell lennie.');
        header('Location: ' . $redirectTo);
        exit;
    }

    $ext            = pathinfo($file['name'], PATHINFO_EXTENSION);
    $avatarFilename = 'avatar_' . $userId . '_' . time() . '.' . strtolower($ext);

    if (!is_dir(AVATAR_DIR)) {
        mkdir(AVATAR_DIR, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], AVATAR_DIR . $avatarFilename)) {
        flash('error', 'A kép feltöltése sikertelen. Ellenőrizze a mappa engedélyeit.');
        header('Location: ' . $redirectTo);
        exit;
    }

    // Delete old avatar
    $old = $pdo->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $old->execute([$userId]);
    $oldPic = $old->fetchColumn();
    if ($oldPic && file_exists(AVATAR_DIR . $oldPic)) {
        @unlink(AVATAR_DIR . $oldPic);
    }
}

// Notification preferences — defined keys, opt-out model (missing = enabled)
$notifKeys    = ['tour_added'];
$notifRaw     = $_POST['notif'] ?? [];
$notifPrefs   = [];
foreach ($notifKeys as $key) {
    $notifPrefs[$key] = isset($notifRaw[$key]) ? 1 : 0;
}

// Build update query
$fields = ['firstname=?','lastname=?','username=?','email=?',
           'dateofbirth=?','zipcode=?','city=?','address=?','phone=?',
           'tshirt_size=?','emergency_name=?','emergency_relation=?','emergency_phone=?',
           'notification_prefs=?'];
$params = [$firstname, $lastname, $username, $email,
           $dateofbirth ?: null, $zipcode ?: null, $city ?: null,
           $address ?: null, $phone ?: null, $tshirtSize ?: null,
           $emergencyName ?: null, $emergencyRelation ?: null, $emergencyPhone ?: null,
           json_encode($notifPrefs, JSON_UNESCAPED_UNICODE)];

if ($avatarFilename) {
    $fields[] = 'profile_picture=?';
    $params[]  = $avatarFilename;
}
if ($newPassword !== '') {
    $fields[] = 'password=?';
    $params[]  = password_hash($newPassword, PASSWORD_DEFAULT);
}

$params[] = $userId;
$pdo->prepare("UPDATE users SET " . implode(',', $fields) . " WHERE id=?")->execute($params);

// Refresh session
$_SESSION['user_name']   = trim($lastname . ' ' . $firstname);
$_SESSION['username']    = $username;
if ($avatarFilename) {
    $_SESSION['user_avatar'] = $avatarFilename;
}

flash('success', 'A profil sikeresen frissítve.');
header('Location: ' . $redirectTo);
exit;
