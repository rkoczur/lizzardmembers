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
$fee              = ($_POST['participation_fee'] ?? '') !== '' ? max(0, (int)$_POST['participation_fee']) : null;
$lizzardierPoints = ($_POST['lizzardier_points'] ?? '') !== '' ? max(0, (int)$_POST['lizzardier_points']) : null;
$status        = in_array($_POST['status'] ?? '', ['open','closed','cancelled']) ? $_POST['status'] : 'open';
$accommodation = trim($_POST['accommodation'] ?? '') ?: null;
$travel        = trim($_POST['travel']        ?? '') ?: null;
$equipment     = trim($_POST['equipment']     ?? '') ?: null;
$experience    = trim($_POST['experience']    ?? '') ?: null;

$requiresMembership = !empty($_POST['requires_membership']) ? 1 : 0;

$allowedStdFields   = ['departure_city', 'car_available', 'sharing_room', 'notes'];
$visibleFields      = array_values(array_intersect($_POST['visible_fields'] ?? [], $allowedStdFields));
$disabledFields     = array_values(array_diff($allowedStdFields, $visibleFields));
$disabledFieldsJson = !empty($disabledFields) ? json_encode($disabledFields) : null;

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

$pdo->prepare("UPDATE future_tours SET name=?, description=?, start_date=?, num_days=?, max_attendees=?, participation_fee=?, lizzardier_points=?, country=?, region=?, accommodation=?, travel=?, equipment=?, experience=?, status=?, disabled_standard_fields=?, requires_membership=? WHERE id=?")
    ->execute([$name, $description ?: null, $startDate, $numDays, $maxAttendees, $fee, $lizzardierPoints, $country, $region, $accommodation, $travel, $equipment, $experience, $status, $disabledFieldsJson, $requiresMembership, $id]);

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

// GPX: meglévő fájlok feliratának mentése
foreach ($_POST['gpx_label'] ?? [] as $gfId => $label) {
    $gfId = (int)$gfId;
    if (!$gfId) continue;
    $pdo->prepare("UPDATE future_tour_gpx_files SET label = ? WHERE id = ? AND future_tour_id = ?")
        ->execute([trim($label) ?: null, $gfId, $id]);
}

// GPX fájlok kezelése — törlés
$deleteGpxIds = array_values(array_filter(array_map('intval', $_POST['delete_gpx_ids'] ?? [])));
if ($deleteGpxIds) {
    $ph   = implode(',', array_fill(0, count($deleteGpxIds), '?'));
    $rows = $pdo->prepare("SELECT filename FROM future_tour_gpx_files WHERE id IN ($ph) AND future_tour_id = ?");
    $rows->execute(array_merge($deleteGpxIds, [$id]));
    foreach ($rows->fetchAll(PDO::FETCH_COLUMN) as $fname) {
        if ($fname && file_exists(GPX_DIR . $fname)) @unlink(GPX_DIR . $fname);
    }
    $pdo->prepare("DELETE FROM future_tour_gpx_files WHERE id IN ($ph) AND future_tour_id = ?")
        ->execute(array_merge($deleteGpxIds, [$id]));
}

// GPX fájlok kezelése — feltöltés
if (!empty($_FILES['gpx_files']['tmp_name'])) {
    if (!is_dir(GPX_DIR)) mkdir(GPX_DIR, 0755, true);
    $allowedMimes = ['text/xml','application/xml','application/gpx+xml','text/plain','application/octet-stream'];
    $insGpx = $pdo->prepare("INSERT IGNORE INTO future_tour_gpx_files (future_tour_id, filename, sort_order) VALUES (?, ?, ?)");
    $sortBase = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM future_tour_gpx_files WHERE future_tour_id = $id")->fetchColumn();
    foreach ($_FILES['gpx_files']['tmp_name'] as $i => $tmp) {
        if (($_FILES['gpx_files']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        $ext  = strtolower(pathinfo($_FILES['gpx_files']['name'][$i] ?? '', PATHINFO_EXTENSION));
        $size = (int)($_FILES['gpx_files']['size'][$i] ?? 0);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if ($ext !== 'gpx' || $size > 5 * 1024 * 1024 || !in_array($mime, $allowedMimes, true)) continue;
        $newFile = 'gpx_ft_' . $id . '_' . time() . '_' . $i . '.gpx';
        if (move_uploaded_file($tmp, GPX_DIR . $newFile)) {
            $insGpx->execute([$id, $newFile, $sortBase + $i + 1]);
        }
    }
}

flash('success', 'Meghirdetett túra sikeresen mentve.');
header('Location: ' . BASE_URL . '/admin/future-tour-detail.php?id=' . $id);
exit;
