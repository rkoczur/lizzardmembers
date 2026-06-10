<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';

$pdo = getDb();
ensurePublicSchema($pdo);

// Helyben kezelt bejelentkezés (nem irányítunk át a login.php-re)
$loginError = '';
if (!isLoggedIn() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mtsz_login'])) {
    require_once __DIR__ . '/../includes/login-handler.php';
    $result = attemptLogin($pdo, $_POST['identifier'] ?? '', $_POST['password'] ?? '');
    if ($result['ok']) {
        // Siker után helyben (ezen az oldalon) jelenítjük meg a túraűrlapot
        header('Location: ' . BASE_URL . '/public/mtsz-turanaplo.php');
        exit;
    }
    $loginError = $result['error'];
}

// Admin felületről (Lapok) szerkeszthető bevezető szöveg
$stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = 'mtsz-turanaplo' LIMIT 1");
$stmt->execute();
$page = $stmt->fetch();

$pageTitle     = $page['title'] ?? 'MTSZ túranapló';
$activePubPage = 'mtsz-turanaplo';

// Csak bejelentkezett tagoknak töltjük be az űrlaphoz szükséges adatokat
$loggedIn = isLoggedIn();
if ($loggedIn) {
    require_once __DIR__ . '/../includes/tours-schema.php';
    ensureToursSchema($pdo);

    $currentUserId  = getCurrentUserId();
    $allMembersStmt = $pdo->prepare("SELECT id, firstname, lastname FROM users WHERE id != ? ORDER BY lastname, firstname");
    $allMembersStmt->execute([$currentUserId]);
    $allMembers = $allMembersStmt->fetchAll();
    $countries  = getCountries($pdo);

    $flash_error = getFlash('error');
    $old = $_SESSION['tour_submit_old'] ?? [];
    unset($_SESSION['tour_submit_old']);
}

$metaDescription = $page['meta_description'] ?? '';
$metaKeywords    = $page['meta_keywords'] ?? '';
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap-narrow">
  <div class="pub-page-header">
    <h1><?= e($page['title'] ?? 'MTSZ túranapló') ?></h1>
    <p>Küldd be saját túráidat, és gyűjtsd az MTSZ-minősítéshez szükséges pontokat.</p>
  </div>

  <?php /* Admin felületről szerkeszthető szabad szöveg (TinyMCE) */ ?>
  <?php if (!empty(trim((string)($page['body'] ?? '')))): ?>
    <div class="pub-prose" style="margin-bottom:32px;"><?= $page['body'] ?></div>
  <?php endif; ?>

  <?php if ($loggedIn): ?>

    <?php if (!empty($flash_error)): ?>
      <div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div>
    <?php endif; ?>
    <div class="card-centered">
    <?php include __DIR__ . '/../includes/tour-submit-form.php'; ?>
    </div>

  <?php else: ?>

    <div class="pub-info-box" style="max-width:520px;margin:0 auto;border-left:none;border-top:4px solid var(--primary);padding:32px 28px;">
      <div style="text-align:center;margin-bottom:22px;">
        <div style="font-size:2.4rem;line-height:1;margin-bottom:12px;">🏔️</div>
        <h2 style="font-size:20px;font-weight:800;color:var(--sidebar-bg);margin-bottom:12px;">Túrajelentés beküldése csak tagoknak</h2>
        <p style="font-size:15px;line-height:1.7;color:var(--text);margin:0;">
          Az MTSZ-minősítésekhez szükséges túrajelentéseket kizárólag az egyesület
          <strong>aktív tagsággal rendelkező túratársai</strong> küldhetik be.
          Már tag vagy? Jelentkezz be a beküldéshez.
        </p>
      </div>

      <?php if ($loginError): ?>
        <div class="alert alert-error"><?= e($loginError) ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="on" style="text-align:left;">
        <input type="hidden" name="mtsz_login" value="1">
        <div class="form-group mb-4">
          <label for="identifier">Felhasználónév vagy E-mail</label>
          <input type="text" id="identifier" name="identifier"
                 value="<?= e($_POST['identifier'] ?? '') ?>"
                 autocomplete="username" autofocus required>
        </div>
        <div class="form-group mb-4">
          <label for="password">Jelszó</label>
          <input type="password" id="password" name="password"
                 autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px;">Bejelentkezés</button>
      </form>
      <p style="text-align:center;margin-top:14px;font-size:13px;">
        <a href="<?= BASE_URL ?>/password-reset.php">Elfelejtetted a jelszavad?</a>
      </p>

      <div style="display:flex;align-items:center;gap:12px;margin:22px 0 16px;">
        <div style="flex:1;height:1px;background:var(--border);"></div>
        <span style="font-size:12px;color:var(--text-muted);white-space:nowrap;">vagy</span>
        <div style="flex:1;height:1px;background:var(--border);"></div>
      </div>

      <p style="text-align:center;font-size:14px;line-height:1.6;color:var(--text-muted);margin-bottom:14px;">
        Még nem vagy tag, de szeretnéd megszerezni az MTSZ-minősítéseket?
      </p>
      <a href="<?= BASE_URL ?>/join.php" class="btn btn-secondary" style="display:block;text-align:center;padding:12px 20px;font-size:15px;">Jelentkezés tagnak →</a>
    </div>

  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
