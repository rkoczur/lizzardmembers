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

    $game = quizActiveGame($pdo, $userId);
    if (!$game) {
        unset($_SESSION['quiz_q']);
        jsonExit(200, ['noActiveGame' => true, 'lifetimeProgress' => quizProgress($pdo, $userId)]);
    }
    $gameId = (int)$game['id'];

    // Csalás elleni védelem: ha már van kiadott, meg nem válaszolt kérdés ehhez
    // a körhöz (pl. a tag frissítette az oldalt), UGYANAZT adjuk vissza — nem
    // sorsolunk újat, és nem indítjuk újra az időmérést.
    $pending = quizPendingSessionQuestion($gameId);

    if ($pending !== null) {
        $type           = $pending['type'];
        $id             = $pending['id'];
        $questionNumber = (int)$pending['question_number'];
        $countryOptions = $pending['country_options'] ?? null;
    } else {
        $next = quizPickNextQuestion($pdo, $userId);

        if ($next === null) {
            // A készlet kimerült, mielőtt a kör elérte volna a célhosszt.
            $final = quizFinishGame($pdo, $gameId);
            unset($_SESSION['quiz_q']);
            jsonExit(200, [
                'gameOver' => [
                    'totalScore'     => $final['totalScore'],
                    'questionCount'  => $final['questionCount'],
                    'poolExhausted'  => true,
                ],
                'lifetimeProgress' => quizProgress($pdo, $userId),
            ]);
        }

        $type = $next['type'];
        $id   = $next['id'];

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM quiz_scores WHERE game_id = ?');
        $stmt->execute([$gameId]);
        $questionNumber = (int)$stmt->fetchColumn() + 1;
        $countryOptions = null; // a lenti ágban töltjük fel, ha hegy a kérdés
    }

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
        if ($countryOptions === null) {
            $countryOptions = quizMountainCountryChoices($pdo, $mtn['country']);
        }
        $question = [
            'type'           => 'mountain',
            'id'             => $id,
            'name'           => $mtn['name'],
            'countryOptions' => $countryOptions,
        ];
    }

    if ($pending === null) {
        // A válaszidő szerveroldali méréséhez elmentjük az indítást (csak új kérdésnél).
        $startedAt = microtime(true);
        $_SESSION['quiz_q'] = [
            'type'            => $type,
            'id'              => $id,
            'start'           => $startedAt,
            'game_id'         => $gameId,
            'question_number' => $questionNumber,
            'country_options' => $countryOptions,
        ];
    } else {
        $startedAt = (float)$pending['start'];
    }
    // A tényleges eltelt idő — így egy oldalfrissítés után a kliens időzítője a
    // valós (szerver által mért, pontozásnál is használt) idővel indul újra,
    // nem nullától.
    $elapsedSeconds = max(0.0, microtime(true) - $startedAt);

    $roundSize = quizGameRoundSize($pdo, $userId, $gameId);

    jsonExit(200, [
        'done'             => false,
        'game'             => [
            'id'             => $gameId,
            'questionNumber' => $questionNumber,
            'roundSize'      => $roundSize,
            'roundScore'     => quizGameRunningScore($pdo, $gameId),
        ],
        'question'         => $question,
        'elapsedSeconds'   => round($elapsedSeconds, 1),
        'lifetimeProgress' => quizProgress($pdo, $userId),
    ]);
} catch (Throwable $e) {
    jsonExit(500, ['error' => 'Szerverhiba']);
}
