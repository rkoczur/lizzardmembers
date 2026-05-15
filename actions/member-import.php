<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

verifyCsrf();

$redirectBack = BASE_URL . '/admin/member-import.php';

if (empty($_FILES['csv_file']['tmp_name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    flash('error', 'Nem sikerült a fájl feltöltése.');
    header('Location: ' . $redirectBack);
    exit;
}

$mime = mime_content_type($_FILES['csv_file']['tmp_name']);
$allowedMimes = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'];
if (!in_array($mime, $allowedMimes, true) && !str_ends_with($_FILES['csv_file']['name'], '.csv')) {
    flash('error', 'Csak CSV fájl tölthet fel.');
    header('Location: ' . $redirectBack);
    exit;
}

$handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
if (!$handle) {
    flash('error', 'Nem sikerült a fájl megnyitása.');
    header('Location: ' . $redirectBack);
    exit;
}

// Strip UTF-8 BOM if present
$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") {
    rewind($handle);
}

$pdo = getDb();

// Read and discard header row
$header = fgetcsv($handle, 0, ';');
if (!$header) {
    fclose($handle);
    flash('error', 'A CSV fájl üres vagy érvénytelen.');
    header('Location: ' . $redirectBack);
    exit;
}

$imported = 0;
$errors   = [];
$rowNum   = 1; // header was row 1

$validSizes     = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
$validRelations = ['szülő', 'gyermek', 'testvér', 'egyéb'];

$insertStmt = $pdo->prepare("INSERT INTO users
    (username, email, password, role, firstname, lastname, dateofbirth,
     zipcode, city, address, phone, tshirt_size,
     emergency_name, emergency_relation, emergency_phone,
     member_since, last_payment)
    VALUES (?, ?, ?, 'user', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");

while (($row = fgetcsv($handle, 0, ';')) !== false) {
    $rowNum++;

    // Pad short rows with empty strings
    $row = array_pad($row, 16, '');

    [$lastname, $firstname, $username, $email, $password,
     $phone, $dateofbirth, $memberSince, $lastPayment,
     $zipcode, $city, $address, $tshirtSize,
     $emergencyName, $emergencyRelation, $emergencyPhone] = array_map('trim', $row);

    // Skip entirely blank rows
    if ($lastname === '' && $firstname === '' && $username === '' && $email === '') {
        continue;
    }

    // Required field validation
    if (!$lastname || !$firstname) {
        $errors[] = ['row' => $rowNum, 'msg' => 'Hiányzó vezetéknév vagy keresztnév.'];
        continue;
    }
    if (!$username) {
        $errors[] = ['row' => $rowNum, 'msg' => "$lastname $firstname: hiányzó felhasználónév."];
        continue;
    }
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = ['row' => $rowNum, 'msg' => "$lastname $firstname: érvénytelen e-mail-cím."];
        continue;
    }
    if (!$password || strlen($password) < 6) {
        $errors[] = ['row' => $rowNum, 'msg' => "$lastname $firstname: a jelszó legalább 6 karakter kell legyen."];
        continue;
    }

    // Duplicate check
    $checkStmt->execute([$username, $email]);
    if ($checkStmt->rowCount() > 0) {
        $errors[] = ['row' => $rowNum, 'msg' => "$lastname $firstname: a felhasználónév vagy e-mail már foglalt ($username / $email)."];
        continue;
    }

    // Optional field sanitisation
    $tshirtSize       = in_array(strtoupper($tshirtSize), $validSizes, true) ? strtoupper($tshirtSize) : null;
    $emergencyRelation = in_array($emergencyRelation, $validRelations, true) ? $emergencyRelation : null;
    $dateofbirth      = $dateofbirth  ? $dateofbirth  : null;
    $memberSince      = $memberSince  ? $memberSince  : null;
    $lastPayment      = $lastPayment  ? $lastPayment  : null;

    $insertStmt->execute([
        $username,
        $email,
        password_hash($password, PASSWORD_DEFAULT),
        $firstname,
        $lastname,
        $dateofbirth,
        $zipcode  ?: null,
        $city     ?: null,
        $address  ?: null,
        $phone    ?: null,
        $tshirtSize,
        $emergencyName  ?: null,
        $emergencyRelation,
        $emergencyPhone ?: null,
        $memberSince,
        $lastPayment,
    ]);

    $newId = (int)$pdo->lastInsertId();
    logAudit($pdo, 'create', 'member', $newId, $lastname . ' ' . $firstname, [
        ['k' => 'Forrás', 'v' => 'CSV import'],
        ['k' => 'Felhasználónév', 'v' => $username],
        ['k' => 'E-mail', 'v' => $email],
    ]);

    $imported++;
}

fclose($handle);

$_SESSION['import_results'] = ['imported' => $imported, 'errors' => $errors];

if ($imported > 0) {
    flash('success', $imported . ' tag sikeresen importálva' . (!empty($errors) ? ', ' . count($errors) . ' sor kihagyva' : '') . '.');
} else {
    flash('error', 'Egyetlen tag sem lett importálva.' . (!empty($errors) ? ' ' . count($errors) . ' sor hibás.' : ''));
}

header('Location: ' . $redirectBack);
exit;
