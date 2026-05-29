<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
requireUser();

$pdo    = getDb();
ensureFutureToursSchema($pdo);
$userId = getCurrentUserId();
$stmt   = $pdo->prepare("
    SELECT u.*, COALESCE(SUM(t.points), 0) AS computed_points
    FROM users u
    LEFT JOIN tour_members tm ON tm.user_id = u.id
    LEFT JOIN tours t ON t.id = tm.tour_id
    WHERE u.id = ?
    GROUP BY u.id
    LIMIT 1
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$memberStatus      = getMemberStatus($user['last_payment']);
$memberStatusLabel = getMemberStatusLabel($memberStatus);
$memberStatusClass = getMemberStatusClass($memberStatus);

$levelStart    = [1 => 0, 2 => 3, 3 => 25, 4 => 50, 5 => 100, 6 => 170, 7 => 250, 8 => 330, 9 => 500];
$levelNext     = [1 => 3, 2 => 25, 3 => 50, 4 => 100, 5 => 170, 6 => 250, 7 => 330, 8 => 500, 9 => 500];
$currentPoints = (int)$user['computed_points'];
$currentLevel  = getLevelFromPoints($currentPoints);
$isMaxLevel    = $currentLevel >= 9;
if (!$isMaxLevel) {
    $startPts  = $levelStart[$currentLevel] ?? 0;
    $nextPts   = $levelNext[$currentLevel]  ?? 500;
    $range     = $nextPts - $startPts;
    $progress  = $range > 0 ? min(100, (int)((($currentPoints - $startPts) / $range) * 100)) : 100;
} else {
    $progress  = 100;
    $nextPts   = 500;
}

$myToursStmt = $pdo->prepare("
    SELECT ft.name, ft.start_date, ft.participation_fee, ft.region,
           fta.status, fta.paid_at, fta.applied_at, ft.id AS tour_id,
           c.name_hu AS country_name, c.flag_filename AS country_flag
    FROM future_tour_applications fta
    JOIN future_tours ft ON ft.id = fta.future_tour_id
    LEFT JOIN countries c ON c.code = ft.country
    WHERE fta.user_id = ? AND fta.status != 'cancelled' AND ft.status != 'cancelled'
    ORDER BY ft.start_date ASC
");
$myToursStmt->execute([$userId]);
$myFutureTours  = $myToursStmt->fetchAll();
$hasUnpaidTours = array_filter($myFutureTours, fn($t) => $t['status'] === 'confirmed' && !$t['paid_at']);

$pageTitle  = 'Vezérlőpult';
$activePage = 'dashboard';
include __DIR__ . '/../includes/user-header.php';
?>

<div style="margin-bottom:24px;">
  <h1 style="font-size:22px;font-weight:700;">Üdv újra itt, <?= e($user['firstname'] ?? 'Tag') ?>!</h1>
  <p class="text-muted" style="margin-top:4px;">Íme a tagság áttekintése.</p>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:24px;">
  <div class="stat-card">
    <div class="stat-icon">⭐</div>
    <div class="stat-label">Pontok</div>
    <div class="stat-value" style="color:var(--primary)"><?= number_format($currentPoints) ?></div>
  </div>
  <?php $lvlImg = getLevelImageFilename($currentLevel); ?>
  <div class="stat-card" style="display:flex;align-items:stretch;padding:0;overflow:hidden;">
    <div style="flex:1;min-width:0;padding:20px;">
      <div class="stat-icon">🏅</div>
      <div class="stat-label">Fokozat</div>
      <div class="stat-value" style="font-size:19px;margin-top:6px;"><?= getLevelLabel($currentLevel) ?></div>
    </div>
    <?php if ($lvlImg): ?>
      <div class="stat-level-img-wrap">
        <img src="<?= BASE_URL ?>/assets/img/<?= e($lvlImg) ?>"
             alt="<?= e(getLevelLabel($currentLevel)) ?>">
      </div>
    <?php endif; ?>
  </div>
  <div class="stat-card">
    <div class="stat-icon">📅</div>
    <div class="stat-label">Tagság kezdete</div>
    <div class="stat-value" style="font-size:16px;"><?= formatDate($user['member_since']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">💳</div>
    <div class="stat-label">Utolsó fizetés</div>
    <div class="stat-value" style="font-size:16px;"><?= formatDate($user['last_payment']) ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon"><?= $memberStatus === 'active' ? '✅' : ($memberStatus === 'overdue' ? '⚠️' : '❌') ?></div>
    <div class="stat-label">Tagság státusza</div>
    <div class="stat-value" style="font-size:14px;">
      <span class="badge <?= $memberStatusClass ?>" style="font-size:13px;padding:4px 12px;"><?= $memberStatusLabel ?></span>
    </div>
  </div>
</div>

<!-- Level Progress -->
<div class="card mb-6">
  <div class="card-header">
    <h2>Szint előrehaladás</h2>
    <span class="level-badge <?= getLevelClass($currentLevel) ?>"><?= getLevelLabel($currentLevel) ?> — <?= $currentLevel ?>. szint</span>
  </div>
  <div class="card-body">
    <?php if (!$isMaxLevel): ?>
      <?php
        $currentImg = getLevelImageFilename($currentLevel);
        $nextImg    = getLevelImageFilename($currentLevel + 1);
      ?>
      <div style="display:flex;align-items:center;gap:14px;">
        <!-- Current level image -->
        <div style="flex-shrink:0;text-align:center;width:60px;">
          <?php if ($currentImg): ?>
            <img src="<?= BASE_URL ?>/assets/img/<?= e($currentImg) ?>"
                 style="width:52px;height:52px;object-fit:contain;" alt="<?= e(getLevelLabel($currentLevel)) ?>">
          <?php else: ?>
            <div style="width:52px;height:52px;border-radius:50%;background:var(--border);display:flex;align-items:center;justify-content:center;font-size:22px;margin:0 auto;">⭐</div>
          <?php endif; ?>
          <div style="font-size:11px;color:var(--text-muted);margin-top:4px;line-height:1.3;"><?= e(getLevelLabel($currentLevel)) ?></div>
        </div>

        <!-- Bar + labels -->
        <div style="flex:1;min-width:0;">
          <div style="display:flex;justify-content:space-between;font-size:12px;color:var(--text-muted);margin-bottom:6px;">
            <span><?= number_format($currentPoints) ?> pont</span>
            <span><?= number_format($nextPts) ?> pont</span>
          </div>
          <div style="background:var(--border);border-radius:99px;height:10px;overflow:hidden;">
            <div style="background:var(--primary);width:<?= $progress ?>%;height:100%;border-radius:99px;transition:width .5s;"></div>
          </div>
          <p style="margin-top:6px;font-size:12px;color:var(--text-muted);text-align:center;">
            <?= number_format($nextPts - $currentPoints) ?> pont hiányzik a(z) <?= e(getLevelLabel($currentLevel + 1)) ?> fokozatig
          </p>
        </div>

        <!-- Next level image -->
        <div style="flex-shrink:0;text-align:center;width:60px;">
          <?php if ($nextImg): ?>
            <img src="<?= BASE_URL ?>/assets/img/<?= e($nextImg) ?>"
                 style="width:52px;height:52px;object-fit:contain;opacity:.35;filter:grayscale(40%);" alt="<?= e(getLevelLabel($currentLevel + 1)) ?>">
          <?php endif; ?>
          <div style="font-size:11px;color:var(--text-muted);margin-top:4px;line-height:1.3;"><?= e(getLevelLabel($currentLevel + 1)) ?></div>
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
          <p style="color:var(--success);font-weight:600;">🎉 Elérted a legmagasabb fokozatot – Ezredes!</p>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Applied future tours tile -->
<?php if (!empty($myFutureTours)): ?>
<div class="card" style="margin-bottom:20px;">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h2>
      Jelentkezéseim a meghirdetett túrákra
      <?php if ($hasUnpaidTours): ?>
        <span style="background:var(--danger);color:#fff;border-radius:99px;padding:1px 8px;font-size:11px;font-weight:700;margin-left:6px;vertical-align:middle;"><?= count($hasUnpaidTours) ?></span>
      <?php endif; ?>
    </h2>
    <a href="<?= BASE_URL ?>/user/future-tours.php" class="btn btn-ghost btn-sm">Összes túra</a>
  </div>
  <div class="card-body" style="padding:0;">
    <table style="width:100%;border-collapse:collapse;font-size:13.5px;">
      <tbody>
        <?php foreach ($myFutureTours as $mt): ?>
        <?php $unpaid = $mt['status'] === 'confirmed' && !$mt['paid_at']; ?>
        <tr style="border-bottom:1px solid var(--border);<?= $unpaid ? 'background:var(--danger-bg,#fef2f2);' : '' ?>">
          <td style="padding:11px 16px;">
            <div style="font-weight:600;"><?= e($mt['name']) ?></div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px;"><?= $mt['start_date'] ? formatDate($mt['start_date']) : '—' ?></div>
          </td>
          <td style="padding:11px 16px;white-space:nowrap;font-size:13px;">
            <?php if ($mt['country_flag']): ?>
              <img src="<?= e(getFlagUrl($mt['country_flag'])) ?>"
                   style="width:16px;height:11px;object-fit:cover;vertical-align:middle;border:1px solid var(--border);border-radius:1px;margin-right:5px;" alt="">
            <?php endif; ?>
            <?= e($mt['country_name'] ?? '—') ?>
            <?php if ($mt['region']): ?>
              <div style="font-size:11px;color:var(--text-muted);margin-top:2px;"><?= e($mt['region']) ?></div>
            <?php endif; ?>
          </td>
          <td style="padding:11px 16px;white-space:nowrap;">
            <?php if ($mt['status'] === 'confirmed' && $mt['participation_fee'] !== null && !$mt['paid_at']): ?>
              <span style="display:inline-flex;align-items:center;gap:5px;color:var(--danger,#c0392b);font-size:12.5px;font-weight:600;">⚠ Részvételi díj befizetése szükséges!</span>
            <?php elseif ($mt['status'] === 'confirmed' && $mt['participation_fee'] !== null && $mt['paid_at']): ?>
              <span style="display:inline-flex;align-items:center;gap:5px;color:var(--success,#16a34a);font-size:12.5px;font-weight:600;">✓ Részvételi díj rendezve</span>
            <?php elseif ($mt['status'] === 'confirmed'): ?>
              <span class="badge badge-active">Megerősített</span>
            <?php else: ?>
              <span style="background:var(--warning-bg,#fffbeb);color:var(--warning,#b45309);border-radius:4px;padding:2px 8px;font-size:11.5px;font-weight:600;border:1px solid var(--warning,#f59e0b);">Várólistán</span>
            <?php endif; ?>
          </td>
          <td style="padding:11px 16px;text-align:right;">
            <a href="<?= BASE_URL ?>/user/future-tour-detail.php?id=<?= (int)$mt['tour_id'] ?>" class="btn btn-ghost btn-sm">Részletek</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Profile summary -->
<div class="card">
  <div class="card-header">
    <h2>Adataim</h2>
    <a href="<?= BASE_URL ?>/user/profile.php" class="btn btn-ghost btn-sm">Profil szerkesztése</a>
  </div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
      <div>
        <div class="text-muted" style="font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Teljes név</div>
        <div><?= e($user['lastname'] . ' ' . $user['firstname']) ?></div>
      </div>
      <div>
        <div class="text-muted" style="font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">E-mail</div>
        <div><?= e($user['email']) ?></div>
      </div>
      <div>
        <div class="text-muted" style="font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Város</div>
        <div><?= e($user['city'] ?? '—') ?></div>
      </div>
      <div>
        <div class="text-muted" style="font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;">Pólóméret</div>
        <div><?= e($user['tshirt_size'] ?? '—') ?></div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/user-footer.php'; ?>
