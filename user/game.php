<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/game-schema.php';
requireUser();

require __DIR__ . '/../game/game-data.php';

$pdo  = getDb();
$save = loadGameSave($pdo, getCurrentUserId());

// Toplista: pályánként (hegyenként) a leggyorsabb 5 tag legjobb ideje.
$meId = getCurrentUserId();
$rows = $pdo->query("
    SELECT gr.level, gr.best_time, gr.user_id,
           u.firstname, u.lastname, u.level AS rank_level
    FROM game_results gr
    JOIN users u ON u.id = gr.user_id
    ORDER BY gr.level ASC, gr.best_time ASC, gr.updated_at ASC
")->fetchAll(PDO::FETCH_ASSOC);

$topByLevel = [];
foreach ($rows as $r) {
    $lvl = (int)$r['level'];
    if (!isset($topByLevel[$lvl])) $topByLevel[$lvl] = [];
    if (count($topByLevel[$lvl]) < 5) $topByLevel[$lvl][] = $r;
}
ksort($topByLevel);

/** Játékidő (másodperc) olvasható formátumra: „1:23.4” vagy „12.3 mp”. */
function fmtGameTime(float $s): string
{
    if ($s >= 60) {
        $m   = (int)floor($s / 60);
        $sec = $s - $m * 60;
        return sprintf('%d:%04.1f', $m, $sec);
    }
    return number_format($s, 1, ',', '') . ' mp';
}

$pageTitle  = 'Hegymászó játék';
$activePage = 'game';
include __DIR__ . '/../includes/user-header.php';
?>
<style>
    /* A játék beágyazott stílusai — a tagoldali elrendezést nem írjuk felül (nincs body-szabály) */
    .game-embed { max-width: 1000px; margin: 0 auto; }
    .game-embed .game-box {
        width: 100%;
        aspect-ratio: 16 / 9;
        background: #000;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 8px 40px rgba(0, 0, 0, .35);
        border: 2px solid #34455c;
    }
    .game-embed .game-box canvas { width: 100%; height: 100%; display: block; outline: none; }
    .game-embed .game-help {
        margin-top: 14px;
        background: #232f40;
        color: #c3cedd;
        border: 1px solid #34455c;
        border-radius: 10px;
        padding: 12px 18px;
        font-size: 14px;
    }
    .game-embed .game-help h2 { font-size: 15px; margin-bottom: 6px; color: #9fd08c; }
    .game-embed .game-help ul { list-style: none; margin: 0; padding: 0; }
    .game-embed .game-help li { margin: 3px 0; }
    .game-embed .game-help b {
        background: #34455c; border-radius: 4px; padding: 1px 7px; color: #fff; font-size: 13px;
    }
    .game-embed .game-note { margin-top: 10px; font-size: 13px; color: var(--text-muted, #6b7280); }

    /* Toplista — pályánként a leggyorsabb 5 */
    .game-toplist { max-width: 1000px; margin: 30px auto 0; }
    .game-toplist .gt-title { font-size: 18px; margin-bottom: 4px; }
    .game-toplist .gt-sub { color: var(--text-muted, #6b7280); font-size: 13px; margin-bottom: 16px; }
    .gt-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
    .gt-card { background: var(--card-bg, #fff); border: 1px solid var(--border, #e5e7eb); border-radius: 10px; padding: 14px 16px; }
    .gt-card-head { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; margin-bottom: 8px; }
    .gt-lvl-name { font-weight: 700; font-size: 15px; }
    .gt-lvl-h { color: var(--text-muted, #6b7280); font-size: 12px; white-space: nowrap; }
    .gt-table { width: 100%; border-collapse: collapse; font-size: 14px; }
    .gt-table td { padding: 5px 4px; border-bottom: 1px solid var(--border, #f0f0f0); }
    .gt-table tr:last-child td { border-bottom: none; }
    .gt-rank { width: 28px; text-align: center; font-weight: 700; color: var(--text-muted, #6b7280); }
    .gt-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .gt-time { text-align: right; font-variant-numeric: tabular-nums; font-weight: 600; white-space: nowrap; }
    .gt-row-me { background: rgba(37, 99, 235, .08); }
    .gt-row-me .gt-name { font-weight: 700; }
    .gt-empty { color: var(--text-muted, #6b7280); font-size: 14px; text-align: center; padding: 26px; }
</style>

<div class="game-embed">
    <div class="game-box">
        <canvas id="gameCanvas" width="960" height="540"></canvas>
    </div>
    <div class="game-help">
        <h2>Irányítás</h2>
        <ul>
            <li><b>→</b> előre &nbsp; <b>←</b> hátra &nbsp; <b>↑</b> ugrás (kétszer: dupla ugrás) &nbsp; <b>↓</b> guggolás</li>
            <li><b>Alt</b> (tartva) lassú lopakodás — így nem riasztod el a rókát 🦊</li>
            <li><b>Shift</b> ütés túrabottal &nbsp; <b>Ctrl</b> nadrág le/fel (vízátkeléshez)</li>
            <li><b>S</b> naptej &nbsp; <b>L</b> fejlámpa &nbsp; <b>P</b> fotó / szelfi a csúcskeresztnél &nbsp; <b>Esc</b> szünet</li>
        </ul>
    </div>
    <p class="game-note">Az eredményeid (a megnyitott pályák és a legjobb idők) automatikusan mentődnek a fiókodhoz.</p>
</div>

<div class="game-toplist">
    <h2 class="gt-title">Toplista — a leggyorsabbak pályánként</h2>
    <p class="gt-sub">Minden hegynél a legjobb 5 idő. A saját eredményed kiemelve látszik.</p>

    <?php if (empty($topByLevel)): ?>
        <div class="card"><div class="gt-empty">Még senki sem teljesített egyetlen pályát sem. Légy te az első! 🏔️</div></div>
    <?php else: ?>
    <div class="gt-grid">
        <?php foreach ($topByLevel as $lvl => $entries):
            $name   = $GAME_LEVEL_NAMES[$lvl - 1]   ?? ('Pálya ' . $lvl);
            $height = $GAME_LEVEL_HEIGHTS[$lvl - 1] ?? null;
        ?>
        <div class="gt-card">
            <div class="gt-card-head">
                <span class="gt-lvl-name"><?= $lvl ?>. <?= e($name) ?></span>
                <?php if ($height !== null): ?><span class="gt-lvl-h"><?= number_format($height, 0, ',', ' ') ?> m</span><?php endif; ?>
            </div>
            <table class="gt-table">
                <tbody>
                    <?php foreach ($entries as $i => $row):
                        $medal = ['🥇', '🥈', '🥉'][$i] ?? null;
                        $isMe  = (int)$row['user_id'] === $meId;
                    ?>
                    <tr class="<?= $isMe ? 'gt-row-me' : '' ?>">
                        <td class="gt-rank"><?= $medal ?? ($i + 1) . '.' ?></td>
                        <td class="gt-name"><?= e($row['lastname'] . ' ' . $row['firstname']) ?><?= $isMe ? ' (te)' : '' ?></td>
                        <td class="gt-time"><?= e(fmtGameTime((float)$row['best_time'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
window.GAME_LEVELS = <?= gameLevelsJson() ?>;
window.MEMBER_GAME = {
    saveUrl: '<?= BASE_URL ?>/api/game-result-save.php',
    csrf: '<?= e(csrfToken()) ?>',
    initial: <?= json_encode($save, JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="<?= BASE_URL ?>/game/js/levels.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= BASE_URL ?>/game/js/entities.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= BASE_URL ?>/game/js/render.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= BASE_URL ?>/game/js/main.js?v=<?= APP_VERSION ?>"></script>
<?php
include __DIR__ . '/../includes/user-footer.php';
