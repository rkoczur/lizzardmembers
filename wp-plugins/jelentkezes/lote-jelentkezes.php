<?php
/*
Plugin Name: LOTE Meghirdetett Túrák – Jelentkezés
Description: Leguán Osztag Természetjáró Egyesület meghirdetett túrák natív jelentkezési űrlapja. Shortcode: [lote_jelentkezes] vagy [lote_jelentkezes id="5"]
Version:     2.1
Author:      Leguán Osztag Természetjáró Egyesület
*/

defined('ABSPATH') || exit;

if (!defined('LOTE_FT_APP_BASE')) {
    define('LOTE_FT_APP_BASE', '/lizzardmembers');
}

define('LOTE_FT_API_TOURS',   LOTE_FT_APP_BASE . '/api/future-tours.php');
define('LOTE_FT_API_AUTH',    LOTE_FT_APP_BASE . '/api/member-auth.php');
define('LOTE_FT_API_CSRF',    LOTE_FT_APP_BASE . '/api/csrf-token.php');
define('LOTE_FT_API_SUBMIT',  LOTE_FT_APP_BASE . '/api/submit-application.php');
define('LOTE_FT_API_EMAIL',   LOTE_FT_APP_BASE . '/api/check-member-email.php');
define('LOTE_FT_CACHE_KEY',   'lote_ft_tours_v2');
define('LOTE_FT_CACHE_TTL',   120);

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
    wp_enqueue_style('lote-jelentkezes', plugins_url('style.css', __FILE__), [], '2.0');

    $atts = shortcode_atts(['id' => ''], $atts);
    $data = lote_ft_fetch();
    $uid  = 'lote-ft-' . substr(md5(uniqid()), 0, 8);

    $authUrl   = esc_url(home_url(LOTE_FT_API_AUTH));
    $csrfUrl   = esc_url(home_url(LOTE_FT_API_CSRF));
    $submitUrl = esc_url(home_url(LOTE_FT_API_SUBMIT));
    $emailUrl  = esc_url(home_url(LOTE_FT_API_EMAIL));

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
            <span class="lote-ft-panel-text" style="font-size: 14px;">Már tag vagy? Lépj be a jelentkezéshez!</span>
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
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
          <span class="lote-ft-panel-text">Bejelentkezve: <strong id="<?= esc_attr($uid) ?>-name"></strong></span>
        </div>

      </div>

      <!-- ===== Natív jelentkezési űrlapok ===== -->
      <?php foreach ($tours as $t):
        $tid          = (int)$t['id'];
        $customFields = $t['custom_fields'] ?? [];
        $bId          = esc_attr($uid . '-block-' . $tid);
      ?>
      <div class="lote-ft-form-block" id="<?= $bId ?>">

        <!-- Jelentkezés toggle gomb -->
        <div class="lote-ft-apply-toggle" id="<?= esc_attr($uid) ?>-toggle-wrap-<?= $tid ?>">
          <button type="button" class="lote-ft-apply-btn" id="<?= esc_attr($uid) ?>-apply-toggle-<?= $tid ?>">Jelentkezés</button>
        </div>

        <!-- Form tartalom -->
        <div class="lote-ft-form-inner" id="<?= esc_attr($uid) ?>-inner-<?= $tid ?>" hidden>

          <p class="lote-ft-form-intro">Az alábbi űrlapon jelentkezhetsz a túrára. A jelentkezésedet az adminisztrátor hagyja jóvá, erről e-mailben értesítünk.</p>

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

          <!-- Autó -->
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

          <!-- Szobamegosztás -->
          <div class="lote-ft-field-wrap">
            <label class="lote-ft-label">Szükség esetén aludnál egy helyen mással?</label>
            <select class="lote-ft-select" name="sharing_room">
              <option value="same_gender">Igen, de csak azonos neművel</option>
              <option value="yes">Igen</option>
              <option value="no">Nem</option>
            </select>
          </div>

          <!-- Megjegyzés -->
          <div class="lote-ft-field-wrap">
            <label class="lote-ft-label">Megjegyzések</label>
            <textarea class="lote-ft-textarea" name="notes" rows="3" placeholder="Egyéb megjegyzés, kérés…"></textarea>
          </div>

          <!-- Egyéni mezők -->
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

          <!-- Küldés -->
          <div class="lote-ft-submit-wrap">
            <button type="button" class="lote-ft-submit-btn" id="<?= esc_attr($uid) ?>-submit-<?= $tid ?>">
              Jelentkezés elküldése
            </button>
          </div>
          <div class="lote-ft-inline-error" id="<?= esc_attr($uid) ?>-form-err-<?= $tid ?>" hidden></div>

        </div>

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
      var uid       = <?= json_encode($uid) ?>;
      var authUrl   = <?= json_encode($authUrl) ?>;
      var csrfUrl   = <?= json_encode($csrfUrl) ?>;
      var submitUrl = <?= json_encode($submitUrl) ?>;
      var emailUrl  = <?= json_encode($emailUrl) ?>;
      var tourIds   = <?= json_encode(array_map('intval', $tourIds)) ?>;

      var wrap         = document.getElementById(uid);
      var teaser       = document.getElementById(uid + '-teaser');
      var formWrap     = document.getElementById(uid + '-form-wrap');
      var loginForm    = document.getElementById(uid + '-login-form');
      var toggle       = document.getElementById(uid + '-toggle');
      var cancel       = document.getElementById(uid + '-cancel');
      var errBox       = document.getElementById(uid + '-err');
      var loggedinEl   = document.getElementById(uid + '-loggedin');
      var nameEl       = document.getElementById(uid + '-name');
      var loginSubmit  = document.getElementById(uid + '-submit');

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
            loggedInUser = { firstname: d.firstname, lastname: d.lastname, email: d.email };
            showLoggedIn(d.firstname, d.lastname);
            updateFormModes();
          } else {
            showTeaser();
          }
        })
        .catch(function() { showTeaser(); });

      // ---- Login panel ----

      function showLoggedIn(firstname, lastname) {
        teaser.hidden          = true;
        teaser.style.display   = 'none';
        formWrap.hidden        = true;
        loggedinEl.hidden      = false;
        loggedinEl.style.display = 'flex';
        nameEl.textContent     = (lastname + ' ' + firstname).trim();
      }

      function showTeaser() {
        teaser.hidden          = false;
        teaser.style.display   = '';
        formWrap.hidden        = true;
        loggedinEl.hidden      = true;
        loggedinEl.style.display = 'none';
        errBox.hidden          = true;
        loginForm.reset();
        loginSubmit.disabled    = false;
        loginSubmit.textContent = 'Bejelentkezés';
      }

      function showLoginForm() {
        teaser.hidden   = true;
        formWrap.hidden = false;
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
              loggedInUser = { firstname: d.firstname, lastname: d.lastname, email: d.email };
              showLoggedIn(d.firstname, d.lastname);
              updateFormModes();
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

      // ---- Form mód váltás (vendég ↔ tag) ----

      function updateFormModes() {
        tourIds.forEach(function(tid) {
          var gSection = document.getElementById(uid + '-guest-' + tid);
          var mSection = document.getElementById(uid + '-member-' + tid);
          if (!gSection || !mSection) return;
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

        // Autó radio toggle
        block.querySelectorAll('input[name="car_' + tid + '"]').forEach(function(r) {
          r.addEventListener('change', function() {
            var passRow = document.getElementById(uid + '-pass-' + tid);
            if (passRow) passRow.hidden = (r.value !== '1');
          });
        });

        // Beküldés gomb
        var submitBtn = document.getElementById(uid + '-submit-' + tid);
        if (submitBtn) {
          submitBtn.addEventListener('click', function() { handleSubmit(tid); });
        }
      });

      // ---- Beküldés ----

      function handleSubmit(tid) {
        var block     = document.getElementById(uid + '-block-' + tid);
        var inner     = document.getElementById(uid + '-inner-' + tid);
        var successEl = document.getElementById(uid + '-success-' + tid);
        var submitBtn = document.getElementById(uid + '-submit-' + tid);
        var errEl     = document.getElementById(uid + '-form-err-' + tid);

        hideErr(errEl);

        var data = { tour_id: String(tid), csrf_token: csrfToken || '' };

        if (!loggedInUser) {
          var nameIn  = block.querySelector('[name="guest_name"]');
          var emailIn = block.querySelector('[name="guest_email"]');
          var phoneIn = block.querySelector('[name="guest_phone"]');
          data.guest_name  = nameIn  ? nameIn.value.trim()  : '';
          data.guest_email = emailIn ? emailIn.value.trim() : '';
          data.guest_phone = phoneIn ? phoneIn.value.trim() : '';

          if (!data.guest_name) { showErr(errEl, 'A név megadása kötelező.'); return; }
          if (!data.guest_email) { showErr(errEl, 'Az e-mail cím megadása kötelező.'); return; }
        }

        var carIn  = block.querySelector('input[name="car_' + tid + '"]:checked');
        data.car_available = carIn ? carIn.value : '0';
        var passIn = block.querySelector('[name="passengers"]');
        data.passengers    = passIn ? passIn.value : '0';
        var roomIn = block.querySelector('[name="sharing_room"]');
        data.sharing_room  = roomIn ? roomIn.value : 'same_gender';
        var notesIn = block.querySelector('[name="notes"]');
        data.notes = notesIn ? notesIn.value : '';

        block.querySelectorAll('[name^="custom_field_"]').forEach(function(inp) {
          data[inp.name] = (inp.type === 'checkbox') ? (inp.checked ? '1' : '') : inp.value;
        });

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
