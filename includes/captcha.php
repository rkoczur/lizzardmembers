<?php
/**
 * Google reCAPTCHA v2 ("Nem vagyok robot") integráció a nyilvános űrlapokhoz.
 *
 * A kulcsok a `settings` táblában vannak (admin → Beállítások):
 *   - recaptcha_site_key  (publikus, a widgethez)
 *   - recaptcha_secret    (titkos, a szerveroldali ellenőrzéshez)
 *
 * Ha bármelyik kulcs hiányzik, a reCAPTCHA ki van kapcsolva: a widget nem
 * jelenik meg, és a szerveroldali ellenőrzés nem blokkol semmit.
 *
 * Használat:
 *   - Az űrlapba (a küldés gomb fölé):   <?= recaptchaField($pdo) ?>
 *   - Az oldal aljára EGYSZER:            <?= recaptchaScript($pdo) ?>
 *   - A POST-kezelőben:
 *       if (recaptchaEnabled($pdo) && !verifyRecaptcha($pdo, $_POST['g-recaptcha-response'] ?? '')) { ... }
 */
require_once __DIR__ . '/app-settings-schema.php';

function getRecaptchaConfig(PDO $pdo): array
{
    ensureAppSettingsSchema($pdo); // idempotens — biztosítja, hogy a settings tábla létezik
    return [
        'site_key' => getSetting($pdo, 'recaptcha_site_key', ''),
        'secret'   => getSetting($pdo, 'recaptcha_secret',   ''),
    ];
}

function recaptchaEnabled(PDO $pdo): bool
{
    $c = getRecaptchaConfig($pdo);
    return $c['site_key'] !== '' && $c['secret'] !== '';
}

/** A widget helye az űrlapban (üres string, ha nincs konfigurálva). */
function recaptchaField(PDO $pdo): string
{
    $c = getRecaptchaConfig($pdo);
    if ($c['site_key'] === '' || $c['secret'] === '') return '';
    $key = htmlspecialchars($c['site_key'], ENT_QUOTES, 'UTF-8');
    return '<div class="g-recaptcha" data-sitekey="' . $key . '"></div>';
}

/** A reCAPTCHA API szkript — oldalanként EGYSZER kell beilleszteni. */
function recaptchaScript(PDO $pdo): string
{
    if (!recaptchaEnabled($pdo)) return '';
    return '<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
}

/**
 * Szerveroldali token-ellenőrzés a Google felé.
 * Visszatérés: true = ember / érvényes, false = sikertelen vagy nem ellenőrizhető.
 * (Fail-closed: ha a Google nem elérhető, false-t adunk — barátságos hibaüzenettel.)
 */
function verifyRecaptcha(PDO $pdo, string $token, ?string $ip = null): bool
{
    $c = getRecaptchaConfig($pdo);
    if ($c['secret'] === '') return true; // nincs konfigurálva → ne blokkoljon
    if (trim($token) === '') return false;

    $payload = http_build_query([
        'secret'   => $c['secret'],
        'response' => $token,
        'remoteip' => $ip ?? ($_SERVER['REMOTE_ADDR'] ?? ''),
    ]);

    $raw = recaptchaHttpPost('https://www.google.com/recaptcha/api/siteverify', $payload);
    if ($raw === null) return false;

    $data = json_decode($raw, true);
    return is_array($data) && !empty($data['success']);
}

/** HTTP POST cURL-lel, ha elérhető, különben stream-context fallback. NULL hibánál. */
function recaptchaHttpPost(string $url, string $body): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $out  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($out !== false && $code === 200) ? $out : null;
    }

    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => 'Content-Type: application/x-www-form-urlencoded',
        'content'       => $body,
        'timeout'       => 5,
        'ignore_errors' => true,
    ]]);
    $out = @file_get_contents($url, false, $ctx);
    return $out === false ? null : $out;
}
