<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="tagok_import_sablon.csv"');
header('Cache-Control: max-age=0');

$out = fopen('php://output', 'w');

// UTF-8 BOM so Excel opens it correctly
fputs($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'Vezetéknév',
    'Keresztnév',
    'Felhasználónév',
    'E-mail',
    'Jelszó',
    'Telefonszám',
    'Születési dátum (ÉÉÉÉ-HH-NN)',
    'Tagság kezdete (ÉÉÉÉ-HH-NN)',
    'Utolsó fizetés (ÉÉÉÉ-HH-NN)',
    'Irányítószám',
    'Város',
    'Cím',
    'Pólóméret (XS/S/M/L/XL/XXL/XXXL)',
    'Vészhelyzet – Név',
    'Vészhelyzet – Kapcsolat (szülő/gyermek/testvér/egyéb)',
    'Vészhelyzet – Telefon',
], ';');

// One example row to illustrate the format
fputcsv($out, [
    'Kiss',
    'János',
    'kissjanos',
    'kiss.janos@example.com',
    'titkos123',
    '+36301234567',
    '1985-03-15',
    '2024-01-01',
    '2025-01-01',
    '1234',
    'Budapest',
    'Fő utca 1.',
    'M',
    'Kiss Mária',
    'szülő',
    '+36209876543',
], ';');

fclose($out);
exit;
