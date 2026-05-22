<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/login-log-schema.php';
require_once __DIR__ . '/../includes/audit-schema.php';
requireAdminOrVezeto();

$pdo = getDb();
ensureLoginLogSchema($pdo);
ensureAuditSchema($pdo);

$activeTab = ($_GET['tab'] ?? 'login') === 'audit' ? 'audit' : 'login';

// ── Belépési napló adatok ───────────────────────────────────────────
$filterStatus = $_GET['status'] ?? '';
$filterEvent  = $_GET['etype']  ?? '';
$search       = trim($_GET['q'] ?? '');
$days         = max(1, min(365, (int)($_GET['days'] ?? 30)));

$lWhere  = ['created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)'];
$lParams = [$days];
if ($filterStatus && in_array($filterStatus, ['success','failed'], true)) {
    $lWhere[]  = 'status = ?';
    $lParams[] = $filterStatus;
}
if ($filterEvent === 'login') {
    $lWhere[] = "event_type = 'login'";
} elseif ($filterEvent === 'reset') {
    $lWhere[] = "event_type IN ('password_reset_request','password_reset_complete')";
}
if ($search !== '') {
    $lWhere[]  = '(name LIKE ? OR username LIKE ? OR ip LIKE ?)';
    $lParams[] = '%'.$search.'%';
    $lParams[] = '%'.$search.'%';
    $lParams[] = '%'.$search.'%';
}
$lWhereClause = implode(' AND ', $lWhere);

$loginLogs = $pdo->prepare("SELECT * FROM login_log WHERE $lWhereClause ORDER BY created_at DESC LIMIT 500");
$loginLogs->execute($lParams);
$loginLogs = $loginLogs->fetchAll();

$lcStmt = $pdo->prepare("SELECT COUNT(*) FROM login_log WHERE $lWhereClause"); $lcStmt->execute($lParams); $lTotal = (int)$lcStmt->fetchColumn();

$scStmt = $pdo->prepare("SELECT COUNT(*) FROM login_log WHERE status='success' AND event_type='login' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)"); $scStmt->execute([$days]); $successCount = (int)$scStmt->fetchColumn();
$fcStmt = $pdo->prepare("SELECT COUNT(*) FROM login_log WHERE status='failed' AND event_type='login' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)"); $fcStmt->execute([$days]); $failCount = (int)$fcStmt->fetchColumn();
$rcStmt = $pdo->prepare("SELECT COUNT(*) FROM login_log WHERE event_type='password_reset_complete' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)"); $rcStmt->execute([$days]); $resetCount = (int)$rcStmt->fetchColumn();

function parseUserAgent(string $ua): string
{
    if ($ua === '') return '—';

    // Device
    if (preg_match('/iPhone/i', $ua))                          $device = 'iPhone';
    elseif (preg_match('/iPad/i', $ua))                        $device = 'iPad';
    elseif (preg_match('/Android/i', $ua))                     $device = 'Android';
    elseif (preg_match('/Macintosh|Mac OS X/i', $ua))          $device = 'Mac';
    elseif (preg_match('/Windows/i', $ua))                     $device = 'PC';
    elseif (preg_match('/Linux/i', $ua))                       $device = 'Linux';
    else                                                        $device = 'Egyéb';

    // Browser — order matters: Edge before Chrome, Chrome before Safari
    if (preg_match('/Edg\//i', $ua))                           $browser = 'Edge';
    elseif (preg_match('/OPR\/|Opera/i', $ua))                 $browser = 'Opera';
    elseif (preg_match('/Firefox\/(\d+)/i', $ua, $m))          $browser = 'Firefox ' . $m[1];
    elseif (preg_match('/Chrome\/(\d+)/i', $ua, $m))           $browser = 'Chrome ' . $m[1];
    elseif (preg_match('/Safari\/\d+/i', $ua) &&
            preg_match('/Version\/(\d+)/i', $ua, $m))          $browser = 'Safari ' . $m[1];
    elseif (preg_match('/MSIE|Trident/i', $ua))                $browser = 'IE';
    else                                                        $browser = 'Ismeretlen';

    return $device . ' · ' . $browser;
}

$failReasonLabels = [
    'wrong_password'   => 'Hibás jelszó',
    'unknown_user'     => 'Ismeretlen felhasználó',
    'account_locked'   => 'Fiók zárolva',
    'account_inactive' => 'Inaktív fiók',
    'ip_blocked'       => 'IP zárolva',
];

