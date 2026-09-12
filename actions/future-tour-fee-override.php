<?php
/**
 * Egy jelentkező egyedi részvételi díjának beállítása vagy törlése.
 * Üres mező / „Alapértelmezett” gomb → NULL, azaz újra a túra díja a tagi kedvezménnyel.
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
requireAdmin();
verifyCsrf();

$pdo = getDb();
ensureFutureToursSchema($pdo);

$appId  = (int)($_POST['application_id'] ?? 0);
$tourId = (int)($_POST['tour_id'] ?? 0);
$back   = BASE_URL . '/admin/future-tour-applicants.php?id=' . $tourId;

$stmt = $pdo->prepare("SELECT id FROM future_tour_applications WHERE id = ? LIMIT 1");
$stmt->execute([$appId]);
if (!$stmt->fetchColumn()) {
    flash('error', 'Nem található a jelentkező.');
    header('Location: ' . $back);
    exit;
}

$raw   = trim((string)($_POST['fee'] ?? ''));
$clear = !empty($_POST['clear']) || $raw === '';

if ($clear) {
    $pdo->prepare("UPDATE future_tour_applications SET fee_override = NULL WHERE id = ?")->execute([$appId]);
    flash('success', 'Az egyedi díj törölve — újra az alapértelmezett díj érvényes.');
    header('Location: ' . $back);
    exit;
}

$fee = (float)str_replace([' ', ','], ['', '.'], $raw);
if (!is_numeric(str_replace([' ', ','], ['', '.'], $raw)) || $fee < 0) {
    flash('error', 'Érvénytelen összeg.');
    header('Location: ' . $back);
    exit;
}

$pdo->prepare("UPDATE future_tour_applications SET fee_override = ? WHERE id = ?")->execute([$fee, $appId]);
flash('success', 'Egyedi részvételi díj beállítva: ' . number_format($fee, 0, ',', ' ') . ' Ft.');
header('Location: ' . $back);
exit;
