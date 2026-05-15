<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireUser();

$pageTitle  = 'Toplista';
$activePage = 'toplist';
include __DIR__ . '/../includes/user-header.php';

include __DIR__ . '/../includes/toplist-content.php';

include __DIR__ . '/../includes/user-footer.php';
