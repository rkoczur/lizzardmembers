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

require_once __DIR__ . '/../includes/join-schema.php';
ensureJoinSchema($pdo);
$pendingApps  = $pdo->query("SELECT * FROM member_applications WHERE status='pending' ORDER BY submitted_at DESC LIMIT 8")->fetchAll();
$pendingCount = count($pendingApps);

// ── Szerepkörhöz kötött modulok ────────────────────────────────────────────────

// Könyvelés: folyamatban lévő (kiemelt) tranzakciók — egyesületvezető / helyettes / pénzügyi vezető
$openTxCount = null;
if (canManageFinances()) {
    try {
        $openTxCount = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE highlighted = 1")->fetchColumn();
    } catch (PDOException) { $openTxCount = 0; }
}

// Jóváhagyásra váró túrajelentések — egyesületvezető / helyettes / szakszövetségi vezető
$pendingTours = [];
if (canManageTours()) {
    try {
        $pendingTours = $pdo->query("
            SELECT t.id, t.name, t.tour_date, t.total_km, t.created_at,
                   c.name_hu AS country_name,
                   CONCAT(u.lastname, ' ', u.firstname) AS submitter_name
            FROM tours t
            LEFT JOIN countries c ON c.code = t.country
            LEFT JOIN users u ON u.id = t.submitted_by
            WHERE t.status = 'pending'
            ORDER BY t.created_at DESC
        ")->fetchAll();
    } catch (PDOException) { $pendingTours = []; }
}

// Elfogadásra váró túrajelentkezések — egyesületvezető / helyettes
// Ide tartozik minden nyitott túra jelentkezése, amit az admin még nem fogadott el:
// a vendég jelentkezések ('pending') és a tagok jelentkezései, ahol accepted_at üres.
$pendingTourApps = [];
if (isAdmin()) {
    try {
        require_once __DIR__ . '/../includes/future-tours-schema.php';
        ensureFutureToursSchema($pdo);
        $pendingTourApps = $pdo->query("
            SELECT fta.id, fta.future_tour_id, fta.status, fta.applied_at,
                   fta.member_application_id, fta.user_id,
                   COALESCE(fta.guest_name, CONCAT(u.lastname, ' ', u.firstname)) AS applicant_name,
                   COALESCE(fta.guest_email, u.email) AS applicant_email,
                   ft.name AS tour_name, ft.start_date
            FROM future_tour_applications fta
            JOIN future_tours ft ON ft.id = fta.future_tour_id
            LEFT JOIN users u ON u.id = fta.user_id
            WHERE ft.status = 'open'
              AND (fta.status = 'pending'
                   OR (fta.status IN ('confirmed','waitlist') AND fta.accepted_at IS NULL))
            ORDER BY fta.applied_at ASC
        ")->fetchAll();
    } catch (PDOException) { $pendingTourApps = []; }
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
    <div class="stat-value stat-value-warning"><?= $overdueMembers ?></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon">🚫</div>
    <div class="stat-label">Inaktív</div>
    <div class="stat-value stat-value-danger"><?= $inactiveMembers ?></div>
  </div>
</div>

<?php if ($openTxCount !== null): ?>
<!-- Könyvelés: folyamatban lévő tételek -->
<section class="queue-mod">
  <div class="queue-head">
    <h2 class="queue-title">Pénzügy</h2>
    <a href="<?= BASE_URL ?>/admin/bookkeeping.php" class="queue-all">Könyvelés</a>
  </div>
  <a class="queue-stat" href="<?= BASE_URL ?>/admin/bookkeeping.php?tab=transactions&amp;hl=1">
    <span class="queue-stat-icon">⏳</span>
    <span class="queue-stat-body">
      <span class="queue-stat-label">Folyamatban lévő könyvelési tételek</span>
      <span class="queue-stat-hint">Kiemelt, még nem lezárt tranzakciók a könyvelésben</span>
    </span>
    <span class="queue-stat-value <?= $openTxCount > 0 ? 'is-open' : '' ?>"><?= $openTxCount ?></span>
  </a>
</section>
<?php endif; ?>

<?php if (canManageTours()): ?>
<!-- Jóváhagyásra váró túrajelentések -->
<section class="queue-mod">
  <div class="queue-head">
    <h2 class="queue-title">Jóváhagyásra váró túrajelentések</h2>
    <span class="queue-count <?= count($pendingTours) > 0 ? 'is-open' : '' ?>"><?= count($pendingTours) ?></span>
    <a href="<?= BASE_URL ?>/admin/tours.php" class="queue-all">Összes túra</a>
  </div>
  <?php if (empty($pendingTours)): ?>
    <p class="queue-empty">Nincs jóváhagyásra váró beküldött túra.</p>
  <?php else: ?>
    <ul class="queue-list">
      <?php foreach ($pendingTours as $t): ?>
      <li class="queue-row">
        <div class="queue-row-main">
          <div class="queue-row-title"><?= e($t['name'] ?: ($t['country_name'] ?: 'Névtelen túra')) ?></div>
          <div class="queue-row-meta">
            <?php if ($t['submitter_name']): ?><span>Beküldte: <?= e($t['submitter_name']) ?></span><?php endif; ?>
            <?php if ($t['tour_date']): ?><span><?= formatDate($t['tour_date']) ?></span><?php endif; ?>
            <?php if ($t['total_km']): ?><span><?= e((string)(float)$t['total_km']) ?> km</span><?php endif; ?>
            <span>Beérkezett: <?= e((new DateTime($t['created_at']))->format('Y.m.d H:i')) ?></span>
          </div>
        </div>
        <a href="<?= BASE_URL ?>/admin/tour-detail.php?id=<?= (int)$t['id'] ?>" class="btn btn-primary btn-sm">Áttekintés</a>
      </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php endif; ?>

<?php if (isAdmin()): ?>
<!-- Elfogadásra váró túrajelentkezések -->
<section class="queue-mod">
  <div class="queue-head">
    <h2 class="queue-title">Elfogadásra váró túrajelentkezések</h2>
    <span class="queue-count <?= count($pendingTourApps) > 0 ? 'is-open' : '' ?>"><?= count($pendingTourApps) ?></span>
    <a href="<?= BASE_URL ?>/admin/future-tours.php" class="queue-all">Meghirdetett túrák</a>
  </div>
  <?php if (empty($pendingTourApps)): ?>
    <p class="queue-empty">Nincs elfogadásra váró túrajelentkezés.</p>
  <?php else: ?>
    <ul class="queue-list">
      <?php foreach ($pendingTourApps as $a): ?>
      <li class="queue-row">
        <div class="queue-row-main">
          <div class="queue-row-title">
            <?= e(trim((string)$a['applicant_name']) ?: 'Névtelen jelentkező') ?>
            <?php if ($a['member_application_id']): ?>
              <span class="queue-tag">Tagságra jelentkező</span>
            <?php elseif (!$a['user_id']): ?>
              <span class="queue-tag">Vendég</span>
            <?php endif; ?>
            <?php if ($a['status'] === 'waitlist'): ?>
              <span class="queue-tag queue-tag-wait">Várólista</span>
            <?php endif; ?>
          </div>
          <div class="queue-row-meta">
            <span><?= e($a['tour_name']) ?><?= $a['start_date'] ? ' — ' . formatDate($a['start_date']) : '' ?></span>
            <?php if ($a['applicant_email']): ?><span><?= e($a['applicant_email']) ?></span><?php endif; ?>
            <span>Jelentkezett: <?= e((new DateTime($a['applied_at']))->format('Y.m.d H:i')) ?></span>
          </div>
        </div>
        <a href="<?= BASE_URL ?>/admin/future-tour-applicants.php?id=<?= (int)$a['future_tour_id'] ?>" class="btn btn-primary btn-sm">Kezelés</a>
      </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php endif; ?>

<div class="queue-section-head">
  <h2 class="queue-title">Tagfelvételi kérelmek</h2>
  <a href="<?= BASE_URL ?>/admin/applications.php" class="btn btn-ghost btn-sm">Összes kezelése</a>
</div>

<div class="card">
  <div class="card-header">
    <h2>Függőben lévő kérelmek</h2>
    <?php if ($pendingCount > 0): ?>
      <span class="badge badge-overdue"><?= $pendingCount ?> függőben</span>
    <?php endif; ?>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Beérkezett</th><th>Jelölt</th><th>Telefon / Város</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (empty($pendingApps)): ?>
          <tr><td colspan="4"><div class="empty-state"><div class="empty-icon">✅</div><p>Nincs függőben lévő tagfelvételi kérelem.</p></div></td></tr>
        <?php else: foreach ($pendingApps as $a): ?>
          <tr>
            <td class="td-nowrap-sm"><?= e((new DateTime($a['submitted_at']))->format('Y.m.d H:i')) ?></td>
            <td>
              <div class="td-name"><?= e($a['lastname'] . ' ' . $a['firstname']) ?></div>
              <div class="td-sub"><?= e($a['email']) ?></div>
            </td>
            <td class="td-muted-sm">
              <?php if ($a['phone']): ?><div><?= e($a['phone']) ?></div><?php endif; ?>
              <?php if ($a['city']): ?><div><?= e($a['city']) ?></div><?php endif; ?>
            </td>
            <td><a href="<?= BASE_URL ?>/admin/applications.php?status=pending" class="btn btn-ghost btn-sm">Kezelés</a></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
