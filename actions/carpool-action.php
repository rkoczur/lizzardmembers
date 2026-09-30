<?php
/**
 * Telekocsi-szervező publikus műveletei (bejelentkezés nem kell, az azonosítás e-maillel történik).
 * op: identify | forget | driver_save | driver_remove | book | unbook
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
require_once __DIR__ . '/../includes/carpool.php';
verifyCsrf();

$pdo = getDb();
ensureFutureToursSchema($pdo);
ensureCarpoolSchema($pdo);

$token   = (string)($_POST['t'] ?? '');
$carpool = getCarpoolByToken($pdo, $token);
if (!$carpool) {
    header('Location: ' . BASE_URL . '/public/turanyptar.php');
    exit;
}
$back = BASE_URL . '/public/telekocsi.php?t=' . $token;
$op   = (string)($_POST['op'] ?? '');

function carpoolDone(string $back, string $type, string $msg): never {
    flash($type, $msg);
    header('Location: ' . $back);
    exit;
}

if ($op === 'identify') {
    $email = trim((string)($_POST['email'] ?? ''));
    $app   = filter_var($email, FILTER_VALIDATE_EMAIL)
        ? findCarpoolApplicationByEmail($pdo, (int)$carpool['future_tour_id'], $email) : null;
    if (!$app) {
        $_SESSION['carpool_unknown'][$token] = $email;
        header('Location: ' . $back);
        exit;
    }
    unset($_SESSION['carpool_unknown'][$token]);
    $_SESSION['carpool'][$token] = (int)$app['id'];
    carpoolDone($back, 'success', 'Szia, ' . $app['name'] . '! Válaszd ki, hogyan utazol.');
}

if ($op === 'forget') {
    unset($_SESSION['carpool'][$token]);
    $_SESSION['carpool_forget'][$token] = true;
    header('Location: ' . $back);
    exit;
}

// Innentől azonosított résztvevő kell
$app = getCarpoolApplication($pdo, (int)$carpool['future_tour_id'], carpoolSessionAppId($token));
if (!$app) {
    unset($_SESSION['carpool'][$token]);
    carpoolDone($back, 'error', 'Add meg újra az e-mail címedet.');
}
if ($carpool['tour_status'] === 'cancelled') {
    carpoolDone($back, 'error', 'A túra elmaradt, a telekocsi-szervező csak megtekinthető.');
}
$appId   = (int)$app['id'];
$drivers = getCarpoolDrivers($pdo, (int)$carpool['id']);
$role    = getCarpoolRole($drivers, $appId);

match ($op) {
    'driver_save'   => carpoolDriverSave($pdo, $carpool, $appId, $drivers, $role, $back),
    'driver_remove' => carpoolDriverRemove($pdo, $appId, $back),
    'book'          => carpoolBook($pdo, $carpool, $appId, $role, $back),
    'unbook'        => carpoolUnbook($pdo, $appId, $back),
    default         => carpoolDone($back, 'error', 'Ismeretlen művelet.'),
};

function carpoolDriverSave(PDO $pdo, array $carpool, int $appId, array $drivers, array $role, string $back): never {
    $seats     = (int)($_POST['seats'] ?? 0);
    $phone     = trim((string)($_POST['phone'] ?? ''));
    $extra     = trim((string)($_POST['contact_extra'] ?? ''));
    $departure = trim((string)($_POST['departure'] ?? ''));
    $time      = trim((string)($_POST['departure_time'] ?? ''));
    $route     = trim((string)($_POST['route'] ?? ''));

    $_SESSION['carpool_old'] = $_POST;
    if ($seats < 1 || $seats > CARPOOL_MAX_SEATS) carpoolDone($back, 'error', 'A szabad helyek száma 1 és ' . CARPOOL_MAX_SEATS . ' között lehet.');
    if ($phone === '' && $extra === '')          carpoolDone($back, 'error', 'Adj meg legalább egy elérhetőséget (lehetőleg telefonszámot).');
    if ($departure === '')                        carpoolDone($back, 'error', 'Add meg, honnan indulsz.');

    if ($role['role'] === 'driver') {
        $booked = 0;
        foreach ($drivers as $d) if ((int)$d['id'] === $role['driver_id']) $booked = count($d['passengers']);
        if ($seats < $booked) carpoolDone($back, 'error', "Már {$booked} utas foglalt nálad — ennél kevesebb helyet nem adhatsz meg.");
        $pdo->prepare("UPDATE carpool_drivers SET seats = ?, phone = ?, contact_extra = ?, departure = ?, departure_time = ?, route = ? WHERE id = ?")
            ->execute([$seats, $phone, $extra ?: null, $departure, $time ?: null, $route ?: null, $role['driver_id']]);
        unset($_SESSION['carpool_old']);
        carpoolDone($back, 'success', 'Az autód adatai frissültek.');
    }

    // Aki eddig utas volt, sofőrként már nem foglal helyet máshol
    $pdo->prepare("DELETE FROM carpool_passengers WHERE application_id = ?")->execute([$appId]);
    $pdo->prepare("INSERT INTO carpool_drivers (carpool_id, application_id, seats, phone, contact_extra, departure, departure_time, route)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$carpool['id'], $appId, $seats, $phone, $extra ?: null, $departure, $time ?: null, $route ?: null]);
    unset($_SESSION['carpool_old']);
    carpoolDone($back, 'success', 'Felkerültél a sofőrök közé — az utasok már foglalhatnak nálad.');
}

function carpoolDriverRemove(PDO $pdo, int $appId, string $back): never {
    $pdo->prepare("DELETE FROM carpool_drivers WHERE application_id = ?")->execute([$appId]);
    carpoolDone($back, 'success', 'Már nem szerepelsz sofőrként. Az utasaid foglalása törlődött.');
}

function carpoolBook(PDO $pdo, array $carpool, int $appId, array $role, string $back): never {
    $driverId = (int)($_POST['driver_id'] ?? 0);
    if ($role['role'] === 'driver') carpoolDone($back, 'error', 'Sofőrként nem foglalhatsz helyet más autójában.');
    if ($role['driver_id'] === $driverId) carpoolDone($back, 'success', 'Ebben az autóban már van helyed.');

    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT seats FROM carpool_drivers WHERE id = ? AND carpool_id = ? FOR UPDATE");
    $stmt->execute([$driverId, $carpool['id']]);
    $seats = $stmt->fetchColumn();
    $count = $pdo->prepare("SELECT COUNT(*) FROM carpool_passengers WHERE driver_id = ?");
    $count->execute([$driverId]);
    if ($seats === false || (int)$count->fetchColumn() >= (int)$seats) {
        $pdo->rollBack();
        carpoolDone($back, 'error', 'Ez az autó időközben megtelt. Válassz másikat!');
    }
    $pdo->prepare("DELETE FROM carpool_passengers WHERE application_id = ?")->execute([$appId]);
    $pdo->prepare("INSERT INTO carpool_passengers (driver_id, application_id) VALUES (?, ?)")->execute([$driverId, $appId]);
    $pdo->commit();
    carpoolDone($back, 'success', 'Lefoglaltad a helyed! Egyeztess a sofőrrel az indulásról.');
}

function carpoolUnbook(PDO $pdo, int $appId, string $back): never {
    $pdo->prepare("DELETE FROM carpool_passengers WHERE application_id = ?")->execute([$appId]);
    carpoolDone($back, 'success', 'A foglalásodat töröltük.');
}
