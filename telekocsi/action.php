<?php
/** Az önálló telekocsi-szervező összes POST művelete. Minden ág átirányítással zárul. */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/boards.php';
require_once __DIR__ . '/lib/rides.php';
require_once __DIR__ . '/lib/mailer.php';
require_once __DIR__ . '/lib/notify.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') tkRedirect(tkBase() . '/');
tkVerifyCsrf();

$pdo = tkDb();
$op  = (string)($_POST['op'] ?? '');

/** Név + e-mail ellenőrzése; hiba esetén üzenettel vissza. */
function tkReadIdentity(string $back): array {
    $name  = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    if ($name === '' || mb_strlen($name) > 100) tkFail($back, 'Add meg a neved.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) tkFail($back, 'Érvényes e-mail címet adj meg.');
    return [$name, $email];
}

function tkFail(string $back, string $msg): never {
    tkFlash('error', $msg);
    tkRedirect($back);
}

if ($op === 'create') {
    [$name, $email] = tkReadIdentity(tkBase() . '/');
    $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 150);
    [$board, $person] = tkCreateBoard($pdo, $name, $email, $title);
    tkRemember($board, $person);
    tkFlash('success', 'Elkészült a szervező! A kódja: ' . tkFormatCode($board['code']) . ' — mentsd el a linket.');
    tkRedirect(tkBoardUrl($board['code']));
}

// Innentől egy meglévő szervezőhöz tartozó művelet
$board = tkGetBoard($pdo, tkNormalizeCode((string)($_POST['k'] ?? '')));
if (!$board) tkFail(tkBase() . '/', 'A telekocsi-szervező nem található.');
$back = tkBoardUrl($board['code']);

if ($op === 'join') {
    [$name, $email] = tkReadIdentity($back);
    $person = tkFindPersonByEmail($pdo, (int)$board['id'], $email);
    $msg    = $person ? 'Üdv újra, ' . $person['name'] . '!' : 'Szia, ' . $name . '! Válaszd ki, hogyan utazol.';
    $person = $person ?: tkAddPerson($pdo, (int)$board['id'], $name, $email);
    tkRemember($board, $person);
    tkFlash('success', $msg);
    tkRedirect($back);
}

if ($op === 'forget') {
    tkRemember($board, null);
    tkRedirect($back);
}

// Innentől azonosított résztvevő kell
$me = tkCurrentPerson($pdo, $board);
if (!$me) tkFail($back, 'Előbb csatlakozz a neveddel és e-mail címeddel.');
$role = tkGetRole(tkGetDrivers($pdo, (int)$board['id']), (int)$me['id']);

/** Ha eddig utas volt: a régi autó sofőrje (és a szervező, ha $toOwner) értesítést kap. */
$notifyLeftOld = function (bool $toOwner) use ($pdo, $board, $me, $role): void {
    if ($role['role'] !== 'passenger') return;
    $car = tkFindCar($pdo, (int)$board['id'], (int)$role['car']['id']) ?? $role['car'];
    tkNotify($pdo, $board, 'passenger_left', $car, $me, $toOwner);
};

if ($op === 'driver_save') {
    $in = [
        'seats'          => (int)($_POST['seats'] ?? 0),
        'phone'          => mb_substr(trim((string)($_POST['phone'] ?? '')), 0, 50),
        'contact_extra'  => mb_substr(trim((string)($_POST['contact_extra'] ?? '')), 0, 255),
        'departure'      => mb_substr(trim((string)($_POST['departure'] ?? '')), 0, 255),
        'departure_time' => mb_substr(trim((string)($_POST['departure_time'] ?? '')), 0, 100),
        'route'          => trim((string)($_POST['route'] ?? '')),
    ];
    $error = tkSaveDriver($pdo, $board, $me, $role, $in);
    if ($error) {
        $_SESSION['tk_old'] = $in;
        tkFail($back, $error);
    }
    if ($role['role'] === 'driver') {
        tkFlash('success', 'Az autód adatai frissültek.');
        tkRedirect($back);
    }
    $notifyLeftOld(false);
    $newCar = tkGetRole(tkGetDrivers($pdo, (int)$board['id']), (int)$me['id'])['car'];
    tkNotify($pdo, $board, 'driver_new', $newCar, $me);
    tkFlash('success', 'Felkerültél a sofőrök közé — az utasok már foglalhatnak nálad.');
    tkRedirect($back);
}

if ($op === 'driver_remove') {
    if ($role['role'] !== 'driver') tkRedirect($back);
    $pdo->prepare("DELETE FROM tk_drivers WHERE person_id = ?")->execute([$me['id']]);
    tkNotify($pdo, $board, 'driver_removed', $role['car'], $me);
    tkFlash('success', 'Már nem szerepelsz sofőrként. Az utasaid foglalása törlődött.');
    tkRedirect($back);
}

if ($op === 'book') {
    $driverId = (int)($_POST['driver_id'] ?? 0);
    if ($role['role'] === 'driver') tkFail($back, 'Sofőrként nem foglalhatsz helyet más autójában.');
    if ($role['car'] && (int)$role['car']['id'] === $driverId) tkFail($back, 'Ebben az autóban már van helyed.');
    $error = tkBookSeat($pdo, $board, (int)$me['id'], $driverId);
    if ($error) tkFail($back, $error);
    $notifyLeftOld(false);
    tkNotify($pdo, $board, 'passenger_booked', tkFindCar($pdo, (int)$board['id'], $driverId), $me);
    tkFlash('success', 'Lefoglaltad a helyed! Egyeztess a sofőrrel az indulásról.');
    tkRedirect($back);
}

if ($op === 'unbook') {
    $pdo->prepare("DELETE FROM tk_passengers WHERE person_id = ?")->execute([$me['id']]);
    $notifyLeftOld(true);
    tkFlash('success', 'A foglalásodat töröltük.');
    tkRedirect($back);
}

tkRedirect($back);
