<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
require_once __DIR__ . '/../includes/carpool.php';

$pdo = getDb();
ensureFutureToursSchema($pdo);
ensureCarpoolSchema($pdo);

$token   = (string)($_GET['t'] ?? '');
$carpool = getCarpoolByToken($pdo, $token);
if (!$carpool) {
    header('Location: ' . BASE_URL . '/public/turanyptar.php');
    exit;
}
$tourId = (int)$carpool['future_tour_id'];

// Bejelentkezett tag automatikus azonosítása (kivéve, ha kifejezetten kilépett)
if (!carpoolSessionAppId($token) && isLoggedIn() && empty($_SESSION['carpool_forget'][$token])) {
    $own = findCarpoolApplicationByUser($pdo, $tourId, getCurrentUserId());
    if ($own) $_SESSION['carpool'][$token] = (int)$own['id'];
}

$me = getCarpoolApplication($pdo, $tourId, carpoolSessionAppId($token));
if (!$me) unset($_SESSION['carpool'][$token]);

$unknownEmail = $_SESSION['carpool_unknown'][$token] ?? null;
unset($_SESSION['carpool_unknown'][$token]);
$old = $_SESSION['carpool_old'] ?? [];
unset($_SESSION['carpool_old']);

$adminView = !$me && isLoggedIn() && isAdminOrVezeto();
$canSee    = $me || $adminView;
$drivers   = $canSee ? getCarpoolDrivers($pdo, (int)$carpool['id']) : [];
$role      = $me ? getCarpoolRole($drivers, (int)$me['id']) : ['role' => null, 'driver_id' => null];
$myCar     = null;
foreach ($drivers as $d) if ((int)$d['id'] === $role['driver_id']) $myCar = $d;
$readOnly  = $carpool['tour_status'] === 'cancelled';

$freeTotal = array_sum(array_column($drivers, 'free'));
$tourUrl   = BASE_URL . '/public/tour-detail.php?id=' . $tourId;
$post      = BASE_URL . '/actions/carpool-action.php';

// Sofőr űrlap alapértékei: hibás beküldés → előző adatok, egyébként a saját autó vagy a jelentkezés adatai
$form = [
    'seats'          => $old['seats']          ?? ($role['role'] === 'driver' ? $myCar['seats'] : 3),
    'phone'          => $old['phone']          ?? ($myCar['phone'] ?? ($me['phone'] ?? '')),
    'contact_extra'  => $old['contact_extra']  ?? ($myCar['contact_extra'] ?? ''),
    'departure'      => $old['departure']      ?? ($myCar['departure'] ?? ($me['departure_city'] ?? '')),
    'departure_time' => $old['departure_time'] ?? ($myCar['departure_time'] ?? ''),
    'route'          => $old['route']          ?? ($myCar['route'] ?? ''),
];
$openPanel = $old ? 'driver' : ($role['role'] ?? '');

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$pageTitle     = 'Telekocsi – ' . $carpool['tour_name'];
$activePubPage = 'turanyptar';
$metaRobots    = 'noindex, nofollow';
$metaDescription = 'Telekocsi-szervező a(z) ' . $carpool['tour_name'] . ' túra résztvevőinek.';
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap cp-page">

  <header class="cp-hero">
    <div class="cp-hero-road" aria-hidden="true"></div>
    <p class="cp-hero-kicker">Telekocsi</p>
    <h1><?= e($carpool['tour_name']) ?></h1>
    <p class="cp-hero-date">
      <?= formatDate($carpool['start_date']) ?><?= (int)$carpool['num_days'] > 1 ? ' · ' . (int)$carpool['num_days'] . ' nap' : '' ?>
      · <a href="<?= e($tourUrl) ?>">a túra részletei</a>
    </p>
    <?php if ($canSee): ?>
    <div class="cp-hero-stats">
      <div><strong><?= count($drivers) ?></strong><span>autó</span></div>
      <div><strong><?= $freeTotal ?></strong><span>szabad hely</span></div>
    </div>
    <?php endif; ?>
  </header>

  <?php if ($flash_success): ?><div class="alert alert-success" data-auto-dismiss><?= e($flash_success) ?></div><?php endif; ?>
  <?php if ($flash_error): ?><div class="alert alert-error"><?= e($flash_error) ?></div><?php endif; ?>
  <?php if ($readOnly): ?><div class="alert alert-error">A túra elmaradt — a telekocsi-szervező csak megtekinthető.</div><?php endif; ?>

  <?php if (!$me): ?>
    <?php include __DIR__ . '/../includes/carpool-identify.php'; ?>
  <?php else: ?>
    <?php include __DIR__ . '/../includes/carpool-me.php'; ?>
  <?php endif; ?>

  <?php if ($canSee): ?>
  <section class="cp-board">
    <h2 class="cp-board-title">Autók<?= $adminView ? ' <span class="cp-admin-note">vezetői nézet</span>' : '' ?></h2>
    <?php if (!$drivers): ?>
      <div class="cp-empty">Még nincs sofőr. <?= $me && !$readOnly ? 'Ha autóval jössz, légy te az első!' : '' ?></div>
    <?php endif; ?>
    <div class="cp-cars">
      <?php foreach ($drivers as $car) include __DIR__ . '/../includes/carpool-car.php'; ?>
    </div>
  </section>
  <?php endif; ?>
</div>

<script src="<?= BASE_URL ?>/assets/js/carpool.js?v=<?= APP_VERSION ?>" defer></script>
<?php include __DIR__ . '/../includes/public-footer.php'; ?>
