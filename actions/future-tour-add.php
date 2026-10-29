<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
require_once __DIR__ . '/../includes/future-tour-organizers.php';
require_once __DIR__ . '/../includes/future-tour-content.php';
requireAdminOrVezeto();
if (!canCreateFutureTours()) { flash('error', 'Nincs jogosultságod meghirdetett túra létrehozásához.'); header('Location: ' . BASE_URL . '/admin/future-tours.php'); exit; }
verifyCsrf();

$pdo = getDb();
ensureFutureToursSchema($pdo);

$name          = trim($_POST['name']          ?? '');
$description   = trim($_POST['description']   ?? '');
$shortIntro    = trim($_POST['short_intro']   ?? '') ?: null;
$startDate     = trim($_POST['start_date']    ?? '');
$numDays       = max(1, count(array_filter($_POST['day_number'] ?? [], fn($v) => $v !== '')));
$maxAttendees  = max(1, (int)($_POST['max_attendees'] ?? 1));
$country       = trim($_POST['country']       ?? '') ?: null;
$region        = trim($_POST['region']        ?? '') ?: null;
$fee              = ($_POST['participation_fee'] ?? '') !== '' ? max(0, (int)$_POST['participation_fee']) : null;
$lizzardierPoints = ($_POST['lizzardier_points'] ?? '') !== '' ? max(0, (int)$_POST['lizzardier_points']) : null;
$status        = in_array($_POST['status'] ?? '', ['draft','open','closed','cancelled']) ? $_POST['status'] : 'open';
$accommodation = trim($_POST['accommodation'] ?? '') ?: null;
$experience    = trim($_POST['experience']    ?? '') ?: null;
$feeIncludes   = trim($_POST['fee_includes']  ?? '') ?: null;
$feeExcludes   = trim($_POST['fee_excludes']  ?? '') ?: null;
$content       = tourContentFromPost($_POST);

$requiresMembership = !empty($_POST['requires_membership']) ? 1 : 0;

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

$stmt = $pdo->prepare("INSERT INTO future_tours (name, description, short_intro, start_date, num_days, max_attendees, participation_fee, fee_includes, fee_excludes, lizzardier_points, country, region, accommodation, equipment, experience, experience_tags, fee_note, meeting_place, meeting_time, cover_logo, cover_logo_pos, status, disabled_standard_fields, requires_membership, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->execute([$name, $description ?: null, $shortIntro, $startDate, $numDays, $maxAttendees, $fee, $feeIncludes, $feeExcludes, $lizzardierPoints, $country, $region, $accommodation, $content['equipment'], $experience, $content['experience_tags'], $content['fee_note'], $content['meeting_place'], $content['meeting_time'], $content['cover_logo'], $content['cover_logo_pos'], $status, $disabledFieldsJson, $requiresMembership, getCurrentUserId()]);
$tourId = (int)$pdo->lastInsertId();
saveFutureTourOrganizers($pdo, $tourId, (array)($_POST['organizer_ids'] ?? []));

// Napok + a napok soraiban feltöltött térképek
saveFutureTourDays($pdo, $tourId, $_POST);
saveFutureTourDayGpx($pdo, $tourId, $_POST, $_FILES);

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

// Cover image upload (after INSERT so we have $tourId)
$COVER_DIR = __DIR__ . '/../assets/uploads/tour-covers/';
if (!is_dir($COVER_DIR)) mkdir($COVER_DIR, 0755, true);

if (!empty($_FILES['cover_img']['tmp_name']) && $_FILES['cover_img']['error'] === UPLOAD_ERR_OK) {
    $allowedMimes = ['image/jpeg','image/png','image/webp'];
    $size = (int)($_FILES['cover_img']['size'] ?? 0);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['cover_img']['tmp_name']);
    $ext  = match($mime) { 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', default => '' };
    if ($ext && $size <= 5 * 1024 * 1024) {
        $newFile = 'tour_cover_' . $tourId . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['cover_img']['tmp_name'], $COVER_DIR . $newFile)) {
            $pdo->prepare("UPDATE future_tours SET cover_img = ? WHERE id = ?")->execute([$newFile, $tourId]);
        }
    }
}

// Gallery images upload
if (!empty($_FILES['gallery_files']['tmp_name'][0])) {
    if (!is_dir(TOUR_GALLERY_DIR)) mkdir(TOUR_GALLERY_DIR, 0755, true);
    $galAllowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $insGal = $pdo->prepare("INSERT IGNORE INTO future_tour_gallery_images (future_tour_id, filename, sort_order) VALUES (?, ?, ?)");
    $galCount = 0;
    foreach ($_FILES['gallery_files']['tmp_name'] as $i => $tmp) {
        if ($galCount >= GALLERY_MAX_IMAGES) break;
        if (($_FILES['gallery_files']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        $gsize = (int)($_FILES['gallery_files']['size'][$i] ?? 0);
        $gmime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!isset($galAllowed[$gmime]) || $gsize > GALLERY_MAX_BYTES) continue;
        $gFile = 'ftgal_' . $tourId . '_' . time() . '_' . $i . '.' . $galAllowed[$gmime];
        if (move_uploaded_file($tmp, TOUR_GALLERY_DIR . $gFile)) {
            $insGal->execute([$tourId, $gFile, $i]);
            $galCount++;
        }
    }
}

flash('success', 'Meghirdetett túra sikeresen létrehozva.');
header('Location: ' . BASE_URL . '/admin/future-tour-detail.php?id=' . $tourId);
exit;
