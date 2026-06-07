<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';

$pdo = getDb();
ensurePublicSchema($pdo);

$docs = $pdo->query("SELECT * FROM documents ORDER BY category ASC, sort_order ASC, id DESC")->fetchAll();
$byCategory = [];
foreach ($docs as $d) {
    $byCategory[$d['category']][] = $d;
}

$pageTitle     = 'Dokumentumtár';
$activePubPage = 'irattar';
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap-narrow">
  <div class="pub-page-header">
    <h1>Dokumentumtár</h1>
    <p>Az egyesület hivatalos dokumentumai letölthetők itt.</p>
  </div>

  <?php if (empty($byCategory)): ?>
    <div class="pub-empty-state">
      <div style="font-size:48px;margin-bottom:12px;">📁</div>
      <p>Hamarosan lesznek dokumentumok feltöltve.</p>
    </div>
  <?php else: ?>
    <?php foreach ($byCategory as $category => $items): ?>
    <div class="pub-doc-category">
      <h2><?= e($category) ?></h2>
      <div class="pub-doc-list">
        <?php foreach ($items as $d): ?>
        <a href="<?= BASE_URL ?>/assets/uploads/docs/<?= urlencode($d['filename']) ?>"
           target="_blank" rel="noopener" class="pub-doc-item">
          <svg class="pub-doc-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
            <line x1="16" y1="13" x2="8" y2="13"/>
            <line x1="16" y1="17" x2="8" y2="17"/>
          </svg>
          <span class="pub-doc-title"><?= e($d['title']) ?></span>
          <span class="pub-doc-dl">PDF ↓</span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
