<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
requireAdmin();

$pdo = getDb();
ensureFutureToursSchema($pdo);

$tourId = (int)($_GET['id'] ?? 0);
if (!$tourId) {
    header('Location: ' . BASE_URL . '/admin/future-tours.php');
    exit;
}

$tourStmt = $pdo->prepare("SELECT * FROM future_tours WHERE id = ? LIMIT 1");
$tourStmt->execute([$tourId]);
$tour = $tourStmt->fetch();
if (!$tour) {
    header('Location: ' . BASE_URL . '/admin/future-tours.php');
    exit;
}

// Get custom fields for this tour
$customFieldsStmt = $pdo->prepare("SELECT * FROM future_tour_custom_fields WHERE future_tour_id = ? ORDER BY sort_order ASC, id ASC");
$customFieldsStmt->execute([$tourId]);
$customFields = $customFieldsStmt->fetchAll();

// Get applications with user data
$appsStmt = $pdo->prepare("
    SELECT fta.*, u.firstname, u.lastname, u.email, u.phone
    FROM future_tour_applications fta
    JOIN users u ON u.id = fta.user_id
    WHERE fta.future_tour_id = ? AND fta.status != 'cancelled'
    ORDER BY fta.status ASC, fta.applied_at ASC
");
$appsStmt->execute([$tourId]);
$applications = $appsStmt->fetchAll();

// Get all answers indexed by [application_id][field_id]
$answersStmt = $pdo->prepare("
    SELECT ftaa.application_id, ftaa.field_id, ftaa.answer
    FROM future_tour_application_answers ftaa
    JOIN future_tour_applications fta ON fta.id = ftaa.application_id
    WHERE fta.future_tour_id = ?
");
$answersStmt->execute([$tourId]);
$answersRaw = $answersStmt->fetchAll();
$answers = [];
foreach ($answersRaw as $row) {
    $answers[$row['application_id']][$row['field_id']] = $row['answer'];
}

$sharingRoomLabels = [
    'same_gender' => 'Igen, de csak azonos neművel',
    'yes'         => 'Igen',
    'no'          => 'Nem',
];
$statusLabels = [
    'confirmed' => 'Megerősített',
    'waitlist'  => 'Várólistán',
    'cancelled' => 'Lemondva',
];

$safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $tour['name']);
$filename = 'jelentkezok_' . $safeName . '_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel

// Header row
$header = ['Vezetéknév', 'Keresztnév', 'E-mail', 'Telefon', 'Státusz', 'Fizetés', 'Jelentkezés dátuma', 'Autóval jön', 'Szabad helyek', 'Szobamegosztás', 'Megjegyzések'];
foreach ($customFields as $cf) {
    $header[] = $cf['field_name'];
}
fputcsv($out, $header, ';');

foreach ($applications as $app) {
    $row = [
        $app['lastname'],
        $app['firstname'],
        $app['email'],
        $app['phone'] ?? '',
        $statusLabels[$app['status']] ?? $app['status'],
        $app['paid_at'] ? date('Y.m.d', strtotime($app['paid_at'])) : 'Nem fizette be',
        date('Y.m.d H:i', strtotime($app['applied_at'])),
        $app['car_available'] ? 'Igen' : 'Nem',
        $app['car_available'] ? (int)$app['passengers'] : '',
        $sharingRoomLabels[$app['sharing_room']] ?? $app['sharing_room'],
        $app['notes'] ?? '',
    ];
    foreach ($customFields as $cf) {
        $row[] = $answers[$app['id']][$cf['id']] ?? '';
    }
    fputcsv($out, $row, ';');
}

fclose($out);
exit;
