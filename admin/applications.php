<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/join-schema.php';
require_once __DIR__ . '/../includes/user-schema.php';
requireAdmin();

$pdo = getDb();
ensureJoinSchema($pdo);
ensureUserSchema($pdo);

// ── Counts ───────────────────────────────────────────────────────────
$countPending  = (int)$pdo->query("SELECT COUNT(*) FROM member_applications WHERE status='pending'")->fetchColumn();
$countApproved = (int)$pdo->query("SELECT COUNT(*) FROM member_applications WHERE status='approved'")->fetchColumn();
$countRejected = (int)$pdo->query("SELECT COUNT(*) FROM member_applications WHERE status='rejected'")->fetchColumn();

// ── Filter ───────────────────────────────────────────────────────────
$allowedStatuses = ['pending', 'approved', 'rejected', 'all'];
$statusFilter    = in_array($_GET['status'] ?? '', $allowedStatuses, true) ? $_GET['status'] : 'pending';

$where  = $statusFilter !== 'all' ? "WHERE ma.status = " . $pdo->quote($statusFilter) : '';
$stmt   = $pdo->query("
    SELECT ma.*, u.id AS user_id
    FROM member_applications ma
    LEFT JOIN users u ON u.email = ma.email
    {$where}
    ORDER BY ma.submitted_at DESC
");
$applications = $stmt->fetchAll();

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$pageTitle  = 'Tagfelvételi kérelmek';
$activePage = 'applications';
include __DIR__ . '/../includes/admin-header.php';
?>

<?php if ($flash_success): ?>
  <div class="alert alert-success" data-auto-dismiss><?= e($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_error): ?>
  <div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="page-header">
  <h1>Tagfelvételi kérelmek</h1>
</div>

<!-- Stats cards -->
<div class="stats-grid" style="margin-bottom:24px;">
  <div class="stat-card">
    <div class="stat-icon">⏳</div>
    <div class="stat-label">Függőben</div>
    <div class="stat-value" style="color:var(--warning,#d97706);"><?= $countPending ?></div>
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

<!-- Filter tabs -->
<div style="display:flex;gap:4px;margin-bottom:16px;border-bottom:2px solid var(--border);padding-bottom:0;">
  <?php
  $tabs = [
    'pending'  => 'Függőben (' . $countPending . ')',
    'approved' => 'Jóváhagyva (' . $countApproved . ')',
    'rejected' => 'Elutasítva (' . $countRejected . ')',
    'all'      => 'Összes',
  ];
  foreach ($tabs as $key => $label):
    $isActive = $statusFilter === $key;
  ?>
    <a href="?status=<?= e($key) ?>"
       style="padding:8px 16px;font-size:13px;font-weight:<?= $isActive ? '700' : '500' ?>;
              color:<?= $isActive ? 'var(--primary)' : 'var(--text-muted)' ?>;
              border-bottom:2px solid <?= $isActive ? 'var(--primary)' : 'transparent' ?>;
              margin-bottom:-2px;text-decoration:none;transition:color .15s;">
      <?= e($label) ?>
    </a>
  <?php endforeach; ?>
</div>

<!-- Table -->
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

<!-- ═══════════════════════════════════════════════════════════════════
     DETAIL MODAL
═══════════════════════════════════════════════════════════════════ -->
<div id="detail-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;padding:16px;">
  <div style="background:var(--card-bg,#fff);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.18);width:100%;max-width:540px;max-height:90vh;overflow-y:auto;">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px;border-bottom:1px solid var(--border);">
      <h2 style="font-size:16px;font-weight:700;margin:0;">Kérelem részletei</h2>
      <button type="button" onclick="closeDetailModal()" style="background:none;border:none;cursor:pointer;color:var(--text-muted);padding:4px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div style="padding:20px 24px 24px;" id="detail-body">
      <!-- filled by JS -->
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     APPROVE MODAL
═══════════════════════════════════════════════════════════════════ -->
<div id="approve-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;padding:16px;">
  <div style="background:var(--card-bg,#fff);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.18);width:100%;max-width:520px;max-height:90vh;overflow-y:auto;">
    <!-- Modal header -->
    <div style="display:flex;align-items:center;justify-content:space-between;padding:20px 24px 0;">
      <h2 style="font-size:16px;font-weight:700;margin:0;">Tagfelvétel jóváhagyása</h2>
      <button type="button" onclick="closeApproveModal()" style="background:none;border:none;cursor:pointer;color:var(--text-muted);padding:4px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <!-- Applicant info -->
    <div style="margin:16px 24px 0;padding:14px 16px;background:var(--bg,#f8fafc);border-radius:8px;border:1px solid var(--border);">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin-bottom:8px;">Kérelmező adatai</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px 16px;font-size:13px;">
        <div><span style="color:var(--text-muted);">Név:</span> <strong id="ai-name"></strong></div>
        <div><span style="color:var(--text-muted);">E-mail:</span> <span id="ai-email"></span></div>
        <div><span style="color:var(--text-muted);">Telefon:</span> <span id="ai-phone">—</span></div>
        <div><span style="color:var(--text-muted);">Beérkezett:</span> <span id="ai-submitted"></span></div>
      </div>
    </div>

    <!-- Form -->
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

<!-- ═══════════════════════════════════════════════════════════════════
     REJECT MODAL
═══════════════════════════════════════════════════════════════════ -->
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
// ── Detail modal ─────────────────────────────────────────────────────
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

// ── Slugify helper ────────────────────────────────────────────────────
function slugify(str) {
  var map = {'á':'a','é':'e','í':'i','ó':'o','ö':'o','ő':'o','ú':'u','ü':'u','ű':'u',
             'Á':'a','É':'e','Í':'i','Ó':'o','Ö':'o','Ő':'o','Ú':'u','Ü':'u','Ű':'u'};
  return str.toLowerCase()
    .replace(/[áéíóöőúüűÁÉÍÓÖŐÚÜŰ]/g, function(c){ return map[c] || c; })
    .replace(/\s+/g, '.')
    .replace(/[^a-z0-9._\-]/g, '')
    .replace(/\.{2,}/g, '.');
}

// ── Approve modal ────────────────────────────────────────────────────
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

  // Suggest username from name
  var parts = name.trim().split(' ');
  var suggested = '';
  if (parts.length >= 2) {
    // Hungarian: lastname firstname → firstname.lastname
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

// ── Reject modal ─────────────────────────────────────────────────────
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

// ── Close on overlay click ───────────────────────────────────────────
document.getElementById('approve-overlay').addEventListener('click', function(e) {
  if (e.target === this) closeApproveModal();
});
document.getElementById('reject-overlay').addEventListener('click', function(e) {
  if (e.target === this) closeRejectModal();
});

// ── ESC key closes modals ────────────────────────────────────────────
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeApproveModal();
    closeRejectModal();
  }
});
</script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
