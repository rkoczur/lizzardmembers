<?php
/** Egy autó kártyája ülésekkel. Vár: $car, $me, $role, $board, $post. */
$isMine   = $role['role'] === 'driver' && (int)$car['person_id'] === (int)$me['id'];
$isMyRide = $role['role'] === 'passenger' && (int)$role['car']['id'] === (int)$car['id'];
$canBook  = $role['role'] !== 'driver' && !$isMyRide;
$initials = fn(string $n): string => mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($n)), 0, 2))));
?>
<article class="tk-car<?= $isMine ? ' is-mine' : '' ?><?= $car['free'] === 0 ? ' is-full' : '' ?>">
  <header class="tk-car-head">
    <span class="tk-car-avatar" aria-hidden="true"><?= e($initials($car['name'])) ?></span>
    <div class="tk-car-driver">
      <span class="tk-car-kicker">Sofőr</span>
      <h3 class="tk-car-name"><?= e($car['name']) ?><?php if ($isMine): ?> <span class="tk-tag">te</span><?php endif; ?></h3>
    </div>
    <span class="tk-car-free"><?= $car['free'] === 0 ? 'Megtelt' : $car['free'] . ' szabad' ?></span>
  </header>

  <dl class="tk-car-depart">
    <div><dt>Honnan</dt><dd><?= e($car['departure']) ?></dd></div>
    <div><dt>Mikor</dt><dd><?= $car['departure_time'] ? e($car['departure_time']) : 'egyeztetés alatt' ?></dd></div>
  </dl>

  <?php if ($car['route']): ?>
    <p class="tk-car-route"><span class="tk-label">Útvonal</span><?= nl2br(e($car['route'])) ?></p>
  <?php endif; ?>

  <div class="tk-seats">
    <?php foreach ($car['passengers'] as $s): $isMe = (int)$s['person_id'] === (int)$me['id']; ?>
      <div class="tk-seat is-taken<?= $isMe ? ' is-me' : '' ?>">
        <span class="tk-seat-mark"><?= e($initials($s['name'])) ?></span>
        <span class="tk-seat-name"><?= e($s['name']) ?><?= $isMe ? ' (te)' : '' ?></span>
      </div>
    <?php endforeach; ?>
    <?php for ($i = 0; $i < $car['free']; $i++): ?>
      <?php if ($canBook): ?>
        <form method="post" action="<?= e($post) ?>" class="tk-seat-form">
          <input type="hidden" name="csrf" value="<?= e(tkCsrf()) ?>">
          <input type="hidden" name="k" value="<?= e($board['code']) ?>">
          <input type="hidden" name="op" value="book">
          <input type="hidden" name="driver_id" value="<?= (int)$car['id'] ?>">
          <button type="submit" class="tk-seat is-free">
            <span class="tk-seat-mark">+</span>
            <span class="tk-seat-name"><?= $role['role'] === 'passenger' ? 'Átülök ide' : 'Foglalom' ?></span>
          </button>
        </form>
      <?php else: ?>
        <div class="tk-seat is-free is-static">
          <span class="tk-seat-mark"></span>
          <span class="tk-seat-name">Szabad</span>
        </div>
      <?php endif; ?>
    <?php endfor; ?>
  </div>

  <footer class="tk-car-contact">
    <?php if ($car['phone']): ?>
      <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $car['phone'])) ?>" class="tk-contact"><?= e($car['phone']) ?></a>
    <?php endif; ?>
    <?php if ($car['contact_extra']): ?>
      <span class="tk-contact"><?= e($car['contact_extra']) ?></span>
    <?php endif; ?>
    <a href="mailto:<?= e($car['email']) ?>" class="tk-contact"><?= e($car['email']) ?></a>
  </footer>

  <?php if ($isMine && $car['passengers']): ?>
    <div class="tk-car-passengers">
      <span class="tk-label">Utasaid elérhetősége</span>
      <?php foreach ($car['passengers'] as $s): ?>
        <div class="tk-pass-row"><strong><?= e($s['name']) ?></strong><a href="mailto:<?= e($s['email']) ?>"><?= e($s['email']) ?></a></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</article>
