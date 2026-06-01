<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/join-schema.php';

$pdo = getDb();
ensureJoinSchema($pdo);

$submitted   = isset($_GET['submitted']);
$old         = $_SESSION['join_old'] ?? [];
unset($_SESSION['join_old']);

$flash_success = getFlash('success');
$flash_error   = getFlash('error');
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Belépés az egyesületbe — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="login-page">
  <div class="login-card join-card">

    <div class="login-logo">
      <div class="app-name"><?= APP_NAME ?></div>
      <div class="app-sub">Tagságkezelés</div>
    </div>

    <?php if ($submitted): ?>
    <!-- ── Sikeres beküldés ── -->
    <div style="text-align:center;padding:16px 0 8px;">
      <div class="join-success-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
      </div>
      <h2 style="font-size:22px;margin:0 0 12px;">Köszönjük a jelentkezésed!</h2>
      <p style="font-size:14px;color:var(--text-muted);line-height:1.7;margin:0 0 8px;">Megkaptuk a belépési kérelmedet. Az egyesület képviselői hamarosan átnézik, és e-mailben értesítünk a döntésről.</p>
      <p style="font-size:13px;color:var(--text-muted);margin:0 0 28px;">Addig is, ha kérdésed van, keress minket!</p>
      <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:6px;">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Vissza a bejelentkezéshez
      </a>
    </div>

    <?php else: ?>
    <!-- ── Jelentkezési űrlap ── -->

    <div style="margin-bottom:24px;">
      <h1 style="font-size:20px;font-weight:700;margin:0 0 6px;">Belépési kérelem</h1>
      <p style="font-size:13px;color:var(--text-muted);margin:0;line-height:1.6;">Töltsd ki az alábbi adatokat, és elküldjük a kérelmedet az egyesület képviselőinek.</p>
    </div>

    <?php if ($flash_error): ?>
      <div class="alert alert-error" style="margin-bottom:20px;"><?= e($flash_error) ?></div>
    <?php endif; ?>
    <?php if ($flash_success): ?>
      <div class="alert alert-success" style="margin-bottom:20px;"><?= e($flash_success) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= BASE_URL ?>/actions/join-submit.php" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

      <div style="margin-bottom:22px;font-size:13px;color:#444;line-height:1.7;">
        <p style="margin:0 0 10px;">Jelen belépési nyilatkozat benyújtásával kérem a Leguán Osztag Természetjáró Egyesület (L.O.T.E. - 1041 Budapest, Rózsa u. 59.) elnökségét, hogy az Egyesület rendes tagjává szíveskedjen fogadni.</p>
        <p style="margin:0 0 10px;">Az Alapszabályt és a Részvételi feltételeket megismertem és az abban foglaltakat magamra nézve elfogadom. A tagsági díjat és a tagdíj fizetés módját ismerem és elfogadom.</p>
        <p style="margin:0 0 10px;">Alulírott kijelentem, hogy az általam közölt adatok a valóságnak megfelelnek, és tudomásul veszem, hogy a valótlan adatközlés a tagsági regisztráció törlését és fegyelmi eljárást von maga után.</p>
        <p style="margin:0;">Jelen nyilatkozat elküldésével hozzájárulok, hogy a megadott adataimat a L.O.T.E. tárolja és kezelje, a Magyar Természetjáró Szövetségnek továbbítsa, valamint, a Magyar Természetjáró Szövetség azokat kezelje, tárolja és a tagnyilvántartás céljából felhasználja a tagság érvényességi idejére. A tag jogosult jelen hozzájárulását írásban bármikor visszavonni.</p>
      </div>

      <div class="form-grid">
        <div class="form-group">
          <label>Vezetéknév <span style="color:var(--danger,#dc2626);">*</span></label>
          <input type="text" name="lastname" value="<?= e($old['lastname'] ?? '') ?>" required autofocus>
        </div>
        <div class="form-group">
          <label>Keresztnév <span style="color:var(--danger,#dc2626);">*</span></label>
          <input type="text" name="firstname" value="<?= e($old['firstname'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>E-mail cím <span style="color:var(--danger,#dc2626);">*</span></label>
          <input type="email" name="email" value="<?= e($old['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Telefonszám</label>
          <input type="tel" name="phone" value="<?= e($old['phone'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Születési dátum <span style="color:var(--danger,#dc2626);">*</span></label>
          <input type="date" name="dateofbirth" value="<?= e($old['dateofbirth'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Irányítószám <span style="color:var(--danger,#dc2626);">*</span></label>
          <input type="text" name="zipcode" value="<?= e($old['zipcode'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Város <span style="color:var(--danger,#dc2626);">*</span></label>
          <input type="text" name="city" value="<?= e($old['city'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Lakcím <span style="color:var(--danger,#dc2626);">*</span></label>
          <input type="text" name="address" value="<?= e($old['address'] ?? '') ?>" required>
        </div>
        <div class="form-group full">
          <label>Megjegyzés / Motiváció</label>
          <textarea name="message" rows="3" style="resize:vertical;"><?= e($old['message'] ?? '') ?></textarea>
        </div>
      </div>

      <div style="margin-top:28px;padding-top:22px;border-top:1px solid var(--border);">
        <p style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-muted);margin:0 0 14px;">Hozzájárulások és elfogadások</p>
        <div class="consent-list">

          <label class="consent-row">
            <input type="checkbox" name="consent_email" value="1" <?= !empty($old) && isset($old['consent_email']) ? 'checked' : '' ?>>
            <span class="consent-row-text">Hozzájárulok, hogy e-mail-címem az események szervezése közben folytatott levelezéseknél a címzettek között nyilvánosan megjelenjen.</span>
          </label>

          <label class="consent-row">
            <input type="checkbox" name="consent_photo" value="1" <?= !empty($old) && isset($old['consent_photo']) ? 'checked' : '' ?>>
            <span class="consent-row-text">Hozzájárulok, hogy az egyesület eseményein rólam készült fényképek a L.O.T.E weboldalán, valamint social-media felületeken nyilvánosan megjelenjenek.</span>
          </label>

          <label class="consent-row consent-row-required">
            <input type="checkbox" name="consent_rules" value="1" required <?= !empty($old) && isset($old['consent_rules']) ? 'checked' : '' ?>>
            <span class="consent-row-text">
              Elolvastam és elfogadom magamra nézve az
              <a href="https://www.lizzard.hu/wp-content/uploads/2018/05/gdpr_adatvedelem_lote_20150521.pdf" target="_blank" rel="noopener noreferrer">Adatvédelmi Tájékoztatóban</a>,
              az Egyesület Alapszabályában és a Részvételi feltételekben foglaltakat.
              <strong style="color:#d97706;display:inline-block;margin-left:4px;">— Kötelező</strong>
            </span>
          </label>

        </div>
      </div>

      <div style="margin-top:24px;">
        <button type="submit" class="btn btn-primary" style="width:100%;padding:13px;font-size:15px;font-weight:700;">
          Jelentkezés elküldése
        </button>
      </div>
    </form>

    <p style="text-align:center;margin-top:18px;font-size:13px;">
      <a href="<?= BASE_URL ?>/login.php" style="color:var(--text-muted);">← Vissza a bejelentkezéshez</a>
    </p>

    <?php endif; ?>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<script>
(function () {
    function sendHeight() {
        var wrap = document.querySelector('.login-page');
        var h = wrap
            ? (wrap.offsetTop + wrap.scrollHeight + 24)
            : Math.max(document.documentElement.scrollHeight, document.body.scrollHeight);
        window.parent.postMessage({ type: 'lote_join_height', height: h }, '*');
    }
    window.addEventListener('load', sendHeight);
    if (window.ResizeObserver) {
        var target = document.querySelector('.login-card') || document.body;
        new ResizeObserver(sendHeight).observe(target);
    }
})();
</script>
</body>
</html>
