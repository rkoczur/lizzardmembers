<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';

$pdo = getDb();
ensurePublicSchema($pdo);

$stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = 'klubelet' LIMIT 1");
$stmt->execute();
$page = $stmt->fetch();

$pageTitle     = 'Klubélet – események';
$activePubPage = 'klubelet';
$metaDescription = $page['meta_description'] ?? '';
$metaKeywords    = $page['meta_keywords'] ?? '';
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap-narrow">
  <div class="pub-page-header">
    <h1>Klubélet – események</h1>
    <p>Nem csak túrákon találkozunk – megismerjük egymást és jól érezzük magunkat!</p>
  </div>

  <?php if (!empty($page['body'])): ?>
    <div class="pub-prose"><?= $page['body'] ?></div>
  <?php else: ?>
    <div class="pub-prose">
      <p>Az egyesületi élet nem merül ki a szervezett túrákban. Rendszeresen szervezünk különféle közösségi programokat, amelyeken tagjaink kikapcsolódhatnak és közelebb kerülhetnek egymáshoz.</p>
      <h2>Közösségi csoportjaink</h2>
      <ul>
        <li><strong>Lizzard Klub</strong> – A főközösségi hub, ahol az egyesületi eseményeket, túrákat és élményeket osztjuk meg.</li>
        <li><strong>Lizzard Filmklub</strong> – Vetítések, mozizások, közös filmnézés.</li>
        <li><strong>Lizzard MászóKa-Land</strong> – Sziklamászás és falimászás barátainak.</li>
      </ul>
      <h2>Éves összejövetelek</h2>
      <p>Az év végi összejöveteleken közösen tekintjük át az elmúlt év emlékeit jó társaság, zene, étel és ital kíséretében.</p>
      <h2>Csatlakozz hozzánk Facebookon!</h2>
      <p>A nem nyilvános eseményeinket zárt Facebook-csoportban hirdetjük meg. Tagjaink ott értesülnek elsőként a különleges programokról.</p>
    </div>
    <div class="pub-social-list">
      <a href="https://www.facebook.com/lizzardoutdoor/" target="_blank" rel="noopener" class="pub-social-btn pub-social-fb">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
        Lizzard Facebook oldal
      </a>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
