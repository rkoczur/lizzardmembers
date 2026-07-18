<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/quiz-schema.php';
requireUser();

$pdo = getDb();
ensureQuizSchema($pdo);

$quizOptions = [
    'colors'     => quizAllColors($pdo),
    'continents' => quizAllContinents($pdo),
    'countries'  => quizAllCountries($pdo),
];

$pageTitle  = 'Kvíz játék';
$activePage = 'quiz';
include __DIR__ . '/../includes/user-header.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/kviz/quiz.css?v=<?= APP_VERSION ?>">

<div class="quiz" id="quiz">

    <div class="quiz-intro">
        <p class="quiz-lead">Tippelj a madarak és hegycsúcsok adataira! Minél gyorsabban és pontosabban válaszolsz,
            annál több pontot kapsz. Minden kérdésre csak egyszer válaszolhatsz.</p>
        <div class="quiz-progress">
            <div class="quiz-progress-bar"><span id="quizProgressFill"></span></div>
            <span class="quiz-progress-label" id="quizProgressLabel">Betöltés…</span>
        </div>
    </div>

    <!-- Kérdés -->
    <section class="quiz-card quiz-question" id="quizQuestion" hidden>
        <div class="quiz-q-head">
            <span class="quiz-badge" id="quizBadge"></span>
            <div class="quiz-timer" id="quizTimer" title="Eltelt idő és pontszorzó">
                <span class="quiz-timer-time" id="quizTimerTime">0,0 mp</span>
                <span class="quiz-mult" id="quizMult">×3,00</span>
            </div>
        </div>
        <h2 class="quiz-q-name" id="quizName"></h2>
        <div class="quiz-q-latin" id="quizLatin" hidden></div>

        <!-- Madár mezők -->
        <div class="quiz-fields" id="quizBirdFields" hidden>
            <div class="quiz-field">
                <label class="quiz-field-label">Mekkora a szárny fesztávja?</label>
                <div class="quiz-num">
                    <input type="text" inputmode="numeric" id="quizWingspan" class="quiz-num-input" placeholder="pl. 120" autocomplete="off">
                    <span class="quiz-num-unit">cm</span>
                </div>
            </div>
            <div class="quiz-field">
                <label class="quiz-field-label">Milyen színei vannak? <small>(több is lehet)</small></label>
                <div class="quiz-pills" id="quizColors"></div>
            </div>
            <div class="quiz-field">
                <label class="quiz-field-label">Mely kontinenseken honos? <small>(több is lehet)</small></label>
                <div class="quiz-pills" id="quizContinents"></div>
            </div>
        </div>

        <!-- Hegy mezők -->
        <div class="quiz-fields" id="quizMountainFields" hidden>
            <div class="quiz-field">
                <label class="quiz-field-label">Melyik országban található?</label>
                <div class="quiz-combo" id="quizCountryCombo">
                    <input type="text" id="quizCountryInput" class="quiz-combo-input" placeholder="Kezdj el gépelni…" autocomplete="off" role="combobox" aria-expanded="false">
                    <div class="quiz-combo-list" id="quizCountryList" hidden></div>
                    <input type="hidden" id="quizCountry">
                </div>
            </div>
            <div class="quiz-field">
                <label class="quiz-field-label">Milyen magas a csúcs?</label>
                <div class="quiz-num">
                    <input type="text" inputmode="numeric" id="quizElevation" class="quiz-num-input" placeholder="pl. 2500" autocomplete="off">
                    <span class="quiz-num-unit">m</span>
                </div>
            </div>
        </div>

        <div class="quiz-actions">
            <button type="button" class="quiz-btn quiz-btn-primary" id="quizSubmit" disabled>Válasz beküldése</button>
        </div>
    </section>

    <!-- Eredmény -->
    <section class="quiz-card quiz-result" id="quizResult" hidden>
        <div class="quiz-result-head">
            <div class="quiz-result-title">
                <h2 class="quiz-result-name" id="quizResultTitle"></h2>
                <div class="quiz-result-latin" id="quizResultLatin" hidden></div>
            </div>
            <div class="quiz-result-score">
                <div class="quiz-score-big"><span id="quizScoreVal">0</span> <small>pont</small></div>
                <div class="quiz-result-meta" id="quizResultMeta"></div>
            </div>
        </div>

        <div class="quiz-result-body" id="quizResultBody">
            <div class="quiz-quickstats" id="quizQuickStats" hidden></div>
            <div class="quiz-breakdown" id="quizBreakdown"></div>
            <div class="quiz-facts" id="quizFacts" hidden></div>
            <div class="quiz-fact-image-box" id="quizFactImage" hidden></div>
        </div>

        <h3 class="quiz-sub">Toplista — <span id="quizResultName"></span></h3>
        <div class="quiz-toplist" id="quizResultToplist"></div>

        <div class="quiz-actions">
            <button type="button" class="quiz-btn quiz-btn-primary" id="quizNext">Következő kérdés →</button>
        </div>
    </section>

    <!-- Minden kérdés megválaszolva -->
    <section class="quiz-card quiz-done" id="quizDone" hidden>
        <div class="quiz-done-emoji">🏆</div>
        <h2>Minden kérdésre válaszoltál!</h2>
        <p>Nincs több megválaszolatlan madár vagy hegycsúcs. Nézd meg, hogy állsz az összesített toplistán!</p>
    </section>

    <!-- Toplisták -->
    <section class="quiz-leaderboard">
        <div class="quiz-tabs">
            <button type="button" class="quiz-tab active" data-tab="overall">Összesített toplista</button>
            <button type="button" class="quiz-tab" data-tab="mine">Megválaszolt kérdéseim</button>
        </div>
        <div class="quiz-tab-panel" id="quizTabOverall">
            <p class="quiz-tab-desc">Minden tag átlagpontszáma az általa megválaszolt kérdésekből.</p>
            <div id="quizOverall"><div class="quiz-empty">Betöltés…</div></div>
        </div>
        <div class="quiz-tab-panel" id="quizTabMine" hidden>
            <p class="quiz-tab-desc">A kérdések, amelyekre már válaszoltál. Kattints egyre a toplistájáért.</p>
            <div id="quizMine"><div class="quiz-empty">Betöltés…</div></div>
            <div class="quiz-item-toplist" id="quizItemToplist" hidden></div>
        </div>
    </section>

</div>

<script>
window.QUIZ = {
    csrf: <?= json_encode(csrfToken(), JSON_HEX_TAG) ?>,
    urls: {
        question:    '<?= BASE_URL ?>/api/quiz-question.php',
        answer:      '<?= BASE_URL ?>/api/quiz-answer.php',
        leaderboard: '<?= BASE_URL ?>/api/quiz-leaderboard.php'
    },
    options: <?= json_encode($quizOptions, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>
};
</script>
<script src="<?= BASE_URL ?>/kviz/quiz.js?v=<?= APP_VERSION ?>"></script>
<?php
include __DIR__ . '/../includes/user-footer.php';
