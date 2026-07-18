/* =========================================================
   Túra a csúcsra — kirajzolás (canvas 2D, vektoros "pixel-art")
   ========================================================= */
'use strict';

const PALETTES = [
    { skyTop: '#7ec8f7', skyBot: '#d9f0ff', hillFar: '#a8c8a0', hillNear: '#7fae74', ground: '#6d9a5b', groundDk: '#517c43', dirt: '#8a6844', dirtDk: '#6f5236', rock: '#8d8d94', rockDk: '#6b6b73', rockLt: '#b0b0b8', snowLine: 2 },
    { skyTop: '#6db4ec', skyBot: '#e6f2fb', hillFar: '#9fb4ad', hillNear: '#8496a0', ground: '#7a9a6a', groundDk: '#5c7850', dirt: '#7d6a50', dirtDk: '#5f5039', rock: '#9a9aa4', rockDk: '#727280', rockLt: '#bcbcc6', snowLine: 1 },
    { skyTop: '#4f8fd0', skyBot: '#dfeefc', hillFar: '#b9c8d6', hillNear: '#a3b4c6', ground: '#cfd9e2', groundDk: '#a9b7c6', dirt: '#8d97a4', dirtDk: '#6d7684', rock: '#aeb6c2', rockDk: '#828b99', rockLt: '#d3dae4', snowLine: 0 },
];

// determinisztikus ál-véletlen (textúrákhoz; Math.random nélkül)
function hash2(x, y) {
    let h = (x | 0) * 374761393 + (y | 0) * 668265263;
    h = (h ^ (h >> 13)) * 1274126177;
    return ((h ^ (h >> 16)) >>> 0) / 4294967296;
}
// szikla-poligon (sokszögű kő) kirajzolása középpont köré
function rockPoly(ctx, cx, cy, r, seed, sides) {
    sides = sides || 7;
    ctx.beginPath();
    for (let i = 0; i < sides; i++) {
        const a = (i / sides) * Math.PI * 2;
        const rr = r * (0.62 + hash2(seed + i * 17, i * 31) * 0.5);
        const px = cx + Math.cos(a) * rr, py = cy + Math.sin(a) * rr * 0.85;
        i === 0 ? ctx.moveTo(px, py) : ctx.lineTo(px, py);
    }
    ctx.closePath();
}

// hex szín világosítása/sötétítése (d: -255..255)
function adjust(hex, d) {
    const n = parseInt(hex.slice(1), 16);
    const cl = v => v < 0 ? 0 : v > 255 ? 255 : v;
    return 'rgb(' + cl((n >> 16) + d) + ',' + cl(((n >> 8) & 255) + d) + ',' + cl((n & 255) + d) + ')';
}

// talaj-textúra: 10×10 px mozaik, 4 árnyalat, oldalszomszédok sosem azonos színűek
function groundTexture(ctx, x0, x1, yTop, yBot, camX, camW, pal, isSnow) {
    // 4 árnyalat világostól sötétig (felső föld-réteg, ill. mélyebbi kőzet)
    const dirtR = isSnow
        ? ['#f4f8fc', '#dde8f3', '#c6d5e6', '#adbdd6']
        : [adjust(pal.dirt, 22), pal.dirt, pal.dirtDk, adjust(pal.dirtDk, -18)];
    const rockR = isSnow
        ? ['#e6eef6', '#cdd9e8', '#b3c1d6', '#97a6c0']
        : [pal.rockLt, pal.rock, pal.rockDk, adjust(pal.rockDk, -20)];

    const gx0 = Math.floor(x0 / 10) * 10;
    const gy0 = Math.floor(yTop / 10) * 10;
    const nCols = Math.max(1, Math.ceil((x1 - gx0) / 10));
    const topIdx = new Int8Array(nCols).fill(-1); // felső szomszéd árnyalata oszloponként
    const drawXa = camX - 10, drawXb = camX + camW + 10;

    for (let y = gy0; y < yBot; y += 10) {
        let left = -1; // bal szomszéd árnyalata
        for (let c = 0; c < nCols; c++) {
            const x = gx0 + c * 10;
            const t = topIdx[c];
            // kezdő tipp a hash-ból, majd léptetés amíg eltér a bal és felső szomszédtól
            let idx = (hash2(x, y) * 4) | 0; if (idx > 3) idx = 3;
            let guard = 0;
            while ((idx === left || idx === t) && guard < 4) { idx = (idx + 1) & 3; guard++; }
            left = idx; topIdx[c] = idx;

            // csak a szegmensen belül és a látható sávban rajzolunk (a lánc végig fut a determinizmusért)
            if (x + 10 <= x0 || x >= x1 || x + 10 < drawXa || x > drawXb) continue;
            const dx = Math.max(x, x0), w = Math.min(x + 10, x1) - dx;
            ctx.fillStyle = (y < yTop + 66 ? dirtR : rockR)[idx];
            ctx.fillRect(dx, y, w, 10);
        }
    }
}

// felszíni réteg: 10×10 mozaik, 4 árnyalat, oldalszomszédok sosem egyeznek.
// ramp: 4 szín (világostól sötétig); tufts: van-e ritkított csúcssor (fűszál/hócsomó).
// tufts=false → sík felület (sziklás terep a fahatár felett).
function grassCap(ctx, x0, x1, surfY, camX, camW, ramp, tufts) {
    const rowY = [surfY - 8, surfY + 2, surfY + 12];
    const bias = [0.2, 1.0, 2.0]; // felül világos, lejjebb sötétebb (fény felülről)
    const gx0 = Math.floor(x0 / 10) * 10;
    const nCols = Math.max(1, Math.ceil((x1 - gx0) / 10));
    const topIdx = new Int8Array(nCols).fill(-1);
    const drawXa = camX - 10, drawXb = camX + camW + 10;

    for (let rr = 0; rr < rowY.length; rr++) {
        const y = rowY[rr];
        let left = -1;
        for (let c = 0; c < nCols; c++) {
            const x = gx0 + c * 10;
            // legfelső sor: csak csomókban (fűcsúcs/hó); sziklánál (tufts=false) egyáltalán nincs
            if (rr === 0 && (!tufts || hash2(x, surfY | 0) < 0.5)) { left = -1; topIdx[c] = -1; continue; }
            const t = topIdx[c];
            let idx = bias[rr] + (hash2(x + 3, y * 2 + 1) - 0.5) * 1.6;
            idx = idx < 0 ? 0 : idx > 3 ? 3 : Math.round(idx);
            let guard = 0;
            while ((idx === left || idx === t) && guard < 4) { idx = (idx + 1) & 3; guard++; }
            left = idx; topIdx[c] = idx;

            if (x + 10 <= x0 || x >= x1 || x + 10 < drawXa || x > drawXb) continue;
            const dx = Math.max(x, x0), w = Math.min(x + 10, x1) - dx;
            ctx.fillStyle = ramp[idx];
            ctx.fillRect(dx, y, w, 10);
        }
    }
}

