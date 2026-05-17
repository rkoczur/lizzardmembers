<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle ?? 'Admin') ?> — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<?php require_once __DIR__ . '/version.php'; ?>
<body>
<div class="app-wrapper">

  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
      <div class="logo-wrap">
        <img src="<?= BASE_URL ?>/assets/img/lizzard_logo.png" alt="logo" class="sidebar-logo-img">
        <div>
          <div class="app-name"><?= APP_NAME ?></div>
          <div class="app-tagline">Admin felület</div>
        </div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-label">Főmenü</div>
      <a href="<?= BASE_URL ?>/admin/index.php" class="<?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
          <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
        </svg>
        Vezérlőpult
      </a>
      <a href="<?= BASE_URL ?>/admin/members.php" class="<?= ($activePage ?? '') === 'members' ? 'active' : '' ?>">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        Tagok
      </a>
      <a href="<?= BASE_URL ?>/admin/tours.php" class="<?= ($activePage ?? '') === 'tours' ? 'active' : '' ?>">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/>
          <line x1="8" y1="2" x2="8" y2="18"/>
          <line x1="16" y1="6" x2="16" y2="22"/>
        </svg>
        Túrák
      </a>
      <a href="<?= BASE_URL ?>/admin/toplist.php" class="<?= ($activePage ?? '') === 'toplist' ? 'active' : '' ?>">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path d="M8 21H5a2 2 0 0 1-2-2v-1a5 5 0 0 1 5-5h8a5 5 0 0 1 5 5v1a2 2 0 0 1-2 2h-3"/>
          <polyline points="12 3 15 8 21 8 16.5 12.5 18.5 18.5 12 15 5.5 18.5 7.5 12.5 3 8 9 8 12 3"/>
        </svg>
        Toplista
      </a>
      <a href="<?= BASE_URL ?>/admin/statistics.php" class="<?= ($activePage ?? '') === 'statistics' ? 'active' : '' ?>">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>
        </svg>
        Statisztikák
      </a>
      <a href="<?= BASE_URL ?>/admin/security.php" class="<?= ($activePage ?? '') === 'security' ? 'active' : '' ?>">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
        Biztonság
      </a>
      <a href="<?= BASE_URL ?>/admin/logs.php" class="<?= ($activePage ?? '') === 'logs' ? 'active' : '' ?>">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/>
          <rect x="9" y="3" width="6" height="4" rx="1"/>
          <line x1="9" y1="12" x2="15" y2="12"/>
          <line x1="9" y1="16" x2="13" y2="16"/>
        </svg>
        Naplók
      </a>
      <a href="<?= BASE_URL ?>/admin/settings.php" class="<?= ($activePage ?? '') === 'settings' ? 'active' : '' ?>">
        <svg class="nav-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <circle cx="12" cy="12" r="3"/>
          <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
        </svg>
        Beállítások
      </a>
      <div class="nav-section-label" style="margin-top:8px;">Fiók</div>
      <a href="<?= BASE_URL ?>/admin/profile.php" class="<?= ($activePage ?? '') === 'profile' ? 'active' : '' ?>">
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
        <div class="user-name"><?= e($_SESSION['user_name'] ?? 'Admin') ?></div>
        <div class="user-role"><?= isAdmin() ? 'Rendszergazda' : 'Vezető' ?></div>
      </div>
    </div>
    <div class="sidebar-version">Verzió: <?= APP_VERSION ?> - Copyright © Koczur Richárd</div>
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
      <div class="topbar-actions">
        <a href="<?= BASE_URL ?>/user/index.php" class="btn btn-secondary btn-sm" title="Tagok portáljának megtekintése" style="display:flex;align-items:center;gap:5px;">
          <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
            <circle cx="12" cy="12" r="3"/>
          </svg>
          Tagnézet
        </a>
      </div>
    </div>
    <div class="page-body">
      <div id="alert-container"></div>
