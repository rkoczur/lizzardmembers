<?php /** Csatlakozás / visszaazonosítás név + e-mail címmel. Vár: $board, $post. */ ?>
<section class="tk-card tk-join">
  <h2 class="tk-h2">Csatlakozz a szervezőhöz</h2>
  <p class="tk-p">Add meg a neved és az e-mail címed. Ha már csatlakoztál, ugyanazzal az e-mail címmel visszatérhetsz.</p>
  <form method="post" action="<?= e($post) ?>" class="tk-form">
    <input type="hidden" name="csrf" value="<?= e(tkCsrf()) ?>">
    <input type="hidden" name="k" value="<?= e($board['code']) ?>">
    <input type="hidden" name="op" value="join">
    <label class="tk-field">
      <span class="tk-label">Neved *</span>
      <input type="text" name="name" class="tk-input" required maxlength="100" autocomplete="name" placeholder="pl. Nagy Péter">
    </label>
    <label class="tk-field">
      <span class="tk-label">E-mail címed *</span>
      <input type="email" name="email" class="tk-input" required maxlength="190" autocomplete="email" placeholder="pelda@email.hu">
    </label>
    <div class="tk-actions tk-field-wide">
      <button type="submit" class="tk-btn">Belépek</button>
    </div>
  </form>
</section>
