<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminOrVezeto();

$pageTitle  = 'Toplista';
$activePage = 'toplist';
include __DIR__ . '/../includes/admin-header.php';

include __DIR__ . '/../includes/toplist-content.php';

include __DIR__ . '/../includes/admin-footer.php';