function render(ctx, game) {
    const { lvl, player: p, cam } = game;
    const W = ctx.canvas.width, H = ctx.canvas.height;
    const pal = PALETTES[lvl.tier];

    // --- ég ---
    const sky = ctx.createLinearGradient(0, 0, 0, H);
    sky.addColorStop(0, pal.skyTop); sky.addColorStop(1, pal.skyBot);
    ctx.fillStyle = sky; ctx.fillRect(0, 0, W, H);

    // nap
    const sunX = W - 130, sunY = 90;
    const sunGlow = ctx.createRadialGradient(sunX, sunY, 10, sunX, sunY, p.inSun ? 120 : 70);
    sunGlow.addColorStop(0, 'rgba(255,240,150,0.95)');
    sunGlow.addColorStop(1, 'rgba(255,240,150,0)');
    ctx.fillStyle = sunGlow; ctx.beginPath(); ctx.arc(sunX, sunY, p.inSun ? 120 : 70, 0, 7); ctx.fill();
    ctx.fillStyle = '#ffe873'; ctx.beginPath(); ctx.arc(sunX, sunY, 34, 0, 7); ctx.fill();

    // --- parallax hegyek ---
    drawHills(ctx, W, H, cam.x * 0.2, pal.hillFar, H * 0.55, 260, lvl.n, pal.snowLine <= 1);
    drawHills(ctx, W, H, cam.x * 0.45, pal.hillNear, H * 0.68, 200, lvl.n + 5, pal.snowLine === 0);

    // felhők
    for (let i = 0; i < 5; i++) {
        const cx = ((i * 337 + 100 - cam.x * 0.3) % (W + 300)) - 150;
        drawCloud(ctx, cx, 60 + (i % 3) * 45);
    }

    ctx.save();
    ctx.translate(-cam.x, -cam.y);

    // napos sávok jelzése (meleg fénypászma)
    for (const s of lvl.suns) {
        if (s.x1 < cam.x || s.x0 > cam.x + W) continue;
        const g = ctx.createLinearGradient(0, cam.y, 0, cam.y + H);
        g.addColorStop(0, 'rgba(255,220,90,0.28)'); g.addColorStop(1, 'rgba(255,220,90,0.04)');
        ctx.fillStyle = g;
        ctx.fillRect(s.x0, cam.y, s.x1 - s.x0, H);
    }

    // --- talaj (rétegzett kőzet + textúra) ---
    const isSnow = lvl.tier === 2;
    const bottom = cam.y + H + 60;
    for (const s of lvl.segs) {
        if (s.x + s.w < cam.x - 6 || s.x > cam.x + W + 6) continue;
        const x0 = s.x, x1 = s.x + s.w;

        // alapkőzet (legalsó, sötét)
        ctx.fillStyle = pal.rockDk;
        ctx.fillRect(x0, s.y, s.w, bottom - s.y);
        // föld/kőzet réteg
        ctx.fillStyle = pal.dirt;
        ctx.fillRect(x0, s.y, s.w, 70);
        // 10×10 mozaik-textúra (4 árnyalat, oldalszomszédok sosem egyeznek)
        groundTexture(ctx, x0, x1, s.y + 14, Math.min(bottom, s.y + 240), cam.x, W, pal, isSnow);
        // vékony sötét földcsíkok (rétegződés)
        ctx.fillStyle = pal.dirtDk;
        for (let ly = s.y + 22; ly < s.y + 130; ly += 26) {
            ctx.fillRect(x0, ly + (hash2(x0, ly) * 4 | 0), s.w, 3);
        }
        // beágyazott kő-poligonok a földben (több él)
        for (let rx = x0 + 8; rx < x1 - 4; rx += 34) {
            const h = hash2(rx, s.y);
            if (h < 0.5) continue;
            const cx = rx + h * 10, cy = s.y + 24 + hash2(rx, 7) * 80, rad = 5 + h * 7;
            ctx.fillStyle = h > 0.78 ? pal.rockLt : pal.rock;
            rockPoly(ctx, cx, cy, rad, rx, 6 + (h * 3 | 0)); ctx.fill();
            ctx.fillStyle = 'rgba(0,0,0,0.12)';
            rockPoly(ctx, cx + 1, cy + 2, rad * 0.6, rx + 5, 5); ctx.fill();
        }

        // felszíni réteg — a magasság dönti el: fű / hó / (fahatár felett) csupasz szikla
        const rocky = !isSnow && elevationAt(lvl, s.y) > 2000; // 2000 m felett nincs fű, csak kőzet
        let capRamp, capTufts;
        if (isSnow) { capRamp = ['#ffffff', '#e9f1fa', '#d3e0ee', '#bccadd']; capTufts = true; }
        else if (rocky) { capRamp = [pal.rockLt, pal.rock, pal.rockDk, adjust(pal.rockDk, -18)]; capTufts = false; }
        else { capRamp = [adjust(pal.ground, 26), pal.ground, pal.groundDk, adjust(pal.groundDk, -20)]; capTufts = true; }
        grassCap(ctx, x0, x1, s.y, cam.x, W, capRamp, capTufts);

        // sziklafal a lépcső bal élénél (szögletes poligonok)
        const leftGround = groundAtGen(lvl.segs, x0 - 6);
        if (leftGround > s.y + 6) { // kiugró él → sziklafal
            ctx.fillStyle = pal.rock;
            ctx.beginPath();
            ctx.moveTo(x0, s.y);
            let yy = s.y;
            let flip = 0;
            while (yy < leftGround) {
                const nx = x0 + (flip % 2 ? -3 : 3) + (hash2(x0, yy) - 0.5) * 5;
                ctx.lineTo(nx, yy); yy += 12 + hash2(x0, yy) * 8; flip++;
            }
            ctx.lineTo(x0, leftGround); ctx.lineTo(x0 - 6, leftGround); ctx.lineTo(x0 - 6, s.y);
            ctx.closePath(); ctx.fill();
            // fény/árnyék élek
            ctx.strokeStyle = pal.rockDk; ctx.lineWidth = 2;
            ctx.beginPath(); ctx.moveTo(x0 - 5, s.y + 4); ctx.lineTo(x0 - 5, leftGround); ctx.stroke();
            ctx.strokeStyle = pal.rockLt; ctx.lineWidth = 1;
            ctx.beginPath(); ctx.moveTo(x0 + 1, s.y + 6); ctx.lineTo(x0 + 1, leftGround - 4); ctx.stroke();
        }
    }

    // dekor
    for (const d of lvl.decor) {
        if (d.x < cam.x - 40 || d.x > cam.x + W + 40) continue;
        if (d.kind === 0) { // kő — sokszögű, árnyékolt
            ctx.fillStyle = pal.rock;
            rockPoly(ctx, d.x, d.y - 6, 11, d.x | 0, 7); ctx.fill();
            ctx.fillStyle = pal.rockLt; // megvilágított oldal
            rockPoly(ctx, d.x - 2, d.y - 8, 6, (d.x | 0) + 3, 6); ctx.fill();
            ctx.fillStyle = 'rgba(0,0,0,0.18)'; // árnyék alul
            ctx.beginPath(); ctx.ellipse(d.x, d.y - 1, 11, 3, 0, 0, 7); ctx.fill();
        } else if (d.kind === 1 && lvl.tier < 2) { // virág
            ctx.fillStyle = '#e3554f'; ctx.beginPath(); ctx.arc(d.x, d.y - 10, 4, 0, 7); ctx.fill();
            ctx.strokeStyle = '#4c7a3f'; ctx.beginPath(); ctx.moveTo(d.x, d.y); ctx.lineTo(d.x, d.y - 8); ctx.stroke();
        } else { // bokor / hókupac
            ctx.fillStyle = lvl.tier === 2 ? '#ffffff' : '#517c43';
            ctx.beginPath(); ctx.arc(d.x, d.y - 6, 9, 0, 7); ctx.arc(d.x + 8, d.y - 4, 7, 0, 7); ctx.fill();
        }
    }

    // fák
    for (const t of lvl.trees) {
        if (t.x < cam.x - 80 || t.x > cam.x + W + 80) continue;
        drawTree(ctx, t, lvl.tier);
    }
    // bokrok (fahatár közelében, 1500–2000 m)
    for (const b of lvl.bushes) {
        if (b.x < cam.x - 60 || b.x > cam.x + W + 60) continue;
        drawBush(ctx, b, lvl.tier);
    }

    // víz
    for (const w of lvl.waters) {
        if (w.x1 < cam.x || w.x0 > cam.x + W) continue;
        ctx.fillStyle = 'rgba(70,140,210,0.75)';
        ctx.fillRect(w.x0, w.y - 18, w.x1 - w.x0, 60);
        ctx.strokeStyle = 'rgba(255,255,255,0.6)'; ctx.lineWidth = 2;
        ctx.beginPath();
        for (let wx = w.x0; wx < w.x1; wx += 24) {
            ctx.moveTo(wx, w.y - 14 + Math.sin(wx / 20 + game.time * 3) * 2);
            ctx.lineTo(wx + 14, w.y - 14 + Math.sin(wx / 20 + game.time * 3) * 2);
        }
        ctx.stroke();
    }

    // barlangok — szabálytalan SZIKLATÖMBBE vájva (nem téglalap), pályánként más formával
    for (const c of lvl.caves) {
        if (c.x1 < cam.x - 60 || c.x0 > cam.x + W + 60) continue;
        const gy = groundAtGen(lvl.segs, (c.x0 + c.x1) / 2);
        const seed = c.x0 | 0;
        const shape = (hash2(seed, 3) * 3) | 0;               // 0 gömbölyű, 1 kétcsúcsú, 2 aszimmetrikus
        const marginL = 22 + hash2(seed, 1) * 30;
        const marginR = 22 + hash2(seed, 2) * 30;
        const crest = c.ceilY - (110 + hash2(seed, 4) * 80);  // a tömb teteje (magasság változó)
        const W0 = c.x0 - marginL, W1 = c.x1 + marginR, WW = W1 - W0;
        const bump = (u, cc, wd) => Math.max(0, 1 - ((u - cc) / wd) ** 2);
        const steps = Math.max(8, Math.round(WW / 20));

        // a sziklaforma szabálytalan felső körvonalának pontjai
        const crestPts = [];
        for (let i = 0; i <= steps; i++) {
            const u = i / steps, px = W0 + u * WW;
            let hf;
            if (shape === 1) hf = Math.max(bump(u, 0.30, 0.34), bump(u, 0.72, 0.32));
            else if (shape === 2) hf = Math.max(bump(u, 0.42, 0.52), bump(u, 0.80, 0.24) * 0.7);
            else hf = bump(u, 0.5, 0.58);
            const jit = (hash2(seed + i * 13, i * 7) - 0.5) * 20;
            let py = gy - (gy - crest) * hf + jit;
            if (py > gy - 4) py = gy - 4;
            crestPts.push([px, py]);
        }
        const tracePath = () => {
            ctx.beginPath();
            ctx.moveTo(W0, gy + 8);
            for (const q of crestPts) ctx.lineTo(q[0], q[1]);
            ctx.lineTo(W1, gy + 8);
            ctx.closePath();
        };

        // rétegzett kőzet a sziklaforma sziluettjére vágva
        ctx.save();
        tracePath(); ctx.clip();
        ctx.fillStyle = pal.rockDk;
        ctx.fillRect(W0, crest - 30, WW, gy - crest + 40);
        ctx.fillStyle = pal.rock;
        for (let ly = crest; ly < gy; ly += 18) ctx.fillRect(W0, ly, WW, 11);
        ctx.strokeStyle = pal.rockDk; ctx.lineWidth = 1.5;
        for (let ly = crest + 8; ly < gy - 4; ly += 18) {
            ctx.beginPath(); ctx.moveTo(W0, ly);
            for (let vx = W0; vx <= W1; vx += 26) ctx.lineTo(vx, ly + (hash2(vx, ly) - 0.5) * 8);
            ctx.stroke();
        }
        for (let rx = W0 + 14; rx < W1 - 8; rx += 38) {
            const h = hash2(rx, seed);
            ctx.fillStyle = h > 0.6 ? pal.rockLt : pal.rockDk;
            rockPoly(ctx, rx, crest + 24 + h * 70, 6 + h * 6, rx, 7); ctx.fill();
        }
        ctx.restore();

        // körvonal: sötét sziklaperem + néhány megvilágított él
        tracePath();
        ctx.strokeStyle = pal.rockDk; ctx.lineWidth = 2.5; ctx.stroke();
        ctx.strokeStyle = pal.rockLt; ctx.lineWidth = 1;
        ctx.beginPath();
        for (let i = 0; i < crestPts.length - 1; i++) {
            if (hash2(seed + i, 2) > 0.5) {
                ctx.moveTo(crestPts[i][0], crestPts[i][1] + 1.5);
                ctx.lineTo(crestPts[i + 1][0], crestPts[i + 1][1] + 1.5);
            }
        }
        ctx.stroke();

        // a barlang belső ürege (sötét, sziklás plafon-perem)
        ctx.fillStyle = '#20242b';
        ctx.beginPath();
        ctx.moveTo(c.x0, c.ceilY);
        const drop0 = 4 + hash2(seed, 8) * 10;
        for (let sx = c.x0; sx <= c.x1; sx += 24) {
            const drop = drop0 + hash2(sx, 9) * 16;
            ctx.lineTo(sx + 7, c.ceilY);
            ctx.lineTo(sx + 12, c.ceilY + drop);
            ctx.lineTo(sx + 17, c.ceilY);
        }
        ctx.lineTo(c.x1, gy); ctx.lineTo(c.x0, gy);
        ctx.closePath(); ctx.fill();

        // cseppkövek a plafonról (változó hosszúság/sűrűség)
        ctx.fillStyle = pal.rockDk;
        for (let sx = c.x0 + 16; sx < c.x1 - 8; sx += 30 + hash2(sx, 4) * 16) {
            const len = 8 + hash2(sx, 12) * 22;
            ctx.beginPath();
            ctx.moveTo(sx - 5, c.ceilY); ctx.lineTo(sx, c.ceilY + len); ctx.lineTo(sx + 5, c.ceilY);
            ctx.closePath(); ctx.fill();
        }
        // sztalagmitok a padlón
        for (let sx = c.x0 + 12; sx < c.x1 - 6; sx += 34) {
            const up = 6 + hash2(sx, 21) * 14;
            ctx.beginPath();
            ctx.moveTo(sx - 6, gy); ctx.lineTo(sx, gy - up); ctx.lineTo(sx + 6, gy);
            ctx.closePath(); ctx.fill();
        }
        // világosabb szikla-blokkok a falban
        for (let rx = c.x0 + 20; rx < c.x1 - 10; rx += 60) {
            const h = hash2(rx, 5);
            ctx.fillStyle = 'rgba(150,150,165,0.3)';
            rockPoly(ctx, rx, c.ceilY + 18 + h * 24, 8 + h * 5, rx + 3, 6); ctx.fill();
        }
    }

    // tárgyak
    for (const it of lvl.items) {
        if (it.taken || it.x < cam.x - 40 || it.x > cam.x + W + 40) continue;
        const bob = Math.sin(game.time * 4 + it.x) * 4;
        drawItem(ctx, it.x, it.y + (it.falling ? 0 : bob), it.kind);
    }

    // kígyók
    for (const s of lvl.snakes) {
        if (s.dead || s.x < cam.x - 60 || s.x > cam.x + W + 60) continue;
        drawSnake(ctx, s, game.time);
    }
    // medvék
    for (const b of lvl.bears) {
        if (b.dead || b.x < cam.x - 100 || b.x > cam.x + W + 100) continue;
        drawBear(ctx, b);
    }
    // rókák
    for (const f of lvl.foxes) {
        if (f.gone || f.x < cam.x - 100 || f.x > cam.x + W + 100) continue;
        drawFox(ctx, f, game.time);
        if (f.photoReady) {
            ctx.fillStyle = '#fff'; ctx.font = 'bold 14px sans-serif'; ctx.textAlign = 'center';
            ctx.fillText('📷 P — fotó!', f.x, f.y - 30);
        }
    }

    // csúcskereszt
    drawSummitCross(ctx, lvl.summitX, lvl.summitY);
    if (p.atSummit && !p.selfieDone) {
        ctx.fillStyle = '#fff'; ctx.font = 'bold 16px sans-serif'; ctx.textAlign = 'center';
        ctx.fillText('Nyomd meg a P-t a csúcs-szelfihez! 🤳', lvl.summitX, lvl.summitY - 130);
    }

    // effektek
    for (const e of game.effects) {
        ctx.globalAlpha = Math.max(0, e.t * 2);
        if (e.kind === 'poof') {
            ctx.fillStyle = '#ddd';
            for (let i = 0; i < 5; i++) {
                ctx.beginPath(); ctx.arc(e.x + Math.cos(i * 1.3) * (0.5 - e.t) * 40, e.y + Math.sin(i * 1.3) * (0.5 - e.t) * 40, 6, 0, 7); ctx.fill();
            }
        } else {
            ctx.fillStyle = '#ffe873';
            for (let i = 0; i < 6; i++) {
                ctx.beginPath(); ctx.arc(e.x + Math.cos(i) * (0.5 - e.t) * 50, e.y + Math.sin(i) * (0.5 - e.t) * 50, 3, 0, 7); ctx.fill();
            }
        }
        ctx.globalAlpha = 1;
    }

    // --- játékos ---
    drawHiker(ctx, p, game.time);

    ctx.restore();

    // --- sötétség barlangban ---
    if (p.inCave) {
        const px = p.x + p.w / 2 - cam.x, py = p.y + p.h / 2 - cam.y;
        ctx.save();
        if (p.headlight) {
            const g = ctx.createRadialGradient(px, py, 40, px, py, 260);
            g.addColorStop(0, 'rgba(0,0,0,0)');
            g.addColorStop(1, 'rgba(0,0,0,0.93)');
            ctx.fillStyle = g;
        } else {
            const g = ctx.createRadialGradient(px, py, 8, px, py, 60);
            g.addColorStop(0, 'rgba(0,0,0,0.35)');
            g.addColorStop(1, 'rgba(0,0,0,0.97)');
            ctx.fillStyle = g;
        }
        ctx.fillRect(0, 0, W, H);
        ctx.restore();
        if (!p.headlight) {
            ctx.fillStyle = '#fff'; ctx.font = 'bold 15px sans-serif'; ctx.textAlign = 'center';
            ctx.fillText('Koromsötét van! Kapcsold fel a fejlámpát! (L)', W / 2, 50);
        }
    }

    // fotóvillanás
    if (p.flashT > 0) {
        ctx.fillStyle = 'rgba(255,255,255,' + Math.min(1, p.flashT * 3) + ')';
        ctx.fillRect(0, 0, W, H);
    }
}

