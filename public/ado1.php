<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';

$pdo = getDb();
ensurePublicSchema($pdo);

$stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = 'ado1' LIMIT 1");
$stmt->execute();
$page = $stmt->fetch();

$pageTitle     = 'Adó 1%';
$activePubPage = 'ado1';
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap-narrow">
  <div class="pub-page-header">
    <h1>Adó 1%</h1>
    <p>Támogasd az egyesületet személyi jövedelemadód 1%-ával – Neked semmibe sem kerül!</p>
  </div>

  <div class="pub-info-box" style="font-size:16px;border-left-color:var(--warning);">
    <strong>Adószámunk: 18902622-1-41</strong><br>
    Leguán Osztag Természetjáró Egyesület
  </div>

  <?php if (!empty($page['body'])): ?>
    <div class="pub-prose"><?= $page['body'] ?></div>
  <?php else: ?>
    <div class="pub-prose">
      <h2>Miért felajánlásod az 1%-od?</h2>
      <p>Az egyesület közhasznú szervezet, így fogadhat személyi jövedelemadó 1%-os felajánlásokat. A felajánlás Neked semmibe sem kerül – az állam utalja át az összeget az általad megjelölt szervezetnek.</p>
      <h2>Hogyan ajánld fel?</h2>
      <ol>
        <li>Az eSZJA portálon (eszja.nav.gov.hu) jelentkezz be ügyfélkapus azonosítóval</li>
        <li>Keresd meg a „Civil/egyéb kedvezményezett" mezőt</li>
        <li>Add meg adószámunkat: <strong>18902622-1-41</strong></li>
        <li>Küldd el a nyilatkozatot</li>
      </ol>
      <p>Ha saját magad töltöd ki az adóbevallásodat, az EGYSZA nyomtatványon jelölheted meg az összeget.</p>
      <h2>Mire fordítjuk?</h2>
      <ul>
        <li>Tagdíj mérséklése</li>
        <li>Ingyenes programok szervezése</li>
        <li>Felszerelés vásárlása és karbantartása</li>
        <li>Buszköltség csökkentése</li>
        <li>Weboldal és adminisztrációs költségek</li>
      </ul>
      <p>Kérdés esetén írj nekünk: <a href="mailto:info@lizzard.hu">info@lizzard.hu</a></p>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
