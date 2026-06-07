<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

// If can't connect to MySQL server at all
if (!canConnectToServer()) {
    header('Location: ' . BASE_URL . '/setup.php?error=noserver');
    exit;
}

// If database doesn't exist
if (!databaseExists()) {
    header('Location: ' . BASE_URL . '/setup.php');
    exit;
}

// Always show the public homepage
header('Location: ' . BASE_URL . '/public/index.php');
exit;
