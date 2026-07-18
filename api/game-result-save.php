<?php
/**
 * Hegymászó játék — pályaeredmény mentése (munkamenet-alapú, tagoknak).
 *
 * A tagoldali beágyazott játék hívja minden csúcs-szelfi (pályateljesítés) után.
 * Tagonként és pályánként a legjobb (legrövidebb) időt tartja, és számolja a teljesítéseket.
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/game-schema.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const GAME_TOTAL_LEVELS = 19;

function jsonExit(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonExit(405, ['error' => 'Method not allowed']);
}

if (!isLoggedIn()) {
    jsonExit(401, ['error' => 'Bejelentkezés szükséges']);
}

// CSRF — a token a POST törzsében érkezik (nem dobunk HTML-t, JSON-t adunk vissza)
$token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    jsonExit(403, ['error' => 'Érvénytelen kérés-token']);
}

$level = (int)($_POST['level'] ?? 0);
$time  = (float)($_POST['time'] ?? 0);

if ($level < 1 || $level > GAME_TOTAL_LEVELS) {
    jsonExit(422, ['error' => 'Érvénytelen pálya']);
}
if ($time <= 0 || $time > 36000) { // reális felső korlát: 10 óra
    jsonExit(422, ['error' => 'Érvénytelen idő']);
}
$time = round($time, 1);

try {
    $pdo = getDb();
    ensureGameSchema($pdo);

    $userId = getCurrentUserId();

    $stmt = $pdo->prepare("
        INSERT INTO game_results (user_id, level, best_time, completions)
        VALUES (:uid, :lvl, :t, 1)
        ON DUPLICATE KEY UPDATE
            best_time   = LEAST(best_time, VALUES(best_time)),
            completions = completions + 1
    ");
    $stmt->execute([':uid' => $userId, ':lvl' => $level, ':t' => $time]);

    $save = loadGameSave($pdo, $userId, GAME_TOTAL_LEVELS);

    echo json_encode([
        'ok'       => true,
        'level'    => $level,
        'best'     => $save['best'][(string)$level] ?? $time,
        'unlocked' => $save['unlocked'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    jsonExit(500, ['error' => 'Adatbázis hiba']);
}
