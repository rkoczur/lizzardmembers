<?php
/**
 * Importált tranzakciók kézi összerendelése túrákkal — esemény-nevenként.
 * A Kimutatás tab táblázata névsoronként küld egy label[]/event[] párt; minden kiválasztott
 * túrát az adott esemény-névvel rendelkező, még összerendeletlen tranzakciókra alkalmazzuk.
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

$labels = $_POST['label'] ?? [];
$events = $_POST['event'] ?? [];

$upd = $pdo->prepare("UPDATE transactions SET event_type = ?, event_id = ? WHERE event_label = ? AND event_id IS NULL");

$linkedRows   = 0;
$linkedEvents = 0;

foreach ($labels as $i => $label) {
    $label = trim((string)$label);
    $choice = trim((string)($events[$i] ?? ''));
    if ($label === '' || $choice === '' || !str_contains($choice, ':')) continue;

    [$type, $idStr] = explode(':', $choice, 2);
    $id = (int)$idStr;
    if (!in_array($type, ['tour', 'future_tour'], true) || $id <= 0) continue;

    // Ellenőrzés: létezik-e a választott túra
    $tbl = $type === 'tour' ? 'tours' : 'future_tours';
    $chk = $pdo->prepare("SELECT 1 FROM `$tbl` WHERE id = ? LIMIT 1");
    $chk->execute([$id]);
    if (!$chk->fetchColumn()) continue;

    $upd->execute([$type, $id, $label]);
    if ($upd->rowCount() > 0) { $linkedRows += $upd->rowCount(); $linkedEvents++; }
}

if ($linkedRows > 0) {
    flash('success', "Összerendelve: {$linkedRows} tranzakció, {$linkedEvents} eseményhez.");
} else {
    flash('error', 'Nem történt összerendelés — nem választottál ki túrát egyik eseményhez sem.');
}
header('Location: ' . BASE_URL . '/admin/bookkeeping.php?tab=report');
exit;
