<?php
function ensureLoginLogSchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS `login_log` (
        `id`          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
        `user_id`     INT UNSIGNED     NULL,
        `name`        VARCHAR(150)     NOT NULL DEFAULT '',
        `username`    VARCHAR(100)     NOT NULL DEFAULT '',
        `ip`          VARCHAR(45)      NOT NULL,
        `user_agent`  VARCHAR(500)     NOT NULL DEFAULT '',
        `event_type`  ENUM('login','password_reset_request','password_reset_complete') NOT NULL DEFAULT 'login',
        `status`      ENUM('success','failed') NOT NULL,
        `fail_reason` VARCHAR(255)     NULL,
        `created_at`  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Migration for existing installations
    try {
        $pdo->exec("ALTER TABLE `login_log` ADD COLUMN `event_type` ENUM('login','password_reset_request','password_reset_complete') NOT NULL DEFAULT 'login' AFTER `user_agent`");
    } catch (PDOException $e) { /* column already exists — ignore */ }
}
