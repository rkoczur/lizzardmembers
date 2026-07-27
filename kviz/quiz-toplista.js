/* Kvíz toplisták — kliens logika (natív JS). */
(function () {
    'use strict';

    var CFG = window.QUIZ_TL;
    if (!CFG) { return; }

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
                        ' <small>(' + num(r.questionCount) + ' kérdéses kör)</small></td>' +
                        '<td class="quiz-tl-score">' + num(r.score) + ' pont</td></tr>';
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
                    var count = r.answerCount || 1;
                    return '<button type="button" class="quiz-mine-item" data-type="' + r.type + '" data-id="' + r.id + '">' +
                        '<span class="quiz-mine-name">' + icon + ' ' + esc(r.name) + '</span>' +
                        '<span class="quiz-mine-meta">' +
                        '<span class="quiz-mine-score">' + num(r.score) + '</span>' +
                        '<span class="quiz-mine-count">' + num(count) + ' fő válaszolt</span>' +
                        '</span></button>';
                }).join('') + '</div>';
                mineLoaded = true;
            })
            .catch(function () { $('quizMine').innerHTML = '<div class="quiz-empty">Nem sikerült betölteni.</div>'; });
    }

    function loadItemToplist(type, id, btn) {
        Array.prototype.forEach.call(document.querySelectorAll('.quiz-mine-item'), function (b) { b.classList.remove('active'); });
        if (btn) btn.classList.add('active');
        var box = $('quizItemToplist');
        if (btn) btn.insertAdjacentElement('afterend', box);
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

    function init() {
        Array.prototype.forEach.call(document.querySelectorAll('.quiz-tab'), function (t) {
            t.addEventListener('click', function () { switchTab(t.dataset.tab); });
        });
        $('quizMine').addEventListener('click', function (e) {
            var item = e.target.closest('.quiz-mine-item');
            if (!item) return;
            var box = $('quizItemToplist');
            if (item.classList.contains('active') && !box.hidden) {
                item.classList.remove('active');
                hide(box);
                return;
            }
            loadItemToplist(item.dataset.type, +item.dataset.id, item);
        });

        loadOverall();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