// ── Audit napló adatok ─────────────────────────────────────────────
$filterType   = $_GET['type']   ?? '';
$filterAction = $_GET['action'] ?? '';
$aSearch      = trim($_GET['aq'] ?? '');

$aWhere  = ['created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)'];
$aParams = [];
if ($filterType && in_array($filterType, ['member','tour'], true)) {
    $aWhere[]  = 'entity_type = ?';
    $aParams[] = $filterType;
}
if ($filterAction && in_array($filterAction, ['create','update','delete'], true)) {
    $aWhere[]  = 'action = ?';
    $aParams[] = $filterAction;
}
if ($aSearch !== '') {
    $aWhere[]  = '(entity_label LIKE ? OR admin_name LIKE ?)';
    $aParams[] = '%'.$aSearch.'%';
    $aParams[] = '%'.$aSearch.'%';
}
$aWhereClause = implode(' AND ', $aWhere);

$auditLogs = $pdo->prepare("SELECT * FROM audit_log WHERE $aWhereClause ORDER BY created_at DESC");
$auditLogs->execute($aParams);
$auditLogs = $auditLogs->fetchAll();

$aTotalStmt = $pdo->query('SELECT COUNT(*) FROM audit_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)');
$aTotal     = (int)$aTotalStmt->fetchColumn();

$actionLabels  = ['create' => 'Létrehozás', 'update' => 'Módosítás', 'delete' => 'Törlés'];
$actionClasses = ['create' => 'badge-active', 'update' => 'badge-overdue', 'delete' => 'badge-inactive'];
$typeLabels    = ['member' => 'Tag', 'tour' => 'Túra'];

$pageTitle  = 'Naplók';
$activePage = 'logs';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="page-header">
  <h1>Naplók</h1>
  <?php if ($activeTab === 'audit'): ?>
  <a href="<?= BASE_URL ?>/actions/audit-export.php<?= $filterType || $filterAction || $aSearch !== '' ? '?'.http_build_query(array_filter(['type'=>$filterType,'action'=>$filterAction,'q'=>$aSearch])) : '' ?>" class="btn btn-ghost btn-sm">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="14" height="14">
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
      <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
    </svg>
    Exportálás (CSV)
  </a>
  <?php endif; ?>
</div>

<!-- Tabsáv -->
<div style="display:flex;gap:0;border-bottom:2px solid var(--border);margin-bottom:20px;">
  <a href="?tab=login"
     style="padding:10px 22px;font-size:14px;font-weight:600;text-decoration:none;border-bottom:2px solid <?= $activeTab==='login' ? 'var(--primary)' : 'transparent' ?>;margin-bottom:-2px;color:<?= $activeTab==='login' ? 'var(--primary)' : 'var(--text-muted)' ?>;">
    Belépési napló
  </a>
  <a href="?tab=audit"
     style="padding:10px 22px;font-size:14px;font-weight:600;text-decoration:none;border-bottom:2px solid <?= $activeTab==='audit' ? 'var(--primary)' : 'transparent' ?>;margin-bottom:-2px;color:<?= $activeTab==='audit' ? 'var(--primary)' : 'var(--text-muted)' ?>;">
    Audit napló
  </a>
</div>

