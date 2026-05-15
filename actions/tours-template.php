<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="turak_import_sablon.csv"');
header('Cache-Control: max-age=0');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'Kód',
    'Elnevezés',
    'Útvonal',
    'Országkód',
    'Tájegység',
    'Dátum (ÉÉÉÉ-HH-NN)',
    'Napok',
    'Szállás (sator/turistahaz/apartman/hotel)',
    'Túramód (gyalogos/kerekparos/vizi/si/barlangi/munka)',
    'Altípus (gyalogos: normal/tajekozodasi | kerekparos: mout/terep | vizi: folyasirany/allovi/szemben | barlangi: kiepitett/kiepitetlen)',
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
], ';');

fputcsv($out, [
    '',               // Kód — üresen hagyva: automatikusan generált
    'Mátra körüljáró',
    'Gyöngyös – Mátraháza – Parádfürdő',
    'HU',
    'Mátra',
    '2025-04-20',
    '2',
    'turistahaz',
    'gyalogos',
    'normal',
    '24.5',
    '',
    '800',
    '',
    '',
    'vandor',
    '2',              // 2 éj vándortúra → mozgótábor +12 pt
    '0',
    '1',
    '5',
], ';');

fputcsv($out, [
    '5K',             // Kód — manuálisan megadva
    '',
    '',
    'AT',
    'Alpok',
    '2025-07-10',
    '5',
    'sator',
    'kerekparos',
    'terep',
    '120',
    '30',
    '1200',
    '800',
    '',
    'vandor',
    '5',              // 5 éj vándortúra → mozgótábor +30 pt
    '0',
    '0',
    '12',
], ';');

fclose($out);
exit;
