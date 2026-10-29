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
if (!canCreateFutureTours()) { flash('error', 'Nincs jogosultságod meghirdetett túra szerkesztéséhez.'); header('Location: ' . BASE_URL . '/admin/future-tours.php'); exit; }
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
    header('Location: ' . BASE_URL . '/admin/future-tour-detail.php?id=' . $id);
    exit;
}
if (!$startDate) {
    flash('error', 'A kezdés dátumának megadása kötelező.');
    header('Location: ' . BASE_URL . '/admin/future-tour-detail.php?id=' . $id);
    exit;
}

// Cover image handling
$COVER_DIR = __DIR__ . '/../assets/uploads/tour-covers/';
if (!is_dir($COVER_DIR)) mkdir($COVER_DIR, 0755, true);

$coverStmt = $pdo->prepare("SELECT cover_img FROM future_tours WHERE id = ? LIMIT 1");
$coverStmt->execute([$id]);
$currentCover = $coverStmt->fetchColumn() ?: null;
$newCoverImg  = $currentCover;

if (!empty($_POST['delete_cover_img'])) {
    if ($currentCover && file_exists($COVER_DIR . $currentCover)) @unlink($COVER_DIR . $currentCover);
    $newCoverImg = null;
}

if (!empty($_FILES['cover_img']['tmp_name']) && $_FILES['cover_img']['error'] === UPLOAD_ERR_OK) {
    $allowedMimes = ['image/jpeg','image/png','image/webp'];
    $size = (int)($_FILES['cover_img']['size'] ?? 0);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['cover_img']['tmp_name']);
    $ext  = match($mime) { 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', default => '' };
    if ($ext && $size <= 5 * 1024 * 1024) {
        $newFile = 'tour_cover_' . $id . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['cover_img']['tmp_name'], $COVER_DIR . $newFile)) {
            if ($currentCover && file_exists($COVER_DIR . $currentCover)) @unlink($COVER_DIR . $currentCover);
            $newCoverImg = $newFile;
        }
    }
}

$pdo->prepare("UPDATE future_tours SET name=?, description=?, short_intro=?, start_date=?, num_days=?, max_attendees=?, participation_fee=?, fee_includes=?, fee_excludes=?, lizzardier_points=?, country=?, region=?, accommodation=?, travel=NULL, equipment=?, experience=?, experience_tags=?, fee_note=?, meeting_place=?, meeting_time=?, status=?, disabled_standard_fields=?, requires_membership=?, cover_img=?, cover_logo=?, cover_logo_pos=? WHERE id=?")
    ->execute([$name, $description ?: null, $shortIntro, $startDate, $numDays, $maxAttendees, $fee, $feeIncludes, $feeExcludes, $lizzardierPoints, $country, $region, $accommodation, $content['equipment'], $experience, $content['experience_tags'], $content['fee_note'], $content['meeting_place'], $content['meeting_time'], $status, $disabledFieldsJson, $requiresMembership, $newCoverImg, $content['cover_logo'], $content['cover_logo_pos'], $id]);

// GPX: meglévő fájlok felirata és napja (üres = egész túra) — a napok mentése előtt,
// mert a választó a régi számozást küldi, amit a saveFutureTourDays() számoz át
foreach ($_POST['gpx_label'] ?? [] as $gfId => $label) {
    $gfId   = (int)$gfId;
    $gpxDay = (int)($_POST['gpx_day'][$gfId] ?? 0);
    if (!$gfId) continue;
    $pdo->prepare("UPDATE future_tour_gpx_files SET label = ?, day_number = ? WHERE id = ? AND future_tour_id = ?")
        ->execute([trim($label) ?: null, $gpxDay > 0 ? min($gpxDay, 255) : null, $gfId, $id]);
}

// Napok (cím, leírás) + a naphoz kötött térképek követik a napjukat
saveFutureTourDays($pdo, $id, $_POST);

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

