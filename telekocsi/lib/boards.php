<?php
/** Szervezők (táblák) és résztvevők: séma, létrehozás, azonosítás. */

function tkEnsureSchema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tk_boards (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code       CHAR(8) NOT NULL,
        title      VARCHAR(150) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_tk_board_code (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS tk_people (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        board_id   INT UNSIGNED NOT NULL,
        name       VARCHAR(100) NOT NULL,
        email      VARCHAR(190) NOT NULL,
        secret     CHAR(32) NOT NULL,
        is_owner   TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_tk_people_email (board_id, email),
        UNIQUE KEY uq_tk_people_secret (secret),
        FOREIGN KEY (board_id) REFERENCES tk_boards(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS tk_drivers (
        id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        board_id       INT UNSIGNED NOT NULL,
        person_id      INT UNSIGNED NOT NULL,
        seats          TINYINT UNSIGNED NOT NULL DEFAULT 1,
        phone          VARCHAR(50) DEFAULT NULL,
        contact_extra  VARCHAR(255) DEFAULT NULL,
        departure      VARCHAR(255) NOT NULL,
        departure_time VARCHAR(100) DEFAULT NULL,
        route          TEXT,
        created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_tk_driver_person (person_id),
        FOREIGN KEY (board_id) REFERENCES tk_boards(id) ON DELETE CASCADE,
        FOREIGN KEY (person_id) REFERENCES tk_people(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS tk_passengers (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        driver_id  INT UNSIGNED NOT NULL,
        person_id  INT UNSIGNED NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_tk_passenger_person (person_id),
        FOREIGN KEY (driver_id) REFERENCES tk_drivers(id) ON DELETE CASCADE,
        FOREIGN KEY (person_id) REFERENCES tk_people(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function tkGenerateCode(PDO $pdo): string {
    $chars = TK_CODE_CHARS;
    do {
        $code = '';
        for ($i = 0; $i < TK_CODE_LEN; $i++) $code .= $chars[random_int(0, strlen($chars) - 1)];
        $stmt = $pdo->prepare("SELECT 1 FROM tk_boards WHERE code = ?");
        $stmt->execute([$code]);
    } while ($stmt->fetchColumn());
    return $code;
}

function tkGetBoard(PDO $pdo, string $code): ?array {
    if ($code === '') return null;
    $stmt = $pdo->prepare("SELECT * FROM tk_boards WHERE code = ? LIMIT 1");
    $stmt->execute([$code]);
    return $stmt->fetch() ?: null;
}

/** Új szervező; a létrehozó az első (tulajdonos) résztvevő. Visszaadja: [board, person]. */
function tkCreateBoard(PDO $pdo, string $name, string $email, string $title): array {
    $pdo->beginTransaction();
    $code = tkGenerateCode($pdo);
    $pdo->prepare("INSERT INTO tk_boards (code, title) VALUES (?, ?)")->execute([$code, $title ?: null]);
    $boardId = (int)$pdo->lastInsertId();
    $person  = tkAddPerson($pdo, $boardId, $name, $email, true);
    $pdo->commit();
    return [tkGetBoard($pdo, $code), $person];
}

function tkAddPerson(PDO $pdo, int $boardId, string $name, string $email, bool $owner = false): array {
    $pdo->prepare("INSERT INTO tk_people (board_id, name, email, secret, is_owner) VALUES (?, ?, ?, ?, ?)")
        ->execute([$boardId, $name, mb_strtolower($email), bin2hex(random_bytes(16)), (int)$owner]);
    return tkGetPerson($pdo, $boardId, (int)$pdo->lastInsertId());
}

function tkGetPerson(PDO $pdo, int $boardId, int $personId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM tk_people WHERE id = ? AND board_id = ? LIMIT 1");
    $stmt->execute([$personId, $boardId]);
    return $stmt->fetch() ?: null;
}

function tkFindPersonByEmail(PDO $pdo, int $boardId, string $email): ?array {
    $stmt = $pdo->prepare("SELECT * FROM tk_people WHERE board_id = ? AND email = ? LIMIT 1");
    $stmt->execute([$boardId, mb_strtolower(trim($email))]);
    return $stmt->fetch() ?: null;
}

/** Megjegyzett résztvevő: a táblánként külön sütiben tárolt titkos azonosító alapján. */
function tkCurrentPerson(PDO $pdo, array $board): ?array {
    $secret = (string)($_COOKIE['tk_' . $board['code']] ?? '');
    if (!preg_match('/^[a-f0-9]{32}$/', $secret)) return null;
    $stmt = $pdo->prepare("SELECT * FROM tk_people WHERE secret = ? AND board_id = ? LIMIT 1");
    $stmt->execute([$secret, $board['id']]);
    return $stmt->fetch() ?: null;
}

function tkRemember(array $board, ?array $person): void {
    $expires = $person ? time() + TK_COOKIE_DAYS * 86400 : time() - 3600;
    setcookie('tk_' . $board['code'], $person['secret'] ?? '', [
        'expires' => $expires, 'path' => tkBase(), 'httponly' => true, 'samesite' => 'Lax',
    ]);
}

function tkGetPeople(PDO $pdo, int $boardId): array {
    $stmt = $pdo->prepare("SELECT * FROM tk_people WHERE board_id = ? ORDER BY is_owner DESC, name ASC");
    $stmt->execute([$boardId]);
    return $stmt->fetchAll();
}
