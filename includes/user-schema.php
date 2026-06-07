<?php
function ensureUserSchema(PDO $pdo): void
{
    foreach ([
        "ALTER TABLE `users` ADD COLUMN `phone` VARCHAR(30) DEFAULT NULL AFTER `address`",
        "ALTER TABLE `users` ADD COLUMN `emergency_name` VARCHAR(100) DEFAULT NULL AFTER `tshirt_size`",
        "ALTER TABLE `users` ADD COLUMN `emergency_relation` ENUM('szülő','gyermek','testvér','egyéb') DEFAULT NULL AFTER `emergency_name`",
        "ALTER TABLE `users` ADD COLUMN `emergency_phone` VARCHAR(30) DEFAULT NULL AFTER `emergency_relation`",
        "ALTER TABLE `users` ADD COLUMN `login_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `active`",
        "ALTER TABLE `users` ADD COLUMN `locked_at` TIMESTAMP NULL DEFAULT NULL AFTER `login_attempts`",
        "ALTER TABLE `users` MODIFY COLUMN `role` ENUM('admin','user','vezeto','helyettes','penzugyi','jogi','kommunikacios') NOT NULL DEFAULT 'user'",
        "ALTER TABLE `users` ADD COLUMN `notification_prefs` TEXT DEFAULT NULL AFTER `emergency_phone`",
        "ALTER TABLE `users` ADD COLUMN `consent_email_visibility` TINYINT(1) DEFAULT NULL AFTER `notification_prefs`",
        "ALTER TABLE `users` ADD COLUMN `consent_photo` TINYINT(1) DEFAULT NULL AFTER `consent_email_visibility`",
    ] as $sql) {
        try { $pdo->exec($sql); } catch (PDOException) {}
    }
}
