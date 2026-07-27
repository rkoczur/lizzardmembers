<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/quiz-schema.php';
requireUser();

$quizBase = BASE_URL . '/user';

$pageTitle  = 'Kvíz toplisták';
$activePage = 'quiz';
include __DIR__ . '/../includes/user-header.php';
include __DIR__ . '/../includes/quiz-toplista-body.php';
include __DIR__ . '/../includes/user-footer.php';
