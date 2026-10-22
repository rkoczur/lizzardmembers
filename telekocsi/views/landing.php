<?php /** Nyitóoldal: új szervező létrehozása vagy meglévő megnyitása kóddal. Vár: $post. */ ?>
<section class="tk-hero">
  <p class="tk-kicker">Közös autózás, egyszerűen</p>
  <h1 class="tk-hero-title">Telekocsi<span>szervező</span></h1>
  <p class="tk-lead">Hozz létre egy szervezőt, küldd el a linket a társaságnak, és mindenki látja,
    ki visz autót, hol van még szabad hely.</p>
</section>

<section class="tk-card tk-create" data-tk-reveal>
  <button type="button" class="tk-cta" data-tk-reveal-btn>
    <span class="tk-cta-plus" aria-hidden="true"></span>
    <span>Új telekocsi-szervező létrehozása</span>
  </button>
  <form method="post" action="<?= e($post) ?>" class="tk-form" data-tk-reveal-body hidden>
    <input type="hidden" name="csrf" value="<?= e(tkCsrf()) ?>">
    <input type="hidden" name="op" value="create">
    <label class="tk-field">
      <span class="tk-label">Neved *</span>
      <input type="text" name="name" class="tk-input" required maxlength="100" autocomplete="name" placeholder="pl. Kiss Anna">
    </label>
    <label class="tk-field">
      <span class="tk-label">E-mail címed *</span>
      <input type="email" name="email" class="tk-input" required maxlength="190" autocomplete="email" placeholder="pelda@email.hu">
    </label>
    <label class="tk-field tk-field-wide">
      <span class="tk-label">Mire szervezed? <em>nem kötelező</em></span>
      <input type="text" name="title" class="tk-input" maxlength="150" placeholder="pl. Mátrai túra, szombat">
    </label>
    <div class="tk-actions tk-field-wide">
      <button type="submit" class="tk-btn">Létrehozás</button>
    </div>
  </form>
</section>

<section class="tk-card tk-open">
  <h2 class="tk-h2">Van már kódod?</h2>
  <form method="get" action="<?= e(tkBase()) ?>/" class="tk-open-form">
    <input type="text" name="k" class="tk-input tk-input-code" required maxlength="9"
           placeholder="ABCD-2345" autocomplete="off" spellcheck="false">
    <button type="submit" class="tk-btn tk-btn-dark">Megnyitás</button>
  </form>
</section>