function drawHills(ctx, W, H, off, color, baseY, amp, seed, snowCaps) {
    ctx.fillStyle = color;
    ctx.beginPath();
    ctx.moveTo(0, H);
    const peaks = [];
    for (let x = -100; x <= W + 100; x += 90) {
        const wx = x + off;
        const h = Math.abs(Math.sin(wx * 0.004 + seed) * 0.6 + Math.sin(wx * 0.0013 + seed * 2) * 0.4);
        const y = baseY - h * amp;
        ctx.lineTo(x, y);
        peaks.push([x, y]);
    }
    ctx.lineTo(W, H); ctx.closePath(); ctx.fill();
    if (snowCaps) {
        ctx.fillStyle = 'rgba(255,255,255,0.85)';
        for (const [x, y] of peaks) {
            if (y < baseY - amp * 0.55) { ctx.beginPath(); ctx.arc(x, y + 6, 12, 0, 7); ctx.fill(); }
        }
    }
}

function drawCloud(ctx, x, y) {
    ctx.fillStyle = 'rgba(255,255,255,0.85)';
    ctx.beginPath();
    ctx.arc(x, y, 18, 0, 7); ctx.arc(x + 20, y - 8, 22, 0, 7); ctx.arc(x + 45, y, 17, 0, 7);
    ctx.fill();
}

