<?php
/**
 * Meghirdetett túra értesítő — előkészítés (AJAX, JSON).
 * Összegyűjti a feliratkozott (opt-in), aktív, e-mail címmel rendelkező
 * tagokat, és eltárolja a küldési feladatot a session-be.
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/app-settings-schema.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
requireAdminOrVezeto();
verifyCsrf();

header('Content-Type: application/json; charset=utf-8');

if (!canManageTours()) {
    echo json_encode(['ok' => false, 'error' => 'Nincs jogosultságod túraértesítőt küldeni.']);
    exit;
}

$tourId = (int)($_POST['tour_id'] ?? 0);
if (!$tourId) {
    echo json_encode(['ok' => false, 'error' => 'Érvénytelen túra.']);
    exit;
}

$pdo = getDb();
ensureFutureToursSchema($pdo);

$tourStmt = $pdo->prepare("SELECT id FROM future_tours WHERE id = ? LIMIT 1");
$tourStmt->execute([$tourId]);
if (!$tourStmt->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'A túra nem található.']);
    exit;
}

ensureAppSettingsSchema($pdo);
$smtp = getSmtpConfig($pdo);
if ($smtp['host'] === '') {
    echo json_encode(['ok' => false, 'error' => 'Az SMTP szerver nincs beállítva. Kérjük, először konfigurálja a Beállítások oldalon.']);
    exit;
}

// Aktív, e-mail címmel rendelkező tagok, akik nem kapcsolták ki az értesítőt
// (opt-out modell: hiányzó beállítás = bekapcsolva)
$rows = $pdo->query("SELECT id, notification_prefs FROM users WHERE active = 1 AND email IS NOT NULL AND email <> ''")->fetchAll();
$ids  = [];
foreach ($rows as $r) {
    $prefs = json_decode($r['notification_prefs'] ?? '{}', true) ?: [];
    if (($prefs['tour_announcement'] ?? 1) != 0) {
        $ids[] = (int)$r['id'];
    }
}

if (empty($ids)) {
    echo json_encode(['ok' => false, 'error' => 'Nincs olyan tag, aki feliratkozott az új túra értesítőkre.']);
    exit;
}

$token = bin2hex(random_bytes(16));
$_SESSION['ft_announce_job'] = [
    'token'   => $token,
    'tour_id' => $tourId,
    'ids'     => $ids,
];

echo json_encode([
    'ok'    => true,
    'token' => $token,
    'ids'   => $ids,
    'total' => count($ids),
]);
