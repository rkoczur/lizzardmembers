<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit-schema.php';
requireAdmin();

$pdo = getDb();
ensureAuditSchema($pdo);

$filterType   = $_GET['type']   ?? '';
$filterAction = $_GET['action'] ?? '';
$search       = trim($_GET['q'] ?? '');

$where  = ['created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)'];
$params = [];

if ($filterType && in_array($filterType, ['member', 'tour'], true)) {
    $where[]  = 'entity_type = ?';
    $params[] = $filterType;
}
if ($filterAction && in_array($filterAction, ['create', 'update', 'delete'], true)) {
    $where[]  = 'action = ?';
    $params[] = $filterAction;
}
if ($search !== '') {
    $where[]  = '(entity_label LIKE ? OR admin_name LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$stmt = $pdo->prepare('SELECT * FROM audit_log WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC');
$stmt->execute($params);
$logs = $stmt->fetchAll();

$actionLabels = ['create' => 'Létrehozás', 'update' => 'Módosítás', 'delete' => 'Törlés'];
$typeLabels   = ['member' => 'Tag', 'tour' => 'Túra'];

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="audit_naplo_' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['ID', 'Dátum/idő', 'Admin', 'Művelet', 'Típus', 'Entitás', 'Változások'], ';');

foreach ($logs as $log) {
    $changesText = '';
    if ($log['changes']) {
        $c = json_decode($log['changes'], true);
        if (is_array($c)) {
            $parts = [];
            if ($log['action'] === 'update') {
                foreach ($c as $ch) {
                    $parts[] = ($ch['k'] ?? '') . ': ' . ($ch['f'] ?? '—') . ' → ' . ($ch['t'] ?? '—');
                }
            } else {
                foreach ($c as $ch) {
                    $parts[] = ($ch['k'] ?? '') . ': ' . ($ch['v'] ?? '—');
                }
            }
            $changesText = implode('; ', $parts);
        }
    }
    fputcsv($out, [
        $log['id'],
        $log['created_at'],
        $log['admin_name'],
        $actionLabels[$log['action']] ?? $log['action'],
        $typeLabels[$log['entity_type']] ?? $log['entity_type'],
        $log['entity_label'],
        $changesText,
    ], ';');
}

fclose($out);
exit;