// fakéreg 4 árnyalata (világostól sötétig)
const BARK = ['#8f6238', '#6b4a2c', '#513824', '#39270f'];

// lombkorona 4 árnyalata típus/tier szerint (világostól sötétig)
function leafRamp(tier, type) {
    if (tier === 2) return ['#6f8d7f', '#517263', '#3a5748', '#2b4236']; // havas fenyő
    if (type === 2) return ['#8dbf5a', '#69a23f', '#4d7d2e', '#386021']; // tölgy
    if (type === 1) return ['#7ec457', '#5ea23f', '#457e2e', '#326022']; // gömb lombos
    if (type === 3) return ['#a0cb6c', '#7bb04c', '#5c8d38', '#446a29']; // nyír
    return ['#57a349', '#3f7a37', '#2f6029', '#234f21'];                 // fenyő
}

// blokkos mozaik maszk szerint: 10px cellák, 4 árnyalat, oldalszomszédok sosem egyeznek.
// bias(c,r) opcionális irány-fény (0..3, kicsi=világos); enélkül hash-alapú.
function tileMosaic(ctx, ox, oy, cols, rows, mask, shades, seed, bias) {
    const top = new Int8Array(cols).fill(-1);
    for (let r = 0; r < rows; r++) {
        let left = -1;
        for (let c = 0; c < cols; c++) {
            if (!mask(c, r)) { left = -1; top[c] = -1; continue; }
            const tp = top[c];
            const base = bias ? bias(c, r) : hash2(seed + c * 13, r * 7) * 3;
            let idx = base + (hash2(seed + c * 5 + 2, r * 11 + 1) - 0.5) * 1.5;
            idx = idx < 0 ? 0 : idx > 3 ? 3 : Math.round(idx);
            let g = 0;
            while ((idx === left || idx === tp) && g < 4) { idx = (idx + 1) & 3; g++; }
            left = idx; top[c] = idx;
            ctx.fillStyle = shades[idx];
            ctx.fillRect(ox + c * 10, oy + r * 10, 10, 10);
        }
    }
}

