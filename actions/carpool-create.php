<?php
/** Telekocsi-szervező létrehozása egy meghirdetett túrához (egyedi link generálása). */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
require_once __DIR__ . '/../includes/carpool.php';
requireAdminOrVezeto();
verifyCsrf();

$pdo = getDb();
ensureFutureToursSchema($pdo);
ensureCarpoolSchema($pdo);

$tourId = (int)($_POST['tour_id'] ?? 0);
$back   = BASE_URL . '/admin/future-tour-applicants.php?id=' . $tourId;

$stmt = $pdo->prepare("SELECT id FROM future_tours WHERE id = ? LIMIT 1");
$stmt->execute([$tourId]);
if (!$stmt->fetchColumn()) {
    flash('error', 'Nem található a túra.');
    header('Location: ' . BASE_URL . '/admin/future-tours.php');
    exit;
}

createCarpool($pdo, $tourId, getCurrentUserId());
flash('success', 'A telekocsi-szervező elkészült — a link megosztható a résztvevőkkel.');
header('Location: ' . $back . '#telekocsi');
exit;
