<?php
/**
 * Önálló telekocsi-szervező: konfiguráció, adatbázis, közös segédfüggvények.
 * Az alaprendszerből semmit nem használ; a DB-adatokat a saját config.ini-ből,
 * annak hiányában a szülőmappa config.ini [database] szekciójából olvassa.
 */

const TK_MAX_SEATS = 8;
const TK_CODE_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // 0/O, 1/I nélkül
const TK_CODE_LEN = 8;
const TK_COOKIE_DAYS = 180;

session_name('telekocsi');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

/** Beállítások: a saját config.ini szekciói felülírják a szülőmappa config.ini azonos szekcióit. */
function tkIni(): array {
    static $ini = null;
    if ($ini !== null) return $ini;
    $read = fn(string $f): array => is_file($f) ? (parse_ini_file($f, true) ?: []) : [];
    $ini  = array_replace($read(__DIR__ . '/../../config.ini'), $read(__DIR__ . '/../config.ini'));
    return $ini;
}

function tkConfig(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $db  = tkIni()['database'] ?? [];
    $cfg = [
        'host' => $db['host'] ?? 'localhost',
        'port' => $db['port'] ?? '3306',
        'user' => $db['user'] ?? 'root',
        'pass' => $db['password'] ?? '',
        'name' => $db['dbname'] ?? '',
    ];
    return $cfg;
}

function tkDb(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c   = tkConfig();
    $pdo = new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4", $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    tkEnsureSchema($pdo);
    return $pdo;
}

/** Az app URL-gyökere (pl. /lizzardmembers/telekocsi) — a szkript helyéből számolva. */
function tkBase(): string {
    return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
}

function tkBoardUrl(string $code, bool $absolute = false): string {
    $path = tkBase() . '/' . $code;
    if (!$absolute) return $path;
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $path;
}

function e(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/** Kód megjelenítése négyes csoportokban: ABCD-EFGH. */
function tkFormatCode(string $code): string {
    return substr($code, 0, 4) . '-' . substr($code, 4);
}

/** Beírt kód normalizálása (kötőjel, szóköz ki, nagybetű). Érvénytelen → ''. */
function tkNormalizeCode(string $raw): string {
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw));
    $re   = '/^[' . TK_CODE_CHARS . ']{' . TK_CODE_LEN . '}$/';
    return preg_match($re, $code) ? $code : '';
}

function tkCsrf(): string {
    if (empty($_SESSION['tk_csrf'])) $_SESSION['tk_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['tk_csrf'];
}

function tkVerifyCsrf(): void {
    $token = $_SESSION['tk_csrf'] ?? '';
    if ($token === '' || !hash_equals($token, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Lejárt az oldal. Töltsd újra, és próbáld meg ismét.');
    }
}

function tkFlash(string $type, string $msg): void {
    $_SESSION['tk_flash'] = ['type' => $type, 'msg' => $msg];
}

function tkTakeFlash(): ?array {
    $f = $_SESSION['tk_flash'] ?? null;
    unset($_SESSION['tk_flash']);
    return $f;
}

function tkRedirect(string $url): never {
    header('Location: ' . $url);
    exit;
}
