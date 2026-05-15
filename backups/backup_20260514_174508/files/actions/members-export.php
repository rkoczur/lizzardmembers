<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$pdo = getDb();
$members = $pdo->query("
    SELECT lastname, firstname, username, email, phone,
           dateofbirth, member_since, last_payment,
           level, points, city, zipcode, address, tshirt_size,
           emergency_name, emergency_relation, emergency_phone, role
    FROM users
    ORDER BY role DESC, lastname, firstname
")->fetchAll();

$filename = 'tagok_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$out = fopen('php://output', 'w');

// UTF-8 BOM so Excel opens it correctly
fputs($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'Vezetéknév','Keresztnév','Felhasználónév','E-mail','Telefonszám',
    'Születési dátum','Tagság kezdete','Utolsó fizetés',
    'Szint','Pontok','Tagság státusza',
    'Irányítószám','Város','Cím','Pólóméret',
    'Vészhelyzet – Név','Vészhelyzet – Kapcsolat','Vészhelyzet – Telefon',
    'Szerepkör',
], ';');

foreach ($members as $m) {
    fputcsv($out, [
        $m['lastname']           ?? '',
        $m['firstname']          ?? '',
        $m['username']           ?? '',
        $m['email']              ?? '',
        $m['phone']              ?? '',
        $m['dateofbirth']        ?? '',
        $m['member_since']       ?? '',
        $m['last_payment']       ?? '',
        getLevelLabel((int)$m['level']),
        (int)$m['points'],
        getMemberStatusLabel(getMemberStatus($m['last_payment'])),
        $m['zipcode']            ?? '',
        $m['city']               ?? '',
        $m['address']            ?? '',
        $m['tshirt_size']        ?? '',
        $m['emergency_name']     ?? '',
        $m['emergency_relation'] ?? '',
        $m['emergency_phone']    ?? '',
        $m['role'] === 'admin' ? 'Admin' : 'Tag',
    ], ';');
}

fclose($out);
exit;
