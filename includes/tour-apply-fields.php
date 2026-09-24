<?php
/**
 * Túra-specifikus jelentkezési mezők — közös a bejelentkezett tag, a vendég és a
 * tagfelvétellel induló jelentkezés űrlapja között (`public/tour-apply.php`).
 * A mezőnevek megegyeznek a feldolgozókéval: `actions/future-tour-apply.php`,
 * `actions/future-tour-apply-guest.php`, `actions/join-submit.php`.
 *
 * Bemenet:
 *   $fieldEnabled — closure(string $field): bool — be van-e kapcsolva a standard mező
 *   $customFields — a túra egyedi kérdései (future_tour_custom_fields sorai)
 */
?>
        <?php if ($fieldEnabled('departure_city')): ?>
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;">Honnan indulnál? <span style="color:var(--danger)">*</span></label>
          <input type="text" name="departure_city" required placeholder="pl. Budapest XIII. kerület" style="margin-top:6px;width:100%;">
          <small style="display:block;color:var(--text-muted);font-size:11.5px;margin-top:4px;">Budapest esetén a kerületet is add meg!</small>
        </div>
        <?php endif; ?>

        <?php if ($fieldEnabled('car_available')): ?>
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;">Tudsz autóval jönni?</label>
          <div style="display:flex;gap:16px;margin-top:6px;">
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;"><input type="radio" name="car_available" value="1" id="car-yes"> Igen</label>
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:normal;"><input type="radio" name="car_available" value="0" id="car-no" checked> Nem</label>
          </div>
        </div>
        <div id="passengers-row" style="margin-bottom:16px;display:none;">
          <div class="form-group">
            <label style="font-size:13px;font-weight:600;">Ha igen, hány hely van melletted?</label>
            <input type="number" name="passengers" min="0" max="10" value="0" style="width:80px;margin-top:6px;">
            <small style="display:block;color:var(--text-muted);font-size:11.5px;margin-top:4px;">Ha már megvan, hogy kivel utazol, akkor is a maximum számot írd be, és majd a megjegyzésnél jelezd, hogy ki az utasod.</small>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($fieldEnabled('sharing_room')): ?>
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;">Szükség esetén aludnál egy helyen mással?</label>
          <select name="sharing_room" style="margin-top:6px;width:100%;">
            <option value="same_gender">Igen, de csak azonos neművel</option>
            <option value="yes">Igen</option>
            <option value="no">Nem</option>
          </select>
        </div>
        <?php endif; ?>

        <?php if ($fieldEnabled('notes')): ?>
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;">Megjegyzések</label>
          <textarea name="notes" rows="3" placeholder="Egyéb megjegyzés, kérés…" style="margin-top:6px;"></textarea>
        </div>
        <?php endif; ?>

        <?php foreach ($customFields as $cf): ?>
        <div class="form-group" style="margin-bottom:16px;">
          <label style="font-size:13px;font-weight:600;"><?= e($cf['field_name']) ?></label>
          <?php if ($cf['field_type'] === 'textarea'): ?>
            <textarea name="custom_field_<?= (int)$cf['id'] ?>" rows="2" style="margin-top:6px;"></textarea>
          <?php elseif ($cf['field_type'] === 'checkbox'): ?>
            <label style="display:flex;align-items:center;gap:8px;margin-top:8px;font-weight:normal;cursor:pointer;">
              <input type="checkbox" name="custom_field_<?= (int)$cf['id'] ?>" value="1"> Igen
            </label>
          <?php elseif ($cf['field_type'] === 'select' && !empty($cf['field_options'])): ?>
            <select name="custom_field_<?= (int)$cf['id'] ?>" style="margin-top:6px;width:100%;">
              <option value="">— válassz —</option>
              <?php foreach (array_map('trim', explode(',', $cf['field_options'])) as $opt): ?>
                <?php if ($opt !== ''): ?><option value="<?= e($opt) ?>"><?= e($opt) ?></option><?php endif; ?>
              <?php endforeach; ?>
            </select>
          <?php elseif ($cf['field_type'] === 'number'): ?>
            <input type="number" name="custom_field_<?= (int)$cf['id'] ?>" style="margin-top:6px;">
          <?php else: ?>
            <input type="text" name="custom_field_<?= (int)$cf['id'] ?>" style="margin-top:6px;">
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
