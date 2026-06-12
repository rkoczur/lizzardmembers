<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/join-schema.php';
requireAdminOrVezeto();

$pdo = getDb();
require_once __DIR__ . '/../includes/user-schema.php';
ensureUserSchema($pdo);
ensureJoinSchema($pdo);

// Az utolsó tagdíj fizetés dátuma a tranzakciós naplóból származtatott — frissítés megjelenítés előtt
recalcMembershipPayments($pdo);

// Determine active tab; non-admins cannot access applications
$tab = ($_GET['tab'] ?? '') === 'applications' ? 'applications' : 'members';
if ($tab === 'applications' && !isAdmin()) {
    header('Location: ' . BASE_URL . '/admin/members.php');
    exit;
}

// Members list
$members = $pdo->query("SELECT id, firstname, lastname, email, city, member_since, last_payment, level, points, profile_picture, active, role, is_candidate, locked_at FROM users ORDER BY role DESC, lastname, firstname")->fetchAll();

// Pending count (always needed for tab badge)
$pendingCount = 0;
try {
    $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM member_applications WHERE status='pending'")->fetchColumn();
} catch (Throwable) {}

// Applications data (only when tab=applications)
$countApproved = 0;
$countRejected = 0;
$applications  = [];
$statusFilter  = 'pending';

