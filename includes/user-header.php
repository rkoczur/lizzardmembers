<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'Fiókom') ?> — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="app-wrapper">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="logo-wrap">
        <img src="<?= BASE_URL ?>/assets/img/lizzard_logo.png" alt="logo" class="sidebar-logo-img">
        <div>
          <div class="app-name"><?= APP_NAME ?></div>
          <div class="app-tagline">Tagok portálja</div>
        </div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-label">Főmenü</div>
      <a href="<?= BASE_URL ?>/user/index.php" class="<?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
          <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
        </svg>
        Vezérlőpult
      </a>
      <a href="<?= BASE_URL ?>/user/toplist.php" class="<?= ($activePage ?? '') === 'toplist' ? 'active' : '' ?>">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path d="M8 21H5a2 2 0 0 1-2-2v-1a5 5 0 0 1 5-5h8a5 5 0 0 1 5 5v1a2 2 0 0 1-2 2h-3"/>
          <polyline points="12 3 15 8 21 8 16.5 12.5 18.5 18.5 12 15 5.5 18.5 7.5 12.5 3 8 9 8 12 3"/>
        </svg>
        Toplista
      </a>
      <a href="<?= BASE_URL ?>/user/tours.php" class="<?= ($activePage ?? '') === 'tours' ? 'active' : '' ?>">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path d="M3 12l9-9 9 9"/><path d="M9 21V12h6v9"/><path d="M3 12v9h18v-9"/>
        </svg>
        Túrák
      </a>
      <a href="<?= BASE_URL ?>/user/statistics.php" class="<?= ($activePage ?? '') === 'statistics' ? 'active' : '' ?>">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
        </svg>
        Statisztikák
      </a>
      <div class="nav-section-label" style="margin-top:8px;">Fiók</div>
      <a href="<?= BASE_URL ?>/user/profile.php" class="<?= ($activePage ?? '') === 'profile' ? 'active' : '' ?>">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
          <circle cx="12" cy="7" r="4"/>
        </svg>
        Saját profilom
      </a>
      <a href="<?= BASE_URL ?>/logout.php">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        Kijelentkezés
      </a>
    </nav>

    <div class="sidebar-user">
      <img class="user-avatar-sm"
           src="<?= getAvatarUrl($_SESSION['user_avatar'] ?? null) ?>"
           alt="avatar">
      <div class="user-info">
        <div class="user-name"><?= e($_SESSION['user_name'] ?? 'Tag') ?></div>
        <div class="user-role">Tag</div>
      </div>
    </div>
  </aside>

  <div class="sidebar-overlay" id="sidebar-overlay"></div>

  <!-- Main -->
  <div class="main-content">
    <div class="topbar">
      <button class="hamburger-btn" id="hamburger-btn" aria-label="Menü megnyitása">
        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
      </button>
      <span class="page-title"><?= e($pageTitle ?? '') ?></span>
    </div>
    <div class="page-body">
      <div id="alert-container"></div>
