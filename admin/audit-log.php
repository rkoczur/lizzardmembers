<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminOrVezeto();
header('Location: ' . BASE_URL . '/admin/logs.php?tab=audit');
exit;
