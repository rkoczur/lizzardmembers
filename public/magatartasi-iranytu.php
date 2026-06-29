<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';

$pdo = getDb();
ensurePublicSchema($pdo);

$stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = 'magatartasi-iranytu' LIMIT 1");
$stmt->execute();
$page = $stmt->fetch();

$pageTitle       = 'Magatartási iránytű';
$activePubPage   = 'magatartasi-iranytu';
$metaDescription = $page['meta_description'] ?? '';
$metaKeywords    = $page['meta_keywords'] ?? '';
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap-narrow">
  <div class="pub-page-header">
    <h1>Magatartási iránytű</h1>
  </div>

  <?php if (!empty($page['body'])): ?>
    <div class="pub-prose"><?= $page['body'] ?></div>
  <?php else: ?>
    <div class="pub-info-box">
      Ez az oldal még nincs kitöltve. Az admin felületen szerkeszthető: <strong>Weboldal → Lapok → Magatartási iránytű</strong>.
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
