<?php
/**
 * Meghirdetett túra értesítő — egy köteg kiküldése (AJAX, JSON).
 * Minden levél után rövid késleltetés, és minden levél bekerül az email_log-ba.
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/app-settings-schema.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/email-log-schema.php';
require_once __DIR__ . '/../includes/future-tour-announcement-email.php';
requireAdminOrVezeto();
verifyCsrf();

header('Content-Type: application/json; charset=utf-8');

if (!canManageTours()) {
    echo json_encode(['ok' => false, 'error' => 'Nincs jogosultságod túraértesítőt küldeni.']);
    exit;
}

$token    = (string)($_POST['token'] ?? '');
$batchIds = array_values(array_filter(array_map('intval', $_POST['batch_ids'] ?? [])));

$job = $_SESSION['ft_announce_job'] ?? null;
if (!$job || !hash_equals((string)($job['token'] ?? ''), $token)) {
    echo json_encode(['ok' => false, 'error' => 'Érvénytelen vagy lejárt küldési munkamenet. Töltse újra az oldalt.']);
    exit;
}

$allowed  = array_map('intval', $job['ids'] ?? []);
$batchIds = array_values(array_intersect($batchIds, $allowed));
$tourId   = (int)$job['tour_id'];

$pdo = getDb();
ensureFutureToursSchema($pdo);
ensureAppSettingsSchema($pdo);
ensureEmailLogSchema($pdo);
$smtp = getSmtpConfig($pdo);
if ($smtp['host'] === '') {
    echo json_encode(['ok' => false, 'error' => 'Az SMTP szerver nincs beállítva.']);
    exit;
}

$tourStmt = $pdo->prepare("SELECT ft.*, c.name_hu AS country_name FROM future_tours ft LEFT JOIN countries c ON c.code = ft.country WHERE ft.id = ? LIMIT 1");
$tourStmt->execute([$tourId]);
$tour = $tourStmt->fetch();
if (!$tour) {
    echo json_encode(['ok' => false, 'error' => 'A túra nem található.']);
    exit;
}

if (empty($batchIds)) {
    echo json_encode(['ok' => true, 'results' => []]);
    exit;
}

$ph    = rtrim(str_repeat('?,', count($batchIds)), ',');
$stmt  = $pdo->prepare("SELECT id, firstname, lastname, email FROM users WHERE id IN ($ph)");
$stmt->execute($batchIds);
$members = $stmt->fetchAll();

// Túra-adatok előkészítése (egyszer)
$proto       = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$absBaseUrl  = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL;
$applyUrl    = $absBaseUrl . '/user/future-tour-apply-public.php?id=' . $tourId;
$formattedDate = $tour['start_date'] ? formatDate($tour['start_date']) : '—';
$fee         = (int)round((float)($tour['participation_fee'] ?? 0));
$feeText     = $fee > 0 ? number_format($fee, 0, ',', ' ') . ' Ft' : 'Ingyenes';
$countryName = $tour['country_name'] ?: ($tour['country'] ?? '');
$subject     = 'Új meghirdetett túra – ' . $tour['name'];

session_write_close();

$mailer  = new SmtpMailer($smtp);
$results = [];
$first   = true;

foreach ($members as $m) {
    if (!$first) usleep(400000); // 0,4 mp késleltetés
    $first = false;

    $name = trim($m['lastname'] . ' ' . $m['firstname']);
    $html = buildFutureTourAnnouncementEmailHtml(
        $m['firstname'] ?? '',
        $tour['name'] ?? '',
        $tour['short_intro'] ?? '',
        $countryName,
        $tour['region'] ?? '',
        $formattedDate,
        (int)($tour['num_days'] ?? 1),
        $feeText,
        $applyUrl,
        APP_NAME
    );

    if (trim((string)$m['email']) === '') {
        logEmailEntry($pdo, (int)$m['id'], '', $name, $subject, $html, 'tour_announcement', 'failed', 'Hiányzó e-mail cím');
        $results[] = ['id' => (int)$m['id'], 'name' => $name, 'ok' => false, 'error' => 'Hiányzó e-mail cím'];
        continue;
    }

    try {
        $response = $mailer->send($m['email'], $name, $subject, $html);
        logEmailEntry($pdo, (int)$m['id'], $m['email'], $name, $subject, $html, 'tour_announcement', 'sent', '', $response);
        $results[] = ['id' => (int)$m['id'], 'name' => $name, 'ok' => true];
    } catch (Throwable $e) {
        $err = $e->getMessage();
        logEmailEntry($pdo, (int)$m['id'], $m['email'], $name, $subject, $html, 'tour_announcement', 'failed', $err, $err);
        error_log('Tour announcement error to ' . $m['email'] . ': ' . $err);
        $results[] = ['id' => (int)$m['id'], 'name' => $name, 'ok' => false, 'error' => $err];
    }
}

echo json_encode(['ok' => true, 'results' => $results]);
