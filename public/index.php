<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';

$pdo = getDb();
ensurePublicSchema($pdo);
ensureFutureToursSchema($pdo);

// Hero background image (stored in pages table with slug 'hero-image')
$heroImgStmt = $pdo->prepare("SELECT body FROM pages WHERE slug = 'hero-image' LIMIT 1");
$heroImgStmt->execute();
$heroImgFile = $heroImgStmt->fetchColumn() ?: null;

// Latest 3 news posts
$latestPosts = $pdo->query("SELECT * FROM posts WHERE published = 1 ORDER BY created_at DESC LIMIT 3")->fetchAll();


$HU_MONTHS = ['január','február','március','április','május','június','július','augusztus','szeptember','október','november','december'];
function huDateRange(string $startYmd, int $numDays, array $m): string {
    $sTs = strtotime($startYmd);
    $sY  = (int)date('Y', $sTs);
    $sMo = (int)date('n', $sTs);
    $sD  = (int)date('j', $sTs);
    if ($numDays <= 1) {
        return $sY . '. ' . $m[$sMo - 1] . ' ' . $sD . '.';
    }
    $eTs = strtotime($startYmd . ' +' . ($numDays - 1) . ' days');
    $eY  = (int)date('Y', $eTs);
    $eMo = (int)date('n', $eTs);
    $eD  = (int)date('j', $eTs);
    if ($sY === $eY && $sMo === $eMo) {
        return $sY . '. ' . $m[$sMo - 1] . ' ' . $sD . '–' . $eD . '.';
    } elseif ($sY === $eY) {
        return $sY . '. ' . $m[$sMo - 1] . ' ' . $sD . '. – ' . $m[$eMo - 1] . ' ' . $eD . '.';
    } else {
        return $sY . '. ' . $m[$sMo - 1] . ' ' . $sD . '. – ' . $eY . '. ' . $m[$eMo - 1] . ' ' . $eD . '.';
    }
}

$pageTitle     = 'Lizzard Outdoor';
$activePubPage = 'home';
include __DIR__ . '/../includes/public-header.php';
?>

<!-- Hero -->
<section class="pub-hero">
  <?php if ($heroImgFile): ?>
    <img src="<?= BASE_URL ?>/assets/uploads/hero/<?= e($heroImgFile) ?>" class="pub-hero-bg" alt="">
    <div class="pub-hero-overlay"></div>
  <?php endif; ?>
  <div class="pub-hero-content">
    <img src="<?= BASE_URL ?>/assets/img/logo_collision-text2_small.png" alt="Lizzard Outdoor" style="max-width:200px;width:55%;margin-bottom:12px;display:block;margin-left:auto;margin-right:auto;">
    <h1>#doyouevenhike</h1>
    <p>Természetbarát közösségi háló fiataloknak és kevésbé fiataloknak. Gyere velünk túrázni, kerékpározni, kajakozni!</p>
    <div class="pub-hero-actions">
      <a href="<?= BASE_URL ?>/public/turanyptar.php" class="btn-hero-primary">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Túranaptár
      </a>
      <a href="<?= BASE_URL ?>/join.php" class="btn-hero-ghost">Csatlakozz hozzánk</a>
    </div>
  </div>
</section>

<!-- About strip -->
<section class="pub-strip">
  <div style="max-width:700px;margin:0 auto;">
    <h2>Kik vagyunk mi?</h2>
    <p>Fiatalok vagyunk (bármit is jelentsen ez), akik szeretnének minél nagyobb szeletet felfedezni a világból. Ezt nagyobb közösségben a legjobb művelni, ezért szeretnénk ha Te is csatlakoznál hozzánk. Ha velünk tartasz, akkor egy baráti társaságba csöppenhetsz, ahol a kalandok és a természet szeretete a fő hajtóerő.</p>
    <a href="<?= BASE_URL ?>/public/rolunk.php" class="btn-hero-ghost" style="display:inline-flex;align-items:center;gap:8px;">Tudj meg többet →</a>
  </div>
</section>