if ($tab === 'applications') {
    try {
        $countApproved = (int)$pdo->query("SELECT COUNT(*) FROM member_applications WHERE status='approved'")->fetchColumn();
        $countRejected = (int)$pdo->query("SELECT COUNT(*) FROM member_applications WHERE status='rejected'")->fetchColumn();
        $allowedStatuses = ['pending', 'approved', 'rejected', 'all'];
        $statusFilter    = in_array($_GET['status'] ?? '', $allowedStatuses, true) ? $_GET['status'] : 'pending';
        $where           = $statusFilter !== 'all' ? "WHERE ma.status = " . $pdo->quote($statusFilter) : '';
        $applications    = $pdo->query("
            SELECT ma.*, u.id AS user_id
            FROM member_applications ma
            LEFT JOIN users u ON u.email = ma.email
            {$where}
            ORDER BY ma.submitted_at DESC
        ")->fetchAll();
    } catch (Throwable) {}
}

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$pageTitle  = 'Tagok';
$activePage = 'members';
include __DIR__ . '/../includes/admin-header.php';
?>

<?php if ($flash_success): ?>
  <div class="alert alert-success" data-auto-dismiss><?= e($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_error): ?>
  <div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div>
<?php endif; ?>

<!-- Main tab navigation -->
<div class="tab-nav">
  <a href="<?= BASE_URL ?>/admin/members.php" class="tab-link<?= $tab === 'members' ? ' active' : '' ?>">Tagok</a>
  <?php if (isAdmin()): ?>
  <a href="<?= BASE_URL ?>/admin/members.php?tab=applications" class="tab-link<?= $tab === 'applications' ? ' active' : '' ?>">
    Jelentkezések
    <?php if ($pendingCount > 0): ?>
      <span class="badge-counter badge-counter-danger"><?= $pendingCount ?></span>
    <?php endif; ?>
  </a>
  <?php endif; ?>
  <a href="<?= BASE_URL ?>/admin/toplist.php" class="tab-link">Toplista</a>
</div>

<?php if ($tab === 'members'): ?>
<!-- ═══════════════════════════════════════════════════════════════════
     TAGOK TAB
═══════════════════════════════════════════════════════════════════ -->

<div class="page-header">
  <h1>Összes tag</h1>
  <div class="flex items-center gap-2">
    <div class="search-bar">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input type="text" id="member-search" placeholder="Tagok keresése…">
    </div>
    <a href="<?= BASE_URL ?>/actions/members-export.php" class="btn btn-ghost btn-sm">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="7 10 12 15 17 10"/>
        <line x1="12" y1="15" x2="12" y2="3"/>
      </svg>
      Exportálás (CSV)
    </a>
    <a href="<?= BASE_URL ?>/actions/members-template.php" class="btn btn-ghost btn-sm">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/>
        <line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="9" x2="9" y2="21"/>
      </svg>
      Sablon (CSV)
    </a>
    <?php if (isAdmin()): ?>
    <a href="<?= BASE_URL ?>/admin/member-import.php" class="btn btn-ghost btn-sm">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="7 10 12 15 17 10"/>
        <line x1="12" y1="3" x2="12" y2="15"/>
        <polyline points="17 8 12 3 7 8"/>
      </svg>
      Importálás (CSV)
    </a>
    <a href="<?= BASE_URL ?>/admin/member-add.php" class="btn btn-primary btn-sm">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      Tag hozzáadása
    </a>
    <?php endif; ?>
  </div>
</div>

<form method="post" action="<?= BASE_URL ?>/admin/email-compose.php" id="bulk-email-form">
<input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;min-height:36px;">
  <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;user-select:none;">
    <input type="checkbox" id="select-all"> <span style="white-space:nowrap;">Összes kijelölése</span>
  </label>
  <button type="submit" class="btn btn-primary btn-sm" id="bulk-email-btn" disabled style="display:flex;align-items:center;gap:5px;">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="14" height="14">
      <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
    </svg>
    <span id="bulk-email-label">E-mail küldés kijelölteknek</span>
  </button>
</div>

<div class="card">
  <div class="table-wrap">
    <table id="member-table">
      <thead>
        <tr>
          <th style="width:36px;"></th>
          <th>Tag</th>
          <th>Szerepkör<span class="col-filter-wrap"><button type="button" class="col-filter-btn" data-filter="role" title="Szűrés">▾</button><ul class="col-filter-menu"><li class="selected"><button type="button" data-value="">Mind</button></li><li><button type="button" data-value="admin">Admin</button></li><li><button type="button" data-value="vezeto">Vezető</button></li><li><button type="button" data-value="user">Tag</button></li></ul></span></th>
          <th>Város</th>
          <th>Tagság kezdete</th>
          <th>Utolsó fizetés</th>
          <th>Szint<span class="col-filter-wrap"><button type="button" class="col-filter-btn" data-filter="level" title="Szűrés">▾</button><ul class="col-filter-menu"><li class="selected"><button type="button" data-value="">Mind</button></li><li><button type="button" data-value="1">Újonc</button></li><li><button type="button" data-value="2">Közlegény</button></li><li><button type="button" data-value="3">Tizedes</button></li><li><button type="button" data-value="4">Őrmester</button></li><li><button type="button" data-value="5">Hadnagy</button></li><li><button type="button" data-value="6">Százados</button></li><li><button type="button" data-value="7">Őrnagy</button></li><li><button type="button" data-value="8">Alezredes</button></li><li><button type="button" data-value="9">Ezredes</button></li></ul></span></th>
          <th>Pontok</th>
          <th>Tagság státusza<span class="col-filter-wrap"><button type="button" class="col-filter-btn" data-filter="status" title="Szűrés">▾</button><ul class="col-filter-menu"><li class="selected"><button type="button" data-value="">Mind</button></li><li><button type="button" data-value="active">Aktív</button></li><li><button type="button" data-value="overdue">Tagdíj elmaradás</button></li><li><button type="button" data-value="inactive">Inaktív</button></li></ul></span></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($members as $m): ?>
        <?php $ms = getMemberStatus($m['last_payment']); ?>
        <tr data-role="<?= e($m['role']) ?>" data-level="<?= (int)$m['level'] ?>" data-status="<?= e($ms) ?>">
          <td style="text-align:center;">
            <input type="checkbox" class="member-cb" name="member_ids[]" value="<?= $m['id'] ?>">
          </td>
          <td>
            <div class="td-avatar">
              <img src="<?= getAvatarUrl($m['profile_picture']) ?>" alt="">
              <div>
                <div class="td-name"><?= e($m['lastname'] . ' ' . $m['firstname']) ?></div>
                <div class="td-sub"><?= e($m['email']) ?></div>
              </div>
            </div>
          </td>
          <td>
            <?php $roleClass = $m['role'] === 'user' ? 'badge-user' : ($m['role'] === 'admin' ? 'badge-admin' : 'badge-vezeto'); ?>
            <span class="badge <?= $roleClass ?>"><?= e(getRoleLabel($m['role'])) ?></span>
            <?php if (!empty($m['is_candidate'])): ?>
              <span class="badge badge-candidate" style="margin-left:4px;" title="Jelölt — a nyilvános oldalon nem jelenik meg">Jelölt</span>
            <?php endif; ?>
            <?php if (!empty($m['locked_at'])): ?>
              <span class="badge badge-inactive" style="margin-left:4px;" title="Zárolva: <?= e((new DateTime($m['locked_at']))->format('Y.m.d H:i')) ?>">🔒 Zárolt</span>
            <?php endif; ?>
          </td>
          <td><?= e($m['city'] ?? '—') ?></td>
          <td><?= formatDate($m['member_since']) ?></td>
          <td><?= formatDate($m['last_payment']) ?></td>
          <td><span class="level-badge <?= getLevelClass($m['level']) ?>"><?= getLevelLabel($m['level']) ?></span></td>
          <td><strong><?= number_format($m['points']) ?></strong></td>
          <td><span class="badge <?= getMemberStatusClass($ms) ?>"><?= getMemberStatusLabel($ms) ?></span></td>
          <td style="white-space:nowrap;">
            <a href="<?= BASE_URL ?>/admin/member-detail.php?id=<?= $m['id'] ?>" class="btn btn-ghost btn-sm">Megtekintés</a>
            <?php if (isAdmin() && $m['id'] !== getCurrentUserId()): ?>
            <button type="button" class="btn btn-danger btn-sm"
                    data-delete-id="<?= $m['id'] ?>"
                    data-delete-name="<?= e(addslashes($m['lastname'] . ' ' . $m['firstname'])) ?>"
                    onclick="memberDelete(this)">Törlés</button>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($members)): ?>
        <tr><td colspan="10">
          <div class="empty-state">
            <div class="empty-icon">👥</div>
            <p>Nem találhatók tagok. A tagok a felhasználók regisztrációjakor jönnek létre.</p>
          </div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</form>

