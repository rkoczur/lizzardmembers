<?php
/**
 * Meghirdetett túra értesítő — minta (előnézet) a kiküldés előtt.
 * A pontosan ugyanazzal a sablonnal rendereli az e-mailt, mint a kiküldés,
 * minta keresztnévvel és minta leiratkozó linkkel. Új lapon megnyitva nézhető.
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
require_once __DIR__ . '/../includes/future-tour-announcement-email.php';
requireAdminOrVezeto();

if (!canManageTours()) {
    http_response_code(403);
    echo 'Nincs jogosultságod a túraértesítő megtekintéséhez.';
    exit;
}

$pdo = getDb();
ensureFutureToursSchema($pdo);

$tourId = (int)($_GET['tour_id'] ?? 0);
$stmt = $pdo->prepare("SELECT ft.*, c.name_hu AS country_name FROM future_tours ft LEFT JOIN countries c ON c.code = ft.country WHERE ft.id = ? LIMIT 1");
$stmt->execute([$tourId]);
$tour = $stmt->fetch();

if (!$tour) {
    http_response_code(404);
    echo 'A túra nem található.';
    exit;
}

$proto       = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$absBaseUrl  = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL;
$applyUrl    = $absBaseUrl . '/user/future-tour-apply-public.php?id=' . $tourId;
$formattedDate = $tour['start_date'] ? formatDate($tour['start_date']) : '—';
$fee         = (int)round((float)($tour['participation_fee'] ?? 0));
$feeText     = $fee > 0 ? number_format($fee, 0, ',', ' ') . ' Ft' : 'Ingyenes';
$countryName = $tour['country_name'] ?: ($tour['country'] ?? '');
$sampleUnsub = $absBaseUrl . '/unsubscribe.php?uid=0&t=minta'; // minta — előnézetben nem aktív

$html = buildFutureTourAnnouncementEmailHtml(
    'Túratárs',                       // minta keresztnév
    $tour['name'] ?? '',
    $tour['short_intro'] ?? '',
    $countryName,
    $tour['region'] ?? '',
    $formattedDate,
    (int)($tour['num_days'] ?? 1),
    $feeText,
    $sampleUnsub,
    APP_NAME
);

header('Content-Type: text/html; charset=utf-8');
echo $html;
