<?php
/**
 * Kvíz — következő (még nem válaszolt) véletlen kérdés lekérése.
 *
 * A válasz nem tartalmazza a helyes megoldást. A kérdés indítási ideje a
 * munkamenetbe kerül, így a válaszidőt a szerver hitelesen méri.
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

try {
    $pdo    = getDb();
    ensureQuizSchema($pdo);
    $userId = getCurrentUserId();

    $progress = quizProgress($pdo, $userId);
    $next     = quizPickNextQuestion($pdo, $userId);

    if ($next === null) {
        unset($_SESSION['quiz_q']);
        jsonExit(200, ['done' => true, 'progress' => $progress]);
    }

    $type = $next['type'];
    $id   = $next['id'];

    if ($type === 'bird') {
        $bird = quizGetBird($pdo, $id);
        if (!$bird) {
            jsonExit(500, ['error' => 'A kérdés nem található']);
        }
        $question = ['type' => 'bird', 'id' => $id, 'name' => $bird['name'], 'latin_name' => $bird['latin_name']];
    } else {
        $mtn = quizGetMountain($pdo, $id);
        if (!$mtn) {
            jsonExit(500, ['error' => 'A kérdés nem található']);
        }
        $question = ['type' => 'mountain', 'id' => $id, 'name' => $mtn['name']];
    }

    // A válaszidő szerveroldali méréséhez elmentjük az indítást.
    $_SESSION['quiz_q'] = ['type' => $type, 'id' => $id, 'start' => microtime(true)];

    jsonExit(200, ['done' => false, 'question' => $question, 'progress' => $progress]);
} catch (Throwable $e) {
    jsonExit(500, ['error' => 'Szerverhiba']);
}
