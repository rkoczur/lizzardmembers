<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/user-schema.php';
require_once __DIR__ . '/../includes/ip-block-schema.php';
requireAdminOrVezeto();

$pdo = getDb();
ensureUserSchema($pdo);
ensureIpBlockSchema($pdo);

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$lockedUsers = $pdo->query("
    SELECT id, firstname, lastname, username, email, login_attempts, locked_at
    FROM users
    WHERE locked_at IS NOT NULL
    ORDER BY locked_at DESC
")->fetchAll();

$blockedIps = $pdo->query("
    SELECT ip, attempts, last_attempt
    FROM ip_blocks
    WHERE blocked = 1
    ORDER BY last_attempt DESC
")->fetchAll();

$recentAttempts = $pdo->query("
    SELECT ip, attempts, last_attempt
    FROM ip_blocks
    WHERE blocked = 0
    ORDER BY last_attempt DESC
    LIMIT 20
")->fetchAll();

$pageTitle  = 'Biztonság';
$activePage = 'security';
include __DIR__ . '/../includes/admin-header.php';
?>

<?php if ($flash_success): ?>
  <div class="alert alert-success" data-auto-dismiss><?= e($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_error): ?>
  <div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="tab-nav">
  <a href="<?= BASE_URL ?>/admin/security.php" class="tab-link<?= $activePage === 'security' ? ' active' : '' ?>">Biztonság</a>
  <a href="<?= BASE_URL ?>/admin/logs.php" class="tab-link<?= $activePage === 'logs' ? ' active' : '' ?>">Naplók</a>
  <a href="<?= BASE_URL ?>/admin/settings.php" class="tab-link<?= $activePage === 'settings' ? ' active' : '' ?>">Beállítások</a>
  <a href="<?= BASE_URL ?>/admin/orphaned-assets.php" class="tab-link<?= $activePage === 'tools' ? ' active' : '' ?>">Felesleges fájlok</a>
  <?php if (isRootAdmin()): ?><a href="<?= BASE_URL ?>/admin/backup.php" class="tab-link<?= $activePage === 'backup' ? ' active' : '' ?>">Mentés</a><?php endif; ?>
</div>

<div class="page-header">
  <h1>Biztonság</h1>
</div>

<!-- Locked accounts -->
<div class="card" style="margin-bottom:24px;">
  <div class="card-header">
    <h2>🔒 Zárolt fiókok</h2>
    <span class="badge <?= count($lockedUsers) > 0 ? 'badge-inactive' : 'badge-active' ?>">
      <?= count($lockedUsers) ?> db
    </span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Tag</th>
          <th>Hibás kísérletek</th>
          <th>Zárolás ideje</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($lockedUsers)): ?>
          <tr><td colspan="4">
            <div class="empty-state">
              <div class="empty-icon">✅</div>
              <p>Nincs zárolt fiók.</p>
            </div>
          </td></tr>
        <?php else: ?>
          <?php foreach ($lockedUsers as $u): ?>
          <tr>
            <td>
              <div class="td-name"><?= e($u['lastname'] . ' ' . $u['firstname']) ?></div>
              <div class="td-sub">@<?= e($u['username']) ?> — <?= e($u['email']) ?></div>
            </td>
            <td><span class="badge badge-inactive"><?= (int)$u['login_attempts'] ?> / 3</span></td>
            <td style="white-space:nowrap;font-size:13px;"><?= e((new DateTime($u['locked_at']))->format('Y.m.d H:i:s')) ?></td>
            <td style="display:flex;gap:6px;">
              <?php if (isAdmin()): ?>
              <form method="post" action="<?= BASE_URL ?>/actions/member-unlock.php">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-primary btn-sm">Feloldás</button>
              </form>
              <?php endif; ?>
              <a href="<?= BASE_URL ?>/admin/member-detail.php?id=<?= $u['id'] ?>" class="btn btn-ghost btn-sm">Megtekintés</a>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Blocked IPs -->
<div class="card" style="margin-bottom:24px;">
  <div class="card-header">
    <h2>🚫 Zárolt IP-címek</h2>
    <span class="badge <?= count($blockedIps) > 0 ? 'badge-inactive' : 'badge-active' ?>">
      <?= count($blockedIps) ?> db
    </span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>IP-cím</th>
          <th>Kísérletek</th>
          <th>Utolsó kísérlet</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($blockedIps)): ?>
          <tr><td colspan="4">
            <div class="empty-state">
              <div class="empty-icon">✅</div>
              <p>Nincs zárolt IP-cím.</p>
            </div>
          </td></tr>
        <?php else: ?>
          <?php foreach ($blockedIps as $row): ?>
          <tr>
            <td><code style="font-size:13px;"><?= e($row['ip']) ?></code></td>
            <td><span class="badge badge-inactive"><?= (int)$row['attempts'] ?> / 3</span></td>
            <td style="white-space:nowrap;font-size:13px;"><?= e((new DateTime($row['last_attempt']))->format('Y.m.d H:i:s')) ?></td>
            <td>
              <?php if (isAdmin()): ?>
              <form method="post" action="<?= BASE_URL ?>/actions/ip-unblock.php">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="ip" value="<?= e($row['ip']) ?>">
                <button type="submit" class="btn btn-primary btn-sm">Feloldás</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Recent failed IP attempts (not yet blocked) -->
<?php if (!empty($recentAttempts)): ?>
<div class="card">
  <div class="card-header">
    <h2>⚠️ Figyelés alatt álló IP-k</h2>
    <span style="font-size:12px;color:var(--text-muted);">Ismeretlen felhasználónévvel próbálkozó, de még nem zárolt IP-k</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>IP-cím</th>
          <th>Kísérletek</th>
          <th>Utolsó kísérlet</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentAttempts as $row): ?>
        <tr>
          <td><code style="font-size:13px;"><?= e($row['ip']) ?></code></td>
          <td>
            <span class="badge badge-overdue"><?= (int)$row['attempts'] ?> / 3</span>
          </td>
          <td style="white-space:nowrap;font-size:13px;"><?= e((new DateTime($row['last_attempt']))->format('Y.m.d H:i:s')) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
