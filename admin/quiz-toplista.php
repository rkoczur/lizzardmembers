<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/quiz-schema.php';
requireAdminOrVezeto();

$quizBase = BASE_URL . '/admin';

$pageTitle  = 'Kvíz toplisták';
$activePage = 'quiz';
include __DIR__ . '/../includes/admin-header.php';
include __DIR__ . '/../includes/quiz-toplista-body.php';
include __DIR__ . '/../includes/admin-footer.php';
