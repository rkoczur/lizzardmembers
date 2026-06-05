<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';

$pdo = getDb();
ensurePublicSchema($pdo);

$stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = 'kapcsolat' LIMIT 1");
$stmt->execute();
$page = $stmt->fetch();

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
    <div class="pub-contact-item">
      <div class="pub-contact-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
      </div>
      <div>
        <h4>Cím</h4>
        <p>Leguán Osztag Természetjáró Egyesület<br>1041 Budapest, Rózsa u. 59. III/9.</p>
      </div>
    </div>
    <div class="pub-contact-item">
      <div class="pub-contact-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 13a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
      </div>
      <div>
        <h4>Telefon</h4>
        <p><a href="tel:+36203961021">+36 (20) 396-1021</a></p>
      </div>
    </div>
    <div class="pub-contact-item">
      <div class="pub-contact-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
      </div>
      <div>
        <h4>E-mail</h4>
        <p><a href="mailto:info@lizzard.hu">info@lizzard.hu</a></p>
        <p><a href="mailto:koczur.richard@lizzard.hu">koczur.richard@lizzard.hu</a> (Elnök)</p>
      </div>
    </div>
    <div class="pub-contact-item">
      <div class="pub-contact-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
      </div>
      <div>
        <h4>Bankszámla</h4>
        <p>16200120-18542675<br>MagNet Magyar Közösségi Bank</p>
        <p style="font-size:12px;color:var(--text-muted);">Adószám: 18902622-1-41</p>
      </div>
    </div>
  </div>

  <?php if (!empty($page['body'])): ?>
    <div class="pub-prose"><?= $page['body'] ?></div>
  <?php endif; ?>

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

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
