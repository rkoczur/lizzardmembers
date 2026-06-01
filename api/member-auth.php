<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/login-log-schema.php';
require_once __DIR__ . '/../includes/ip-block-schema.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// OPTIONS preflight (same-site fetch with credentials)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$action = $_GET['action'] ?? ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'login' : 'status');

// ---- Logout ----
if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- Status check ----
if ($action === 'status') {
    if (isLoggedIn()) {
        $pdo  = getDb();
        $stmt = $pdo->prepare("SELECT firstname, lastname, email, level FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([getCurrentUserId()]);
        $u        = $stmt->fetch();
        $level    = (int)($u['level'] ?? 1);
        $discount = getTourFeeDiscount($level);
        echo json_encode([
            'logged_in' => true,
            'firstname' => $u['firstname'] ?? '',
            'lastname'  => $u['lastname']  ?? '',
            'email'     => $u['email']     ?? '',
            'level'     => $level,
            'discount'  => $discount,
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['logged_in' => false]);
    }
    exit;
}

// ---- Login ----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Érvénytelen kérés.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$login    = trim($_POST['login']    ?? '');
$password =      $_POST['password'] ?? '';

if ($login === '' || $password === '') {
    echo json_encode(['error' => 'Kérjük töltsd ki az összes mezőt.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = getDb();
    ensureLoginLogSchema($pdo);
    ensureIpBlockSchema($pdo);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Szerver hiba. Kérjük próbáld újra.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ip        = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
$writeLog  = function (string $status, ?int $uid, string $name, string $uname, ?string $reason) use ($pdo, $ip, $userAgent): void {
    $pdo->prepare("INSERT INTO login_log (user_id, name, username, ip, user_agent, status, fail_reason) VALUES (?,?,?,?,?,?,?)")
        ->execute([$uid ?: null, $name, $uname, $ip, $userAgent, $status, $reason]);
};

// 1. Check if IP is blocked
$ipRow = $pdo->prepare("SELECT attempts, blocked FROM ip_blocks WHERE ip = ?");
$ipRow->execute([$ip]);
$ipBlock = $ipRow->fetch();

if ($ipBlock && $ipBlock['blocked']) {
    $writeLog('failed', null, '', $login, 'ip_blocked');
    echo json_encode(['error' => 'Az Ön IP-címe zárolva lett ismételt sikertelen bejelentkezési kísérlet miatt. Kérje az adminisztrátor segítségét.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Fetch user (active accounts only)
$stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND active = 1 LIMIT 1");
$stmt->execute([$login, $login]);
$user = $stmt->fetch();

if (!$user) {
    $writeLog('failed', null, '', $login, 'unknown_user');
    // Increment IP attempt counter
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
    if ($newIpAttempts >= 3) {
        echo json_encode(['error' => 'Az Ön IP-címe zárolva lett ismételt sikertelen bejelentkezési kísérlet miatt. Kérje az adminisztrátor segítségét.'], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['error' => 'Hibás felhasználónév/e-mail cím vagy jelszó. (' . $newIpAttempts . '/3 IP-kísérlet)'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 3. Check if account is locked by failed attempts
if (!empty($user['locked_at'])) {
    $writeLog('failed', (int)$user['id'], $user['lastname'] . ' ' . $user['firstname'], $user['username'], 'account_locked');
    echo json_encode(['error' => 'A fiókja zárolva lett ismételt hibás bejelentkezési kísérlet miatt. Kérje az adminisztrátor segítségét.'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. Verify password
if (!password_verify($password, $user['password'])) {
    $writeLog('failed', (int)$user['id'], $user['lastname'] . ' ' . $user['firstname'], $user['username'], 'wrong_password');
    $newAttempts = (int)($user['login_attempts'] ?? 0) + 1;
    if ($newAttempts >= 3) {
        $pdo->prepare("UPDATE users SET login_attempts = ?, locked_at = NOW() WHERE id = ?")
            ->execute([$newAttempts, $user['id']]);
        echo json_encode(['error' => 'A fiókja zárolva lett ismételt hibás bejelentkezési kísérlet miatt. Kérje az adminisztrátor segítségét.'], JSON_UNESCAPED_UNICODE);
    } else {
        $pdo->prepare("UPDATE users SET login_attempts = ? WHERE id = ?")
            ->execute([$newAttempts, $user['id']]);
        $remaining = 3 - $newAttempts;
        echo json_encode(['error' => 'Hibás jelszó. Még ' . $remaining . ' kísérlet a fiók zárolása előtt.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 5. Successful login — reset counters
$pdo->prepare("UPDATE users SET login_attempts = 0, locked_at = NULL WHERE id = ?")
    ->execute([$user['id']]);
$writeLog('success', (int)$user['id'], $user['lastname'] . ' ' . $user['firstname'], $user['username'], null);
setUserSession($user);

$discount = getTourFeeDiscount((int)($user['level'] ?? 1));
echo json_encode([
    'success'   => true,
    'firstname' => $user['firstname'],
    'lastname'  => $user['lastname'],
    'email'     => $user['email'],
    'level'     => (int)($user['level'] ?? 1),
    'discount'  => $discount,
], JSON_UNESCAPED_UNICODE);
