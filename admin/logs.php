<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/login-log-schema.php';
require_once __DIR__ . '/../includes/audit-schema.php';
require_once __DIR__ . '/../includes/email-log-schema.php';
requireAdminOrVezeto();

$pdo = getDb();
ensureLoginLogSchema($pdo);
ensureAuditSchema($pdo);
ensureEmailLogSchema($pdo);

$activeTab = in_array($_GET['tab'] ?? '', ['audit', 'email'], true) ? $_GET['tab'] : 'login';

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

// ── E-mail napló adatok ────────────────────────────────────────────
$emailLogs   = [];
$eTotal      = 0;
$eTotalSent  = 0;
$eTotalFailed= 0;
$eTotalAll   = 0;
$eSearch     = trim($_GET['eq']      ?? '');
$eStatus     = in_array($_GET['estatus'] ?? '', ['sent','failed'], true) ? $_GET['estatus'] : '';
$allowedETypes = ['tour_added','tour_submitted','tour_rejected',
    'join_confirm','join_admin_notify','welcome',
    'future_tour_application','future_tour_guest_application',
    'future_tour_new_application_admin','future_tour_guest_application_admin',
    'future_tour_waitlist_promoted','future_tour_guest_approved','future_tour_guest_rejected'];
$eType       = in_array($_GET['etype2'] ?? '', $allowedETypes, true) ? $_GET['etype2'] : '';
$eDays       = max(1, min(365, (int)($_GET['edays'] ?? 30)));

$eWhere  = ['sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)'];
$eParams = [$eDays];
if ($eStatus) { $eWhere[] = 'status = ?';      $eParams[] = $eStatus; }
if ($eType)   { $eWhere[] = 'email_type = ?';  $eParams[] = $eType;   }
if ($eSearch !== '') {
    $eWhere[]  = '(recipient_name LIKE ? OR recipient_email LIKE ? OR subject LIKE ?)';
    $eParams[] = '%'.$eSearch.'%';
    $eParams[] = '%'.$eSearch.'%';
    $eParams[] = '%'.$eSearch.'%';
}
$eWhereClause = implode(' AND ', $eWhere);

$eStmt = $pdo->prepare("SELECT id, user_id, recipient_email, recipient_name, subject, email_type, status, error_message, smtp_response, sent_at FROM email_log WHERE $eWhereClause ORDER BY sent_at DESC LIMIT 500");
$eStmt->execute($eParams);
$emailLogs = $eStmt->fetchAll();

$etStmt = $pdo->prepare("SELECT COUNT(*) FROM email_log WHERE $eWhereClause"); $etStmt->execute($eParams); $eTotal = (int)$etStmt->fetchColumn();
$esSent = $pdo->prepare("SELECT COUNT(*) FROM email_log WHERE status='sent'   AND sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)"); $esSent->execute([$eDays]); $eTotalSent  = (int)$esSent->fetchColumn();
$esFail = $pdo->prepare("SELECT COUNT(*) FROM email_log WHERE status='failed' AND sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)"); $esFail->execute([$eDays]); $eTotalFailed = (int)$esFail->fetchColumn();
$esAll  = $pdo->query("SELECT COUNT(*) FROM email_log"); $eTotalAll = (int)$esAll->fetchColumn();

$emailTypeLabels = [
    'tour_added'                       => 'Túra értesítő',
    'tour_submitted'                   => 'Túra beküldve',
    'tour_rejected'                    => 'Túra elutasítva',
    'join_confirm'                     => 'Belépési kérelem visszaigazolás',
    'join_admin_notify'                => 'Belépési kérelem (admin)',
    'welcome'                          => 'Üdvözlő e-mail',
    'future_tour_application'          => 'API: Meghirdetett túra – jelentkezés',
    'future_tour_guest_application'    => 'API: Meghirdetett túra – vendég jelentkezés',
    'future_tour_new_application_admin'=> 'API: Meghirdetett túra – jelentkezés (admin)',
    'future_tour_guest_application_admin' => 'API: Meghirdetett túra – vendég (admin)',
    'future_tour_waitlist_promoted'    => 'Meghirdetett túra – várólistáról előre',
    'future_tour_guest_approved'       => 'Meghirdetett túra – vendég jóváhagyva',
    'future_tour_guest_rejected'       => 'Meghirdetett túra – vendég elutasítva',
];

