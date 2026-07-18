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

    var state = { question: null, timer: null, start: 0 };

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

    /* Kereshető ország-legördülő. */
    var combo = { open: false, active: -1, filtered: [] };
    function initCombo() {
        var input = $('quizCountryInput'), list = $('quizCountryList'), hidden = $('quizCountry');

        function render(items) {
            combo.filtered = items;
            combo.active = -1;
            if (!items.length) {
                list.innerHTML = '<div class="quiz-combo-empty">Nincs találat</div>';
            } else {
                list.innerHTML = items.map(function (c, i) {
                    return '<div class="quiz-combo-opt" data-i="' + i + '">' + esc(c) + '</div>';
                }).join('');
            }
            show(list); combo.open = true; input.setAttribute('aria-expanded', 'true');
        }
        function filter() {
            var q = input.value.trim().toLowerCase();
            var all = CFG.options.countries;
            var items = q ? all.filter(function (c) { return c.toLowerCase().indexOf(q) !== -1; }) : all.slice();
            render(items.slice(0, 60));
        }
        function choose(val) {
            input.value = val; hidden.value = val;
            input.classList.add('chosen');
            hide(list); combo.open = false; input.setAttribute('aria-expanded', 'false');
            validate();
        }

        input.addEventListener('focus', filter);
        input.addEventListener('input', function () {
            hidden.value = ''; input.classList.remove('chosen');
            filter(); validate();
        });
        input.addEventListener('keydown', function (e) {
            if (!combo.open) return;
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                var d = e.key === 'ArrowDown' ? 1 : -1;
                combo.active = Math.max(0, Math.min(combo.filtered.length - 1, combo.active + d));
                Array.prototype.forEach.call(list.children, function (ch, i) {
                    ch.classList.toggle('active', i === combo.active);
                });
                var act = list.children[combo.active]; if (act) act.scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter') {
                if (combo.active >= 0 && combo.filtered[combo.active]) { e.preventDefault(); choose(combo.filtered[combo.active]); }
            } else if (e.key === 'Escape') {
                hide(list); combo.open = false;
            }
        });
        list.addEventListener('mousedown', function (e) {
            var opt = e.target.closest('.quiz-combo-opt');
            if (opt) { e.preventDefault(); choose(combo.filtered[+opt.dataset.i]); }
        });
        document.addEventListener('click', function (e) {
            if (!$('quizCountryCombo').contains(e.target)) { hide(list); combo.open = false; input.setAttribute('aria-expanded', 'false'); }
        });
    }
    function resetCombo() {
        $('quizCountryInput').value = '';
        $('quizCountryInput').classList.remove('chosen');
        $('quizCountry').value = '';
        hide($('quizCountryList'));
    }

    /* Csak számjegyeket engedő mezők. */
    function initNumeric(input) {
        input.addEventListener('input', function () {
            var v = input.value.replace(/[^0-9]/g, '');
            if (v !== input.value) input.value = v;
            validate();
        });
    }

    /* ---------- Kérdés betöltése ---------- */

    function setError(msg) {
        clearError();
        var box = document.createElement('div');
        box.className = 'quiz-error'; box.id = 'quizError';
        box.textContent = msg;
        $('quiz').insertBefore(box, $('quizQuestion'));
    }
    function clearError() { var e = $('quizError'); if (e) e.remove(); }

    function loadQuestion() {
        stopTimer();
        clearError();
        hide($('quizResult')); hide($('quizDone'));
        fetch(CFG.urls.question, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) { setError(data.error); return; }
                updateProgress(data.progress);
                if (data.done) { hide($('quizQuestion')); show($('quizDone')); return; }
                renderQuestion(data.question);
            })
            .catch(function () { setError('Nem sikerült betölteni a kérdést. Próbáld újra!'); });
    }

    function renderQuestion(q) {
        state.question = q;
        show($('quizQuestion'));

        $('quizName').textContent = q.name;
        var latin = $('quizLatin');
        var badge = $('quizBadge');
        if (q.type === 'bird') {
            badge.textContent = '🐦 Madár';
            badge.className = 'quiz-badge bird';
            show($('quizBirdFields')); hide($('quizMountainFields'));
            $('quizWingspan').value = '';
            resetPills($('quizColors')); resetPills($('quizContinents'));
            if (q.latin_name) { latin.textContent = q.latin_name; show(latin); } else { hide(latin); }
        } else {
            hide(latin);
            badge.textContent = '⛰️ Hegycsúcs';
            badge.className = 'quiz-badge mountain';
            hide($('quizBirdFields')); show($('quizMountainFields'));
            $('quizElevation').value = '';
            resetCombo();
        }
        validate();
        startTimer();
    }

    /* ---------- Időmérés + szorzó ---------- */

    function multiplierFor(sec) { return Math.max(1, 3 - 0.025 * sec); }

    function startTimer() {
        state.start = Date.now();
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
                ok = $('quizWingspan').value !== '';
            } else {
                ok = $('quizElevation').value !== '' && $('quizCountry').value !== '';
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
        updateProgress(data.progress);
        hide($('quizQuestion'));
        show($('quizResult'));

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

        // A frissen megválaszolt kérdés megjelenhet a "Megválaszolt kérdéseim" listában.
        mineLoaded = false;
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

    /* ---------- Toplista renderelés ---------- */

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

    function updateProgress(p) {
        if (!p) return;
        var pct = p.total ? Math.round(p.answered / p.total * 100) : 0;
        $('quizProgressFill').style.width = pct + '%';
        $('quizProgressLabel').textContent = num(p.answered) + ' / ' + num(p.total) + ' kérdés megválaszolva';
    }

    /* ---------- Toplista fülek ---------- */

    var overallLoaded = false, mineLoaded = false;

    function loadOverall() {
        fetch(CFG.urls.leaderboard + '?view=overall', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var box = $('quizOverall');
                if (!data.rows || !data.rows.length) { box.innerHTML = '<div class="quiz-empty">Még senki sem játszott. Légy te az első! 🐦⛰️</div>'; return; }
                var medals = ['🥇', '🥈', '🥉'];
                var body = data.rows.map(function (r) {
                    return '<tr class="' + (r.isMe ? 'quiz-tl-me' : '') + '">' +
                        '<td class="quiz-tl-rank">' + (medals[r.rank - 1] || (r.rank + '.')) + '</td>' +
                        '<td class="quiz-tl-name">' + esc(r.name) + (r.isMe ? ' (te)' : '') +
                        ' <small>(' + num(r.count) + ' kérdés)</small></td>' +
                        '<td class="quiz-tl-score">' + num(r.average, 1) + ' átlag</td></tr>';
                }).join('');
                box.innerHTML = '<table class="quiz-tl-table"><tbody>' + body + '</tbody></table>';
                overallLoaded = true;
            })
            .catch(function () { $('quizOverall').innerHTML = '<div class="quiz-empty">Nem sikerült betölteni.</div>'; });
    }

    function loadMine() {
        hide($('quizItemToplist'));
        fetch(CFG.urls.leaderboard + '?view=mine', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var box = $('quizMine');
                if (!data.rows || !data.rows.length) { box.innerHTML = '<div class="quiz-empty">Még egy kérdésre sem válaszoltál.</div>'; return; }
                box.innerHTML = '<div class="quiz-mine-grid">' + data.rows.map(function (r) {
                    var icon = r.type === 'bird' ? '🐦' : '⛰️';
                    return '<button type="button" class="quiz-mine-item" data-type="' + r.type + '" data-id="' + r.id + '">' +
                        '<span class="quiz-mine-name">' + icon + ' ' + esc(r.name) + '</span>' +
                        '<span class="quiz-mine-score">' + num(r.score) + '</span></button>';
                }).join('') + '</div>';
                mineLoaded = true;
            })
            .catch(function () { $('quizMine').innerHTML = '<div class="quiz-empty">Nem sikerült betölteni.</div>'; });
    }

    function loadItemToplist(type, id, btn) {
        Array.prototype.forEach.call(document.querySelectorAll('.quiz-mine-item'), function (b) { b.classList.remove('active'); });
        if (btn) btn.classList.add('active');
        var box = $('quizItemToplist');
        box.innerHTML = '<div class="quiz-empty">Betöltés…</div>';
        show(box);
        fetch(CFG.urls.leaderboard + '?view=item&type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(id), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.error) { box.innerHTML = '<div class="quiz-empty">' + esc(data.error) + '</div>'; return; }
                box.innerHTML = '<h4>' + (data.type === 'bird' ? '🐦' : '⛰️') + ' ' + esc(data.name) + ' — toplista</h4>' + toplistTable(data.toplist);
            })
            .catch(function () { box.innerHTML = '<div class="quiz-empty">Nem sikerült betölteni.</div>'; });
    }

    function switchTab(tab) {
        Array.prototype.forEach.call(document.querySelectorAll('.quiz-tab'), function (t) {
            t.classList.toggle('active', t.dataset.tab === tab);
        });
        $('quizTabOverall').hidden = tab !== 'overall';
        $('quizTabMine').hidden = tab !== 'mine';
        if (tab === 'overall' && !overallLoaded) loadOverall();
        if (tab === 'mine' && !mineLoaded) loadMine();
    }

    /* ---------- Init ---------- */

    function init() {
        buildPills($('quizColors'), CFG.options.colors, true);
        buildPills($('quizContinents'), CFG.options.continents, false);
        initCombo();
        initNumeric($('quizWingspan'));
        initNumeric($('quizElevation'));

        $('quizColors').addEventListener('click', validate);
        $('quizContinents').addEventListener('click', validate);
        $('quizSubmit').addEventListener('click', submit);
        $('quizNext').addEventListener('click', loadQuestion);

        Array.prototype.forEach.call(document.querySelectorAll('.quiz-tab'), function (t) {
            t.addEventListener('click', function () { switchTab(t.dataset.tab); });
        });
        $('quizMine').addEventListener('click', function (e) {
            var item = e.target.closest('.quiz-mine-item');
            if (item) loadItemToplist(item.dataset.type, +item.dataset.id, item);
        });

        loadQuestion();
        loadOverall();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