<form id="member-delete-form" method="post" action="<?= BASE_URL ?>/actions/member-delete.php" style="display:none;">
  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
  <input type="hidden" name="id" id="member-delete-id">
</form>

<script>
function memberDelete(btn) {
  var name = btn.dataset.deleteName;
  if (!confirm('Biztosan törli ' + name + ' tagot? A művelet nem vonható vissza.')) return;
  document.getElementById('member-delete-id').value = btn.dataset.deleteId;
  document.getElementById('member-delete-form').submit();
}

(function () {
  var selectAll = document.getElementById('select-all');
  var btn       = document.getElementById('bulk-email-btn');
  var label     = document.getElementById('bulk-email-label');

  function updateBtn() {
    var checked = document.querySelectorAll('.member-cb:checked');
    var n       = checked.length;
    btn.disabled = n === 0;
    label.textContent = n > 0
      ? 'E-mail küldés (' + n + ' főnek)'
      : 'E-mail küldés kijelölteknek';
    selectAll.indeterminate = n > 0 && n < document.querySelectorAll('.member-cb').length;
    selectAll.checked = n > 0 && n === document.querySelectorAll('.member-cb').length;
  }

  document.querySelectorAll('.member-cb').forEach(function (cb) {
    cb.addEventListener('change', updateBtn);
  });

  selectAll.addEventListener('change', function () {
    document.querySelectorAll('.member-cb').forEach(function (cb) {
      var row = cb.closest('tr');
      if (!row || row.style.display === 'none') return;
      cb.checked = selectAll.checked;
    });
    updateBtn();
  });
})();
</script>

<?php else: ?>
<!-- ═══════════════════════════════════════════════════════════════════
     JELENTKEZÉSEK TAB
═══════════════════════════════════════════════════════════════════ -->

<div class="page-header">
  <h1>Tagfelvételi kérelmek</h1>
</div>

<!-- Stats cards -->
<div class="stats-grid" style="margin-bottom:24px;">
  <div class="stat-card">
    <div class="stat-icon">⏳</div>
    <div class="stat-label">Függőben</div>
    <div class="stat-value" style="color:var(--warning,#d97706);"><?= $pendingCount ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">✅</div>
    <div class="stat-label">Jóváhagyva</div>
    <div class="stat-value" style="color:var(--success,#16a34a);"><?= $countApproved ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">❌</div>
    <div class="stat-label">Elutasítva</div>
    <div class="stat-value" style="color:var(--danger,#dc2626);"><?= $countRejected ?></div>
  </div>
