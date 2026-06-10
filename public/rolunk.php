<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';
require_once __DIR__ . '/../includes/user-schema.php';

$pdo = getDb();
ensurePublicSchema($pdo);
ensureUserSchema($pdo);

$stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = 'rolunk' LIMIT 1");
$stmt->execute();
$page = $stmt->fetch();

$leaders = $pdo->query("
    SELECT id, lastname, firstname, role, email, profile_picture, consent_email_visibility
    FROM users
    WHERE role IN ('admin','helyettes','penzugyi','jogi','kommunikacios','vezeto')
      AND active = 1
      AND COALESCE(is_candidate, 0) = 0
    ORDER BY FIELD(role,'admin','helyettes','penzugyi','jogi','kommunikacios','vezeto'), lastname ASC
")->fetchAll();

$pageTitle    = $page['title'] ?? 'Rólunk';
$activePubPage = 'rolunk';
$metaDescription = $page['meta_description'] ?? '';
$metaKeywords    = $page['meta_keywords'] ?? '';
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap-narrow">
  <div class="pub-page-header">
    <h1><?= e($page['title'] ?? 'Rólunk') ?></h1>
    <p>Ismerj meg minket!</p>
  </div>

  <?php if (!empty($page['body'])): ?>
    <div class="pub-prose"><?= $page['body'] ?></div>
  <?php else: ?>
  <?php endif; ?>

  <?php if (!empty($leaders)): ?>
  <div style="margin-top:40px;">
    <h2 class="pub-section-title">Vezetőség</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:20px;margin-top:24px;">
      <?php foreach ($leaders as $l): ?>
      <div class="card" style="display:flex;flex-direction:column;align-items:center;padding:28px 20px;text-align:center;gap:10px;">
        <img src="<?= e(getAvatarUrl($l['profile_picture'])) ?>"
             style="width:88px;height:88px;border-radius:50%;object-fit:cover;border:3px solid var(--primary-light);"
             alt="<?= e($l['lastname'] . ' ' . $l['firstname']) ?>">
        <div style="font-size:16px;font-weight:700;color:var(--text);"><?= e($l['lastname'] . ' ' . $l['firstname']) ?></div>
        <div style="font-size:13px;font-weight:600;color:var(--primary);"><?= e(getRoleLabel($l['role'])) ?></div>
        <?php if (!empty($l['email'])): ?>
          <a href="mailto:<?= e($l['email']) ?>" style="font-size:12.5px;color:var(--text-muted);"><?= e($l['email']) ?></a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (empty($page['body'])): ?>
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
