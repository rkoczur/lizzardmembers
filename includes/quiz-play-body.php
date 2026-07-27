<?php
/**
 * Kvíz — játék nézet, közös tartalom.
 * Elvárt változók: $quizOptions, $quizActiveGame, $flash_success, $flash_error,
 * $quizBase (pl. BASE_URL.'/user' vagy BASE_URL.'/admin').
 */
$quizLifetimeProgress = quizProgress(getDb(), getCurrentUserId());
$quizNoMoreQuestions  = !$quizActiveGame && $quizLifetimeProgress['total'] > 0
    && $quizLifetimeProgress['answered'] >= $quizLifetimeProgress['total'];
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/kviz/quiz.css?v=<?= APP_VERSION ?>">

<?php if ($flash_success): ?>
  <div class="alert alert-success" data-auto-dismiss><?= e($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_error): ?>
  <div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="quiz" id="quiz">

    <div class="quiz-intro">
        <div class="quiz-top-actions">
            <?php if (isAdmin()): ?>
            <form action="<?= BASE_URL ?>/actions/quiz-reset.php" method="post" onsubmit="return confirm('Biztosan törlöd az összes kvíz pontodat és köreidet? A művelet nem vonható vissza.');">
                <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
                <button type="submit" class="quiz-btn quiz-btn-outline" title="Csak admin számára: saját kvíz pontok törlése">🔄 Pontjaim resetelése</button>
            </form>
            <?php endif; ?>
            <a href="<?= $quizBase ?>/quiz-toplista.php" class="quiz-btn quiz-btn-outline">🏆 Toplisták</a>
        </div>
        <p class="quiz-lead">Tippelj a madarak és hegycsúcsok adataira! Minél gyorsabban és pontosabban válaszolsz,
            annál több pontot kapsz. Minden kérdésre csak egyszer válaszolhatsz egész pályafutásod során.</p>
        <div class="quiz-progress">
            <div class="quiz-progress-bar"><span id="quizProgressFill" style="width:<?= $quizLifetimeProgress['total'] ? round($quizLifetimeProgress['answered'] / $quizLifetimeProgress['total'] * 100) : 0 ?>%"></span></div>
            <span class="quiz-progress-label" id="quizProgressLabel"><?= (int)$quizLifetimeProgress['answered'] ?> / <?= (int)$quizLifetimeProgress['total'] ?> kérdés megválaszolva összesen</span>
        </div>
    </div>

    <!-- Kör-előrehaladás (kérdésszám + eddigi pontszám a kör közben, mindvégig látható) -->
    <div class="quiz-round-bar" id="quizRoundBar" hidden>
        <span class="quiz-round-bar-progress" id="quizRoundProgress"></span>
        <span class="quiz-round-bar-score" id="quizRoundScoreLabel"></span>
    </div>

    <!-- Induló képernyő -->
    <section class="quiz-card quiz-start" id="quizStart" <?= ($quizActiveGame || $quizNoMoreQuestions) ? 'hidden' : '' ?>>
        <h2>🎮 Egy kör indítása</h2>
        <p>Egy kör <strong>20 kérdésből</strong> áll (madár és hegycsúcs vegyesen, véletlenszerűen) — vagy
            kevesebből, ha már nincs ennyi meg nem válaszolt kérdésed. A kör végén megkapod az
            <strong>összpontszámodat</strong> (a 20 kérdésre kapott pontok összege), ez kerül fel az
            összesített toplistára a legjobb köröd alapján.</p>
        <ul class="quiz-start-rules">
            <li>Madárnál a fesztávot, a színeket és a kontinenseket kell eltalálnod.</li>
            <li>Hegycsúcsnál az országot és a magasságot.</li>
            <li>Minél gyorsabban és minél pontosabban válaszolsz, annál nagyobb a pontszorzód.</li>
            <li>Egy kérdésre csak egyszer válaszolhatsz — a következő körben már új kérdéseket kapsz.</li>
        </ul>
        <div class="quiz-actions">
            <button type="button" class="quiz-btn quiz-btn-primary" id="quizStartBtn">Kör indítása →</button>
        </div>
    </section>

    <!-- Nincs több kérdés soha -->
    <section class="quiz-card quiz-done" id="quizNoMore" <?= $quizNoMoreQuestions ? '' : 'hidden' ?>>
        <div class="quiz-done-emoji">🏆</div>
        <h2>Minden kérdésre válaszoltál!</h2>
        <p>Nincs több megválaszolatlan madár vagy hegycsúcs. Nézd meg, hogy állsz az összesített toplistán!</p>
        <a href="<?= $quizBase ?>/quiz-toplista.php" class="quiz-btn quiz-btn-primary">Toplisták megtekintése</a>
    </section>

    <!-- Kérdés -->
    <section class="quiz-card quiz-question" id="quizQuestion" hidden>
        <div class="quiz-q-head">
            <span class="quiz-badge" id="quizBadge"></span>
            <div class="quiz-timer" id="quizTimer" title="Eltelt idő és pontszorzó">
                <span class="quiz-timer-time" id="quizTimerTime">0,0 mp</span>
                <span class="quiz-mult" id="quizMult">×2,50</span>
            </div>
        </div>
        <h2 class="quiz-q-name" id="quizName"></h2>
        <div class="quiz-q-latin" id="quizLatin" hidden></div>

        <!-- Madár mezők -->
        <div class="quiz-fields" id="quizBirdFields" hidden>
            <div class="quiz-field">
                <label class="quiz-field-label">Mekkora a szárny fesztávja?</label>
                <div class="quiz-slider-wrap">
                    <div class="quiz-slider-value"><span id="quizWingspanValue">150</span> <span class="quiz-num-unit">cm</span></div>
                    <input type="range" id="quizWingspan" class="quiz-slider" min="5" max="300" step="1" value="150">
                    <div class="quiz-slider-range"><span>5 cm</span><span>300 cm</span></div>
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
                <div class="quiz-pills" id="quizCountryChoices"></div>
                <input type="hidden" id="quizCountry">
            </div>
            <div class="quiz-field">
                <label class="quiz-field-label">Milyen magas a csúcs?</label>
                <div class="quiz-slider-wrap">
                    <div class="quiz-slider-value"><span id="quizElevationValue">4500</span> <span class="quiz-num-unit">m</span></div>
                    <input type="range" id="quizElevation" class="quiz-slider" min="0" max="9000" step="1" value="4500">
                    <div class="quiz-slider-range"><span>0 m</span><span>9000 m</span></div>
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

