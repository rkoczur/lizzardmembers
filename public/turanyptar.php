<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';

$pdo = getDb();
ensureFutureToursSchema($pdo);

// Nézet: aktuális (nyitott) túrák, vagy a régebbi (lezárt) túrák
$showArchive = !empty($_GET['regi']);
$statusFilter = $showArchive ? "ft.status = 'closed'" : "ft.status = 'open'";

$tours = $pdo->query("
    SELECT ft.*, c.name_hu AS country_name, c.flag_filename AS country_flag,
           (SELECT COUNT(*) FROM future_tour_applications fta WHERE fta.future_tour_id = ft.id AND fta.status = 'confirmed') AS confirmed_count,
           (SELECT COUNT(*) FROM future_tour_applications fta WHERE fta.future_tour_id = ft.id AND fta.status = 'waitlist')  AS waitlist_count
    FROM future_tours ft
    LEFT JOIN countries c ON c.code = ft.country
    WHERE $statusFilter
    ORDER BY ft.start_date " . ($showArchive ? "DESC" : "ASC") . ", ft.created_at DESC
")->fetchAll();

$archiveCount = (int)$pdo->query("SELECT COUNT(*) FROM future_tours WHERE status = 'closed'")->fetchColumn();

$HU_MONTHS = ['január','február','március','április','május','június','július','augusztus','szeptember','október','november','december'];
function huDateRange(string $startYmd, int $numDays, array $m): string {
    $sTs = strtotime($startYmd);
    $sY  = (int)date('Y', $sTs);
    $sMo = (int)date('n', $sTs);
    $sD  = (int)date('j', $sTs);
    if ($numDays <= 1) {
        return $sY . '. ' . $m[$sMo - 1] . ' ' . $sD . '.';
    }
    $eTs = strtotime($startYmd . ' +' . ($numDays - 1) . ' days');
    $eY  = (int)date('Y', $eTs);
    $eMo = (int)date('n', $eTs);
    $eD  = (int)date('j', $eTs);
    if ($sY === $eY && $sMo === $eMo) {
        return $sY . '. ' . $m[$sMo - 1] . ' ' . $sD . '–' . $eD . '.';
    } elseif ($sY === $eY) {
        return $sY . '. ' . $m[$sMo - 1] . ' ' . $sD . '. – ' . $m[$eMo - 1] . ' ' . $eD . '.';
    } else {
        return $sY . '. ' . $m[$sMo - 1] . ' ' . $sD . '. – ' . $eY . '. ' . $m[$eMo - 1] . ' ' . $eD . '.';
    }
}

$pageTitle     = 'Túranaptár';
$activePubPage = 'turanyptar';
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap">
  <div class="pub-page-header">
    <h1><?= $showArchive ? 'Régebbi túrák' : 'Túranaptár' ?></h1>
    <p>
      <?= $showArchive
            ? 'Korábbi, már lezárt túráink.'
            : 'Közelgő és meghirdetett túrák. Jelentkezz még ma!' ?>
    </p>
  </div>

  <?php if ($showArchive): ?>
    <div style="margin-bottom:20px;">
      <a href="<?= BASE_URL ?>/public/turanyptar.php" class="btn btn-ghost btn-sm">← Vissza az aktuális túrákhoz</a>
    </div>
  <?php endif; ?>

  <?php if (empty($tours)): ?>
    <div class="pub-empty-state">
      <div style="font-size:48px;margin-bottom:12px;">🗓️</div>
      <?php if ($showArchive): ?>
        <p>Nincs régebbi, lezárt túra.</p>
      <?php else: ?>
        <p>Jelenleg nincs meghirdetett túra. Kövess minket Facebookon az első értesítésekért!</p>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <?php $currentMonthKey = null; ?>
    <?php foreach ($tours as $t): ?>
      <?php
      $confirmed = (int)$t['confirmed_count'];
      $maxSlots  = (int)$t['max_attendees'];
      $spotsLeft = max(0, $maxSlots - $confirmed);
      $huDateRange = $t['start_date'] ? huDateRange($t['start_date'], (int)$t['num_days'], $HU_MONTHS) : '—';
      $ts        = $t['start_date'] ? strtotime($t['start_date']) : false;
      $monthKey  = $ts ? date('Y-n', $ts) : 'unknown';
      $monthYear = $ts ? date('Y', $ts) : '';
      $monthName = $ts ? $HU_MONTHS[(int)date('n', $ts) - 1] : 'Időpont nélkül';
      ?>
      <?php if ($monthKey !== $currentMonthKey): ?>
        <?php if ($currentMonthKey !== null): ?>
          </div>
        <?php endif; ?>
        <div class="pub-tour-month">
          <div class="pub-tour-month-label">
            <svg class="pub-tour-month-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 20l6-11 4 6 2-3 6 8z"/><circle cx="17" cy="6" r="2"/></svg>
            <?php if ($monthYear !== ''): ?>
              <span class="pub-tour-month-year"><?= e($monthYear) ?></span>
            <?php endif; ?>
            <span class="pub-tour-month-name"><?= e($monthName) ?></span>
          </div>
          <span class="pub-tour-month-line"></span>
        </div>
        <div class="pub-tour-cards">
        <?php $currentMonthKey = $monthKey; ?>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/public/tour-detail.php?id=<?= (int)$t['id'] ?>" class="pub-tour-card">
        <div class="pub-tour-card-img-wrap">
          <?php if (!empty($t['cover_img'])): ?>
            <img src="<?= BASE_URL ?>/assets/uploads/tour-covers/<?= e($t['cover_img']) ?>" class="pub-tour-card-img" alt="<?= e($t['name']) ?>">
          <?php else: ?>
            <div class="pub-tour-card-img-placeholder">
              <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M3 17l5-8 4 6 3-4 5 6H3z"/></svg>
            </div>
          <?php endif; ?>
          <div class="pub-tour-card-rank-overlay">
            <div class="pub-tour-card-lizzardier">
              <img src="<?= BASE_URL ?>/assets/img/ures_small.png" alt="Lizzardier">
              <?php if ($t['lizzardier_points'] !== null): ?>
                <span class="pub-tour-card-lizzardier-pts"><?= (int)$t['lizzardier_points'] ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="pub-tour-card-body">
          <div class="pub-tour-card-title">
            <?php if (!empty($t['country_flag'])): ?>
              <img src="<?= e(getFlagUrl($t['country_flag'])) ?>" alt="">
            <?php endif; ?>
            <?= e($t['name']) ?>
          </div>
          <div class="pub-tour-card-dates"><?= $huDateRange ?></div>
          <div class="pub-tour-card-spots">
            <?php if ($t['status'] !== 'open'): ?>
              <span class="badge badge-inactive">Lezárt</span>
            <?php elseif ($spotsLeft > 0): ?>
              <span class="badge badge-active"><?= $spotsLeft ?> szabad hely</span>
            <?php else: ?>
              <span class="badge badge-overdue">Betelt</span>
            <?php endif; ?>
            <?php if ((int)$t['waitlist_count'] > 0): ?>
              <span style="font-size:11px;color:var(--text-muted);">+<?= (int)$t['waitlist_count'] ?> várólistán</span>
            <?php endif; ?>
          </div>
          <?php if (!empty($t['short_intro'])): ?>
            <div class="pub-tour-card-intro"><?= e($t['short_intro']) ?></div>
          <?php endif; ?>
        </div>
        <div class="pub-tour-card-side">
          <div class="pub-tour-card-lizzardier">
            <img src="<?= BASE_URL ?>/assets/img/ures_small.png" alt="Lizzardier">
            <?php if ($t['lizzardier_points'] !== null): ?>
              <span class="pub-tour-card-lizzardier-pts"><?= (int)$t['lizzardier_points'] ?></span>
            <?php endif; ?>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$showArchive && $archiveCount > 0): ?>
    <div style="text-align:center;margin-top:32px;">
      <a href="<?= BASE_URL ?>/public/turanyptar.php?regi=1" class="btn btn-secondary">Régebbi túrák megtekintése (<?= $archiveCount ?>)</a>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