<?php if ($activeTab === 'login'): ?>
<!-- ══════════════════ BELÉPÉSI NAPLÓ ══════════════════ -->

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;">
  <div class="card">
    <div class="card-body" style="display:flex;align-items:center;gap:16px;">
      <div style="font-size:28px;font-weight:700;color:var(--primary);"><?= number_format($successCount) ?></div>
      <div><div style="font-weight:600;font-size:14px;">Sikeres belépés</div><div style="font-size:12px;color:var(--text-muted);">utolsó <?= $days ?> napban</div></div>
    </div>
  </div>
  <div class="card">
    <div class="card-body" style="display:flex;align-items:center;gap:16px;">
      <div style="font-size:28px;font-weight:700;color:var(--danger);"><?= number_format($failCount) ?></div>
      <div><div style="font-weight:600;font-size:14px;">Sikertelen kísérlet</div><div style="font-size:12px;color:var(--text-muted);">utolsó <?= $days ?> napban</div></div>
    </div>
  </div>
  <div class="card">
    <div class="card-body" style="display:flex;align-items:center;gap:16px;">
      <div style="font-size:28px;font-weight:700;color:var(--warning,#d97706);"><?= number_format($resetCount) ?></div>
      <div><div style="font-weight:600;font-size:14px;">Jelszóvisszaállítás</div><div style="font-size:12px;color:var(--text-muted);">utolsó <?= $days ?> napban</div></div>
    </div>
  </div>
  <div class="card">
    <div class="card-body" style="display:flex;align-items:center;gap:16px;">
      <div style="font-size:28px;font-weight:700;color:var(--text);"><?= number_format($lTotal) ?></div>
      <div><div style="font-weight:600;font-size:14px;">Összes esemény</div><div style="font-size:12px;color:var(--text-muted);">utolsó <?= $days ?> napban</div></div>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:16px;">
  <form method="get" class="filter-bar">
    <input type="hidden" name="tab" value="login">
    <div class="search-bar" style="flex:1;min-width:180px;">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" name="q" value="<?= e($search) ?>" placeholder="Név, felhasználónév vagy IP…">
    </div>
    <select name="etype" class="form-control" style="width:auto;min-width:160px;">
      <option value="">Minden esemény</option>
      <option value="login" <?= $filterEvent==='login'?'selected':'' ?>>Bejelentkezés</option>
      <option value="reset" <?= $filterEvent==='reset'?'selected':'' ?>>Jelszóvisszaállítás</option>
    </select>
    <select name="status" class="form-control" style="width:auto;min-width:150px;">
      <option value="">Minden státusz</option>
      <option value="success" <?= $filterStatus==='success'?'selected':'' ?>>Sikeres</option>
      <option value="failed"  <?= $filterStatus==='failed' ?'selected':'' ?>>Sikertelen</option>
    </select>
    <select name="days" class="form-control" style="width:auto;min-width:130px;">
      <?php foreach ([7,14,30,60,90,180,365] as $d): ?>
        <option value="<?= $d ?>" <?= $days===$d?'selected':'' ?>>Utolsó <?= $d ?> nap</option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Szűrés</button>
    <?php if ($filterStatus || $filterEvent || $search !== '' || $days !== 30): ?>
      <a href="?tab=login" class="btn btn-ghost btn-sm">Visszaállítás</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Dátum / idő</th><th>Felhasználó</th><th>IP-cím</th><th>Esemény</th><th>Böngésző</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($loginLogs)): ?>
        <tr><td colspan="5"><div class="empty-state"><div class="empty-icon">📋</div><p>Nincs esemény a megadott feltételek alapján.</p></div></td></tr>
        <?php else: foreach ($loginLogs as $log): ?>
        <?php $et = $log['event_type'] ?? 'login'; ?>
        <tr>
          <td style="white-space:nowrap;font-size:13px;"><?= e((new DateTime($log['created_at']))->format('Y.m.d H:i:s')) ?></td>
          <td>
            <?php if ($log['name'] !== ''): ?>
              <div class="td-name"><?= e($log['name']) ?></div><div class="td-sub">@<?= e($log['username']) ?></div>
            <?php else: ?>
              <span style="color:var(--text-muted);font-size:13px;"><?= e($log['username']) !== '' ? e($log['username']) : '—' ?></span>
            <?php endif; ?>
          </td>
          <td><code style="font-size:13px;"><?= e($log['ip']) ?></code></td>
          <td>
            <?php if ($et === 'password_reset_request'): ?>
              <span class="badge badge-overdue">Visszaállítás kérve</span>
            <?php elseif ($et === 'password_reset_complete'): ?>
              <span class="badge badge-active">Jelszó megváltva</span>
            <?php elseif ($log['status']==='success'): ?>
              <span class="badge badge-active">Sikeres belépés</span>
            <?php else: ?>
              <span class="badge badge-inactive">Sikertelen belépés</span>
              <?php if ($log['fail_reason']): ?><div style="font-size:11px;color:var(--text-muted);margin-top:2px;"><?= e($failReasonLabels[$log['fail_reason']] ?? $log['fail_reason']) ?></div><?php endif; ?>
            <?php endif; ?>
          </td>
          <td style="font-size:12px;color:var(--text-muted);white-space:nowrap;"><?= e(parseUserAgent($log['user_agent'])) ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if (!empty($loginLogs)): ?>
  <div style="padding:12px 20px;font-size:12px;color:var(--text-muted);border-top:1px solid var(--border);">
    <?= count($loginLogs) ?> esemény látható <?= ($filterStatus||$filterEvent||$search!=='') ? '(szűrve)' : '' ?> — összesen <?= $lTotal ?> az utolsó <?= $days ?> napban
  </div>
  <?php endif; ?>
