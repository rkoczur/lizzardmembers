<?php
/** Az azonosított résztvevő panelje. Vár: $board, $me, $role, $myCar, $form, $openPanel, $freeTotal, $post. */
$hidden = '<input type="hidden" name="csrf" value="' . e(tkCsrf()) . '"><input type="hidden" name="k" value="' . e($board['code']) . '">';
$status = match ($role['role']) {
    'driver'    => 'Sofőrként jössz, ' . (int)$myCar['seats'] . ' szabad hellyel.',
    'passenger' => 'Utasként jössz ' . $myCar['name'] . ' autójában.',
    default     => 'Még nem választottad ki, hogyan utazol.',
};
?>
<section class="tk-card tk-me">
  <div class="tk-me-head">
    <div>
      <p class="tk-me-name"><?= e($me['name']) ?><?php if ($me['is_owner']): ?> <span class="tk-tag">szervező</span><?php endif; ?></p>
      <p class="tk-me-status"><?= e($status) ?></p>
    </div>
    <form method="post" action="<?= e($post) ?>">
      <?= $hidden ?><input type="hidden" name="op" value="forget">
      <button type="submit" class="tk-link">Nem te vagy? Kilépés</button>
    </form>
  </div>

  <div class="tk-roles" role="tablist">
    <button type="button" role="tab" class="tk-role<?= $openPanel === 'driver' ? ' is-active' : '' ?>" data-tk-tab="driver">
      <span class="tk-role-icon tk-icon-wheel" aria-hidden="true"></span>
      <span class="tk-role-title">Sofőr vagyok</span>
      <span class="tk-role-sub">Helyet kínálok az autómban</span>
    </button>
    <button type="button" role="tab" class="tk-role<?= $openPanel === 'passenger' ? ' is-active' : '' ?>" data-tk-tab="passenger">
      <span class="tk-role-icon tk-icon-seat" aria-hidden="true"></span>
      <span class="tk-role-title">Utas vagyok</span>
      <span class="tk-role-sub">Helyet keresek egy autóban</span>
    </button>
  </div>

  <div class="tk-tab<?= $openPanel === 'driver' ? ' is-open' : '' ?>" data-tk-panel="driver">
    <form method="post" action="<?= e($post) ?>" class="tk-form tk-driver-form">
      <?= $hidden ?><input type="hidden" name="op" value="driver_save">
      <div class="tk-field">
        <span class="tk-label">Szabad helyek melletted</span>
        <div class="tk-stepper">
          <button type="button" class="tk-step" data-tk-step="-1" aria-label="Kevesebb">−</button>
          <input type="number" name="seats" min="1" max="<?= TK_MAX_SEATS ?>" value="<?= (int)$form['seats'] ?>" class="tk-step-value" data-tk-seats>
          <button type="button" class="tk-step" data-tk-step="1" aria-label="Több">+</button>
        </div>
      </div>
      <label class="tk-field">
        <span class="tk-label">Telefonszám <em>ajánlott</em></span>
        <input type="tel" name="phone" class="tk-input" maxlength="50" value="<?= e((string)$form['phone']) ?>" placeholder="+36 30 123 4567" data-tk-contact>
      </label>
      <label class="tk-field">
        <span class="tk-label">Egyéb elérhetőség</span>
        <input type="text" name="contact_extra" class="tk-input" maxlength="255" value="<?= e((string)$form['contact_extra']) ?>" placeholder="pl. Messenger: Kiss Anna" data-tk-contact>
      </label>
      <label class="tk-field">
        <span class="tk-label">Honnan indulsz? *</span>
        <input type="text" name="departure" class="tk-input" required maxlength="255" value="<?= e((string)$form['departure']) ?>" placeholder="pl. Budapest, Újpest-Központ">
      </label>
      <label class="tk-field tk-field-wide">
        <span class="tk-label">Mikor indulsz?</span>
        <input type="text" name="departure_time" class="tk-input" maxlength="100" value="<?= e((string)$form['departure_time']) ?>" placeholder="pl. szombat 6:30">
      </label>
      <label class="tk-field tk-field-wide">
        <span class="tk-label">Útvonal, felszállási pontok</span>
        <textarea name="route" class="tk-input" rows="3" placeholder="pl. M3 → Gödöllő, Hatvan (benzinkút) → Eger"><?= e((string)$form['route']) ?></textarea>
      </label>
      <p class="tk-hint tk-hint-warn tk-field-wide" data-tk-contact-hint hidden>Legalább egy elérhetőséget adj meg, hogy az utasok el tudjanak érni.</p>
      <div class="tk-actions tk-field-wide">
        <button type="submit" class="tk-btn"><?= $role['role'] === 'driver' ? 'Mentés' : 'Sofőrként jelentkezem' ?></button>
        <?php if ($role['role'] === 'passenger'): ?>
          <span class="tk-hint">A mostani utas-foglalásod ezzel törlődik.</span>
        <?php endif; ?>
      </div>
    </form>
    <?php if ($role['role'] === 'driver'): ?>
      <form method="post" action="<?= e($post) ?>" class="tk-remove" data-tk-confirm="Biztosan visszalépsz sofőrként? Az utasaid foglalása is törlődik.">
        <?= $hidden ?><input type="hidden" name="op" value="driver_remove">
        <button type="submit" class="tk-link tk-link-danger">Mégsem viszek autót</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="tk-tab<?= $openPanel === 'passenger' ? ' is-open' : '' ?>" data-tk-panel="passenger">
    <?php if ($role['role'] === 'passenger'): ?>
      <p class="tk-booked">Helyed van <strong><?= e($myCar['name']) ?></strong> autójában — indulás: <strong><?= e($myCar['departure']) ?></strong>.</p>
      <p class="tk-hint">Másik autóba úgy ülhetsz át, hogy ott a szabad helyre kattintasz.</p>
      <form method="post" action="<?= e($post) ?>" data-tk-confirm="Biztosan lemondod a helyedet?">
        <?= $hidden ?><input type="hidden" name="op" value="unbook">
        <button type="submit" class="tk-link tk-link-danger">Lemondom a helyem</button>
      </form>
    <?php elseif ($role['role'] === 'driver'): ?>
      <p class="tk-hint">Sofőrként nem foglalhatsz helyet. Ha mégis utasként jönnél, előbb lépj vissza sofőrként.</p>
    <?php elseif ($freeTotal > 0): ?>
      <p class="tk-hint">Lent az autóknál a <span class="tk-seat-demo" aria-hidden="true"></span> szabad helyre kattintva foglalhatsz magadnak helyet.</p>
    <?php else: ?>
      <p class="tk-hint">Jelenleg nincs szabad hely. Nézz vissza később — amint egy sofőr helyet kínál, itt látni fogod.</p>
    <?php endif; ?>
  </div>
</section>
