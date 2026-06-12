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
$tx = $stmt->fetch();

if ($tx) {
    $pdo->prepare("DELETE FROM transactions WHERE id = ?")->execute([$id]);
    logAudit($pdo, 'delete', 'transaction', $id,
        transactionAuditLabel($tx['tx_date'], $tx['tx_type'], $tx['category'], $tx['amount']));
    // Tagdíj befizetés törlődhetett → tagok utolsó fizetés dátumának frissítése
    recalcMembershipPayments($pdo);
    flash('success', 'Tranzakció törölve.');
} else {
    flash('error', 'A tranzakció nem található.');
}

header('Location: ' . BASE_URL . '/admin/bookkeeping.php');
exit;
