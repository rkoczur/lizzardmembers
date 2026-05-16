<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

verifyCsrf();

$redirectBack = BASE_URL . '/admin/member-import.php';
$step = trim($_POST['step'] ?? 'validate');

// ── CONFIRM: read validated rows from session and insert ──────────────────────
if ($step === 'confirm') {
    $preview = $_SESSION['member_import_preview'] ?? null;
    if (!$preview || empty($preview['rows'])) {
        flash('error', 'Nincs érvényes előnézeti adat. Töltsd fel újra a fájlt.');
        header('Location: ' . $redirectBack);
        exit;
    }

    $pdo        = getDb();
    $checkStmt  = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $insertStmt = $pdo->prepare("INSERT INTO users
        (username, email, password, role, firstname, lastname, dateofbirth,
         zipcode, city, address, phone, tshirt_size,
         emergency_name, emergency_relation, emergency_phone,
         member_since, last_payment)
        VALUES (?, ?, ?, 'user', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $imported = 0;
    $errors   = [];

    foreach ($preview['rows'] as $r) {
        $checkStmt->execute([$r['username'], $r['email']]);
        if ($checkStmt->rowCount() > 0) {
            $errors[] = ['row' => $r['_row'], 'msg' => "{$r['lastname']} {$r['firstname']}: a felhasználónév vagy e-mail időközben foglalttá vált."];
            continue;
        }

        $insertStmt->execute([
            $r['username'], $r['email'], $r['password_hash'],
            $r['firstname'], $r['lastname'], $r['dateofbirth'],
            $r['zipcode'], $r['city'], $r['address'], $r['phone'],
            $r['tshirt_size'], $r['emergency_name'], $r['emergency_relation'],
            $r['emergency_phone'], $r['member_since'], $r['last_payment'],
        ]);

        $newId = (int)$pdo->lastInsertId();
        logAudit($pdo, 'create', 'member', $newId, $r['lastname'] . ' ' . $r['firstname'], [
            ['k' => 'Forrás', 'v' => 'CSV import'],
            ['k' => 'Felhasználónév', 'v' => $r['username']],
            ['k' => 'E-mail', 'v' => $r['email']],
        ]);
        $imported++;
    }

    unset($_SESSION['member_import_preview']);

    $_SESSION['import_results'] = ['imported' => $imported, 'errors' => $errors];
    if ($imported > 0) {
        flash('success', $imported . ' tag sikeresen importálva' . (!empty($errors) ? ', ' . count($errors) . ' sor kihagyva' : '') . '.');
    } else {
        flash('error', 'Egyetlen tag sem lett importálva.' . (!empty($errors) ? ' ' . count($errors) . ' sor hibás.' : ''));
    }
    header('Location: ' . $redirectBack);
    exit;
}

// ── VALIDATE: parse and validate CSV, no DB inserts ───────────────────────────
if (empty($_FILES['csv_file']['tmp_name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    flash('error', 'Nem sikerült a fájl feltöltése.');
    header('Location: ' . $redirectBack);
    exit;
}

$mime = mime_content_type($_FILES['csv_file']['tmp_name']);
$allowedMimes = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'];
if (!in_array($mime, $allowedMimes, true) && !str_ends_with($_FILES['csv_file']['name'], '.csv')) {
    flash('error', 'Csak CSV fájl tölthető fel.');
    header('Location: ' . $redirectBack);
    exit;
}

$handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
if (!$handle) {
    flash('error', 'Nem sikerült a fájl megnyitása.');
    header('Location: ' . $redirectBack);
    exit;
}

$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") {
    rewind($handle);
}

$pdo    = getDb();
$header = fgetcsv($handle, 0, ';');
if (!$header) {
    fclose($handle);
    flash('error', 'A CSV fájl üres vagy érvénytelen.');
    header('Location: ' . $redirectBack);
    exit;
}

$validSizes     = ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
$validRelations = ['szülő', 'gyermek', 'testvér', 'egyéb'];
$checkStmt      = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");

$validRows     = [];
$errors        = [];
$rowNum        = 1;
$totalDataRows = 0;
$seenUsernames = [];
$seenEmails    = [];

while (($row = fgetcsv($handle, 0, ';')) !== false) {
    $rowNum++;
    $row = array_pad($row, 16, '');

    [$lastname, $firstname, $username, $email, $password,
     $phone, $dateofbirth, $memberSince, $lastPayment,
     $zipcode, $city, $address, $tshirtSize,
     $emergencyName, $emergencyRelation, $emergencyPhone] = array_map('trim', $row);

    if ($lastname === '' && $firstname === '' && $username === '' && $email === '') {
        continue;
    }
    $totalDataRows++;

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

    $usernameLc = strtolower($username);
    $emailLc    = strtolower($email);
    if (isset($seenUsernames[$usernameLc]) || isset($seenEmails[$emailLc])) {
        $errors[] = ['row' => $rowNum, 'msg' => "$lastname $firstname: a felhasználónév vagy e-mail a fájlon belül ismétlődik ($username / $email)."];
        continue;
    }

    $checkStmt->execute([$username, $email]);
    if ($checkStmt->rowCount() > 0) {
        $errors[] = ['row' => $rowNum, 'msg' => "$lastname $firstname: a felhasználónév vagy e-mail már foglalt ($username / $email)."];
        continue;
    }

    $seenUsernames[$usernameLc] = true;
    $seenEmails[$emailLc]       = true;

    $validRows[] = [
        '_row'               => $rowNum,
        'lastname'           => $lastname,
        'firstname'          => $firstname,
        'username'           => $username,
        'email'              => $email,
        'password_hash'      => password_hash($password, PASSWORD_DEFAULT),
        'phone'              => $phone ?: null,
        'dateofbirth'        => $dateofbirth ?: null,
        'member_since'       => $memberSince ?: null,
        'last_payment'       => $lastPayment ?: null,
        'zipcode'            => $zipcode ?: null,
        'city'               => $city ?: null,
        'address'            => $address ?: null,
        'tshirt_size'        => in_array(strtoupper($tshirtSize), $validSizes, true) ? strtoupper($tshirtSize) : null,
        'emergency_name'     => $emergencyName ?: null,
        'emergency_relation' => in_array($emergencyRelation, $validRelations, true) ? $emergencyRelation : null,
        'emergency_phone'    => $emergencyPhone ?: null,
    ];
}

fclose($handle);

$_SESSION['member_import_preview'] = [
    'rows'            => $validRows,
    'errors'          => $errors,
    'total_data_rows' => $totalDataRows,
];

header('Location: ' . $redirectBack);
exit;
