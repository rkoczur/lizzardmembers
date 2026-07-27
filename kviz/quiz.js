/* Kvíz játék — kliens logika (natív JS). */
(function () {
    'use strict';

    var CFG = window.QUIZ;
    if (!CFG) { return; }

    /* Színnevek → megjelenítési szín (svájcisapka a pilleken). */
    var COLOR_HEX = {
        'barna': '#8B5A2B', 'fehér': '#ffffff', 'fekete': '#222222', 'kék': '#2f6fed',
        'lila': '#7d3cc9', 'narancs': '#e8821e', 'piros': '#d63333', 'rózsaszín': '#ec6aaa',
        'szürke': '#8a8a8a', 'sárga': '#f2c200', 'zöld': '#2fa84f'
    };

    var $ = function (id) { return document.getElementById(id); };
    function show(el) { if (el) el.hidden = false; }
    function hide(el) { if (el) el.hidden = true; }
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    /* Magyaros számformázás (tizedesvessző). */
    function num(n, dec) {
        return Number(n).toFixed(dec == null ? 0 : dec).replace('.', ',');
    }

    var state = { question: null, timer: null, start: 0, game: null };

    /* ---------- Vezérlők felépítése ---------- */

    function buildPills(container, values, withSwatch) {
        container.innerHTML = '';
        values.forEach(function (val) {
            var pill = document.createElement('button');
            pill.type = 'button';
            pill.className = 'quiz-pill';
            pill.dataset.value = val;
            var inner = '';
            if (withSwatch) {
                var hex = COLOR_HEX[val] || '#bbbbbb';
                inner += '<span class="quiz-swatch" style="background:' + hex + '"></span>';
            }
            inner += '<span>' + esc(val) + '</span>';
            inner += '<svg class="quiz-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>';
            pill.innerHTML = inner;
            pill.addEventListener('click', function () { pill.classList.toggle('on'); });
            container.appendChild(pill);
        });
    }

    function selectedPills(container) {
        return Array.prototype.map.call(container.querySelectorAll('.quiz-pill.on'), function (p) {
            return p.dataset.value;
        });
    }
    function resetPills(container) {
        Array.prototype.forEach.call(container.querySelectorAll('.quiz-pill.on'), function (p) {
            p.classList.remove('on');
        });
    }

    /* Ország-választó gombok (a helyes + 5 véletlenszerű, kérdésenként a szervertől kapva). */
    function buildCountryChoices(container, values, hiddenInput) {
        container.innerHTML = '';
        hiddenInput.value = '';
        values.forEach(function (val) {
            var pill = document.createElement('button');
            pill.type = 'button';
            pill.className = 'quiz-pill';
            pill.dataset.value = val;
            pill.innerHTML = '<span>' + esc(val) + '</span>' +
                '<svg class="quiz-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>';
            pill.addEventListener('click', function () {
                Array.prototype.forEach.call(container.querySelectorAll('.quiz-pill'), function (p) { p.classList.remove('on'); });
                pill.classList.add('on');
                hiddenInput.value = val;
                validate();
            });
            container.appendChild(pill);
        });
    }

    /* Csúszka mezők (fesztáv / magasság). */
    function initSlider(input, labelEl, unit) {
        input.addEventListener('input', function () {
            labelEl.textContent = input.value;
            validate();
        });
    }
    function resetSlider(input, labelEl, mid) {
        input.value = mid;
        labelEl.textContent = mid;
    }

    /* ---------- Kérdés betöltése / kör indítása ---------- */

    function setError(msg) {
        clearError();
        var box = document.createElement('div');
        box.className = 'quiz-error'; box.id = 'quizError';
        box.textContent = msg;
        $('quiz').insertBefore(box, $('quizStart'));
    }
    function clearError() { var e = $('quizError'); if (e) e.remove(); }

    function hideAllScreens() {
        hide($('quizStart')); hide($('quizNoMore')); hide($('quizQuestion')); hide($('quizResult'));
        hide($('quizRoundBar'));
        $('quizRoundOverModal').classList.remove('open');
    }

    function startGame() {
        stopTimer();
        clearError();
        hideAllScreens();
        fetch(CFG.urls.gameStart, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ csrf_token: CFG.csrf })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) { setError(data.error); show($('quizStart')); return; }
                updateLifetimeProgress(data.lifetimeProgress);
                if (data.done) { show($('quizNoMore')); return; }
                state.game = data.game;
                renderQuestion(data.question, data.elapsedSeconds);
            })
            .catch(function () { setError('Nem sikerült elindítani a kört. Próbáld újra!'); show($('quizStart')); });
    }

    function loadQuestion() {
        stopTimer();
        clearError();
        hide($('quizResult'));
        fetch(CFG.urls.question, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) { setError(data.error); return; }
                updateLifetimeProgress(data.lifetimeProgress);
                if (data.noActiveGame) { hideAllScreens(); show($('quizStart')); return; }
                if (data.gameOver) { hide($('quizQuestion')); showRoundOverStandalone(data.gameOver); return; }
                state.game = data.game;
                renderQuestion(data.question, data.elapsedSeconds);
            })
            .catch(function () { setError('Nem sikerült betölteni a kérdést. Próbáld újra!'); });
    }

    /* Ha a "Következő kérdés" gomb védekező ágon kör-véget kap, önálló képernyőn mutatjuk. */
    function showRoundOverStandalone(gameOver) {
        hideAllScreens();
        show($('quizResult'));
        $('quizResultBody').hidden = true;
        $('quizResult').querySelector('.quiz-result-head').hidden = true;
        $('quizResult').querySelector('.quiz-sub').hidden = true;
        $('quizResultToplist').innerHTML = '';
        hide($('quizNext'));
        renderRoundOver(gameOver);
    }

    function renderQuestion(q, elapsedSeconds) {
        state.question = q;
        hideAllScreens();
        show($('quizQuestion'));
        $('quizResult').querySelector('.quiz-result-head').hidden = false;
        $('quizResult').querySelector('.quiz-sub').hidden = false;
        $('quizResultBody').hidden = false;

        updateRoundProgress(state.game);

        $('quizName').textContent = q.name;
        var latin = $('quizLatin');
        var badge = $('quizBadge');
        if (q.type === 'bird') {
            badge.textContent = '🐦 Madár';
            badge.className = 'quiz-badge bird';
            show($('quizBirdFields')); hide($('quizMountainFields'));
            resetSlider($('quizWingspan'), $('quizWingspanValue'), 150);
            resetPills($('quizColors')); resetPills($('quizContinents'));
            if (q.latin_name) { latin.textContent = q.latin_name; show(latin); } else { hide(latin); }
        } else {
            hide(latin);
            badge.textContent = '⛰️ Hegycsúcs';
            badge.className = 'quiz-badge mountain';
            hide($('quizBirdFields')); show($('quizMountainFields'));
            resetSlider($('quizElevation'), $('quizElevationValue'), 4500);
            buildCountryChoices($('quizCountryChoices'), q.countryOptions || [], $('quizCountry'));
        }
        validate();
        startTimer(elapsedSeconds);
    }

    /* ---------- Időmérés + szorzó ---------- */

    function multiplierFor(sec) { return Math.max(1, 2.5 - 0.07 * sec); }

    /* elapsedSeconds: ha a kérdés egy oldalfrissítés után ugyanaz maradt (csalás
       elleni védelem), a szerver által már mért idővel folytatjuk a számlálást,
       nem nullától — így a látható időzítő sosem téveszt meg a tényleges pontszámról. */
    function startTimer(elapsedSeconds) {
        state.start = Date.now() - (Number(elapsedSeconds) || 0) * 1000;
        tick();
        state.timer = setInterval(tick, 100);
    }
    function stopTimer() { if (state.timer) { clearInterval(state.timer); state.timer = null; } }
    function tick() {
        var sec = (Date.now() - state.start) / 1000;
        var m = multiplierFor(sec);
        $('quizTimerTime').textContent = num(sec, 1) + ' mp';
        var mult = $('quizMult');
        mult.textContent = '×' + num(m, 2);
        mult.classList.toggle('low', m <= 1.01);
    }

    /* ---------- Beküldhetőség ---------- */

    function validate() {
        var ok = false, q = state.question;
        if (q) {
            if (q.type === 'bird') {
                ok = true; // a csúszkának mindig van értéke
            } else {
                ok = $('quizCountry').value !== '';
            }
        }
        $('quizSubmit').disabled = !ok;
    }

    /* ---------- Válasz beküldése ---------- */

    function submit() {
        var q = state.question;
        if (!q) return;
        stopTimer();
        $('quizSubmit').disabled = true;

        var payload = { csrf_token: CFG.csrf, type: q.type, id: q.id };
        if (q.type === 'bird') {
            payload.wingspan = parseInt($('quizWingspan').value, 10);
            payload.colors = selectedPills($('quizColors'));
            payload.continents = selectedPills($('quizContinents'));
        } else {
            payload.elevation = parseInt($('quizElevation').value, 10);
            payload.country = $('quizCountry').value;
        }

        fetch(CFG.urls.answer, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        })
            .then(function (r) { return r.json().then(function (d) { return { status: r.status, d: d }; }); })
            .then(function (res) {
                if (res.status === 409) { setError(res.d.error || 'Ez a kérdés már nem aktív.'); loadQuestion(); return; }
                if (res.d.error) { setError(res.d.error); $('quizSubmit').disabled = false; startTimer(); return; }
                renderResult(res.d, q);
            })
            .catch(function () { setError('Nem sikerült beküldeni a választ. Próbáld újra!'); $('quizSubmit').disabled = false; });
    }

    function renderResult(data, q) {
        updateLifetimeProgress(data.lifetimeProgress);
        state.game = data.game;
        updateRoundProgress(state.game);
        hide($('quizQuestion'));
        show($('quizResult'));
        $('quizResult').querySelector('.quiz-result-head').hidden = false;
        $('quizResult').querySelector('.quiz-sub').hidden = false;
        $('quizResultBody').hidden = false;

        $('quizScoreVal').textContent = num(data.score);
        $('quizResultMeta').innerHTML =
            'Válaszidő: <b>' + num(data.seconds, 1) + ' mp</b> &middot; Időszorzó: <b>×' +
            num(data.breakdown.multiplier, 2) + '</b>';
        $('quizResultName').textContent = q.name;
        $('quizResultTitle').textContent = q.name;
        var latinEl = $('quizResultLatin');
        if (q.type === 'bird' && q.latin_name) { latinEl.textContent = q.latin_name; show(latinEl); }
        else { hide(latinEl); }

        var rows = [];
        function row(label, pts, answerLine, cls) {
            rows.push(
                '<div class="quiz-brk-row ' + (cls || '') + '">' +
                '<div><span class="quiz-brk-label">' + esc(label) + '</span>' +
                (answerLine ? '<div class="quiz-brk-answer">' + answerLine + '</div>' : '') + '</div>' +
                '<span class="quiz-brk-val">' + pts + '</span></div>'
            );
        }
        var b = data.breakdown, c = data.correct, g = data.given;

        if (q.type === 'bird') {
            row('Fesztáv', num(b.wingspan) + ' pont',
                'Tipped: <b>' + esc(num(g.wingspan)) + ' cm</b> &middot; Valós: <b>' + esc(num(c.wingspan)) + ' cm</b>',
                Number(g.wingspan) === Number(c.wingspan) ? 'correct' : '');
            row('Színek', num(b.colors) + ' pont',
                'Tipped: ' + listOrDash(g.colors) + ' &middot; Valós: ' + listOrDash(c.colors),
                b.colors > 0 ? 'correct' : (b.colors < 0 ? 'wrong' : ''));
            row('Kontinensek', num(b.continents) + ' pont',
                'Tipped: ' + listOrDash(g.continents) + ' &middot; Valós: ' + listOrDash(c.continents),
                b.continents > 0 ? 'correct' : (b.continents < 0 ? 'wrong' : ''));
        } else {
            var okCountry = (g.country || '') === c.country;
            row('Ország', num(b.country) + ' pont',
                'Tipped: <b>' + (g.country ? esc(g.country) : '—') + '</b> &middot; Valós: <b>' + esc(c.country) + '</b>',
                okCountry ? 'correct' : 'wrong');
            row('Magasság', num(b.elevation) + ' pont',
                'Tipped: <b>' + esc(num(g.elevation)) + ' m</b> &middot; Valós: <b>' + esc(num(c.elevation)) + ' m</b>',
                Number(g.elevation) === Number(c.elevation) ? 'correct' : '');
        }
        row('Időszorzó', '×' + num(b.multiplier, 2), 'Alappont összesen: <b>' + num(b.raw) + '</b>');
        row('Végső pontszám', num(data.score) + ' pont', '', 'quiz-brk-total');

        $('quizBreakdown').innerHTML = rows.join('');
        arrangeResultBody();
        renderQuickStats(data, q);
        renderFacts(data.facts, q.type);
        renderFactImage(data.facts, q);
        $('quizResultToplist').innerHTML = toplistTable(data.toplist);

        if (data.gameOver) {
            hide($('quizNext'));
            renderRoundOver(data.gameOver);
        } else {
            show($('quizNext'));
        }
    }

    /* A kör vége felugró ablakban jelenik meg — bezárva az utolsó kérdés adatai
       és képe (ami mögötte, az eredmény képernyőn már renderelve van) továbbra
       is látható marad. */
    function renderRoundOver(gameOver) {
        $('quizRoundOverScore').textContent = num(gameOver.totalScore);
        $('quizRoundOverText').textContent = gameOver.poolExhausted
            ? 'Elfogytak a kérdések — ' + num(gameOver.questionCount) + ' kérdésre válaszoltál ebben a körben.'
            : num(gameOver.questionCount) + ' kérdésre válaszoltál ebben a körben.';
        $('quizRoundOverModal').classList.add('open');
    }

    /* Kép, érdekesség, táplálkozás/hegység a lebontás elé kerül — mindkét kérdéstípusnál. */
    function arrangeResultBody() {
        var body = $('quizResultBody');
        ['quizQuickStats', 'quizFacts', 'quizFactImage', 'quizBreakdown'].forEach(function (id) {
            body.appendChild($(id));
        });
    }

    function renderQuickStats(data, q) {
        var box = $('quizQuickStats');
        var c = data.correct;
        if (q.type === 'bird') {
            box.innerHTML =
                quickStatItem('🌍', 'Kontinens', listOrDash(c.continents)) +
                quickStatItem('📏', 'Fesztáv', '<b>' + esc(num(c.wingspan)) + ' cm</b>');
        } else {
            box.innerHTML =
                quickStatItem('📍', 'Ország', '<b>' + esc(c.country) + '</b>') +
                quickStatItem('⛰️', 'Magasság', '<b>' + esc(num(c.elevation)) + ' m</b>');
        }
        show(box);
    }
    function quickStatItem(icon, label, valueHtml) {
        return '<div class="quiz-qs-item"><span class="quiz-qs-icon">' + icon + '</span>' +
            '<div><div class="quiz-qs-label">' + esc(label) + '</div>' +
            '<div class="quiz-qs-value">' + valueHtml + '</div></div></div>';
    }

    function renderFacts(facts, type) {
        var box = $('quizFacts');
        if (!facts) { hide(box); return; }
        var cards = [];
        if (type === 'bird') {
            if (facts.fun_fact) cards.push(factCard('💡', 'Érdekesség', facts.fun_fact));
            if (facts.diet) cards.push(factCard('🍽️', 'Táplálkozás', facts.diet));
        } else {
            if (facts.fun_fact) cards.push(factCard('💡', 'Érdekesség', facts.fun_fact));
            if (facts.mountain_range) cards.push(factCard('⛰️', 'Hegység', facts.mountain_range));
        }
        if (!cards.length) { hide(box); return; }
        box.innerHTML = cards.join('');
        show(box);
    }
    function renderFactImage(facts, q) {
        var box = $('quizFactImage');
        if (!facts || !facts.image) { box.innerHTML = ''; hide(box); return; }
        box.innerHTML = factImage(facts.image, q ? q.name : '');
        show(box);
    }
    function factImage(src, alt) {
        return '<div class="quiz-fact-image"><img src="' + esc(src) + '" alt="' + esc(alt) + '" loading="lazy"></div>';
    }
    function factCard(icon, label, text) {
        return '<div class="quiz-fact-card"><div class="quiz-fact-icon">' + icon + '</div>' +
            '<div><div class="quiz-fact-label">' + esc(label) + '</div>' +
            '<div class="quiz-fact-text">' + esc(text) + '</div></div></div>';
    }

    function listOrDash(arr) {
        if (!arr || !arr.length) return '<b>—</b>';
        return '<b>' + arr.map(esc).join(', ') + '</b>';
    }

    /* ---------- Toplista renderelés (az adott kérdés saját toplistája) ---------- */

    function toplistTable(rows) {
        if (!rows || !rows.length) return '<div class="quiz-empty">Még nincs eredmény ehhez a kérdéshez.</div>';
        var medals = ['🥇', '🥈', '🥉'];
        var body = rows.map(function (r) {
            return '<tr class="' + (r.isMe ? 'quiz-tl-me' : '') + '">' +
                '<td class="quiz-tl-rank">' + (medals[r.rank - 1] || (r.rank + '.')) + '</td>' +
                '<td class="quiz-tl-name">' + esc(r.name) + (r.isMe ? ' (te)' : '') + '</td>' +
                '<td class="quiz-tl-score">' + num(r.score) + ' pont</td></tr>';
        }).join('');
        return '<table class="quiz-tl-table"><tbody>' + body + '</tbody></table>';
    }

    /* ---------- Előrehaladás ---------- */

    function updateLifetimeProgress(p) {
        if (!p) return;
        var pct = p.total ? Math.round(p.answered / p.total * 100) : 0;
        $('quizProgressFill').style.width = pct + '%';
        $('quizProgressLabel').textContent = num(p.answered) + ' / ' + num(p.total) + ' kérdés megválaszolva összesen';
    }

    function updateRoundProgress(game) {
        var bar = $('quizRoundBar');
        if (!game) { hide(bar); return; }
        $('quizRoundProgress').textContent = 'Kérdés ' + num(game.questionNumber) + ' / ' + num(game.roundSize);
        $('quizRoundScoreLabel').textContent = num(game.roundScore) + ' pont eddig ebben a körben';
        show(bar);
    }

    /* ---------- Init ---------- */

    function init() {
        buildPills($('quizColors'), CFG.options.colors, true);
        buildPills($('quizContinents'), CFG.options.continents, false);
        initSlider($('quizWingspan'), $('quizWingspanValue'));
        initSlider($('quizElevation'), $('quizElevationValue'));

        $('quizColors').addEventListener('click', validate);
        $('quizContinents').addEventListener('click', validate);
        $('quizSubmit').addEventListener('click', submit);
        $('quizNext').addEventListener('click', loadQuestion);
        $('quizStartBtn').addEventListener('click', startGame);
        $('quizPlayAgain').addEventListener('click', startGame);

        if (CFG.activeGame) {
            loadQuestion();
        }
        // Ha nincs aktív kör és van még kérdés, a szerver már megjelenítette az induló
        // képernyőt (#quizStart); ha nincs több kérdés soha, a #quizNoMore-t.
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
