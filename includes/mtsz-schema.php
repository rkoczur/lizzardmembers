<?php
/**
 * MTSZ (Magyar Természetjáró Szövetség) jelvényes minősítések tárolása.
 * Egy tag több fokozatot is megszerezhet, fokozatonként legfeljebb egyszer.
 */
function ensureMtszSchema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS mtsz_qualifications (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id     INT UNSIGNED NOT NULL,
            grade       VARCHAR(20) NOT NULL,
            reg_number  VARCHAR(60) DEFAULT NULL,
            awarded_on  DATE DEFAULT NULL,
            created_by  INT UNSIGNED DEFAULT NULL,
            created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_grade (user_id, grade),
            KEY idx_user (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}