function drawTree(ctx, t, tier) {
    const seed = (t.x | 0);
    let type = t.type;
    if (type === undefined) type = (hash2(seed, 1) * 4) | 0;
    if (tier === 2) type = hash2(seed, 2) < 0.85 ? 0 : 3; // havason szinte csak fenyő

    // típus → forma (cellaszám + törzsszélesség + silhouette-maszk)
    const jig = (c, r) => (hash2(seed + c * 7, r * 3) - 0.5); // perem-zaj
    let cols, rows, trunkW, mask;
    if (type === 0) {              // fenyő – kúpos
        cols = 9; rows = 10; trunkW = 12;
        const cc = (cols - 1) / 2;
        mask = (c, r) => Math.abs(c - cc) <= (r + 1) / rows * (cc + 0.6) + jig(c, r) * 0.8;
    } else if (type === 3) {       // nyír – magas, keskeny
        cols = 5; rows = 9; trunkW = 10;
        const cc = (cols - 1) / 2, mid = (rows - 1) / 2;
        mask = (c, r) => {
            const dx = (c - cc) / (cc + 0.4), dy = (r - mid) / (mid + 0.7);
            return dx * dx * 1.25 + dy * dy <= 1 + jig(c, r) * 0.22;
        };
    } else if (type === 2) {       // tölgy – széles, tömött
        cols = 11; rows = 7; trunkW = 22;
        const cc = (cols - 1) / 2, mid = (rows - 1) / 2 - 0.3;
        mask = (c, r) => {
            const dx = (c - cc) / (cc + 0.5), dy = (r - mid) / (mid + 0.9);
            return dx * dx + dy * dy * 1.15 <= 1 + jig(c, r) * 0.28;
        };
    } else {                       // gömb lombos
        cols = 8; rows = 7; trunkW = 14; type = 1;
        const cc = (cols - 1) / 2, mid = (rows - 1) / 2;
        mask = (c, r) => {
            const dx = (c - cc) / (cc + 0.5), dy = (r - mid) / (mid + 0.6);
            return dx * dx + dy * dy <= 1 + jig(c, r) * 0.26;
        };
    }

    const cc = (cols - 1) / 2;
    const canopyBottomY = t.y - 30;
    const oy = canopyBottomY - rows * 10;
    const ox = t.x - cc * 10 - 5;

    // --- törzs (kéreg-mozaik, jobb oldala árnyékosabb) ---
    const tcols = Math.max(1, Math.round(trunkW / 10));
    const toX = t.x - (tcols * 10) / 2;
    const trunkTop = canopyBottomY - 6;
    const trows = Math.max(1, Math.ceil((t.y - trunkTop) / 10));
    tileMosaic(ctx, toX, t.y - trows * 10, tcols, trows, () => true, BARK, seed + 91,
        c => (tcols > 1 ? c / (tcols - 1) : 0.5) * 1.8 + 0.7);

    // --- lombkorona (4 árnyalatú mozaik, fény bal-felülről) ---
    const shades = leafRamp(tier, type);
    tileMosaic(ctx, ox, oy, cols, rows, mask, shades, seed + 7,
        (c, r) => (c / (cols - 1)) * 1.0 + (r / (rows - 1)) * 1.9);

    // --- hósapka a lomb tetején (havas tier) ---
    if (tier === 2) {
        const snow = ['#ffffff', '#e9f1fa', '#d3e0ee'];
        let lastS = -1;
        for (let c = 0; c < cols; c++) {
            let rt = -1;
            for (let r = 0; r < rows; r++) { if (mask(c, r)) { rt = r; break; } }
            if (rt < 0) { lastS = -1; continue; }
            let si = (hash2(seed + c * 3, 5) * 3) | 0; if (si > 2) si = 2;
            if (si === lastS) si = (si + 1) % 3;
            lastS = si;
            ctx.fillStyle = snow[si];
            ctx.fillRect(ox + c * 10, oy + rt * 10, 10, 10);
        }
    }

    // --- termés (blokkos, hogy passzoljon a stílushoz) ---
    if (t.fruity && !t.shaken) {
        for (let i = 0; i < t.fruits; i++) {
            const ax = t.x - 16 + i * 16, ay = canopyBottomY - 14 - (i % 2) * 12;
            ctx.fillStyle = '#c94236'; ctx.fillRect(ax - 3, ay - 3, 6, 6);
            ctx.fillStyle = '#f07a6e'; ctx.fillRect(ax - 3, ay - 3, 2, 2);
        }
    }
}

// alacsony bokor — a fákkal azonos mozaik-technika, kisebb, gömbölyded lombbal
function drawBush(ctx, b, tier) {
    const seed = (b.x | 0);
    const shades = tier === 2
        ? ['#7f9a8d', '#5f7d6e', '#465f52', '#33453b']
        : (b.type ? ['#89c25a', '#66a03c', '#4b7c2c', '#365f21']
            : ['#6fb84a', '#4f9636', '#3a7328', '#2b551d']);
    const cols = 6, rows = 4;
    const cc = (cols - 1) / 2, mid = (rows - 1) / 2;
    const ox = b.x - cc * 10 - 5;
    const oy = b.y - rows * 10 + 4;   // talaj fölé emelkedik, alja a talajnál
    const jig = (c, r) => (hash2(seed + c * 7, r * 3) - 0.5);
    const mask = (c, r) => {
        const dx = (c - cc) / (cc + 0.5), dy = (r - mid) / (mid + 0.5);
        return dx * dx + dy * dy <= 1 + jig(c, r) * 0.3;
    };
    tileMosaic(ctx, ox, oy, cols, rows, mask, shades, seed + 3,
        (c, r) => (c / (cols - 1)) * 0.9 + (r / (rows - 1)) * 2.0);
}

// részletes szendvics (felső dombos zsemle, saláta, paradicsom, sajt, sonka, alsó zsemle)
function drawSandwich(ctx, x, y, sup) {
    const hw = sup ? 15 : 13;
    // szuper: sárga fényudvar
    if (sup) {
        ctx.save(); ctx.shadowColor = '#ffe873'; ctx.shadowBlur = 16;
        ctx.fillStyle = '#ffd94d'; roundRectPath(ctx, x - hw, y - 12, hw * 2, 22, 6); ctx.fill();
        ctx.restore();
    }
    // alsó zsemle
    ctx.fillStyle = sup ? '#e6a72c' : '#cf9a44';
    roundRectPath(ctx, x - hw, y + 3, hw * 2, 6, 2.5); ctx.fill();
    // sajt (kilógó háromszögek)
    ctx.fillStyle = '#f6c945';
    ctx.beginPath();
    ctx.moveTo(x - hw, y + 2); ctx.lineTo(x + hw, y + 2);
    ctx.lineTo(x + hw - 5, y + 6); ctx.lineTo(x, y + 4); ctx.lineTo(x - hw + 4, y + 6);
    ctx.closePath(); ctx.fill();
    // sonka (rózsaszín)
    ctx.fillStyle = sup ? '#e0574a' : '#d97c6a';
    roundRectPath(ctx, x - hw + 1, y - 1, hw * 2 - 2, 4, 2); ctx.fill();
    // paradicsom-korong
    ctx.fillStyle = sup ? '#d63a2c' : '#cf4b39';
    ctx.beginPath(); ctx.arc(x - hw + 5, y + 1, 2.6, 0, 7); ctx.arc(x + hw - 5, y + 1, 2.6, 0, 7); ctx.fill();
    // saláta (zöld, hullámos, kicsit szélesebb)
    ctx.fillStyle = '#7fbf3f';
    ctx.beginPath();
    ctx.moveTo(x - hw - 2, y - 3);
    const segs = (hw + 2);
    for (let i = 0; i <= segs; i++) {
        const px = x - hw - 2 + (i / segs) * (hw + 2) * 2;
        ctx.lineTo(px, y - 3 + (i % 2 ? 3 : 0));
    }
    ctx.lineTo(x + hw + 2, y - 5); ctx.lineTo(x - hw - 2, y - 5); ctx.closePath(); ctx.fill();
    // felső zsemle (dombos)
    ctx.fillStyle = sup ? '#ffcf4a' : '#e8b45a';
    ctx.beginPath();
    ctx.moveTo(x - hw, y - 3);
    ctx.quadraticCurveTo(x - hw, y - 13, x, y - 13);
    ctx.quadraticCurveTo(x + hw, y - 13, x + hw, y - 3);
    ctx.closePath(); ctx.fill();
    // fény a zsemlén
    ctx.fillStyle = 'rgba(255,255,255,0.4)';
    ctx.beginPath(); ctx.ellipse(x - hw * 0.35, y - 9, hw * 0.4, 2.2, -0.3, 0, 7); ctx.fill();
    // szezámmag
    ctx.fillStyle = '#fff6de';
    ctx.fillRect(x - 5, y - 9, 2, 1); ctx.fillRect(x + 2, y - 10, 2, 1); ctx.fillRect(x - 1, y - 6, 2, 1);
    if (sup) {
        ctx.fillStyle = '#fff'; ctx.font = 'bold 11px sans-serif'; ctx.textAlign = 'center';
        ctx.fillText('★', x, y - 16);
    }
}

