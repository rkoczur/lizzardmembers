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
        // MTSZ pontszámítás mezők
        "ALTER TABLE `tours` ADD COLUMN `tour_type` ENUM('gyalogos','kerekparos','vizi','si','barlangi','munka') NOT NULL DEFAULT 'gyalogos' AFTER `accommodation`",
        "ALTER TABLE `tours` ADD COLUMN `sub_type` VARCHAR(50) DEFAULT NULL AFTER `tour_type`",
        "ALTER TABLE `tours` ADD COLUMN `is_alpine` TINYINT(1) NOT NULL DEFAULT 0 AFTER `sub_type`",
        "ALTER TABLE `tours` ADD COLUMN `multi_day_type` ENUM('csillag','vandor') DEFAULT NULL AFTER `is_alpine`",
        "ALTER TABLE `tours` ADD COLUMN `camping_nights_fixed` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `multi_day_type`",
        "ALTER TABLE `tours` ADD COLUMN `camping_nights_mobile` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `camping_nights_fixed`",
        "ALTER TABLE `tours` ADD COLUMN `tour_hours` DECIMAL(6,2) DEFAULT NULL AFTER `camping_nights_mobile`",
        "ALTER TABLE `tours` ADD COLUMN `boat_portages` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `tour_hours`",
        // Magashegyi szakasz külön km/szint mezők
        "ALTER TABLE `tours` ADD COLUMN `alpine_km` DECIMAL(8,1) DEFAULT NULL AFTER `total_km`",
        "ALTER TABLE `tours` ADD COLUMN `alpine_elevation` INT UNSIGNED DEFAULT NULL AFTER `total_elevation`",
        // Kétféle pontrendszer: MTSZ (auto-számított) és Lizzardier (kézzel megadott)
        "ALTER TABLE `tours` ADD COLUMN `mtsz_points` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `points`",
        // Egyedi túrasorszám (pl. 12GY, 5K)
        "ALTER TABLE `tours` ADD COLUMN `tour_code` VARCHAR(20) DEFAULT NULL AFTER `id`",
        // Vendég résztvevők száma
        "ALTER TABLE `tours` ADD COLUMN `guest_count` SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER `participant_count`",
        // Túra útvonala (hosszú szabad szöveges mező)
        "ALTER TABLE `tours` ADD COLUMN `route` TEXT DEFAULT NULL AFTER `name`",
    ] as $sql) {
        try { $pdo->exec($sql); } catch (PDOException) {}
    }
}
