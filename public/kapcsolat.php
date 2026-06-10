<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';
require_once __DIR__ . '/../includes/captcha.php';

$pdo = getDb();
ensurePublicSchema($pdo);

$stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = 'kapcsolat' LIMIT 1");
$stmt->execute();
$page = $stmt->fetch();

$contactSuccess = getFlash('contact_success');
$contactError   = getFlash('contact_error');
$cOld = $_SESSION['contact_old'] ?? [];
unset($_SESSION['contact_old']);

$pageTitle     = 'Kapcsolat';
$activePubPage = 'kapcsolat';
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap-narrow">
  <div class="pub-page-header">
    <h1>Elérhetőségeink</h1>
    <p>Keress minket bátran – szívesen segítünk!</p>
  </div>

  <div class="pub-contact-grid">
    <!-- Cím (nagyobb, bal oszlop) -->
    <div class="pub-contact-item">
      <div class="pub-contact-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
      </div>
      <div>
        <h4>Cím</h4>
        <p class="pub-contact-name">Leguán Osztag Természetjáró Egyesület</p>
        <p class="pub-contact-data">1041 Budapest, Rózsa u. 59. III/9.</p>
      </div>
    </div>
    <!-- Telefon (kisebb, jobb oszlop) -->
    <div class="pub-contact-item">
      <div class="pub-contact-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
      </div>
      <div>
        <h4>Telefon</h4>
        <p><a class="pub-contact-data" href="tel:+36203961021">+36 (20) 396-1021</a></p>
      </div>
    </div>
    <!-- Bankszámla (nagyobb, bal oszlop) -->
    <div class="pub-contact-item">
      <div class="pub-contact-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
      </div>
      <div>
        <h4>Bankszámla</h4>
        <p class="pub-contact-data">16200120-18542675</p>
        <p class="pub-contact-name">MagNet Magyar Közösségi Bank</p>
        <p class="pub-contact-sub">Adószám: 18902622-1-41</p>
      </div>
    </div>
    <!-- E-mail (kisebb, jobb oszlop) -->
    <div class="pub-contact-item">
      <div class="pub-contact-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
      </div>
      <div>
        <h4>E-mail</h4>
        <p><a class="pub-contact-mail" href="mailto:info@lizzard.hu">info@lizzard.hu</a></p>
      </div>
    </div>
  </div>

  <?php if (!empty($page['body'])): ?>
    <div class="pub-prose"><?= $page['body'] ?></div>
  <?php endif; ?>

  <div class="pub-contact-form-section" id="kapcsolat-form">
    <h2 class="pub-section-title">Írj nekünk!</h2>

    <?php if ($contactSuccess): ?>
      <div class="alert alert-success" data-auto-dismiss><?= e($contactSuccess) ?></div>
    <?php endif; ?>
    <?php if ($contactError): ?>
      <div class="alert alert-error" data-auto-dismiss><?= e($contactError) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL ?>/actions/contact-send.php" class="pub-contact-form">
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
      <!-- spam-csapda: emberek számára rejtett mező -->
      <div class="pub-hp" aria-hidden="true">
        <label>Ezt a mezőt hagyd üresen
          <input type="text" name="website" tabindex="-1" autocomplete="off">
        </label>
      </div>
      <div class="pub-form-grid">
        <div class="pub-form-group">
          <label for="cf-name">Neved <span style="color:var(--danger)">*</span></label>
          <input type="text" id="cf-name" name="name" maxlength="150" value="<?= e($cOld['name'] ?? '') ?>" required>
        </div>
        <div class="pub-form-group">
          <label for="cf-email">E-mail címed <span style="color:var(--danger)">*</span></label>
          <input type="email" id="cf-email" name="email" maxlength="190" value="<?= e($cOld['email'] ?? '') ?>" required>
        </div>
        <div class="pub-form-group pub-form-full">
          <label for="cf-subject">Tárgy</label>
          <input type="text" id="cf-subject" name="subject" maxlength="200" value="<?= e($cOld['subject'] ?? '') ?>">
        </div>
        <div class="pub-form-group pub-form-full">
          <label for="cf-message">Üzenet <span style="color:var(--danger)">*</span></label>
          <textarea id="cf-message" name="message" rows="6" maxlength="5000" required><?= e($cOld['message'] ?? '') ?></textarea>
        </div>
      </div>
      <?= recaptchaField($pdo) ?>
      <button type="submit" class="btn btn-primary">Üzenet küldése</button>
    </form>
  </div>

  <h2 class="pub-section-title" style="margin-top:32px;">Közösségi oldalak</h2>
  <div class="pub-social-list">
    <a href="https://www.facebook.com/lizzardoutdoor/" target="_blank" rel="noopener" class="pub-social-btn pub-social-fb">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
      Facebook
    </a>
    <a href="https://www.instagram.com/lizzardoutdoor/" target="_blank" rel="noopener" class="pub-social-btn pub-social-ig">
      Instagram
    </a>
    <a href="https://www.youtube.com/@lizzardhu" target="_blank" rel="noopener" class="pub-social-btn pub-social-yt">
      YouTube
    </a>
  </div>
</div>

<?= recaptchaScript($pdo) ?>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
