<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle     = 'Süti tájékoztató';
$activePubPage = '';
$metaDescription = 'Tájékoztató a Lizzard Outdoor weboldalán használt sütikről (cookie-król) és az adatkezelésről.';
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap-narrow">
  <div class="pub-page-header">
    <h1>Süti (cookie) tájékoztató</h1>
    <p>Hogyan és milyen célból használunk sütiket ezen a weboldalon.</p>
  </div>

  <div class="pub-prose">
    <p>
      Weboldalunk a működéshez és a látogatottság anonim méréséhez sütiket (cookie-kat)
      használ. Tiszteletben tartjuk a magánszférádat: <strong>nem tárolunk IP-címet</strong>,
      nem használunk hirdetési vagy harmadik féltől származó nyomkövető sütiket, és nem adunk
      át adatot külső szolgáltatónak. A mérési adatok kizárólag a saját szerverünkön maradnak.
    </p>

    <h2>Milyen sütiket használunk?</h2>
    <table class="pub-log-table" style="min-width:0;width:100%;margin:14px 0;">
      <thead>
        <tr><th>Süti</th><th>Cél</th><th>Típus</th><th class="right">Élettartam</th></tr>
      </thead>
      <tbody>
        <tr>
          <td><code>lo_consent</code></td>
          <td>A süti-választásodat tárolja, hogy ne kérdezzünk rá újra.</td>
          <td>Szükséges</td>
          <td class="right">~13 hónap</td>
        </tr>
        <tr>
          <td><code>lo_vid</code></td>
          <td>Anonim, véletlenszerű látogató-azonosító a látogatottság méréséhez (egyedi látogatók számolása). Csak akkor jön létre, ha elfogadod.</td>
          <td>Statisztikai</td>
          <td class="right">~13 hónap</td>
        </tr>
        <tr>
          <td><code>PHPSESSID</code></td>
          <td>Munkamenet-süti a bejelentkezés és az oldal alapműködéséhez.</td>
          <td>Szükséges</td>
          <td class="right">A böngésző bezárásáig</td>
        </tr>
      </tbody>
    </table>

    <h2>Jogalap és hozzájárulás</h2>
    <p>
      A statisztikai süti (<code>lo_vid</code>) csak a kifejezett hozzájárulásod után jön létre
      (GDPR 6. cikk (1) a) pont). A szükséges sütik a weboldal működéséhez elengedhetetlenek.
      A hozzájárulásodat bármikor módosíthatod az alábbi gombbal, vagy a böngésződ süti-beállításaiban.
    </p>

    <p style="margin-top:18px;">
      <button type="button" id="cookie-reset" class="btn btn-secondary">Süti-beállítások módosítása</button>
    </p>
  </div>
</div>

<script>
document.getElementById('cookie-reset').addEventListener('click', function () {
  document.cookie = 'lo_consent=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
  document.cookie = 'lo_vid=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
  location.reload();
});
</script>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
