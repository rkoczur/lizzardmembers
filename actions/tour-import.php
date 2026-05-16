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
$step = trim($_POST['step'] ?? 'validate');

// ── CONFIRM: read validated rows from session and insert ──────────────────────
if ($step === 'confirm') {
    $preview = $_SESSION['tour_import_preview'] ?? null;
    if (!$preview || empty($preview['rows'])) {
        flash('error', 'Nincs érvényes előnézeti adat. Töltsd fel újra a fájlt.');
        header('Location: ' . $redirectBack);
        exit;
    }

    $pdo = getDb();
    ensureToursSchema($pdo);

    $checkCode  = $pdo->prepare("SELECT id FROM tours WHERE tour_code = ?");
    $insertStmt = $pdo->prepare("INSERT INTO tours
        (tour_code, name, route, country, region, tour_date, days, accommodation,
         tour_type, sub_type, is_alpine,
         total_km, alpine_km, total_elevation, alpine_elevation,
         tour_hours, multi_day_type, camping_nights_fixed, camping_nights_mobile,
         boat_portages, guest_count, points, mtsz_points)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $imported = 0;
    $errors   = [];

    foreach ($preview['rows'] as $r) {
        $checkCode->execute([$r['tour_code']]);
        if ($checkCode->rowCount() > 0) {
            $errors[] = ['row' => $r['_row'], 'msg' => '"' . $r['tour_code'] . '" kód időközben már bekerült az adatbázisba.'];
            continue;
        }

        $insertStmt->execute([
            $r['tour_code'], $r['name'], $r['route'], $r['country'], $r['region'],
            $r['tour_date'], $r['days'], $r['accommodation'],
            $r['tour_type'], $r['sub_type'], $r['is_alpine'],
            $r['total_km'], $r['alpine_km'], $r['total_elevation'], $r['alpine_elevation'],
            $r['tour_hours'], $r['multi_day_type'], $r['camping_nights_fixed'], 0,
            $r['boat_portages'], $r['guest_count'], $r['points'], $r['mtsz_points'],
        ]);

        $newId = (int)$pdo->lastInsertId();
        logAudit($pdo, 'create', 'tour', $newId, $r['tour_code'] . ($r['name'] ? ' – ' . $r['name'] : ''), [
            ['k' => 'Forrás', 'v' => 'CSV import'],
            ['k' => 'Ország', 'v' => $r['country']],
        ]);
        $imported++;
    }

    unset($_SESSION['tour_import_preview']);

    $_SESSION['tour_import_results'] = ['imported' => $imported, 'errors' => $errors];
    if ($imported > 0) {
        flash('success', $imported . ' túra sikeresen importálva' . (!empty($errors) ? ', ' . count($errors) . ' sor kihagyva' : '') . '.');
    } else {
        flash('error', 'Egyetlen túra sem lett importálva.' . (!empty($errors) ? ' ' . count($errors) . ' sor hibás.' : ''));
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

$pdo = getDb();
ensureToursSchema($pdo);

$header = fgetcsv($handle, 0, ';');
if (!$header) {
    fclose($handle);
    flash('error', 'A CSV fájl üres vagy érvénytelen.');
    header('Location: ' . $redirectBack);
    exit;
}

$existingCodes = $pdo->query("SELECT tour_code FROM tours WHERE tour_code IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
$usedCodes     = array_fill_keys($existingCodes, true);

$validTourTypes = ['gyalogos', 'kerekparos', 'vizi', 'si', 'barlangi', 'munka'];
$validSubTypes  = [
    'gyalogos'   => ['normal', 'tajekozodasi'],
    'kerekparos' => ['mout', 'terep'],
    'vizi'       => ['folyasirany', 'allovi', 'szemben'],
    'barlangi'   => ['kiepitett', 'kiepitetlen'],
    'si'         => [],
    'munka'      => [],
];
$validAccom    = ['sator', 'turistahaz', 'apartman', 'hotel'];
$validMultiDay = ['csillag', 'vandor'];

$validRows     = [];
$errors        = [];
$rowNum        = 1;
$totalDataRows = 0;

while (($row = fgetcsv($handle, 0, ';')) !== false) {
    $rowNum++;
    $row = array_pad($row, 20, '');
    $row = array_map('trim', $row);

    [
        $tourCode, $name, $route, $country, $region, $tourDate, $days, $accommodation,
        $tourType, $subType, $totalKm, $alpineKm, $totalElev, $alpineElev,
        $tourHours, $multiDayType, $campFixed, $portages,
        $guestCount, $points,
    ] = $row;

    if ($country === '' && $name === '' && $tourCode === '') {
        continue;
    }
    $totalDataRows++;

    if ($country === '') {
        $errors[] = ['row' => $rowNum, 'msg' => ($tourCode ?: ($name ?: $rowNum . '. sor')) . ': hiányzó országkód (kötelező mező, pl. HU, AT, SK).'];
        continue;
    }
    $country = strtoupper($country);
    if (!getCountryByCode($pdo, $country)) {
        $errors[] = ['row' => $rowNum, 'msg' => ($tourCode ?: ($name ?: $rowNum . '. sor')) . ': "' . $country . '" ismeretlen országkód — ellenőrizd a Beállítások › Országok listát.'];
        continue;
    }

    if ($tourCode !== '') {
        if (isset($usedCodes[$tourCode])) {
            $errors[] = ['row' => $rowNum, 'msg' => '"' . $tourCode . '" kód már létezik az adatbázisban vagy az importfájlban.'];
            continue;
        }
    } else {
        $type   = in_array($tourType, $validTourTypes, true) ? $tourType : 'gyalogos';
        $abbrev = getTourTypeAbbrev($type);
        $max    = 0;
        foreach (array_keys($usedCodes) as $c) {
            $n = (int)preg_replace('/[^0-9]/', '', $c);
            if ($n > $max) $max = $n;
        }
        $tourCode = ($max + 1) . $abbrev;
    }
    $usedCodes[$tourCode] = true;

    $tourType = in_array($tourType, $validTourTypes, true) ? $tourType : 'gyalogos';
    $allowed  = $validSubTypes[$tourType] ?? [];
    if ($subType === '') {
        $subType = $allowed[0] ?? null;
    } elseif ($allowed && !in_array($subType, $allowed, true)) {
        $errors[] = ['row' => $rowNum, 'msg' => ($tourCode ?: $rowNum . '. sor') . ': érvénytelen altípus "' . $subType . '" (' . $tourType . '). Elfogadott értékek: ' . implode(', ', $allowed) . '.'];
        continue;
    } elseif (!$allowed) {
        $subType = null;
    }

    $accommodation = in_array($accommodation, $validAccom, true) ? $accommodation : null;
    $multiDayType  = in_array($multiDayType, $validMultiDay, true) ? $multiDayType : null;
    $days          = max(1, (int)$days ?: 1);
    $totalKm       = $totalKm    !== '' ? (float)$totalKm    : null;
    $alpineKm      = $alpineKm   !== '' ? (float)$alpineKm   : null;
    $totalElev     = $totalElev  !== '' ? (int)$totalElev    : null;
    $alpineElev    = $alpineElev !== '' ? (int)$alpineElev   : null;
    $tourHours     = $tourHours  !== '' ? (float)$tourHours  : null;
    $campFixed     = max(0, (int)$campFixed);
    $portages      = max(0, (int)$portages);
    $guestCount    = max(0, (int)$guestCount);
    $points        = max(0, (int)$points);
    $isAlpine      = ($alpineKm !== null && $alpineKm > 0) ? 1 : 0;
    $tourDate      = $tourDate !== '' ? $tourDate : null;
    $subType       = ($subType !== '' && $subType !== null) ? $subType : null;

    $mtszPoints = calculateTourPoints([
        'tour_type'            => $tourType,
        'sub_type'             => $subType,
        'days'                 => $days,
        'tour_date'            => $tourDate,
        'accommodation'        => $accommodation,
        'total_km'             => $totalKm,
        'alpine_km'            => $alpineKm,
        'total_elevation'      => $totalElev,
        'alpine_elevation'     => $alpineElev,
        'tour_hours'           => $tourHours,
        'multi_day_type'       => $multiDayType,
        'camping_nights_fixed' => $campFixed,
        'boat_portages'        => $portages,
    ]);

    $validRows[] = [
        '_row'                 => $rowNum,
        'tour_code'            => $tourCode,
        'name'                 => $name !== '' ? $name : null,
        'route'                => $route !== '' ? $route : null,
        'country'              => $country,
        'region'               => $region !== '' ? $region : null,
        'tour_date'            => $tourDate,
        'days'                 => $days,
        'accommodation'        => $accommodation,
        'tour_type'            => $tourType,
        'sub_type'             => $subType,
        'is_alpine'            => $isAlpine,
        'total_km'             => $totalKm,
        'alpine_km'            => $alpineKm,
        'total_elevation'      => $totalElev,
        'alpine_elevation'     => $alpineElev,
        'tour_hours'           => $tourHours,
        'multi_day_type'       => $multiDayType,
        'camping_nights_fixed' => $campFixed,
        'boat_portages'        => $portages,
        'guest_count'          => $guestCount,
        'points'               => $points,
        'mtsz_points'          => $mtszPoints,
    ];
}

fclose($handle);

$_SESSION['tour_import_preview'] = [
    'rows'            => $validRows,
    'errors'          => $errors,
    'total_data_rows' => $totalDataRows,
];

header('Location: ' . $redirectBack);
exit;
