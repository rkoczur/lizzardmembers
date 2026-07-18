/* =========================================================
   Túra a csúcsra — játékos-fizika és entitás-logika
   ========================================================= */
'use strict';

// node-teszt alatt a levels.js nem globális — betöltjük
if (typeof module !== 'undefined' && typeof groundAtGen === 'undefined') {
    var groundAtGen = require('./levels.js').groundAtGen;
}

const PHYS = {
    gravity: 1500,
    walkSpeed: 220,
    crouchSpeed: 90,
    waterSpeed: 110,
    sneakSpeed: 95,    // Alt/AltGr tartva: lassú lopakodás rókához
    jumpVel: -560,
    doubleJumpVel: -520,
    playerW: 26,
    playerH: 48,
    crouchH: 30,
    climbStep: 14,     // ekkora lépcsőre ugrás nélkül fellép
};

function makePlayer(lvl) {
    return {
        x: 60, y: groundAtGen(lvl.segs, 60) - PHYS.playerH,
        vx: 0, vy: 0,
        w: PHYS.playerW, h: PHYS.playerH,
        facing: 1,
        onGround: true,
        jumpsUsed: 0,
        crouching: false,
        trousersOn: true,
        headlight: false,
        attackT: 0,        // hátralévő ütés-idő
        attackCd: 0,
        hurtT: 0,          // sérthetetlenség villogással
        hearts: 3,
        sunburn: 0,        // 0..100, 100 = -1 szív
        hunger: 100,       // 0 = -1 szív
        sunscreen: 0,      // 0..100 védelem
        inWater: false,
        inCave: false,
        inSun: false,
        walkAnim: 0,
        dead: false,
        atSummit: false,
        selfieDone: false,
        msg: '', msgT: 0,
        flashT: 0,
    };
}

function say(p, text, t) { p.msg = text; p.msgT = t || 2.2; }

function loseHeart(p, why) {
    if (p.hurtT > 0 || p.dead) return;
    p.hearts--;
    p.hurtT = 1.6;
    say(p, why, 2.5);
    if (p.hearts <= 0) { p.dead = true; }
}

function groundAt(lvl, x) { return groundAtGen(lvl.segs, x); }

