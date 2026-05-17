<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit-schema.php';
requireAdminOrVezeto();

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

$totalStmt = $pdo->query('SELECT COUNT(*) FROM audit_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)');
$totalCount = (int)$totalStmt->fetchColumn();

$actionLabels = ['create' => 'Létrehozás', 'update' => 'Módosítás', 'delete' => 'Törlés'];
$actionClasses = ['create' => 'badge-active', 'update' => 'badge-overdue', 'delete' => 'badge-inactive'];
$typeLabels   = ['member' => 'Tag', 'tour' => 'Túra'];

$pageTitle  = 'Audit napló';
$activePage = 'audit';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="page-header">
  <h1>Audit napló <span style="font-size:14px;font-weight:400;color:var(--text-muted);">(utolsó 90 nap)</span></h1>
  <div class="flex items-center gap-2">
    <a href="<?= BASE_URL ?>/actions/audit-export.php<?= $filterType || $filterAction || $search !== '' ? '?' . http_build_query(array_filter(['type' => $filterType, 'action' => $filterAction, 'q' => $search])) : '' ?>" class="btn btn-ghost btn-sm">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="7 10 12 15 17 10"/>
        <line x1="12" y1="15" x2="12" y2="3"/>
      </svg>
      Exportálás (CSV)
    </a>
  </div>
</div>

<div class="card" style="margin-bottom:16px;">
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;padding:4px 0;">
    <div class="search-bar" style="flex:1;min-width:180px;">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Keresés entitás vagy admin neve alapján…">
    </div>
    <select name="type" class="form-control" style="width:auto;min-width:130px;">
      <option value="">Minden típus</option>
      <option value="member" <?= $filterType === 'member' ? 'selected' : '' ?>>Tag</option>
      <option value="tour"   <?= $filterType === 'tour'   ? 'selected' : '' ?>>Túra</option>
    </select>
    <select name="action" class="form-control" style="width:auto;min-width:150px;">
      <option value="">Minden művelet</option>
      <option value="create" <?= $filterAction === 'create' ? 'selected' : '' ?>>Létrehozás</option>
      <option value="update" <?= $filterAction === 'update' ? 'selected' : '' ?>>Módosítás</option>
      <option value="delete" <?= $filterAction === 'delete' ? 'selected' : '' ?>>Törlés</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Szűrés</button>
    <?php if ($filterType || $filterAction || $search !== ''): ?>
      <a href="<?= BASE_URL ?>/admin/audit-log.php" class="btn btn-ghost btn-sm">Visszaállítás</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table id="audit-table">
      <thead>
        <tr>
          <th>Dátum / idő</th>
          <th>Admin</th>
          <th>Művelet</th>
          <th>Típus</th>
          <th>Entitás</th>
          <th>Változások</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($logs as $log): ?>
        <?php
          $changes = $log['changes'] ? json_decode($log['changes'], true) : [];
          $isUpdate = $log['action'] === 'update';
        ?>
        <tr>
          <td style="white-space:nowrap;font-size:13px;">
            <?= e((new DateTime($log['created_at']))->format('Y.m.d H:i:s')) ?>
          </td>
          <td style="font-size:13px;"><?= e($log['admin_name']) ?></td>
          <td>
            <span class="badge <?= $actionClasses[$log['action']] ?? 'badge-inactive' ?>">
              <?= e($actionLabels[$log['action']] ?? $log['action']) ?>
            </span>
          </td>
          <td style="font-size:13px;"><?= e($typeLabels[$log['entity_type']] ?? $log['entity_type']) ?></td>
          <td style="font-size:13px;font-weight:500;"><?= e($log['entity_label']) ?></td>
          <td style="font-size:12px;color:var(--text-muted);max-width:340px;">
            <?php if ($changes): ?>
              <?php if ($isUpdate): ?>
                <?php foreach ($changes as $c): ?>
                  <div style="line-height:1.6;">
                    <span style="color:var(--text);"><?= e($c['k'] ?? '') ?>:</span>
                    <span style="text-decoration:line-through;opacity:.6;"><?= e($c['f'] ?? '—') ?></span>
                    &rarr;
                    <strong style="color:var(--text);"><?= e($c['t'] ?? '—') ?></strong>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <?php foreach ($changes as $c): ?>
                  <div style="line-height:1.6;">
                    <span style="color:var(--text);"><?= e($c['k'] ?? '') ?>:</span>
                    <span><?= e($c['v'] ?? '—') ?></span>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            <?php else: ?>
              <span style="opacity:.4;">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?>
        <tr><td colspan="6">
          <div class="empty-state">
            <div class="empty-icon">📋</div>
            <p>Nincs rögzített esemény a megadott szűrési feltételeknek megfelelően.</p>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if (!empty($logs)): ?>
  <div style="padding:12px 20px;font-size:12px;color:var(--text-muted);border-top:1px solid var(--border);">
    <?= count($logs) ?> bejegyzés látható
    <?= ($filterType || $filterAction || $search !== '') ? '(szűrve)' : '' ?>
    — összesen <?= $totalCount ?> esemény az utolsó 90 napban
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