<!-- Tour calendar (after "Who are we") -->
<?php
$upcomingTours = $pdo->query("
    SELECT ft.*, c.name_hu AS country_name, c.flag_filename AS country_flag,
           (SELECT COUNT(*) FROM future_tour_applications fta WHERE fta.future_tour_id = ft.id AND fta.status = 'confirmed') AS confirmed_count
    FROM future_tours ft
    LEFT JOIN countries c ON c.code = ft.country
    WHERE ft.status != 'cancelled' AND ft.start_date >= CURDATE()
    ORDER BY ft.start_date ASC
    LIMIT 3
")->fetchAll();
?>
<div class="pub-wrap">
  <h2 class="pub-section-title">Közelgő túrák</h2>
  <p class="pub-section-subtitle">Jelentkezz a következő kalandokra!</p>
  <?php if (empty($upcomingTours)): ?>
    <div style="text-align:center;padding:40px;color:var(--text-muted);">
      <div style="font-size:40px;margin-bottom:10px;">🗓️</div>
      <p>Jelenleg nincs meghirdetett túra. Kövess minket Facebookon!</p>
    </div>
  <?php else: ?>
  <div class="pub-tour-cards">
    <?php foreach ($upcomingTours as $t): ?>
    <?php
    $confirmed = (int)$t['confirmed_count'];
    $maxSlots  = (int)$t['max_attendees'];
    $spotsLeft = max(0, $maxSlots - $confirmed);
    $huDateRange = $t['start_date'] ? huDateRange($t['start_date'], (int)$t['num_days'], $HU_MONTHS) : '—';
    ?>
    <a href="<?= BASE_URL ?>/public/tour-detail.php?id=<?= (int)$t['id'] ?>" class="pub-tour-card">
      <?php if (!empty($t['cover_img'])): ?>
        <img src="<?= BASE_URL ?>/assets/uploads/tour-covers/<?= e($t['cover_img']) ?>" class="pub-tour-card-img" alt="<?= e($t['name']) ?>">
      <?php else: ?>
        <div class="pub-tour-card-img-placeholder">
          <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path d="M3 17l5-8 4 6 3-4 5 6H3z"/></svg>
        </div>
      <?php endif; ?>
      <div class="pub-tour-card-body">
        <div class="pub-tour-card-title-row">
          <div class="pub-tour-card-title">
            <?php if (!empty($t['country_flag'])): ?>
              <img src="<?= e(getFlagUrl($t['country_flag'])) ?>" alt="">
            <?php endif; ?>
            <?= e($t['name']) ?>
          </div>
          <div class="pub-tour-card-spots">
            <?php if ($t['status'] !== 'open'): ?>
              <span class="badge badge-inactive">Lezárt</span>
            <?php elseif ($spotsLeft > 0): ?>
              <span class="badge badge-active"><?= $spotsLeft ?> szabad hely</span>
            <?php else: ?>
              <span class="badge badge-overdue">Betelt</span>
            <?php endif; ?>
          </div>
        </div>
        <div class="pub-tour-card-dates"><?= $huDateRange ?></div>
        <?php if (!empty($t['short_intro'])): ?>
          <div class="pub-tour-card-intro"><?= e($t['short_intro']) ?></div>
        <?php endif; ?>
      </div>
      <div class="pub-tour-card-side">
        <div class="pub-tour-card-lizzardier">
          <img src="<?= BASE_URL ?>/assets/img/ures_small.png" alt="Lizzardier">
          <?php if ($t['lizzardier_points'] !== null): ?>
            <span class="pub-tour-card-lizzardier-pts"><?= (int)$t['lizzardier_points'] ?></span>
          <?php endif; ?>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
  <div style="margin-top:20px;text-align:center;">
    <a href="<?= BASE_URL ?>/public/turanyptar.php" class="btn btn-ghost">Összes túra megjelenítése →</a>
  </div>
  <?php endif; ?>
</div>

<!-- Latest news -->
<?php if (!empty($latestPosts)): ?>
<div class="pub-wrap" style="padding-top:0;">
  <h2 class="pub-section-title">Legfrissebb hírek</h2>
  <p class="pub-section-subtitle">Maradj naprakész az egyesületi élettel!</p>
  <div class="pub-post-grid">
    <?php foreach ($latestPosts as $p): ?>
    <article class="pub-post-card">
      <?php if (!empty($p['cover_img'])): ?>
        <img src="<?= BASE_URL ?>/assets/uploads/posts/<?= e($p['cover_img']) ?>" class="pub-post-card-img" alt="<?= e($p['title']) ?>">
      <?php else: ?>
        <div class="pub-post-card-img-placeholder"><?= $p['category'] === 'beszmolok' ? '🎒' : '📰' ?></div>
      <?php endif; ?>
      <div class="pub-post-card-body">
        <div class="pub-post-meta">
          <span class="pub-post-category-badge <?= e($p['category']) ?>"><?= $p['category'] === 'beszmolok' ? 'Élményblog' : 'Hírek' ?></span>
          <span><?= date('Y.m.d', strtotime($p['created_at'])) ?></span>
        </div>
        <h3><a href="<?= BASE_URL ?>/public/post.php?slug=<?= urlencode($p['slug']) ?>"><?= e($p['title']) ?></a></h3>
        <?php if (!empty($p['excerpt'])): ?>
          <p><?= e($p['excerpt']) ?></p>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/public/post.php?slug=<?= urlencode($p['slug']) ?>" class="btn btn-ghost btn-sm" style="margin-top:auto;">Olvasd tovább →</a>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <div style="margin-top:20px;text-align:center;">
    <a href="<?= BASE_URL ?>/public/hirek.php" class="btn btn-ghost">Összes hír →</a>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