</div>

<?php else: ?>
<!-- ══════════════════ AUDIT NAPLÓ ══════════════════ -->

<div class="card" style="margin-bottom:16px;">
  <form method="get" class="filter-bar">
    <input type="hidden" name="tab" value="audit">
    <div class="search-bar" style="flex:1;min-width:180px;">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" name="aq" value="<?= e($aSearch) ?>" placeholder="Keresés entitás vagy admin neve alapján…">
    </div>
    <select name="type" class="form-control" style="width:auto;min-width:130px;">
      <option value="">Minden típus</option>
      <option value="member" <?= $filterType==='member'?'selected':'' ?>>Tag</option>
      <option value="tour"   <?= $filterType==='tour'  ?'selected':'' ?>>Túra</option>
    </select>
    <select name="action" class="form-control" style="width:auto;min-width:150px;">
      <option value="">Minden művelet</option>
      <option value="create" <?= $filterAction==='create'?'selected':'' ?>>Létrehozás</option>
      <option value="update" <?= $filterAction==='update'?'selected':'' ?>>Módosítás</option>
      <option value="delete" <?= $filterAction==='delete'?'selected':'' ?>>Törlés</option>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Szűrés</button>
    <?php if ($filterType||$filterAction||$aSearch!==''): ?>
      <a href="?tab=audit" class="btn btn-ghost btn-sm">Visszaállítás</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Dátum / idő</th><th>Admin</th><th>Művelet</th><th>Típus</th><th>Entitás</th><th>Változások</th></tr>
      </thead>
      <tbody>
        <?php foreach ($auditLogs as $log):
          $changes  = $log['changes'] ? json_decode($log['changes'], true) : [];
          $isUpdate = $log['action'] === 'update';
        ?>
        <tr>
          <td style="white-space:nowrap;font-size:13px;"><?= e((new DateTime($log['created_at']))->format('Y.m.d H:i:s')) ?></td>
          <td style="font-size:13px;"><?= e($log['admin_name']) ?></td>
          <td><span class="badge <?= $actionClasses[$log['action']] ?? 'badge-inactive' ?>"><?= e($actionLabels[$log['action']] ?? $log['action']) ?></span></td>
          <td style="font-size:13px;"><?= e($typeLabels[$log['entity_type']] ?? $log['entity_type']) ?></td>
          <td style="font-size:13px;font-weight:500;"><?= e($log['entity_label']) ?></td>
          <td style="font-size:12px;color:var(--text-muted);max-width:340px;">
            <?php if ($changes): ?>
              <?php if ($isUpdate): foreach ($changes as $c): ?>
                <div style="line-height:1.6;"><span style="color:var(--text);"><?= e($c['k']??'') ?>:</span> <span style="text-decoration:line-through;opacity:.6;"><?= e($c['f']??'—') ?></span> &rarr; <strong style="color:var(--text);"><?= e($c['t']??'—') ?></strong></div>
              <?php endforeach; else: foreach ($changes as $c): ?>
                <div style="line-height:1.6;"><span style="color:var(--text);"><?= e($c['k']??'') ?>:</span> <span><?= e($c['v']??'—') ?></span></div>
              <?php endforeach; endif; ?>
            <?php else: ?><span style="opacity:.4;">—</span><?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($auditLogs)): ?>
        <tr><td colspan="6"><div class="empty-state"><div class="empty-icon">📋</div><p>Nincs rögzített esemény a megadott szűrési feltételeknek megfelelően.</p></div></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if (!empty($auditLogs)): ?>
  <div style="padding:12px 20px;font-size:12px;color:var(--text-muted);border-top:1px solid var(--border);">
    <?= count($auditLogs) ?> bejegyzés látható <?= ($filterType||$filterAction||$aSearch!=='') ? '(szűrve)' : '' ?> — összesen <?= $aTotal ?> az utolsó 90 napban
  </div>
  <?php endif; ?>
</div>

<?php endif; ?>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
