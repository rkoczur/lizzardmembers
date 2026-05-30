<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';
requireAdminOrVezeto();

$pdo = getDb();
ensureFutureToursSchema($pdo);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/admin/future-tours.php');
    exit;
}

$tour = $pdo->prepare("SELECT ft.*, c.name_hu AS country_name FROM future_tours ft LEFT JOIN countries c ON c.code = ft.country WHERE ft.id = ? LIMIT 1");
$tour->execute([$id]);
$tour = $tour->fetch();
if (!$tour) {
    header('Location: ' . BASE_URL . '/admin/future-tours.php');
    exit;
}

$applications = $pdo->prepare("
    SELECT fta.*, u.firstname, u.lastname, u.email, u.phone, COALESCE(u.level, 1) AS user_level
    FROM future_tour_applications fta
    LEFT JOIN users u ON u.id = fta.user_id
    WHERE fta.future_tour_id = ? AND fta.status IN ('confirmed','waitlist')
    ORDER BY fta.status ASC, fta.applied_at ASC
");
$applications->execute([$id]);
$applications = $applications->fetchAll();

$pendingGuests = $pdo->prepare("
    SELECT * FROM future_tour_applications
    WHERE future_tour_id = ? AND status = 'pending'
    ORDER BY applied_at ASC
");
$pendingGuests->execute([$id]);
$pendingGuests = $pendingGuests->fetchAll();

$addableMembers = $pdo->prepare("
    SELECT u.id, u.lastname, u.firstname
    FROM users u
    WHERE u.active = 1
      AND u.id NOT IN (
          SELECT user_id FROM future_tour_applications
          WHERE future_tour_id = ? AND status != 'cancelled' AND user_id IS NOT NULL
      )
    ORDER BY u.lastname ASC, u.firstname ASC
");
$addableMembers->execute([$id]);
$addableMembers = $addableMembers->fetchAll();

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$confirmedCount = array_sum(array_map(fn($a) => $a['status'] === 'confirmed' ? 1 : 0, $applications));

$pageTitle  = 'Jelentkezők – ' . e($tour['name']);
$activePage = 'tours';
include __DIR__ . '/../includes/admin-header.php';
?>

<?php if ($flash_success): ?>
  <div class="alert alert-success" data-auto-dismiss><?= e($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_error): ?>
  <div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="page-header">
  <div class="flex items-center gap-2">
    <a href="<?= BASE_URL ?>/admin/future-tours.php" class="btn btn-secondary btn-sm">← Vissza</a>
    <h1>Jelentkezők – <?= e($tour['name']) ?></h1>
  </div>
  <div style="display:flex;gap:8px;align-items:center;">
    <a href="<?= BASE_URL ?>/admin/future-tour-detail.php?id=<?= $id ?>" class="btn btn-ghost btn-sm">Szerkesztés</a>
    <?php if (isAdmin()): ?>
    <a href="<?= BASE_URL ?>/actions/future-tour-export.php?id=<?= $id ?>" class="btn btn-ghost btn-sm">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="14" height="14" style="vertical-align:middle;margin-right:3px;">
        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
        <polyline points="7 10 12 15 17 10"/>
        <line x1="12" y1="15" x2="12" y2="3"/>
      </svg>
      CSV export
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- Tour summary bar -->
<div style="display:flex;gap:20px;align-items:center;background:var(--card);border:1px solid var(--border);border-radius:10px;padding:12px 20px;margin-bottom:20px;flex-wrap:wrap;">
  <div>
    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Időpont</div>
    <div style="font-weight:600;"><?= $tour['start_date'] ? formatDate($tour['start_date']) : '—' ?></div>
  </div>
  <div style="width:1px;background:var(--border);align-self:stretch;"></div>
  <div>
    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Férőhelyek</div>
    <div style="font-weight:600;">
      <span style="color:var(--primary);"><?= $confirmedCount ?></span> / <?= (int)$tour['max_attendees'] ?>
      <?php if (array_sum(array_map(fn($a) => $a['status'] === 'waitlist' ? 1 : 0, $applications)) > 0): ?>
        <span style="color:var(--warning,#f59e0b);font-size:12px;margin-left:4px;">(+<?= array_sum(array_map(fn($a) => $a['status'] === 'waitlist' ? 1 : 0, $applications)) ?> várólistán)</span>
      <?php endif; ?>
    </div>
  </div>
  <div style="width:1px;background:var(--border);align-self:stretch;"></div>
  <div>
    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Részvételi díj</div>
    <div style="font-weight:600;">
      <?php if ($tour['participation_fee'] !== null): ?>
        <?= number_format((float)$tour['participation_fee'], 0, ',', '&nbsp;') ?> Ft
      <?php else: ?>
        <span style="color:var(--text-muted);">—</span>
      <?php endif; ?>
    </div>
  </div>
  <?php if (!empty($tour['country_name'])): ?>
  <div style="width:1px;background:var(--border);align-self:stretch;"></div>
  <div>
    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Helyszín</div>
    <div style="font-weight:600;"><?= e($tour['country_name']) ?><?= !empty($tour['region']) ? ', ' . e($tour['region']) : '' ?></div>
  </div>
  <?php endif; ?>
</div>

<?php if (!empty($pendingGuests)): ?>
<!-- Pending guests -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-header" style="background:#fffbeb;border-bottom:2px solid var(--warning,#f59e0b);">
    <h2 style="color:var(--warning,#b45309);">⏳ Jóváhagyásra váró vendég-jelentkezések (<?= count($pendingGuests) ?>)</h2>
  </div>
  <div class="card-body" style="padding:0;">
    <table style="width:100%;border-collapse:collapse;font-size:13.5px;">
      <thead>
        <tr style="border-bottom:1px solid var(--border);">
          <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--text-muted);font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;">Vendég neve</th>
          <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--text-muted);font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;">Elérhetőség</th>
          <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--text-muted);font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;">Jelentkezett</th>
          <th style="padding:10px 16px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pendingGuests as $pg): ?>
        <tr style="border-bottom:1px solid var(--border);background:#fffdf5;">
          <td style="padding:10px 16px;">
            <div style="font-weight:600;"><?= e($pg['guest_name']) ?></div>
            <span style="background:#f3e8d0;color:#92400e;border-radius:4px;padding:1px 6px;font-size:10.5px;font-weight:600;border:1px solid #d97706;">Vendég</span>
          </td>
          <td style="padding:10px 16px;color:var(--text-muted);font-size:12px;">
            <?= e($pg['guest_email']) ?><?= $pg['guest_phone'] ? '<br>' . e($pg['guest_phone']) : '' ?>
          </td>
          <td style="padding:10px 16px;color:var(--text-muted);font-size:12px;white-space:nowrap;">
            <?= date('Y.m.d H:i', strtotime($pg['applied_at'])) ?>
          </td>
          <td style="padding:10px 16px;text-align:right;white-space:nowrap;">
            <form method="post" action="<?= BASE_URL ?>/actions/future-tour-approve-guest.php" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
              <input type="hidden" name="application_id" value="<?= (int)$pg['id'] ?>">
              <input type="hidden" name="tour_id" value="<?= $id ?>">
              <button type="submit" class="btn btn-primary btn-sm">Jóváhagy</button>
            </form>
            <form method="post" action="<?= BASE_URL ?>/actions/future-tour-reject-guest.php" style="display:inline;margin-left:6px;"
                  onsubmit="return confirm('Biztosan elutasítja ezt a jelentkezést?')">
              <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
              <input type="hidden" name="application_id" value="<?= (int)$pg['id'] ?>">
              <input type="hidden" name="tour_id" value="<?= $id ?>">
              <button type="submit" class="btn btn-danger btn-sm">Elutasít</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Applicants table -->
