<?php
/** Meghirdetett túra .ics naptárfájlja (Outlook asztali, Apple Naptár stb.) — publikus, mint a tour-detail.php */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
require_once __DIR__ . '/../includes/calendar-invite.php';

$pdo = getDb();
ensureFutureToursSchema($pdo);

$proto      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host       = $_SERVER['HTTP_HOST'] ?? 'localhost';
$absBaseUrl = $proto . '://' . $host . BASE_URL;

$ev = getTourCalendarEvent($pdo, (int)($_GET['id'] ?? 0), $absBaseUrl);
if (!$ev) {
    http_response_code(404);
    exit('A túra nem található.');
}

header('Content-Type: text/calendar; charset=UTF-8');
header('Content-Disposition: attachment; filename="tura-' . $ev['id'] . '.ics"');
echo calendarIcs($ev, preg_replace('/:\d+$/', '', $host));