function updatePlayer(p, lvl, input, dt, game) {
    if (p.dead) return;

    // --- állapot-zónák ---
    p.inWater = lvl.waters.some(w => p.x + p.w / 2 > w.x0 && p.x + p.w / 2 < w.x1);
    const cave = lvl.caves.find(c => p.x + p.w > c.x0 && p.x < c.x1);
    p.inCave = !!cave;
    p.inSun = !p.inCave && lvl.suns.some(s => p.x + p.w / 2 > s.x0 && p.x + p.w / 2 < s.x1);

    // --- guggolás ---
    const wantCrouch = input.down || (cave && p.onGround);
    if (wantCrouch && !p.crouching && p.onGround) {
        p.crouching = true; p.y += PHYS.playerH - PHYS.crouchH; p.h = PHYS.crouchH;
    } else if (!wantCrouch && p.crouching) {
        // csak akkor állhat fel, ha nincs plafon fölötte
        const c2 = lvl.caves.find(c => p.x + p.w > c.x0 && p.x < c.x1);
        if (!c2) { p.crouching = false; p.y -= PHYS.playerH - PHYS.crouchH; p.h = PHYS.playerH; }
    }

    // --- vízszintes mozgás ---
    let speed = PHYS.walkSpeed;
    if (p.crouching) speed = PHYS.crouchSpeed;
    if (p.inWater) speed = PHYS.waterSpeed;
    // lassú lopakodás (Alt/AltGr tartva) — így nem riasztod el a rókát
    p.sneaking = !!input.slow && !p.crouching && !p.inWater;
    if (p.sneaking) speed = PHYS.sneakSpeed;
    p.vx = 0;
    if (input.right) { p.vx = speed; p.facing = 1; }
    if (input.left) { p.vx = -speed; p.facing = -1; }

    let nx = p.x + p.vx * dt;

    // vízbe nadrágban nem lehet belépni
    if (p.trousersOn) {
        for (const w of lvl.waters) {
            const cx = nx + p.w / 2;
            const wasOut = (p.x + p.w / 2) <= w.x0 || (p.x + p.w / 2) >= w.x1;
            if (wasOut && cx > w.x0 && cx < w.x1) {
                nx = p.vx > 0 ? w.x0 - p.w / 2 : w.x1 - p.w / 2;
                say(p, 'Vedd le a nadrágod a vízhez! (Ctrl)');
            }
        }
    }
    // barlangba csak guggolva
    if (!p.crouching) {
        for (const c of lvl.caves) {
            const wasOut = p.x + p.w <= c.x0 || p.x >= c.x1;
            if (wasOut && nx + p.w > c.x0 && nx < c.x1) {
                nx = p.vx > 0 ? c.x0 - p.w : c.x1;
                say(p, 'Guggolj le a barlanghoz! (↓)');
            }
        }
    }

    // lépcsős terep: fal-ütközés (földön és levegőben is)
    const feetY = p.y + p.h;
    const aheadX = p.vx > 0 ? nx + p.w : nx;
    const gAhead = groundAt(lvl, aheadX);
    if (gAhead < feetY - PHYS.climbStep) {
        // túl magas lépcső — megállítjuk a falnál
        if (p.vx !== 0) {
            const wallX = findWallX(lvl, p.x + (p.vx > 0 ? p.w : 0), aheadX, feetY);
            if (wallX !== null) nx = p.vx > 0 ? wallX - p.w : wallX;
        }
    } else if (gAhead < feetY && gAhead >= feetY - PHYS.climbStep && p.onGround) {
        p.y = gAhead - p.h; // automatikus fellépés kis lépcsőre
    }
    if (nx < 0) nx = 0;
    if (nx > lvl.length - p.w) nx = lvl.length - p.w;
    p.x = nx;

    // --- ugrás ---
    if (input.jumpPressed) {
        if (p.onGround && !p.crouching) {
            p.vy = PHYS.jumpVel; p.onGround = false; p.jumpsUsed = 1;
        } else if (!p.onGround && p.jumpsUsed === 1) {
            p.vy = PHYS.doubleJumpVel; p.jumpsUsed = 2;
        }
        input.jumpPressed = false;
    }

    // --- gravitáció + talaj ---
    p.vy += PHYS.gravity * dt;
    p.y += p.vy * dt;
    const gNow = Math.max(groundAt(lvl, p.x + 2), groundAt(lvl, p.x + p.w - 2));
    const gUnder = Math.min(groundAt(lvl, p.x + 2), groundAt(lvl, p.x + p.w - 2));
    const landY = p.vy >= 0 ? gUnder : gNow;
    if (p.y + p.h >= landY && p.vy >= 0) {
        p.y = landY - p.h; p.vy = 0;
        p.onGround = true; p.jumpsUsed = 0;
    } else if (p.y + p.h < landY - 1) {
        p.onGround = false;
    }
    // barlang-plafon
    if (cave && p.y < cave.ceilY) { p.y = cave.ceilY; if (p.vy < 0) p.vy = 0; }

    // --- ütés túrabottal ---
    if (p.attackCd > 0) p.attackCd -= dt;
    if (input.attackPressed && p.attackCd <= 0) {
        p.attackT = 0.22; p.attackCd = 0.45;
        input.attackPressed = false;
        hitWithPole(p, lvl, game);
    }
    if (p.attackT > 0) p.attackT -= dt;

    // --- állapotsávok ---
    const moving = Math.abs(p.vx) > 1 || !p.onGround;
    if (moving) {
        p.hunger -= lvl.hungerRate * dt * (p.inWater ? 1.3 : 1);
        if (p.sunscreen > 0) p.sunscreen = Math.max(0, p.sunscreen - 3.2 * dt); // a naptej mozgás közben kopik
    } else {
        p.hunger -= 0.15 * dt;
    }
    if (p.hunger <= 0) { p.hunger = 55; loseHeart(p, 'Kimerültél az éhségtől! -1 élet'); }

    if (p.inWater) {
        p.sunburn = Math.max(0, p.sunburn - 9 * dt); // a víz hűsíti a leégést
    } else if (p.inSun && p.sunscreen <= 0) {
        p.sunburn += lvl.sunPower * dt * (p.trousersOn ? 1 : 1.5);
        if (p.sunburn >= 100) { p.sunburn = 35; loseHeart(p, 'Leégtél! -1 élet'); }
    } else if (!p.inSun) {
        p.sunburn = Math.max(0, p.sunburn - 1.2 * dt);
    }

    if (p.hurtT > 0) p.hurtT -= dt;
    if (p.msgT > 0) p.msgT -= dt; else p.msg = '';
    if (p.flashT > 0) p.flashT -= dt;
    if (moving && p.onGround) p.walkAnim += dt * 10; else if (p.onGround) p.walkAnim = 0;

    // --- csúcskereszt ---
    p.atSummit = Math.abs((p.x + p.w / 2) - lvl.summitX) < 70 && p.onGround;
}

