<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
requireAdmin();
verifyCsrf();

$pdo = getDb();
ensureFutureToursSchema($pdo);

$name          = trim($_POST['name']          ?? '');
$description   = trim($_POST['description']   ?? '');
$startDate     = trim($_POST['start_date']    ?? '');
$numDays       = max(1, count(array_filter($_POST['day_number'] ?? [], fn($v) => $v !== '')));
$maxAttendees  = max(1, (int)($_POST['max_attendees'] ?? 1));
$country       = trim($_POST['country']       ?? '') ?: null;
$region        = trim($_POST['region']        ?? '') ?: null;
$fee           = ($_POST['participation_fee'] ?? '') !== '' ? max(0, (int)$_POST['participation_fee']) : null;
$status        = in_array($_POST['status'] ?? '', ['open','closed','cancelled']) ? $_POST['status'] : 'open';
$accommodation = trim($_POST['accommodation'] ?? '') ?: null;
$travel        = trim($_POST['travel']        ?? '') ?: null;
$equipment     = trim($_POST['equipment']     ?? '') ?: null;
$experience    = trim($_POST['experience']    ?? '') ?: null;

$allowedStdFields   = ['departure_city', 'car_available', 'sharing_room', 'notes'];
$visibleFields      = array_values(array_intersect($_POST['visible_fields'] ?? [], $allowedStdFields));
$disabledFields     = array_values(array_diff($allowedStdFields, $visibleFields));
$disabledFieldsJson = !empty($disabledFields) ? json_encode($disabledFields) : null;

if (!$name) {
    flash('error', 'A túra nevének megadása kötelező.');
    header('Location: ' . BASE_URL . '/admin/future-tour-detail.php?new=1');
    exit;
}
if (!$startDate) {
    flash('error', 'A kezdés dátumának megadása kötelező.');
    header('Location: ' . BASE_URL . '/admin/future-tour-detail.php?new=1');
    exit;
}

$stmt = $pdo->prepare("INSERT INTO future_tours (name, description, start_date, num_days, max_attendees, participation_fee, country, region, accommodation, travel, equipment, experience, status, disabled_standard_fields, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$name, $description ?: null, $startDate, $numDays, $maxAttendees, $fee, $country, $region, $accommodation, $travel, $equipment, $experience, $status, $disabledFieldsJson, getCurrentUserId()]);
$tourId = (int)$pdo->lastInsertId();

// Save days
$dayNumbers     = $_POST['day_number']     ?? [];
$dayTypes       = $_POST['day_tour_type']  ?? [];
$dayKms         = $_POST['day_km']         ?? [];
$dayElevations  = $_POST['day_elevation']  ?? [];
$dayDescs       = $_POST['day_description'] ?? [];

$dayStmt = $pdo->prepare("INSERT INTO future_tour_days (future_tour_id, day_number, tour_type, km, elevation, description) VALUES (?, ?, ?, ?, ?, ?)");
foreach ($dayNumbers as $i => $dayNum) {
    $dayStmt->execute([
        $tourId,
        max(1, (int)$dayNum),
        trim($dayTypes[$i] ?? '') ?: null,
        ($dayKms[$i] ?? '') !== '' ? (float)$dayKms[$i] : null,
        ($dayElevations[$i] ?? '') !== '' ? (int)$dayElevations[$i] : null,
        trim($dayDescs[$i] ?? '') ?: null,
    ]);
}

// Save custom fields
$fieldNames   = $_POST['field_name']    ?? [];
$fieldTypes   = $_POST['field_type']   ?? [];
$fieldOptions = $_POST['field_options'] ?? [];
$fieldStmt    = $pdo->prepare("INSERT INTO future_tour_custom_fields (future_tour_id, field_name, field_type, field_options, sort_order) VALUES (?, ?, ?, ?, ?)");
foreach ($fieldNames as $i => $fname) {
    $fname = trim($fname);
    if (!$fname) continue;
    $ftype   = in_array($fieldTypes[$i] ?? '', ['text','number','checkbox','select','textarea']) ? $fieldTypes[$i] : 'text';
    $fopts   = $ftype === 'select' ? (trim($fieldOptions[$i] ?? '') ?: null) : null;
    $fieldStmt->execute([$tourId, $fname, $ftype, $fopts, $i]);
}

flash('success', 'Meghirdetett túra sikeresen létrehozva.');
header('Location: ' . BASE_URL . '/admin/future-tour-detail.php?id=' . $tourId);
exit;
