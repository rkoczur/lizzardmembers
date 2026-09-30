<?php
/** Telekocsi: az azonosított résztvevő saját szerepe. Vár: $me, $role, $myCar, $form, $openPanel, $freeTotal, $readOnly, $token, $post. */
$hidden = '<input type="hidden" name="csrf_token" value="' . csrfToken() . '"><input type="hidden" name="t" value="' . e($token) . '">';
$status = match ($role['role']) {
    'driver'    => 'Sofőrként jössz, ' . (int)$myCar['seats'] . ' hellyel.',
    'passenger' => 'Utasként jössz ' . $myCar['name'] . ' autójában.',
    default     => 'Még nem választottad ki, hogyan utazol.',
};
?>
<section class="cp-panel cp-me">
  <div class="cp-me-head">
    <div>
      <p class="cp-me-name"><?= e($me['name']) ?></p>
      <p class="cp-me-status"><?= e($status) ?></p>
    </div>
    <form method="post" action="<?= $post ?>">
      <?= $hidden ?><input type="hidden" name="op" value="forget">
      <button type="submit" class="cp-link-btn">Nem te vagy? Kilépés</button>
    </form>
  </div>

  <?php if (!$readOnly): ?>
  <div class="cp-roles" role="tablist">
    <button type="button" class="cp-role<?= $openPanel === 'driver' ? ' is-active' : '' ?>" data-cp-tab="driver">
      <span class="cp-role-icon cp-role-icon-wheel" aria-hidden="true"></span>
      <span class="cp-role-title">Sofőr vagyok</span>
      <span class="cp-role-sub">Helyet kínálok az autómban</span>
    </button>
    <button type="button" class="cp-role<?= $openPanel === 'passenger' ? ' is-active' : '' ?>" data-cp-tab="passenger">
      <span class="cp-role-icon cp-role-icon-seat" aria-hidden="true"></span>
      <span class="cp-role-title">Utas vagyok</span>
      <span class="cp-role-sub">Helyet keresek egy autóban</span>
    </button>
  </div>

  <div class="cp-tab<?= $openPanel === 'driver' ? ' is-open' : '' ?>" data-cp-panel="driver">
    <form method="post" action="<?= $post ?>" class="cp-driver-form">
      <?= $hidden ?><input type="hidden" name="op" value="driver_save">
      <div class="cp-field cp-field-seats">
        <span class="cp-label">Szabad helyek melletted</span>
        <div class="cp-stepper">
          <button type="button" class="cp-step" data-cp-step="-1" aria-label="Kevesebb">−</button>
          <input type="number" name="seats" min="1" max="<?= CARPOOL_MAX_SEATS ?>" value="<?= (int)$form['seats'] ?>" class="cp-step-value" data-cp-seats>
          <button type="button" class="cp-step" data-cp-step="1" aria-label="Több">+</button>
        </div>
      </div>
      <label class="cp-field">
        <span class="cp-label">Telefonszám <em>ajánlott</em></span>
        <input type="tel" name="phone" class="cp-input" value="<?= e((string)$form['phone']) ?>" placeholder="+36 30 123 4567" data-cp-contact>
      </label>
      <label class="cp-field">
        <span class="cp-label">Egyéb elérhetőség</span>
        <input type="text" name="contact_extra" class="cp-input" value="<?= e((string)$form['contact_extra']) ?>" placeholder="pl. Messenger: Kiss Anna" data-cp-contact>
      </label>
      <label class="cp-field">
        <span class="cp-label">Honnan indulsz? *</span>
        <input type="text" name="departure" class="cp-input" required value="<?= e((string)$form['departure']) ?>" placeholder="pl. Budapest, Újpest-Központ">
      </label>
      <label class="cp-field">
        <span class="cp-label">Mikor indulsz?</span>
        <input type="text" name="departure_time" class="cp-input" value="<?= e((string)$form['departure_time']) ?>" placeholder="pl. szombat 6:30">
      </label>
      <label class="cp-field cp-field-wide">
        <span class="cp-label">Útvonal, felszállási pontok</span>
        <textarea name="route" class="cp-input" rows="3" placeholder="pl. M3 → Gödöllő, Hatvan (benzinkút) → Eger"><?= e((string)$form['route']) ?></textarea>
      </label>
      <p class="cp-hint cp-field-wide" data-cp-contact-hint hidden>Legalább egy elérhetőséget adj meg, hogy az utasok el tudjanak érni.</p>
      <div class="cp-actions cp-field-wide">
        <button type="submit" class="cp-btn"><?= $role['role'] === 'driver' ? 'Mentés' : 'Sofőrként jelentkezem' ?></button>
        <?php if ($role['role'] === 'passenger'): ?>
          <span class="cp-hint">A mostani utas-foglalásod ezzel törlődik.</span>
        <?php endif; ?>
      </div>
    </form>
    <?php if ($role['role'] === 'driver'): ?>
    <form method="post" action="<?= $post ?>" class="cp-remove"
          data-cp-confirm="Biztosan visszalépsz sofőrként? Az utasaid foglalása is törlődik.">
      <?= $hidden ?><input type="hidden" name="op" value="driver_remove">
      <button type="submit" class="cp-link-btn cp-link-danger">Mégsem viszek autót</button>
    </form>
    <?php endif; ?>
  </div>

  <div class="cp-tab<?= $openPanel === 'passenger' ? ' is-open' : '' ?>" data-cp-panel="passenger">
    <?php if ($role['role'] === 'passenger'): ?>
      <div class="cp-booked">
        <p>Helyed van <strong><?= e($myCar['name']) ?></strong> autójában — indulás: <strong><?= e($myCar['departure']) ?></strong>.</p>
        <p class="cp-hint">Másik autóba úgy ülhetsz át, hogy ott a szabad helyre kattintasz.</p>
        <form method="post" action="<?= $post ?>" data-cp-confirm="Biztosan lemondod a helyedet?">
          <?= $hidden ?><input type="hidden" name="op" value="unbook">
          <button type="submit" class="cp-link-btn cp-link-danger">Lemondom a helyem</button>
        </form>
      </div>
    <?php elseif ($role['role'] === 'driver'): ?>
      <p class="cp-hint">Sofőrként nem foglalhatsz helyet. Ha mégis utasként jönnél, előbb lépj vissza sofőrként.</p>
    <?php elseif ($freeTotal > 0): ?>
      <p class="cp-hint">Lent a sofőröknél a <span class="cp-seat-demo" aria-hidden="true"></span> szabad helyre kattintva foglalhatsz magadnak helyet.</p>
    <?php else: ?>
      <p class="cp-hint">Jelenleg nincs szabad hely. Nézz vissza később — amint egy sofőr helyet kínál, itt látni fogod.</p>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</section>
