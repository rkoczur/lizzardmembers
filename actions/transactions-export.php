<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/bookkeeping-schema.php';
requireLogin();
if (!canManageFinances()) { header('Location: ' . BASE_URL . '/admin/index.php'); exit; }

$pdo = getDb();
ensureBookkeepingSchema($pdo);

// Dátumtartomány + opcionális típusszűrő
$from = trim($_GET['date_from'] ?? '');
$to   = trim($_GET['date_to']   ?? '');
$type = in_array($_GET['type'] ?? '', ['income', 'expense'], true) ? $_GET['type'] : '';

$fromValid = $from !== '' && strtotime($from) !== false;
$toValid   = $to   !== '' && strtotime($to)   !== false;

$where = []; $params = [];
if ($fromValid) { $where[] = 'tx_date >= ?'; $params[] = date('Y-m-d', strtotime($from)); }
if ($toValid)   { $where[] = 'tx_date <= ?'; $params[] = date('Y-m-d', strtotime($to)); }
if ($type !== '') { $where[] = 'tx_type = ?'; $params[] = $type; }
$whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare("SELECT * FROM transactions $whereClause ORDER BY tx_date ASC, id ASC");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Fájlnév a tartomány alapján
$fnFrom = $fromValid ? date('Ymd', strtotime($from)) : 'kezdet';
$fnTo   = $toValid   ? date('Ymd', strtotime($to))   : date('Ymd');

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="konyveles_' . $fnFrom . '_' . $fnTo . '.csv"');
header('Cache-Control: max-age=0');

$out = fopen('php://output', 'w');
fputs($out, "\xEF\xBB\xBF");

// A sablonnal megegyező oszlopok → az export visszaimportálható
fputcsv($out, [
    'Dátum (ÉÉÉÉ-HH-NN)', 'Típus (bevetel/kiadas)', 'Kategória', 'Leírás',
    'Esemény', 'Partner', 'Összeg', 'Számla', 'Számlaszám',
], ';');

$totalIncome = 0.0; $totalExpense = 0.0;
foreach ($rows as $r) {
    $amount = (float)$r['amount'];
    if ($r['tx_type'] === 'income') $totalIncome += $amount; else $totalExpense += $amount;
    fputcsv($out, [
        $r['tx_date'],
        $r['tx_type'] === 'income' ? 'bevetel' : 'kiadas',
        $r['category'],
        $r['description'],
        $r['event_label'] ?? '',
        $r['partner'],
        rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.'),
        $r['account'],
        $r['invoice_number'] ?? '',
    ], ';');
}

// Összesítő sorok
fputcsv($out, [], ';');
fputcsv($out, ['Összes bevétel', '', '', '', '', '', number_format($totalIncome, 0, '.', ''), '', ''], ';');
fputcsv($out, ['Összes kiadás',  '', '', '', '', '', number_format($totalExpense, 0, '.', ''), '', ''], ';');
fputcsv($out, ['Eredmény',       '', '', '', '', '', number_format($totalIncome - $totalExpense, 0, '.', ''), '', ''], ';');

fclose($out);
exit;
