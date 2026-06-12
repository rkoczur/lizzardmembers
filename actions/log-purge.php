<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin(); // törlés csak teljes adminnak (Egyesületvezető / -helyettes)
verifyCsrf();

$log   = in_array($_POST['log']   ?? '', ['login', 'audit', 'email'], true) ? $_POST['log']   : 'audit';
$range = in_array($_POST['range'] ?? '', ['month', 'year'], true)           ? $_POST['range'] : '';

if ($range === '') {
    flash('error', 'Érvénytelen időtartam.');
    header('Location: ' . BASE_URL . '/admin/logs.php?tab=' . $log);
    exit;
}

// Naplótípus -> tábla + dátumoszlop + séma
$map = [
    'login' => ['table' => 'login_log', 'col' => 'created_at', 'schema' => 'login-log-schema.php', 'fn' => 'ensureLoginLogSchema'],
    'audit' => ['table' => 'audit_log', 'col' => 'created_at', 'schema' => 'audit-schema.php',     'fn' => 'ensureAuditSchema'],
    'email' => ['table' => 'email_log', 'col' => 'sent_at',    'schema' => 'email-log-schema.php', 'fn' => 'ensureEmailLogSchema'],
];
$cfg      = $map[$log];
$interval = $range === 'year' ? '1 YEAR' : '1 MONTH';

$pdo = getDb();
require_once __DIR__ . '/../includes/' . $cfg['schema'];
$cfg['fn']($pdo);

// Whitelistelt tábla/oszlop/intervallum — biztonságos
$stmt = $pdo->prepare("DELETE FROM `{$cfg['table']}` WHERE `{$cfg['col']}` < DATE_SUB(NOW(), INTERVAL $interval)");
$stmt->execute();
$deleted = $stmt->rowCount();

$logLabels   = ['login' => 'belépési', 'audit' => 'audit', 'email' => 'e-mail'];
$rangeLabels = ['month' => '1 hónapnál régebbi', 'year' => '1 évnél régebbi'];

if ($deleted > 0) {
    flash('success', $deleted . ' ' . $logLabels[$log] . ' naplóbejegyzés törölve (' . $rangeLabels[$range] . ').');
} else {
    flash('success', 'Nem volt törölhető bejegyzés a megadott feltétellel.');
}
header('Location: ' . BASE_URL . '/admin/logs.php?tab=' . $log);
exit;
