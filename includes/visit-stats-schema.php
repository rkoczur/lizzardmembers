<?php
/**
 * Látogatottsági statisztika — séma és page-view követés.
 *
 * Csak a publikus oldalakat méri (a public-header.php hívja a trackPageView()-t).
 * Adatvédelem: IP-t NEM tárolunk; csak egy anonim first-party süti sózott hash-ét,
 * egy is_member flaget, az útvonalat és az időbélyeget.
 */

require_once __DIR__ . '/app-settings-schema.php';

function ensureVisitStatsSchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS `page_views` (
        `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `view_date`     DATE NOT NULL,
        `viewed_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `visitor_hash`  CHAR(64) NOT NULL,
        `is_member`     TINYINT(1) NOT NULL DEFAULT 0,
        `page_path`     VARCHAR(255) NOT NULL,
        KEY `idx_date` (`view_date`),
        KEY `idx_date_visitor` (`view_date`, `visitor_hash`),
        KEY `idx_path` (`page_path`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Egy publikus oldalmegtekintés naplózása. Soha nem dob kivételt a hívó felé:
 * az analitika nem törheti meg az oldalt.
 */
function trackPageView(): void
{
    try {
        // 0) GDPR: csak elfogadott süti-hozzájárulás esetén mérünk és állítunk lo_vid sütit
        if (($_COOKIE['lo_consent'] ?? '') !== '1') {
            return;
        }

        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // 1) Bot-szűrés
        if ($ua === '' || preg_match('/(bot|crawl|spider|slurp|facebookexternalhit|preview|monitor|curl|wget|python-requests|headless)/i', $ua)) {
            return;
        }

        $pdo = getDb();
        if (!$pdo) {
            return;
        }

        // 2) Anonim látogató-azonosító (first-party süti)
        $vid = $_COOKIE['lo_vid'] ?? '';
        if (!preg_match('/^[a-f0-9]{32}$/', $vid)) {
            $vid = bin2hex(random_bytes(16));
            if (!headers_sent()) {
                setcookie('lo_vid', $vid, [
                    'expires'  => time() + 60 * 60 * 24 * 400, // ~13 hónap
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Lax',
                    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                ]);
            }
        }

        // Sózott hash — az app_secret-tel; ha nem elérhető, APP_NAME a fallback salt
        try { $salt = getAppSecret($pdo); } catch (Throwable) { $salt = APP_NAME; }
        $visitorHash = hash('sha256', $vid . '|' . $salt);

        // 3) Útvonal (query nélkül, BASE_URL prefix levágva, max 255 karakter)
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if (BASE_URL !== '' && str_starts_with($path, BASE_URL)) {
            $path = substr($path, strlen(BASE_URL));
        }
        if ($path === '') { $path = '/'; }
        $path = substr($path, 0, 255);

        $isMember = getCurrentUserId() > 0 ? 1 : 0;

        // 4) Beszúrás — lusta sémakészítés, ha a tábla még nem létezik
        $sql = "INSERT INTO page_views (view_date, visitor_hash, is_member, page_path)
                VALUES (CURDATE(), ?, ?, ?)";
        try {
            $pdo->prepare($sql)->execute([$visitorHash, $isMember, $path]);
        } catch (PDOException) {
            static $schemaTried = false;
            if (!$schemaTried) {
                $schemaTried = true;
                ensureVisitStatsSchema($pdo);
                $pdo->prepare($sql)->execute([$visitorHash, $isMember, $path]);
            }
        }
    } catch (Throwable) {
        // némán elnyeljük — az analitika nem befolyásolhatja az oldal működését
    }
}
