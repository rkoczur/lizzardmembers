<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/profile-change.php';
requireAdmin();

verifyCsrf();

$pdo = getDb();
ensureProfileChangeSchema($pdo);

$redirect  = BASE_URL . '/admin/members.php?tab=changes';
$requestId = (int)($_POST['request_id'] ?? 0);
$action    = $_POST['action'] ?? '';

$stmt = $pdo->prepare("
    SELECT r.*, u.firstname, u.lastname,
           u.zipcode AS cur_zipcode, u.city AS cur_city, u.address AS cur_address, u.phone AS cur_phone
    FROM profile_change_requests r
    JOIN users u ON u.id = r.user_id
    WHERE r.id = ? AND r.status = 'pending'
");
$stmt->execute([$requestId]);
$req = $stmt->fetch();

if (!$req || !in_array($action, ['approve', 'reject'], true)) {
    flash('error', 'A kérelem nem található, vagy már feldolgozták.');
    header('Location: ' . $redirect);
    exit;
}

$name = trim($req['lastname'] . ' ' . $req['firstname']);

if ($action === 'approve') {
    $pdo->prepare("UPDATE users SET zipcode=?, city=?, address=?, phone=? WHERE id=?")
        ->execute([$req['zipcode'], $req['city'], $req['address'], $req['phone'], $req['user_id']]);

    $changes = [];
    foreach (PROFILE_CHANGE_FIELDS as $key => $label) {
        $oldVal = (string)($req['cur_' . $key] ?? '');
        $newVal = (string)($req[$key] ?? '');
        if ($oldVal !== $newVal) {
            $changes[] = ['k' => $label, 'f' => $oldVal ?: '—', 't' => $newVal ?: '—'];
        }
    }
    logAudit($pdo, 'update', 'member', (int)$req['user_id'], $name, $changes ?: null);
}

$pdo->prepare("UPDATE profile_change_requests SET status=?, reviewed_at=NOW(), reviewed_by=? WHERE id=?")
    ->execute([$action === 'approve' ? 'approved' : 'rejected', getCurrentUserId(), $requestId]);

flash('success', $action === 'approve'
    ? $name . ' adatmódosítása jóváhagyva.'
    : $name . ' adatmódosítása elutasítva.');
header('Location: ' . $redirect);
exit;
