<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';

$pdo = getDb();
ensurePublicSchema($pdo);

$stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = 'reszveteli-feltetelek' LIMIT 1");
$stmt->execute();
$page = $stmt->fetch();

$pageTitle     = 'Részvételi feltételek';
$activePubPage = 'reszveteli-feltetelek';
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap-narrow">
  <div class="pub-page-header">
    <h1>Részvételi feltételek</h1>
  </div>

  <?php if (!empty($page['body'])): ?>
    <div class="pub-prose"><?= $page['body'] ?></div>
  <?php else: ?>
    <div class="pub-info-box">
      Ez az oldal még nincs kitöltve. Az admin felületen szerkeszthető: <strong>Weboldal → Lapok → Részvételi feltételek</strong>.
    </div>
    <div class="pub-prose">
      <h2>Általános rendelkezések</h2>
      <p>A Lizzard Outdoor (Leguán Osztag Természetjáró Egyesület) nem utazási iroda, hanem önkéntes közösség. A résztvevők saját felelősségükre, saját költségükre vesznek részt a programokon.</p>
      <h2>Felelősség</h2>
      <p>Az egyesület semmilyen biztosítást nem nyújt, és nem vállal felelősséget sérülésekért, balesetekért vagy vagyoni károkért. Minden résztvevő saját megfelelő fizikai állapotáról, egészségéről és felszereléséről köteles gondoskodni.</p>
      <h2>Programváltoztatás</h2>
      <p>Az egyesület fenntartja a jogot a programok módosítására vagy lemondására időjárás, elégtelen létszám vagy előre nem látható körülmények esetén, visszatérítési kötelezettség nélkül.</p>
      <h2>Tagság</h2>
      <p>A programokon kizárólag az egyesület tagjai vehetnek részt, kivéve a kimondottan vendégeknek meghirdetett eseményeket. A tagság feltétele a belépési nyilatkozat kitöltése és az éves tagdíj befizetése.</p>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
