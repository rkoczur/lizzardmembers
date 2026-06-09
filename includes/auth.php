<?php

// ── Basic guards ──────────────────────────────────────────────────────────────

function requireLogin(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
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

function getCurrentUserId(): int
{
    return (int)($_SESSION['user_id'] ?? 0);
}

function setUserSession(array $user): void
{
    $_SESSION['user_id']     = $user['id'];
    $_SESSION['user_role']   = $user['role'];
    $_SESSION['user_name']   = trim(($user['lastname'] ?? '') . ' ' . ($user['firstname'] ?? ''));
    $_SESSION['username']    = $user['username'];
    $_SESSION['user_avatar'] = $user['profile_picture'] ?? null;
}

// ── Role helpers ──────────────────────────────────────────────────────────────

function currentRole(): string
{
    return $_SESSION['user_role'] ?? 'user';
}

/** Full admin rights: Egyesületvezető + Egyesületvezető-helyettes */
function isAdmin(): bool
{
    return in_array(currentRole(), ['admin', 'helyettes'], true);
}

/** Legacy: true only for the top admin role value */
function isRootAdmin(): bool
{
    return currentRole() === 'admin';
}

/** Szakszövetségi vezető (legacy role value 'vezeto') */
function isVezeto(): bool
{
    return currentRole() === 'vezeto';
}

/** Any leadership role — can log into the admin area */
function isAnyLeader(): bool
{
    return in_array(currentRole(), ['admin', 'helyettes', 'penzugyi', 'jogi', 'kommunikacios', 'vezeto'], true);
}

function isAdminOrVezeto(): bool
{
    return isAnyLeader();
}

// ── Access guards ─────────────────────────────────────────────────────────────

/** Full admin only (admin + helyettes) */
function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . '/user/index.php');
        exit;
    }
}

/** Any leadership role */
function requireAdminOrVezeto(): void
{
    requireLogin();
    if (!isAnyLeader()) {
        header('Location: ' . BASE_URL . '/user/index.php');
        exit;
    }
}

function requireLeader(): void
{
    requireAdminOrVezeto();
}

// ── Section permission helpers ────────────────────────────────────────────────

function canManageMembers(): bool
{
    return isAdmin();
}

function canManageFinances(): bool
{
    return in_array(currentRole(), ['admin', 'helyettes', 'penzugyi'], true);
}

function canManageDocuments(): bool
{
    return in_array(currentRole(), ['admin', 'helyettes', 'penzugyi', 'jogi'], true);
}

function canManagePosts(): bool
{
    return in_array(currentRole(), ['admin', 'helyettes', 'kommunikacios'], true);
}

function canManageFaq(): bool
{
    return in_array(currentRole(), ['admin', 'helyettes', 'kommunikacios'], true);
}

/**
 * Kommunikációs vezető may edit: ado1, klubelet, rolunk, tagsag
 * Jogi vezető may edit: reszveteli-feltetelek
 * $slug = null means "can the user manage at least one page?"
 */
function canManagePages(?string $slug = null): bool
{
    if (isAdmin()) return true;
    $role = currentRole();
    if ($role === 'kommunikacios') {
        return $slug === null || in_array($slug, ['ado1', 'klubelet', 'rolunk', 'tagsag', 'mtsz-turanaplo'], true);
    }
    if ($role === 'jogi') {
        return $slug === null || $slug === 'reszveteli-feltetelek';
    }
    if ($role === 'penzugyi') {
        return $slug === null || $slug === 'penzugyek';
    }
    return false;
}

function canManageTours(): bool
{
    return in_array(currentRole(), ['admin', 'helyettes', 'vezeto'], true);
}

function canManagePayments(): bool
{
    return in_array(currentRole(), ['admin', 'helyettes', 'penzugyi'], true);
}

function canAccessWebsite(): bool
{
    return canManageFinances() || canManageDocuments() || canManagePosts()
        || canManageFaq() || canManagePages();
}

// ── Display helpers ───────────────────────────────────────────────────────────

function getRoleLabel(string $role): string
{
    return match($role) {
        'admin'         => 'Egyesületvezető',
        'helyettes'     => 'Egyesületvezető-helyettes',
        'penzugyi'      => 'Pénzügyi vezető',
        'jogi'          => 'Jogi vezető',
        'kommunikacios' => 'Kommunikációs vezető',
        'vezeto'        => 'Szakszövetségi vezető',
        default         => 'Tag',
    };
}
