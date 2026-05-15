<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$pdo = getDb();
require_once __DIR__ . '/../includes/user-schema.php';
ensureUserSchema($pdo);
$members = $pdo->query("SELECT id, firstname, lastname, email, city, member_since, last_payment, level, points, profile_picture, active, role, locked_at FROM users ORDER BY role DESC, lastname, firstname")->fetchAll();

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
    <a href="<?= BASE_URL ?>/admin/member-add.php" class="btn btn-primary btn-sm">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      Tag hozzáadása
    </a>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table id="member-table">
      <thead>
        <tr>
          <th>Tag</th>
          <th>Szerepkör</th>
          <th>Város</th>
          <th>Tagság kezdete</th>
          <th>Utolsó fizetés</th>
          <th>Szint</th>
          <th>Pontok</th>
          <th>Tagság státusza</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($members as $m): ?>
        <tr>
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
            <span class="badge <?= $m['role'] === 'admin' ? 'badge-admin' : 'badge-user' ?>"><?= $m['role'] === 'admin' ? 'Admin' : 'Tag' ?></span>
            <?php if (!empty($m['locked_at'])): ?>
              <span class="badge badge-inactive" style="margin-left:4px;" title="Zárolva: <?= e((new DateTime($m['locked_at']))->format('Y.m.d H:i')) ?>">🔒 Zárolt</span>
            <?php endif; ?>
          </td>
          <td><?= e($m['city'] ?? '—') ?></td>
          <td><?= formatDate($m['member_since']) ?></td>
          <td><?= formatDate($m['last_payment']) ?></td>
          <td><span class="level-badge <?= getLevelClass($m['level']) ?>"><?= getLevelLabel($m['level']) ?></span></td>
          <td><strong><?= number_format($m['points']) ?></strong></td>
          <?php $ms = getMemberStatus($m['last_payment']); ?>
          <td><span class="badge <?= getMemberStatusClass($ms) ?>"><?= getMemberStatusLabel($ms) ?></span></td>
          <td>
            <a href="<?= BASE_URL ?>/admin/member-detail.php?id=<?= $m['id'] ?>" class="btn btn-ghost btn-sm">Megtekintés</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($members)): ?>
        <tr><td colspan="9">
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

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
