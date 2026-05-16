<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tours-schema.php';
requireAdmin();

$pdo = getDb();
ensureToursSchema($pdo);

$pageTitle  = 'Statisztikák';
$activePage = 'statistics';
include __DIR__ . '/../includes/admin-header.php';

include __DIR__ . '/../includes/statistics-content.php';

include __DIR__ . '/../includes/admin-footer.php';
