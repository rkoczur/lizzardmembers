<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/user-schema.php';
require_once __DIR__ . '/../includes/mtsz-schema.php';
requireAdminOrVezeto();
$ro = !isAdmin();
$mtszRo = !canManageMtsz();

$pdo = getDb();
ensureUserSchema($pdo);
ensureMtszSchema($pdo);
recalcMembershipPayments($pdo); // utolsó tagdíj fizetés a tranzakciókból
$id  = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/admin/members.php');
    exit;
}

$member = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$member->execute([$id]);
$member = $member->fetch();
if (!$member) {
    header('Location: ' . BASE_URL . '/admin/members.php');
    exit;
}

$isSelf = (getCurrentUserId() === (int)$member['id']);

// Túraértesítő (új meghirdetett túrák) értesítési beállítás — opt-out modell (hiányzó = bekapcsolva)
$memberNotif = json_decode($member['notification_prefs'] ?? '{}', true) ?: [];
$notifTourAnnouncement = ($memberNotif['tour_announcement'] ?? 1) != 0;

$tcStmt = $pdo->prepare("SELECT COUNT(*) FROM tour_members WHERE user_id = ?");
$tcStmt->execute([$id]);
$tourCount = (int)$tcStmt->fetchColumn();

// Utolsó sikeres bejelentkezés ideje (a login_log alapján)
$lastLogin = null;
try {
    require_once __DIR__ . '/../includes/login-log-schema.php';
    ensureLoginLogSchema($pdo);
    $llStmt = $pdo->prepare("SELECT MAX(created_at) FROM login_log WHERE user_id = ? AND status = 'success' AND event_type = 'login'");
    $llStmt->execute([$id]);
    $lastLogin = $llStmt->fetchColumn() ?: null;
} catch (Throwable $e) {
    $lastLogin = null;
}

