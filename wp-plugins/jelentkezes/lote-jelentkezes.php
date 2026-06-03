<?php
/*
Plugin Name: LOTE Meghirdetett Túrák – Jelentkezés
Description: Leguán Osztag Természetjáró Egyesület meghirdetett túrák natív jelentkezési űrlapja. Shortcode: [lote_jelentkezes] vagy [lote_jelentkezes id="5"]
Version:     2.2
Author:      Leguán Osztag Természetjáró Egyesület
*/

defined('ABSPATH') || exit;

if (!defined('LOTE_FT_APP_BASE')) {
    define('LOTE_FT_APP_BASE', '/lizzardmembers');
}

define('LOTE_FT_API_TOURS',     LOTE_FT_APP_BASE . '/api/future-tours.php');
define('LOTE_FT_API_AUTH',      LOTE_FT_APP_BASE . '/api/member-auth.php');
define('LOTE_FT_API_CSRF',      LOTE_FT_APP_BASE . '/api/csrf-token.php');
define('LOTE_FT_API_SUBMIT',    LOTE_FT_APP_BASE . '/api/submit-application.php');
define('LOTE_FT_API_EMAIL',     LOTE_FT_APP_BASE . '/api/check-member-email.php');
define('LOTE_FT_API_JOIN_TOUR', LOTE_FT_APP_BASE . '/api/join-tour-submit.php');
define('LOTE_FT_CACHE_KEY',     'lote_ft_tours_v3');
define('LOTE_FT_CACHE_TTL',     120);

function lote_ft_fetch(): ?array
{
    $cached = get_transient(LOTE_FT_CACHE_KEY);
    if ($cached !== false) return $cached;

    $url      = home_url(LOTE_FT_API_TOURS);
    $response = wp_remote_get($url, [
        'timeout' => 10,
        'headers' => ['X-Lote-Key' => defined('LOTE_FT_API_KEY') ? LOTE_FT_API_KEY : ''],
    ]);

    if (is_wp_error($response)) return null;

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data) || isset($data['error'])) return null;

    set_transient(LOTE_FT_CACHE_KEY, $data, LOTE_FT_CACHE_TTL);
    return $data;
}