function drawItem(ctx, x, y, kind) {
    if (kind === 'apple') {
        // alma – árnyékolt, levéllel
        ctx.fillStyle = '#b8322a';
        ctx.beginPath(); ctx.arc(x - 3, y, 7, 0, 7); ctx.arc(x + 3, y, 7, 0, 7); ctx.fill();
        ctx.fillStyle = '#e3554f';
        ctx.beginPath(); ctx.arc(x - 2, y - 1, 5.5, 0, 7); ctx.fill();
        ctx.fillStyle = 'rgba(255,255,255,0.5)';
        ctx.beginPath(); ctx.ellipse(x - 3, y - 3, 2.2, 1.3, -0.5, 0, 7); ctx.fill();
        ctx.strokeStyle = '#5a3a1c'; ctx.lineWidth = 1.5; ctx.lineCap = 'round';
        ctx.beginPath(); ctx.moveTo(x, y - 6); ctx.lineTo(x + 1, y - 11); ctx.stroke();
        ctx.fillStyle = '#4c7a3f';
        ctx.beginPath(); ctx.ellipse(x + 4, y - 11, 4, 2.2, 0.6, 0, 7); ctx.fill();
    } else if (kind === 'sandwich') {
        drawSandwich(ctx, x, y, false);
    } else { // szuper szendvics
        drawSandwich(ctx, x, y, true);
    }
}

function drawSnake(ctx, s, time) {
    const dir = s.dir;
    // hullámzó középvonal pontjai
    const pts = [];
    for (let i = 0; i <= 22; i++) {
        pts.push([s.x + i * 1.3 * dir, s.y + 6 + Math.sin(i * 0.9 + time * 8) * 3]);
    }
    const trace = () => { ctx.beginPath(); pts.forEach((p, i) => i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1])); ctx.stroke(); };
    ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    // sötét alaptest (has-árnyék)
    ctx.strokeStyle = '#2f5220'; ctx.lineWidth = 9; trace();
    // zöld test
    ctx.strokeStyle = '#5a9636'; ctx.lineWidth = 6.5; trace();
    // háti fény
    ctx.strokeStyle = '#7cb84f'; ctx.lineWidth = 2.5;
    ctx.beginPath(); pts.forEach((p, i) => i ? ctx.lineTo(p[0], p[1] - 1.5) : ctx.moveTo(p[0], p[1] - 1.5)); ctx.stroke();
    // háti mintázat (sötét foltok)
    ctx.fillStyle = '#26401a';
    for (let i = 2; i < pts.length - 2; i += 2) {
        ctx.beginPath(); ctx.arc(pts[i][0], pts[i][1], 1.8, 0, 7); ctx.fill();
    }
    // fej
    const [hx, hy] = pts[pts.length - 1];
    ctx.fillStyle = '#5a9636';
    ctx.beginPath(); ctx.ellipse(hx, hy, 7, 5, 0, 0, 7); ctx.fill();
    // sárga nyakörv (vízisikló)
    ctx.fillStyle = '#f4d43a';
    ctx.beginPath(); ctx.arc(hx - 6 * dir, hy, 2.3, 0, 7); ctx.fill();
    // szem
    ctx.fillStyle = '#ffce00'; ctx.beginPath(); ctx.arc(hx + 2 * dir, hy - 1.5, 2, 0, 7); ctx.fill();
    ctx.fillStyle = '#000'; ctx.beginPath(); ctx.arc(hx + 2.5 * dir, hy - 1.5, 0.9, 0, 7); ctx.fill();
    // villás nyelv (időnként kicsap)
    if (Math.sin(time * 6) > 0.4) {
        ctx.strokeStyle = '#c94f42'; ctx.lineWidth = 1.2;
        const tx = hx + 12 * dir;
        ctx.beginPath();
        ctx.moveTo(hx + 6 * dir, hy); ctx.lineTo(tx, hy);
        ctx.moveTo(tx, hy); ctx.lineTo(tx + 3 * dir, hy - 2);
        ctx.moveTo(tx, hy); ctx.lineTo(tx + 3 * dir, hy + 2);
        ctx.stroke();
    }
}

function drawBear(ctx, b) {
    const x = b.x, y = b.y;
    ctx.save();
    if (b.hurtT > 0) ctx.globalAlpha = 0.6;
    ctx.fillStyle = '#5e4530';
    // test
    ctx.beginPath(); ctx.ellipse(x + 32, y + 30, 30, 22, 0, 0, 7); ctx.fill();
    // fej
    ctx.beginPath(); ctx.arc(x + (b.dir > 0 ? 58 : 6), y + 14, 15, 0, 7); ctx.fill();
    // fülek
    ctx.beginPath(); ctx.arc(x + (b.dir > 0 ? 52 : 12), y + 3, 5, 0, 7); ctx.arc(x + (b.dir > 0 ? 64 : 0), y + 3, 5, 0, 7); ctx.fill();
    // lábak
    ctx.fillRect(x + 10, y + 44, 10, 10); ctx.fillRect(x + 44, y + 44, 10, 10);
    // orr, szem
    ctx.fillStyle = '#2c1f14';
    ctx.beginPath(); ctx.arc(x + (b.dir > 0 ? 70 : -6), y + 16, 4, 0, 7); ctx.fill();
    ctx.fillStyle = '#000';
    ctx.beginPath(); ctx.arc(x + (b.dir > 0 ? 60 : 4), y + 10, 2, 0, 7); ctx.fill();
    if (b.state === 'charge') {
        ctx.fillStyle = '#c94f42'; ctx.font = 'bold 13px sans-serif'; ctx.textAlign = 'center';
        ctx.fillText('!', x + 32, y - 12);
    }
    // életpontok
    ctx.fillStyle = '#c94f42';
    for (let i = 0; i < b.hp; i++) ctx.fillRect(x + 20 + i * 10, y - 8, 7, 4);
    ctx.restore();
}

