<?php
/*
Plugin Name: LOTE Toplista
Description: Leguán Osztag Természetjáró Egyesület toplista megjelenítő – iframe nélkül.
Version:     1.1
*/

defined('ABSPATH') || exit;

define('LOTE_TL_API_PATH',  '/connect/api/toplist.php');
define('LOTE_TL_APP_BASE',  '/connect');               // az app gyökérútja a WordPressen belül
define('LOTE_TL_CACHE_KEY', 'lote_toplist_data');
define('LOTE_TL_CACHE_TTL', 300);

function lote_tl_fetch(): ?array {
    $cached = get_transient(LOTE_TL_CACHE_KEY);
    if ($cached !== false) return $cached;

    $url      = home_url(LOTE_TL_API_PATH);
    $response = wp_remote_get($url, [
        'timeout' => 10,
        'headers' => ['X-Lote-Key' => defined('LOTE_TL_API_KEY') ? LOTE_TL_API_KEY : ''],
    ]);

    if (is_wp_error($response)) return null;

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data) || isset($data['error'])) return null;

    set_transient(LOTE_TL_CACHE_KEY, $data, LOTE_TL_CACHE_TTL);
    return $data;
}

function lote_tl_level_class(int $level): string {
    return 'lote-tl-lvl-' . max(1, min(9, $level));
}

function lote_tl_rank_badge(int $rank): string {
    $cls = match($rank) { 1 => 'gold', 2 => 'silver', 3 => 'bronze', default => 'plain' };
    return '<span class="lote-tl-rank lote-tl-rank--' . $cls . '">' . $rank . '</span>';
}

function lote_tl_level_img(?string $path, string $label): string {
    if (!$path) return '';
    $url = home_url(LOTE_TL_APP_BASE . $path);
    return '<img src="' . esc_url($url) . '" alt="' . esc_attr($label) . '" class="lote-tl-lvl-img" loading="lazy">';
}

