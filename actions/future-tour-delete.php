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

// Kapcsolódó feltöltött fájlok törlése (a DB sorokat a CASCADE viszi, a fájlokat kézzel kell)
$galRows = $pdo->prepare("SELECT filename FROM future_tour_gallery_images WHERE future_tour_id = ?");
$galRows->execute([$id]);
foreach ($galRows->fetchAll(PDO::FETCH_COLUMN) as $gfn) {
    if ($gfn && file_exists(TOUR_GALLERY_DIR . $gfn)) @unlink(TOUR_GALLERY_DIR . $gfn);
}
$gpxRows = $pdo->prepare("SELECT filename FROM future_tour_gpx_files WHERE future_tour_id = ?");
$gpxRows->execute([$id]);
foreach ($gpxRows->fetchAll(PDO::FETCH_COLUMN) as $xfn) {
    if ($xfn && file_exists(GPX_DIR . $xfn)) @unlink(GPX_DIR . $xfn);
}
$coverFn = $pdo->prepare("SELECT cover_img FROM future_tours WHERE id = ?");
$coverFn->execute([$id]);
$coverFn = $coverFn->fetchColumn();
if ($coverFn) {
    $coverPath = __DIR__ . '/../assets/uploads/tour-covers/' . $coverFn;
    if (file_exists($coverPath)) @unlink($coverPath);
}

$pdo->prepare("DELETE FROM future_tours WHERE id = ?")->execute([$id]);

flash('success', 'Meghirdetett túra törölve.');
header('Location: ' . BASE_URL . '/admin/future-tours.php');
exit;