add_shortcode('lote_jelentkezes', function (array $atts): string {
    wp_enqueue_style('lote-jelentkezes', plugins_url('style.css', __FILE__), [], '2.3');

    $atts = shortcode_atts(['id' => ''], $atts);
    $data = lote_ft_fetch();
    $uid  = 'lote-ft-' . substr(md5(uniqid()), 0, 8);

    $authUrl     = esc_url(home_url(LOTE_FT_API_AUTH));
    $csrfUrl     = esc_url(home_url(LOTE_FT_API_CSRF));
    $submitUrl   = esc_url(home_url(LOTE_FT_API_SUBMIT));
    $emailUrl    = esc_url(home_url(LOTE_FT_API_EMAIL));
    $joinTourUrl = esc_url(home_url(LOTE_FT_API_JOIN_TOUR));

    if (!$data) {
        return '<p class="lote-ft-error">A jelentkezési űrlap jelenleg nem érhető el. Kérjük, próbáld újra később.</p>';
    }

    $tours = $data['tours'] ?? [];

    if (!empty($atts['id'])) {
        $filterId = (int)$atts['id'];
        $tours    = array_values(array_filter($tours, fn($t) => (int)$t['id'] === $filterId));
    }

    if (empty($tours)) {
        return '<p class="lote-ft-empty">Jelenleg nincs elérhető nyitott túra.</p>';
    }

    $tourIds = array_column($tours, 'id');

    ob_start();
    ?>
    <div class="lote-ft-wrap" id="<?= esc_attr($uid) ?>">

      <!-- ===== Login sáv ===== -->
      <div class="lote-ft-login-panel" id="<?= esc_attr($uid) ?>-login-panel">

        <div id="<?= esc_attr($uid) ?>-teaser" class="lote-ft-teaser" hidden>
          <div class="lote-ft-teaser-text">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span class="lote-ft-panel-text" style="font-size: 14px;">Ha már tag vagy jelentkezz be:</span>
          </div>
          <button type="button" class="lote-ft-login-toggle" id="<?= esc_attr($uid) ?>-toggle">Bejelentkezés</button>
        </div>

        <div id="<?= esc_attr($uid) ?>-form-wrap" class="lote-ft-form-wrap" hidden>
          <form id="<?= esc_attr($uid) ?>-login-form" class="lote-ft-login-form" novalidate>
            <div class="lote-ft-login-fields">
              <div class="lote-ft-field">
                <label for="<?= esc_attr($uid) ?>-u" class="lote-ft-panel-text">Felhasználónév vagy e-mail</label>
                <input type="text" id="<?= esc_attr($uid) ?>-u" name="login" autocomplete="username" placeholder="felhasznalonev" required>
              </div>
              <div class="lote-ft-field">
                <label for="<?= esc_attr($uid) ?>-p" class="lote-ft-panel-text">Jelszó</label>
                <input type="password" id="<?= esc_attr($uid) ?>-p" name="password" autocomplete="current-password" placeholder="••••••••" required>
              </div>
            </div>
            <div class="lote-ft-login-row">
              <button type="submit" class="lote-ft-btn lote-ft-btn--gold" id="<?= esc_attr($uid) ?>-submit">Bejelentkezés</button>
              <button type="button" class="lote-ft-cancel lote-ft-panel-text" id="<?= esc_attr($uid) ?>-cancel">Mégse</button>
            </div>
            <div class="lote-ft-error" id="<?= esc_attr($uid) ?>-err" hidden></div>
          </form>
        </div>

        <div id="<?= esc_attr($uid) ?>-loggedin" class="lote-ft-loggedin" hidden>
          <div style="display:flex;align-items:center;gap:8px;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span class="lote-ft-panel-text">Bejelentkezve: <strong id="<?= esc_attr($uid) ?>-name"></strong></span>
          </div>
          <button type="button" class="lote-ft-logout-btn" id="<?= esc_attr($uid) ?>-logout">Kijelentkezés</button>
        </div>

      </div>

      <!-- ===== Natív jelentkezési űrlapok ===== -->
      <?php foreach ($tours as $t):
        $tid             = (int)$t['id'];
        $customFields    = $t['custom_fields'] ?? [];
        $bId             = esc_attr($uid . '-block-' . $tid);
        $disabledFlds    = $t['disabled_standard_fields'] ?? [];
        $fldOn           = fn(string $f): bool => !in_array($f, $disabledFlds, true);
        $requiresMember  = !empty($t['requires_membership']);
      ?>
      <div class="lote-ft-form-block" id="<?= $bId ?>">

        <!-- Jelentkezés toggle gomb -->
        <div class="lote-ft-apply-toggle" id="<?= esc_attr($uid) ?>-toggle-wrap-<?= $tid ?>">
          <?php if ($requiresMember): ?>
          <?php endif; ?>
          <?php if ($t['participation_fee'] !== null): ?>
          <div class="lote-ft-fee-card">
            <span class="lote-ft-fee-badge" id="<?= esc_attr($uid) ?>-fee-badge-<?= $tid ?>" hidden></span>
            <span class="lote-ft-fee-label">Részvételi díj</span>
            <div class="lote-ft-fee-prices">
              <span class="lote-ft-fee-base" id="<?= esc_attr($uid) ?>-fee-base-<?= $tid ?>"><?= number_format((float)$t['participation_fee'], 0, ',', ' ') ?> Ft</span>
              <span class="lote-ft-fee-arrow" id="<?= esc_attr($uid) ?>-fee-arrow-<?= $tid ?>" hidden>→</span>
              <span class="lote-ft-fee-disc" id="<?= esc_attr($uid) ?>-fee-disc-<?= $tid ?>" hidden></span>
            </div>
          </div>
          <?php endif; ?>
          <button type="button" class="lote-ft-apply-btn" id="<?= esc_attr($uid) ?>-apply-toggle-<?= $tid ?>">Jelentkezés</button>
        </div>

        <!-- Form tartalom -->
        <div class="lote-ft-form-inner" id="<?= esc_attr($uid) ?>-inner-<?= $tid ?>" hidden>

          <?php
            $maxAtt   = (int)$t['max_attendees'];
            $spotsL   = (int)$t['spots_left'];
            $confCnt  = (int)$t['confirmed_count'];
            $waitCnt  = (int)$t['waitlist_count'];
            $pct      = $maxAtt > 0 ? min(100, (int)round($confCnt / $maxAtt * 100)) : 100;
            $spotsClass = $spotsL <= 0 ? 'full' : ($spotsL <= 3 ? 'low' : 'ok');
          ?>
          <div class="lote-ft-capacity">
            <div class="lote-ft-capacity-row">
              <div class="lote-ft-cap-stat">
                <span class="lote-ft-cap-label">Létszámlimit</span>
                <span class="lote-ft-cap-num"><?= $maxAtt ?> fő</span>
              </div>
              <div class="lote-ft-cap-stat">
                <span class="lote-ft-cap-label">Szabad helyek</span>
                <span class="lote-ft-cap-num lote-ft-spots-<?= $spotsClass ?>">
                  <?= $spotsL > 0 ? $spotsL . ' fő' : 'Betelt' ?>
                </span>
              </div>
              <?php if ($waitCnt > 0): ?>
              <div class="lote-ft-cap-stat">
                <span class="lote-ft-cap-label">Várólistán</span>
                <span class="lote-ft-cap-num lote-ft-spots-wait"><?= $waitCnt ?> fő</span>
              </div>
              <?php endif; ?>
            </div>
            <div class="lote-ft-cap-track">
              <div class="lote-ft-cap-fill lote-ft-cap-fill--<?= $spotsClass ?>" style="width:<?= $pct ?>%"></div>
            </div>
          </div>

          <!-- ===== JOIN SECTION (csak tagoknak + nem bejelentkezett) ===== -->
          <div class="lote-ft-join-section" id="<?= esc_attr($uid) ?>-join-<?= $tid ?>" hidden>

            <!-- Notice -->
            <div style="background:#fffbeb;border:1px solid #f59e0b;border-radius:8px;padding:14px 16px;margin-bottom:14px;font-size:13px;color:#92400e;line-height:1.55;">
              <strong>Ez a túra csak az egyesület tagjai számára érhető el.</strong><br>
              <span style="color:#b45309;font-size:12.5px;line-height:1.1;">Ha már tag vagy, lépj be az alábbi gombbal. Ha még nem vagy tag, az alábbi űrlappal egyszerre kérheted felvételedet és jelentkezhetsz a túrára. ⚠ <strong>Fontos:</strong> A tagság csak az éves tagdíj befizetésével válik érvényessé. Az éves tagdíj összege: <strong>5 000 Ft</strong></span>
            </div>

            <!-- Mini login sáv -->
            <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
              <span style="font-size:13px;color:#6b7280;">Ha már tag vagy, jelentkezz be:</span>
              <button type="button" id="<?= esc_attr($uid) ?>-join-login-<?= $tid ?>" style="background:#29776f;color:#fff;border:none;border-radius:6px;padding:6px 14px;font-size:13px;cursor:pointer;font-weight:600;">Bejelentkezés</button>
            </div>

            <!-- Elválasztó -->
            <div style="display:flex;align-items:center;gap:10px;margin:20px 0;">
              <hr style="flex:1;border:none;border-top:1px solid #e5e7eb;margin:0;">
              <span style="font-size:13px;color:#9ca3af;white-space:nowrap;font-weight:700;text-transform:uppercase;letter-spacing:.06em;border-bottom:2px solid #29776F;">Tagfelvételi kérelemmel jelentkezés:</span>
              <hr style="flex:1;border:none;border-top:1px solid #e5e7eb;margin:0;">
            </div>

            <!-- Személyes adatok -->
            <div class="lote-ft-row-2">
              <div class="lote-ft-field-wrap">
                <label class="lote-ft-label">Vezetéknév <span class="lote-ft-req">*</span></label>
                <input class="lote-ft-input" type="text" name="lastname">
              </div>
              <div class="lote-ft-field-wrap">
                <label class="lote-ft-label">Keresztnév <span class="lote-ft-req">*</span></label>
                <input class="lote-ft-input" type="text" name="firstname">
              </div>
              <div class="lote-ft-field-wrap">
                <label class="lote-ft-label">E-mail cím <span class="lote-ft-req">*</span></label>
                <input class="lote-ft-input" type="email" name="email">
              </div>
              <div class="lote-ft-field-wrap">
                <label class="lote-ft-label">Telefonszám</label>
                <input class="lote-ft-input" type="tel" name="phone">
              </div>
              <div class="lote-ft-field-wrap">
                <label class="lote-ft-label">Születési dátum <span class="lote-ft-req">*</span></label>
                <input class="lote-ft-input" type="date" name="dateofbirth">
              </div>
              <div class="lote-ft-field-wrap">
                <label class="lote-ft-label">Irányítószám <span class="lote-ft-req">*</span></label>
                <input class="lote-ft-input" type="text" name="zipcode">
              </div>
              <div class="lote-ft-field-wrap">
                <label class="lote-ft-label">Város <span class="lote-ft-req">*</span></label>
                <input class="lote-ft-input" type="text" name="city">
              </div>
              <div class="lote-ft-field-wrap">
                <label class="lote-ft-label">Lakcím <span class="lote-ft-req">*</span></label>
                <input class="lote-ft-input" type="text" name="address">
              </div>
            </div>
            <div class="lote-ft-field-wrap">
              <label class="lote-ft-label">Megjegyzés / Motiváció</label>
              <textarea class="lote-ft-textarea" name="message" rows="2"></textarea>
            </div>

            <!-- Túra-specifikus adatok -->
            <div style="display:flex;align-items:center;gap:10px;margin:20px 0 16px;">
              <hr style="flex:1;border:none;border-top:1px solid #e5e7eb;margin:0;">
              <span style="font-size:13px;color:#9ca3af;white-space:nowrap;font-weight:700;text-transform:uppercase;letter-spacing:.06em;border-bottom:2px solid #29776F;">Túra-specifikus adatok</span>
              <hr style="flex:1;border:none;border-top:1px solid #e5e7eb;margin:0;">
            </div>

            <?php if ($fldOn('departure_city')): ?>
            <div class="lote-ft-field-wrap">
              <label class="lote-ft-label">Honnan indulnál? <span class="lote-ft-req">*</span></label>
              <input class="lote-ft-input" type="text" name="departure_city" placeholder="pl. Budapest XIII. kerület">
              <p class="lote-ft-hint">Budapest esetén a kerületet is add meg!</p>
            </div>
            <?php endif; ?>

            <?php if ($fldOn('car_available')): ?>
            <div class="lote-ft-field-wrap">
              <label class="lote-ft-label">Tudsz autóval jönni?</label>
              <div class="lote-ft-radios">
                <label class="lote-ft-radio-wrap">
                  <input type="radio" name="j_car_<?= $tid ?>" value="1"> Igen
                </label>
                <label class="lote-ft-radio-wrap">
                  <input type="radio" name="j_car_<?= $tid ?>" value="0" checked> Nem
                </label>
              </div>
            </div>
            <div class="lote-ft-field-wrap" id="<?= esc_attr($uid) ?>-jpass-<?= $tid ?>" hidden>
              <label class="lote-ft-label">Hány hely van melletted?</label>
              <input class="lote-ft-input lote-ft-input-narrow" type="number" name="passengers" min="0" max="10" value="0">
              <p class="lote-ft-hint">Ha már megvan, hogy kivel utazol, akkor is a maximum számot írd be, és majd a megjegyzésnél jelezd, hogy ki az utasod.</p>
            </div>
            <?php endif; ?>

            <?php if ($fldOn('sharing_room')): ?>
            <div class="lote-ft-field-wrap">
              <label class="lote-ft-label">Szükség esetén aludnál egy helyen mással?</label>
              <select class="lote-ft-select" name="sharing_room">
                <option value="same_gender">Igen, de csak azonos neművel</option>
                <option value="yes">Igen</option>
                <option value="no">Nem</option>
              </select>
            </div>
            <?php endif; ?>

            <?php if ($fldOn('notes')): ?>
            <div class="lote-ft-field-wrap">
              <label class="lote-ft-label">Megjegyzések a túrával kapcsolatban</label>
              <textarea class="lote-ft-textarea" name="notes" rows="2" placeholder="Egyéb megjegyzés, kérés…"></textarea>
            </div>
            <?php endif; ?>

            <?php foreach ($customFields as $cf): ?>
            <div class="lote-ft-field-wrap">
              <label class="lote-ft-label"><?= esc_html($cf['field_name']) ?></label>
              <?php if ($cf['field_type'] === 'textarea'): ?>
                <textarea class="lote-ft-textarea" name="j_custom_field_<?= (int)$cf['id'] ?>" rows="2"></textarea>
              <?php elseif ($cf['field_type'] === 'checkbox'): ?>
                <label class="lote-ft-checkbox-wrap">
                  <input type="checkbox" name="j_custom_field_<?= (int)$cf['id'] ?>" value="1"> Igen
                </label>
              <?php elseif ($cf['field_type'] === 'select' && !empty($cf['field_options'])): ?>
                <select class="lote-ft-select" name="j_custom_field_<?= (int)$cf['id'] ?>">
                  <option value="">— válassz —</option>
                  <?php foreach (array_filter(array_map('trim', explode(',', $cf['field_options']))) as $opt): ?>
                    <option value="<?= esc_attr($opt) ?>"><?= esc_html($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php elseif ($cf['field_type'] === 'number'): ?>
                <input class="lote-ft-input lote-ft-input-narrow" type="number" name="j_custom_field_<?= (int)$cf['id'] ?>">
              <?php else: ?>
                <input class="lote-ft-input" type="text" name="j_custom_field_<?= (int)$cf['id'] ?>">
              <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <!-- Hozzájárulások -->
            <div class="lote-ft-consents">
              <label class="lote-ft-consent-row">
                <input type="checkbox" name="consent_email" value="1">
                <span>Hozzájárulok, hogy e-mail-címem az események szervezésekor a levelezésekben nyilvánosan megjelenjen.</span>
              </label>
              <label class="lote-ft-consent-row">
                <input type="checkbox" name="consent_photo" value="1">
                <span>Hozzájárulok, hogy az egyesület eseményein rólam készült fotók a L.O.T.E. weboldalán és social-media felületeken megjelenjenek.</span>
              </label>
              <label class="lote-ft-consent-row lote-ft-consent-row--required">
                <input type="checkbox" name="consent_rules" value="1">
                <span>Elolvastam és elfogadom az <a href="https://www.lizzard.hu/wp-content/uploads/2018/05/gdpr_adatvedelem_lote_20150521.pdf" target="_blank" rel="noopener noreferrer">Adatvédelmi Tájékoztatóban</a>, az Alapszabályban és a Részvételi feltételekben foglaltakat. <strong style="color:#d97706;">— Kötelező</strong></span>
              </label>
            </div>

            <div class="lote-ft-submit-wrap">
              <button type="button" class="lote-ft-submit-btn" id="<?= esc_attr($uid) ?>-join-submit-<?= $tid ?>">
                Tagságra és túrára jelentkezés
              </button>
            </div>
            <div class="lote-ft-inline-error" id="<?= esc_attr($uid) ?>-join-err-<?= $tid ?>" hidden></div>

          </div><!-- /join-section -->

          <!-- ===== REGULAR SECTION (vendég / bejelentkezett tag) ===== -->
          <div id="<?= esc_attr($uid) ?>-regular-<?= $tid ?>">

            <p class="lote-ft-form-intro">Az alábbi űrlapon jelentkezhetsz a túrára. A jelentkezésedet az adminisztrátor hagyja jóvá, erről e-mailben értesítünk.</p>

            <!-- Vendég mezők (bejelentkezés előtt látható) -->
            <div class="lote-ft-guest-section" id="<?= esc_attr($uid) ?>-guest-<?= $tid ?>">
              <div class="lote-ft-row-2">
                <div class="lote-ft-field-wrap">
                  <label class="lote-ft-label">Teljes név <span class="lote-ft-req">*</span></label>
                  <input class="lote-ft-input" type="text" name="guest_name" placeholder="pl. Kovács János">
                </div>
                <div class="lote-ft-field-wrap">
                  <label class="lote-ft-label">E-mail cím <span class="lote-ft-req">*</span></label>
                  <input class="lote-ft-input" type="email" name="guest_email" placeholder="pelda@email.hu">
                </div>
              </div>
              <div class="lote-ft-field-wrap">
                <label class="lote-ft-label">Telefonszám</label>
                <input class="lote-ft-input" type="tel" name="guest_phone" placeholder="+36 30 123 4567">
              </div>
            </div>

            <!-- Tag badge (bejelentkezés után látható) -->
            <div class="lote-ft-member-badge" id="<?= esc_attr($uid) ?>-member-<?= $tid ?>" hidden>
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              <div>
                <strong class="lote-ft-m-name"></strong>
                <div class="lote-ft-m-email"></div>
              </div>
            </div>

            <hr class="lote-ft-divider">

            <?php if ($fldOn('departure_city')): ?>
            <div class="lote-ft-field-wrap">
              <label class="lote-ft-label">Honnan indulnál? <span class="lote-ft-req">*</span></label>
              <input class="lote-ft-input" type="text" name="departure_city" placeholder="pl. Budapest XIII. kerület" required>
              <p class="lote-ft-hint">Budapest esetén a kerületet is add meg!</p>
            </div>
            <?php endif; ?>

            <?php if ($fldOn('car_available')): ?>
            <div class="lote-ft-field-wrap">
              <label class="lote-ft-label">Tudsz autóval jönni?</label>
              <div class="lote-ft-radios">
                <label class="lote-ft-radio-wrap">
                  <input type="radio" name="car_<?= $tid ?>" value="1"> Igen
                </label>
                <label class="lote-ft-radio-wrap">
                  <input type="radio" name="car_<?= $tid ?>" value="0" checked> Nem
                </label>
              </div>
            </div>
            <div class="lote-ft-field-wrap" id="<?= esc_attr($uid) ?>-pass-<?= $tid ?>" hidden>
              <label class="lote-ft-label">Hány hely van melletted?</label>
              <input class="lote-ft-input lote-ft-input-narrow" type="number" name="passengers" min="0" max="10" value="0">
              <p class="lote-ft-hint">Ha már megvan, hogy kivel utazol, akkor is a maximum számot írd be, és majd a megjegyzésnél jelezd, hogy ki az utasod.</p>
            </div>
            <?php endif; ?>

            <?php if ($fldOn('sharing_room')): ?>
            <div class="lote-ft-field-wrap">
              <label class="lote-ft-label">Szükség esetén aludnál egy helyen mással?</label>
              <select class="lote-ft-select" name="sharing_room">
                <option value="same_gender">Igen, de csak azonos neművel</option>
                <option value="yes">Igen</option>
                <option value="no">Nem</option>
              </select>
            </div>
            <?php endif; ?>

            <?php if ($fldOn('notes')): ?>
            <div class="lote-ft-field-wrap">
              <label class="lote-ft-label">Megjegyzések</label>
              <textarea class="lote-ft-textarea" name="notes" rows="3" placeholder="Egyéb megjegyzés, kérés…"></textarea>
            </div>
            <?php endif; ?>

            <?php foreach ($customFields as $cf): ?>
            <div class="lote-ft-field-wrap">
              <label class="lote-ft-label"><?= esc_html($cf['field_name']) ?></label>
              <?php if ($cf['field_type'] === 'textarea'): ?>
                <textarea class="lote-ft-textarea" name="custom_field_<?= (int)$cf['id'] ?>" rows="2"></textarea>
              <?php elseif ($cf['field_type'] === 'checkbox'): ?>
                <label class="lote-ft-checkbox-wrap">
                  <input type="checkbox" name="custom_field_<?= (int)$cf['id'] ?>" value="1"> Igen
                </label>
              <?php elseif ($cf['field_type'] === 'select' && !empty($cf['field_options'])): ?>
                <select class="lote-ft-select" name="custom_field_<?= (int)$cf['id'] ?>">
                  <option value="">— válassz —</option>
                  <?php foreach (array_filter(array_map('trim', explode(',', $cf['field_options']))) as $opt): ?>
                    <option value="<?= esc_attr($opt) ?>"><?= esc_html($opt) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php elseif ($cf['field_type'] === 'number'): ?>
                <input class="lote-ft-input lote-ft-input-narrow" type="number" name="custom_field_<?= (int)$cf['id'] ?>">
              <?php else: ?>
                <input class="lote-ft-input" type="text" name="custom_field_<?= (int)$cf['id'] ?>">
              <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <div class="lote-ft-submit-wrap">
              <button type="button" class="lote-ft-submit-btn" id="<?= esc_attr($uid) ?>-submit-<?= $tid ?>">
                Jelentkezés elküldése
              </button>
            </div>
            <div class="lote-ft-inline-error" id="<?= esc_attr($uid) ?>-form-err-<?= $tid ?>" hidden></div>

          </div><!-- /regular-section -->

        </div><!-- /form-inner -->

        <!-- Siker állapot -->
        <div class="lote-ft-success" id="<?= esc_attr($uid) ?>-success-<?= $tid ?>" hidden>
          <div class="lote-ft-success-ico">✅</div>
          <div class="lote-ft-success-title">Jelentkezés elküldve!</div>
          <div class="lote-ft-success-text">Köszönjük a jelentkezésedet! Hamarosan visszajelzünk e-mailben.<br>Az adminisztrátor jóváhagyása után véglegesítjük a részvételed.</div>
        </div>

      </div>
      <?php endforeach; ?>

    </div>

    <script>
    (function () {
      var uid          = <?= json_encode($uid) ?>;
      var authUrl      = <?= json_encode($authUrl) ?>;
      var csrfUrl      = <?= json_encode($csrfUrl) ?>;
      var submitUrl    = <?= json_encode($submitUrl) ?>;
      var emailUrl     = <?= json_encode($emailUrl) ?>;
      var joinTourUrl  = <?= json_encode($joinTourUrl) ?>;
      var tourIds      = <?= json_encode(array_map('intval', $tourIds)) ?>;
      var tourFees     = <?= json_encode(array_combine(
            array_map('intval', $tourIds),
            array_map(fn($t) => $t['participation_fee'], $tours)
        )) ?>;
      var tourDisabledFields = <?= json_encode(array_combine(
            array_map('intval', $tourIds),
            array_map(fn($t) => $t['disabled_standard_fields'] ?? [], $tours)
        )) ?>;
      var tourRequiresMembership = <?= json_encode(array_combine(
            array_map('intval', $tourIds),
            array_map(fn($t) => !empty($t['requires_membership']), $tours)
        )) ?>;

      var wrap        = document.getElementById(uid);
      var teaser      = document.getElementById(uid + '-teaser');
      var formWrap    = document.getElementById(uid + '-form-wrap');
      var loginForm   = document.getElementById(uid + '-login-form');
      var toggle      = document.getElementById(uid + '-toggle');
      var cancel      = document.getElementById(uid + '-cancel');
      var errBox      = document.getElementById(uid + '-err');
      var loggedinEl  = document.getElementById(uid + '-loggedin');
      var nameEl      = document.getElementById(uid + '-name');
      var loginSubmit = document.getElementById(uid + '-submit');

      var loggedInUser = null;
      var csrfToken    = null;

      // CSRF token és auth állapot párhuzamos lekérdezése
      fetch(csrfUrl, { credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(d) { csrfToken = d.token || null; })
        .catch(function() {});

      fetch(authUrl + '?action=status', { credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(d) {
          if (d.logged_in) {
            loggedInUser = { firstname: d.firstname, lastname: d.lastname, email: d.email, discount: d.discount || 0 };
            showLoggedIn(d.firstname, d.lastname);
            updateFormModes();
            updateAllFeeDisplays(d.discount || 0);
          } else {
            showTeaser();
            updateFormModes();
          }
        })
        .catch(function() { showTeaser(); updateFormModes(); });

      // ---- Login panel ----

      function showLoggedIn(firstname, lastname) {
        teaser.hidden            = true;
        teaser.style.display     = 'none';
        formWrap.hidden          = true;
        loggedinEl.hidden        = false;
        loggedinEl.style.display = 'flex';
        nameEl.textContent       = (lastname + ' ' + firstname).trim();
      }

      function showTeaser() {
        teaser.hidden           = false;
        teaser.style.display    = '';
        formWrap.hidden         = true;
        loggedinEl.hidden       = true;
        loggedinEl.style.display = 'none';
        errBox.hidden           = true;
        loginForm.reset();
        loginSubmit.disabled    = false;
        loginSubmit.textContent = 'Bejelentkezés';
      }

      function showLoginForm() {
        teaser.hidden     = true;
        formWrap.hidden   = false;
        loggedinEl.hidden = true;
        loginForm.querySelector('input[name="login"]').focus();
      }

      toggle.addEventListener('click', showLoginForm);
      cancel.addEventListener('click', showTeaser);

      loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        errBox.hidden           = true;
        loginSubmit.disabled    = true;
        loginSubmit.textContent = 'Bejelentkezés…';

        fetch(authUrl, {
          method:      'POST',
          credentials: 'include',
          headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
          body:        new URLSearchParams({
            login:    loginForm.querySelector('[name="login"]').value,
            password: loginForm.querySelector('[name="password"]').value,
          }).toString(),
        })
          .then(function(r) { return r.json(); })
          .then(function(d) {
            if (d.success) {
              loggedInUser = { firstname: d.firstname, lastname: d.lastname, email: d.email, discount: d.discount || 0 };
              showLoggedIn(d.firstname, d.lastname);
              updateFormModes();
              updateAllFeeDisplays(d.discount || 0);
            } else {
              errBox.textContent      = d.error || 'Hiba történt.';
              errBox.hidden           = false;
              loginSubmit.disabled    = false;
              loginSubmit.textContent = 'Bejelentkezés';
            }
          })
          .catch(function() {
            errBox.textContent      = 'Hálózati hiba. Kérjük próbáld újra.';
            errBox.hidden           = false;
            loginSubmit.disabled    = false;
            loginSubmit.textContent = 'Bejelentkezés';
          });
      });

      // ---- Form mód váltás (vendég / tag / join) ----

      function updateFormModes() {
        tourIds.forEach(function(tid) {
          var joinSec    = document.getElementById(uid + '-join-' + tid);
          var regularSec = document.getElementById(uid + '-regular-' + tid);
          var gSection   = document.getElementById(uid + '-guest-' + tid);
          var mSection   = document.getElementById(uid + '-member-' + tid);

          var needsJoin = tourRequiresMembership[tid] && !loggedInUser;

          if (joinSec)    joinSec.hidden    = !needsJoin;
          if (regularSec) regularSec.hidden = needsJoin;

          if (!needsJoin && gSection && mSection) {
            if (loggedInUser) {
              gSection.hidden = true;
              mSection.hidden = false;
              var nEl = mSection.querySelector('.lote-ft-m-name');
              var eEl = mSection.querySelector('.lote-ft-m-email');
              if (nEl) nEl.textContent = (loggedInUser.lastname + ' ' + loggedInUser.firstname).trim();
              if (eEl) eEl.textContent = loggedInUser.email;
            } else {
              gSection.hidden = false;
              mSection.hidden = true;
            }
          }
        });
      }

      // ---- Per-túra toggle + autó toggle + beküldés ----

      tourIds.forEach(function(tid) {
        var block = document.getElementById(uid + '-block-' + tid);
        if (!block) return;

        // Jelentkezés toggle gomb
        var applyToggle = document.getElementById(uid + '-apply-toggle-' + tid);
        var inner       = document.getElementById(uid + '-inner-' + tid);
        if (applyToggle && inner) {
          applyToggle.addEventListener('click', function() {
            var opening = inner.hidden;
            inner.hidden = !opening;
            applyToggle.textContent = opening ? 'Mégse' : 'Jelentkezés';
            applyToggle.classList.toggle('lote-ft-apply-btn--open', opening);
          });
        }

        // Autó radio toggle – regular section
        var regularSec = document.getElementById(uid + '-regular-' + tid);
        if (regularSec) {
          regularSec.querySelectorAll('input[name="car_' + tid + '"]').forEach(function(r) {
            r.addEventListener('change', function() {
              var passRow = document.getElementById(uid + '-pass-' + tid);
              if (passRow) passRow.hidden = (r.value !== '1');
            });
          });
        }

        // Autó radio toggle – join section
        var joinSec = document.getElementById(uid + '-join-' + tid);
        if (joinSec) {
          joinSec.querySelectorAll('input[name="j_car_' + tid + '"]').forEach(function(r) {
            r.addEventListener('change', function() {
              var passRow = document.getElementById(uid + '-jpass-' + tid);
              if (passRow) passRow.hidden = (r.value !== '1');
            });
          });
        }

        // Regular submit gomb
        var submitBtn = document.getElementById(uid + '-submit-' + tid);
        if (submitBtn) {
          submitBtn.addEventListener('click', function() { handleSubmit(tid); });
        }

        // Join submit gomb
        var joinSubmitBtn = document.getElementById(uid + '-join-submit-' + tid);
        if (joinSubmitBtn) {
          joinSubmitBtn.addEventListener('click', function() { handleJoinSubmit(tid); });
        }

        // Mini login gomb a join sectionben
        var joinLoginBtn = document.getElementById(uid + '-join-login-' + tid);
        if (joinLoginBtn) {
          joinLoginBtn.addEventListener('click', showLoginForm);
        }
      });

      // ---- Regular beküldés ----

      function handleSubmit(tid) {
        var block     = document.getElementById(uid + '-block-' + tid);
        var inner     = document.getElementById(uid + '-inner-' + tid);
        var successEl = document.getElementById(uid + '-success-' + tid);
        var submitBtn = document.getElementById(uid + '-submit-' + tid);
        var errEl     = document.getElementById(uid + '-form-err-' + tid);

        hideErr(errEl);

        var data           = { tour_id: String(tid), csrf_token: csrfToken || '' };
        var disabledFields = tourDisabledFields[tid] || [];

        if (!loggedInUser) {
          var nameIn  = block.querySelector('[name="guest_name"]');
          var emailIn = block.querySelector('[name="guest_email"]');
          var phoneIn = block.querySelector('[name="guest_phone"]');
          data.guest_name  = nameIn  ? nameIn.value.trim()  : '';
          data.guest_email = emailIn ? emailIn.value.trim() : '';
          data.guest_phone = phoneIn ? phoneIn.value.trim() : '';

          if (!data.guest_name)  { showErr(errEl, 'A név megadása kötelező.'); return; }
          if (!data.guest_email) { showErr(errEl, 'Az e-mail cím megadása kötelező.'); return; }
        }

        var regularSec = document.getElementById(uid + '-regular-' + tid);
        var deptIn = regularSec ? regularSec.querySelector('[name="departure_city"]') : null;
        data.departure_city = deptIn ? deptIn.value.trim() : '';
        if (disabledFields.indexOf('departure_city') === -1 && !data.departure_city) {
          showErr(errEl, 'Az indulási helyszín megadása kötelező.'); return;
        }

        var carIn  = regularSec ? regularSec.querySelector('input[name="car_' + tid + '"]:checked') : null;
        data.car_available = carIn ? carIn.value : '0';
        var passIn = regularSec ? regularSec.querySelector('[name="passengers"]') : null;
        data.passengers    = passIn ? passIn.value : '0';
        var roomIn = regularSec ? regularSec.querySelector('[name="sharing_room"]') : null;
        data.sharing_room  = roomIn ? roomIn.value : 'same_gender';
        var notesIn = regularSec ? regularSec.querySelector('[name="notes"]') : null;
        data.notes = notesIn ? notesIn.value : '';

        if (regularSec) {
          regularSec.querySelectorAll('[name^="custom_field_"]').forEach(function(inp) {
            data[inp.name] = (inp.type === 'checkbox') ? (inp.checked ? '1' : '') : inp.value;
          });
        }

        submitBtn.disabled    = true;
        submitBtn.textContent = 'Küldés…';

        function doPost() {
          fetch(submitUrl, {
            method:      'POST',
            credentials: 'include',
            headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
            body:        new URLSearchParams(data).toString(),
          })
            .then(function(r) { return r.json(); })
            .then(function(d) {
              if (d.success) {
                var titleEl = successEl.querySelector('.lote-ft-success-title');
                var textEl  = successEl.querySelector('.lote-ft-success-text');
                if (d.status === 'confirmed') {
                  if (titleEl) titleEl.textContent = 'Sikeresen jelentkeztél!';
                  if (textEl)  textEl.textContent  = 'Hamarosan e-mail értesítőt kapsz. Kérjük, a részvételi díjat 14 napon belül utald el.';
                } else if (d.status === 'waitlist') {
                  if (titleEl) titleEl.textContent = 'Felkerültél a várólistára!';
                  if (textEl)  textEl.textContent  = 'Ha felszabadul egy hely, e-mailben értesítünk.';
                }
                inner.hidden     = true;
                successEl.hidden = false;
                var toggleWrap = document.getElementById(uid + '-toggle-wrap-' + tid);
                if (toggleWrap) toggleWrap.hidden = true;
              } else {
                submitBtn.disabled    = false;
                submitBtn.textContent = 'Jelentkezés elküldése';
                showErr(errEl, d.error || 'Hiba történt. Kérjük próbáld újra.');
              }
            })
            .catch(function() {
              submitBtn.disabled    = false;
              submitBtn.textContent = 'Jelentkezés elküldése';
              showErr(errEl, 'Hálózati hiba. Kérjük próbáld újra.');
            });
        }

        if (!loggedInUser && data.guest_email) {
          fetch(emailUrl + '?email=' + encodeURIComponent(data.guest_email), { credentials: 'include' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
              if (d.registered) {
                submitBtn.disabled    = false;
                submitBtn.textContent = 'Jelentkezés elküldése';
                showErr(errEl, 'Ezzel az e-mail címmel már van regisztrált felhasználó – lépj be a jelentkezéshez!');
              } else {
                doPost();
              }
            })
            .catch(function() { doPost(); });
        } else {
          doPost();
        }
      }

      // ---- Join+tour beküldés (csak tagoknak túra, nem bejelentkezett) ----

      function handleJoinSubmit(tid) {
        var joinSec   = document.getElementById(uid + '-join-' + tid);
        var inner     = document.getElementById(uid + '-inner-' + tid);
        var successEl = document.getElementById(uid + '-success-' + tid);
        var submitBtn = document.getElementById(uid + '-join-submit-' + tid);
        var errEl     = document.getElementById(uid + '-join-err-' + tid);

        hideErr(errEl);

        function getJoinVal(name) {
          var el = joinSec ? joinSec.querySelector('[name="' + name + '"]') : null;
          return el ? el.value.trim() : '';
        }

        var lastname    = getJoinVal('lastname');
        var firstname   = getJoinVal('firstname');
        var email       = getJoinVal('email');
        var dateofbirth = getJoinVal('dateofbirth');
        var zipcode     = getJoinVal('zipcode');
        var city        = getJoinVal('city');
        var address     = getJoinVal('address');
        var consentRulesEl = joinSec ? joinSec.querySelector('[name="consent_rules"]') : null;

        if (!lastname || !firstname || !email || !dateofbirth || !zipcode || !city || !address) {
          showErr(errEl, 'A csillaggal jelölt mezők kitöltése kötelező.'); return;
        }
        if (!consentRulesEl || !consentRulesEl.checked) {
          showErr(errEl, 'Az adatvédelmi tájékoztató és az alapszabály elfogadása kötelező.'); return;
        }

        var disabledFields = tourDisabledFields[tid] || [];

        var data = {
          tour_id:       String(tid),
          csrf_token:    csrfToken || '',
          lastname:      lastname,
          firstname:     firstname,
          email:         email,
          phone:         getJoinVal('phone'),
          dateofbirth:   dateofbirth,
          zipcode:       zipcode,
          city:          city,
          address:       address,
          message:       getJoinVal('message'),
          consent_email: (joinSec && joinSec.querySelector('[name="consent_email"]') && joinSec.querySelector('[name="consent_email"]').checked) ? '1' : '0',
          consent_photo: (joinSec && joinSec.querySelector('[name="consent_photo"]') && joinSec.querySelector('[name="consent_photo"]').checked) ? '1' : '0',
          consent_rules: '1',
        };

        // Tour-specific fields from join section
        var deptIn = joinSec ? joinSec.querySelector('[name="departure_city"]') : null;
        data.departure_city = deptIn ? deptIn.value.trim() : '';
        if (disabledFields.indexOf('departure_city') === -1 && !data.departure_city) {
          showErr(errEl, 'Az indulási helyszín megadása kötelező.'); return;
        }

        var carIn = joinSec ? joinSec.querySelector('input[name="j_car_' + tid + '"]:checked') : null;
        data.car_available = carIn ? carIn.value : '0';
        var passIn = joinSec ? joinSec.querySelector('[name="passengers"]') : null;
        data.passengers = passIn ? passIn.value : '0';
        var roomIn = joinSec ? joinSec.querySelector('[name="sharing_room"]') : null;
        data.sharing_room = roomIn ? roomIn.value : 'same_gender';
        var notesIn = joinSec ? joinSec.querySelector('[name="notes"]') : null;
        data.notes = notesIn ? notesIn.value : '';

        // Custom fields (j_custom_field_N → custom_field_N)
        if (joinSec) {
          joinSec.querySelectorAll('[name^="j_custom_field_"]').forEach(function(inp) {
            var realName = inp.name.substring(2); // strip 'j_' prefix
            data[realName] = (inp.type === 'checkbox') ? (inp.checked ? '1' : '') : inp.value;
          });
        }

        submitBtn.disabled    = true;
        submitBtn.textContent = 'Küldés…';

        fetch(joinTourUrl, {
          method:      'POST',
          credentials: 'include',
          headers:     { 'Content-Type': 'application/x-www-form-urlencoded' },
          body:        new URLSearchParams(data).toString(),
        })
          .then(function(r) { return r.json(); })
          .then(function(d) {
            if (d.success) {
              var titleEl = successEl.querySelector('.lote-ft-success-title');
              var textEl  = successEl.querySelector('.lote-ft-success-text');
              if (titleEl) titleEl.textContent = 'Tagságra jelentkezés elküldve!';
              if (textEl)  textEl.innerHTML    = 'Köszönjük! Kérelmedet megkaptuk, hamarosan visszajelzünk e-mailben.<br>Amint taggá váltál, automatikusan jóváhagyjuk a túrajelentkezésedet is.';
              inner.hidden     = true;
              successEl.hidden = false;
              var toggleWrap = document.getElementById(uid + '-toggle-wrap-' + tid);
              if (toggleWrap) toggleWrap.hidden = true;
            } else {
              submitBtn.disabled    = false;
              submitBtn.textContent = 'Tagságra és túrára jelentkezés';
              showErr(errEl, d.error || 'Hiba történt. Kérjük próbáld újra.');
            }
          })
          .catch(function() {
            submitBtn.disabled    = false;
            submitBtn.textContent = 'Tagságra és túrára jelentkezés';
            showErr(errEl, 'Hálózati hiba. Kérjük próbáld újra.');
          });
      }

      // ---- Részvételi díj kijelzés ----

      function formatFt(amount) {
        return Math.round(amount).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' Ft';
      }

      function updateAllFeeDisplays(discount) {
        tourIds.forEach(function(tid) {
          var fee     = tourFees[tid];
          var baseEl  = document.getElementById(uid + '-fee-base-'  + tid);
          var arrowEl = document.getElementById(uid + '-fee-arrow-' + tid);
          var discEl  = document.getElementById(uid + '-fee-disc-'  + tid);
          var badgeEl = document.getElementById(uid + '-fee-badge-' + tid);
          if (!baseEl || fee === null || fee === undefined) return;
          if (discount > 0) {
            baseEl.classList.add('lote-ft-fee-base--crossed');
            arrowEl.hidden      = false;
            discEl.hidden       = false;
            discEl.textContent  = formatFt(Math.round(fee * (1 - discount / 100)));
            badgeEl.hidden      = false;
            badgeEl.textContent = discount + '% tag kedvezmény';
          } else {
            baseEl.classList.remove('lote-ft-fee-base--crossed');
            arrowEl.hidden = true;
            discEl.hidden  = true;
            badgeEl.hidden = true;
          }
        });
      }

      // ---- Kijelentkezés ----

      var logoutBtn = document.getElementById(uid + '-logout');
      if (logoutBtn) {
        logoutBtn.addEventListener('click', function() {
          logoutBtn.disabled    = true;
          logoutBtn.textContent = 'Kijelentkezés…';
          fetch(authUrl + '?action=logout', { credentials: 'include' })
            .then(function() {
              loggedInUser = null;
              showTeaser();
              updateFormModes();
              updateAllFeeDisplays(0);
            })
            .catch(function() {
              logoutBtn.disabled    = false;
              logoutBtn.textContent = 'Kijelentkezés';
            });
        });
      }

      function showErr(el, msg) {
        if (!el) return;
        el.textContent = msg;
        el.hidden      = false;
      }

      function hideErr(el) {
        if (!el) return;
        el.hidden      = true;
        el.textContent = '';
      }

    })();
    </script>
    <?php
    return ob_get_clean();
});
