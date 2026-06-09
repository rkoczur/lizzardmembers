<?php require_once __DIR__ . '/version.php'; ?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'Lizzard Outdoor') ?> — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/public.css">
  <link rel="icon" href="<?= BASE_URL ?>/assets/img/lizzard_logo.png" type="image/png">
</head>
<body class="pub-page">

<nav class="pub-nav">
  <div class="pub-nav-inner">
    <a class="pub-nav-logo" href="<?= BASE_URL ?>/public/index.php">
      <img src="<?= BASE_URL ?>/assets/img/lizzard_logo.png" alt="<?= APP_NAME ?>">
      <span><?= APP_NAME ?></span>
    </a>

    <ul class="pub-nav-menu">

      <!-- Rólunk -->
      <li class="pub-nav-item<?= in_array($activePubPage ?? '', ['rolunk','kapcsolat','gyik','reszveteli-feltetelek','ado1','irattar','penzugyek']) ? ' active' : '' ?>">
        <span>
          Rólunk
          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
        </span>
        <div class="pub-nav-dropdown">
          <a href="<?= BASE_URL ?>/public/rolunk.php">Kik vagyunk mi?</a>
          <a href="<?= BASE_URL ?>/public/kapcsolat.php">Elérhetőségeink</a>
          <a href="<?= BASE_URL ?>/public/gyik.php">GYIK</a>
          <a href="<?= BASE_URL ?>/public/reszveteli-feltetelek.php">Részvételi feltételek</a>
          <a href="<?= BASE_URL ?>/public/ado1.php">Adó 1%</a>
          <a href="<?= BASE_URL ?>/public/irattar.php">Dokumentumtár</a>
          <a href="<?= BASE_URL ?>/public/penzugyek.php">Pénzügyek</a>
        </div>
      </li>

      <!-- Tagság -->
      <li class="pub-nav-item<?= in_array($activePubPage ?? '', ['tagsag','lizzardier','toplista','mtsz-turanaplo']) ? ' active' : '' ?>">
        <span>
          Tagság
          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
        </span>
        <div class="pub-nav-dropdown">
          <a href="<?= BASE_URL ?>/public/tagsag.php">Tagság információk</a>
          <a href="<?= BASE_URL ?>/public/lizzardier.php">Lizzardier pontverseny</a>
          <a href="<?= BASE_URL ?>/public/mtsz-turanaplo.php">MTSZ túranapló</a>
          <a href="<?= BASE_URL ?>/public/toplista.php">Tagok / Toplista</a>
          <a href="<?= BASE_URL ?>/join.php">Belépés az egyesületbe</a>
        </div>
      </li>

      <!-- Közösség -->
      <li class="pub-nav-item<?= in_array($activePubPage ?? '', ['hirek','beszmolok','ev-turatarsa','klubelet']) ? ' active' : '' ?>">
        <span>
          Közösség
          <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
        </span>
        <div class="pub-nav-dropdown">
          <a href="<?= BASE_URL ?>/public/hirek.php">Egyesületi hírek</a>
          <a href="<?= BASE_URL ?>/public/beszmolok.php">Élményblog</a>
          <a href="<?= BASE_URL ?>/public/ev-turatarsa.php">Az év túratársa</a>
          <a href="<?= BASE_URL ?>/public/klubelet.php">Klubélet – események</a>
        </div>
      </li>

      <!-- Adó 1% (közvetlen link) -->
      <li class="pub-nav-item<?= ($activePubPage ?? '') === 'ado1-direct' ? ' active' : '' ?>">
        <a href="<?= BASE_URL ?>/public/ado1.php">Adó 1%</a>
      </li>

      <!-- Belépés – highlighted -->
      <li class="pub-nav-item" style="margin-left:4px;">
        <a href="<?= BASE_URL ?>/join.php" class="pub-nav-highlight">Belépés az egyesületbe</a>
      </li>

      <!-- Túranaptár – highlighted -->
      <li class="pub-nav-item" style="margin-left:4px;">
        <a href="<?= BASE_URL ?>/public/turanyptar.php" class="pub-nav-highlight<?= ($activePubPage ?? '') === 'turanyptar' ? ' pub-nav-highlight-active' : '' ?>">Túranaptár</a>
      </li>

    </ul><!-- /.pub-nav-menu -->

    <!-- Hamburger -->
    <button class="pub-nav-hamburger" id="pub-hamburger" aria-label="Menü">
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
      </svg>
      Menü
    </button>
  </div><!-- /.pub-nav-inner -->

  <!-- Login / dashboard button — absolute, full-width nav edge -->
  <div class="pub-nav-login">
    <?php if (isLoggedIn()): ?>
      <a href="<?= BASE_URL ?>/<?= isAdmin() ? 'admin/index.php' : 'user/index.php' ?>" class="btn btn-primary btn-sm">
        Dashboard →
      </a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary btn-sm">
        Tagok belépése
      </a>
    <?php endif; ?>
  </div>

  <!-- Mobile menu -->
  <div class="pub-nav-mobile-menu" id="pub-mobile-menu">

    <!-- Rólunk -->
    <div class="pub-mobile-group">
      <button class="pub-mobile-btn" type="button">
        Rólunk
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
      <div class="pub-mobile-sub">
        <a href="<?= BASE_URL ?>/public/rolunk.php">Kik vagyunk mi?</a>
        <a href="<?= BASE_URL ?>/public/kapcsolat.php">Elérhetőségeink</a>
        <a href="<?= BASE_URL ?>/public/gyik.php">GYIK</a>
        <a href="<?= BASE_URL ?>/public/reszveteli-feltetelek.php">Részvételi feltételek</a>
        <a href="<?= BASE_URL ?>/public/irattar.php">Dokumentumtár</a>
        <a href="<?= BASE_URL ?>/public/penzugyek.php">Pénzügyek</a>
      </div>
    </div>

    <!-- Tagság -->
    <div class="pub-mobile-group">
      <button class="pub-mobile-btn" type="button">
        Tagság
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
      <div class="pub-mobile-sub">
        <a href="<?= BASE_URL ?>/public/tagsag.php">Tagság információk</a>
        <a href="<?= BASE_URL ?>/public/lizzardier.php">Lizzardier pontverseny</a>
        <a href="<?= BASE_URL ?>/public/mtsz-turanaplo.php">MTSZ túranapló</a>
        <a href="<?= BASE_URL ?>/public/toplista.php">Tagok / Toplista</a>
        <a href="<?= BASE_URL ?>/join.php">Belépés az egyesületbe</a>
      </div>
    </div>

    <!-- Közösség -->
    <div class="pub-mobile-group">
      <button class="pub-mobile-btn" type="button">
        Közösség
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
      </button>
      <div class="pub-mobile-sub">
        <a href="<?= BASE_URL ?>/public/hirek.php">Egyesületi hírek</a>
        <a href="<?= BASE_URL ?>/public/beszmolok.php">Élményblog</a>
        <a href="<?= BASE_URL ?>/public/ev-turatarsa.php">Az év túratársa</a>
        <a href="<?= BASE_URL ?>/public/klubelet.php">Klubélet – események</a>
      </div>
    </div>

    <!-- Közvetlen linkek -->
    <a href="<?= BASE_URL ?>/public/ado1.php" class="pub-mobile-direct">Adó 1%</a>
    <a href="<?= BASE_URL ?>/join.php" class="pub-mobile-direct pub-mobile-highlight">Belépés az egyesületbe</a>
    <a href="<?= BASE_URL ?>/public/turanyptar.php" class="pub-mobile-direct pub-mobile-highlight">Túranaptár</a>
    <?php if (isLoggedIn()): ?>
      <a href="<?= BASE_URL ?>/<?= isAdmin() ? 'admin/index.php' : 'user/index.php' ?>" class="pub-mobile-direct" style="color:#F4E7CF;font-weight:700;">Dashboard →</a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/login.php" class="pub-mobile-direct" style="color:#F4E7CF;font-weight:700;">Tagok belépése →</a>
    <?php endif; ?>

  </div>
</nav>

<script>
(function () {
  var btn  = document.getElementById('pub-hamburger');
  var menu = document.getElementById('pub-mobile-menu');
  if (btn && menu) {
    btn.addEventListener('click', function () {
      menu.classList.toggle('open');
    });
  }

  // Submenu toggle
  document.querySelectorAll('.pub-mobile-btn').forEach(function (b) {
    b.addEventListener('click', function () {
      var group = this.closest('.pub-mobile-group');
      var isOpen = group.classList.contains('open');
      // Csuk minden mást
      document.querySelectorAll('.pub-mobile-group.open').forEach(function (g) {
        g.classList.remove('open');
      });
      if (!isOpen) group.classList.add('open');
    });
  });
})();
</script>
