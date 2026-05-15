<?php
function ensureToursSchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS `tours` (
        `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `name`              VARCHAR(200) DEFAULT NULL,
        `country`           VARCHAR(100) NOT NULL,
        `region`            VARCHAR(100) DEFAULT NULL,
        `tour_date`         DATE DEFAULT NULL,
        `days`              TINYINT UNSIGNED NOT NULL DEFAULT 1,
        `accommodation`     VARCHAR(100) DEFAULT NULL,
        `total_km`          DECIMAL(8,1) DEFAULT NULL,
        `total_elevation`   INT UNSIGNED DEFAULT NULL,
        `participant_count` SMALLINT UNSIGNED DEFAULT NULL,
        `points`            INT UNSIGNED NOT NULL DEFAULT 0,
        `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `tour_members` (
        `tour_id`    INT UNSIGNED NOT NULL,
        `user_id`    INT UNSIGNED NOT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`tour_id`, `user_id`),
        FOREIGN KEY (`tour_id`) REFERENCES `tours`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Migrate existing installs — errors ignored if column already exists
    foreach ([
        "ALTER TABLE `tours` ADD COLUMN `name` VARCHAR(200) DEFAULT NULL AFTER `id`",
        "ALTER TABLE `tours` ADD COLUMN `tour_date` DATE DEFAULT NULL AFTER `region`",
        "ALTER TABLE `tours` ADD COLUMN `participant_count` SMALLINT UNSIGNED DEFAULT NULL AFTER `total_elevation`",
    ] as $sql) {
        try { $pdo->exec($sql); } catch (PDOException) {}
    }
}
