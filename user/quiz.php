<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/quiz-schema.php';
requireUser();

$pdo = getDb();
ensureQuizSchema($pdo);

$quizOptions = [
    'colors'     => quizAllColors($pdo),
    'continents' => quizAllContinents($pdo),
];
$quizActiveGame = quizActiveGame($pdo, getCurrentUserId());

$flash_success = getFlash('success');
$flash_error   = getFlash('error');
$quizBase      = BASE_URL . '/user';

$pageTitle  = 'Kvíz játék';
$activePage = 'quiz';
include __DIR__ . '/../includes/user-header.php';
include __DIR__ . '/../includes/quiz-play-body.php';
include __DIR__ . '/../includes/user-footer.php';
