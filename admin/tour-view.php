<?php
// Túra megtekintése úgy, ahogy a tagok látják — kiegészítve a túrán rangot szerzett tagokkal
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/rank-history.php';
requireAdminOrVezeto();

$adminView = true;
require __DIR__ . '/../user/tour-detail.php';
