<?php
/**
 * Bejelentkezés nélküli leiratkozás a túraértesítőkről (e-mail linkről).
 * A linket HMAC-token védi. A tényleges leiratkozás POST-ra történik
 * (megerősítő gomb), hogy a levelezőkliensek link-előtöltése ne iratkoztasson le véletlenül.
 */
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/app-settings-schema.php';
require_once __DIR__ . '/includes/user-schema.php';

$pdo = getDb();
ensureAppSettingsSchema($pdo);
ensureUserSchema($pdo);

$uid   = (int)($_GET['uid'] ?? $_POST['uid'] ?? 0);
$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$valid = verifyUnsubscribeToken($pdo, $uid, $token);

$done = false;
if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("SELECT notification_prefs FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$uid]);
    $prefs = json_decode(((string)$stmt->fetchColumn()) ?: '{}', true) ?: [];
    $prefs['tour_announcement'] = 0;
    $pdo->prepare("UPDATE users SET notification_prefs = ? WHERE id = ?")
        ->execute([json_encode($prefs, JSON_UNESCAPED_UNICODE), $uid]);
    $done = true;
}

$pageTitle     = 'Leiratkozás';
$activePubPage = '';
include __DIR__ . '/includes/public-header.php';
?>

<div class="pub-wrap-narrow">
  <div class="pub-page-header">
    <h1>Túraértesítők</h1>
  </div>

  <?php if (!$valid): ?>
    <div class="pub-info-box" style="border-left-color:var(--danger);max-width:600px;">
      <p style="margin:0;font-size:15px;line-height:1.6;">A leiratkozási link érvénytelen vagy hiányos. Ha le szeretnél iratkozni, lépj be a fiókodba, és a <strong>Saját profilom → E-mail értesítések</strong> alatt kapcsold ki az „Új meghirdetett túrák" értesítést.</p>
      <p style="margin:14px 0 0;"><a class="btn btn-primary" href="<?= BASE_URL ?>/login.php">Bejelentkezés</a></p>
    </div>

  <?php elseif ($done): ?>
    <div class="pub-info-box" style="max-width:600px;">
      <h2 style="margin:0 0 8px;font-size:18px;color:var(--primary);">✅ Sikeresen leiratkoztál</h2>
      <p style="margin:0;font-size:15px;line-height:1.6;">Mostantól nem küldünk e-mailt az új meghirdetett túrákról. A többi e-mail értesítést ez nem érinti.</p>
      <p style="margin:12px 0 0;font-size:13px;color:var(--text-muted);line-height:1.6;">Meggondoltad magad? Bejelentkezés után a <strong>Saját profilom → E-mail értesítések</strong> alatt bármikor újra bekapcsolhatod.</p>
      <p style="margin:14px 0 0;"><a class="btn btn-secondary" href="<?= BASE_URL ?>/login.php">Bejelentkezés</a></p>
    </div>

  <?php else: ?>
    <div class="pub-info-box" style="max-width:600px;">
      <h2 style="margin:0 0 8px;font-size:18px;">Leiratkozás a túraértesítőkről</h2>
      <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">Biztosan le szeretnél iratkozni az <strong>új meghirdetett túrák</strong> e-mailes értesítőiről? A többi értesítést (pl. túra-hozzárendelés) ez nem érinti.</p>
      <form method="post" action="<?= BASE_URL ?>/unsubscribe.php">
        <input type="hidden" name="uid" value="<?= (int)$uid ?>">
        <input type="hidden" name="t" value="<?= e($token) ?>">
        <button type="submit" class="btn btn-primary">Leiratkozom</button>
      </form>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/public-footer.php'; ?>
