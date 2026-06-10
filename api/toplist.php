<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/user-schema.php';

$requestKey = $_SERVER['HTTP_X_LOTE_KEY'] ?? '';
if (!API_KEY || !hash_equals(API_KEY, $requestKey)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=300');

// Csak aktív (idei tagdíj) és tagdíj-elmaradásos (tavalyi) tagok jelennek meg
define('LOTE_STATUS_FILTER', "
    u.last_payment IS NOT NULL
    AND u.last_payment != '0000-00-00'
    AND YEAR(u.last_payment) >= YEAR(CURDATE()) - 1
");

function toplistLevelImagePath(int $level): ?string
{
    $filename = getLevelImageFilename($level);
    return $filename ? '/assets/img/' . $filename : null;
}

try {
    $pdo = getDb();
    ensureUserSchema($pdo);

    // 1. Örökös toplista
    $allTime = $pdo->query("
        SELECT u.firstname, u.lastname, u.level,
               COALESCE(SUM(t.points), 0) AS total_points
        FROM users u
        LEFT JOIN tour_members tm ON tm.user_id = u.id
        LEFT JOIN tours t ON t.id = tm.tour_id
        WHERE u.role != 'admin'
          AND COALESCE(u.is_candidate, 0) = 0
          AND " . LOTE_STATUS_FILTER . "
        GROUP BY u.id, u.firstname, u.lastname, u.level
        HAVING total_points >= 3
        ORDER BY total_points DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($allTime as &$r) {
        $r['level']        = (int)$r['level'];
        $r['level_label']  = getLevelLabel($r['level']);
        $r['image_path']   = toplistLevelImagePath($r['level']);
        $r['total_points'] = (int)$r['total_points'];
    }
    unset($r);

    // 2. Idei toplista
    $stmtYear = $pdo->prepare("
        SELECT u.firstname, u.lastname, u.level,
               SUM(t.points) AS total_points
        FROM tour_members tm
        JOIN tours t ON t.id = tm.tour_id AND YEAR(t.tour_date) = :yr
        JOIN users u ON u.id = tm.user_id
        WHERE u.role != 'admin'
          AND COALESCE(u.is_candidate, 0) = 0
          AND " . LOTE_STATUS_FILTER . "
        GROUP BY u.id, u.firstname, u.lastname, u.level
        ORDER BY total_points DESC
        LIMIT 20
    ");
    $stmtYear->execute([':yr' => (int)date('Y')]);
    $currentYear = $stmtYear->fetchAll(PDO::FETCH_ASSOC);

    foreach ($currentYear as &$r) {
        $r['level']        = (int)($r['level'] ?? 1);
        $r['level_label']  = getLevelLabel($r['level']);
        $r['image_path']   = toplistLevelImagePath($r['level']);
        $r['total_points'] = (int)$r['total_points'];
    }
    unset($r);

    // 3. Év túratársa (korrigált pontszám)
    $allYearRows = $pdo->query("
        SELECT YEAR(t.tour_date) AS yr, u.id, u.firstname, u.lastname, u.level,
               SUM(t.points) AS pts
        FROM tour_members tm
        JOIN tours t ON t.id = tm.tour_id
        JOIN users u ON u.id = tm.user_id
        WHERE t.tour_date IS NOT NULL
          AND u.role != 'admin'
          AND YEAR(t.tour_date) < YEAR(CURDATE())
          AND " . LOTE_STATUS_FILTER . "
        GROUP BY YEAR(t.tour_date), u.id, u.firstname, u.lastname, u.level
        ORDER BY yr ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $pointsByYear = [];
    foreach ($allYearRows as $row) {
        $pointsByYear[(int)$row['yr']][(int)$row['id']] = $row;
    }

    $byYearTop      = [];
    $prevChampionId = null;

    foreach ($pointsByYear as $yr => $users) {
        $prevYearUsers = $pointsByYear[$yr - 1] ?? [];
        $adjusted      = [];

        foreach ($users as $uid => $udata) {
            $score = (float)$udata['pts'];
            $bonus = 0.0;
            if ($prevChampionId !== null && $uid !== $prevChampionId) {
                $bonus  = (float)($prevYearUsers[$uid]['pts'] ?? 0) * 0.20;
                $score += $bonus;
            }
            $adjusted[$uid] = ['score' => $score, 'raw' => (float)$udata['pts'], 'bonus' => $bonus, 'data' => $udata];
        }

        uasort($adjusted, fn($a, $b) => $b['score'] <=> $a['score']);
        $winnerId       = array_key_first($adjusted);
        $w              = $adjusted[$winnerId];
        $lvl            = (int)($w['data']['level'] ?? 1);
        $byYearTop[$yr] = [
            'year'         => $yr,
            'firstname'    => $w['data']['firstname'],
            'lastname'     => $w['data']['lastname'],
            'level'        => $lvl,
            'level_label'  => getLevelLabel($lvl),
            'image_path'   => toplistLevelImagePath($lvl),
            'total_points' => round($w['score'], 1),
            'raw_points'   => (int)$w['raw'],
            'bonus'        => round($w['bonus'], 1),
        ];

        $prevChampionId = $winnerId;
    }

    krsort($byYearTop);

    echo json_encode([
        'alltime'      => $allTime,
        'year'         => $currentYear,
        'yearwinner'   => array_values($byYearTop),
        'current_year' => (int)date('Y'),
    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Adatbázis hiba'], JSON_UNESCAPED_UNICODE);
}
