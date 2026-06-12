<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
if (!canManageFinances()) { header('Location: ' . BASE_URL . '/admin/index.php'); exit; }

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="konyveles_import_sablon.csv"');
header('Cache-Control: max-age=0');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'Dátum (ÉÉÉÉ-HH-NN)',
    'Típus (bevetel/kiadas)',
    'Kategória',
    'Leírás',
    'Esemény',
    'Partner',
    'Összeg',
    'Számla',
    'Számlaszám',
], ';');

fputcsv($out, [
    '2026-01-15',
    'bevetel',
    'Tagdíj',
    '2026. évi tagdíj',
    '',
    'Kovács János',
    '15000',
    'Bankszámla',
    '',
], ';');

fputcsv($out, [
    '2026-02-03',
    'kiadas',
    'Felszerelés',
    'Kötél vásárlás',
    'Mátra túra (2026.03.10)',
    'Decathlon Kft.',
    '24990',
    'Készpénz',
    '2026/0042',
], ';');

fputcsv($out, [
    '2026-03-20',
    'bevetel',
    'Részvételi díj',
    'Alpok túra befizetés',
    'Alpok túra (2026.07.10)',
    'Nagy Andrea',
    '45000',
    'Bankszámla',
    '',
], ';');

fclose($out);
exit;