</div>

<!-- Filter subtabs -->
<div class="tab-nav" style="margin-bottom:16px;">
  <?php
  $subTabs = [
    'pending'  => 'Függőben (' . $pendingCount . ')',
    'approved' => 'Jóváhagyva (' . $countApproved . ')',
    'rejected' => 'Elutasítva (' . $countRejected . ')',
    'all'      => 'Összes',
  ];
  foreach ($subTabs as $key => $label):
    $isActive = $statusFilter === $key;
  ?>
    <a href="<?= BASE_URL ?>/admin/members.php?tab=applications&status=<?= e($key) ?>"
       class="tab-link<?= $isActive ? ' active' : '' ?>">
      <?= e($label) ?>
    </a>
  <?php endforeach; ?>
</div>

<!-- Applications table -->
<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Beérkezett</th>
          <th>Jelölt</th>
          <th>Telefon / Város</th>
          <th>Hozzájárulások</th>
          <th>Státusz</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($applications)): ?>
          <tr>
            <td colspan="6">
              <div class="empty-state">
                <div class="empty-icon">📋</div>
                <p>Nincs megjeleníthető kérelem ebben a kategóriában.</p>
              </div>
            </td>
          </tr>
        <?php else: foreach ($applications as $app): ?>
          <?php
            $submittedFmt = (new DateTime($app['submitted_at']))->format('Y.m.d H:i');
            $isPending    = $app['status'] === 'pending';
          ?>
          <tr>
            <td style="font-size:13px;white-space:nowrap;"><?= e($submittedFmt) ?></td>
            <td>
              <div class="td-name"><?= e($app['lastname'] . ' ' . $app['firstname']) ?></div>
              <div class="td-sub"><?= e($app['email']) ?></div>
            </td>
            <td style="font-size:13px;color:var(--text-muted);">
              <?php if ($app['phone']): ?><div><?= e($app['phone']) ?></div><?php endif; ?>
              <?php if ($app['city']): ?><div><?= e($app['city']) ?></div><?php endif; ?>
            </td>
            <td>
              <div style="display:flex;gap:4px;flex-wrap:wrap;">
                <span title="E-mail láthatóság" style="display:inline-flex;align-items:center;gap:3px;font-size:11px;padding:2px 7px;border-radius:99px;background:<?= $app['consent_email'] ? 'var(--success-light,#dcfce7)' : 'var(--border)' ?>;color:<?= $app['consent_email'] ? 'var(--success,#16a34a)' : 'var(--text-muted)' ?>;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                  E-mail
                </span>
                <span title="Fénykép megjelenítés" style="display:inline-flex;align-items:center;gap:3px;font-size:11px;padding:2px 7px;border-radius:99px;background:<?= $app['consent_photo'] ? 'var(--success-light,#dcfce7)' : 'var(--border)' ?>;color:<?= $app['consent_photo'] ? 'var(--success,#16a34a)' : 'var(--text-muted)' ?>;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                  Fotó
                </span>
                <span title="Szabályzat elfogadva" style="display:inline-flex;align-items:center;gap:3px;font-size:11px;padding:2px 7px;border-radius:99px;background:<?= $app['consent_rules'] ? 'var(--success-light,#dcfce7)' : 'var(--border)' ?>;color:<?= $app['consent_rules'] ? 'var(--success,#16a34a)' : 'var(--text-muted)' ?>;">
                  <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                  Szabályzat
                </span>
              </div>
            </td>
            <td>
              <?php if ($app['status'] === 'pending'): ?>
                <span class="badge" style="background:var(--warning-light,#fef3c7);color:var(--warning,#d97706);">Függőben</span>
              <?php elseif ($app['status'] === 'approved'): ?>
                <span class="badge badge-active">Jóváhagyva</span>
              <?php else: ?>
                <span class="badge badge-inactive">Elutasítva</span>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap;display:flex;gap:4px;flex-wrap:wrap;align-items:center;">
              <button type="button" class="btn btn-ghost btn-sm"
                      data-detail='<?= htmlspecialchars(json_encode([
                          'id'          => $app['id'],
                          'name'        => $app['lastname'] . ' ' . $app['firstname'],
                          'email'       => $app['email'],
                          'phone'       => $app['phone'] ?? '',
                          'dateofbirth' => $app['dateofbirth'] ?? '',
                          'zipcode'     => $app['zipcode'] ?? '',
                          'city'        => $app['city'] ?? '',
                          'address'     => $app['address'] ?? '',
                          'message'     => $app['message'] ?? '',
                          'consent_email' => (bool)$app['consent_email'],
                          'consent_photo' => (bool)$app['consent_photo'],
                          'consent_rules' => (bool)$app['consent_rules'],
                          'submitted'   => $submittedFmt,
                          'status'      => $app['status'],
                      ], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
                      onclick="openDetailModal(this)">
                Részletek
              </button>
              <?php if ($isPending): ?>
                <button type="button" class="btn btn-primary btn-sm"
                        data-approve-id="<?= $app['id'] ?>"
                        data-name="<?= e($app['lastname'] . ' ' . $app['firstname']) ?>"
                        data-email="<?= e($app['email']) ?>"
                        data-phone="<?= e($app['phone'] ?? '') ?>"
                        data-submitted="<?= e($submittedFmt) ?>"
                        onclick="openApproveModal(this)">
                  Jóváhagyás
                </button>
                <button type="button" class="btn btn-ghost btn-sm"
                        data-reject-id="<?= $app['id'] ?>"
                        data-name="<?= e($app['lastname'] . ' ' . $app['firstname']) ?>"
                        onclick="openRejectModal(this)">
                  Elutasítás
                </button>
              <?php elseif ($app['status'] === 'approved' && $app['user_id']): ?>
                <a href="<?= BASE_URL ?>/admin/member-detail.php?id=<?= (int)$app['user_id'] ?>" class="btn btn-ghost btn-sm">Tag megtekintése</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- DETAIL MODAL -->
