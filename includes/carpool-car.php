<?php
/** Telekocsi: egy autó kártyája ülésekkel. Vár: $car, $me, $role, $readOnly, $token, $post. */
$isMine    = $role['role'] === 'driver' && (int)$car['id'] === $role['driver_id'];
$canBook   = $me && !$readOnly && $role['role'] !== 'driver' && (int)$car['id'] !== $role['driver_id'];
$initials  = fn(string $n): string => implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim($n)), 0, 2)));
?>
<article class="cp-car<?= $isMine ? ' is-mine' : '' ?><?= $car['free'] === 0 ? ' is-full' : '' ?>">
  <header class="cp-car-head">
    <div class="cp-car-from">
      <span class="cp-car-from-label">Indul</span>
      <span class="cp-car-from-place"><?= e($car['departure']) ?></span>
      <?php if ($car['departure_time']): ?><span class="cp-car-time"><?= e($car['departure_time']) ?></span><?php endif; ?>
    </div>
    <span class="cp-car-free"><?= $car['free'] === 0 ? 'Megtelt' : $car['free'] . ' szabad' ?></span>
  </header>

  <?php if ($car['route']): ?>
    <p class="cp-car-route"><?= nl2br(e($car['route'])) ?></p>
  <?php endif; ?>

  <div class="cp-seats">
    <div class="cp-seat cp-seat-driver" title="Sofőr">
      <span class="cp-seat-mark"><?= e($initials($car['name'])) ?></span>
      <span class="cp-seat-name"><?= e($car['name']) ?><?= $isMine ? ' (te)' : '' ?></span>
    </div>
    <?php foreach ($car['passengers'] as $p): $isMe = $me && (int)$p['application_id'] === (int)$me['id']; ?>
      <div class="cp-seat cp-seat-taken<?= $isMe ? ' is-me' : '' ?>">
        <span class="cp-seat-mark"><?= e($initials($p['name'])) ?></span>
        <span class="cp-seat-name"><?= e($p['name']) ?><?= $isMe ? ' (te)' : '' ?></span>
      </div>
    <?php endforeach; ?>
    <?php for ($i = 0; $i < $car['free']; $i++): ?>
      <?php if ($canBook): ?>
        <form method="post" action="<?= $post ?>" class="cp-seat-form">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <input type="hidden" name="t" value="<?= e($token) ?>">
          <input type="hidden" name="op" value="book">
          <input type="hidden" name="driver_id" value="<?= (int)$car['id'] ?>">
          <button type="submit" class="cp-seat cp-seat-free">
            <span class="cp-seat-mark">+</span>
            <span class="cp-seat-name"><?= $role['role'] === 'passenger' ? 'Átülök ide' : 'Foglalom' ?></span>
          </button>
        </form>
      <?php else: ?>
        <div class="cp-seat cp-seat-free is-static">
          <span class="cp-seat-mark"></span>
          <span class="cp-seat-name">Szabad</span>
        </div>
      <?php endif; ?>
    <?php endfor; ?>
  </div>

  <footer class="cp-car-contact">
    <?php if ($car['phone']): ?>
      <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $car['phone'])) ?>" class="cp-contact-chip"><?= e($car['phone']) ?></a>
    <?php endif; ?>
    <?php if ($car['contact_extra']): ?>
      <span class="cp-contact-chip cp-contact-plain"><?= e($car['contact_extra']) ?></span>
    <?php endif; ?>
    <a href="mailto:<?= e($car['email']) ?>" class="cp-contact-chip cp-contact-plain"><?= e($car['email']) ?></a>
  </footer>

  <?php if ($isMine && $car['passengers']): ?>
    <div class="cp-car-passengers">
      <span class="cp-label">Utasaid elérhetősége</span>
      <?php foreach ($car['passengers'] as $p): ?>
        <div class="cp-pass-row">
          <strong><?= e($p['name']) ?></strong>
          <?php if ($p['phone']): ?><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $p['phone'])) ?>"><?= e($p['phone']) ?></a><?php endif; ?>
          <a href="mailto:<?= e($p['email']) ?>"><?= e($p['email']) ?></a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</article>
