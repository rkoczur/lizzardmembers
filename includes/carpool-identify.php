<?php
/** Telekocsi: e-mail alapú azonosítás. Vár: $token, $post, $tourUrl, $unknownEmail. */
?>
<section class="cp-panel cp-identify">
  <h2>Ki vagy?</h2>
  <p>Add meg azt az e-mail címet, amellyel a túrára jelentkeztél.</p>
  <form method="post" action="<?= $post ?>" class="cp-identify-form">
    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
    <input type="hidden" name="t" value="<?= e($token) ?>">
    <input type="hidden" name="op" value="identify">
    <input type="email" name="email" class="cp-input" required autocomplete="email"
           placeholder="pelda@email.hu" value="<?= e((string)$unknownEmail) ?>">
    <button type="submit" class="cp-btn">Tovább</button>
  </form>
  <?php if ($unknownEmail !== null): ?>
    <div class="cp-unknown">
      <strong>Ezzel az e-mail címmel még nem jelentkeztek a túrára.</strong>
      Ha jönnél, <a href="<?= e($tourUrl) ?>">itt tudsz jelentkezni a túrára</a>, utána térj vissza ide.
    </div>
  <?php endif; ?>
</section>