function drawFox(ctx, f, time) {
    const x = f.x, y = f.y, d = f.dir;
    const orange = '#e07d2f', orangeDk = '#b8621f', cream = '#f5ede0', dark = '#3a2414';
    const wag = Math.sin(time * 5) * 3;

    // bozontos farok (sötét tő → narancs → fehér vég)
    ctx.fillStyle = orangeDk;
    ctx.beginPath(); ctx.ellipse(x - 20 * d, y + 8 + wag, 13, 6, -0.5 * d, 0, 7); ctx.fill();
    ctx.fillStyle = orange;
    ctx.beginPath(); ctx.ellipse(x - 18 * d, y + 7 + wag, 10, 5, -0.5 * d, 0, 7); ctx.fill();
    ctx.fillStyle = cream;
    ctx.beginPath(); ctx.arc(x - 28 * d, y + 5 + wag * 1.3, 4, 0, 7); ctx.fill();

    // hátsó láb
    ctx.fillStyle = dark; ctx.fillRect(x - 9 * d, y + 16, 4, 9);
    // test
    ctx.fillStyle = orange;
    ctx.beginPath(); ctx.ellipse(x, y + 11, 18, 9, 0, 0, 7); ctx.fill();
    // fehér mellkas/has
    ctx.fillStyle = cream;
    ctx.beginPath(); ctx.ellipse(x + 4 * d, y + 14, 12, 5, 0, 0, 7); ctx.fill();
    // első láb
    ctx.fillStyle = dark; ctx.fillRect(x + 7 * d, y + 16, 4, 9);

    // fej
    const hx = x + 16 * d, hy = y + 4;
    ctx.fillStyle = orange;
    ctx.beginPath(); ctx.arc(hx, hy, 8, 0, 7); ctx.fill();
    // fülek (narancs) + sötét hegyek
    ctx.beginPath(); ctx.moveTo(hx - 7 * d, hy - 4); ctx.lineTo(hx - 6 * d, hy - 15); ctx.lineTo(hx + 1 * d, hy - 6); ctx.closePath(); ctx.fill();
    ctx.beginPath(); ctx.moveTo(hx + 2 * d, hy - 6); ctx.lineTo(hx + 7 * d, hy - 15); ctx.lineTo(hx + 10 * d, hy - 4); ctx.closePath(); ctx.fill();
    ctx.fillStyle = dark;
    ctx.beginPath(); ctx.moveTo(hx - 6 * d, hy - 15); ctx.lineTo(hx - 4.5 * d, hy - 11); ctx.lineTo(hx - 8 * d, hy - 12); ctx.closePath(); ctx.fill();
    ctx.beginPath(); ctx.moveTo(hx + 7 * d, hy - 15); ctx.lineTo(hx + 8.5 * d, hy - 12); ctx.lineTo(hx + 5 * d, hy - 11); ctx.closePath(); ctx.fill();
    // fehér pofa + orr
    ctx.fillStyle = cream;
    ctx.beginPath(); ctx.moveTo(hx + 3 * d, hy - 2); ctx.lineTo(hx + 14 * d, hy + 2); ctx.lineTo(hx + 3 * d, hy + 6); ctx.closePath(); ctx.fill();
    ctx.fillStyle = dark; ctx.beginPath(); ctx.arc(hx + 14 * d, hy + 2, 2, 0, 7); ctx.fill();
    // szem
    ctx.fillStyle = dark; ctx.beginPath(); ctx.ellipse(hx + 5 * d, hy - 1, 1.6, 2, 0, 0, 7); ctx.fill();
    ctx.fillStyle = 'rgba(255,255,255,0.7)'; ctx.beginPath(); ctx.arc(hx + 5.5 * d, hy - 1.6, 0.6, 0, 7); ctx.fill();
}

function drawSummitCross(ctx, x, y) {
    ctx.fillStyle = '#7a5a38';
    ctx.fillRect(x - 5, y - 120, 10, 120);
    ctx.fillRect(x - 32, y - 95, 64, 9);
    ctx.strokeStyle = 'rgba(0,0,0,0.15)'; ctx.lineWidth = 2;
    ctx.strokeRect(x - 5, y - 120, 10, 120);
    // kőrakás a tövénél
    ctx.fillStyle = '#8d8d94';
    ctx.beginPath(); ctx.ellipse(x, y - 4, 26, 10, 0, 0, 7); ctx.fill();
    ctx.beginPath(); ctx.ellipse(x - 8, y - 12, 12, 7, 0, 0, 7); ctx.fill();
    ctx.beginPath(); ctx.ellipse(x + 10, y - 10, 10, 6, 0, 0, 7); ctx.fill();
}

