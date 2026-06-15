<?php
/**
 * Előre definiált értékek összevonása ("tisztítás").
 * A kiválasztott forrás-értékekkel rendelkező tranzakciókat a cél értékre írja át (a megfelelő
 * oszlopban: category / partner / account), majd a kiürült forrás-presetet törli.
 */
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

$type    = in_array($_POST['preset_type'] ?? '', ['category', 'partner', 'account'], true) ? $_POST['preset_type'] : '';
$target  = trim($_POST['target'] ?? '');
$sources = is_array($_POST['sources'] ?? null) ? $_POST['sources'] : [];

if ($type === '' || $target === '' || empty($sources)) {
    flash('error', 'Hiányzó cél érték vagy beolvasztandó érték.');
    header('Location: ' . BASE_URL . '/admin/bookkeeping.php?tab=presets');
    exit;
}

// Az oszlopnév megegyezik a preset-típussal — fix, whitelistelt érték
$col = $type;

$upd        = $pdo->prepare("UPDATE transactions SET `$col` = ? WHERE `$col` = ?");
$cntStmt    = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE `$col` = ?");
$delPreset  = $pdo->prepare("DELETE FROM transaction_presets WHERE preset_type = ? AND value = ?");

$reassigned   = 0;
$deletedValues = 0;

foreach ($sources as $src) {
    $src = trim((string)$src);
    if ($src === '' || $src === $target) continue;

    $upd->execute([$target, $src]);
    $reassigned += $upd->rowCount();

    // Ha már nincs hozzárendelt tétel, töröljük az előre definiáltak közül
    $cntStmt->execute([$src]);
    if ((int)$cntStmt->fetchColumn() === 0) {
        $delPreset->execute([$type, $src]);
        $deletedValues += $delPreset->rowCount();
    }
}

// A cél érték legyen meg az előre definiáltak közt
$pdo->prepare("INSERT IGNORE INTO transaction_presets (preset_type, value) VALUES (?, ?)")->execute([$type, $target]);

// Partner-összevonás érintheti a tagok utolsó tagdíj-dátumát
if ($type === 'partner') {
    recalcMembershipPayments($pdo);
}

flash('success', "Összevonva: {$reassigned} tranzakció a(z) „{$target}” értékre, {$deletedValues} érték törölve.");
header('Location: ' . BASE_URL . '/admin/bookkeeping.php?tab=presets');
exit;
