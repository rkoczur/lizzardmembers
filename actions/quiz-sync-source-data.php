<?php
/**
 * Kvíz — a birds.json / mountain_peaks.csv forrásfájlokban azóta felvett, de az
 * adatbázisból még hiányzó madarak/hegyek pótlása. A meglévőket nem érinti.
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/quiz-schema.php';
requireAdmin();

verifyCsrf();

$pdo = getDb();
ensureQuizSchema($pdo);

$addedBirds     = syncQuizBirdsFromSource($pdo);
$addedMountains = syncQuizMountainsFromSource($pdo);

if ($addedBirds === 0 && $addedMountains === 0) {
    flash('success', 'Nincs pótolandó madár vagy hegycsúcs, minden szinkronban van.');
} else {
    flash('success', "Pótolva: {$addedBirds} új madár, {$addedMountains} új hegycsúcs.");
}

header('Location: ' . BASE_URL . '/admin/quiz-questions.php');
exit;
