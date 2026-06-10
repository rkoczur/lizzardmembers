<?php
function ensurePublicSchema(PDO $pdo): void
{
    // Blog posts (news + tour reports)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS posts (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category    ENUM('hirek','beszamolok') NOT NULL DEFAULT 'hirek',
            title       VARCHAR(255) NOT NULL,
            slug        VARCHAR(255) NOT NULL UNIQUE,
            excerpt     TEXT,
            body        LONGTEXT,
            cover_img   VARCHAR(255) DEFAULT NULL,
            published   TINYINT(1) NOT NULL DEFAULT 0,
            created_by  INT UNSIGNED,
            created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Document archive
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS documents (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category    VARCHAR(100) NOT NULL,
            title       VARCHAR(255) NOT NULL,
            filename    VARCHAR(255) NOT NULL,
            sort_order  INT NOT NULL DEFAULT 0,
            created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Yearly finances
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS finances (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            year        SMALLINT UNSIGNED NOT NULL,
            category    ENUM('income','expense') NOT NULL,
            label       VARCHAR(255) NOT NULL,
            amount      DECIMAL(12,0) NOT NULL DEFAULT 0,
            sort_order  INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Editable static pages
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pages (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug        VARCHAR(100) NOT NULL UNIQUE,
            title       VARCHAR(255) NOT NULL,
            body        LONGTEXT,
            updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // FAQ
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS faq (
            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            question    VARCHAR(500) NOT NULL,
            answer      TEXT NOT NULL,
            sort_order  INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // SEO mezők migrációja meglévő telepítésekhez
    foreach ([
        "ALTER TABLE posts ADD COLUMN cover_alt VARCHAR(255) DEFAULT NULL AFTER cover_img",
        "ALTER TABLE posts ADD COLUMN meta_keywords VARCHAR(500) DEFAULT NULL AFTER excerpt",
        "ALTER TABLE pages ADD COLUMN meta_description VARCHAR(500) DEFAULT NULL AFTER body",
        "ALTER TABLE pages ADD COLUMN meta_keywords VARCHAR(500) DEFAULT NULL AFTER meta_description",
    ] as $sql) {
        try { $pdo->exec($sql); } catch (PDOException) {}
    }

    // Seed default pages if not present
    $defaultPages = [
        ['rolunk',                 'Rólunk',                    ''],
        ['tagsag',                 'Tagság',                    ''],
        ['lizzardier',             'Lizzardier pontverseny',    ''],
        ['mtsz-turanaplo',         'MTSZ túranapló',            ''],
        ['kapcsolat',              'Kapcsolat',                 ''],
        ['reszveteli-feltetelek',  'Részvételi feltételek',     ''],
        ['ado1',                   'Adó 1%',                    ''],
        ['penzugyek',              'Pénzügyek',                 ''],
        ['klubelet',               'Klubélet – események',      ''],
        ['hero-image',             'Hero háttérkép',            ''],
    ];
    $ins = $pdo->prepare("INSERT IGNORE INTO pages (slug, title, body) VALUES (?, ?, ?)");
    foreach ($defaultPages as [$slug, $title, $body]) {
        $ins->execute([$slug, $title, $body]);
    }
}
