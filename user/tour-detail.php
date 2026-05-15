<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tours-schema.php';
requireUser();

$pdo = getDb();
ensureToursSchema($pdo);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/user/tours.php');
    exit;
}

$tourStmt = $pdo->prepare("SELECT * FROM tours WHERE id = ? LIMIT 1");
$tourStmt->execute([$id]);
$tour = $tourStmt->fetch();
if (!$tour) {
    header('Location: ' . BASE_URL . '/user/tours.php');
    exit;
}

$membersStmt = $pdo->prepare("
    SELECT u.lastname, u.firstname, u.level, u.profile_picture
    FROM tour_members tm
    JOIN users u ON u.id = tm.user_id
    WHERE tm.tour_id = ?
    ORDER BY u.lastname, u.firstname
");
$membersStmt->execute([$id]);
$members = $membersStmt->fetchAll();

$userId  = getCurrentUserId();
$isMine  = $pdo->prepare("SELECT 1 FROM tour_members WHERE tour_id = ? AND user_id = ? LIMIT 1");
$isMine->execute([$id, $userId]);
$isMine  = (bool)$isMine->fetchColumn();

$title = $tour['name'] ?: ($tour['country'] . ($tour['region'] ? ' – ' . $tour['region'] : ''));
$pageTitle  = e($title);
$activePage = 'tours';
include __DIR__ . '/../includes/user-header.php';

// Helper: only show a row if value is non-empty
function detailRow(string $label, $value): void {
    if ($value === null || $value === '' || $value === '—') return;
    echo '<tr><td style="width:40%;padding:8px 12px;color:var(--text-muted);font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;">' . htmlspecialchars($label) . '</td>';
    echo '<td style="padding:8px 12px;">' . $value . '</td></tr>';
}
?>

<div class="page-header">
  <div class="flex items-center gap-2">
    <a href="<?= BASE_URL ?>/user/tours.php" class="btn btn-secondary btn-sm">← Vissza</a>
    <h1><?= e($title) ?></h1>
    <?php if ($isMine): ?>
      <span class="badge badge-active">Részt vettem</span>
    <?php endif; ?>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

  <!-- Általános adatok -->
  <div class="card">
    <div class="card-header"><h2>Általános adatok</h2></div>
    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;">
        <tbody>
          <?php
          detailRow('Túra kód',      $tour['tour_code'] ? '<code>' . e($tour['tour_code']) . '</code>' : null);
          detailRow('Ország',         e($tour['country']));
          detailRow('Tájegység',      $tour['region'] ? e($tour['region']) : null);
          detailRow('Dátum',          $tour['tour_date'] ? formatDate($tour['tour_date']) : null);
          detailRow('Napok száma',    (int)$tour['days'] . ' nap');
          detailRow('Szállás',        $tour['accommodation'] ? e(match($tour['accommodation']) {
              'sator'      => 'Sátor',
              'turistahaz' => 'Túristaház',
              'apartman'   => 'Apartman',
              'hotel'      => 'Hotel',
              default      => $tour['accommodation'],
          }) : null);
          detailRow('Vendég résztvevők', ($tour['guest_count'] ?? 0) > 0 ? (int)$tour['guest_count'] . ' fő' : null);
          ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Teljesítmény adatok -->
  <div class="card">
    <div class="card-header"><h2>Teljesítmény</h2></div>
    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;">
        <tbody>
          <?php
          detailRow('Túramód', e(getTourTypeLabel($tour['tour_type'] ?? 'gyalogos')));

          $subLabels = [
              'normal'        => 'Normál gyalogtúra',
              'tajekozodasi'  => 'Tájékozódási verseny',
              'mout'          => 'Műúton / kerékpárúton',
              'terep'         => 'Terepen (földúton)',
              'folyasirany'   => 'Folyóvízen folyásirányban',
              'allovi'        => 'Állóvízen',
              'szemben'       => 'Folyóvízen szemben',
              'kiepitett'     => 'Kiépített barlang',
              'kiepitetlen'   => 'Nem kiépített barlang',
          ];
          if (!empty($tour['sub_type']) && isset($subLabels[$tour['sub_type']])) {
              detailRow('Altípus', e($subLabels[$tour['sub_type']]));
          }

          $totalKm  = ($tour['total_km']  ?? null) !== null ? (float)$tour['total_km']  : 0;
          $alpineKm = ($tour['alpine_km'] ?? null) !== null ? (float)$tour['alpine_km'] : 0;
          $fullKm   = $totalKm + $alpineKm;
          if ($fullKm > 0) {
              $kmVal = number_format($fullKm, 1, ',', ' ') . ' km';
              if ($alpineKm > 0) $kmVal .= ' <small style="color:var(--text-muted);">(' . number_format($alpineKm,1,',','') . ' km magashegyi)</small>';
              detailRow('Megtett km', $kmVal);
          }

          $totalElev  = ($tour['total_elevation']  ?? null) !== null ? (int)$tour['total_elevation']  : 0;
          $alpineElev = ($tour['alpine_elevation'] ?? null) !== null ? (int)$tour['alpine_elevation'] : 0;
          if ($totalElev + $alpineElev > 0) {
              $elevVal = number_format($totalElev + $alpineElev) . ' m';
              if ($alpineElev > 0) $elevVal .= ' <small style="color:var(--text-muted);">(' . number_format($alpineElev) . ' m magashegyi)</small>';
              detailRow('Szintemelkedés', $elevVal);
          }

          if ($tour['tour_hours'] !== null) {
              detailRow('Időtartam', number_format((float)$tour['tour_hours'], 1, ',', ' ') . ' óra');
          }

          if (!empty($tour['multi_day_type'])) {
              detailRow('Többnapos típusa', $tour['multi_day_type'] === 'csillag' ? 'Csillagtúra' : 'Vándortúra');
          }
          if ((int)($tour['camping_nights_fixed'] ?? 0) > 0) {
              detailRow('Eltöltött éjszakák', (int)$tour['camping_nights_fixed'] . ' éj');
          }
          if ($tour['tour_type'] === 'vizi' && (int)($tour['boat_portages'] ?? 0) > 0) {
              detailRow('Hajóátemelések', (int)$tour['boat_portages'] . ' alkalom');
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pontszámok -->
  <div class="card">
    <div class="card-header"><h2>Pontszámok</h2></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;">
      <div style="padding:20px;text-align:center;border-right:1px solid var(--border);">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:6px;">Lizzardier pont</div>
        <div style="font-size:28px;font-weight:700;color:var(--warning);"><?= number_format((int)$tour['points']) ?></div>
      </div>
      <div style="padding:20px;text-align:center;">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-muted);margin-bottom:6px;">MTSZ pont</div>
        <div style="font-size:28px;font-weight:700;color:var(--primary);"><?= number_format((int)($tour['mtsz_points'] ?? 0)) ?></div>
      </div>
    </div>
  </div>

  <?php if (!empty($tour['route'])): ?>
  <!-- Túra útvonala: jobb oszlop, 2. sor (Teljesítmény alatt) -->
  <div class="card">
    <div class="card-header"><h2>Túra útvonala</h2></div>
    <div style="padding:16px 20px;line-height:1.7;white-space:pre-line;"><?= e($tour['route']) ?></div>
  </div>
  <?php endif; ?>

  <!-- Résztvevők -->
  <div class="card">
    <div class="card-header">
      <h2>Résztvevők</h2>
      <span style="font-size:13px;color:var(--text-muted);"><?= count($members) ?> tag<?= ($tour['guest_count'] ?? 0) > 0 ? ', ' . (int)$tour['guest_count'] . ' vendég' : '' ?></span>
    </div>
    <?php if (!empty($members)): ?>
    <div style="padding:12px 16px;display:flex;flex-wrap:wrap;gap:10px;">
      <?php foreach ($members as $m): ?>
      <div style="display:flex;align-items:center;gap:8px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:6px 10px;">
        <img src="<?= getLevelImageUrl((int)$m['level']) ?>" alt="<?= e(getLevelLabel((int)$m['level'])) ?>"
             style="width:auto;height:48px;object-fit:contain;flex-shrink:0;">
        <div>
          <div style="font-size:13px;font-weight:600;"><?= e($m['lastname'] . ' ' . $m['firstname']) ?></div>
          <div style="font-size:11px;"><span class="level-badge <?= getLevelClass((int)$m['level']) ?>" style="font-size:10px;padding:1px 7px;"><?= getLevelLabel((int)$m['level']) ?></span></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="card-body" style="color:var(--text-muted);font-size:13px;">Nincs rögzített résztvevő.</div>
    <?php endif; ?>
  </div>

</div>

<?php include __DIR__ . '/../includes/user-footer.php'; ?>