<div class="card">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
    <h2>Megerősített jelentkezők</h2>
  </div>
  <div class="card-body" style="padding:0;">
    <?php if (empty($applications)): ?>
      <div style="padding:40px;text-align:center;color:var(--text-muted);font-size:14px;">Még senki nem jelentkezett erre a túrára.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;font-size:13.5px;">
        <thead>
          <tr style="border-bottom:2px solid var(--border);">
            <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--text-muted);font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;">Résztvevő</th>
            <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--text-muted);font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;">Honnan jön</th>
            <th style="padding:10px 12px;text-align:left;font-weight:600;color:var(--text-muted);font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;">Jelentkezett</th>
            <th style="padding:10px 12px;text-align:center;font-weight:600;color:var(--text-muted);font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;">Autó / helyek</th>
            <th style="padding:10px 12px;text-align:center;font-weight:600;color:var(--text-muted);font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;">Fizetendő díj</th>
            <th style="padding:10px 12px;text-align:center;font-weight:600;color:var(--text-muted);font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;">Fizetés</th>
            <?php if (isAdmin()): ?>
            <th style="padding:10px 12px;"></th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($applications as $app): ?>
          <tr style="border-bottom:1px solid var(--border);<?= $app['status'] === 'waitlist' ? 'opacity:.65;' : '' ?>">
            <td style="padding:12px 16px;">
              <?php if ($app['user_id']): ?>
                <div style="font-weight:600;"><?= e($app['lastname'] . ' ' . $app['firstname']) ?></div>
                <div style="font-size:11.5px;color:var(--text-muted);"><?= e($app['email']) ?></div>
                <?php if ($app['phone']): ?>
                  <div style="font-size:11.5px;color:var(--text-muted);"><?= e($app['phone']) ?></div>
                <?php endif; ?>
              <?php else: ?>
                <div style="font-weight:600;"><?= e($app['guest_name']) ?></div>
                <div style="font-size:11.5px;color:var(--text-muted);"><?= e($app['guest_email']) ?></div>
                <?php if ($app['guest_phone']): ?>
                  <div style="font-size:11.5px;color:var(--text-muted);"><?= e($app['guest_phone']) ?></div>
                <?php endif; ?>
                <span style="background:#f3e8d0;color:#92400e;border-radius:4px;padding:1px 6px;font-size:10.5px;font-weight:600;border:1px solid #d97706;margin-top:2px;display:inline-block;">Vendég</span>
              <?php endif; ?>
              <?php if ($app['status'] === 'waitlist'): ?>
                <span style="background:var(--warning-bg,#fffbeb);color:var(--warning,#b45309);border-radius:4px;padding:1px 6px;font-size:10.5px;font-weight:600;border:1px solid var(--warning,#f59e0b);margin-top:2px;display:inline-block;">Várólistán</span>
              <?php endif; ?>
            </td>
            <td style="padding:12px 12px;font-size:13px;color:var(--text);">
              <?php if ($app['departure_city']): ?>
                <span>📍 <?= e($app['departure_city']) ?></span>
              <?php else: ?>
                <span style="color:var(--text-muted);">—</span>
              <?php endif; ?>
            </td>
            <td style="padding:12px 12px;font-size:12.5px;color:var(--text-muted);white-space:nowrap;">
              <?= date('Y.m.d', strtotime($app['applied_at'])) ?>
              <div style="font-size:11px;"><?= date('H:i', strtotime($app['applied_at'])) ?></div>
            </td>
            <td style="padding:12px 12px;text-align:center;">
              <?php if ($app['car_available']): ?>
                <span style="display:inline-flex;align-items:center;gap:4px;background:#dcfce7;color:#166534;border-radius:6px;padding:3px 10px;font-size:12.5px;font-weight:600;white-space:nowrap;">
                  🚗 Igen
                  <?php if ((int)$app['passengers'] > 0): ?>
                    <span style="background:#166534;color:#fff;border-radius:99px;padding:0 5px;font-size:11px;line-height:1.7;"><?= (int)$app['passengers'] ?> hely</span>
                  <?php else: ?>
                    <span style="font-weight:400;font-size:11px;">(utas nincs)</span>
                  <?php endif; ?>
                </span>
              <?php else: ?>
                <span style="color:var(--text-muted);font-size:12.5px;">—</span>
              <?php endif; ?>
            </td>
            <td style="padding:12px 12px;text-align:center;font-weight:600;">
              <?php if ($tour['participation_fee'] !== null): ?>
                <?php
                  $discount     = $app['user_id'] ? getTourFeeDiscount((int)$app['user_level']) : 0;
                  $effectiveFee = (float)$tour['participation_fee'] * (1 - $discount / 100);
                  $feeColor     = $app['paid_at'] ? 'var(--success,#16a34a)' : 'var(--danger,#dc2626)';
                ?>
                <span style="color:<?= $feeColor ?>;font-size:12.5px;font-weight:600;">
                  <?= number_format($effectiveFee, 0, ',', '&nbsp;') ?> Ft
                </span>
                <?php if ($discount > 0): ?>
                  <div style="font-size:10.5px;color:var(--text-muted);text-decoration:line-through;line-height:1.2;"><?= number_format((float)$tour['participation_fee'], 0, ',', '&nbsp;') ?> Ft</div>
                  <div style="font-size:10.5px;"><span style="background:var(--primary);color:#fff;border-radius:3px;padding:0 4px;">-<?= $discount ?>%</span></div>
                <?php endif; ?>
              <?php else: ?>
                <span style="color:var(--text-muted);font-size:12.5px;">—</span>
              <?php endif; ?>
            </td>
            <td style="padding:12px 12px;text-align:center;">
              <form method="post" action="<?= BASE_URL ?>/actions/future-tour-mark-paid.php" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
                <input type="hidden" name="tour_id" value="<?= $id ?>">
                <?php if ($app['paid_at']): ?>
                  <button type="submit" title="Fizetés visszavonása"
                          style="background:#dcfce7;border:1px solid #86efac;color:#166534;border-radius:6px;padding:4px 10px;cursor:pointer;font-size:12.5px;font-weight:600;white-space:nowrap;">
                    ✓ Fizetve
                  </button>
                <?php else: ?>
                  <button type="submit" title="Fizetés rögzítése"
                          style="background:#fee2e2;border:1px solid #fca5a5;color:#dc2626;border-radius:6px;padding:4px 10px;cursor:pointer;font-size:12.5px;font-weight:600;white-space:nowrap;">
                    ✗ Nem fizetett
                  </button>
                <?php endif; ?>
              </form>
            </td>
            <?php if (isAdmin()): ?>
            <td style="padding:12px 12px;text-align:right;">
              <form method="post" action="<?= BASE_URL ?>/actions/future-tour-remove-applicant.php"
                    onsubmit="return confirm('Biztosan eltávolítja ezt a személyt a túráról?')">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
                <input type="hidden" name="tour_id" value="<?= $id ?>">
                <button type="submit" class="btn btn-danger btn-sm">Eltávolít</button>
              </form>
            </td>
            <?php endif; ?>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
  <?php if (isAdmin() && !empty($addableMembers)): ?>
  <div style="padding:14px 18px;border-top:1px solid var(--border);">
    <form method="post" action="<?= BASE_URL ?>/actions/future-tour-add-applicant.php" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="tour_id" value="<?= $id ?>">
      <select name="user_id" style="flex:1;min-width:160px;font-size:13px;">
        <option value="">— Tag kiválasztása —</option>
        <?php foreach ($addableMembers as $m): ?>
          <option value="<?= (int)$m['id'] ?>"><?= e($m['lastname'] . ' ' . $m['firstname']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="status" style="width:140px;font-size:13px;">
        <option value="confirmed">Megerősített</option>
        <option value="waitlist">Várólistán</option>
      </select>
      <button type="submit" class="btn btn-primary btn-sm">Hozzáadás</button>
    </form>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
