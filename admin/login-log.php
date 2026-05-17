<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/login-log-schema.php';
requireAdminOrVezeto();

$pdo = getDb();
ensureLoginLogSchema($pdo);

$filterStatus = $_GET['status'] ?? '';
$search       = trim($_GET['q'] ?? '');
$days         = max(1, min(365, (int)($_GET['days'] ?? 30)));

$where  = ['created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)'];
$params = [$days];

if ($filterStatus && in_array($filterStatus, ['success', 'failed'], true)) {
    $where[]  = 'status = ?';
    $params[] = $filterStatus;
}
if ($search !== '') {
    $where[]  = '(name LIKE ? OR username LIKE ? OR ip LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$whereClause = implode(' AND ', $where);

$stmt = $pdo->prepare("SELECT * FROM login_log WHERE $whereClause ORDER BY created_at DESC LIMIT 500");
$stmt->execute($params);
$logs = $stmt->fetchAll();

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM login_log WHERE $whereClause");
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();

$sc = $pdo->prepare("SELECT COUNT(*) FROM login_log WHERE status='success' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
$sc->execute([$days]);
$successCount = (int)$sc->fetchColumn();

$fc = $pdo->prepare("SELECT COUNT(*) FROM login_log WHERE status='failed' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
$fc->execute([$days]);
$failCount = (int)$fc->fetchColumn();

$failReasonLabels = [
    'wrong_password'   => 'Hibás jelszó',
    'unknown_user'     => 'Ismeretlen felhasználó',
    'account_locked'   => 'Fiók zárolva',
    'account_inactive' => 'Inaktív fiók',
    'ip_blocked'       => 'IP zárolva',
];

$pageTitle  = 'Bejelentkezési napló';
$activePage = 'login-log';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="page-header">
  <h1>Bejelentkezési napló <span style="font-size:14px;font-weight:400;color:var(--text-muted);">(utolsó <?= $days ?> nap)</span></h1>
</div>

<!-- Összesítő -->
<div class="rg-3" style="margin-bottom:24px;">
  <div class="card">
    <div class="card-body" style="display:flex;align-items:center;gap:16px;">
      <div style="font-size:28px;font-weight:700;color:var(--primary);"><?= number_format($successCount) ?></div>
      <div>
        <div style="font-weight:600;font-size:14px;">Sikeres bejelentkezés</div>
        <div style="font-size:12px;color:var(--text-muted);">utolsó <?= $days ?> napban</div>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-body" style="display:flex;align-items:center;gap:16px;">
      <div style="font-size:28px;font-weight:700;color:var(--danger);"><?= number_format($failCount) ?></div>
      <div>
        <div style="font-weight:600;font-size:14px;">Sikertelen kísérlet</div>
        <div style="font-size:12px;color:var(--text-muted);">utolsó <?= $days ?> napban</div>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-body" style="display:flex;align-items:center;gap:16px;">
      <div style="font-size:28px;font-weight:700;color:var(--text);"><?= number_format($successCount + $failCount) ?></div>
      <div>
        <div style="font-weight:600;font-size:14px;">Összes esemény</div>
        <div style="font-size:12px;color:var(--text-muted);">utolsó <?= $days ?> napban</div>
      </div>
    </div>
  </div>
</div>

<!-- Szűrő -->
<div class="card" style="margin-bottom:16px;">
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;padding:4px 0;">
    <div class="search-bar" style="flex:1;min-width:180px;">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Név, felhasználónév vagy IP…">
    </div>
    <select name="status" class="form-control" style="width:auto;min-width:150px;">
      <option value="">Minden státusz</option>
      <option value="success" <?= $filterStatus === 'success' ? 'selected' : '' ?>>Sikeres</option>
      <option value="failed"  <?= $filterStatus === 'failed'  ? 'selected' : '' ?>>Sikertelen</option>
    </select>
    <select name="days" class="form-control" style="width:auto;min-width:130px;">
      <?php foreach ([7, 14, 30, 60, 90, 180, 365] as $d): ?>
        <option value="<?= $d ?>" <?= $days === $d ? 'selected' : '' ?>>Utolsó <?= $d ?> nap</option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Szűrés</button>
    <?php if ($filterStatus || $search !== '' || $days !== 30): ?>
      <a href="<?= BASE_URL ?>/admin/login-log.php" class="btn btn-ghost btn-sm">Visszaállítás</a>
    <?php endif; ?>
  </form>
</div>

<!-- Napló -->
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Dátum / idő</th>
          <th>Felhasználó</th>
          <th>IP-cím</th>
          <th>Böngésző</th>
          <th>Státusz</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
          <tr><td colspan="5">
            <div class="empty-state">
              <div class="empty-icon">📋</div>
              <p>Nincs rögzített esemény a megadott feltételek alapján.</p>
            </div>
          </td></tr>
        <?php else: ?>
          <?php foreach ($logs as $log): ?>
          <tr>
            <td style="white-space:nowrap;font-size:13px;">
              <?= e((new DateTime($log['created_at']))->format('Y.m.d H:i:s')) ?>
            </td>
            <td>
              <?php if ($log['name'] !== ''): ?>
                <div class="td-name"><?= e($log['name']) ?></div>
                <div class="td-sub">@<?= e($log['username']) ?></div>
              <?php else: ?>
                <span style="color:var(--text-muted);font-size:13px;">
                  <?= e($log['username']) !== '' ? e($log['username']) : '—' ?>
                </span>
              <?php endif; ?>
            </td>
            <td><code style="font-size:13px;"><?= e($log['ip']) ?></code></td>
            <td style="font-size:12px;color:var(--text-muted);max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
              <?= e($log['user_agent']) ?>
            </td>
            <td>
              <?php if ($log['status'] === 'success'): ?>
                <span class="badge badge-active">Sikeres</span>
              <?php else: ?>
                <span class="badge badge-inactive">Sikertelen</span>
                <?php if ($log['fail_reason']): ?>
                  <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                    <?= e($failReasonLabels[$log['fail_reason']] ?? $log['fail_reason']) ?>
                  </div>
                <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if (!empty($logs)): ?>
  <div style="padding:12px 20px;font-size:12px;color:var(--text-muted);border-top:1px solid var(--border);">
    <?= count($logs) ?> esemény látható
    <?= ($filterStatus || $search !== '') ? '(szűrve)' : '' ?>
    — összesen <?= $totalCount ?> esemény az utolsó <?= $days ?> napban
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
