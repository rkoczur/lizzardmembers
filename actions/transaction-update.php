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

$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
$stmt->execute([$id]);
$old = $stmt->fetch();
if (!$old) {
    flash('error', 'A tranzakció nem található.');
    header('Location: ' . BASE_URL . '/admin/bookkeeping.php');
    exit;
}

$txDate      = trim($_POST['tx_date'] ?? '');
$txType      = in_array($_POST['tx_type'] ?? '', ['income','expense'], true) ? $_POST['tx_type'] : '';
$category    = trim($_POST['category'] ?? '');
$description = trim($_POST['description'] ?? '');
$partner     = trim($_POST['partner'] ?? '');
$amount      = (float)str_replace([' ', ','], ['', '.'], (string)($_POST['amount'] ?? ''));
$account     = trim($_POST['account'] ?? '');
$invoiceNo   = trim($_POST['invoice_number'] ?? '');
$event       = resolveTransactionEvent($pdo, trim($_POST['event'] ?? ''));

$dateValid = $txDate !== '' && (bool)strtotime($txDate);
if (!$dateValid || $txType === '' || $category === '' || $description === '' || $partner === '' || $account === '' || $amount < 0) {
    flash('error', 'Hiányzó vagy érvénytelen kötelező mező.');
    header('Location: ' . BASE_URL . '/admin/transaction-detail.php?id=' . $id);
    exit;
}

$pdo->prepare("UPDATE transactions SET
        tx_date=?, tx_type=?, category=?, description=?, event_type=?, event_id=?, event_label=?,
        partner=?, amount=?, account=?, invoice_number=?
    WHERE id=?")
    ->execute([
        $txDate, $txType, $category, $description,
        $event['type'], $event['id'], $event['label'],
        $partner, $amount, $account, ($invoiceNo !== '' ? $invoiceNo : null),
        $id,
    ]);

// Mező-diff az audit naplóhoz ({k,f,t} formátum)
$typeLabel = fn(string $t): string => $t === 'income' ? 'Bevétel' : 'Kiadás';
$fields = [
    'Dátum'       => [(string)$old['tx_date'], $txDate],
    'Típus'       => [$typeLabel($old['tx_type']), $typeLabel($txType)],
    'Kategória'   => [(string)$old['category'], $category],
    'Leírás'      => [(string)$old['description'], $description],
    'Esemény'     => [(string)($old['event_label'] ?? ''), (string)($event['label'] ?? '')],
    'Partner'     => [(string)$old['partner'], $partner],
    'Összeg'      => [number_format((float)$old['amount'], 0, ',', ' ') . ' Ft', number_format($amount, 0, ',', ' ') . ' Ft'],
    'Számla'      => [(string)$old['account'], $account],
    'Számlaszám'  => [(string)($old['invoice_number'] ?? ''), $invoiceNo],
];
$changes = [];
foreach ($fields as $k => [$from, $to]) {
    if ((string)$from !== (string)$to) {
        $changes[] = ['k' => $k, 'f' => $from === '' ? '—' : $from, 't' => $to === '' ? '—' : $to];
    }
}
if ($changes) {
    logAudit($pdo, 'update', 'transaction', $id, transactionAuditLabel($txDate, $txType, $category, $amount), $changes);
}

// Tagdíj befizetés módosulhatott → tagok utolsó fizetés dátumának frissítése
recalcMembershipPayments($pdo);

flash('success', 'Tranzakció módosítva.');
header('Location: ' . BASE_URL . '/admin/bookkeeping.php');
exit;
