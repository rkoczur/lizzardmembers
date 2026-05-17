<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminOrVezeto();

$pdo = getDb();
recalcUserStats($pdo);

$totalMembers    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE active = 1")->fetchColumn();
$activeMembers   = (int)$pdo->query("
    SELECT COUNT(*) FROM users
    WHERE active = 1
      AND (
        role IN ('admin','vezeto')
        OR YEAR(last_payment) = YEAR(CURDATE())
      )
")->fetchColumn();
$overdueMembers  = (int)$pdo->query("
    SELECT COUNT(*) FROM users
    WHERE active = 1 AND role = 'user'
      AND YEAR(last_payment) = YEAR(CURDATE()) - 1
")->fetchColumn();
$inactiveMembers = (int)$pdo->query("
    SELECT COUNT(*) FROM users
    WHERE active = 1 AND role = 'user'
      AND (last_payment IS NULL OR last_payment = '0000-00-00' OR YEAR(last_payment) < YEAR(CURDATE()) - 1)
")->fetchColumn();

$recentMembers = $pdo->query("SELECT id, firstname, lastname, email, member_since, last_payment, level, points, profile_picture, role FROM users ORDER BY created_at DESC LIMIT 8")->fetchAll();

$adminId   = getCurrentUserId();
$adminStmt = $pdo->prepare("
    SELECT u.*, COALESCE(SUM(t.points), 0) AS computed_points
    FROM users u
    LEFT JOIN tour_members tm ON tm.user_id = u.id
    LEFT JOIN tours t ON t.id = tm.tour_id
    WHERE u.id = ?
    GROUP BY u.id
    LIMIT 1
");
$adminStmt->execute([$adminId]);
$adminUser = $adminStmt->fetch();

$adminStatus      = getMemberStatus($adminUser['last_payment']);
$adminStatusLabel = getMemberStatusLabel($adminStatus);
$adminStatusClass = getMemberStatusClass($adminStatus);

$levelStart      = [1 => 0, 2 => 3, 3 => 25, 4 => 50, 5 => 100, 6 => 170, 7 => 250, 8 => 330, 9 => 500];
$levelNext       = [1 => 3, 2 => 25, 3 => 50, 4 => 100, 5 => 170, 6 => 250, 7 => 330, 8 => 500, 9 => 500];
$adminPoints     = (int)$adminUser['computed_points'];
$adminLevel      = getLevelFromPoints($adminPoints);
$adminIsMaxLevel = $adminLevel >= 9;
if (!$adminIsMaxLevel) {
    $adminStartPts  = $levelStart[$adminLevel] ?? 0;
    $adminNextPts   = $levelNext[$adminLevel]  ?? 500;
    $adminRange     = $adminNextPts - $adminStartPts;
    $adminProgress  = $adminRange > 0 ? min(100, (int)((($adminPoints - $adminStartPts) / $adminRange) * 100)) : 100;
} else {
    $adminProgress = 100;
    $adminNextPts  = 500;
}

$pageTitle  = 'Vezérlőpult';
$activePage = 'dashboard';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon">👥</div>
    <div class="stat-label">Összes tag</div>
    <div class="stat-value"><?= $totalMembers ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">✅</div>
    <div class="stat-label">Aktív</div>
    <div class="stat-value"><?= $activeMembers ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">⚠️</div>
    <div class="stat-label">Tagdíj elmaradás</div>
    <div class="stat-value" style="color:var(--warning)"><?= $overdueMembers ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🚫</div>
    <div class="stat-label">Inaktív</div>
    <div class="stat-value" style="color:red"><?= $inactiveMembers ?></div>
  </div>
</div>

<!-- Admin's own membership -->
<div style="display:flex;align-items:center;justify-content:space-between;margin:28px 0 12px;">
  <h2 style="font-size:16px;font-weight:700;">Saját tagságom</h2>
  <a href="<?= BASE_URL ?>/admin/profile.php" class="btn btn-ghost btn-sm">Profil szerkesztése</a>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:16px;">
  <div class="stat-card">
    <div class="stat-icon">⭐</div>
    <div class="stat-label">Pontjaim</div>
    <div class="stat-value" style="color:var(--primary)"><?= number_format($adminPoints) ?></div>
  </div>
  <?php $adminLvlImg = getLevelImageFilename($adminLevel); ?>
  <div class="stat-card" style="display:flex;align-items:stretch;padding:0;overflow:hidden;">
    <div style="flex:1;min-width:0;padding:20px;">
      <div class="stat-icon">🏅</div>
      <div class="stat-label">Fokozatom</div>
      <div class="stat-value" style="font-size:19px;margin-top:6px;"><?= getLevelLabel($adminLevel) ?></div>
    </div>
    <?php if ($adminLvlImg): ?>
      <div class="stat-level-img-wrap">
        <img src="<?= BASE_URL ?>/assets/img/<?= e($adminLvlImg) ?>"
             alt="<?= e(getLevelLabel($adminLevel)) ?>">
      </div>
    <?php endif; ?>
  </div>
  <div class="stat-card">
    <div class="stat-icon">📅</div>
    <div class="stat-label">Tagság kezdete</div>
    <div class="stat-value" style="font-size:16px;"><?= formatDate($adminUser['member_since']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">💳</div>
    <div class="stat-label">Utolsó fizetés</div>
    <div class="stat-value" style="font-size:16px;"><?= formatDate($adminUser['last_payment']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><?= $adminStatus === 'active' ? '✅' : ($adminStatus === 'overdue' ? '⚠️' : '❌') ?></div>
    <div class="stat-label">Tagság státusza</div>
    <div class="stat-value" style="font-size:14px;">
      <span class="badge <?= $adminStatusClass ?>" style="font-size:13px;padding:4px 12px;"><?= $adminStatusLabel ?></span>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:28px;">
  <div class="card-header">
    <h2>Szint előrehaladás</h2>
    <span class="level-badge <?= getLevelClass($adminLevel) ?>"><?= getLevelLabel($adminLevel) ?> — <?= $adminLevel ?>. szint</span>
  </div>
  <div class="card-body">
    <?php if (!$adminIsMaxLevel): ?>
      <?php
        $adminCurrentImg = getLevelImageFilename($adminLevel);
        $adminNextImg    = getLevelImageFilename($adminLevel + 1);
      ?>
      <div style="display:flex;align-items:center;gap:14px;">
        <div style="flex-shrink:0;text-align:center;width:60px;">
          <?php if ($adminCurrentImg): ?>
            <img src="<?= BASE_URL ?>/assets/img/<?= e($adminCurrentImg) ?>"
                 style="width:52px;height:52px;object-fit:contain;" alt="<?= e(getLevelLabel($adminLevel)) ?>">
          <?php else: ?>
            <div style="width:52px;height:52px;border-radius:50%;background:var(--border);display:flex;align-items:center;justify-content:center;font-size:22px;margin:0 auto;">⭐</div>
          <?php endif; ?>
          <div style="font-size:11px;color:var(--text-muted);margin-top:4px;line-height:1.3;"><?= e(getLevelLabel($adminLevel)) ?></div>
        </div>
        <div style="flex:1;min-width:0;">
          <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-muted);margin-bottom:6px;">
            <span><?= number_format($adminPoints) ?> pont</span>
            <span><?= number_format($adminNextPts) ?> pont</span>
          </div>
          <div style="background:var(--border);border-radius:99px;height:10px;overflow:hidden;">
            <div style="background:var(--primary);width:<?= $adminProgress ?>%;height:100%;border-radius:99px;transition:width .5s;"></div>
          </div>
          <p style="margin-top:6px;font-size:12px;color:var(--text-muted);text-align:center;">
            <?= number_format($adminNextPts - $adminPoints) ?> pont hiányzik a(z) <?= e(getLevelLabel($adminLevel + 1)) ?> fokozatig
          </p>
        </div>
        <div style="flex-shrink:0;text-align:center;width:60px;">
          <?php if ($adminNextImg): ?>
            <img src="<?= BASE_URL ?>/assets/img/<?= e($adminNextImg) ?>"
                 style="width:52px;height:52px;object-fit:contain;opacity:.35;filter:grayscale(40%);" alt="<?= e(getLevelLabel($adminLevel + 1)) ?>">
          <?php endif; ?>
          <div style="font-size:11px;color:var(--text-muted);margin-top:4px;line-height:1.3;"><?= e(getLevelLabel($adminLevel + 1)) ?></div>
        </div>
      </div>
    <?php else: ?>
      <?php $maxImg = getLevelImageFilename(9); ?>
      <div style="display:flex;align-items:center;gap:16px;">
        <?php if ($maxImg): ?>
          <img src="<?= BASE_URL ?>/assets/img/<?= e($maxImg) ?>"
               style="width:56px;height:56px;object-fit:contain;flex-shrink:0;" alt="Ezredes">
        <?php endif; ?>
        <div>
          <div style="background:var(--border);border-radius:99px;height:10px;overflow:hidden;margin-bottom:8px;">
            <div style="background:var(--primary);width:100%;height:100%;border-radius:99px;"></div>
          </div>
          <p style="color:var(--success);font-weight:600;">🎉 Elérte a legmagasabb fokozatot – Ezredes!</p>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<div style="display:flex;align-items:center;margin-bottom:12px;">
  <h2 style="font-size:16px;font-weight:700;">Tagok kezelése</h2>
</div>

<div class="card">
  <div class="card-header">
    <h2>Legutóbbi tagok</h2>
    <a href="<?= BASE_URL ?>/admin/members.php" class="btn btn-ghost btn-sm">Összes megtekintése</a>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Tag</th>
          <th>Szerepkör</th>
          <th>Tagság kezdete</th>
          <th>Szint</th>
          <th>Pontok</th>
          <th>Tagság státusza</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentMembers as $m): ?>
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
          <td><span class="badge <?= $m['role'] === 'admin' ? 'badge-admin' : ($m['role'] === 'vezeto' ? 'badge-vezeto' : 'badge-user') ?>"><?= $m['role'] === 'admin' ? 'Admin' : ($m['role'] === 'vezeto' ? 'Vezető' : 'Tag') ?></span></td>
          <td><?= formatDate($m['member_since']) ?></td>
          <td><span class="level-badge <?= getLevelClass($m['level']) ?>"><?= getLevelLabel($m['level']) ?></span></td>
          <td><strong><?= number_format($m['points']) ?></strong></td>
          <?php $ms = getMemberStatus($m['last_payment']); ?>
          <td><span class="badge <?= getMemberStatusClass($ms) ?>"><?= getMemberStatusLabel($ms) ?></span></td>
          <td><a href="<?= BASE_URL ?>/admin/member-detail.php?id=<?= $m['id'] ?>" class="btn btn-ghost btn-sm">Megtekintés</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recentMembers)): ?>
        <tr><td colspan="6" class="empty-state"><p>Még nincsenek tagok.</p></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