add_shortcode('lote_toplist', function (): string {
    wp_enqueue_style('lote-toplist', plugins_url('style.css', __FILE__), [], '1.1');

    $data = lote_tl_fetch();
    if (!$data) {
        return '<p class="lote-tl-error">A toplista jelenleg nem érhető el. Kérjük, próbálja újra később.</p>';
    }

    $year = (int)($data['current_year'] ?? date('Y'));
    $uid  = 'lote-tl-' . substr(md5(uniqid()), 0, 8);

    ob_start();
    ?>
    <div class="lote-toplist" id="<?= esc_attr($uid) ?>">

      <div class="lote-tl-tabs" role="tablist">
        <button class="lote-tl-tab lote-tl-tab--active" role="tab"
                data-panel="<?= esc_attr($uid) ?>-alltime">Örökös toplista</button>
        <button class="lote-tl-tab" role="tab"
                data-panel="<?= esc_attr($uid) ?>-year"><?= $year ?>. év</button>
        <button class="lote-tl-tab" role="tab"
                data-panel="<?= esc_attr($uid) ?>-yearwinner">Év túratársa</button>
      </div>

      <!-- Örökös -->
      <div class="lote-tl-panel" id="<?= esc_attr($uid) ?>-alltime" role="tabpanel">
        <?php if (empty($data['alltime'])): ?>
          <p class="lote-tl-empty">Még nincs adat.</p>
        <?php else: ?>
        <table class="lote-tl-table">
          <thead><tr><th>#</th><th class="lote-tl-img-col"></th><th>Név</th><th>Rang</th><th class="lote-tl-r">Pontok</th></tr></thead>
          <tbody>
            <?php foreach ($data['alltime'] as $i => $r): ?>
            <tr>
              <td><?= lote_tl_rank_badge($i + 1) ?></td>
              <td class="lote-tl-img-col"><?= lote_tl_level_img($r['image_path'] ?? null, $r['level_label'] ?? '') ?></td>
              <td class="lote-tl-name"><?= esc_html($r['lastname'] . ' ' . $r['firstname']) ?></td>
              <td><span class="lote-tl-badge <?= esc_attr(lote_tl_level_class((int)$r['level'])) ?>"><?= esc_html($r['level_label']) ?></span></td>
              <td class="lote-tl-r"><strong><?= number_format((int)$r['total_points']) ?></strong></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

      <!-- Idei -->
      <div class="lote-tl-panel lote-tl-panel--hidden" id="<?= esc_attr($uid) ?>-year" role="tabpanel">
        <?php if (empty($data['year'])): ?>
          <p class="lote-tl-empty">Még nincs idei túra.</p>
        <?php else: ?>
        <table class="lote-tl-table">
          <thead><tr><th>#</th><th class="lote-tl-img-col"></th><th>Név</th><th>Rang</th><th class="lote-tl-r">Pontok</th></tr></thead>
          <tbody>
            <?php foreach ($data['year'] as $i => $r): ?>
            <tr>
              <td><?= lote_tl_rank_badge($i + 1) ?></td>
              <td class="lote-tl-img-col"><?= lote_tl_level_img($r['image_path'] ?? null, $r['level_label'] ?? '') ?></td>
              <td class="lote-tl-name"><?= esc_html($r['lastname'] . ' ' . $r['firstname']) ?></td>
              <td><span class="lote-tl-badge <?= esc_attr(lote_tl_level_class((int)$r['level'])) ?>"><?= esc_html($r['level_label']) ?></span></td>
              <td class="lote-tl-r"><strong><?= number_format((int)$r['total_points']) ?></strong></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

      <!-- Év túratársa -->
      <div class="lote-tl-panel lote-tl-panel--hidden" id="<?= esc_attr($uid) ?>-yearwinner" role="tabpanel">
        <?php if (empty($data['yearwinner'])): ?>
          <p class="lote-tl-empty">Még nincs adat.</p>
        <?php else: ?>
        <table class="lote-tl-table">
          <thead><tr><th>Év</th><th class="lote-tl-img-col"></th><th>Név</th><th>Rang</th><th class="lote-tl-r">Pontok</th></tr></thead>
          <tbody>
            <?php foreach ($data['yearwinner'] as $r): ?>
            <tr>
              <td><strong><?= (int)$r['year'] ?></strong></td>
              <td class="lote-tl-img-col"><?= lote_tl_level_img($r['image_path'] ?? null, $r['level_label'] ?? '') ?></td>
              <td class="lote-tl-name"><?= esc_html($r['lastname'] . ' ' . $r['firstname']) ?></td>
              <td><span class="lote-tl-badge <?= esc_attr(lote_tl_level_class((int)$r['level'])) ?>"><?= esc_html($r['level_label']) ?></span></td>
              <td class="lote-tl-r">
                <strong><?= number_format((float)$r['total_points'], 1) ?></strong>
                <?php if ((float)$r['bonus'] > 0): ?>
                  <br><small class="lote-tl-bonus"><?= number_format((int)$r['raw_points']) ?> + <?= number_format((float)$r['bonus'], 1) ?> bónusz</small>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

    </div>
    <script>
    (function () {
        var wrap = document.getElementById(<?= json_encode($uid) ?>);
        if (!wrap) return;
        var tabs   = wrap.querySelectorAll('.lote-tl-tab');
        var panels = wrap.querySelectorAll('.lote-tl-panel');
        tabs.forEach(function (btn) {
            btn.addEventListener('click', function () {
                tabs.forEach(function (b) {
                    b.classList.remove('lote-tl-tab--active');
                    b.setAttribute('aria-selected', 'false');
                });
                panels.forEach(function (p) { p.classList.add('lote-tl-panel--hidden'); });
                btn.classList.add('lote-tl-tab--active');
                btn.setAttribute('aria-selected', 'true');
                var target = document.getElementById(btn.dataset.panel);
                if (target) target.classList.remove('lote-tl-panel--hidden');
            });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
});
