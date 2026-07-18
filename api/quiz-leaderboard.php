<?php
/**
 * Kvíz — toplisták.
 *
 *   ?view=overall                      → összesített átlag-toplista (mindenki)
 *   ?view=mine                         → a tag által megválaszolt kérdések listája
 *   ?view=item&type=bird&id=5          → egy adott kérdés toplistája
 *                                        (csak ha a tag már válaszolt rá)
 */

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/quiz-schema.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function jsonExit(int $code, array $payload): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isLoggedIn()) {
    jsonExit(401, ['error' => 'Bejelentkezés szükséges']);
}

$view = $_GET['view'] ?? 'overall';

try {
    $pdo    = getDb();
    ensureQuizSchema($pdo);
    $userId = getCurrentUserId();

    if ($view === 'overall') {
        jsonExit(200, ['view' => 'overall', 'rows' => quizOverallLeaderboard($pdo, $userId)]);
    }

    if ($view === 'mine') {
        jsonExit(200, ['view' => 'mine', 'rows' => quizMyItems($pdo, $userId)]);
    }

    if ($view === 'item') {
        $type = (string)($_GET['type'] ?? '');
        $id   = (int)($_GET['id'] ?? 0);
        if (!in_array($type, ['bird', 'mountain'], true) || $id <= 0) {
            jsonExit(422, ['error' => 'Érvénytelen kérdés']);
        }
        // Csak azon kérdés toplistáját láthatja, amit már megválaszolt.
        if (!quizHasAnswered($pdo, $userId, $type, $id)) {
            jsonExit(403, ['error' => 'Ennek a kérdésnek a toplistája csak akkor látható, ha már válaszoltál rá.']);
        }
        $name = $type === 'bird'
            ? (quizGetBird($pdo, $id)['name'] ?? '')
            : (quizGetMountain($pdo, $id)['name'] ?? '');
        jsonExit(200, [
            'view'    => 'item',
            'type'    => $type,
            'id'      => $id,
            'name'    => $name,
            'toplist' => quizItemToplist($pdo, $type, $id, $userId, 20),
        ]);
    }

    jsonExit(422, ['error' => 'Ismeretlen nézet']);
} catch (Throwable $e) {
    jsonExit(500, ['error' => 'Szerverhiba']);
}
