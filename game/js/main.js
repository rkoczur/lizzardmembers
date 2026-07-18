/* =========================================================
   Túra a csúcsra — fő játékciklus, HUD, menük, bemenet
   ========================================================= */
'use strict';

(function () {
    if (typeof window === 'undefined') return; // node-teszt alatt nem indul el

    const canvas = document.getElementById('gameCanvas');
    const ctx = canvas.getContext('2d');
    const W = canvas.width, H = canvas.height;
    const TOTAL_LEVELS = 19;
    const SAVE_KEY = 'turaACsucsra_v1';

    // --- mentés / betöltés ---
    // Tagoldalon (window.MEMBER_GAME) a szerverről kapott eredmények az elsődlegesek.
    function loadSave() {
        if (window.MEMBER_GAME && window.MEMBER_GAME.initial) {
            const s = window.MEMBER_GAME.initial;
            return { unlocked: (s.unlocked | 0) || 1, best: s.best || {} };
        }
        try {
            const s = JSON.parse(localStorage.getItem(SAVE_KEY));
            if (s && typeof s.unlocked === 'number') return s;
        } catch (e) { /* sérült mentés */ }
        return { unlocked: 1, best: {} };
    }
    function persist() { try { localStorage.setItem(SAVE_KEY, JSON.stringify(save)); } catch (e) { } }
    // Tagoldalon: pályaeredmény elküldése a szervernek (fiókhoz mentés). Hibát csendben nyel.
    function syncResult(level, time) {
        const M = window.MEMBER_GAME;
        if (!M || !M.saveUrl) return;
        try {
            const body = new URLSearchParams();
            body.set('csrf_token', M.csrf);
            body.set('level', String(level));
            body.set('time', String(time));
            fetch(M.saveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
                credentials: 'same-origin',
                keepalive: true,
            }).catch(() => { });
        } catch (e) { /* a mentés hibája nem törheti meg a játékot */ }
    }
    let save = loadSave();

    // --- bemenet ---
    const input = {
        left: false, right: false, down: false, slow: false,
        jumpPressed: false, attackPressed: false, photoPressed: false,
    };

    const game = {
        state: 'menu',        // menu | playing | paused | gameover | selfie | complete
        lvl: null, player: null,
        cam: { x: 0, y: 0 },
        time: 0,
        levelIndex: 1,
        effects: [],
        menuPage: 0,
        selfieT: 0,
        hover: -1,
    };

    function startLevel(n) {
        game.levelIndex = n;
        game.lvl = generateLevel(n);
        game.player = makePlayer(game.lvl);
        game.time = 0;
        game.effects = [];
        game.state = 'playing';
        game.cam.x = 0;
        game.cam.y = game.player.y - H * 0.6;
    }

    // --- billentyűzet ---
    window.addEventListener('keydown', (e) => {
        const p = game.player;
        switch (e.code) {
            case 'ArrowLeft': input.left = true; e.preventDefault(); break;
            case 'ArrowRight': input.right = true; e.preventDefault(); break;
            case 'ArrowDown': input.down = true; e.preventDefault(); break;
            case 'AltLeft': case 'AltRight': input.slow = true; e.preventDefault(); break;
            case 'ArrowUp':
                if (!e.repeat) input.jumpPressed = true;
                e.preventDefault(); break;
            case 'ShiftLeft': case 'ShiftRight':
                if (!e.repeat) input.attackPressed = true;
                break;
            case 'ControlLeft': case 'ControlRight':
                // AltGr Windowson egy szintetikus Ctrl-t is küld — ne váltson nadrágot
                if (e.getModifierState && e.getModifierState('AltGraph')) { input.slow = true; break; }
                if (!e.repeat && game.state === 'playing' && p) {
                    p.trousersOn = !p.trousersOn;
                    sayHUD(p, p.trousersOn ? 'Nadrág felvéve' : 'Nadrág levéve — mehet a vízátkelés!');
                }
                e.preventDefault(); break;
            case 'KeyS':
                if (!e.repeat && game.state === 'playing' && p) {
                    p.sunscreen = 100;
                    sayHUD(p, 'Naptej felkenve ☀');
                }
                break;
            case 'KeyL':
                if (!e.repeat && game.state === 'playing' && p) {
                    p.headlight = !p.headlight;
                    sayHUD(p, p.headlight ? 'Fejlámpa bekapcsolva' : 'Fejlámpa kikapcsolva');
                }
                break;
            case 'KeyP':
                if (!e.repeat) {
                    if (game.state === 'playing' && p && p.atSummit && !p.selfieDone) {
                        doSelfie();
                    } else if (game.state === 'playing') {
                        input.photoPressed = true;
                    }
                }
                break;
            case 'Escape':
                if (game.state === 'playing') game.state = 'paused';
                else if (game.state === 'paused') game.state = 'menu'; // Esc mégegyszer = kilépés a szintből
                else if (game.state === 'gameover' || game.state === 'complete') game.state = 'menu';
                break;
            case 'Enter':
                if (game.state === 'paused') game.state = 'playing'; // Enter = folytatás
                else if (game.state === 'gameover') startLevel(game.levelIndex);
                else if (game.state === 'complete') {
                    if (game.levelIndex < TOTAL_LEVELS) startLevel(game.levelIndex + 1);
                    else game.state = 'menu';
                }
                break;
        }
    });
    window.addEventListener('keyup', (e) => {
        switch (e.code) {
            case 'ArrowLeft': input.left = false; break;
            case 'ArrowRight': input.right = false; break;
            case 'ArrowDown': input.down = false; break;
            case 'AltLeft': case 'AltRight': input.slow = false; break;
        }
    });
    // Ha az ablak elveszti a fókuszt (Alt+Tab, StickyKeys-ablak, fülváltás),
    // a böngésző elnyelheti a keyup-ot → a gomb "beragadna". Ilyenkor mindent elengedünk.
    function releaseAllKeys() {
        input.left = input.right = input.down = input.slow = false;
        input.jumpPressed = input.attackPressed = input.photoPressed = false;
    }
    window.addEventListener('blur', releaseAllKeys);
    document.addEventListener('visibilitychange', () => { if (document.hidden) releaseAllKeys(); });

    function sayHUD(p, text) { p.msg = text; p.msgT = 2; }

    function doSelfie() {
        const p = game.player;
        p.selfieDone = true;
        p.flashT = 0.5;
        game.state = 'selfie';
        game.selfieT = 0;
        // legjobb idő mentése
        const t = Math.round(game.time * 10) / 10;
        const key = String(game.levelIndex);
        if (!save.best[key] || t < save.best[key]) save.best[key] = t;
        if (game.levelIndex >= save.unlocked && game.levelIndex < TOTAL_LEVELS) {
            save.unlocked = game.levelIndex + 1;
        }
        persist();
        syncResult(game.levelIndex, t); // tagoldalon a fiókhoz is elmentjük
    }

    // --- egér a menühöz ---
    function canvasPos(e) {
        const r = canvas.getBoundingClientRect();
        return { x: (e.clientX - r.left) * (W / r.width), y: (e.clientY - r.top) * (H / r.height) };
    }
    function levelBoxes() {
        const boxes = [];
        const cols = 5, bw = 158, bh = 74, gapX = 22, gapY = 18;
        const x0 = (W - cols * bw - (cols - 1) * gapX) / 2, y0 = 130;
        for (let i = 0; i < TOTAL_LEVELS; i++) {
            const c = i % cols, r = Math.floor(i / cols);
            boxes.push({ n: i + 1, x: x0 + c * (bw + gapX), y: y0 + r * (bh + gapY), w: bw, h: bh });
        }
        return boxes;
    }
    canvas.addEventListener('mousemove', (e) => {
        if (game.state !== 'menu') { game.hover = -1; return; }
        const m = canvasPos(e);
        game.hover = -1;
        for (const b of levelBoxes()) {
            if (m.x > b.x && m.x < b.x + b.w && m.y > b.y && m.y < b.y + b.h && b.n <= save.unlocked) game.hover = b.n;
        }
        canvas.style.cursor = game.hover > 0 ? 'pointer' : 'default';
    });
    canvas.addEventListener('click', (e) => {
        const m = canvasPos(e);
        if (game.state === 'menu') {
            for (const b of levelBoxes()) {
                if (m.x > b.x && m.x < b.x + b.w && m.y > b.y && m.y < b.y + b.h && b.n <= save.unlocked) {
                    startLevel(b.n);
                    return;
                }
            }
        } else if (game.state === 'gameover') {
            startLevel(game.levelIndex);
        } else if (game.state === 'complete') {
            if (game.levelIndex < TOTAL_LEVELS) startLevel(game.levelIndex + 1);
            else game.state = 'menu';
        } else if (game.state === 'paused') {
            game.state = 'playing';
        }
    });

    // --- HUD ---
    function fmtTime(t) {
        const m = Math.floor(t / 60), s = Math.floor(t % 60);
        return m + ':' + String(s).padStart(2, '0');
    }
    function drawBar(x, y, w, val, color, label, icon) {
        ctx.fillStyle = 'rgba(20,26,34,0.65)';
        roundRectPath(ctx, x, y, w, 16, 8); ctx.fill();
        ctx.fillStyle = color;
        if (val > 0) { roundRectPath(ctx, x + 2, y + 2, Math.max(6, (w - 4) * Math.min(1, val / 100)), 12, 6); ctx.fill(); }
        ctx.fillStyle = '#fff'; ctx.font = 'bold 11px sans-serif'; ctx.textAlign = 'left';
        ctx.fillText(icon + ' ' + label, x + 6, y + 12);
    }
    function drawHUD() {
        const p = game.player, lvl = game.lvl;
        // szívek
        ctx.font = '22px sans-serif'; ctx.textAlign = 'left';
        for (let i = 0; i < 3; i++) {
            ctx.globalAlpha = i < p.hearts ? 1 : 0.25;
            ctx.fillText('❤', 16 + i * 26, 30);
        }
        ctx.globalAlpha = 1;
        // sávok
        drawBar(16, 42, 170, p.sunburn, '#e07840', 'Leégés', '☀');
        drawBar(16, 62, 170, p.hunger, '#8bbf4d', 'Jóllakottság', '🍎');
        drawBar(16, 82, 170, p.sunscreen, '#f2e28a', 'Naptej', '🧴');
        // állapotikonok
        ctx.font = '13px sans-serif'; ctx.fillStyle = '#fff';
        let iconY = 118;
        ctx.fillText(p.trousersOn ? '👖 Nadrág: fent' : '🩳 Nadrág: lent', 16, iconY); iconY += 18;
        if (p.headlight) { ctx.fillText('🔦 Fejlámpa: be', 16, iconY); iconY += 18; }
        if (p.sneaking) { ctx.fillText('🤫 Lopakodás', 16, iconY); iconY += 18; }
        // idő + pálya
        ctx.textAlign = 'right'; ctx.font = 'bold 16px sans-serif';
        ctx.fillStyle = 'rgba(20,26,34,0.65)';
        roundRectPath(ctx, W - 250, 12, 238, 46, 8); ctx.fill();
        ctx.fillStyle = '#fff';
        ctx.fillText('⏱ ' + fmtTime(game.time), W - 22, 32);
        ctx.font = '12px sans-serif';
        ctx.fillText(game.levelIndex + '. ' + lvl.name + ' (' + lvl.height + ' m)', W - 22, 50);
        // haladás a csúcsig + aktuális magasság
        const prog = Math.min(1, (p.x / lvl.summitX));
        const elev = elevationAt(lvl, p.y + p.h); // a játékos lába a talajszinten
        ctx.fillStyle = 'rgba(20,26,34,0.65)';
        roundRectPath(ctx, W / 2 - 130, 14, 260, 12, 6); ctx.fill();
        ctx.fillStyle = '#9fd08c';
        roundRectPath(ctx, W / 2 - 128, 16, 256 * prog, 8, 4); ctx.fill();
        ctx.font = 'bold 11px sans-serif'; ctx.fillStyle = '#fff'; ctx.textAlign = 'center';
        ctx.fillText('⛰ ' + elev + ' m — ' + Math.round(prog * 100) + '%', W / 2, 40);
        ctx.textAlign = 'left';
    }

    // --- menü ---
    function drawMenu() {
        const g = ctx.createLinearGradient(0, 0, 0, H);
        g.addColorStop(0, '#27364a'); g.addColorStop(1, '#141b26');
        ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
        // háttér hegyek
        drawHills(ctx, W, H, 0, 'rgba(120,150,180,0.25)', H * 0.8, 300, 3, true);

        ctx.textAlign = 'center';
        ctx.fillStyle = '#fff'; ctx.font = 'bold 34px sans-serif';
        ctx.fillText('⛰ TÚRA A CSÚCSRA ⛰', W / 2, 62);
        ctx.font = '15px sans-serif'; ctx.fillStyle = '#9fd08c';
        ctx.fillText('Válassz hegyet! Mászd meg mind a 19 csúcsot, és készíts szelfit a csúcskeresztnél!', W / 2, 92);

        ctx.font = '12px sans-serif';
        for (const b of levelBoxes()) {
            const unlocked = b.n <= save.unlocked;
            const hovered = game.hover === b.n;
            ctx.fillStyle = unlocked ? (hovered ? '#3e6b8c' : '#2c4258') : 'rgba(44,58,74,0.5)';
            roundRectPath(ctx, b.x, b.y, b.w, b.h, 8); ctx.fill();
            if (hovered) { ctx.strokeStyle = '#9fd08c'; ctx.lineWidth = 2; roundRectPath(ctx, b.x, b.y, b.w, b.h, 8); ctx.stroke(); }
            const meta = levelMeta(b.n);
            ctx.fillStyle = unlocked ? '#fff' : 'rgba(255,255,255,0.3)';
            ctx.font = 'bold 13px sans-serif';
            ctx.fillText(b.n + '. ' + meta.name, b.x + b.w / 2, b.y + 24);
            ctx.font = '11px sans-serif';
            ctx.fillStyle = unlocked ? '#a9c2d8' : 'rgba(255,255,255,0.25)';
            ctx.fillText(meta.height + ' m', b.x + b.w / 2, b.y + 42);
            if (!unlocked) {
                ctx.font = '15px sans-serif';
                ctx.fillText('🔒', b.x + b.w / 2, b.y + 62);
            } else if (save.best[String(b.n)]) {
                ctx.fillStyle = '#ffd94d';
                ctx.fillText('★ ' + fmtTime(save.best[String(b.n)]), b.x + b.w / 2, b.y + 60);
            } else {
                ctx.fillStyle = '#9fd08c';
                ctx.fillText('Kattints az induláshoz!', b.x + b.w / 2, b.y + 60);
            }
        }
        ctx.textAlign = 'left';
    }

    function drawOverlayBox(title, lines, color) {
        ctx.fillStyle = 'rgba(10,14,20,0.72)'; ctx.fillRect(0, 0, W, H);
        ctx.fillStyle = 'rgba(35,47,64,0.95)';
        roundRectPath(ctx, W / 2 - 260, H / 2 - 110, 520, 220, 14); ctx.fill();
        ctx.textAlign = 'center';
        ctx.fillStyle = color; ctx.font = 'bold 30px sans-serif';
        ctx.fillText(title, W / 2, H / 2 - 60);
        ctx.fillStyle = '#e8eef5'; ctx.font = '16px sans-serif';
        lines.forEach((l, i) => ctx.fillText(l, W / 2, H / 2 - 20 + i * 28));
        ctx.textAlign = 'left';
    }

    function drawSelfie() {
        // elhalványuló játéktér + polaroid
        render(ctx, game);
        ctx.fillStyle = 'rgba(10,14,20,0.55)'; ctx.fillRect(0, 0, W, H);
        const t = Math.min(1, game.selfieT / 0.6);
        const py = H / 2 - 150 * t;
        ctx.save();
        ctx.translate(W / 2, py + 130);
        ctx.rotate(-0.05);
        // polaroid keret
        ctx.fillStyle = '#f5f2ea';
        ctx.fillRect(-160, -130, 320, 290);
        // fotó: ég + hegy + kereszt + túrázó
        const fx = -140, fy = -110, fw = 280, fh = 210;
        const skyG = ctx.createLinearGradient(0, fy, 0, fy + fh);
        skyG.addColorStop(0, '#7ec8f7'); skyG.addColorStop(1, '#d9f0ff');
        ctx.fillStyle = skyG; ctx.fillRect(fx, fy, fw, fh);
        ctx.fillStyle = game.lvl.tier === 2 ? '#dfe8f2' : '#7fae74';
        ctx.beginPath();
        ctx.moveTo(fx, fy + fh); ctx.lineTo(fx + fw * 0.5, fy + 40); ctx.lineTo(fx + fw, fy + fh);
        ctx.closePath(); ctx.fill();
        // kereszt — a hegy csúcsán áll (nem beleágyazva)
        const peakX = fx + fw * 0.5, peakY = fy + 40;
        ctx.fillStyle = '#6b4a2c';
        ctx.fillRect(peakX - 3, peakY - 52, 6, 54);   // függőleges szár, töve a csúcson
        ctx.fillRect(peakX - 15, peakY - 40, 30, 6);  // kereszttartó
        ctx.strokeStyle = 'rgba(0,0,0,0.2)'; ctx.lineWidth = 1;
        ctx.strokeRect(peakX - 3, peakY - 52, 6, 54);

        // --- mosolygó túrázó fej (részletes arc + túrakalap) ---
        const hx = fx + fw * 0.5 + 45, hy = fy + 150;
        // nyak
        ctx.fillStyle = '#d29b73'; ctx.fillRect(hx - 6, hy + 18, 12, 12);
        // fej
        ctx.fillStyle = '#eebb92';
        ctx.beginPath(); ctx.arc(hx, hy, 26, 0, 7); ctx.fill();
        // fülek
        ctx.beginPath(); ctx.arc(hx - 25, hy + 2, 5, 0, 7); ctx.arc(hx + 25, hy + 2, 5, 0, 7); ctx.fill();
        // pirospozsgás arc
        ctx.fillStyle = 'rgba(235,120,110,0.4)';
        ctx.beginPath(); ctx.arc(hx - 14, hy + 7, 5, 0, 7); ctx.arc(hx + 14, hy + 7, 5, 0, 7); ctx.fill();
        // szemöldök
        ctx.strokeStyle = '#5a3d28'; ctx.lineWidth = 2; ctx.lineCap = 'round';
        ctx.beginPath();
        ctx.moveTo(hx - 13, hy - 6); ctx.lineTo(hx - 4, hy - 8);
        ctx.moveTo(hx + 4, hy - 8); ctx.lineTo(hx + 13, hy - 6); ctx.stroke();
        // szemek (fehér + pupilla + csillanás)
        ctx.fillStyle = '#fff';
        ctx.beginPath(); ctx.ellipse(hx - 8, hy - 1, 4.5, 5.5, 0, 0, 7); ctx.ellipse(hx + 8, hy - 1, 4.5, 5.5, 0, 0, 7); ctx.fill();
        ctx.fillStyle = '#3a2a1a';
        ctx.beginPath(); ctx.arc(hx - 7, hy, 2.4, 0, 7); ctx.arc(hx + 9, hy, 2.4, 0, 7); ctx.fill();
        ctx.fillStyle = '#fff';
        ctx.beginPath(); ctx.arc(hx - 8, hy - 2, 1, 0, 7); ctx.arc(hx + 8, hy - 2, 1, 0, 7); ctx.fill();
        // orr
        ctx.strokeStyle = '#c98d63'; ctx.lineWidth = 2; ctx.lineJoin = 'round';
        ctx.beginPath(); ctx.moveTo(hx, hy + 1); ctx.lineTo(hx - 2, hy + 8); ctx.lineTo(hx + 3, hy + 8); ctx.stroke();
        // mosoly
        ctx.strokeStyle = '#8a3d2a'; ctx.lineWidth = 2.5;
        ctx.beginPath(); ctx.arc(hx, hy + 9, 11, 0.25, Math.PI - 0.25); ctx.stroke();
        // --- túrakalap: karima + tető + szalag + toll ---
        ctx.fillStyle = '#6d5334';   // karima
        ctx.beginPath(); ctx.ellipse(hx, hy - 18, 34, 9, 0, 0, 7); ctx.fill();
        ctx.fillStyle = '#7d6140';   // tető
        ctx.beginPath();
        ctx.moveTo(hx - 18, hy - 18);
        ctx.lineTo(hx - 15, hy - 34);
        ctx.quadraticCurveTo(hx, hy - 43, hx + 15, hy - 34);
        ctx.lineTo(hx + 18, hy - 18);
        ctx.closePath(); ctx.fill();
        ctx.fillStyle = '#5a4327';   // szalag
        ctx.fillRect(hx - 17, hy - 24, 34, 5);
        ctx.fillStyle = '#c0392b';   // szalag dísz (toll)
        ctx.beginPath(); ctx.ellipse(hx + 13, hy - 26, 3, 7, -0.4, 0, 7); ctx.fill();
        ctx.strokeStyle = 'rgba(0,0,0,0.15)'; ctx.lineWidth = 1;
        ctx.beginPath(); ctx.ellipse(hx, hy - 18, 34, 9, 0, 0, 7); ctx.stroke();
        // felirat
        ctx.fillStyle = '#333'; ctx.font = 'bold 16px cursive, sans-serif'; ctx.textAlign = 'center';
        ctx.fillText(game.lvl.name + ' ✓', 0, 130);
        ctx.font = '13px sans-serif';
        ctx.fillText(game.lvl.height + ' m — ' + fmtTime(game.time), 0, 150);
        ctx.restore();

        if (game.selfieT > 1.4) {
            ctx.textAlign = 'center';
            ctx.fillStyle = '#fff'; ctx.font = 'bold 20px sans-serif';
            ctx.fillText('🏔 Csúcs meghódítva!', W / 2, 60);
            ctx.font = '15px sans-serif'; ctx.fillStyle = '#9fd08c';
            ctx.fillText(game.levelIndex < TOTAL_LEVELS
                ? 'Enter vagy kattintás: következő hegy — Esc: menü'
                : 'Minden csúcsot meghódítottál! GRATULÁLUNK! (Esc: menü)', W / 2, H - 30);
            ctx.textAlign = 'left';
        }
    }

    // --- kamera ---
    function updateCamera(dt) {
        const p = game.player;
        const tx = p.x - W * 0.38;
        const ty = p.y - H * 0.58;
        game.cam.x += (tx - game.cam.x) * Math.min(1, dt * 6);
        game.cam.y += (ty - game.cam.y) * Math.min(1, dt * 4);
        if (game.cam.x < 0) game.cam.x = 0;
        if (game.cam.x > game.lvl.length - W) game.cam.x = game.lvl.length - W;
    }

    // --- fő ciklus ---
    let lastT = 0;
    function loop(ts) {
        const dt = Math.min(0.033, (ts - lastT) / 1000 || 0.016);
        lastT = ts;

        if (game.state === 'playing') {
            game.time += dt;
            updatePlayer(game.player, game.lvl, input, dt, game);
            updateEntities(game.lvl, game.player, input, dt, game);
            updateCamera(dt);
            input.photoPressed = false;
            input.jumpPressed = false;
            input.attackPressed = false;
            if (game.player.dead) game.state = 'gameover';
        } else if (game.state === 'selfie') {
            game.selfieT += dt;
            if (game.player.flashT > 0) game.player.flashT -= dt;
            if (game.selfieT > 1.4 && game.state === 'selfie') game.state = 'complete';
        }

        // kirajzolás
        if (game.state === 'menu') {
            drawMenu();
        } else if (game.state === 'selfie' || game.state === 'complete') {
            drawSelfie();
        } else {
            render(ctx, game);
            drawHUD();
            if (game.state === 'paused') {
                drawOverlayBox('SZÜNET', [
                    'Kilépés a szintből: Esc (mégegyszer)',
                    'Folytatás: ENTER',
                ], '#9fd08c');
            } else if (game.state === 'gameover') {
                drawOverlayBox('VÉGE A JÁTÉKNAK', [
                    'Elfogyott mind a három életed a(z) ' + game.lvl.name + ' hegyen.',
                    'Enter vagy kattintás: újrapróbálás — Esc: menü',
                ], '#e3554f');
            }
        }
        requestAnimationFrame(loop);
    }
    requestAnimationFrame(loop);
})();
