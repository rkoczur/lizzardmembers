<?php
function ensureEmailLogSchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS `email_log` (
        `id`               INT AUTO_INCREMENT PRIMARY KEY,
        `user_id`          INT DEFAULT NULL,
        `recipient_email`  VARCHAR(255) NOT NULL,
        `recipient_name`   VARCHAR(255) DEFAULT NULL,
        `subject`          VARCHAR(500) NOT NULL,
        `html_body`        MEDIUMTEXT NOT NULL,
        `email_type`       VARCHAR(100) DEFAULT NULL,
        `status`           ENUM('sent','failed') NOT NULL DEFAULT 'sent',
        `error_message`    TEXT DEFAULT NULL,
        `smtp_response`    TEXT DEFAULT NULL,
        `sent_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_user_id`  (`user_id`),
        KEY `idx_sent_at`  (`sent_at`),
        KEY `idx_status`   (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Migration for existing installations
    try {
        $pdo->exec("ALTER TABLE `email_log` ADD COLUMN `smtp_response` TEXT DEFAULT NULL AFTER `error_message`");
    } catch (PDOException) { /* column already exists — ignore */ }
}

function logEmailEntry(
    PDO $pdo,
    ?int $userId,
    string $recipientEmail,
    string $recipientName,
    string $subject,
    string $htmlBody,
    string $emailType,
    string $status = 'sent',
    string $errorMessage = '',
    string $smtpResponse = ''
): void {
    $pdo->prepare("INSERT INTO email_log
        (user_id, recipient_email, recipient_name, subject, html_body, email_type, status, error_message, smtp_response)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([
            $userId ?: null,
            $recipientEmail,
            $recipientName ?: null,
            $subject,
            $htmlBody,
            $emailType ?: null,
            $status,
            $errorMessage ?: null,
            $smtpResponse ?: null,
        ]);
}
