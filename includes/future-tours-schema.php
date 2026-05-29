<?php
function ensureFutureToursSchema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS future_tours (
            id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name          VARCHAR(255) NOT NULL,
            description   TEXT,
            start_date    DATE NOT NULL,
            num_days      INT UNSIGNED NOT NULL DEFAULT 1,
            max_attendees INT UNSIGNED NOT NULL DEFAULT 1,
            country       VARCHAR(10),
            region        VARCHAR(255),
            participation_fee DECIMAL(10,2) DEFAULT NULL,
            status        ENUM('open','closed','cancelled') NOT NULL DEFAULT 'open',
            created_by    INT UNSIGNED,
            created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS future_tour_days (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            future_tour_id  INT UNSIGNED NOT NULL,
            day_number      TINYINT UNSIGNED NOT NULL,
            tour_type       VARCHAR(50),
            km              DECIMAL(6,1),
            elevation       INT,
            description     TEXT,
            FOREIGN KEY (future_tour_id) REFERENCES future_tours(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS future_tour_custom_fields (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            future_tour_id  INT UNSIGNED NOT NULL,
            field_name      VARCHAR(255) NOT NULL,
            field_type      ENUM('text','number','checkbox','select','textarea') NOT NULL DEFAULT 'text',
            field_options   TEXT DEFAULT NULL,
            sort_order      INT NOT NULL DEFAULT 0,
            FOREIGN KEY (future_tour_id) REFERENCES future_tours(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Migrate existing installs — ignore error if column already exists
    try { $pdo->exec("ALTER TABLE future_tour_custom_fields ADD COLUMN field_options TEXT DEFAULT NULL AFTER field_type"); } catch (Throwable) {}
    try { $pdo->exec("ALTER TABLE future_tours ADD COLUMN participation_fee DECIMAL(10,2) DEFAULT NULL AFTER max_attendees"); } catch (Throwable) {}
    try { $pdo->exec("ALTER TABLE future_tours ADD COLUMN accommodation TEXT DEFAULT NULL AFTER region"); } catch (Throwable) {}
    try { $pdo->exec("ALTER TABLE future_tours ADD COLUMN travel TEXT DEFAULT NULL AFTER accommodation"); } catch (Throwable) {}
    try { $pdo->exec("ALTER TABLE future_tours ADD COLUMN equipment TEXT DEFAULT NULL AFTER travel"); } catch (Throwable) {}
    try { $pdo->exec("ALTER TABLE future_tours ADD COLUMN experience TEXT DEFAULT NULL AFTER equipment"); } catch (Throwable) {}
    // Guest application support
    try { $pdo->exec("ALTER TABLE future_tour_applications MODIFY user_id INT UNSIGNED NULL"); } catch (Throwable) {}
    try { $pdo->exec("ALTER TABLE future_tour_applications MODIFY status ENUM('confirmed','waitlist','cancelled','pending') NOT NULL DEFAULT 'confirmed'"); } catch (Throwable) {}
    try { $pdo->exec("ALTER TABLE future_tour_applications ADD COLUMN guest_name VARCHAR(255) DEFAULT NULL AFTER user_id"); } catch (Throwable) {}
    try { $pdo->exec("ALTER TABLE future_tour_applications ADD COLUMN guest_email VARCHAR(255) DEFAULT NULL AFTER guest_name"); } catch (Throwable) {}
    try { $pdo->exec("ALTER TABLE future_tour_applications ADD COLUMN guest_phone VARCHAR(50) DEFAULT NULL AFTER guest_email"); } catch (Throwable) {}

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS future_tour_applications (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            future_tour_id  INT UNSIGNED NOT NULL,
            user_id         INT UNSIGNED NULL,
            guest_name      VARCHAR(255) DEFAULT NULL,
            guest_email     VARCHAR(255) DEFAULT NULL,
            guest_phone     VARCHAR(50)  DEFAULT NULL,
            status          ENUM('confirmed','waitlist','cancelled','pending') NOT NULL DEFAULT 'confirmed',
            car_available   TINYINT(1) NOT NULL DEFAULT 0,
            passengers      TINYINT UNSIGNED NOT NULL DEFAULT 0,
            sharing_room    ENUM('same_gender','yes','no') NOT NULL DEFAULT 'same_gender',
            notes           TEXT,
            paid_at         TIMESTAMP NULL DEFAULT NULL,
            applied_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_tour_user (future_tour_id, user_id),
            FOREIGN KEY (future_tour_id) REFERENCES future_tours(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS future_tour_application_answers (
            id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            application_id  INT UNSIGNED NOT NULL,
            field_id        INT UNSIGNED NOT NULL,
            answer          TEXT,
            FOREIGN KEY (application_id) REFERENCES future_tour_applications(id) ON DELETE CASCADE,
            FOREIGN KEY (field_id) REFERENCES future_tour_custom_fields(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}
