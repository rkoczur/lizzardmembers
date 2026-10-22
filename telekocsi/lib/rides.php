<?php
/** Autók és helyfoglalások: lekérdezés és módosító műveletek. */

/** Sofőrök az utaslistával, indulási hely szerint rendezve. */
function tkGetDrivers(PDO $pdo, int $boardId): array {
    $stmt = $pdo->prepare("
        SELECT d.*, p.name, p.email
        FROM tk_drivers d JOIN tk_people p ON p.id = d.person_id
        WHERE d.board_id = ?
        ORDER BY d.departure ASC, d.id ASC
    ");
    $stmt->execute([$boardId]);
    $drivers = $stmt->fetchAll();

    $pStmt = $pdo->prepare("
        SELECT s.driver_id, s.person_id, p.name, p.email
        FROM tk_passengers s
        JOIN tk_drivers d ON d.id = s.driver_id
        JOIN tk_people p ON p.id = s.person_id
        WHERE d.board_id = ?
        ORDER BY s.id ASC
    ");
    $pStmt->execute([$boardId]);
    $byDriver = [];
    foreach ($pStmt->fetchAll() as $s) $byDriver[$s['driver_id']][] = $s;

    foreach ($drivers as &$d) {
        $d['passengers'] = $byDriver[$d['id']] ?? [];
        $d['free']       = max(0, (int)$d['seats'] - count($d['passengers']));
    }
    return $drivers;
}

/** A résztvevő szerepe: ['role' => 'driver'|'passenger'|null, 'car' => ?array]. */
function tkGetRole(array $drivers, int $personId): array {
    foreach ($drivers as $d) {
        if ((int)$d['person_id'] === $personId) return ['role' => 'driver', 'car' => $d];
        foreach ($d['passengers'] as $s) {
            if ((int)$s['person_id'] === $personId) return ['role' => 'passenger', 'car' => $d];
        }
    }
    return ['role' => null, 'car' => null];
}

/** Sofőr adatainak mentése (új vagy meglévő). Hibánál üzenetet ad vissza, sikernél null-t. */
function tkSaveDriver(PDO $pdo, array $board, array $me, array $role, array $in): ?string {
    if ($in['seats'] < 1 || $in['seats'] > TK_MAX_SEATS) return 'A szabad helyek száma 1 és ' . TK_MAX_SEATS . ' között lehet.';
    if ($in['phone'] === '' && $in['contact_extra'] === '') return 'Adj meg legalább egy elérhetőséget (lehetőleg telefonszámot).';
    if ($in['departure'] === '') return 'Add meg, honnan indulsz.';

    $values = [$in['seats'], $in['phone'] ?: null, $in['contact_extra'] ?: null, $in['departure'],
               $in['departure_time'] ?: null, $in['route'] ?: null];

    if ($role['role'] === 'driver') {
        $booked = count($role['car']['passengers']);
        if ($in['seats'] < $booked) return "Már {$booked} utas foglalt nálad — ennél kevesebb helyet nem adhatsz meg.";
        $pdo->prepare("UPDATE tk_drivers SET seats = ?, phone = ?, contact_extra = ?, departure = ?, departure_time = ?, route = ? WHERE id = ?")
            ->execute([...$values, $role['car']['id']]);
        return null;
    }
    // Aki eddig utas volt, sofőrként már nem foglal helyet máshol
    $pdo->prepare("DELETE FROM tk_passengers WHERE person_id = ?")->execute([$me['id']]);
    $pdo->prepare("INSERT INTO tk_drivers (seats, phone, contact_extra, departure, departure_time, route, board_id, person_id)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([...$values, $board['id'], $me['id']]);
    return null;
}

/** Hely foglalása egy autóban (átülés esetén a régi helyet felszabadítja). Hibánál üzenet. */
function tkBookSeat(PDO $pdo, array $board, int $personId, int $driverId): ?string {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT seats FROM tk_drivers WHERE id = ? AND board_id = ? FOR UPDATE");
    $stmt->execute([$driverId, $board['id']]);
    $seats = $stmt->fetchColumn();
    $count = $pdo->prepare("SELECT COUNT(*) FROM tk_passengers WHERE driver_id = ?");
    $count->execute([$driverId]);
    if ($seats === false || (int)$count->fetchColumn() >= (int)$seats) {
        $pdo->rollBack();
        return 'Ez az autó időközben megtelt. Válassz másikat!';
    }
    $pdo->prepare("DELETE FROM tk_passengers WHERE person_id = ?")->execute([$personId]);
    $pdo->prepare("INSERT INTO tk_passengers (driver_id, person_id) VALUES (?, ?)")->execute([$driverId, $personId]);
    $pdo->commit();
    return null;
}