</div>

<!-- Kör vége összegzés — felugró ablak, bezárva is látszik alatta az utolsó kérdés adata/képe. -->
<div class="modal-backdrop" id="quizRoundOverModal">
    <div class="modal quiz-round-over-modal">
        <div class="modal-header">
            <h2>🎉 Kör vége!</h2>
            <button class="modal-close" type="button" data-modal-close aria-label="Bezárás">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <p id="quizRoundOverText"></p>
            <div class="quiz-score-big quiz-round-over-score"><span id="quizRoundOverScore">0</span> <small>összpontszám</small></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" type="button" data-modal-close>Utolsó kérdés megtekintése</button>
            <a href="<?= $quizBase ?>/quiz-toplista.php" class="quiz-btn quiz-btn-outline">🏆 Toplisták</a>
            <button type="button" class="quiz-btn quiz-btn-primary" id="quizPlayAgain">Új kör indítása →</button>
        </div>
    </div>
</div>

<script>
window.QUIZ = {
    csrf: <?= json_encode(csrfToken(), JSON_HEX_TAG) ?>,
    urls: {
        gameStart: '<?= BASE_URL ?>/api/quiz-game-start.php',
        question:  '<?= BASE_URL ?>/api/quiz-question.php',
        answer:    '<?= BASE_URL ?>/api/quiz-answer.php'
    },
    options: <?= json_encode($quizOptions, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>,
    activeGame: <?= $quizActiveGame ? 'true' : 'false' ?>,
    noMoreQuestions: <?= $quizNoMoreQuestions ? 'true' : 'false' ?>
};
</script>
<script src="<?= BASE_URL ?>/kviz/quiz.js?v=<?= APP_VERSION ?>"></script>
