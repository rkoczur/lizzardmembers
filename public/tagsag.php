<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';

$pdo = getDb();
ensurePublicSchema($pdo);

$stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = 'tagsag' LIMIT 1");
$stmt->execute();
$page = $stmt->fetch();

$pageTitle     = $page['title'] ?? 'Tagság';
$activePubPage = 'tagsag';
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap-narrow">
  <div class="pub-page-header">
    <h1><?= e($page['title'] ?? 'Tagság') ?></h1>
    <p>Tudj meg mindent a Lizzard Outdoor tagságáról!</p>
  </div>

  <?php if (!empty($page['body'])): ?>
    <div class="pub-prose"><?= $page['body'] ?></div>
  <?php else: ?>
    <div class="pub-prose">
      <h2>Tagság előnyei</h2>
      <ul>
        <li><strong>Lizzardier pontok</strong> – Pontokat gyűjthetsz, rangokat érhetsz el, és egyedi felvarrókat kaphatsz.</li>
        <li><strong>Év túratársa cím</strong> – A legtöbb pontot gyűjtő tag ingyenes szállást nyer egy általa választott túrán.</li>
        <li><strong>Csapatt-pólók</strong> – Évente 50+ pontot gyűjtők egyedi technikai pólót kapnak.</li>
        <li><strong>Felszerelés kölcsönzés</strong> – Tagok szabadon használhatják az egyesület felszereléseit.</li>
        <li><strong>Különleges programok</strong> – Kizárólag tagoknak szóló belföldi és külföldi túrák.</li>
        <li><strong>MTSZ tagság</strong> – Automatikus tagság a Magyar Természetjáró Szövetségben és a BTSSZ-ben.</li>
        <li><strong>Szavazati jog</strong> – Részvétel a közgyűléseken és a programtervezésben.</li>
      </ul>
      <h2>Éves tagdíj</h2>
      <p><strong>5 000 Ft / év.</strong> A tagdíj az adott naptári évre szól (december 31-ig érvényes), összege nem kerül arányosításra.</p>
      <p>Fizetési lehetőségek:</p>
      <ul>
        <li>Átutalás: <strong>16200120-18542675</strong> (Leguán Osztag T.E., MagNet Bank)</li>
        <li>Készpénz: előre egyeztetett találkozón</li>
      </ul>
      <h2>Hogyan lesz valaki tag?</h2>
      <ol>
        <li>Töltsd ki a <a href="<?= BASE_URL ?>/join.php">belépési kérelmet</a>.</li>
        <li>Utald el az éves tagdíjat.</li>
        <li>Az elnökség jóváhagyása után hivatalosan is tag vagy!</li>
      </ol>
    </div>
  <?php endif; ?>

  <!-- Rank table is always shown -->
  <h2 class="pub-section-title" style="margin-top:36px;">Rangfokozatok</h2>
  <table class="pub-rank-table">
    <thead>
      <tr>
        <th></th>
        <th>Rang</th>
        <th style="text-align:right;">Minimális pont</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $ranks = [
        [9, 500], [8, 330], [7, 250], [6, 170],
        [5, 100], [4, 50],  [3, 25],  [2, 3], [1, 0],
      ];
      foreach ($ranks as [$lvl, $pts]):
        $img = getLevelImageFilename($lvl);
      ?>
      <tr>
        <td style="width:60px;">
          <?php if ($img): ?><img src="<?= BASE_URL ?>/assets/img/<?= $img ?>" class="pub-rank-img" alt=""><?php endif; ?>
        </td>
        <td><span class="level-badge <?= getLevelClass($lvl) ?>"><?= getLevelLabel($lvl) ?></span></td>
        <td><?= $pts > 0 ? $pts . ' pont' : '—' ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div style="margin-top:32px;text-align:center;">
    <a href="<?= BASE_URL ?>/join.php" class="btn btn-primary" style="padding:13px 32px;font-size:15px;">
      Belépés az egyesületbe →
    </a>
  </div>
</div>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
