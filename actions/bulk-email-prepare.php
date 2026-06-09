<?php
/**
 * Tömeges e-mail küldés — előkészítés (AJAX, JSON).
 * Eltárolja a küldési feladatot (tárgy, szöveg, címzettek) a session-be,
 * és visszaadja a címzett-azonosítók listáját, amelyet a kliens kötegekben küld be.
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/app-settings-schema.php';
requireAdmin();
verifyCsrf();

header('Content-Type: application/json; charset=utf-8');

$ids     = array_values(array_unique(array_filter(array_map('intval', $_POST['member_ids'] ?? []))));
$subject = trim($_POST['subject'] ?? '');
$body    = trim($_POST['body']    ?? '');

if (empty($ids) || $subject === '' || $body === '') {
    echo json_encode(['ok' => false, 'error' => 'Hiányos adatok. Töltse ki a tárgyat és a szöveget is.']);
    exit;
}

$pdo = getDb();
ensureAppSettingsSchema($pdo);
$smtp = getSmtpConfig($pdo);
if ($smtp['host'] === '') {
    echo json_encode(['ok' => false, 'error' => 'Az SMTP szerver nincs beállítva. Kérjük, először konfigurálja a Beállítások oldalon.']);
    exit;
}

// Csak létező felhasználókra szűrünk, megőrizve a névsorrendet
$ph    = rtrim(str_repeat('?,', count($ids)), ',');
$valid = $pdo->prepare("SELECT id FROM users WHERE id IN ($ph) ORDER BY lastname, firstname");
$valid->execute($ids);
$validIds = array_map('intval', $valid->fetchAll(PDO::FETCH_COLUMN));

if (empty($validIds)) {
    echo json_encode(['ok' => false, 'error' => 'Nincs érvényes címzett.']);
    exit;
}

$token = bin2hex(random_bytes(16));
$_SESSION['email_job'] = [
    'token'   => $token,
    'subject' => $subject,
    'body'    => $body,
    'ids'     => $validIds,
];

echo json_encode([
    'ok'    => true,
    'token' => $token,
    'ids'   => $validIds,
    'total' => count($validIds),
]);
