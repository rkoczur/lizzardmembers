<?php
/**
 * Kvíz játék — adatbázis-séma, adatfeltöltés (seed) és pontozó logika.
 *
 * A séma lustán jön létre (CREATE TABLE IF NOT EXISTS), az alapadatok pedig
 * a kviz/birds.json és kviz/mountain_peaks.csv fájlokból töltődnek fel, ha a
 * megfelelő tábla üres. Így a játék mindig az adatbázisból dolgozik.
 *
 * Kérdéstípusok:
 *   - 'bird'     : madár  → fesztáv (cm) + színek + kontinensek
 *   - 'mountain' : hegycsúcs → magasság (m) + ország
 */

const QUIZ_BIRDS_JSON = __DIR__ . '/../kviz/birds.json';
const QUIZ_MOUNTAINS_CSV = __DIR__ . '/../kviz/mountain_peaks.csv';

/** Séma létrehozása + alapadatok feltöltése (idempotens). */
function ensureQuizSchema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS `quiz_birds` (
        `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name`        VARCHAR(190) NOT NULL,
        `wingspan_cm` SMALLINT UNSIGNED NOT NULL,
        `colors`      JSON NOT NULL,
        `continents`  JSON NOT NULL,
        `latin_name`  VARCHAR(190) NULL,
        `diet`        TEXT NULL,
        `fun_fact`    TEXT NULL,
        `image`       VARCHAR(255) NULL,
        UNIQUE KEY `uniq_bird_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Migráció: ha a tábla egy korábbi verzióból már létezik, ide kerülnek az új oszlopok.
    $pdo->exec("ALTER TABLE `quiz_birds`
        ADD COLUMN IF NOT EXISTS `latin_name` VARCHAR(190) NULL AFTER `continents`,
        ADD COLUMN IF NOT EXISTS `diet`       TEXT NULL       AFTER `latin_name`,
        ADD COLUMN IF NOT EXISTS `fun_fact`   TEXT NULL       AFTER `diet`,
        ADD COLUMN IF NOT EXISTS `image`      VARCHAR(255) NULL AFTER `fun_fact`");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `quiz_mountains` (
        `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name`           VARCHAR(190) NOT NULL,
        `elevation_m`    MEDIUMINT UNSIGNED NOT NULL,
        `country`        VARCHAR(190) NOT NULL,
        `mountain_range` VARCHAR(190) NULL,
        `fun_fact`       TEXT NULL,
        `image`          VARCHAR(255) NULL,
        UNIQUE KEY `uniq_mountain_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("ALTER TABLE `quiz_mountains`
        ADD COLUMN IF NOT EXISTS `mountain_range` VARCHAR(190) NULL AFTER `country`,
        ADD COLUMN IF NOT EXISTS `fun_fact`       TEXT NULL         AFTER `mountain_range`,
        ADD COLUMN IF NOT EXISTS `image`          VARCHAR(255) NULL AFTER `fun_fact`");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `quiz_scores` (
        `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id`    INT UNSIGNED NOT NULL,
        `item_type`  ENUM('bird','mountain') NOT NULL,
        `item_id`    INT UNSIGNED NOT NULL,
        `score`      INT UNSIGNED NOT NULL,
        `seconds`    DECIMAL(7,2) NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_user_item` (`user_id`, `item_type`, `item_id`),
        KEY `idx_item` (`item_type`, `item_id`),
        KEY `idx_user` (`user_id`),
        CONSTRAINT `fk_quiz_scores_user` FOREIGN KEY (`user_id`)
            REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    seedQuizBirds($pdo);
    seedQuizMountains($pdo);
    backfillQuizBirdFacts($pdo);
    backfillQuizMountainFacts($pdo);
    backfillQuizBirdImages($pdo);
    backfillQuizMountainImages($pdo);

    $done = true;
}

/** Madarak feltöltése a JSON-ból, ha a tábla üres. */
function seedQuizBirds(PDO $pdo): void
{
    if ((int)$pdo->query("SELECT COUNT(*) FROM quiz_birds")->fetchColumn() > 0) {
        return;
    }
    if (!is_readable(QUIZ_BIRDS_JSON)) {
        return;
    }
    $data = json_decode((string)file_get_contents(QUIZ_BIRDS_JSON), true);
    if (!is_array($data)) {
        return;
    }

    $stmt = $pdo->prepare("INSERT IGNORE INTO quiz_birds (name, wingspan_cm, colors, continents, latin_name, diet, fun_fact, image)
                           VALUES (:name, :ws, :colors, :continents, :latin, :diet, :fact, :image)");
    foreach ($data as $b) {
        $name = trim((string)($b['name'] ?? ''));
        $ws   = $b['wingspan_cm'] ?? null;
        $cols = $b['colors'] ?? [];
        $cont = $b['continents'] ?? [];
        // Hiányos rekordok kihagyása.
        if ($name === '' || !is_numeric($ws) || (int)$ws <= 0
            || !is_array($cols) || !$cols || !is_array($cont) || !$cont) {
            continue;
        }
        $cols = array_values(array_unique(array_map('strval', $cols)));
        $cont = array_values(array_unique(array_map('strval', $cont)));
        $stmt->execute([
            ':name'       => $name,
            ':ws'         => (int)$ws,
            ':colors'     => json_encode($cols, JSON_UNESCAPED_UNICODE),
            ':continents' => json_encode($cont, JSON_UNESCAPED_UNICODE),
            ':latin'      => quizNullIfEmpty($b['latin_name'] ?? null),
            ':diet'       => quizNullIfEmpty($b['diet'] ?? null),
            ':fact'       => quizNullIfEmpty($b['fun_fact'] ?? null),
            ':image'      => quizNullIfEmpty($b['image'] ?? null),
        ]);
    }
}

/** Üres/hiányzó mező -> NULL, hogy a backfill később felismerje és pótolja. */
function quizNullIfEmpty(?string $v): ?string
{
    $v = trim((string)$v);
    return $v === '' ? null : $v;
}

/**
 * A már seedelt madár-rekordokat kiegészíti a JSON-ban található latin
 * névvel / étrenddel / érdekességgel, ha ezek korábban még nem lettek
 * elmentve (pl. mert a tábla egy régebbi verzióban jött létre).
 */
function backfillQuizBirdFacts(PDO $pdo): void
{
    if ((int)$pdo->query("SELECT COUNT(*) FROM quiz_birds WHERE latin_name IS NULL")->fetchColumn() === 0) {
        return;
    }
    if (!is_readable(QUIZ_BIRDS_JSON)) {
        return;
    }
    $data = json_decode((string)file_get_contents(QUIZ_BIRDS_JSON), true);
    if (!is_array($data)) {
        return;
    }

    $stmt = $pdo->prepare("UPDATE quiz_birds SET latin_name = :latin, diet = :diet, fun_fact = :fact
                           WHERE name = :name AND latin_name IS NULL");
    foreach ($data as $b) {
        $name  = trim((string)($b['name'] ?? ''));
        $latin = quizNullIfEmpty($b['latin_name'] ?? null);
        if ($name === '' || $latin === null) {
            continue;
        }
        $stmt->execute([
            ':latin' => $latin,
            ':diet'  => quizNullIfEmpty($b['diet'] ?? null),
            ':fact'  => quizNullIfEmpty($b['fun_fact'] ?? null),
            ':name'  => $name,
        ]);
    }
}

/**
 * A már seedelt madár-rekordokat kiegészíti a JSON-ban található kép
 * elérési úttal, ha ez korábban még nem lett elmentve (pl. mert a tábla
 * a kép-letöltés előtt jött létre).
 */
function backfillQuizBirdImages(PDO $pdo): void
{
    if ((int)$pdo->query("SELECT COUNT(*) FROM quiz_birds WHERE image IS NULL")->fetchColumn() === 0) {
        return;
    }
    if (!is_readable(QUIZ_BIRDS_JSON)) {
        return;
    }
    $data = json_decode((string)file_get_contents(QUIZ_BIRDS_JSON), true);
    if (!is_array($data)) {
        return;
    }

    $stmt = $pdo->prepare("UPDATE quiz_birds SET image = :image WHERE name = :name AND image IS NULL");
    foreach ($data as $b) {
        $name  = trim((string)($b['name'] ?? ''));
        $image = quizNullIfEmpty($b['image'] ?? null);
        if ($name === '' || $image === null) {
            continue;
        }
        $stmt->execute([':image' => $image, ':name' => $name]);
    }
}

/** Hegycsúcsok feltöltése a CSV-ből, ha a tábla üres. */
function seedQuizMountains(PDO $pdo): void
{
    if ((int)$pdo->query("SELECT COUNT(*) FROM quiz_mountains")->fetchColumn() > 0) {
        return;
    }
    if (!is_readable(QUIZ_MOUNTAINS_CSV)) {
        return;
    }
    $fh = fopen(QUIZ_MOUNTAINS_CSV, 'r');
    if (!$fh) {
        return;
    }

    $stmt = $pdo->prepare("INSERT IGNORE INTO quiz_mountains (name, elevation_m, country, mountain_range, fun_fact, image)
                           VALUES (:name, :el, :country, :range, :fact, :image)");
    $first = true;
    while (($row = fgetcsv($fh)) !== false) {
        if ($first) { // fejléc kihagyása (BOM-mal együtt)
            $first = false;
            continue;
        }
        if (count($row) < 3) {
            continue;
        }
        $name    = trim((string)$row[0]);
        $el      = $row[1];
        $country = trim((string)$row[2]);
        if ($name === '' || $country === '' || !is_numeric($el) || (int)$el <= 0) {
            continue;
        }
        $stmt->execute([
            ':name'    => $name,
            ':el'      => (int)$el,
            ':country' => $country,
            ':range'   => quizNullIfEmpty($row[3] ?? null),
            ':fact'    => quizNullIfEmpty($row[4] ?? null),
            ':image'   => quizNullIfEmpty($row[5] ?? null),
        ]);
    }
    fclose($fh);
}

/**
 * A már seedelt hegy-rekordokat kiegészíti a CSV-ben található hegység
 * névvel / érdekességgel, ha ezek korábban még nem lettek elmentve.
 */
function backfillQuizMountainFacts(PDO $pdo): void
{
    if ((int)$pdo->query("SELECT COUNT(*) FROM quiz_mountains WHERE fun_fact IS NULL")->fetchColumn() === 0) {
        return;
    }
    if (!is_readable(QUIZ_MOUNTAINS_CSV)) {
        return;
    }
    $fh = fopen(QUIZ_MOUNTAINS_CSV, 'r');
    if (!$fh) {
        return;
    }

    $stmt = $pdo->prepare("UPDATE quiz_mountains SET mountain_range = COALESCE(:range, mountain_range), fun_fact = :fact
                           WHERE name = :name AND fun_fact IS NULL");
    $first = true;
    while (($row = fgetcsv($fh)) !== false) {
        if ($first) {
            $first = false;
            continue;
        }
        if (count($row) < 4) {
            continue;
        }
        $name  = trim((string)$row[0]);
        $range = quizNullIfEmpty($row[3] ?? null);
        $fact  = quizNullIfEmpty($row[4] ?? null);
        if ($name === '' || ($range === null && $fact === null)) {
            continue;
        }
        $stmt->execute([
            ':range' => $range,
            ':fact'  => $fact,
            ':name'  => $name,
        ]);
    }
    fclose($fh);
}

/**
 * A már seedelt hegy-rekordokat kiegészíti a CSV-ben található kép
 * elérési úttal, ha ez korábban még nem lett elmentve (pl. mert a tábla
 * a kép-letöltés előtt jött létre).
 */
function backfillQuizMountainImages(PDO $pdo): void
{
    if ((int)$pdo->query("SELECT COUNT(*) FROM quiz_mountains WHERE image IS NULL")->fetchColumn() === 0) {
        return;
    }
    if (!is_readable(QUIZ_MOUNTAINS_CSV)) {
        return;
    }
    $fh = fopen(QUIZ_MOUNTAINS_CSV, 'r');
    if (!$fh) {
        return;
    }

    $stmt = $pdo->prepare("UPDATE quiz_mountains SET image = :image WHERE name = :name AND image IS NULL");
    $first = true;
    while (($row = fgetcsv($fh)) !== false) {
        if ($first) {
            $first = false;
            continue;
        }
        $name  = trim((string)($row[0] ?? ''));
        $image = quizNullIfEmpty($row[5] ?? null);
        if ($name === '' || $image === null) {
            continue;
        }
        $stmt->execute([':image' => $image, ':name' => $name]);
    }
    fclose($fh);
}

/* ----------------------------------------------------------------------- */
/*  Adat-segédfüggvények (választási lehetőségek)                          */
/* ----------------------------------------------------------------------- */

/** Az összes szín, ami a madaraknál előfordul — ábécésorrendben. */
function quizAllColors(PDO $pdo): array
{
    return quizDistinctJsonValues($pdo, 'colors');
}

/** Az összes kontinens, ami a madaraknál előfordul — ábécésorrendben. */
function quizAllContinents(PDO $pdo): array
{
    return quizDistinctJsonValues($pdo, 'continents');
}

/** Egy JSON-tömb oszlop (colors/continents) összes egyedi értéke a madaraknál. */
function quizDistinctJsonValues(PDO $pdo, string $column): array
{
    if (!in_array($column, ['colors', 'continents'], true)) {
        return [];
    }
    $set = [];
    foreach ($pdo->query("SELECT `$column` AS v FROM quiz_birds") as $r) {
        $arr = json_decode((string)$r['v'], true);
        if (is_array($arr)) {
            foreach ($arr as $val) {
                $set[(string)$val] = true;
            }
        }
    }
    $out = array_keys($set);
    quizSortHu($out);
    return $out;
}

/** Az összes ország, ami a hegycsúcsoknál előfordul — ábécésorrendben. */
function quizAllCountries(PDO $pdo): array
{
    $out = $pdo->query("SELECT DISTINCT country FROM quiz_mountains")
               ->fetchAll(PDO::FETCH_COLUMN);
    quizSortHu($out);
    return $out;
}

/** Magyar ékezetérzékeny rendezés, ha elérhető a Collator; egyébként sima. */
function quizSortHu(array &$arr): void
{
    if (class_exists('Collator')) {
        $c = new Collator('hu_HU');
        $c->sort($arr);
    } else {
        sort($arr, SORT_STRING | SORT_FLAG_CASE);
    }
}

/* ----------------------------------------------------------------------- */
/*  Rekord-lekérdezés                                                       */
/* ----------------------------------------------------------------------- */

/** Egy madár rekordja dekódolt színek/kontinensek tömbökkel, vagy null. */
function quizGetBird(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT id, name, wingspan_cm, colors, continents, latin_name, diet, fun_fact, image
                           FROM quiz_birds WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $row['wingspan_cm'] = (int)$row['wingspan_cm'];
    $row['colors']      = json_decode((string)$row['colors'], true) ?: [];
    $row['continents']  = json_decode((string)$row['continents'], true) ?: [];
    return $row;
}

/** Egy hegycsúcs rekordja, vagy null. */
function quizGetMountain(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT id, name, elevation_m, country, mountain_range, fun_fact, image
                           FROM quiz_mountains WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $row['elevation_m'] = (int)$row['elevation_m'];
    return $row;
}

/** Egy tag által még nem válaszolt véletlen kérdés: ['type'=>, 'id'=>] vagy null. */
function quizPickNextQuestion(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare("
        (SELECT 'bird' AS t, b.id AS id FROM quiz_birds b
         WHERE NOT EXISTS (SELECT 1 FROM quiz_scores s
                           WHERE s.user_id = :u1 AND s.item_type = 'bird' AND s.item_id = b.id))
        UNION ALL
        (SELECT 'mountain' AS t, m.id AS id FROM quiz_mountains m
         WHERE NOT EXISTS (SELECT 1 FROM quiz_scores s
                           WHERE s.user_id = :u2 AND s.item_type = 'mountain' AND s.item_id = m.id))
        ORDER BY RAND() LIMIT 1
    ");
    $stmt->execute([':u1' => $userId, ':u2' => $userId]);
    $row = $stmt->fetch();
    return $row ? ['type' => $row['t'], 'id' => (int)$row['id']] : null;
}

/** Összes kérdés száma és a tag által megválaszoltak száma. */
function quizProgress(PDO $pdo, int $userId): array
{
    $total = (int)$pdo->query("SELECT COUNT(*) FROM quiz_birds")->fetchColumn()
           + (int)$pdo->query("SELECT COUNT(*) FROM quiz_mountains")->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_scores WHERE user_id = ?");
    $stmt->execute([$userId]);
    return ['answered' => (int)$stmt->fetchColumn(), 'total' => $total];
}

/* ----------------------------------------------------------------------- */
/*  Toplisták                                                               */
/* ----------------------------------------------------------------------- */

/** Egy adott kérdés (madár/hegy) toplistája: a legjobb $limit eredmény. */
function quizItemToplist(PDO $pdo, string $type, int $id, int $meId, int $limit = 10): array
{
    $stmt = $pdo->prepare("
        SELECT s.user_id, s.score, s.seconds, u.firstname, u.lastname
        FROM quiz_scores s
        JOIN users u ON u.id = s.user_id
        WHERE s.item_type = :t AND s.item_id = :id
        ORDER BY s.score DESC, s.seconds ASC, s.created_at ASC
        LIMIT " . (int)$limit . "
    ");
    $stmt->execute([':t' => $type, ':id' => $id]);
    $rows = [];
    foreach ($stmt->fetchAll() as $i => $r) {
        $rows[] = [
            'rank'  => $i + 1,
            'name'  => trim($r['lastname'] . ' ' . $r['firstname']),
            'score' => (int)$r['score'],
            'seconds' => (float)$r['seconds'],
            'isMe'  => (int)$r['user_id'] === $meId,
        ];
    }
    return $rows;
}

/** Összesített toplista: minden tag átlagpontja és megválaszolt kérdéseinek száma. */
function quizOverallLeaderboard(PDO $pdo, int $meId, int $limit = 100): array
{
    $stmt = $pdo->prepare("
        SELECT s.user_id, u.firstname, u.lastname,
               AVG(s.score) AS avg_score, COUNT(*) AS cnt
        FROM quiz_scores s
        JOIN users u ON u.id = s.user_id
        GROUP BY s.user_id, u.firstname, u.lastname
        ORDER BY avg_score DESC, cnt DESC
        LIMIT " . (int)$limit . "
    ");
    $stmt->execute();
    $rows = [];
    foreach ($stmt->fetchAll() as $i => $r) {
        $rows[] = [
            'rank'    => $i + 1,
            'name'    => trim($r['lastname'] . ' ' . $r['firstname']),
            'average' => round((float)$r['avg_score'], 1),
            'count'   => (int)$r['cnt'],
            'isMe'    => (int)$r['user_id'] === $meId,
        ];
    }
    return $rows;
}

/** A tag által megválaszolt kérdések listája (típus, név, pont). */
function quizMyItems(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare("
        SELECT s.item_type, s.item_id, s.score, s.seconds, s.created_at,
               COALESCE(b.name, m.name) AS name
        FROM quiz_scores s
        LEFT JOIN quiz_birds b     ON s.item_type = 'bird'     AND b.id = s.item_id
        LEFT JOIN quiz_mountains m ON s.item_type = 'mountain' AND m.id = s.item_id
        WHERE s.user_id = ?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$userId]);
    $rows = [];
    foreach ($stmt->fetchAll() as $r) {
        $rows[] = [
            'type'  => $r['item_type'],
            'id'    => (int)$r['item_id'],
            'name'  => (string)$r['name'],
            'score' => (int)$r['score'],
        ];
    }
    return $rows;
}

/** Igaz, ha a tag már válaszolt az adott kérdésre. */
function quizHasAnswered(PDO $pdo, int $userId, string $type, int $id): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM quiz_scores WHERE user_id = ? AND item_type = ? AND item_id = ? LIMIT 1");
    $stmt->execute([$userId, $type, $id]);
    return (bool)$stmt->fetchColumn();
}

/* ----------------------------------------------------------------------- */
/*  Pontozás                                                                */
/* ----------------------------------------------------------------------- */

/** Időszorzó: 3-ról indul, másodpercenként 0,025-tel csökken, 1 alá nem megy. */
function quizTimeMultiplier(float $seconds): float
{
    return max(1.0, 3.0 - 0.025 * $seconds);
}

/**
 * Fesztáv / magasság alappont: pontos találat = $max, minden egységnyi
 * eltérés $perUnit-tal csökkenti, nulla alá nem megy.
 */
function quizProximityPoints(float $guess, float $actual, float $max, float $perUnit): float
{
    return max(0.0, $max - $perUnit * abs($guess - $actual));
}

/**
 * Több választós (szín / kontinens) alappont: a $total pont oszlik el a helyes
 * válaszok között, a hibás választás -1 pont.
 */
function quizMultiSelectPoints(array $selected, array $correct, float $total): float
{
    $correctCount = count($correct);
    if ($correctCount === 0) {
        return 0.0;
    }
    $per = $total / $correctCount;
    $correctSet  = array_fill_keys(array_map('strval', $correct), true);
    $seen = [];
    $pts  = 0.0;
    foreach ($selected as $s) {
        $s = (string)$s;
        if (isset($seen[$s])) {
            continue; // duplikátumot egyszer számolunk
        }
        $seen[$s] = true;
        $pts += isset($correctSet[$s]) ? $per : -1.0;
    }
    return $pts;
}

/**
 * Madár-kérdés kiértékelése.
 * @return array{score:int, breakdown:array}
 */
function quizScoreBird(array $bird, array $answer, float $seconds): array
{
    $mult = quizTimeMultiplier($seconds);

    $wingspanPts = quizProximityPoints(
        (float)($answer['wingspan'] ?? -99999),
        (float)$bird['wingspan_cm'],
        40.0,
        0.8
    );
    $colorPts     = quizMultiSelectPoints($answer['colors'] ?? [], $bird['colors'], 20.0);
    $continentPts = quizMultiSelectPoints($answer['continents'] ?? [], $bird['continents'], 20.0);

    $raw   = $wingspanPts + $colorPts + $continentPts;
    $score = max(0, (int)ceil($raw * $mult));

    return [
        'score' => $score,
        'breakdown' => [
            'multiplier'  => round($mult, 3),
            'wingspan'    => round($wingspanPts, 2),
            'colors'      => round($colorPts, 2),
            'continents'  => round($continentPts, 2),
            'raw'         => round($raw, 2),
        ],
    ];
}

/**
 * Hegycsúcs-kérdés kiértékelése.
 * @return array{score:int, breakdown:array}
 */
function quizScoreMountain(array $mountain, array $answer, float $seconds): array
{
    $mult = quizTimeMultiplier($seconds);

    $heightPts  = quizProximityPoints(
        (float)($answer['elevation'] ?? -999999),
        (float)$mountain['elevation_m'],
        40.0,
        0.04
    );
    $countryPts = (trim((string)($answer['country'] ?? '')) === (string)$mountain['country']) ? 40.0 : 0.0;

    $raw   = $heightPts + $countryPts;
    $score = max(0, (int)ceil($raw * $mult));

    return [
        'score' => $score,
        'breakdown' => [
            'multiplier' => round($mult, 3),
            'elevation'  => round($heightPts, 2),
            'country'    => round($countryPts, 2),
            'raw'        => round($raw, 2),
        ],
    ];
}
