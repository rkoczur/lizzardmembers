<?php
function requireLogin(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function requireAdmin(): void
{
    requireLogin();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        header('Location: ' . BASE_URL . '/user/index.php');
        exit;
    }
}

function requireUser(): void
{
    requireLogin();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool
{
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

function getCurrentUserId(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function isVezeto(): bool
{
    return ($_SESSION['user_role'] ?? '') === 'vezeto';
}

function isAdminOrVezeto(): bool
{
    return in_array($_SESSION['user_role'] ?? '', ['admin', 'vezeto'], true);
}

function requireAdminOrVezeto(): void
{
    requireLogin();
    if (!isAdminOrVezeto()) {
        header('Location: ' . BASE_URL . '/user/index.php');
        exit;
    }
}

function setUserSession(array $user): void
{
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = trim(($user['lastname'] ?? '') . ' ' . ($user['firstname'] ?? ''));
    $_SESSION['username']  = $user['username'];
}