$pageTitle  = 'Naplók';
$activePage = 'logs';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="tab-nav">
  <a href="<?= BASE_URL ?>/admin/security.php" class="tab-link<?= $activePage === 'security' ? ' active' : '' ?>">Biztonság</a>
  <a href="<?= BASE_URL ?>/admin/logs.php" class="tab-link<?= $activePage === 'logs' ? ' active' : '' ?>">Naplók</a>
  <a href="<?= BASE_URL ?>/admin/settings.php" class="tab-link<?= $activePage === 'settings' ? ' active' : '' ?>">Beállítások</a>
  <a href="<?= BASE_URL ?>/admin/orphaned-assets.php" class="tab-link<?= $activePage === 'tools' ? ' active' : '' ?>">Felesleges fájlok</a>
</div>

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
<div class="tab-nav tab-nav-flush">
  <a href="?tab=login" class="tab-link<?= $activeTab === 'login' ? ' active' : '' ?>">Belépési napló</a>
  <a href="?tab=audit" class="tab-link<?= $activeTab === 'audit' ? ' active' : '' ?>">Audit napló</a>
  <a href="?tab=email" class="tab-link<?= $activeTab === 'email' ? ' active' : '' ?>">E-mail napló</a>
</div>

<?php if ($activeTab === 'login'): ?>
<!-- ══════════════════ BELÉPÉSI NAPLÓ ══════════════════ -->

<div class="rg-4">
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

<?php elseif ($activeTab === 'audit'): ?>
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

<?php elseif ($activeTab === 'email'): ?>
<!-- ══════════════════ E-MAIL NAPLÓ ══════════════════ -->

