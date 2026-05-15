<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tours-schema.php';
requireAdmin();

$pdo = getDb();
ensureToursSchema($pdo);

$tours = $pdo->query("
    SELECT tour_code, name, country, region, tour_date, days, accommodation,
           tour_type, sub_type, total_km, alpine_km, total_elevation, alpine_elevation,
           tour_hours, multi_day_type, camping_nights_fixed,
           boat_portages, guest_count, points, mtsz_points
    FROM tours
    ORDER BY tour_date DESC, created_at DESC
")->fetchAll();

$filename = 'turak_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'Kód',
    'Elnevezés',
    'Ország',
    'Tájegység',
    'Dátum (ÉÉÉÉ-HH-NN)',
    'Napok',
    'Szállás (sator/turistahaz/apartman/hotel)',
    'Túramód (gyalogos/kerekparos/vizi/si/barlangi/munka)',
    'Altípus',
    'Km',
    'Magashegyi km',
    'Szintemelkedés (m)',
    'Magashegyi szintemelkedés (m)',
    'Túraidő (óra)',
    'Többnapos típusa (csillag/vandor)',
    'Eltöltött éjszakák',
    'Hajóátemelések',
    'Vendég résztvevők',
    'Lizzardier pont',
    'MTSZ pont',
], ';');

foreach ($tours as $t) {
    fputcsv($out, [
        $t['tour_code']             ?? '',
        $t['name']                  ?? '',
        $t['country']               ?? '',
        $t['region']                ?? '',
        $t['tour_date']             ?? '',
        (int)$t['days'],
        $t['accommodation']         ?? '',
        $t['tour_type']             ?? 'gyalogos',
        $t['sub_type']              ?? '',
        $t['total_km'] !== null     ? (float)$t['total_km']          : '',
        $t['alpine_km'] !== null    ? (float)$t['alpine_km']         : '',
        $t['total_elevation'] !== null ? (int)$t['total_elevation']  : '',
        $t['alpine_elevation'] !== null ? (int)$t['alpine_elevation']: '',
        $t['tour_hours'] !== null   ? (float)$t['tour_hours']        : '',
        $t['multi_day_type']        ?? '',
        (int)$t['camping_nights_fixed'],
        (int)$t['boat_portages'],
        (int)$t['guest_count'],
        (int)$t['points'],
        (int)$t['mtsz_points'],
    ], ';');
}

fclose($out);
exit;
