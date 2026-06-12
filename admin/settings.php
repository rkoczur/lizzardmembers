<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tours-schema.php';
require_once __DIR__ . '/../includes/app-settings-schema.php';
requireAdminOrVezeto();

$pdo = getDb();
ensureToursSchema($pdo);
ensureAppSettingsSchema($pdo);
$smtp = getSmtpConfig($pdo);

$recaptchaSiteKey  = getSetting($pdo, 'recaptcha_site_key', '');
$recaptchaHasSecret = getSetting($pdo, 'recaptcha_secret', '') !== '';
$socialDefaultImg  = getSetting($pdo, 'social_default_image', '');

$countries = getCountries($pdo, false);

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$pageTitle  = 'Beállítások';
$activePage = 'settings';
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
  <h1>Beállítások</h1>
</div>

<div id="settings-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

  <!-- Országok -->
  <div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
      <h2>Országok</h2>
      <?php if (isAdmin()): ?>
      <a href="<?= BASE_URL ?>/admin/country-add.php" class="btn btn-primary btn-sm">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" width="14" height="14">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        Új ország
      </a>
      <?php endif; ?>
    </div>
    <div class="card-body" style="padding-bottom:0;">
      <p style="color:var(--text-muted);font-size:13px;margin-bottom:16px;">
        Az itt megadott országkódokat kell használni a túrák CSV-importjában (<strong>Országkód</strong> oszlop, pl. <code>HU</code>, <code>AT</code>).
        Az inaktív országok nem jelennek meg a túra-szerkesztő legördülőjében.
      </p>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:48px;">Zászló</th>
            <th style="width:70px;">Kód</th>
            <th>Magyar elnevezés</th>
            <th style="width:90px;">Sorrend</th>
            <th style="width:80px;">Aktív</th>
            <th style="width:110px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($countries as $c): ?>
          <tr <?= $c['active'] ? '' : 'style="opacity:.5;"' ?>>
            <td>
              <?php if ($c['flag_filename']): ?>
                <img src="<?= e(getFlagUrl($c['flag_filename'])) ?>"
                     style="width:32px;height:22px;object-fit:cover;border:1px solid var(--border);border-radius:2px;" alt="">
              <?php else: ?>
                <span style="display:inline-block;width:32px;height:22px;background:var(--bg-subtle);border:1px solid var(--border);border-radius:2px;"></span>
              <?php endif; ?>
            </td>
            <td><code style="font-size:.9em;"><?= e($c['code']) ?></code></td>
            <td><?= e($c['name_hu']) ?></td>
            <td style="color:var(--text-muted);font-size:.9em;"><?= (int)$c['sort_order'] ?></td>
            <td>
              <?php if ($c['active']): ?>
                <span class="badge badge-active">Aktív</span>
              <?php else: ?>
                <span class="badge badge-inactive">Inaktív</span>
              <?php endif; ?>
            </td>
            <td>
              <a href="<?= BASE_URL ?>/admin/country-detail.php?id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm"><?= isAdmin() ? 'Szerkesztés' : 'Megtekintés' ?></a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($countries)): ?>
          <tr><td colspan="6">
            <div class="empty-state"><p>Még nincs egyetlen ország sem rögzítve.</p></div>
          </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- SMTP + reCAPTCHA beállítások (jobb oszlop, egymás alatt) -->
  <?php if (isAdmin()): ?>
  <div class="settings-stack">
  <div class="card">
    <div class="card-header"><h2>SMTP beállítások</h2></div>
    <div class="card-body">
      <form method="post" action="<?= BASE_URL ?>/actions/settings-smtp-save.php">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="form-grid">
          <div class="form-group">
            <label>SMTP szerver</label>
            <input type="text" name="smtp_host" value="<?= e($smtp['host']) ?>" placeholder="pl. smtp.gmail.com">
          </div>
          <div class="form-group">
            <label>Port</label>
            <input type="number" name="smtp_port" value="<?= (int)$smtp['port'] ?: 587 ?>" min="1" max="65535" placeholder="587">
          </div>
          <div class="form-group">
            <label>Titkosítás</label>
            <select name="smtp_encryption">
              <option value="tls" <?= $smtp['encryption'] === 'tls' ? 'selected' : '' ?>>STARTTLS (port 587)</option>
              <option value="ssl" <?= $smtp['encryption'] === 'ssl' ? 'selected' : '' ?>>SSL/TLS (port 465)</option>
              <option value=""   <?= $smtp['encryption'] === ''    ? 'selected' : '' ?>>Nincs titkosítás (port 25)</option>
            </select>
          </div>
          <div class="form-group">
            <label>SMTP felhasználónév</label>
            <input type="text" name="smtp_user" value="<?= e($smtp['user']) ?>" placeholder="felhasznalo@domain.hu" autocomplete="off">
          </div>
          <div class="form-group">
            <label>SMTP jelszó</label>
            <input type="password" name="smtp_pass" placeholder="<?= $smtp['pass'] !== '' ? '••••••••' : 'Jelszó megadása' ?>" autocomplete="new-password">
            <small style="color:var(--text-muted);">Üresen hagyva a meglévő jelszó megmarad.</small>
          </div>
          <div class="form-group">
            <label>Feladó e-mail cím</label>
            <input type="email" name="smtp_from_email" value="<?= e($smtp['from_email']) ?>" placeholder="noreply@domain.hu">
            <small style="color:var(--text-muted);">Üresen hagyva az SMTP felhasználónevet használja.</small>
          </div>
          <div class="form-group">
            <label>Feladó megjelenített neve</label>
            <input type="text" name="smtp_from_name" value="<?= e($smtp['from_name']) ?>" placeholder="<?= e(APP_NAME) ?>">
          </div>
        </div>
        <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
          <button type="submit" class="btn btn-primary">SMTP beállítások mentése</button>
        </div>
      </form>
      <form method="post" action="<?= BASE_URL ?>/actions/settings-smtp-test.php" style="margin-top:10px;">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <button type="submit" class="btn btn-secondary btn-sm" style="display:flex;align-items:center;gap:5px;">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="14" height="14">
            <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
          </svg>
          Teszt e-mail küldése saját címre
        </button>
      </form>
    </div>
  </div>

  <!-- reCAPTCHA (spam-védelem) -->
  <div class="card">
    <div class="card-header"><h2>reCAPTCHA (spam-védelem)</h2></div>
    <div class="card-body">
      <p style="color:var(--text-muted);font-size:13px;margin-bottom:16px;">
        Google reCAPTCHA v2 („Nem vagyok robot") a nyilvános űrlapokhoz (kapcsolat, belépési kérelem, vendég túrajelentkezés, jelszó-visszaállítás).
        A kulcsokat a <a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener">Google reCAPTCHA admin</a> oldalon hozhatod létre (típus: <strong>reCAPTCHA v2 → „I'm not a robot" Checkbox</strong>).
        Ha üresen hagyod, a reCAPTCHA kikapcsolt marad.
      </p>
      <form method="post" action="<?= BASE_URL ?>/actions/settings-captcha-save.php">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <div class="form-grid">
          <div class="form-group">
            <label>Site key (publikus)</label>
            <input type="text" name="recaptcha_site_key" value="<?= e($recaptchaSiteKey) ?>" placeholder="6Lxxxxxxxxxxxxxxxxxxxxxxxxx" autocomplete="off">
          </div>
          <div class="form-group">
            <label>Secret key (titkos)</label>
            <input type="password" name="recaptcha_secret" placeholder="<?= $recaptchaHasSecret ? '••••••••' : 'Titkos kulcs megadása' ?>" autocomplete="new-password">
            <small style="color:var(--text-muted);">Üresen hagyva a meglévő titkos kulcs megmarad.</small>
          </div>
        </div>
        <?php if ($recaptchaHasSecret): ?>
        <label style="display:flex;align-items:center;gap:8px;margin-top:12px;font-size:13px;cursor:pointer;">
          <input type="checkbox" name="recaptcha_clear_secret" value="1">
          Titkos kulcs törlése (reCAPTCHA kikapcsolása)
        </label>
        <?php endif; ?>
        <div style="margin-top:16px;">
          <button type="submit" class="btn btn-primary">reCAPTCHA beállítások mentése</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Közösségi megosztási kép (OpenGraph) -->
  <div class="card">
    <div class="card-header"><h2>Közösségi megosztási kép (OpenGraph)</h2></div>
    <div class="card-body">
      <p style="color:var(--text-muted);font-size:13px;margin-bottom:16px;">
        Ez a kép jelenik meg alapértelmezetten, amikor az oldal linkjét megosztják (Facebook, Messenger stb.).
        A túrák és bejegyzések a saját borítóképüket használják; ez a globális tartalék.
      </p>
      <form method="post" action="<?= BASE_URL ?>/actions/settings-social-save.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <?php if ($socialDefaultImg !== ''): ?>
          <img src="<?= e(SOCIAL_URL . $socialDefaultImg) ?>" alt="" style="max-width:320px;width:100%;border-radius:8px;display:block;margin-bottom:10px;border:1px solid var(--border);">
          <label style="display:flex;align-items:center;gap:8px;font-size:13px;margin-bottom:12px;cursor:pointer;">
            <input type="checkbox" name="delete_social_image" value="1" style="width:auto;"> Kép törlése (visszaáll a logóra)
          </label>
        <?php endif; ?>
        <div class="form-group">
          <label>Kép feltöltése</label>
          <input type="file" name="social_image" accept="image/jpeg,image/png,image/webp">
          <small style="display:block;margin-top:4px;color:var(--text-muted);">Ajánlott méret: 1200×630 px. JPG, PNG vagy WebP, max. 5 MB.</small>
        </div>
        <div style="margin-top:16px;">
          <button type="submit" class="btn btn-primary">Megosztási kép mentése</button>
        </div>
      </form>
    </div>
  </div>
  </div><!-- /.settings-stack -->
  <?php endif; ?>

</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
