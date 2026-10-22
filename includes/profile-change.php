<?php
/**
 * Lakcím / telefonszám módosítási kérelmek.
 * A tag a profilján nem írhatja át közvetlenül ezeket az adatokat — admin hagyja jóvá.
 * Tagonként legfeljebb egy függő kérelem van; újabb módosítás felülírja.
 */

const PROFILE_CHANGE_FIELDS = [
    'zipcode' => 'Irányítószám',
    'city'    => 'Város',
    'address' => 'Cím',
    'phone'   => 'Telefonszám',
];

function ensureProfileChangeSchema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS profile_change_requests (
            id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id      INT UNSIGNED NOT NULL,
            zipcode      VARCHAR(20)  DEFAULT NULL,
            city         VARCHAR(100) DEFAULT NULL,
            address      VARCHAR(255) DEFAULT NULL,
            phone        VARCHAR(30)  DEFAULT NULL,
            status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_at  DATETIME DEFAULT NULL,
            reviewed_by  INT UNSIGNED DEFAULT NULL,
            KEY idx_user_status (user_id, status),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function getPendingProfileChange(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM profile_change_requests WHERE user_id = ? AND status = 'pending' LIMIT 1");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

/** Igaz, ha a kért értékek bármelyike eltér a tag jelenlegi adataitól. */
function profileChangeDiffers(array $user, array $requested): bool
{
    foreach (array_keys(PROFILE_CHANGE_FIELDS) as $key) {
        if ((string)($user[$key] ?? '') !== (string)($requested[$key] ?? '')) {
            return true;
        }
    }
    return false;
}

/**
 * Függő kérelem mentése (létrehozás vagy felülírás).
 * Ha a kért adat megegyezik a jelenlegivel, a függő kérelem törlődik.
 * Visszatérés: true, ha függő kérelem maradt.
 */
function saveProfileChangeRequest(PDO $pdo, array $user, array $requested): bool
{
    $userId  = (int)$user['id'];
    $pending = getPendingProfileChange($pdo, $userId);

    if (!profileChangeDiffers($user, $requested)) {
        if ($pending) {
            $pdo->prepare("DELETE FROM profile_change_requests WHERE id = ?")->execute([$pending['id']]);
        }
        return false;
    }

    $values = [];
    foreach (array_keys(PROFILE_CHANGE_FIELDS) as $key) {
        $values[] = ($requested[$key] ?? '') !== '' ? $requested[$key] : null;
    }

    if ($pending) {
        $pdo->prepare("UPDATE profile_change_requests SET zipcode=?, city=?, address=?, phone=?, created_at=NOW() WHERE id=?")
            ->execute([...$values, $pending['id']]);
    } else {
        $pdo->prepare("INSERT INTO profile_change_requests (zipcode, city, address, phone, user_id) VALUES (?, ?, ?, ?, ?)")
            ->execute([...$values, $userId]);
    }
    return true;
}

function countPendingProfileChanges(PDO $pdo): int
{
    return (int)$pdo->query("SELECT COUNT(*) FROM profile_change_requests WHERE status = 'pending'")->fetchColumn();
}

/** Függő kérelmek a jelenlegi adatokkal együtt (admin lista). */
function getPendingProfileChanges(PDO $pdo): array
{
    return $pdo->query("
        SELECT r.*, u.firstname, u.lastname, u.email,
               u.zipcode AS cur_zipcode, u.city AS cur_city, u.address AS cur_address, u.phone AS cur_phone
        FROM profile_change_requests r
        JOIN users u ON u.id = r.user_id
        WHERE r.status = 'pending'
        ORDER BY r.created_at
    ")->fetchAll();
}