<div id="detail-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;padding:16px;">
  <div style="background:var(--card-bg,#fff);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.18);width:100%;max-width:540px;max-height:90vh;overflow-y:auto;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px;border-bottom:1px solid var(--border);">
      <h2 style="font-size:16px;font-weight:700;margin:0;">Kérelem részletei</h2>
      <button type="button" onclick="closeDetailModal()" style="background:none;border:none;cursor:pointer;color:var(--text-muted);padding:4px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div style="padding:20px 24px 24px;" id="detail-body"></div>
  </div>
</div>

<!-- APPROVE MODAL -->
<div id="approve-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;padding:16px;">
  <div style="background:var(--card-bg,#fff);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.18);width:100%;max-width:520px;max-height:90vh;overflow-y:auto;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px 0;">
      <h2 style="font-size:16px;font-weight:700;margin:0;">Tagfelvétel jóváhagyása</h2>
      <button type="button" onclick="closeApproveModal()" style="background:none;border:none;cursor:pointer;color:var(--text-muted);padding:4px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div style="margin:16px 24px 0;padding:14px 16px;background:var(--bg,#f8fafc);border-radius:8px;border:1px solid var(--border);">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:8px;">Kérelmező adatai</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 16px;font-size:13px;">
        <div><span style="color:var(--text-muted);">Név:</span> <strong id="ai-name"></strong></div>
        <div><span style="color:var(--text-muted);">E-mail:</span> <span id="ai-email"></span></div>
        <div><span style="color:var(--text-muted);">Telefon:</span> <span id="ai-phone">—</span></div>
        <div><span style="color:var(--text-muted);">Beérkezett:</span> <span id="ai-submitted"></span></div>
      </div>
    </div>
    <form method="post" action="<?= BASE_URL ?>/actions/application-process.php" id="approve-form" style="padding:16px 24px 24px;">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="action" value="approve">
      <input type="hidden" name="app_id" id="approve-app-id">
      <div class="form-grid" style="margin-top:12px;">
        <div class="form-group full">
          <label>Felhasználónév <span style="color:var(--danger,#dc2626);">*</span></label>
          <input type="text" name="username" id="approve-username" required placeholder="pl. nagy.janos"
                 pattern="[a-z0-9._\-]+" title="Csak kisbetű, szám, pont, kötőjel és aláhúzás megengedett">
          <small style="color:var(--text-muted);font-size:11px;">Automatikusan javasolt, de szerkeszthető</small>
        </div>
        <div class="form-group full">
          <label>Tagság kezdete</label>
          <input type="date" name="member_since" id="approve-member-since" value="<?= date('Y-m-d') ?>">
        </div>
      </div>
      <div style="margin:12px 0 4px;padding:11px 14px;background:var(--bg,#f8fafc);border:1px solid var(--border);border-radius:8px;font-size:12px;color:var(--text-muted);display:flex;align-items:center;gap:8px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="flex-shrink:0;color:var(--primary);"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        A jelszó automatikusan generálódik és csak az üdvözlő e-mailben kerül elküldésre a tag számára.
      </div>
      <label class="notif-row" style="margin-top:12px;margin-bottom:20px;">
        <input type="checkbox" name="send_welcome_email" value="1" id="approve-welcome" checked>
        <span class="notif-slider"></span>
        <span class="notif-info">
          <strong>Üdvözlő e-mail küldése</strong>
          <small>Elküldi a belépési adatokat és a jelszót a kérelmező e-mail-címére</small>
        </span>
      </label>
      <div style="display:flex;gap:8px;justify-content:flex-end;">
        <button type="button" onclick="closeApproveModal()" class="btn btn-ghost">Mégse</button>
        <button type="submit" class="btn btn-primary">Jóváhagyás és fiók létrehozása</button>
      </div>
    </form>
  </div>
</div>

<!-- REJECT MODAL -->
<div id="reject-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;padding:16px;">
  <div style="background:var(--card-bg,#fff);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.18);width:100%;max-width:420px;">
    <div style="padding:24px;">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
        <div style="width:40px;height:40px;border-radius:50%;background:var(--danger-light,#fee2e2);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="var(--danger,#dc2626)" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </div>
        <div>
          <h2 style="font-size:16px;font-weight:700;margin:0;">Kérelem elutasítása</h2>
          <p style="font-size:13px;color:var(--text-muted);margin:2px 0 0;">Biztosan elutasítja <strong id="reject-name"></strong> kérelmét?</p>
        </div>
      </div>
      <p style="font-size:13px;color:var(--text-muted);margin-bottom:20px;">Ez a művelet nem vonható vissza. A kérelem elutasítottként lesz megjelölve.</p>
      <form method="post" action="<?= BASE_URL ?>/actions/application-process.php">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="app_id" id="reject-app-id">
        <div style="display:flex;gap:8px;justify-content:flex-end;">
          <button type="button" onclick="closeRejectModal()" class="btn btn-ghost">Mégse</button>
          <button type="submit" class="btn btn-primary" style="background:var(--danger,#dc2626);border-color:var(--danger,#dc2626);">Elutasítás</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openDetailModal(btn) {
  var d = JSON.parse(btn.getAttribute('data-detail'));
  function row(label, val) {
    if (!val) return '';
    return '<div style="display:flex;gap:12px;padding:8px 0;border-bottom:1px solid var(--border,#e5e7eb);font-size:13px;">'
      + '<span style="color:var(--text-muted);min-width:130px;flex-shrink:0;">' + label + '</span>'
      + '<span style="font-weight:500;word-break:break-word;">' + val + '</span></div>';
  }
  function consent(label, val) {
    var color = val ? 'var(--success,#16a34a)' : 'var(--text-muted)';
    var icon  = val
      ? '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;"><polyline points="20 6 9 17 4 12"/></svg>'
      : '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
    return '<div style="display:flex;align-items:flex-start;gap:8px;padding:6px 0;font-size:13px;">'
      + '<span style="color:' + color + ';flex-shrink:0;margin-top:1px;">' + icon + '</span>'
      + '<span style="color:' + color + ';">' + label + '</span></div>';
  }
  var statusLabel = {'pending':'Függőben','approved':'Jóváhagyva','rejected':'Elutasítva'}[d.status] || d.status;
  var statusColor = {'pending':'var(--warning,#d97706)','approved':'var(--success,#16a34a)','rejected':'var(--danger,#dc2626)'}[d.status] || '';
  var html = '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">'
    + '<div><div style="font-size:17px;font-weight:700;">' + d.name + '</div>'
    + '<div style="font-size:12px;color:var(--text-muted);margin-top:2px;">' + d.email + '</div></div>'
    + '<span style="font-size:12px;font-weight:600;padding:3px 10px;border-radius:99px;background:rgba(0,0,0,.06);color:' + statusColor + ';">' + statusLabel + '</span>'
    + '</div>';
  html += '<div style="background:var(--bg,#f8fafc);border-radius:8px;padding:4px 12px;margin-bottom:16px;">';
  html += row('E-mail', d.email);
  html += row('Telefon', d.phone);
  html += row('Születési dátum', d.dateofbirth);
  html += row('Irányítószám', d.zipcode);
  html += row('Város', d.city);
  html += row('Cím', d.address);
  html += row('Beérkezett', d.submitted);
  html += '</div>';
  if (d.message) {
    html += '<div style="margin-bottom:16px;">'
      + '<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:6px;">Megjegyzés / Motiváció</div>'
      + '<div style="background:var(--bg,#f8fafc);border-radius:8px;padding:12px 14px;font-size:13px;line-height:1.6;white-space:pre-wrap;">' + d.message.replace(/</g,'&lt;') + '</div>'
      + '</div>';
  }
  html += '<div style="margin-top:4px;">'
    + '<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:8px;">Hozzájárulások</div>'
    + consent('E-mail cím láthatóság az eseményszervezési levelezésekben', d.consent_email)
    + consent('Fényképek megjelenítése a weboldalon és social-media felületeken', d.consent_photo)
    + consent('Adatvédelmi tájékoztató, Alapszabály és Részvételi feltételek elfogadva', d.consent_rules)
    + '</div>';
  document.getElementById('detail-body').innerHTML = html;
  document.getElementById('detail-overlay').style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeDetailModal() {
  document.getElementById('detail-overlay').style.display = 'none';
  document.body.style.overflow = '';
}
document.getElementById('detail-overlay').addEventListener('click', function(e) {
  if (e.target === this) closeDetailModal();
});

function slugify(str) {
  var map = {'á':'a','é':'e','í':'i','ó':'o','ö':'o','ő':'o','ú':'u','ü':'u','ű':'u',
             'Á':'a','É':'e','Í':'i','Ó':'o','Ö':'o','Ő':'o','Ú':'u','Ü':'u','Ű':'u'};
  return str.toLowerCase()
    .replace(/[áéíóöőúüűÁÉÍÓÖŐÚÜŰ]/g, function(c){ return map[c] || c; })
    .replace(/\s+/g, '.')
    .replace(/[^a-z0-9._\-]/g, '')
    .replace(/\.{2,}/g, '.');
}

function openApproveModal(btn) {
  var appId     = btn.dataset.approveId;
  var name      = btn.dataset.name;
  var email     = btn.dataset.email;
  var phone     = btn.dataset.phone || '—';
  var submitted = btn.dataset.submitted;
  document.getElementById('ai-name').textContent      = name;
  document.getElementById('ai-email').textContent     = email;
  document.getElementById('ai-phone').textContent     = phone;
  document.getElementById('ai-submitted').textContent = submitted;
  document.getElementById('approve-app-id').value     = appId;
  var parts = name.trim().split(' ');
  var suggested = '';
  if (parts.length >= 2) {
    suggested = slugify(parts[parts.length - 1]) + '.' + slugify(parts[0]);
  } else {
    suggested = slugify(parts[0] || '');
  }
  document.getElementById('approve-username').value = suggested;
  var overlay = document.getElementById('approve-overlay');
  overlay.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  setTimeout(function(){ document.getElementById('approve-username').focus(); }, 80);
}
function closeApproveModal() {
  document.getElementById('approve-overlay').style.display = 'none';
  document.body.style.overflow = '';
}

function openRejectModal(btn) {
  document.getElementById('reject-name').textContent = btn.dataset.name;
  document.getElementById('reject-app-id').value     = btn.dataset.rejectId;
  var overlay = document.getElementById('reject-overlay');
  overlay.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}
function closeRejectModal() {
  document.getElementById('reject-overlay').style.display = 'none';
  document.body.style.overflow = '';
}

document.getElementById('approve-overlay').addEventListener('click', function(e) {
  if (e.target === this) closeApproveModal();
});
document.getElementById('reject-overlay').addEventListener('click', function(e) {
  if (e.target === this) closeRejectModal();
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeApproveModal();
    closeRejectModal();
    closeDetailModal();
  }
});
</script>

<?php endif; ?>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
