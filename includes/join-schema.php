<?php
function ensureJoinSchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS `member_applications` (
        `id`            INT AUTO_INCREMENT PRIMARY KEY,
        `lastname`      VARCHAR(100) NOT NULL,
        `firstname`     VARCHAR(100) NOT NULL,
        `email`         VARCHAR(255) NOT NULL,
        `phone`         VARCHAR(50)  DEFAULT NULL,
        `dateofbirth`   DATE         DEFAULT NULL,
        `zipcode`       VARCHAR(20)  DEFAULT NULL,
        `city`          VARCHAR(100) DEFAULT NULL,
        `address`       VARCHAR(255) DEFAULT NULL,
        `message`       TEXT         DEFAULT NULL,
        `consent_email` TINYINT(1)   NOT NULL DEFAULT 0,
        `consent_photo` TINYINT(1)   NOT NULL DEFAULT 0,
        `consent_rules` TINYINT(1)   NOT NULL DEFAULT 0,
        `status`        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        `submitted_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `reviewed_at`   DATETIME     DEFAULT NULL,
        `reviewed_by`   INT          DEFAULT NULL,
        KEY `idx_email`        (`email`),
        KEY `idx_status`       (`status`),
        KEY `idx_submitted_at` (`submitted_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
