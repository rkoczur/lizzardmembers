<footer class="pub-footer">
  <div class="pub-footer-inner" style="grid-template-columns:1fr 1fr;">
    <div>
      <h4>Lizzard Outdoor</h4>
      <p>Leguán Osztag Természetjáró Egyesület</p>
      <p>1041 Budapest, Rózsa u. 59. III/9.</p>
      <p>Adószám: 18902622-1-41</p>
      <p>Bankszámla: 16200120-18542675</p>
    </div>
    <div>
      <h4>Kapcsolat</h4>
      <a href="mailto:info@lizzard.hu">info@lizzard.hu</a>
      <a href="tel:+36203961021">+36 (20) 396-1021</a>
      <div class="pub-social-links">
        <a href="https://www.facebook.com/lizzardoutdoor/" target="_blank" rel="noopener" class="pub-social-btn pub-social-fb">Facebook</a>
        <a href="https://www.instagram.com/lizzardoutdoor/" target="_blank" rel="noopener" class="pub-social-btn pub-social-ig">Instagram</a>
        <a href="https://www.youtube.com/@lizzardhu" target="_blank" rel="noopener" class="pub-social-btn pub-social-yt">YouTube</a>
      </div>
    </div>
  </div>
  <div class="pub-footer-bottom">
    <span>© <?= date('Y') ?> Leguán Osztag Természetjáró Egyesület — Minden jog fenntartva</span>
    <span>
      <a href="<?= BASE_URL ?>/public/cookie-tajekoztato.php" style="color:inherit;">Süti tájékoztató</a>
      · v<?= APP_VERSION ?> · Copyright © Koczur Richárd
    </span>
  </div>
</footer>

<?php if (!isset($_COOKIE['lo_consent'])): ?>
<div id="cookie-consent" class="cookie-consent" role="dialog" aria-live="polite" aria-label="Süti tájékoztató">
  <div class="cookie-consent-text">
    Anonim sütit használunk a látogatottság méréséhez. IP-címet nem tárolunk.
    <a href="<?= BASE_URL ?>/public/cookie-tajekoztato.php">Részletek</a>
  </div>
  <div class="cookie-consent-actions">
    <button type="button" class="cookie-btn cookie-btn-ghost" data-consent="0">Elutasítom</button>
    <button type="button" class="cookie-btn cookie-btn-accept" data-consent="1">Elfogadom</button>
  </div>
</div>
<script>
(function () {
  var el = document.getElementById('cookie-consent');
  if (!el) return;
  el.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-consent]');
    if (!btn) return;
    var val = btn.getAttribute('data-consent');
    var exp = new Date(); exp.setTime(exp.getTime() + 400 * 24 * 60 * 60 * 1000);
    document.cookie = 'lo_consent=' + val + ';expires=' + exp.toUTCString() + ';path=/;SameSite=Lax';
    if (val === '0') {
      // elutasításkor a meglévő mérési sütit is töröljük
      document.cookie = 'lo_vid=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
    }
    el.classList.add('cookie-consent-hidden');
  });
})();
</script>
<?php endif; ?>

<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= APP_VERSION ?>" defer></script>
</body>
</html>
