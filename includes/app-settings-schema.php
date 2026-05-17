<?php
function ensureAppSettingsSchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `key`        VARCHAR(100) NOT NULL,
        `value`      TEXT         DEFAULT NULL,
        `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `password_resets` (
        `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `user_id`    INT UNSIGNED NOT NULL,
        `token_hash` VARCHAR(64)  NOT NULL,
        `expires_at` DATETIME     NOT NULL,
        `used_at`    DATETIME     DEFAULT NULL,
        `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_token_hash` (`token_hash`),
        KEY `idx_user_id` (`user_id`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function getSetting(PDO $pdo, string $key, string $default = ''): string
{
    static $cache = [];
    if (!isset($cache[$key])) {
        $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        $cache[$key] = $val !== false ? (string)$val : null;
    }
    return $cache[$key] ?? $default;
}

function saveSetting(PDO $pdo, string $key, string $value): void
{
    $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")
        ->execute([$key, $value]);
}

function getSmtpConfig(PDO $pdo): array
{
    return [
        'host'       => getSetting($pdo, 'smtp_host',       ''),
        'port'       => (int)getSetting($pdo, 'smtp_port',  '587'),
        'user'       => getSetting($pdo, 'smtp_user',       ''),
        'pass'       => getSetting($pdo, 'smtp_pass',       ''),
        'from_name'  => getSetting($pdo, 'smtp_from_name',  APP_NAME),
        'from_email' => getSetting($pdo, 'smtp_from_email', ''),
        'encryption' => getSetting($pdo, 'smtp_encryption', 'tls'),
    ];
}