<div class="rg-4">
  <div class="card">
    <div class="card-body" style="display:flex;align-items:center;gap:16px;">
      <div style="font-size:28px;font-weight:700;color:var(--primary);"><?= number_format($eTotalSent) ?></div>
      <div><div style="font-weight:600;font-size:14px;">Elküldve</div><div style="font-size:12px;color:var(--text-muted);">utolsó <?= $eDays ?> napban</div></div>
    </div>
  </div>
  <div class="card">
    <div class="card-body" style="display:flex;align-items:center;gap:16px;">
      <div style="font-size:28px;font-weight:700;color:var(--danger);"><?= number_format($eTotalFailed) ?></div>
      <div><div style="font-weight:600;font-size:14px;">Sikertelen</div><div style="font-size:12px;color:var(--text-muted);">utolsó <?= $eDays ?> napban</div></div>
    </div>
  </div>
  <div class="card">
    <div class="card-body" style="display:flex;align-items:center;gap:16px;">
      <div style="font-size:28px;font-weight:700;color:var(--text);"><?= number_format($eTotalSent + $eTotalFailed) ?></div>
      <div><div style="font-weight:600;font-size:14px;">Összes</div><div style="font-size:12px;color:var(--text-muted);">utolsó <?= $eDays ?> napban</div></div>
    </div>
  </div>
  <div class="card">
    <div class="card-body" style="display:flex;align-items:center;gap:16px;">
      <div style="font-size:28px;font-weight:700;color:var(--text-muted);"><?= number_format($eTotalAll) ?></div>
      <div><div style="font-weight:600;font-size:14px;">Összes rekord</div><div style="font-size:12px;color:var(--text-muted);">teljes napló</div></div>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:16px;">
  <form method="get" class="filter-bar">
    <input type="hidden" name="tab" value="email">
    <div class="search-bar" style="flex:1;min-width:180px;">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" name="eq" value="<?= e($eSearch) ?>" placeholder="Keresés neve, e-mail vagy tárgy alapján…">
    </div>
    <select name="etype2" class="form-control" style="width:auto;min-width:200px;">
      <option value="">Minden típus</option>
      <optgroup label="Klasszikus túrák">
        <option value="tour_added"     <?= $eType==='tour_added'    ?'selected':'' ?>>Túra értesítő</option>
        <option value="tour_submitted" <?= $eType==='tour_submitted'?'selected':'' ?>>Túra beküldve</option>
        <option value="tour_rejected"  <?= $eType==='tour_rejected' ?'selected':'' ?>>Túra elutasítva</option>
      </optgroup>
      <optgroup label="Tagság">
        <option value="join_confirm"      <?= $eType==='join_confirm'     ?'selected':'' ?>>Belépési kérelem visszaigazolás</option>
        <option value="join_admin_notify" <?= $eType==='join_admin_notify'?'selected':'' ?>>Belépési kérelem (admin)</option>
        <option value="welcome"           <?= $eType==='welcome'          ?'selected':'' ?>>Üdvözlő e-mail</option>
      </optgroup>
      <optgroup label="Meghirdetett túrák">
        <option value="future_tour_application"           <?= $eType==='future_tour_application'          ?'selected':'' ?>>Jelentkezés visszaigazolás</option>
        <option value="future_tour_guest_application"     <?= $eType==='future_tour_guest_application'    ?'selected':'' ?>>Vendég jelentkezés visszaigazolás</option>
        <option value="future_tour_new_application_admin" <?= $eType==='future_tour_new_application_admin'?'selected':'' ?>>Új jelentkezés (admin)</option>
        <option value="future_tour_guest_application_admin" <?= $eType==='future_tour_guest_application_admin'?'selected':'' ?>>Új vendég jelentkezés (admin)</option>
        <option value="future_tour_waitlist_promoted"     <?= $eType==='future_tour_waitlist_promoted'    ?'selected':'' ?>>Várólistáról előre lépett</option>
        <option value="future_tour_guest_approved"        <?= $eType==='future_tour_guest_approved'       ?'selected':'' ?>>Vendég jóváhagyva</option>
        <option value="future_tour_guest_rejected"        <?= $eType==='future_tour_guest_rejected'       ?'selected':'' ?>>Vendég elutasítva</option>
      </optgroup>
    </select>
    <select name="estatus" class="form-control" style="width:auto;min-width:150px;">
      <option value="">Minden státusz</option>
      <option value="sent"   <?= $eStatus==='sent'  ?'selected':'' ?>>Elküldve</option>
      <option value="failed" <?= $eStatus==='failed'?'selected':'' ?>>Sikertelen</option>
    </select>
    <select name="edays" class="form-control" style="width:auto;min-width:130px;">
      <?php foreach ([7,14,30,60,90,180,365] as $d): ?>
        <option value="<?= $d ?>" <?= $eDays===$d?'selected':'' ?>>Utolsó <?= $d ?> nap</option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Szűrés</button>
    <?php if ($eStatus || $eType || $eSearch !== '' || $eDays !== 30): ?>
      <a href="?tab=email" class="btn btn-ghost btn-sm">Visszaállítás</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Dátum / idő</th>
          <th>Címzett</th>
          <th>Tárgy</th>
          <th>Típus</th>
          <th>Státusz</th>
          <th style="width:80px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($emailLogs)): ?>
        <tr><td colspan="6"><div class="empty-state"><div class="empty-icon">📧</div><p>Nincs e-mail a megadott feltételek alapján.</p></div></td></tr>
        <?php else: foreach ($emailLogs as $log): ?>
        <tr>
          <td style="white-space:nowrap;font-size:13px;"><?= e((new DateTime($log['sent_at']))->format('Y.m.d H:i:s')) ?></td>
          <td>
            <div class="td-name"><?= e($log['recipient_name'] ?? '—') ?></div>
            <div class="td-sub"><?= e($log['recipient_email']) ?></div>
          </td>
          <td style="font-size:13px;max-width:260px;"><?= e($log['subject']) ?></td>
          <td>
            <?php $tLabel = $emailTypeLabels[$log['email_type'] ?? ''] ?? e($log['email_type'] ?? '—'); ?>
            <span class="badge badge-overdue" style="font-size:11px;"><?= $tLabel ?></span>
          </td>
          <td>
            <?php if ($log['status'] === 'sent'): ?>
              <span class="badge badge-active">Elküldve</span>
              <?php if (!empty($log['smtp_response'])): ?><div class="log-srv-resp" title="<?= e($log['smtp_response']) ?>"><?= e($log['smtp_response']) ?></div><?php endif; ?>
            <?php else: ?>
              <span class="badge badge-inactive">Sikertelen</span>
              <?php if ($log['error_message']): ?><div class="log-srv-resp" title="<?= e($log['error_message']) ?>"><?= e($log['error_message']) ?></div><?php endif; ?>
            <?php endif; ?>
          </td>
          <td>
            <button type="button" class="btn btn-ghost btn-sm"
              data-preview-id="<?= (int)$log['id'] ?>"
              data-subject="<?= e($log['subject']) ?>"
              data-meta="<?= e(($log['recipient_name'] ?? '') . ' <' . $log['recipient_email'] . '> — ' . (new DateTime($log['sent_at']))->format('Y.m.d H:i')) ?>">
              Előnézet
            </button>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if (!empty($emailLogs)): ?>
  <div style="padding:12px 20px;font-size:12px;color:var(--text-muted);border-top:1px solid var(--border);">
    <?= count($emailLogs) ?> bejegyzés látható <?= ($eStatus||$eType||$eSearch!=='') ? '(szűrve)' : '' ?> — összesen <?= $eTotal ?> az utolsó <?= $eDays ?> napban
  </div>
  <?php endif; ?>