// fal x-pozíciójának keresése (szegmenshatár a két x között)
function findWallX(lvl, fromX, toX, feetY) {
    const lo = Math.min(fromX, toX), hi = Math.max(fromX, toX);
    for (const s of lvl.segs) {
        if (s.x > lo - 1 && s.x <= hi + 1 && s.y < feetY - PHYS.climbStep) return s.x;
    }
    return null;
}

function hitWithPole(p, lvl, game) {
    const reach = 46;
    const hx0 = p.facing > 0 ? p.x + p.w : p.x - reach;
    const hx1 = hx0 + reach;
    const hy = p.y + p.h / 2;

    // kígyók
    for (const s of lvl.snakes) {
        if (s.dead) continue;
        const sy = groundAt(lvl, s.x) - 10;
        if (s.x + 18 > hx0 && s.x < hx1 && Math.abs(sy - (p.y + p.h)) < 60) {
            s.dead = true; game.effects.push({ kind: 'poof', x: s.x, y: sy, t: 0.4 });
            say(p, 'Kígyó elhárítva!');
        }
    }
    // medvék
    for (const b of lvl.bears) {
        if (b.dead) continue;
        if (b.x + 60 > hx0 && b.x < hx1) {
            b.hp--; b.hurtT = 0.4; b.x += p.facing * 26;
            if (b.hp <= 0) { b.dead = true; say(p, 'A medve elmenekült!'); game.effects.push({ kind: 'poof', x: b.x + 30, y: groundAt(lvl, b.x + 30) - 30, t: 0.5 }); }
        }
    }
    // gyümölcsfa megrázása
    for (const t of lvl.trees) {
        if (!t.fruity || t.shaken) continue;
        if (Math.abs((t.x) - (p.x + p.w / 2)) < 55) {
            t.shaken = true;
            for (let i = 0; i < t.fruits; i++) {
                lvl.items.push({ x: t.x - 25 + i * 22, y: t.y - 120, kind: 'apple', taken: false, vy: 0, onGround: false, falling: true });
            }
            say(p, 'Hullik az alma!');
        }
    }
}

