<?php
/**
 * Hegymászó játék — eredmények sémája és betöltő segédfüggvény.
 *
 * Tagonként és pályánként egy sor: a legjobb (legrövidebb) idő és a teljesítések száma.
 * A séma lustán jön létre (CREATE TABLE IF NOT EXISTS) az első használatkor.
 */

function ensureGameSchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS `game_results` (
        `id`                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id`            INT UNSIGNED NOT NULL,
        `level`              TINYINT UNSIGNED NOT NULL,
        `best_time`          DECIMAL(7,1) NOT NULL,
        `completions`        INT UNSIGNED NOT NULL DEFAULT 1,
        `first_completed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_user_level` (`user_id`, `level`),
        KEY `idx_user` (`user_id`),
        CONSTRAINT `fk_game_results_user` FOREIGN KEY (`user_id`)
            REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Egy tag játékmentése a játék által várt alakban: { unlocked, best:{ "<szint>": idő } }.
 * unlocked = a legmagasabb teljesített szint + 1 (a szintek sorban nyílnak).
 */
function loadGameSave(PDO $pdo, int $userId, int $totalLevels = 19): array
{
    ensureGameSchema($pdo);
    $stmt = $pdo->prepare("SELECT level, best_time FROM game_results WHERE user_id = ? ORDER BY level");
    $stmt->execute([$userId]);

    $best = [];
    $maxCompleted = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $lvl = (int)$r['level'];
        if ($lvl < 1 || $lvl > $totalLevels) {
            continue;
        }
        $best[(string)$lvl] = (float)$r['best_time'];
        if ($lvl > $maxCompleted) {
            $maxCompleted = $lvl;
        }
    }

    $unlocked = min($totalLevels, max(1, $maxCompleted + 1));
    return ['unlocked' => $unlocked, 'best' => $best];
}
