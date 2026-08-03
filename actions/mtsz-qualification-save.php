<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mtsz-schema.php';
requireLeader();
verifyCsrf();

if (!canManageMtsz()) {
    flash('error', 'Nincs jogosultságod MTSZ minősítés rögzítéséhez.');
    header('Location: ' . BASE_URL . '/admin/index.php');
    exit;
}

$pdo = getDb();
ensureMtszSchema($pdo);

$id        = (int)($_POST['id']      ?? 0);
$userId    = (int)($_POST['user_id'] ?? 0);
$grade     = trim($_POST['grade']      ?? '');
$regNumber = trim($_POST['reg_number'] ?? '');
$awardedOn = trim($_POST['awarded_on'] ?? '');

$back = BASE_URL . '/admin/member-detail.php?id=' . $userId . '#mtsz';

if (!$userId) {
    flash('error', 'Érvénytelen tag.');
    header('Location: ' . BASE_URL . '/admin/members.php');
    exit;
}

$member = $pdo->prepare("SELECT id, lastname, firstname FROM users WHERE id = ? LIMIT 1");
$member->execute([$userId]);
$member = $member->fetch();
if (!$member) {
    flash('error', 'A tag nem található.');
    header('Location: ' . BASE_URL . '/admin/members.php');
    exit;
}

if (!array_key_exists($grade, mtszGradeLabels())) {
    flash('error', 'Érvénytelen fokozat.');
    header('Location: ' . $back);
    exit;
}

if ($awardedOn !== '') {
    $d = DateTime::createFromFormat('Y-m-d', $awardedOn);
    if (!$d || $d->format('Y-m-d') !== $awardedOn) {
        flash('error', 'Érvénytelen dátum.');
        header('Location: ' . $back);
        exit;
    }
} else {
    $awardedOn = null;
}

$regNumber = $regNumber !== '' ? mb_substr($regNumber, 0, 60) : null;
$memberName = trim(($member['lastname'] ?? '') . ' ' . ($member['firstname'] ?? ''));

// Ugyanaz a fokozat egy tagnál csak egyszer szerepelhet
$dup = $pdo->prepare("SELECT id FROM mtsz_qualifications WHERE user_id = ? AND grade = ? AND id <> ? LIMIT 1");
$dup->execute([$userId, $grade, $id]);
if ($dup->fetchColumn()) {
    flash('error', 'Ez a fokozat már rögzítve van ehhez a taghoz.');
    header('Location: ' . $back);
    exit;
}

if ($id) {
    $existing = $pdo->prepare("SELECT * FROM mtsz_qualifications WHERE id = ? AND user_id = ? LIMIT 1");
    $existing->execute([$id, $userId]);
    $existing = $existing->fetch();
    if (!$existing) {
        flash('error', 'A minősítés nem található.');
        header('Location: ' . $back);
        exit;
    }
    $pdo->prepare("UPDATE mtsz_qualifications SET grade = ?, reg_number = ?, awarded_on = ? WHERE id = ?")
        ->execute([$grade, $regNumber, $awardedOn, $id]);

    $changes = [];
    if ($existing['grade'] !== $grade) {
        $changes['Fokozat'] = ['from' => mtszGradeLabel($existing['grade']), 'to' => mtszGradeLabel($grade)];
    }
    if (($existing['reg_number'] ?? null) !== $regNumber) {
        $changes['MTSZ nyilvántartási szám'] = ['from' => $existing['reg_number'], 'to' => $regNumber];
    }
    if (($existing['awarded_on'] ?? null) !== $awardedOn) {
        $changes['Megszerzés dátuma'] = ['from' => $existing['awarded_on'], 'to' => $awardedOn];
    }
    logAudit($pdo, 'update', 'member', $userId, $memberName . ' — MTSZ minősítés', $changes ?: null);
    flash('success', 'MTSZ minősítés módosítva.');
} else {
    $pdo->prepare("INSERT INTO mtsz_qualifications (user_id, grade, reg_number, awarded_on, created_by) VALUES (?,?,?,?,?)")
        ->execute([$userId, $grade, $regNumber, $awardedOn, getCurrentUserId()]);
    logAudit($pdo, 'create', 'member', $userId, $memberName . ' — MTSZ minősítés', [
        'Fokozat'                   => mtszGradeLabel($grade),
        'MTSZ nyilvántartási szám'  => $regNumber,
        'Megszerzés dátuma'         => $awardedOn,
    ]);
    flash('success', 'MTSZ minősítés rögzítve.');
}

header('Location: ' . $back);
exit;