// GPX fájlok kezelése — feltöltés: egész túrához, illetve a napok soraiban naphoz kötve
foreach ($_FILES['gpx_files']['tmp_name'] ?? [] as $i => $tmp) {
    if (($_FILES['gpx_files']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
    storeFutureTourGpx($pdo, $id, $tmp, $_FILES['gpx_files']['name'][$i] ?? '', (int)($_FILES['gpx_files']['size'][$i] ?? 0), null, (string)$i);
}
saveFutureTourDayGpx($pdo, $id, $_POST, $_FILES);

// Fotógaléria kezelése (csak túrakezelő jogosultsággal)
if (canManageTours()) {
    // 1) Feliratok mentése
    foreach ($_POST['gallery_label'] ?? [] as $gid => $label) {
        $gid = (int)$gid;
        if (!$gid) continue;
        $pdo->prepare("UPDATE future_tour_gallery_images SET label = ? WHERE id = ? AND future_tour_id = ?")
            ->execute([trim($label) ?: null, $gid, $id]);
    }
    // 2) Átrendezés — a gallery_order[] a vizuális sorrendben érkezik
    $galOrder = array_values(array_filter(array_map('intval', $_POST['gallery_order'] ?? [])));
    if ($galOrder) {
        $updOrder = $pdo->prepare("UPDATE future_tour_gallery_images SET sort_order = ? WHERE id = ? AND future_tour_id = ?");
        foreach ($galOrder as $pos => $gid) {
            $updOrder->execute([$pos, $gid, $id]);
        }
    }
    // 3) Törlés (előbb a fájl, majd a sor)
    $deleteGalIds = array_values(array_filter(array_map('intval', $_POST['delete_gallery_ids'] ?? [])));
    if ($deleteGalIds) {
        $gph  = implode(',', array_fill(0, count($deleteGalIds), '?'));
        $grows = $pdo->prepare("SELECT filename FROM future_tour_gallery_images WHERE id IN ($gph) AND future_tour_id = ?");
        $grows->execute(array_merge($deleteGalIds, [$id]));
        foreach ($grows->fetchAll(PDO::FETCH_COLUMN) as $gfn) {
            if ($gfn && file_exists(TOUR_GALLERY_DIR . $gfn)) @unlink(TOUR_GALLERY_DIR . $gfn);
        }
        $pdo->prepare("DELETE FROM future_tour_gallery_images WHERE id IN ($gph) AND future_tour_id = ?")
            ->execute(array_merge($deleteGalIds, [$id]));
    }
    // 4) Új képek feltöltése (a maradék kapacitás erejéig)
    if (!empty($_FILES['gallery_files']['tmp_name'][0])) {
        if (!is_dir(TOUR_GALLERY_DIR)) mkdir(TOUR_GALLERY_DIR, 0755, true);
        $galExisting = (int)$pdo->query("SELECT COUNT(*) FROM future_tour_gallery_images WHERE future_tour_id = $id")->fetchColumn();
        $galSortBase = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) FROM future_tour_gallery_images WHERE future_tour_id = $id")->fetchColumn();
        $galAllowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $insGal = $pdo->prepare("INSERT IGNORE INTO future_tour_gallery_images (future_tour_id, filename, sort_order) VALUES (?, ?, ?)");
        foreach ($_FILES['gallery_files']['tmp_name'] as $i => $tmp) {
            if ($galExisting >= GALLERY_MAX_IMAGES) break;
            if (($_FILES['gallery_files']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
            $gsize = (int)($_FILES['gallery_files']['size'][$i] ?? 0);
            $gmime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
            if (!isset($galAllowed[$gmime]) || $gsize > GALLERY_MAX_BYTES) continue;
            $gFile = 'ftgal_' . $id . '_' . time() . '_' . $i . '.' . $galAllowed[$gmime];
            if (move_uploaded_file($tmp, TOUR_GALLERY_DIR . $gFile)) {
                $insGal->execute([$id, $gFile, $galSortBase + $i + 1]);
                $galExisting++;
            }
        }
    }
}

saveFutureTourOrganizers($pdo, $id, (array)($_POST['organizer_ids'] ?? []));

flash('success', 'Meghirdetett túra sikeresen mentve.');
header('Location: ' . BASE_URL . '/admin/future-tour-detail.php?id=' . $id);
exit;
