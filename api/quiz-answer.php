<?php
/**
 * Kvíz — válasz beküldése, pontszámítás és mentés.
 *
 * A kérés JSON törzsben érkezik:
 *   { csrf_token, type:'bird'|'mountain', id,
 *     wingspan, colors:[], continents:[],   // madárnál
 *     elevation, country }                   // hegynél
 *
 * Egy kérdésre egy tag csak egyszer válaszolhat. A válaszidőt a szerver a
 * kérdés kiadásakor elmentett indítási időből számolja.
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonExit(405, ['error' => 'Method not allowed']);
}
if (!isLoggedIn()) {
    jsonExit(401, ['error' => 'Bejelentkezés szükséges']);
}

$body = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($body)) {
    $body = $_POST;
}

// CSRF
if (!hash_equals($_SESSION['csrf_token'] ?? '', (string)($body['csrf_token'] ?? ''))) {
    jsonExit(403, ['error' => 'Érvénytelen kérés-token']);
}

$type = (string)($body['type'] ?? '');
$id   = (int)($body['id'] ?? 0);

if (!in_array($type, ['bird', 'mountain'], true) || $id <= 0) {
    jsonExit(422, ['error' => 'Érvénytelen kérdés']);
}

// Aktív (kiadott) kérdés ellenőrzése — csak arra lehet válaszolni.
$pending = $_SESSION['quiz_q'] ?? null;
if (!is_array($pending) || $pending['type'] !== $type || (int)$pending['id'] !== $id) {
    jsonExit(409, ['error' => 'Nincs aktív kérdés ehhez a válaszhoz. Kérj új kérdést!']);
}

$seconds = max(0.0, microtime(true) - (float)$pending['start']);
$seconds = min($seconds, 86400.0); // épeszű felső korlát

try {
    $pdo    = getDb();
    ensureQuizSchema($pdo);
    $userId = getCurrentUserId();

    if (quizHasAnswered($pdo, $userId, $type, $id)) {
        unset($_SESSION['quiz_q']);
        jsonExit(409, ['error' => 'Erre a kérdésre már válaszoltál.']);
    }

    if ($type === 'bird') {
        $bird = quizGetBird($pdo, $id);
        if (!$bird) {
            jsonExit(404, ['error' => 'A kérdés nem található']);
        }
        $answer = [
            'wingspan'   => (float)($body['wingspan'] ?? -1),
            'colors'     => is_array($body['colors'] ?? null) ? $body['colors'] : [],
            'continents' => is_array($body['continents'] ?? null) ? $body['continents'] : [],
        ];
        $result  = quizScoreBird($bird, $answer, $seconds);
        $correct = [
            'wingspan'   => $bird['wingspan_cm'],
            'colors'     => $bird['colors'],
            'continents' => $bird['continents'],
        ];
        $facts = [
            'latin_name' => $bird['latin_name'],
            'diet'       => $bird['diet'],
            'fun_fact'   => $bird['fun_fact'],
            'image'      => $bird['image'] ? BASE_URL . '/kviz/' . $bird['image'] : null,
        ];
    } else {
        $mtn = quizGetMountain($pdo, $id);
        if (!$mtn) {
            jsonExit(404, ['error' => 'A kérdés nem található']);
        }
        $answer = [
            'elevation' => (float)($body['elevation'] ?? -1),
            'country'   => (string)($body['country'] ?? ''),
        ];
        $result  = quizScoreMountain($mtn, $answer, $seconds);
        $correct = [
            'elevation' => $mtn['elevation_m'],
            'country'   => $mtn['country'],
        ];
        $facts = [
            'mountain_range' => $mtn['mountain_range'],
            'fun_fact'       => $mtn['fun_fact'],
            'image'          => $mtn['image'] ? BASE_URL . '/kviz/' . $mtn['image'] : null,
        ];
    }

    $score = $result['score'];

    $stmt = $pdo->prepare("
        INSERT IGNORE INTO quiz_scores (user_id, item_type, item_id, score, seconds)
        VALUES (:u, :t, :id, :sc, :sec)
    ");
    $stmt->execute([
        ':u'   => $userId,
        ':t'   => $type,
        ':id'  => $id,
        ':sc'  => $score,
        ':sec' => round($seconds, 2),
    ]);
    if ($stmt->rowCount() === 0) {
        // Verseny közben időközben elmentette máshol — ne írjuk felül.
        unset($_SESSION['quiz_q']);
        jsonExit(409, ['error' => 'Erre a kérdésre már válaszoltál.']);
    }

    unset($_SESSION['quiz_q']);

    jsonExit(200, [
        'ok'        => true,
        'score'     => $score,
        'seconds'   => round($seconds, 1),
        'breakdown' => $result['breakdown'],
        'correct'   => $correct,
        'given'     => $answer,
        'facts'     => $facts,
        'toplist'   => quizItemToplist($pdo, $type, $id, $userId, 10),
        'progress'  => quizProgress($pdo, $userId),
    ]);
} catch (Throwable $e) {
    jsonExit(500, ['error' => 'Szerverhiba a mentés közben']);
}
