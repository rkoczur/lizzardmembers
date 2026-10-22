<?php
/** Önálló telekocsi-szervező: kód nélkül a nyitóoldal, kóddal az adott szervező. */
require_once __DIR__ . '/lib/core.php';
require_once __DIR__ . '/lib/boards.php';
require_once __DIR__ . '/lib/rides.php';

$pdo   = tkDb();
$raw   = (string)($_GET['k'] ?? '');
$code  = tkNormalizeCode($raw);
$board = tkGetBoard($pdo, $code);
$flash = tkTakeFlash();

if ($raw !== '' && !$board) {
    tkFlash('error', 'Nincs ilyen kódú telekocsi-szervező: ' . $raw);
    tkRedirect(tkBase() . '/');
}
// Kötőjeles vagy kisbetűs kód → kanonikus URL
if ($board && $raw !== $code) tkRedirect(tkBoardUrl($code));

$post = tkBase() . '/action.php';
$view = $board ? 'board' : 'landing';
if ($board) {
    $me     = tkCurrentPerson($pdo, $board);
    $people  = tkGetPeople($pdo, (int)$board['id']);
    $drivers = tkGetDrivers($pdo, (int)$board['id']);
    $role    = $me ? tkGetRole($drivers, (int)$me['id']) : ['role' => null, 'car' => null];
    $myCar   = $role['car'];
    $old     = $_SESSION['tk_old'] ?? [];
    unset($_SESSION['tk_old']);
    // Sofőr űrlap: hibás beküldés → előző adatok, egyébként a saját autó
    $isDriver = $role['role'] === 'driver';
    $form = [];
    foreach (['seats' => 3, 'phone' => '', 'contact_extra' => '', 'departure' => '', 'departure_time' => '', 'route' => ''] as $key => $default) {
        $form[$key] = $old[$key] ?? ($isDriver ? $myCar[$key] : $default);
    }
    $openPanel = $old ? 'driver' : (string)$role['role'];
    $freeTotal = array_sum(array_column($drivers, 'free'));
}
include __DIR__ . '/views/layout.php';
