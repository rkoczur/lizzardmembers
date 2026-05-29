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

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/admin/future-tours.php');
    exit;
}

$tour = $pdo->prepare("SELECT id FROM future_tours WHERE id = ? LIMIT 1");
$tour->execute([$id]);
if (!$tour->fetch()) {
    flash('error', 'A túra nem található.');
    header('Location: ' . BASE_URL . '/admin/future-tours.php');
    exit;
}

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

if (!$name) {
    flash('error', 'A túra nevének megadása kötelező.');
    header('Location: ' . BASE_URL . '/admin/future-tour-detail.php?id=' . $id);
    exit;
}
if (!$startDate) {
    flash('error', 'A kezdés dátumának megadása kötelező.');
    header('Location: ' . BASE_URL . '/admin/future-tour-detail.php?id=' . $id);
    exit;
}

$pdo->prepare("UPDATE future_tours SET name=?, description=?, start_date=?, num_days=?, max_attendees=?, participation_fee=?, country=?, region=?, accommodation=?, travel=?, equipment=?, experience=?, status=? WHERE id=?")
    ->execute([$name, $description ?: null, $startDate, $numDays, $maxAttendees, $fee, $country, $region, $accommodation, $travel, $equipment, $experience, $status, $id]);

// Sync days: delete existing, re-insert from POST
$pdo->prepare("DELETE FROM future_tour_days WHERE future_tour_id = ?")->execute([$id]);

$dayNumbers    = $_POST['day_number']      ?? [];
$dayTypes      = $_POST['day_tour_type']   ?? [];
$dayKms        = $_POST['day_km']          ?? [];
$dayElevations = $_POST['day_elevation']   ?? [];
$dayDescs      = $_POST['day_description'] ?? [];

$dayStmt = $pdo->prepare("INSERT INTO future_tour_days (future_tour_id, day_number, tour_type, km, elevation, description) VALUES (?, ?, ?, ?, ?, ?)");
foreach ($dayNumbers as $i => $dayNum) {
    $dayStmt->execute([
        $id,
        max(1, (int)$dayNum),
        trim($dayTypes[$i] ?? '') ?: null,
        ($dayKms[$i] ?? '') !== '' ? (float)$dayKms[$i] : null,
        ($dayElevations[$i] ?? '') !== '' ? (int)$dayElevations[$i] : null,
        trim($dayDescs[$i] ?? '') ?: null,
    ]);
}

// Sync custom fields: update existing, insert new, delete removed
$fieldIds     = $_POST['field_id']      ?? [];
$fieldNames   = $_POST['field_name']   ?? [];
$fieldTypes   = $_POST['field_type']   ?? [];
$fieldOptions = $_POST['field_options'] ?? [];

$keepIds    = [];
$updateStmt = $pdo->prepare("UPDATE future_tour_custom_fields SET field_name=?, field_type=?, field_options=?, sort_order=? WHERE id=? AND future_tour_id=?");
$insertStmt = $pdo->prepare("INSERT INTO future_tour_custom_fields (future_tour_id, field_name, field_type, field_options, sort_order) VALUES (?, ?, ?, ?, ?)");

foreach ($fieldIds as $i => $fid) {
    $fname = trim($fieldNames[$i] ?? '');
    if (!$fname) continue;
    $ftype = in_array($fieldTypes[$i] ?? '', ['text','number','checkbox','select','textarea']) ? $fieldTypes[$i] : 'text';
    $fopts = $ftype === 'select' ? (trim($fieldOptions[$i] ?? '') ?: null) : null;
    $fid   = (int)$fid;
    if ($fid > 0) {
        $updateStmt->execute([$fname, $ftype, $fopts, $i, $fid, $id]);
        $keepIds[] = $fid;
    } else {
        $insertStmt->execute([$id, $fname, $ftype, $fopts, $i]);
        $keepIds[] = (int)$pdo->lastInsertId();
    }
}

// Remove fields no longer in POST
if ($keepIds) {
    $placeholders = implode(',', array_fill(0, count($keepIds), '?'));
    $pdo->prepare("DELETE FROM future_tour_custom_fields WHERE future_tour_id = ? AND id NOT IN ($placeholders)")
        ->execute(array_merge([$id], $keepIds));
} else {
    $pdo->prepare("DELETE FROM future_tour_custom_fields WHERE future_tour_id = ?")->execute([$id]);
}

flash('success', 'Meghirdetett túra sikeresen mentve.');
header('Location: ' . BASE_URL . '/admin/future-tour-detail.php?id=' . $id);
exit;
