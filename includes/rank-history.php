<?php
/**
 * Ranglétra — melyik túrával és mikor érte el a tag az egyes Lizzardier szinteket.
 * A rang nincs tárolva: a tag túráit időrendben összesítjük, és ahol a pontösszeg
 * átlépi egy szint küszöbét, az a túra hozta a rangot (egy túra több szintet is hozhat).
 */

// Szintek minimális pontszáma (ugyanaz, mint getLevelFromPoints())
function getLevelMinPoints(int $level): int
{
    return [1 => 0, 2 => 3, 3 => 25, 4 => 50, 5 => 100, 6 => 170, 7 => 250, 8 => 330, 9 => 500][$level] ?? 0;
}

/**
 * A tag rangjai időrendben: [['level', 'tour' => ?array, 'total'], ...].
 * Az 1. szint (Újonc) mindig az első, túra nélkül.
 */
function getRankHistory(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT t.id, t.name, t.region, t.tour_date, t.points,
               COALESCE(c.name_hu, t.country) AS country_name
        FROM tour_members tm
        JOIN tours t ON t.id = tm.tour_id
        LEFT JOIN countries c ON c.code = t.country
        WHERE tm.user_id = ?
        ORDER BY t.tour_date IS NULL, t.tour_date, t.id
    ");
    $stmt->execute([$userId]);

    $ranks = [['level' => 1, 'tour' => null, 'total' => 0]];
    $total = 0;
    $level = 1;
    foreach ($stmt->fetchAll() as $tour) {
        $total += (int)$tour['points'];
        while ($level < 9 && $total >= getLevelMinPoints($level + 1)) {
            $level++;
            $ranks[] = ['level' => $level, 'tour' => $tour, 'total' => $total];
        }
    }
    return $ranks;
}

// Túra helyszíne: tájegység, ország
function getTourPlace(array $tour): string
{
    return implode(', ', array_filter([$tour['region'] ?? null, $tour['country_name'] ?? null]));
}

/**
 * Egy túrán rangot szerzett tagok: [['user' => [...], 'levels' => [int, ...]], ...].
 * Minden résztvevő ranglétráját kiszámolja, és kiválasztja az ehhez a túrához tartozó szinteket.
 */
function getTourRankAchievers(PDO $pdo, int $tourId): array
{
    $stmt = $pdo->prepare("
        SELECT u.id, u.lastname, u.firstname
        FROM tour_members tm
        JOIN users u ON u.id = tm.user_id
        WHERE tm.tour_id = ?
        ORDER BY u.lastname, u.firstname
    ");
    $stmt->execute([$tourId]);

    $achievers = [];
    foreach ($stmt->fetchAll() as $user) {
        $levels = [];
        foreach (getRankHistory($pdo, (int)$user['id']) as $r) {
            if ($r['tour'] && (int)$r['tour']['id'] === $tourId) $levels[] = $r['level'];
        }
        if ($levels) $achievers[] = ['user' => $user, 'levels' => $levels];
    }
    return $achievers;
}