function updateEntities(lvl, p, input, dt, game) {
    // --- kígyók ---
    for (const s of lvl.snakes) {
        if (s.dead) continue;
        s.x += s.dir * s.speed * dt;
        if (s.x < s.x0) { s.x = s.x0; s.dir = 1; }
        if (s.x > s.x1) { s.x = s.x1; s.dir = -1; }
        s.y = groundAt(lvl, s.x) - 12;
        // harapás
        if (rectsOverlap(p.x, p.y, p.w, p.h, s.x, s.y, 26, 12) && p.y + p.h > s.y - 6) {
            loseHeart(p, 'Megmart a kígyó! -1 élet');
        }
    }

    // --- medvék ---
    for (const b of lvl.bears) {
        if (b.dead) continue;
        b.y = groundAt(lvl, b.x + 30) - 52;
        if (b.hurtT > 0) { b.hurtT -= dt; continue; }
        const dx = (p.x + p.w / 2) - (b.x + 30);
        if (Math.abs(dx) < 300) {
            b.state = 'charge'; b.dir = dx > 0 ? 1 : -1;
            b.x += b.dir * (85 + lvl.n * 2.5) * dt;
        } else {
            b.state = 'idle';
        }
        // a medve a levegőben is eléri a játékost (nem lehet átugrani)
        if (rectsOverlap(p.x, p.y, p.w, p.h, b.x, b.y - 90, 62, 142)) {
            loseHeart(p, 'A medve megtámadott! -1 élet');
            p.vy = -350; p.x += (p.x < b.x ? -60 : 60);
        }
    }

    // --- róka ---
    for (const f of lvl.foxes) {
        if (f.gone) continue;
        f.y = groundAt(lvl, f.x) - 22;
        const dx = (p.x + p.w / 2) - f.x;
        const dist = Math.abs(dx);
        if (f.state !== 'flee' && dist < 340 && Math.abs(p.vx) > 150 && Math.sign(p.vx) === -Math.sign(dx)) {
            f.state = 'flee'; // túl gyorsan közelítettél
        }
        if (f.state === 'flee') {
            f.x += (dx > 0 ? -1 : 1) * 260 * dt;
            if (dist > 900) f.gone = true;
        }
        // fotó lehetőség
        f.photoReady = f.state !== 'flee' && dist < 140 && !f.photoDone;
        if (f.photoReady && input.photoPressed) {
            input.photoPressed = false;
            f.photoDone = true; f.state = 'flee';
            game.time = Math.max(0, game.time - 20);
            p.flashT = 0.35;
            say(p, 'Rókafotó! -20 másodperc 📷', 3);
        }
    }

    // --- tárgyak (alma, szendvics, szuper szendvics) ---
    for (const it of lvl.items) {
        if (it.taken) continue;
        if (it.falling) {
            it.vy += 900 * dt; it.y += it.vy * dt;
            const g = groundAt(lvl, it.x);
            if (it.y >= g - 12) { it.y = g - 12; it.falling = false; }
        }
        if (rectsOverlap(p.x, p.y, p.w, p.h, it.x - 12, it.y - 12, 24, 24)) {
            it.taken = true;
            if (it.kind === 'apple') { p.hunger = Math.min(100, p.hunger + 28); say(p, 'Alma! +éhségcsökkentés'); }
            else if (it.kind === 'sandwich') { p.hunger = Math.min(100, p.hunger + 55); say(p, 'Szendvics! Jóllakottság ↑'); }
            else { p.hearts = Math.min(3, p.hearts + 1); p.hunger = Math.min(100, p.hunger + 60); say(p, 'SZUPER SZENDVICS! +1 élet ❤'); }
            game.effects.push({ kind: 'sparkle', x: it.x, y: it.y, t: 0.5 });
        }
    }

    // --- effektek ---
    for (const e of game.effects) e.t -= dt;
    game.effects = game.effects.filter(e => e.t > 0);
}

function rectsOverlap(x1, y1, w1, h1, x2, y2, w2, h2) {
    return x1 < x2 + w2 && x1 + w1 > x2 && y1 < y2 + h2 && y1 + h1 > y2;
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = { PHYS, makePlayer, updatePlayer, updateEntities, rectsOverlap, hitWithPole, loseHeart };
}
