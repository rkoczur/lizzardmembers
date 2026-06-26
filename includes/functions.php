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

function getLevelImageUrl(int $level): string
{
    $file = getLevelImageFilename($level);
    return $file ? BASE_URL . '/assets/img/' . $file : BASE_URL . '/assets/img/default-avatar.svg';
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

function getTourFeeDiscount(int $level, string $role = 'user'): int
{
    static $leaderRoles = ['admin', 'helyettes', 'penzugyi', 'jogi', 'kommunikacios', 'vezeto'];
    if (in_array($role, $leaderRoles, true)) return 15;
    return match(true) {
        $level >= 9 => 15,
        $level >= 7 => 10,
        $level >= 5 => 5,
        default     => 0,
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

/**
 * Az utolsó tagdíj fizetés dátumának származtatása a tranzakciós naplóból.
 * Minden taghoz a legutóbbi „Tagdíj” kategóriájú befizetés (income) dátumát veszi,
 * ahol a tranzakció partnere a tag teljes neve (vezetéknév + keresztnév).
 * Csak akkor fut, ha már van tagdíj-tranzakció, hogy ne törölje a meglévő adatokat.
 */
function recalcMembershipPayments(PDO $pdo): void
{
    try {
        $has = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE tx_type = 'income' AND category = 'Tagdíj'")->fetchColumn();
    } catch (Throwable) {
        return; // a transactions tábla még nem létezik
    }
    if ($has === 0) return; // nincs tagdíj-tranzakció — ne írjuk felül a meglévő értékeket

    $pdo->exec("
        UPDATE users u
        LEFT JOIN (
            SELECT partner, MAX(tx_date) AS last_pay
            FROM transactions
            WHERE tx_type = 'income' AND category = 'Tagdíj'
            GROUP BY partner
        ) t ON t.partner = TRIM(CONCAT(COALESCE(u.lastname,''), ' ', COALESCE(u.firstname,''))) COLLATE utf8mb4_unicode_ci
        SET u.last_payment = t.last_pay
    ");
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

/**
 * Kiszámítja egy túra MTSZ pontértékét a 2018-as Természetjáró Minősítési Szabályzat alapján.
 *
 * Km/szint kettős mező logika (gyalogos, kerékpáros):
 *   total_km/total_elevation  = nem magashegyi szakasz (normál ráta)
 *   alpine_km/alpine_elevation = magashegyi szakasz (normál ráta × 2)
 *
 * Sátorozás pluszpont (állótábor/mozgótábor) csak ha accommodation = 'sator'.
 *
 * Téli hónap: ha van magashegyi szakasz, XI és III is beleszámít.
 *
 * Bonus-szorzó ha vegyes: ha az magashegyi alappont ≥ normál alappont → bónusz is ×2.
 */
function calculateTourPoints(array $t): int
{
    $type       = $t['tour_type']    ?? 'gyalogos';
    $sub        = $t['sub_type']     ?? 'normal';
    $days       = max(1, (int)($t['days'] ?? 1));
    $date       = $t['tour_date']    ?? null;
    $accom      = $t['accommodation'] ?? '';
    $multiDay   = $t['multi_day_type'] ?? null;
    $portages   = (int)($t['boat_portages'] ?? 0);

    // Nem magashegyi km/szint
    $normalKm   = ($t['total_km']        !== null && $t['total_km']        !== '') ? (float)$t['total_km']        : null;
    $normalElev = ($t['total_elevation'] !== null && $t['total_elevation'] !== '') ? (int)$t['total_elevation']   : null;
    // Magashegyi km/szint
    $alpineKm   = ($t['alpine_km']        !== null && $t['alpine_km']        !== '') ? (float)$t['alpine_km']        : null;
    $alpineElev = ($t['alpine_elevation'] !== null && $t['alpine_elevation'] !== '') ? (int)$t['alpine_elevation']   : null;
    // Időalapú
    $hours      = ($t['tour_hours']  !== null && $t['tour_hours']  !== '') ? (float)$t['tour_hours'] : null;
    $campNights = (int)($t['camping_nights_fixed'] ?? 0);

    $hasAlpine = ($alpineKm !== null && $alpineKm > 0) || ($alpineElev !== null && $alpineElev > 0);

    // --- Alappont-számítás (nem magashegyi és magashegyi szakasz külön) ---
    $normalBase = 0.0;
    $alpineBase = 0.0; // magashegyi szakasz normál rátán (majd ×2)

    switch ($type) {
        case 'gyalogos':
            $kmRate   = ($sub === 'tajekozodasi') ? 3.0 : 1.5;
            $elevRate = 2.0;
            $normalBase += ($normalKm ?? 0) * $kmRate + (($normalElev ?? 0) / 100) * $elevRate;
            $alpineBase += ($alpineKm ?? 0) * $kmRate + (($alpineElev ?? 0) / 100) * $elevRate;
            break;
        case 'kerekparos':
            $kmRate   = ($sub === 'terep') ? 1.0 : 0.5;
            $elevRate = ($sub === 'terep') ? 2.0 : 1.0;
            $normalBase += ($normalKm ?? 0) * $kmRate + (($normalElev ?? 0) / 100) * $elevRate;
            $alpineBase += ($alpineKm ?? 0) * $kmRate + (($alpineElev ?? 0) / 100) * $elevRate;
            break;
        case 'vizi':
            $rate = match($sub) { 'szemben' => 2.0, 'allovi' => 1.5, default => 1.0 };
            $normalBase += ($normalKm ?? 0) * $rate;
            break;
        case 'si':
            $normalBase += ($hours ?? 0) * 6;
            break;
        case 'barlangi':
            $normalBase += ($hours ?? 0) * ($sub === 'kiepitetlen' ? 10.0 : 4.0);
            break;
        case 'munka':
            $normalBase += ($hours ?? 0) * 7;
            break;
    }

    // --- Pluszpontok ---
    $bonus = 0.0;

    // Síkvidéki: csak ha nincs magashegyi szakasz ÉS normalElev ≤ 100 m
    if ($type === 'gyalogos' && !$hasAlpine && $normalElev !== null && $normalElev <= 100) {
        $bonus += $days * 3;
    }

    // Téli (gyalogos, kerékpáros): XII–I–II; ha van magashegyi: XI és III is
    if (in_array($type, ['gyalogos', 'kerekparos'], true) && $date) {
        $month  = (int)date('n', strtotime($date));
        $winter = $hasAlpine ? [11, 12, 1, 2, 3] : [12, 1, 2];
        if (in_array($month, $winter, true)) {
            $bonus += $days * 3;
        }
    }

    // Többnapos: eltöltött éjszakák alapján; sátras szállásnál a tábor pontjai duplázódnak
    // (pl. 2 éj sátor vándortábor: 2 × 3 × 2 = 12 pont)
    $campMult = ($accom === 'sator') ? 2 : 1;
    if ($multiDay === 'csillag')    $bonus += $campNights * 1 * $campMult;
    elseif ($multiDay === 'vandor') $bonus += $campNights * 3 * $campMult;

    // Hajóátemelés
    if ($type === 'vizi') $bonus += $portages * 3;

    // Bonus-szorzó: ha a magashegyi alappont domináns → bónusz is ×2
    $bonusMult = ($hasAlpine && in_array($type, ['gyalogos', 'kerekparos'], true) && $alpineBase >= $normalBase) ? 2 : 1;

    return (int)round($normalBase + $alpineBase * 2 + $bonus * $bonusMult);
}

function getTourTypeAbbrev(string $type): string
{
    return match($type) {
        'gyalogos'   => 'GY',
        'kerekparos' => 'K',
        'vizi'       => 'V',
        'si'         => 'S',
        'barlangi'   => 'B',
        'munka'      => 'M',
        default      => strtoupper(mb_substr($type, 0, 1, 'UTF-8')),
    };
}

function generateTourCode(PDO $pdo, string $tourType): string
{
    $codes = $pdo->query("SELECT tour_code FROM tours WHERE tour_code IS NOT NULL")
                 ->fetchAll(PDO::FETCH_COLUMN);
    $max = 0;
    foreach ($codes as $code) {
        $num = (int)preg_replace('/[^0-9]/', '', $code);
        if ($num > $max) $max = $num;
    }
    return ($max + 1) . getTourTypeAbbrev($tourType);
}

function getTourTypeLabel(string $type): string
{
    return match($type) {
        'gyalogos'   => 'Gyalogos',
        'kerekparos' => 'Kerékpáros',
        'vizi'       => 'Vízitúra',
        'si'         => 'Síelés',
        'barlangi'   => 'Barlangi',
        'munka'      => 'Munkatúra',
        default      => $type,
    };
}

function getCountries(PDO $pdo, bool $activeOnly = true): array
{
    $sql = "SELECT * FROM countries" . ($activeOnly ? " WHERE active = 1" : "") . " ORDER BY sort_order ASC, name_hu ASC";
    return $pdo->query($sql)->fetchAll();
}

function getCountryByCode(PDO $pdo, string $code): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM countries WHERE code = ? LIMIT 1");
    $stmt->execute([strtoupper($code)]);
    return $stmt->fetch() ?: null;
}

function getFlagUrl(?string $filename): string
{
    if (!$filename) return '';
    return FLAG_URL . $filename;
}

/**
 * Elküldi a választ a kliensnek és lezárja a kapcsolatot, majd a háttérben folytatja a futást
 * (pl. lassú SMTP e-mail-küldés). Így a kliens (fetch) nem fut hálózati hibába / időtúllépésbe,
 * miközben a háttérmunka még zajlik. FPM/LiteSpeed alatt finish_request, mod_php alatt
 * Content-Length + kapcsolat lezárása.
 */
function respondAndContinue(string $body, string $contentType = 'application/json; charset=utf-8'): void
{
    ignore_user_abort(true);
    if (!headers_sent()) {
        header('Content-Type: ' . $contentType);
    }
    if (function_exists('fastcgi_finish_request')) {
        echo $body;
        fastcgi_finish_request();
        return;
    }
    if (function_exists('litespeed_finish_request')) {
        echo $body;
        litespeed_finish_request();
        return;
    }
    // Fallback (Apache mod_php): tartalomhossz + kapcsolat lezárása, majd a pufferek ürítése
    if (!headers_sent() && !ini_get('zlib.output_compression')) {
        header('Connection: close');
        header('Content-Length: ' . strlen($body));
    }
    echo $body;
    while (ob_get_level() > 0) { @ob_end_flush(); }
    @flush();
}

function generateMemberPassword(): string
{
    $words = ['turist','hegyes','erdos','szikla','kaland','termek','kavics',
              'virago','lombok','napfeny','vihart','szeles','ligetes','patak',
              'vadon','mezos','dombos','berkes','cserje','csapat'];
    $word  = $words[array_rand($words)];
    $digits = str_pad((string)random_int(10, 99), 2, '0', STR_PAD_LEFT);
    $upper  = chr(random_int(65, 90));
    return ucfirst($word) . $digits . $upper;
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
