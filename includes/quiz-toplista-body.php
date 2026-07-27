<?php
/**
 * Kvíz — toplisták nézet, közös tartalom.
 * Elvárt változó: $quizBase (pl. BASE_URL.'/user' vagy BASE_URL.'/admin').
 */
?>
<link rel="stylesheet" href="<?= BASE_URL ?>/kviz/quiz.css?v=<?= APP_VERSION ?>">

<div class="quiz" id="quiz">

    <div class="quiz-intro">
        <div class="quiz-top-actions">
            <a href="<?= $quizBase ?>/quiz.php" class="quiz-btn quiz-btn-outline">← Vissza a kvízhez</a>
        </div>
        <p class="quiz-lead">Nézd meg, hogy állsz a többi taghoz képest, és milyen kérdésekre válaszoltál már.</p>
    </div>

    <!-- Toplisták -->
    <section class="quiz-leaderboard">
        <div class="quiz-tabs">
            <button type="button" class="quiz-tab active" data-tab="overall">Összesített toplista</button>
            <button type="button" class="quiz-tab" data-tab="mine">Megválaszolt kérdéseim</button>
        </div>
        <div class="quiz-tab-panel" id="quizTabOverall">
            <p class="quiz-tab-desc">Egy kör 20 kérdésből áll (madár és hegycsúcs vegyesen), a kör eredménye a 20 kérdésre
                kapott pontok összege. A rangsor mindenki <strong>legjobb (befejezett) körének</strong> összpontszáma
                alapján rendez.</p>
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
window.QUIZ_TL = {
    urls: {
        leaderboard: '<?= BASE_URL ?>/api/quiz-leaderboard.php'
    }
};
</script>
<script src="<?= BASE_URL ?>/kviz/quiz-toplista.js?v=<?= APP_VERSION ?>"></script>