// MTSZ jelvényes minősítések
$mtszRows = getMtszQualifications($pdo, $id);
$mtszEditId  = (int)($_GET['mtsz_edit'] ?? 0);
$mtszEditRow = null;
foreach ($mtszRows as $r) {
    if ((int)$r['id'] === $mtszEditId) { $mtszEditRow = $r; break; }
}
$mtszTakenGrades = array_column($mtszRows, 'grade');

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$pageTitle  = ($member['lastname'] ?? '') . ' ' . ($member['firstname'] ?? '');
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
  <div class="flex items-center gap-2">
    <a href="<?= BASE_URL ?>/admin/members.php" class="btn btn-secondary btn-sm">← Vissza</a>
    <h1><?= e($member['lastname'] . ' ' . $member['firstname']) ?></h1>
  </div>
  <div class="flex items-center gap-2">
    <?php $ms = getMemberStatus($member['last_payment']); ?>
    <span class="badge <?= getMemberStatusClass($ms) ?>"><?= getMemberStatusLabel($ms) ?></span>
    <?php if (!empty($member['locked_at'])): ?>
      <span class="badge badge-inactive">🔒 Fiók zárolva</span>
      <?php if (!$ro): ?>
      <form method="post" action="<?= BASE_URL ?>/actions/member-unlock.php" style="display:inline;">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="id" value="<?= $member['id'] ?>">
        <button type="submit" class="btn btn-primary btn-sm">Fiók feloldása</button>
      </form>
      <?php endif; ?>
    <?php endif; ?>
    <?php if (!$ro && !$isSelf): ?>
    <form method="post" action="<?= BASE_URL ?>/actions/member-delete.php" style="display:inline;"
          onsubmit="return confirmDelete('Biztosan törli <?= e(addslashes($member['lastname'] . ' ' . $member['firstname'])) ?> tagot? A művelet nem vonható vissza.')">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="id" value="<?= $member['id'] ?>">
      <button type="submit" class="btn btn-danger btn-sm">Tag törlése</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<div class="profile-layout">
  <!-- Avatar card -->
  <div class="profile-avatar-card">
    <div class="avatar-upload-wrap">
      <img id="avatar-preview"
           src="<?= getAvatarUrl($member['profile_picture']) ?>"
           alt="Profilkép">
      <?php if (!$ro): ?>
      <div class="avatar-overlay" id="avatar-upload-overlay" title="Fotó módosítása">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
          <circle cx="12" cy="13" r="4"/>
        </svg>
      </div>
      <?php endif; ?>
    </div>
    <div class="member-name"><?= e($member['lastname'] . ' ' . $member['firstname']) ?></div>
    <div class="member-username">@<?= e($member['username']) ?></div>
    <div class="divider"></div>
    <div class="points-display">
      <div class="points-value"><?= number_format($member['points']) ?></div>
      <div class="points-label">Pontok</div>
    </div>
    <?php $lvlImg = getLevelImageFilename((int)$member['level']); if ($lvlImg): ?>
    <img src="<?= BASE_URL ?>/assets/img/<?= $lvlImg ?>" alt="<?= getLevelLabel((int)$member['level']) ?>" style="width:auto;height:auto;max-width:100%;border-radius:0;box-shadow:none;">
    <?php endif; ?>
    <span class="level-badge <?= getLevelClass($member['level']) ?>" style="font-size:13px;padding:5px 14px;">
      <?= getLevelLabel($member['level']) ?> — Szint <?= $member['level'] ?>
    </span>
    <div class="divider"></div>
    <small class="text-muted">Tag azóta: <?= formatDate($member['member_since']) ?></small>
    <small class="text-muted">Utolsó fizetés: <?= formatDate($member['last_payment']) ?></small>
    <small class="text-muted">Utolsó belépés: <?= $lastLogin ? e((new DateTime($lastLogin))->format('Y.m.d H:i')) : 'N/A' ?></small>
    <small class="text-muted">Részt vett túrákon: <strong><?= $tourCount ?></strong></small>
    <?php if (!empty($mtszRows)): ?>
      <div class="divider"></div>
      <small class="text-muted" style="margin-bottom:2px;">MTSZ minősítések</small>
      <div class="mtsz-side-list">
        <?php foreach ($mtszRows as $q): $qImg = mtszGradeImageUrl($q['grade']); ?>
          <div class="mtsz-side-item">
            <?php if ($qImg): ?>
              <img src="<?= e($qImg) ?>" alt="<?= e(mtszGradeLabel($q['grade'])) ?>">
            <?php else: ?>
              <span class="mtsz-badge <?= mtszGradeClass($q['grade']) ?>" style="margin-right:0;"><?= e(mtszGradeShortLabel($q['grade'])) ?></span>
            <?php endif; ?>
            <span>
              <span class="mtsz-side-name"><?= e(mtszGradeLabel($q['grade'])) ?></span>
              <span class="mtsz-side-meta" style="display:block;"><?= formatDate($q['awarded_on']) ?></span>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($member['locked_at'])): ?>
      <div style="margin-top:10px;padding:8px 12px;background:var(--danger-bg,#fff1f0);border-radius:8px;text-align:center;">
        <div style="font-size:18px;">🔒</div>
        <div style="font-size:12px;font-weight:600;color:var(--danger,#c0392b);">Fiók zárolva</div>
        <div style="font-size:11px;color:var(--text-muted);margin-top:2px;"><?= e((new DateTime($member['locked_at']))->format('Y.m.d H:i')) ?></div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Jobb hasáb: tag adatai + MTSZ minősítések (egyező szélességgel) -->
  <div style="display:flex;flex-direction:column;gap:24px;min-width:0;">

  <!-- Edit form -->
  <div class="card">
    <div class="card-header">
      <h2>Tag adatai</h2>
      <?php if ($ro): ?>
        <span class="badge badge-vezeto" style="font-size:11px;">Csak megtekintés</span>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <form method="post" action="<?= BASE_URL ?>/actions/member-update.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="id" value="<?= $member['id'] ?>">
        <?php if (!$ro): ?>
        <input type="file" id="avatar-file-input" name="avatar" accept="image/*" style="display:none;">
        <?php endif; ?>

        <div class="form-grid">
          <div class="form-group">
            <label>Vezetéknév</label>
            <input type="text" name="lastname" value="<?= e($member['lastname'] ?? '') ?>" <?= $ro ? 'readonly' : 'required' ?>>
          </div>
          <div class="form-group">
            <label>Keresztnév</label>
            <input type="text" name="firstname" value="<?= e($member['firstname'] ?? '') ?>" <?= $ro ? 'readonly' : 'required' ?>>
          </div>
          <div class="form-group">
            <label>Felhasználónév</label>
            <input type="text" name="username" value="<?= e($member['username']) ?>" <?= $ro ? 'readonly' : 'required' ?>>
          </div>
          <div class="form-group">
            <label>E-mail</label>
            <input type="email" name="email" value="<?= e($member['email']) ?>" <?= $ro ? 'readonly' : 'required' ?>>
          </div>
          <div class="form-group">
            <label>Születési dátum</label>
            <input type="date" name="dateofbirth" value="<?= e($member['dateofbirth'] ?? '') ?>" <?= $ro ? 'readonly' : '' ?>>
          </div>
          <div class="form-group">
            <label>Pólóméret</label>
            <select name="tshirt_size" <?= $ro ? 'disabled' : '' ?>>
              <option value="">— Válasszon —</option>
              <?php foreach (['XS','S','M','L','XL','XXL','XXXL'] as $sz): ?>
                <option value="<?= $sz ?>" <?= ($member['tshirt_size'] ?? '') === $sz ? 'selected' : '' ?>><?= $sz ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Irányítószám</label>
            <input type="text" name="zipcode" value="<?= e($member['zipcode'] ?? '') ?>" <?= $ro ? 'readonly' : '' ?>>
          </div>
          <div class="form-group">
            <label>Város</label>
            <input type="text" name="city" value="<?= e($member['city'] ?? '') ?>" <?= $ro ? 'readonly' : '' ?>>
          </div>
          <div class="form-group full">
            <label>Cím</label>
            <input type="text" name="address" value="<?= e($member['address'] ?? '') ?>" <?= $ro ? 'readonly' : '' ?>>
          </div>
          <div class="form-group">
            <label>Telefonszám</label>
            <input type="tel" name="phone" value="<?= e($member['phone'] ?? '') ?>" <?= $ro ? 'readonly' : '' ?>>
          </div>
        </div>

        <h3 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin:24px 0 14px;padding-top:20px;border-top:1px solid var(--border);">Vészhelyzet esetén értesítendő</h3>
        <div class="form-grid">
          <div class="form-group">
            <label>Név</label>
            <input type="text" name="emergency_name" value="<?= e($member['emergency_name'] ?? '') ?>" <?= $ro ? 'readonly' : '' ?>>
          </div>
          <div class="form-group">
            <label>Kapcsolat</label>
            <select name="emergency_relation" <?= $ro ? 'disabled' : '' ?>>
              <option value="">— Válasszon —</option>
              <?php foreach (['szülő','gyermek','testvér','egyéb'] as $rel): ?>
                <option value="<?= $rel ?>" <?= ($member['emergency_relation'] ?? '') === $rel ? 'selected' : '' ?>><?= $rel ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Telefonszám</label>
            <input type="tel" name="emergency_phone" value="<?= e($member['emergency_phone'] ?? '') ?>" <?= $ro ? 'readonly' : '' ?>>
          </div>
        </div>

        <h3 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin:24px 0 14px;padding-top:20px;border-top:1px solid var(--border);">Tagság</h3>
        <div class="form-grid">
          <div class="form-group">
            <label>Tagság kezdete</label>
            <input type="date" name="member_since" value="<?= e($member['member_since'] ?? '') ?>" <?= $ro ? 'readonly' : '' ?>>
          </div>
          <div class="form-group">
            <label>Utolsó tagdíj fizetés</label>
            <input type="date" value="<?= e($member['last_payment'] ?? '') ?>" readonly disabled>
            <small class="text-muted">A tranzakciós napló alapján automatikusan számolt (legutóbbi „Tagdíj” befizetés) — nem szerkeszthető.</small>
          </div>
          <div class="form-group">
            <label>Szerepkör</label>
            <?php if ($isSelf): ?>
              <input type="text" value="<?= e(getRoleLabel($member['role'])) ?> (saját fiók)" readonly>
              <input type="hidden" name="role" value="<?= e($member['role']) ?>">
              <span class="form-hint">Saját szerepkörödet nem módosíthatod.</span>
            <?php elseif ($ro): ?>
              <input type="text" value="<?= e(getRoleLabel($member['role'])) ?>" readonly>
            <?php else: ?>
              <select name="role">
                <?php foreach (['user'=>'Tag','vezeto'=>'Szakszövetségi vezető','kommunikacios'=>'Kommunikációs vezető','jogi'=>'Jogi vezető','penzugyi'=>'Pénzügyi vezető','helyettes'=>'Egyesületvezető-helyettes','admin'=>'Egyesületvezető'] as $val => $lbl): ?>
                  <option value="<?= $val ?>" <?= $member['role'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                <?php endforeach; ?>
              </select>
            <?php endif; ?>
          </div>
          <div class="form-group full">
            <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;font-size:13px;text-transform:none;letter-spacing:0;font-weight:600;">
              <input type="checkbox" name="is_candidate" value="1" style="margin-top:2px;" <?= !empty($member['is_candidate']) ? 'checked' : '' ?> <?= $ro ? 'disabled' : '' ?>>
              <span>
                Jelölt (rejtett a nyilvános oldalon)
                <small style="display:block;font-weight:normal;color:var(--text-muted);font-size:12px;margin-top:2px;">A tag a szerepköréhez tartozó jogosultságokkal rendelkezik, de a nyilvános weboldalon sehol nem jelenik meg — sem a toplistán, sem a vezetőknél, sem az év túratársánál.</small>
              </span>
            </label>
          </div>
        </div>

        <?php if (!$isSelf): $memberPerms = json_decode($member['permissions'] ?? '[]', true) ?: []; ?>
        <h3 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin:24px 0 14px;padding-top:20px;border-top:1px solid var(--border);">Egyéni jogosultságok</h3>
        <div class="notif-list">
          <?php foreach (customPermissionLabels() as $permKey => $permLabel): ?>
          <label class="notif-row">
            <input type="checkbox" name="permissions[]" value="<?= e($permKey) ?>" <?= in_array($permKey, $memberPerms, true) ? 'checked' : '' ?> <?= $ro ? 'disabled' : '' ?>>
            <span class="notif-slider"></span>
            <span><?= e($permLabel) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
        <p style="font-size:12px;color:var(--text-muted);margin:6px 0 0;">A szerepkörből adódó jogokon felül adott külön engedélyek. A „Bejegyzés létrehozása” a tagoknak a tag-portálon ad szerkesztőt; a „Meghirdetett túra létrehozása” a vezetőknek ad jogot.</p>
        <?php endif; ?>

        <h3 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin:24px 0 14px;padding-top:20px;border-top:1px solid var(--border);">Értesítések</h3>
        <div class="notif-list">
          <label class="notif-row">
            <input type="checkbox" name="notif_tour_announcement" value="1" <?= $notifTourAnnouncement ? 'checked' : '' ?> <?= $ro ? 'disabled' : '' ?>>
            <span class="notif-slider"></span>
            <span class="notif-info">
              <strong>Túraértesítő</strong>
              <small>Ha be van kapcsolva, a tag e-mailes értesítőt kap az új meghirdetett túrákról. A tag a saját profilján is módosíthatja.</small>
            </span>
          </label>
        </div>

        <?php if (!$ro): ?>
        <div class="pass-section">
          <h3>Jelszó visszaállítása (hagyja üresen a jelenlegi megtartásához)</h3>
          <div class="form-grid">
            <div class="form-group">
              <label>Új jelszó</label>
              <input type="password" name="new_password" minlength="6">
            </div>
            <div class="form-group">
              <label>Jelszó megerősítése</label>
              <input type="password" name="new_password2">
            </div>
          </div>

          <div class="genpass-row">
            <button type="submit" form="genpass-form" class="btn btn-secondary btn-sm"
                    onclick="return confirm('Biztosan új jelszót generálsz, és kiküldöd a tag e-mail címére a belépési adatokkal?');">
              🔑 Új jelszó generálása és kiküldése
            </button>
            <small>Új, véletlenszerű jelszót állít be, és e-mailben elküldi a tagnak a felhasználónevével és a belépési linkkel együtt.</small>
          </div>
        </div>

        <div class="flex gap-2" style="margin-top:20px;">
          <button type="submit" class="btn btn-primary">Változások mentése</button>
          <a href="<?= BASE_URL ?>/admin/members.php" class="btn btn-secondary">Mégse</a>
        </div>
        <?php else: ?>
        <div style="margin-top:20px;">
          <a href="<?= BASE_URL ?>/admin/members.php" class="btn btn-secondary">← Vissza a listához</a>
        </div>
        <?php endif; ?>
      </form>

      <?php if (!$ro): ?>
      <!-- Külön űrlap az új jelszó generálásához (a fő űrlapba nem ágyazható) -->
      <form id="genpass-form" method="post" action="<?= BASE_URL ?>/actions/member-generate-password.php">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="id" value="<?= (int)$member['id'] ?>">
      </form>
      <?php endif; ?>
    </div>
  </div>

<!-- MTSZ jelvényes minősítések -->
<div class="card" id="mtsz">
  <div class="card-header">
    <h2>MTSZ minősítések</h2>
    <?php if ($mtszRo): ?>
      <span class="badge badge-vezeto" style="font-size:11px;">Csak megtekintés</span>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <p style="font-size:12px;color:var(--text-muted);margin:0 0 16px;">
      A Magyar Természetjáró Szövetség jelvényes minősítései. Rögzítésre kizárólag az egyesületvezető
      és a szakszövetségi vezető jogosult. A tag a saját profilján megtekintheti a megszerzett fokozatokat.
    </p>

    <!-- Megszerzett fokozatok -->
    <?php if (empty($mtszRows)): ?>
      <p style="color:var(--text-muted);font-size:13.5px;margin:0;">Ehhez a taghoz még nincs MTSZ minősítés rögzítve.</p>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Fokozat</th><th>Nyilvántartási szám</th><th>Megszerzés dátuma</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($mtszRows as $q): $qImg = mtszGradeImageUrl($q['grade']); ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:12px;">
                <?php if ($qImg): ?>
                  <img class="mtsz-badge-img" src="<?= e($qImg) ?>" alt="<?= e(mtszGradeLabel($q['grade'])) ?>">
                <?php else: ?>
                  <span class="mtsz-badge <?= mtszGradeClass($q['grade']) ?>" style="margin-right:0;"><?= e(mtszGradeShortLabel($q['grade'])) ?></span>
                <?php endif; ?>
                <span style="font-size:13.5px;font-weight:600;"><?= e(mtszGradeLabel($q['grade'])) ?></span>
              </div>
            </td>
            <td style="font-size:13px;<?= $q['reg_number'] ? '' : 'color:var(--text-muted);' ?>"><?= $q['reg_number'] ? e($q['reg_number']) : '—' ?></td>
            <td style="font-size:13px;white-space:nowrap;"><?= formatDate($q['awarded_on']) ?></td>
            <td class="td-actions" style="white-space:nowrap;text-align:right;">
              <?php if (!$mtszRo): ?>
              <a href="?id=<?= $id ?>&mtsz_edit=<?= (int)$q['id'] ?>#mtsz" class="btn btn-ghost btn-sm">Szerkesztés</a>
              <form method="post" action="<?= BASE_URL ?>/actions/mtsz-qualification-delete.php" style="display:inline;margin:0;"
                    onsubmit="return confirm('Törlöd a(z) &bdquo;<?= e(mtszGradeLabel($q['grade'])) ?>&rdquo; minősítést?')">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="id" value="<?= (int)$q['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Törlés</button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- Rögzítés / szerkesztés -->
    <?php if (!$mtszRo): ?>
    <?php
    $mtszAvailable = [];
    foreach (mtszGradeLabels() as $gKey => $gLabel) {
        if (!in_array($gKey, $mtszTakenGrades, true) || ($mtszEditRow && $mtszEditRow['grade'] === $gKey)) {
            $mtszAvailable[$gKey] = $gLabel;
        }
    }
    $mtszImgMap = [];
    foreach (array_keys($mtszAvailable) as $gKey) {
        $u = mtszGradeImageUrl($gKey);
        if ($u) $mtszImgMap[$gKey] = $u;
    }
    $previewUrl = $mtszEditRow ? mtszGradeImageUrl($mtszEditRow['grade']) : null;
    ?>
    <h3 style="font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin:24px 0 14px;padding-top:20px;border-top:1px solid var(--border);">
      <?= $mtszEditRow ? 'Minősítés szerkesztése' : 'Új minősítés rögzítése' ?>
    </h3>
    <?php if (empty($mtszAvailable)): ?>
      <p style="color:var(--text-muted);font-size:13px;margin:0;">A tag mind az öt fokozatot megszerezte — nincs rögzíthető további minősítés.</p>
    <?php else: ?>
    <form method="post" action="<?= BASE_URL ?>/actions/mtsz-qualification-save.php">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <input type="hidden" name="user_id" value="<?= $id ?>">
      <?php if ($mtszEditRow): ?>
        <input type="hidden" name="id" value="<?= (int)$mtszEditRow['id'] ?>">
      <?php endif; ?>

      <div style="display:flex;align-items:flex-start;gap:20px;">
        <div id="mtsz-preview" style="flex-shrink:0;<?= $previewUrl ? '' : 'display:none;' ?>">
          <img id="mtsz-preview-img" src="<?= e($previewUrl ?? '') ?>" alt="" style="width:96px;height:auto;object-fit:contain;">
        </div>
        <div style="flex:1;min-width:0;">
          <div class="form-grid cols-3">
            <div class="form-group">
              <label>Fokozat <span style="color:var(--danger)">*</span></label>
              <select name="grade" id="mtsz-grade-select" required
                      data-images='<?= e(json_encode($mtszImgMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>'>
                <?php if (!$mtszEditRow): ?><option value="">— Válasszon —</option><?php endif; ?>
                <?php foreach ($mtszAvailable as $gKey => $gLabel): ?>
                  <option value="<?= e($gKey) ?>"<?= ($mtszEditRow && $mtszEditRow['grade'] === $gKey) ? ' selected' : '' ?>><?= e($gLabel) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Nyilvántartási szám</label>
              <input type="text" name="reg_number" maxlength="60" value="<?= e($mtszEditRow['reg_number'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label>Megszerzés dátuma</label>
              <input type="date" name="awarded_on" value="<?= e($mtszEditRow['awarded_on'] ?? '') ?>">
            </div>
          </div>
          <div style="display:flex;gap:10px;margin-top:16px;">
            <button type="submit" class="btn btn-primary"><?= $mtszEditRow ? 'Mentés' : 'Rögzítés' ?></button>
            <?php if ($mtszEditRow): ?>
              <a href="<?= BASE_URL ?>/admin/member-detail.php?id=<?= $id ?>#mtsz" class="btn btn-ghost">Mégse</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </form>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div><!-- /#mtsz -->

  </div><!-- /jobb hasáb -->
</div><!-- /.profile-layout -->

<script>
// Fokozat választásakor a hozzá tartozó jelvénykép előnézete
(function () {
  var sel = document.getElementById('mtsz-grade-select');
  if (!sel) return;
  var wrap = document.getElementById('mtsz-preview');
  var img  = document.getElementById('mtsz-preview-img');
  var map  = {};
  try { map = JSON.parse(sel.dataset.images || '{}'); } catch (e) { map = {}; }
  sel.addEventListener('change', function () {
    var url = map[sel.value];
    if (url) { img.src = url; wrap.style.display = ''; }
    else { wrap.style.display = 'none'; }
  });
})();
</script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
