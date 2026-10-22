<?php /** Résztvevők listája szerep szerint jelölve. Vár: $people, $drivers, $me. */
$roleOf = fn(array $p): ?string => tkGetRole($drivers, (int)$p['id'])['role']; ?>
<section class="tk-people">
  <h2 class="tk-h2 tk-h2-light">Résztvevők</h2>
  <ul class="tk-chips">
    <?php foreach ($people as $p): $r = $roleOf($p); ?>
      <li class="tk-chip tk-chip-<?= e($r ?? 'none') ?><?= (int)$p['id'] === (int)$me['id'] ? ' is-me' : '' ?>">
        <?= e($p['name']) ?><?= $p['is_owner'] ? ' <small>szervező</small>' : '' ?>
        <em><?= match ($r) { 'driver' => 'sofőr', 'passenger' => 'utas', default => 'még nem döntött' } ?></em>
      </li>
    <?php endforeach; ?>
  </ul>
</section>
