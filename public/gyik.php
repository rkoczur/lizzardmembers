<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';

$pdo = getDb();
ensurePublicSchema($pdo);

$items = $pdo->query("SELECT * FROM faq ORDER BY sort_order ASC, id ASC")->fetchAll();

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
    <div style="text-align:center;padding:60px 20px;color:var(--text-muted);">
      <div style="font-size:48px;margin-bottom:12px;">❓</div>
      <p>Hamarosan lesznek kérdések feltöltve. Addig is írj nekünk: <a href="mailto:info@lizzard.hu">info@lizzard.hu</a></p>
    </div>
  <?php else: ?>
    <div class="pub-faq-list">
      <?php foreach ($items as $item): ?>
      <details class="pub-faq-item">
        <summary><?= e($item['question']) ?></summary>
        <div class="pub-faq-answer"><?= nl2br(e($item['answer'])) ?></div>
      </details>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="pub-info-box" style="margin-top:40px;">
    Nem találtad meg a válaszod? Írj nekünk: <a href="mailto:info@lizzard.hu">info@lizzard.hu</a>
  </div>
</div>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
