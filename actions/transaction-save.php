<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/bookkeeping-schema.php';
requireLogin();
if (!canManageFinances()) { flash('error', 'Nincs jogosultságod ehhez.'); header('Location: ' . BASE_URL . '/admin/index.php'); exit; }
verifyCsrf();

$pdo = getDb();
ensureBookkeepingSchema($pdo);

$txDate      = trim($_POST['tx_date'] ?? '');
$txType      = in_array($_POST['tx_type'] ?? '', ['income','expense'], true) ? $_POST['tx_type'] : '';
$category    = trim($_POST['category'] ?? '');
$description = trim($_POST['description'] ?? '');
$partner     = trim($_POST['partner'] ?? '');
$amount      = (float)str_replace([' ', ','], ['', '.'], (string)($_POST['amount'] ?? ''));
$account     = trim($_POST['account'] ?? '');
$invoiceNo   = trim($_POST['invoice_number'] ?? '');
$event       = resolveTransactionEvent($pdo, trim($_POST['event'] ?? ''));

// Validáció — kötelező mezők
$dateValid = $txDate !== '' && (bool)strtotime($txDate);
if (!$dateValid || $txType === '' || $category === '' || $description === '' || $partner === '' || $account === '' || $amount < 0) {
    flash('error', 'Hiányzó vagy érvénytelen kötelező mező.');
    header('Location: ' . BASE_URL . '/admin/bookkeeping.php');
    exit;
}

$pdo->prepare("INSERT INTO transactions
    (tx_date, tx_type, category, description, event_type, event_id, event_label, partner, amount, account, invoice_number, created_by)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
    ->execute([
        $txDate, $txType, $category, $description,
        $event['type'], $event['id'], $event['label'],
        $partner, $amount, $account, ($invoiceNo !== '' ? $invoiceNo : null),
        getCurrentUserId(),
    ]);

$id = (int)$pdo->lastInsertId();
logAudit($pdo, 'create', 'transaction', $id, transactionAuditLabel($txDate, $txType, $category, $amount));

// Tagdíj befizetés → tagok utolsó fizetés dátumának frissítése
recalcMembershipPayments($pdo);

flash('success', 'Tranzakció rögzítve.');
header('Location: ' . BASE_URL . '/admin/bookkeeping.php');
exit;
