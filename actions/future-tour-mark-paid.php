<?php
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
$tourId = (int)($_POST['tour_id']        ?? 0);

if (!$appId) {
    header('Location: ' . BASE_URL . '/admin/future-tours.php');
    exit;
}

// Toggle: if paid_at is null → set to now; otherwise clear it
$appStmt = $pdo->prepare("SELECT paid_at FROM future_tour_applications WHERE id = ? LIMIT 1");
$appStmt->execute([$appId]);
$app = $appStmt->fetch();

if (!$app) {
    flash('error', 'Nem található a jelentkező.');
    header('Location: ' . BASE_URL . '/admin/future-tour-detail.php?id=' . $tourId);
    exit;
}

if ($app['paid_at']) {
    $pdo->prepare("UPDATE future_tour_applications SET paid_at = NULL WHERE id = ?")->execute([$appId]);
    flash('success', 'Fizetés megjelölése visszavonva.');
} else {
    $pdo->prepare("UPDATE future_tour_applications SET paid_at = NOW() WHERE id = ?")->execute([$appId]);
    flash('success', 'Fizetés megjelölve.');
}

header('Location: ' . BASE_URL . '/admin/future-tour-applicants.php?id=' . $tourId);
exit;
