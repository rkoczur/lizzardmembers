<?php
function ensureAuditSchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS `audit_log` (
        `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
        `admin_id`     INT UNSIGNED     NOT NULL,
        `admin_name`   VARCHAR(100)     NOT NULL,
        `action`       ENUM('create','update','delete') NOT NULL,
        `entity_type`  ENUM('member','tour') NOT NULL,
        `entity_id`    INT UNSIGNED     NOT NULL,
        `entity_label` VARCHAR(255)     NOT NULL,
        `changes`      TEXT,
        `created_at`   TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_entity` (`entity_type`, `entity_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
