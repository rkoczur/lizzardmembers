<?php
// Egy nap sora a meghirdetett túra szerkesztőjében.
// Bemenet: $day (tömb vagy null az új / sablon sorhoz), $dayNum, $ro, $dayGpxCount (nap-szám => csatolt GPX db).
$dayTypes = ['Gyalogtúra', 'Vízitúra', 'Kerékpártúra', 'Síelés', 'Barlangi túra', 'Munkavégzés', 'Közösségi program'];
$dayOrig  = $day ? (int)$day['day_number'] : 0;
$gpxCount = $dayOrig ? ($dayGpxCount[$dayOrig] ?? 0) : 0;
?>
<div class="day-row">
  <?php if (!$ro): ?>
    <button type="button" class="remove-day-btn btn-remove" style="position:absolute;top:8px;right:10px;" title="Nap törlése">✕</button>
  <?php endif; ?>
  <input type="hidden" name="day_id[]" value="<?= $day ? (int)$day['id'] : 0 ?>">
  <input type="hidden" name="day_orig[]" value="<?= $dayOrig ?>">
  <div class="day-row-grid">
    <div class="form-group">
      <label><?= (int)$dayNum ?>. nap</label>
      <input type="hidden" name="day_number[]" value="<?= (int)$dayNum ?>">
    </div>
    <div class="form-group">
      <label>Túratípus</label>
      <select name="day_tour_type[]" <?= $ro ? 'disabled' : '' ?>>
        <option value="">—</option>
        <?php foreach ($dayTypes as $tt): ?>
          <option value="<?= e($tt) ?>" <?= ($day['tour_type'] ?? '') === $tt ? 'selected' : '' ?>><?= e($tt) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Km</label>
      <input type="number" step="0.1" min="0" name="day_km[]" value="<?= e($day['km'] ?? '') ?>" <?= $ro ? 'readonly' : '' ?>>
    </div>
    <div class="form-group">
      <label>Szintemelkedés (m)</label>
      <input type="number" min="0" name="day_elevation[]" value="<?= e($day['elevation'] ?? '') ?>" <?= $ro ? 'readonly' : '' ?>>
    </div>
    <div class="form-group day-row-wide">
      <label>Nap címe</label>
      <input type="text" name="day_title[]" maxlength="255" value="<?= e($day['title'] ?? '') ?>" placeholder="pl. Párizs városnézés" <?= $ro ? 'readonly' : '' ?>>
    </div>
    <div class="form-group day-row-wide">
      <label>Leírás</label>
      <textarea name="day_description[]" rows="4" <?= $ro ? 'readonly' : '' ?>><?= e($day['description'] ?? '') ?></textarea>
    </div>
    <?php if (!$ro): ?>
    <div class="form-group day-row-wide day-gpx">
      <label>Térkép ehhez a naphoz (GPX)</label>
      <input type="file" name="day_gpx[]" accept=".gpx">
      <?php if ($gpxCount): ?>
        <small class="ft-hint"><?= $gpxCount ?> térkép csatolva — kezelés lent, a „GPX térképek” résznél.</small>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
