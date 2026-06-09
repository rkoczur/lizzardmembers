<?php
/**
 * Megosztott bejelentkezési logika a login.php és a beágyazott
 * (helyben megjelenő) bejelentkezési űrlapok számára.
 *
 * Siker esetén beállítja a session-t (setUserSession + avatar), de NEM
 * irányít át — az átirányításról a hívó dönt (szerep alapján vagy saját oldalra).
 */
require_once __DIR__ . '/user-schema.php';
require_once __DIR__ . '/ip-block-schema.php';
require_once __DIR__ . '/login-log-schema.php';

/**
 * Megkísérli a bejelentkezést a megadott azonosító + jelszó párossal.
 * Kezeli az IP-zárolást, a fiók- és IP-szintű kísérletszámlálást és a naplózást.
 *
 * @return array{ok:bool, error:string, user:?array}
 */
function attemptLogin(PDO $pdo, string $identifier, string $password): array
{
    $identifier = trim($identifier);

    if ($identifier === '' || $password === '') {
        return ['ok' => false, 'error' => 'Kérjük, adja meg felhasználónevét és jelszavát.', 'user' => null];
    }

    ensureUserSchema($pdo);
    ensureIpBlockSchema($pdo);
    ensureLoginLogSchema($pdo);

    $ip        = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

    $writeLog = function (string $status, ?int $userId, string $name, string $uname, ?string $reason) use ($pdo, $ip, $userAgent): void {
        $pdo->prepare("INSERT INTO login_log (user_id, name, username, ip, user_agent, status, fail_reason) VALUES (?,?,?,?,?,?,?)")
            ->execute([$userId ?: null, $name, $uname, $ip, $userAgent, $status, $reason]);
    };

    // 1. Check if this IP is already blocked
    $ipRow = $pdo->prepare("SELECT attempts, blocked FROM ip_blocks WHERE ip = ?");
    $ipRow->execute([$ip]);
    $ipBlock = $ipRow->fetch();

    if ($ipBlock && $ipBlock['blocked']) {
        $writeLog('failed', null, '', $identifier, 'ip_blocked');
        return ['ok' => false, 'error' => 'Az Ön IP-címe zárolva lett ismételt sikertelen bejelentkezési kísérlet miatt. Kérje az adminisztrátor segítségét.', 'user' => null];
    }

    // 2. Find user (no active filter — we check states explicitly)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) LIMIT 1");
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if (!$user) {
        $writeLog('failed', null, '', $identifier, 'unknown_user');
        // Unknown identifier → track IP attempts
        if ($ipBlock) {
            $newIpAttempts = (int)$ipBlock['attempts'] + 1;
            $nowBlocked    = $newIpAttempts >= 3 ? 1 : 0;
            $pdo->prepare("UPDATE ip_blocks SET attempts = ?, blocked = ? WHERE ip = ?")
                ->execute([$newIpAttempts, $nowBlocked, $ip]);
        } else {
            $pdo->prepare("INSERT INTO ip_blocks (ip, attempts, blocked) VALUES (?, 1, 0)")
                ->execute([$ip]);
            $newIpAttempts = 1;
        }
        $error = $newIpAttempts >= 3
            ? 'Az Ön IP-címe zárolva lett ismételt sikertelen bejelentkezési kísérlet miatt. Kérje az adminisztrátor segítségét.'
            : 'Érvénytelen felhasználónév vagy jelszó. (' . $newIpAttempts . '/3 IP-kísérlet)';
        return ['ok' => false, 'error' => $error, 'user' => null];
    }

    if (!$user['active']) {
        $writeLog('failed', (int)$user['id'], $user['lastname'] . ' ' . $user['firstname'], $user['username'], 'account_inactive');
        // Deactivated account — generic message to avoid enumeration
        return ['ok' => false, 'error' => 'Érvénytelen felhasználónév vagy jelszó.', 'user' => null];
    }

    if (!empty($user['locked_at'])) {
        $writeLog('failed', (int)$user['id'], $user['lastname'] . ' ' . $user['firstname'], $user['username'], 'account_locked');
        // Account locked by failed attempts
        return ['ok' => false, 'error' => 'A fiókja zárolva lett ismételt hibás bejelentkezési kísérlet miatt. Kérje az adminisztrátor segítségét.', 'user' => null];
    }

    if (!password_verify($password, $user['password'])) {
        $writeLog('failed', (int)$user['id'], $user['lastname'] . ' ' . $user['firstname'], $user['username'], 'wrong_password');
        // Wrong password → track per-user attempts
        $newAttempts = (int)($user['login_attempts'] ?? 0) + 1;
        if ($newAttempts >= 3) {
            $pdo->prepare("UPDATE users SET login_attempts = ?, locked_at = NOW() WHERE id = ?")
                ->execute([$newAttempts, $user['id']]);
            $error = 'A fiókja zárolva lett ismételt hibás bejelentkezési kísérlet miatt. Kérje az adminisztrátor segítségét.';
        } else {
            $pdo->prepare("UPDATE users SET login_attempts = ? WHERE id = ?")
                ->execute([$newAttempts, $user['id']]);
            $remaining = 3 - $newAttempts;
            $error = 'Érvénytelen jelszó. Még ' . $remaining . ' kísérlet a fiók zárolása előtt.';
        }
        return ['ok' => false, 'error' => $error, 'user' => null];
    }

    // Successful login — reset counter, set session
    $pdo->prepare("UPDATE users SET login_attempts = 0, locked_at = NULL WHERE id = ?")
        ->execute([$user['id']]);
    $writeLog('success', (int)$user['id'], $user['lastname'] . ' ' . $user['firstname'], $user['username'], null);
    setUserSession($user);
    $_SESSION['user_avatar'] = $user['profile_picture'];

    return ['ok' => true, 'error' => '', 'user' => $user];
}
