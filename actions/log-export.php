<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminOrVezeto();

$pdo = getDb();
$log = in_array($_GET['log'] ?? '', ['login', 'email'], true) ? $_GET['log'] : 'login';

if ($log === 'login') {
    require_once __DIR__ . '/../includes/login-log-schema.php';
    ensureLoginLogSchema($pdo);

    $days   = max(1, min(365, (int)($_GET['days'] ?? 30)));
    $status = $_GET['status'] ?? '';
    $etype  = $_GET['etype']  ?? '';
    $q      = trim($_GET['q']  ?? '');

    $where  = ['created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)'];
    $params = [$days];
    if ($status && in_array($status, ['success', 'failed'], true)) { $where[] = 'status = ?'; $params[] = $status; }
    if ($etype === 'login')      { $where[] = "event_type = 'login'"; }
    elseif ($etype === 'reset')  { $where[] = "event_type IN ('password_reset_request','password_reset_complete')"; }
    if ($q !== '') {
        $where[]  = '(name LIKE ? OR username LIKE ? OR ip LIKE ?)';
        array_push($params, "%$q%", "%$q%", "%$q%");
    }

    $stmt = $pdo->prepare('SELECT * FROM login_log WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC');
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $eventLabels = [
        'login'                   => 'Bejelentkezés',
        'password_reset_request'  => 'Jelszó-visszaállítás kérve',
        'password_reset_complete' => 'Jelszó megváltoztatva',
    ];
    $failLabels = [
        'wrong_password'   => 'Hibás jelszó',
        'unknown_user'     => 'Ismeretlen felhasználó',
        'account_locked'   => 'Fiók zárolva',
        'account_inactive' => 'Inaktív fiók',
        'ip_blocked'       => 'IP zárolva',
    ];

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="belepesi_naplo_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: max-age=0');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Dátum/idő', 'Név', 'Felhasználónév', 'IP-cím', 'Esemény', 'Státusz', 'Hiba oka', 'Böngésző'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['created_at'],
            $r['name'],
            $r['username'],
            $r['ip'],
            $eventLabels[$r['event_type'] ?? 'login'] ?? ($r['event_type'] ?? ''),
            $r['status'] === 'success' ? 'Sikeres' : 'Sikertelen',
            $r['fail_reason'] ? ($failLabels[$r['fail_reason']] ?? $r['fail_reason']) : '',
            $r['user_agent'] ?? '',
        ], ';');
    }
    fclose($out);
    exit;
}

// ── E-mail napló ───────────────────────────────────────────────────
require_once __DIR__ . '/../includes/email-log-schema.php';
ensureEmailLogSchema($pdo);

$edays   = max(1, min(365, (int)($_GET['edays'] ?? 30)));
$estatus = in_array($_GET['estatus'] ?? '', ['sent', 'failed'], true) ? $_GET['estatus'] : '';
$etype2  = trim($_GET['etype2'] ?? '');
$eq      = trim($_GET['eq'] ?? '');

$where  = ['sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)'];
$params = [$edays];
if ($estatus) { $where[] = 'status = ?';     $params[] = $estatus; }
if ($etype2 !== '') { $where[] = 'email_type = ?'; $params[] = $etype2; }
if ($eq !== '') {
    $where[]  = '(recipient_name LIKE ? OR recipient_email LIKE ? OR subject LIKE ?)';
    array_push($params, "%$eq%", "%$eq%", "%$eq%");
}

$stmt = $pdo->prepare('SELECT id, recipient_email, recipient_name, subject, email_type, status, error_message, sent_at FROM email_log WHERE ' . implode(' AND ', $where) . ' ORDER BY sent_at DESC');
$stmt->execute($params);
$rows = $stmt->fetchAll();

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="email_naplo_' . date('Y-m-d') . '.csv"');
header('Cache-Control: max-age=0');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Dátum/idő', 'Címzett neve', 'Címzett e-mail', 'Tárgy', 'Típus', 'Státusz', 'Hiba'], ';');
foreach ($rows as $r) {
    fputcsv($out, [
        $r['sent_at'],
        $r['recipient_name'] ?? '',
        $r['recipient_email'],
        $r['subject'],
        $r['email_type'] ?? '',
        $r['status'] === 'sent' ? 'Elküldve' : 'Sikertelen',
        $r['error_message'] ?? '',
    ], ';');
}
fclose($out);
exit;
