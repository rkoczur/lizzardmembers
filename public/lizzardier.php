<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';

$pdo = getDb();
ensurePublicSchema($pdo);

$stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = 'lizzardier' LIMIT 1");
$stmt->execute();
$page = $stmt->fetch();

$pageTitle     = $page['title'] ?? 'Lizzardier pontverseny';
$activePubPage = 'lizzardier';
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap-narrow">
  <div class="pub-page-header">
    <h1><?= e($page['title'] ?? 'Lizzardier pontverseny') ?></h1>
    <p>A játék, ahol a túrákon szerzett pontok rangfokozatokat és jutalmakat érnek.</p>
  </div>

  <?php if (!empty($page['body'])): ?>
    <div class="pub-prose"><?= $page['body'] ?></div>
  <?php else: ?>
  <div class="pub-prose">
    <h2>Mi az a Lizzardier?</h2>
    <p>A Lizzardier egy pontgyűjtő verseny, amelyen az egyesület által szervezett túrákon résztvevők pontokat kapnak. Az összesített pontszámok katonai stílusú rangfokozatokat érnek, amelyekhez egyedi felvarrók és kedvezmények járnak.</p>

    <h2>Hogyan lehet pontokat szerezni?</h2>
    <p>A pontok értéke a túra nehézségétől, hosszától és időtartamától függ:</p>
    <ul>
      <li><strong>1 pont</strong> – Egynapos hazai túra, max. 20 km és/vagy 1 000 m szintemelkedés</li>
      <li><strong>2 pont</strong> – Egynapos hazai túra, 20 km vagy 1 000 m felett</li>
      <li><strong>3 pont</strong> – Egynapos külföldi vagy hazai kalandtúra</li>
      <li><strong>4 pont</strong> – Kalandtúra 20+ km és/vagy 1 000+ m szinttel</li>
      <li><strong>5–15+ pont</strong> – Többnapos túrák, nehézségtől és napoktól függően</li>
    </ul>
    <p>A <strong>túraszervező/vezető</strong> az alappont + 1 pont/nap + 1 pont/éjszaka bónuszt kap.</p>

    <h2>Kedvezmények</h2>
    <p>A magasabb rangfokozatok részvételi díj kedvezményt biztosítanak:</p>
    <ul>
      <li>Hadnagy–Százados (5–6): <strong>5%</strong> kedvezmény</li>
      <li>Őrnagy–Alezredes (7–8): <strong>10%</strong> kedvezmény</li>
      <li>Ezredes (9): <strong>15%</strong> kedvezmény</li>
    </ul>
  </div>
  <?php endif; ?>

  <!-- Always show rank table -->
  <h2 class="pub-section-title" style="margin-top:36px;">Rangfokozatok és ponthatárok</h2>
  <table class="pub-rank-table">
    <thead>
      <tr>
        <th></th>
        <th>Rang</th>
        <th style="text-align:right;">Szükséges pont</th>
        <th style="text-align:right;">Díjkedvezmény</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $ranks = [
        [9, 500, '15%'], [8, 330, '10%'], [7, 250, '10%'], [6, 170, '5%'],
        [5, 100, '5%'],  [4, 50, '0%'],   [3, 25, '0%'],   [2, 3, '0%'], [1, 0, '0%'],
      ];
      foreach ($ranks as [$lvl, $pts, $disc]):
        $img = getLevelImageFilename($lvl);
      ?>
      <tr>
        <td style="width:60px;">
          <?php if ($img): ?><img src="<?= BASE_URL ?>/assets/img/<?= $img ?>" class="pub-rank-img" alt=""><?php endif; ?>
        </td>
        <td><span class="level-badge <?= getLevelClass($lvl) ?>"><?= getLevelLabel($lvl) ?></span></td>
        <td style="text-align:right;"><?= $pts > 0 ? $pts . ' pont' : 'Újonc' ?></td>
        <td><?= $disc ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div style="margin-top:32px;text-align:center;">
    <a href="<?= BASE_URL ?>/public/toplista.php" class="btn btn-primary" style="padding:13px 32px;font-size:15px;">
      Aktuális Toplista →
    </a>
  </div>
</div>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
