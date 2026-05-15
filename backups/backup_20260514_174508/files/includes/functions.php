<?php
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function formatDate(?string $date): string
{
    if (!$date || $date === '0000-00-00') return '—';
    try {
        return (new DateTime($date))->format('Y.m.d');
    } catch (Exception) {
        return '—';
    }
}

function getLevelFromPoints(int $points): int
{
    return match(true) {
        $points >= 500 => 9,
        $points >= 330 => 8,
        $points >= 250 => 7,
        $points >= 170 => 6,
        $points >= 100 => 5,
        $points >= 50  => 4,
        $points >= 25  => 3,
        $points >= 3   => 2,
        default        => 1,
    };
}

function getLevelLabel(int $level): string
{
    return match($level) {
        1 => 'Újonc',
        2 => 'Közlegény',
        3 => 'Tizedes',
        4 => 'Őrmester',
        5 => 'Hadnagy',
        6 => 'Százados',
        7 => 'Őrnagy',
        8 => 'Alezredes',
        9 => 'Ezredes',
        default => 'Szint ' . $level,
    };
}

function getLevelImageFilename(int $level): ?string
{
    return match($level) {
        2 => '1_kozlegeny.png',
        3 => '2_tizedes.png',
        4 => '3_ormester.png',
        5 => '4_hadnagy.png',
        6 => '5_szazados.png',
        7 => '6_ornagy.png',
        8 => '7_alezredes.png',
        9 => '8_ezredes.png',
        default => null,
    };
}

function getLevelClass(int $level): string
{
    return match($level) {
        1 => 'level-1',
        2 => 'level-2',
        3 => 'level-3',
        4 => 'level-4',
        5 => 'level-5',
        6 => 'level-6',
        7 => 'level-7',
        8 => 'level-8',
        9 => 'level-9',
        default => 'level-1',
    };
}

function recalcUserStats(PDO $pdo): void
{
    $pdo->exec("UPDATE users SET points = COALESCE((
        SELECT SUM(t.points)
        FROM tour_members tm
        JOIN tours t ON t.id = tm.tour_id
        WHERE tm.user_id = users.id
    ), 0)");

    $pdo->exec("UPDATE users SET level = CASE
        WHEN points >= 500 THEN 9
        WHEN points >= 330 THEN 8
        WHEN points >= 250 THEN 7
        WHEN points >= 170 THEN 6
        WHEN points >= 100 THEN 5
        WHEN points >= 50  THEN 4
        WHEN points >= 25  THEN 3
        WHEN points >= 3   THEN 2
        ELSE 1
    END");
}

function getMemberStatus(?string $lastPayment): string
{
    if (!$lastPayment || $lastPayment === '0000-00-00') return 'inactive';
    $year     = (int)(new DateTime($lastPayment))->format('Y');
    $thisYear = (int)date('Y');
    if ($year === $thisYear)     return 'active';
    if ($year === $thisYear - 1) return 'overdue';
    return 'inactive';
}

function getMemberStatusLabel(string $status): string
{
    return match($status) {
        'active'   => 'Aktív',
        'overdue'  => 'Tagdíj elmaradás',
        'inactive' => 'Inaktív',
        default    => 'Ismeretlen',
    };
}

function getMemberStatusClass(string $status): string
{
    return match($status) {
        'active'   => 'badge-active',
        'overdue'  => 'badge-overdue',
        'inactive' => 'badge-inactive',
        default    => 'badge-inactive',
    };
}

function getAvatarUrl(?string $filename): string
{
    if ($filename && file_exists(AVATAR_DIR . $filename)) {
        return AVATAR_URL . rawurlencode($filename);
    }
    return BASE_URL . '/assets/img/default-avatar.svg';
}

function flash(string $key, string $message): void
{
    $_SESSION['flash'][$key] = $message;
}

function getFlash(string $key): ?string
{
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid request token.');
    }
}

function logAudit(PDO $pdo, string $action, string $entityType, int $entityId, string $entityLabel, ?array $changes = null): void
{
    static $schemaEnsured = false;
    if (!$schemaEnsured) {
        require_once __DIR__ . '/audit-schema.php';
        ensureAuditSchema($pdo);
        $schemaEnsured = true;
    }
    $pdo->prepare("INSERT INTO audit_log (admin_id, admin_name, action, entity_type, entity_id, entity_label, changes) VALUES (?, ?, ?, ?, ?, ?, ?)")
        ->execute([
            (int)($_SESSION['user_id'] ?? 0),
            $_SESSION['user_name'] ?? 'Ismeretlen',
            $action,
            $entityType,
            $entityId,
            $entityLabel,
            $changes !== null ? json_encode($changes, JSON_UNESCAPED_UNICODE) : null,
        ]);
}
