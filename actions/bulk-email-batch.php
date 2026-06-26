<?php
/**
 * Tömeges e-mail küldés — egy köteg elküldése (AJAX, JSON).
 * A kliens kötegekben hívja; minden levél után rövid késleltetés, és minden
 * levél bekerül az email_log-ba (a szerver válaszával / hibaüzenetével együtt).
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/app-settings-schema.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/email-log-schema.php';
require_once __DIR__ . '/../includes/bulk-email-template.php';
requireAdmin();
verifyCsrf();

header('Content-Type: application/json; charset=utf-8');

$token     = (string)($_POST['token'] ?? '');
$batchIds  = array_values(array_filter(array_map('intval', $_POST['batch_ids'] ?? [])));

$job = $_SESSION['email_job'] ?? null;
if (!$job || !hash_equals((string)($job['token'] ?? ''), $token)) {
    echo json_encode(['ok' => false, 'error' => 'Érvénytelen vagy lejárt küldési munkamenet. Töltse újra az oldalt.']);
    exit;
}

// Csak a feladathoz tartozó címzettek küldhetők (engedélyezett halmaz)
$allowed  = array_map('intval', $job['ids'] ?? []);
$batchIds = array_values(array_intersect($batchIds, $allowed));

$subject = (string)$job['subject'];
$body    = (string)$job['body'];

$pdo = getDb();
ensureAppSettingsSchema($pdo);
ensureEmailLogSchema($pdo);
$smtp = getSmtpConfig($pdo);

if ($smtp['host'] === '') {
    echo json_encode(['ok' => false, 'error' => 'Az SMTP szerver nincs beállítva.']);
    exit;
}

if (empty($batchIds)) {
    echo json_encode(['ok' => true, 'results' => []]);
    exit;
}

$ph    = rtrim(str_repeat('?,', count($batchIds)), ',');
$stmt  = $pdo->prepare("SELECT id, firstname, lastname, email, username, level, points, city, member_since FROM users WHERE id IN ($ph)");
$stmt->execute($batchIds);
$members = $stmt->fetchAll();

// A session-t lezárjuk a lassú küldés előtt, hogy ne blokkolja a többi kérést
session_write_close();

$mailer  = new SmtpMailer($smtp);
$results = [];
$first   = true;
$stopped = false;
$stopError = '';

foreach ($members as $m) {
    if (!$first) usleep(400000); // 0,4 mp késleltetés a levelek között
    $first = false;

    $name = trim($m['lastname'] . ' ' . $m['firstname']);
    $subj = applyBulkEmailMerge($subject, $m);
    $html = buildBulkEmailHtml($subj, applyBulkEmailMerge($body, $m));

    if (trim((string)$m['email']) === '') {
        // Hiányzó e-mail cím nem SMTP-hiba — naplózzuk, de folytatjuk
        logEmailEntry($pdo, (int)$m['id'], '', $name, $subj, $html, 'bulk', 'failed', 'Hiányzó e-mail cím');
        $results[] = ['id' => (int)$m['id'], 'name' => $name, 'ok' => false, 'error' => 'Hiányzó e-mail cím'];
        continue;
    }

    try {
        $response = $mailer->send($m['email'], $name, $subj, $html);
        logEmailEntry($pdo, (int)$m['id'], $m['email'], $name, $subj, $html, 'bulk', 'sent', '', $response);
        $results[] = ['id' => (int)$m['id'], 'name' => $name, 'ok' => true];
    } catch (Throwable $e) {
        // Első SMTP-hiba → megszakítjuk a kiküldést, nincs újrapróbálkozás
        $err = $e->getMessage();
        logEmailEntry($pdo, (int)$m['id'], $m['email'], $name, $subj, $html, 'bulk', 'failed', $err, $err);
        error_log('Bulk email error to ' . $m['email'] . ': ' . $err);
        $results[] = ['id' => (int)$m['id'], 'name' => $name, 'ok' => false, 'error' => $err];
        $stopped   = true;
        $stopError = $err;
        break;
    }
}

echo json_encode(['ok' => true, 'results' => $results, 'stopped' => $stopped, 'stopError' => $stopError]);
