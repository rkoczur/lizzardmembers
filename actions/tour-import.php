<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tours-schema.php';
requireAdmin();

verifyCsrf();

$redirectBack = BASE_URL . '/admin/tour-import.php';

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

// Strip UTF-8 BOM if present
$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") {
    rewind($handle);
}

$pdo = getDb();
ensureToursSchema($pdo);

$header = fgetcsv($handle, 0, ';');
if (!$header) {
    fclose($handle);
    flash('error', 'A CSV fájl üres vagy érvénytelen.');
    header('Location: ' . $redirectBack);
    exit;
}

// Load all existing codes to detect duplicates and generate unique new ones
$existingCodes = $pdo->query("SELECT tour_code FROM tours WHERE tour_code IS NOT NULL")
                     ->fetchAll(PDO::FETCH_COLUMN);
$usedCodes = array_fill_keys($existingCodes, true);

$validTourTypes   = ['gyalogos', 'kerekparos', 'vizi', 'si', 'barlangi', 'munka'];
$validAccom       = ['sator', 'turistahaz', 'apartman', 'hotel'];
$validMultiDay    = ['csillag', 'vandor'];

$insertStmt = $pdo->prepare("INSERT INTO tours
    (tour_code, name, country, region, tour_date, days, accommodation,
     tour_type, sub_type, is_alpine,
     total_km, alpine_km, total_elevation, alpine_elevation,
     tour_hours, multi_day_type, camping_nights_fixed, camping_nights_mobile,
     boat_portages, guest_count, points, mtsz_points)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

$imported = 0;
$errors   = [];
$rowNum   = 1;

while (($row = fgetcsv($handle, 0, ';')) !== false) {
    $rowNum++;
    $row = array_pad($row, 20, '');
    $row = array_map('trim', $row);

    [
        $tourCode, $name, $country, $region, $tourDate, $days, $accommodation,
        $tourType, $subType, $totalKm, $alpineKm, $totalElev, $alpineElev,
        $tourHours, $multiDayType, $campFixed, $portages,
        $guestCount, $points, $mtszPoints,
    ] = $row;

    // Skip blank rows
    if ($country === '' && $name === '' && $tourCode === '') {
        continue;
    }

    if ($country === '') {
        $errors[] = ['row' => $rowNum, 'msg' => ($tourCode ?: ($name ?: $rowNum . '. sor')) . ': hiányzó ország (kötelező mező).'];
        continue;
    }

    // Tour code: validate uniqueness if provided, otherwise generate
    if ($tourCode !== '') {
        if (isset($usedCodes[$tourCode])) {
            $errors[] = ['row' => $rowNum, 'msg' => '"' . $tourCode . '" kód már létezik az adatbázisban vagy az importfájlban.'];
            continue;
        }
    } else {
        $type = in_array($tourType, $validTourTypes, true) ? $tourType : 'gyalogos';
        $abbrev = getTourTypeAbbrev($type);
        $max = 0;
        foreach (array_keys($usedCodes) as $c) {
            $n = (int)preg_replace('/[^0-9]/', '', $c);
            if ($n > $max) $max = $n;
        }
        $tourCode = ($max + 1) . $abbrev;
    }
    $usedCodes[$tourCode] = true;

    // Sanitise and coerce
    $tourType       = in_array($tourType, $validTourTypes, true) ? $tourType : 'gyalogos';
    $accommodation  = in_array($accommodation, $validAccom, true) ? $accommodation : null;
    $multiDayType   = in_array($multiDayType, $validMultiDay, true) ? $multiDayType : null;
    $days           = max(1, (int)$days ?: 1);
    $totalKm        = $totalKm   !== '' ? (float)$totalKm   : null;
    $alpineKm       = $alpineKm  !== '' ? (float)$alpineKm  : null;
    $totalElev      = $totalElev !== '' ? (int)$totalElev   : null;
    $alpineElev     = $alpineElev !== '' ? (int)$alpineElev  : null;
    $tourHours      = $tourHours !== '' ? (float)$tourHours  : null;
    $campFixed      = max(0, (int)$campFixed);
    $portages       = max(0, (int)$portages);
    $guestCount     = max(0, (int)$guestCount);
    $points         = max(0, (int)$points);
    $mtszPoints     = max(0, (int)$mtszPoints);
    $isAlpine       = ($alpineKm !== null && $alpineKm > 0) ? 1 : 0;
    $tourDate       = $tourDate !== '' ? $tourDate : null;
    $subType        = $subType  !== '' ? $subType  : null;
    $name           = $name     !== '' ? $name     : null;
    $region         = $region   !== '' ? $region   : null;

    $mtszPoints = calculateTourPoints([
        'tour_type'             => $tourType,
        'sub_type'              => $subType,
        'days'                  => $days,
        'tour_date'             => $tourDate,
        'accommodation'         => $accommodation,
        'total_km'              => $totalKm,
        'alpine_km'             => $alpineKm,
        'total_elevation'       => $totalElev,
        'alpine_elevation'      => $alpineElev,
        'tour_hours'            => $tourHours,
        'multi_day_type'        => $multiDayType,
        'camping_nights_fixed'  => $campFixed,
        'boat_portages'         => $portages,
    ]);

    $insertStmt->execute([
        $tourCode, $name, $country, $region, $tourDate, $days, $accommodation,
        $tourType, $subType, $isAlpine,
        $totalKm, $alpineKm, $totalElev, $alpineElev,
        $tourHours, $multiDayType, $campFixed, 0,
        $portages, $guestCount, $points, $mtszPoints,
    ]);

    $newId = (int)$pdo->lastInsertId();
    logAudit($pdo, 'create', 'tour', $newId, $tourCode . ($name ? ' – ' . $name : ''), [
        ['k' => 'Forrás', 'v' => 'CSV import'],
        ['k' => 'Ország', 'v' => $country],
    ]);

    $imported++;
}

fclose($handle);

$_SESSION['tour_import_results'] = ['imported' => $imported, 'errors' => $errors];

if ($imported > 0) {
    flash('success', $imported . ' túra sikeresen importálva' . (!empty($errors) ? ', ' . count($errors) . ' sor kihagyva' : '') . '.');
} else {
    flash('error', 'Egyetlen túra sem lett importálva.' . (!empty($errors) ? ' ' . count($errors) . ' sor hibás.' : ''));
}

header('Location: ' . $redirectBack);
exit;