function drawHiker(ctx, p, time) {
    const x = p.x, y = p.y, h = p.h, f = p.facing;
    if (p.hurtT > 0 && Math.floor(time * 12) % 2 === 0) return; // sérülés-villogás
    const cx = x + p.w / 2;
    const legPhase = Math.sin(p.walkAnim * 2);

    ctx.save();
    const legTop = y + h - 18;
    const bodyTop = p.crouching ? y + 6 : y + 12;

    // talppálya-árnyék
    ctx.fillStyle = 'rgba(0,0,0,0.16)';
    ctx.beginPath(); ctx.ellipse(cx, y + h - 1, 13, 3.5, 0, 0, 7); ctx.fill();

    // --- HÁTIZSÁK (a test mögött) ---
    const bpX = f > 0 ? cx - 16 : cx + 6;
    ctx.fillStyle = '#2f5470';
    roundRectPath(ctx, bpX, bodyTop + 1, 11, 20, 3); ctx.fill();
    ctx.fillStyle = '#3e6b8c'; // fő felület
    roundRectPath(ctx, bpX + (f > 0 ? 1 : 1), bodyTop + 1, 9, 20, 3); ctx.fill();
    ctx.fillStyle = '#356183'; // fedőlap
    roundRectPath(ctx, bpX, bodyTop + 1, 11, 8, 3); ctx.fill();
    ctx.fillStyle = '#28455c'; // oldalzseb
    roundRectPath(ctx, bpX + (f > 0 ? -1 : 9), bodyTop + 9, 3, 9, 1.5); ctx.fill();
    ctx.strokeStyle = '#efb54a'; ctx.lineWidth = 1.2; // rögzítőpánt
    ctx.beginPath(); ctx.moveTo(bpX + 1, bodyTop + 12); ctx.lineTo(bpX + 10, bodyTop + 12); ctx.stroke();

    // --- LÁBAK (comb+lábszár, térddel; nadrág vagy csupasz + rövidnadrág) ---
    const hipY = legTop - 2, footY = y + h - 3;
    const frontLegDx = legPhase * 6, backLegDx = -legPhase * 6;
    function drawLeg(dx, front) {
        const kneeX = cx + dx * 0.5, kneeY = (hipY + footY) / 2;
        const footX = cx + dx;
        // szár (bőr vagy nadrág)
        ctx.lineCap = 'round';
        if (p.trousersOn) {
            ctx.strokeStyle = front ? '#54683f' : '#3f5030'; ctx.lineWidth = 7;
            ctx.beginPath(); ctx.moveTo(cx, hipY); ctx.lineTo(kneeX, kneeY); ctx.lineTo(footX, footY - 4); ctx.stroke();
        } else {
            ctx.strokeStyle = front ? '#e8b48c' : '#d29b73'; ctx.lineWidth = 6;
            ctx.beginPath(); ctx.moveTo(cx, hipY + 5); ctx.lineTo(kneeX, kneeY); ctx.lineTo(footX, footY - 4); ctx.stroke();
        }
        // bakancs (talp + felsőrész)
        ctx.fillStyle = '#4a3624';
        roundRectPath(ctx, footX - 5 + f * 2, footY - 5, 11, 6, 2); ctx.fill();
        ctx.fillStyle = '#2f2216'; // talp
        ctx.fillRect(footX - 5 + f * 2, footY + 0, 11, 2);
    }
    drawLeg(backLegDx, false);
    drawLeg(frontLegDx, true);
    if (!p.trousersOn) { // rövidnadrág a csupasz lábon
        ctx.fillStyle = '#4a5d3a'; roundRectPath(ctx, cx - 8, legTop - 5, 16, 11, 3); ctx.fill();
        ctx.fillStyle = '#3a4a2d'; ctx.fillRect(cx - 8, legTop + 3, 16, 3);
    }

    // --- TÖRZS (piros túrakabát, árnyékolással + cipzár + gallér) ---
    const bodyBot = legTop + 2, bodyH = bodyBot - bodyTop;
    ctx.fillStyle = '#c94f42';
    roundRectPath(ctx, cx - 8, bodyTop, 16, bodyH, 4); ctx.fill();
    ctx.fillStyle = '#e0685a'; // megvilágított (elülső) sáv
    roundRectPath(ctx, cx + (f > 0 ? -1 : -7), bodyTop + 1, 8, bodyH - 2, 3); ctx.fill();
    ctx.fillStyle = '#a83c31'; // árnyék (hátsó) sáv
    roundRectPath(ctx, cx + (f > 0 ? -8 : 1), bodyTop + 1, 7, bodyH - 2, 3); ctx.fill();
    ctx.strokeStyle = '#7f2b23'; ctx.lineWidth = 1; // cipzár
    ctx.beginPath(); ctx.moveTo(cx + f * 1, bodyTop + 3); ctx.lineTo(cx + f * 1, bodyBot - 3); ctx.stroke();
    ctx.fillStyle = '#e0685a'; // gallér
    roundRectPath(ctx, cx - 7, bodyTop - 2, 14, 5, 2); ctx.fill();
    // váll-pánt (hátizsáké) a mellkason
    ctx.strokeStyle = '#28455c'; ctx.lineWidth = 2.4;
    ctx.beginPath(); ctx.moveTo(cx + f * 6, bodyTop + 1); ctx.lineTo(cx - f * 2, bodyBot - 4); ctx.stroke();

    // --- FEJ + arc árnyékolás + kalap + haj ---
    const headY = bodyTop - 8, headR = 7;
    ctx.fillStyle = '#e8b48c';
    ctx.beginPath(); ctx.arc(cx, headY, headR, 0, 7); ctx.fill();
    ctx.fillStyle = '#d29b73'; // arc árnyékos (hátsó) fele
    ctx.beginPath(); ctx.arc(cx, headY, headR, f > 0 ? Math.PI / 2 : -Math.PI / 2, f > 0 ? 3 * Math.PI / 2 : Math.PI / 2); ctx.fill();
    ctx.fillStyle = '#8a6a4a'; // tarkó-haj
    ctx.beginPath(); ctx.arc(cx - f * 4, headY - 1, 4, 0, 7); ctx.fill();
    // kalap (karima + korona, árnyékkal)
    ctx.fillStyle = '#61462a';
    roundRectPath(ctx, cx - 10, headY - 7, 20, 4, 2); ctx.fill();
    ctx.fillStyle = '#7a5a38';
    roundRectPath(ctx, cx - 6, headY - 13, 12, 7, 2); ctx.fill();
    ctx.fillStyle = '#8f6c44'; ctx.fillRect(cx - 6, headY - 13, 12, 2); // kalap-fény
    ctx.fillStyle = '#5b4326'; ctx.fillRect(cx - 6, headY - 8, 12, 2); // kalapszalag
    // orr + szem
    ctx.fillStyle = '#d29b73'; ctx.beginPath(); ctx.arc(cx + f * headR, headY + 1, 1.6, 0, 7); ctx.fill();
    ctx.fillStyle = '#2a1e14'; ctx.beginPath(); ctx.arc(cx + f * 3, headY - 1, 1.5, 0, 7); ctx.fill();

    // fejlámpa
    if (p.headlight) {
        ctx.fillStyle = '#ffe873';
        ctx.fillRect(cx + f * 5, headY - 8, 4, 4);
        const lg = ctx.createRadialGradient(cx + f * 8, headY - 6, 2, cx + f * 8, headY - 6, 90);
        lg.addColorStop(0, 'rgba(255,240,150,0.5)'); lg.addColorStop(1, 'rgba(255,240,150,0)');
        ctx.fillStyle = lg;
        ctx.beginPath(); ctx.moveTo(cx + f * 7, headY - 6);
        ctx.lineTo(cx + f * 95, headY - 36); ctx.lineTo(cx + f * 95, headY + 26);
        ctx.closePath(); ctx.fill();
    }

    // --- KAR + TÚRABOT (barna), döfő (nem ütő) mozdulattal ---
    // nyugalmi: bot lefelé-előre leszúrva; támadás: előre döf, majd visszahúz
    let handX, handY, tipX, tipY;
    if (p.attackT > 0) {
        const p01 = (0.22 - p.attackT) / 0.22;          // 0→1
        const ext = Math.sin(p01 * Math.PI);            // ki- majd behúzás
        handX = cx + f * (7 + ext * 10); handY = bodyTop + 9;
        tipX = handX + f * (26 + ext * 30); tipY = handY + 4; // közel vízszintes döfés előre
    } else {
        handX = cx + f * 9; handY = bodyTop + 8;
        tipX = cx + f * 14; tipY = y + h;                // leszúrt vándorbot
    }
    // kar a bot markolatához
    ctx.strokeStyle = '#e0685a'; ctx.lineWidth = 4; ctx.lineCap = 'round';
    ctx.beginPath(); ctx.moveTo(cx + f * 3, bodyTop + 6); ctx.lineTo(handX, handY); ctx.stroke();
    ctx.fillStyle = '#e8b48c'; ctx.beginPath(); ctx.arc(handX, handY, 2.4, 0, 7); ctx.fill(); // kézfej
    // BOT: barna nyél
    const ang = Math.atan2(tipY - handY, tipX - handX);
    const gripX = handX - Math.cos(ang) * 7, gripY = handY - Math.sin(ang) * 7; // markolat a kéz mögött
    ctx.strokeStyle = '#7a5028'; ctx.lineWidth = 3.2; ctx.lineCap = 'round';
    ctx.beginPath(); ctx.moveTo(gripX, gripY); ctx.lineTo(tipX, tipY); ctx.stroke();
    ctx.strokeStyle = '#9c6b38'; ctx.lineWidth = 1; // fa-erezet fénye
    ctx.beginPath(); ctx.moveTo(gripX, gripY - 1); ctx.lineTo(tipX, tipY - 1); ctx.stroke();
    ctx.strokeStyle = '#2c2c30'; ctx.lineWidth = 4; // fekete markolat
    ctx.beginPath(); ctx.moveTo(gripX, gripY); ctx.lineTo(handX, handY); ctx.stroke();
    ctx.fillStyle = '#555'; // korong (basket) a hegy közelében
    ctx.beginPath(); ctx.ellipse(tipX - Math.cos(ang) * 6, tipY - Math.sin(ang) * 6, 3, 1.6, ang, 0, 7); ctx.fill();
    ctx.fillStyle = '#c0c0c8'; // fém hegy
    ctx.beginPath(); ctx.arc(tipX, tipY, 1.8, 0, 7); ctx.fill();
    // döfés-villanás a hegynél
    if (p.attackT > 0.11) {
        ctx.strokeStyle = 'rgba(255,255,255,0.7)'; ctx.lineWidth = 1.5;
        ctx.beginPath(); ctx.moveTo(tipX + f * 3, tipY - 4); ctx.lineTo(tipX + f * 7, tipY); ctx.lineTo(tipX + f * 3, tipY + 4); ctx.stroke();
    }

    // naptej-csillogás
    if (p.sunscreen > 0) {
        ctx.globalAlpha = 0.25 + 0.15 * Math.sin(time * 6);
        ctx.fillStyle = '#fff';
        ctx.beginPath(); ctx.arc(cx, headY, 9, 0, 7); ctx.fill();
        ctx.globalAlpha = 1;
    }
    ctx.restore();

    // üzenetbuborék
    if (p.msg && p.msgT > 0) {
        ctx.font = 'bold 13px sans-serif'; ctx.textAlign = 'center';
        const tw = ctx.measureText(p.msg).width + 16;
        ctx.fillStyle = 'rgba(20,26,34,0.85)';
        roundRectPath(ctx, cx - tw / 2, y - 34, tw, 22, 6); ctx.fill();
        ctx.fillStyle = '#fff';
        ctx.fillText(p.msg, cx, y - 19);
    }
}

function roundRectPath(ctx, x, y, w, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = { render, PALETTES };
}