</div>

<!-- Email előnézet modal -->
<div id="email-preview-modal" style="display:none;position:fixed;inset:0;z-index:9000;background:rgba(0,0,0,.55);align-items:center;justify-content:center;">
  <div style="background:var(--bg);border-radius:12px;width:min(860px,96vw);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.35);">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-shrink:0;">
      <div style="min-width:0;">
        <div id="email-preview-subject" style="font-weight:700;font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"></div>
        <div id="email-preview-meta" style="font-size:12px;color:var(--text-muted);margin-top:3px;"></div>
      </div>
      <button id="close-email-preview" class="btn btn-ghost btn-sm" style="flex-shrink:0;">Bezárás</button>
    </div>
    <iframe id="email-preview-frame" style="flex:1;border:none;width:100%;min-height:480px;" sandbox="allow-same-origin"></iframe>
  </div>
</div>

<script>
(function () {
  var modal  = document.getElementById('email-preview-modal');
  var frame  = document.getElementById('email-preview-frame');
  var subjEl = document.getElementById('email-preview-subject');
  var metaEl = document.getElementById('email-preview-meta');
  var closeBtn = document.getElementById('close-email-preview');

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-preview-id]');
    if (!btn) return;
    subjEl.textContent = btn.getAttribute('data-subject');
    metaEl.textContent = btn.getAttribute('data-meta');
    frame.src = '<?= BASE_URL ?>/admin/email-log-preview.php?id=' + btn.getAttribute('data-preview-id');
    modal.style.display = 'flex';
  });

  closeBtn.addEventListener('click', function () {
    modal.style.display = 'none';
    frame.src = '';
  });

  modal.addEventListener('click', function (e) {
    if (e.target === modal) {
      modal.style.display = 'none';
      frame.src = '';
    }
  });
}());
</script>

<?php endif; ?>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
