<?php
// Szervező(k) kártya a meghirdetett túra adatlapjának jobb oszlopában. Bemenet: $organizers (getFutureTourOrganizers()).
if (empty($organizers)) return;
?>
<div class="card tour-org-card">
  <div class="card-header"><h2>Szervező(k)</h2></div>
  <div class="card-body">
    <ul class="to-list">
      <?php foreach ($organizers as $org):
        $orgEmail = trim((string)($org['email'] ?? ''));
        $orgPhone = trim((string)($org['phone'] ?? ''));
      ?>
      <li class="to-person">
        <img class="to-avatar" src="<?= e(getAvatarThumbUrl($org['profile_picture'] ?? null)) ?>" alt="" loading="lazy">
        <div class="to-info">
          <span class="to-name"><?= e($org['lastname'] . ' ' . $org['firstname']) ?></span>
          <?php if ($orgEmail !== ''): ?>
          <a class="to-contact" href="mailto:<?= e($orgEmail) ?>"><?= e($orgEmail) ?></a>
          <?php endif; ?>
          <?php if ($orgPhone !== ''): ?>
          <a class="to-contact" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $orgPhone)) ?>"><?= e($orgPhone) ?></a>
          <?php endif; ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
