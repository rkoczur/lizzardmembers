<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';

$pdo = getDb();
ensurePublicSchema($pdo);

$items = $pdo->query("
    SELECT f.*, c.name AS category_name
    FROM faq f
    LEFT JOIN faq_categories c ON c.id = f.category_id
    ORDER BY (f.category_id IS NULL) ASC, c.sort_order ASC, c.name ASC, f.sort_order ASC, f.id ASC
")->fetchAll();

// Csoportosított nézet csak akkor, ha van legalább egy kategóriába sorolt kérdés
$useGroups = false;
foreach ($items as $it) { if ($it['category_id'] !== null) { $useGroups = true; break; } }

$pageTitle     = 'GYIK – Gyakran ismételt kérdések';
$activePubPage = 'gyik';
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap-narrow">
  <div class="pub-page-header">
    <h1>Gyakran ismételt kérdések</h1>
    <p>Válaszok a leggyakoribb kérdésekre az egyesületről és a túrákról.</p>
  </div>

  <?php if (empty($items)): ?>
    <div class="pub-empty-state">
      <div style="font-size:48px;margin-bottom:12px;">❓</div>
      <p>Hamarosan lesznek kérdések feltöltve. Addig is írj nekünk: <a href="mailto:info@lizzard.hu">info@lizzard.hu</a></p>
    </div>
  <?php elseif (!$useGroups): ?>
    <div class="pub-faq-list">
      <?php foreach ($items as $item): ?>
      <details class="pub-faq-item">
        <summary><?= e($item['question']) ?></summary>
        <div class="pub-faq-answer"><?= nl2br(e($item['answer'])) ?></div>
      </details>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <?php $currentCat = false; ?>
    <?php foreach ($items as $item): ?>
      <?php
        $catKey  = $item['category_id'] !== null ? (int)$item['category_id'] : 0;
        $catName = $item['category_name'] ?? 'Egyéb';
      ?>
      <?php if ($catKey !== $currentCat): ?>
        <?php if ($currentCat !== false): ?></div><?php endif; ?>
        <div class="pub-faq-category">
          <div class="pub-faq-category-label">
            <svg class="pub-faq-category-icon" xmlns="http://www.w3.org/2000/svg" width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/>
            </svg>
            <span class="pub-faq-category-name"><?= e($catName) ?></span>
          </div>
          <span class="pub-faq-category-line"></span>
        </div>
        <div class="pub-faq-list">
        <?php $currentCat = $catKey; ?>
      <?php endif; ?>
      <details class="pub-faq-item">
        <summary><?= e($item['question']) ?></summary>
        <div class="pub-faq-answer"><?= nl2br(e($item['answer'])) ?></div>
      </details>
    <?php endforeach; ?>
    <?php if ($currentCat !== false): ?></div><?php endif; ?>
  <?php endif; ?>

  <div class="pub-info-box" style="margin-top:40px;">
    Nem találtad meg a válaszod? Írj nekünk: <a href="mailto:info@lizzard.hu">info@lizzard.hu</a>
  </div>
</div>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
