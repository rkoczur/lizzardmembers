<?php
/**
 * Telekocsi értesítő: aki feliratkozott (carpool_watchers), e-mailt kap,
 * ha új sofőr regisztrál, vagy egy autóban hely szabadul fel.
 * Egy e-mail megy ki, a feliratkozók BCC-ben. Az esemény okozója nem kap levelet.
 */

function ensureCarpoolWatchSchema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS carpool_watchers (
        id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        carpool_id     INT UNSIGNED NOT NULL,
        application_id INT UNSIGNED NOT NULL,
        created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_carpool_watch_app (application_id),
        FOREIGN KEY (carpool_id) REFERENCES carpools(id) ON DELETE CASCADE,
        FOREIGN KEY (application_id) REFERENCES future_tour_applications(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function isCarpoolWatching(PDO $pdo, int $appId): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM carpool_watchers WHERE application_id = ?");
    $stmt->execute([$appId]);
    return (bool)$stmt->fetchColumn();
}

function setCarpoolWatching(PDO $pdo, int $carpoolId, int $appId, bool $on): void {
    $sql = $on
        ? "INSERT IGNORE INTO carpool_watchers (carpool_id, application_id) VALUES (?, ?)"
        : "DELETE FROM carpool_watchers WHERE carpool_id = ? AND application_id = ?";
    $pdo->prepare($sql)->execute([$carpoolId, $appId]);
}

/** Feliratkozók e-mail címei (nem lemondott jelentkezések), az okozó kivételével. */
function getCarpoolWatcherEmails(PDO $pdo, int $carpoolId, int $exceptAppId): array {
    $stmt = $pdo->prepare("
        SELECT DISTINCT LOWER(TRIM(COALESCE(u.email, fta.guest_email))) AS email
        FROM carpool_watchers w
        JOIN future_tour_applications fta ON fta.id = w.application_id
        LEFT JOIN users u ON u.id = fta.user_id
        WHERE w.carpool_id = ? AND w.application_id <> ? AND fta.status != 'cancelled'
    ");
    $stmt->execute([$carpoolId, $exceptAppId]);
    return array_values(array_filter($stmt->fetchAll(PDO::FETCH_COLUMN),
        fn($m) => filter_var($m, FILTER_VALIDATE_EMAIL)));
}

/**
 * Értesítés kiküldése. $event: 'new_driver' | 'seat_freed'. $driverId: az érintett autó.
 * Hiba esetén csendben naplóz — a felhasználói művelet ne bukjon el miatta.
 */
function notifyCarpoolWatchers(PDO $pdo, array $carpool, string $event, int $driverId, int $actorAppId): void {
    try {
        $car = null;
        foreach (getCarpoolDrivers($pdo, (int)$carpool['id']) as $d) {
            if ((int)$d['id'] === $driverId) $car = $d;
        }
        if (!$car || $car['free'] < 1) return;
        $bcc = getCarpoolWatcherEmails($pdo, (int)$carpool['id'], $actorAppId);
        if (!$bcc) return;

        require_once __DIR__ . '/mailer.php';
        require_once __DIR__ . '/app-settings-schema.php';
        require_once __DIR__ . '/email-log-schema.php';
        ensureEmailLogSchema($pdo);

        $title   = $event === 'new_driver' ? 'Új sofőr' : 'Szabad hely';
        $subject = 'Telekocsi: ' . mb_strtolower($title) . ' – ' . $carpool['tour_name'];
        $html    = carpoolNotifyHtml($carpool, $car, $title);
        $smtp    = getSmtpConfig($pdo);
        $toEmail = $smtp['from_email'] ?: $smtp['user'];
        $logTo   = $toEmail . ' (BCC: ' . count($bcc) . ' címzett)';
        try {
            $resp = (new SmtpMailer($smtp))->send($toEmail, $smtp['from_name'], $subject, $html, '', '', $bcc);
            logEmailEntry($pdo, null, $logTo, $smtp['from_name'], $subject, $html, 'carpool_watch', 'sent', '', $resp);
        } catch (Throwable $ex) {
            logEmailEntry($pdo, null, $logTo, $smtp['from_name'], $subject, $html, 'carpool_watch', 'failed', $ex->getMessage());
        }
    } catch (Throwable $ex) {
        error_log('Carpool watch notify: ' . $ex->getMessage());
    }
}

function carpoolNotifyHtml(array $carpool, array $car, string $title): string {
    $h      = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $link   = $h(carpoolUrl($carpool));
    $intro  = $title === 'Új sofőr'
        ? 'Új sofőr kínál helyet az autójában a(z) <strong>' . $h($carpool['tour_name']) . '</strong> túrára.'
        : 'Felszabadult egy hely a(z) <strong>' . $h($carpool['tour_name']) . '</strong> túra egyik autójában.';
    $time   = $car['departure_time'] ? '<tr><td style="color:#7a7269;width:110px;">Indulás:</td><td><strong>' . $h($car['departure_time']) . '</strong></td></tr>' : '';
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f5efe4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px;">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
  <tr><td style="background:#1a3d39;padding:24px 32px;">
    <h1 style="color:#c8a84b;margin:0;font-size:22px;">' . $h(APP_NAME) . '</h1>
    <p style="color:#a8c5c2;margin:6px 0 0;font-size:14px;">Telekocsi – ' . $h($title) . '</p>
  </td></tr>
  <tr><td style="padding:28px 32px;">
    <p style="font-size:14px;color:#444;line-height:1.7;margin:0 0 16px;">Szia! ' . $intro . '</p>
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5efe4;border:1px solid #ddd5c5;border-radius:8px;margin:0 0 20px;"><tr><td style="padding:16px 20px;">
      <table width="100%" cellpadding="4" cellspacing="0" style="font-size:13.5px;color:#444;">
        <tr><td style="color:#7a7269;width:110px;">Sofőr:</td><td><strong>' . $h($car['name']) . '</strong></td></tr>
        <tr><td style="color:#7a7269;">Honnan:</td><td><strong>' . $h($car['departure']) . '</strong></td></tr>
        ' . $time . '
        <tr><td style="color:#7a7269;">Szabad hely:</td><td><strong>' . (int)$car['free'] . '</strong></td></tr>
      </table>
    </td></tr></table>
    <div style="text-align:center;margin:22px 0;">
      <a href="' . $link . '" style="background:#29776F;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:14px;font-weight:600;">Telekocsi-szervező megnyitása</a>
    </div>
    <p style="font-size:12.5px;color:#666;margin:0;">Az értesítőt a telekocsi-szervező oldalán kapcsolhatod ki.</p>
  </td></tr>
  <tr><td style="padding:16px 32px;background:#f5f5f5;text-align:center;font-size:12px;color:#999;">
    ' . $h(APP_NAME) . ' &bull; Automatikus értesítő
  </td></tr>
</table></td></tr></table></body></html>';
}
