<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';

$pdo = getDb();
ensurePublicSchema($pdo);

$stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = 'rolunk' LIMIT 1");
$stmt->execute();
$page = $stmt->fetch();

$pageTitle    = $page['title'] ?? 'Rólunk';
$activePubPage = 'rolunk';
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap-narrow">
  <div class="pub-page-header">
    <h1><?= e($page['title'] ?? 'Rólunk') ?></h1>
    <p>Ismerd meg a Leguán Osztag Természetjáró Egyesületet!</p>
  </div>

  <?php if (!empty($page['body'])): ?>
    <div class="pub-prose"><?= $page['body'] ?></div>
  <?php else: ?>
    <div class="pub-info-box">
      Ez az oldal még nincs kitöltve. Az admin felületen szerkeszthető: <strong>Weboldal → Lapok → Rólunk</strong>.
    </div>
    <div class="pub-prose">
      <h2>Lizzard Outdoor – Leguán Osztag Természetjáró Egyesület</h2>
      <p>A Lizzard Outdoor (Leguán Osztag Természetjáró Egyesület – L.O.T.E.) egy 2017-ben bejegyzett természetjáró és outdoor közösség, amely fiataloknak és kevésbé fiataloknak nyújt lehetőséget a világ felfedezésére.</p>
      <p>Csapatunkat hegymászók, kerékpárosok, vízitúrázók és kirándulók alkotják, akiket a közös kalandok szeretete köt össze. Működésünk demokratikus elveken alapul: minden programon a részvevők egyenlők, és együtt döntünk a fontos kérdésekben.</p>
      <h2>Történetünk</h2>
      <ul>
        <li><strong>2008</strong> – Teknősbéka Túracsoport megalakulása</li>
        <li><strong>2011</strong> – Lizzard Outdoor névfelvétel</li>
        <li><strong>2014</strong> – Első külföldi túra (Rax-fennsík, Ausztria)</li>
        <li><strong>2017</strong> – Hivatalos egyesületi bejegyzés (Leguán Osztag T.E.)</li>
      </ul>
      <h2>Vezetőség</h2>
      <ul>
        <li>Koczur Richárd – Elnök</li>
        <li>Nacsa Nóra – Alelnök, Outdoor edző</li>
        <li>Kiskó Alíz – Pénzügyi referens</li>
        <li>Dr. Kerényi Edmond – Jogi referens</li>
        <li>Takács Olga – Kommunikációs felelős</li>
        <li>Máté Péter – Szövetségi referens</li>
      </ul>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
