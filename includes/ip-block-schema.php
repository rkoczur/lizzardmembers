<?php
function ensureIpBlockSchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS `ip_blocks` (
        `ip`           VARCHAR(45)      NOT NULL,
        `attempts`     TINYINT UNSIGNED NOT NULL DEFAULT 1,
        `blocked`      TINYINT(1)       NOT NULL DEFAULT 0,
        `last_attempt` TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`ip`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
