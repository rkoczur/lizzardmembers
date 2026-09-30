<?php
/**
 * Telekocsi-szervező: túránként egy egyedi linkkel elérhető tábla,
 * ahol a jelentkezők sofőrként helyet kínálnak, utasként helyet foglalnak.
 * Azonosítás: a túrára jelentkezéskor megadott e-mail cím.
 */

const CARPOOL_MAX_SEATS = 8;

function ensureCarpoolSchema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS carpools (
        id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        future_tour_id INT UNSIGNED NOT NULL,
        token          CHAR(32) NOT NULL,
        created_by     INT UNSIGNED DEFAULT NULL,
        created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_carpool_tour (future_tour_id),
        UNIQUE KEY uq_carpool_token (token),
        FOREIGN KEY (future_tour_id) REFERENCES future_tours(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS carpool_drivers (
        id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        carpool_id     INT UNSIGNED NOT NULL,
        application_id INT UNSIGNED NOT NULL,
        seats          TINYINT UNSIGNED NOT NULL DEFAULT 1,
        phone          VARCHAR(50)  NOT NULL,
        contact_extra  VARCHAR(255) DEFAULT NULL,
        departure      VARCHAR(255) NOT NULL,
        route          TEXT,
        departure_time VARCHAR(100) DEFAULT NULL,
        created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_carpool_driver_app (application_id),
        FOREIGN KEY (carpool_id) REFERENCES carpools(id) ON DELETE CASCADE,
        FOREIGN KEY (application_id) REFERENCES future_tour_applications(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS carpool_passengers (
        id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        driver_id      INT UNSIGNED NOT NULL,
        application_id INT UNSIGNED NOT NULL,
        created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_carpool_passenger_app (application_id),
        FOREIGN KEY (driver_id) REFERENCES carpool_drivers(id) ON DELETE CASCADE,
        FOREIGN KEY (application_id) REFERENCES future_tour_applications(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function getCarpoolByTour(PDO $pdo, int $tourId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM carpools WHERE future_tour_id = ? LIMIT 1");
    $stmt->execute([$tourId]);
    return $stmt->fetch() ?: null;
}

function getCarpoolByToken(PDO $pdo, string $token): ?array {
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
    $stmt = $pdo->prepare("
        SELECT cp.*, ft.name AS tour_name, ft.start_date, ft.num_days, ft.status AS tour_status
        FROM carpools cp
        JOIN future_tours ft ON ft.id = cp.future_tour_id
        WHERE cp.token = ? LIMIT 1
    ");
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

/** Létrehozza (vagy visszaadja a meglévő) telekocsi-szervezőt a túrához. */
function createCarpool(PDO $pdo, int $tourId, ?int $userId): array {
    $existing = getCarpoolByTour($pdo, $tourId);
    if ($existing) return $existing;
    $pdo->prepare("INSERT INTO carpools (future_tour_id, token, created_by) VALUES (?, ?, ?)")
        ->execute([$tourId, bin2hex(random_bytes(16)), $userId]);
    return getCarpoolByTour($pdo, $tourId);
}

function carpoolUrl(array $carpool): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . BASE_URL . '/public/telekocsi.php?t=' . $carpool['token'];
}

/** A túrára szóló (nem lemondott) jelentkezés e-mail alapján — tag vagy vendég. */
function findCarpoolApplicationByEmail(PDO $pdo, int $tourId, string $email): ?array {
    $stmt = $pdo->prepare("
        SELECT fta.id FROM future_tour_applications fta
        LEFT JOIN users u ON u.id = fta.user_id
        WHERE fta.future_tour_id = ? AND fta.status != 'cancelled'
          AND LOWER(COALESCE(u.email, fta.guest_email)) = LOWER(?)
        ORDER BY fta.id ASC LIMIT 1
    ");
    $stmt->execute([$tourId, trim($email)]);
    $id = $stmt->fetchColumn();
    return $id ? getCarpoolApplication($pdo, $tourId, (int)$id) : null;
}

function findCarpoolApplicationByUser(PDO $pdo, int $tourId, int $userId): ?array {
    $stmt = $pdo->prepare("SELECT id FROM future_tour_applications
                           WHERE future_tour_id = ? AND user_id = ? AND status != 'cancelled' LIMIT 1");
    $stmt->execute([$tourId, $userId]);
    $id = $stmt->fetchColumn();
    return $id ? getCarpoolApplication($pdo, $tourId, (int)$id) : null;
}

/** Egy jelentkezés név/e-mail/telefon/indulási hely adatai (tag vagy vendég egységesen). */
function getCarpoolApplication(PDO $pdo, int $tourId, int $appId): ?array {
    $stmt = $pdo->prepare("
        SELECT fta.id, fta.status, fta.departure_city,
               COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.lastname, u.firstname)), ''), fta.guest_name, '') AS name,
               COALESCE(u.email, fta.guest_email, '') AS email,
               COALESCE(NULLIF(u.phone, ''), fta.guest_phone) AS phone
        FROM future_tour_applications fta
        LEFT JOIN users u ON u.id = fta.user_id
        WHERE fta.id = ? AND fta.future_tour_id = ? AND fta.status != 'cancelled'
        LIMIT 1
    ");
    $stmt->execute([$appId, $tourId]);
    return $stmt->fetch() ?: null;
}

/** Sofőrök a foglalt helyekkel (utaslistával) együtt, indulási hely szerint rendezve. */
function getCarpoolDrivers(PDO $pdo, int $carpoolId): array {
    $stmt = $pdo->prepare("
        SELECT d.*,
               COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.lastname, u.firstname)), ''), fta.guest_name, '') AS name,
               COALESCE(u.email, fta.guest_email, '') AS email
        FROM carpool_drivers d
        JOIN future_tour_applications fta ON fta.id = d.application_id
        LEFT JOIN users u ON u.id = fta.user_id
        WHERE d.carpool_id = ? AND fta.status != 'cancelled'
        ORDER BY d.departure ASC, d.id ASC
    ");
    $stmt->execute([$carpoolId]);
    $drivers = $stmt->fetchAll();

    $pStmt = $pdo->prepare("
        SELECT p.driver_id, p.application_id,
               COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.lastname, u.firstname)), ''), fta.guest_name, '') AS name,
               COALESCE(u.email, fta.guest_email, '') AS email,
               COALESCE(NULLIF(u.phone, ''), fta.guest_phone) AS phone
        FROM carpool_passengers p
        JOIN carpool_drivers d ON d.id = p.driver_id
        JOIN future_tour_applications fta ON fta.id = p.application_id
        LEFT JOIN users u ON u.id = fta.user_id
        WHERE d.carpool_id = ? AND fta.status != 'cancelled'
        ORDER BY p.id ASC
    ");
    $pStmt->execute([$carpoolId]);
    $byDriver = [];
    foreach ($pStmt->fetchAll() as $p) $byDriver[$p['driver_id']][] = $p;

    foreach ($drivers as &$d) {
        $d['passengers'] = $byDriver[$d['id']] ?? [];
        $d['free']       = max(0, (int)$d['seats'] - count($d['passengers']));
    }
    return $drivers;
}

/** A jelentkező szerepe: ['role' => 'driver'|'passenger'|null, 'driver_id' => ?int]. */
function getCarpoolRole(array $drivers, int $appId): array {
    foreach ($drivers as $d) {
        if ((int)$d['application_id'] === $appId) return ['role' => 'driver', 'driver_id' => (int)$d['id']];
        foreach ($d['passengers'] as $p) {
            if ((int)$p['application_id'] === $appId) return ['role' => 'passenger', 'driver_id' => (int)$d['id']];
        }
    }
    return ['role' => null, 'driver_id' => null];
}

/** A munkamenetben azonosított jelentkezés az adott tokenhez. */
function carpoolSessionAppId(string $token): int {
    return (int)($_SESSION['carpool'][$token] ?? 0);
}
