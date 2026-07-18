/* =========================================================
   Túra a csúcsra — pályagenerátor (determinisztikus, seedelt)
   Tiszta függvények: node alatt is futtatható teszteléshez.
   ========================================================= */
'use strict';

// Seedelt véletlenszám-generátor (mulberry32)
function mulberry32(seed) {
    let a = seed >>> 0;
    return function () {
        a |= 0; a = (a + 0x6D2B79F5) | 0;
        let t = Math.imul(a ^ (a >>> 15), 1 | a);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

const FALLBACK_LEVELS = [
    { name: 'Hármas-határ-hegy', height: 497 }, { name: 'Kékestető', height: 1014 },
    { name: 'Schneeberg', height: 2076 }, { name: 'Dumbier', height: 2043 },
    { name: 'Grosses Buchstein', height: 2224 }, { name: 'Rysy', height: 2503 },
    { name: 'Sauleck', height: 3079 }, { name: 'Grossvenediger', height: 3657 },
    { name: 'Grossglockner', height: 3798 }, { name: 'Monte Rosa', height: 4634 },
    { name: 'Matterhorn', height: 4478 }, { name: 'Kazbek', height: 5054 },
    { name: 'Denali', height: 6190 }, { name: 'Lenin-csúcs', height: 7134 },
    { name: 'Cho Oyu', height: 8188 }, { name: 'Nanga Parbat', height: 8126 },
    { name: 'Annapurna', height: 8091 }, { name: 'K2', height: 8611 },
    { name: 'Mount Everest', height: 8849 },
];

function levelMeta(n) { // n: 1..19
    const list = (typeof window !== 'undefined' && window.GAME_LEVELS) ? window.GAME_LEVELS : FALLBACK_LEVELS;
    return list[n - 1] || FALLBACK_LEVELS[n - 1];
}

/*
 Pálya-adatszerkezet:
   segs:    [{x, w, y}]  — vízszintes talajlapok, y = talaj teteje (felfelé csökken)
   zones:   [{type:'woods'|'slope'|'rocky', x0, x1}]
   suns:    [{x0, x1}]        — erős napsütés sávok
   waters:  [{x0, x1, y}]     — vízátkelés (talajszinten)
   caves:   [{x0, x1, ceilY}] — barlang: alacsony plafon, sötét
   snakes / bears / foxes / trees / items: entitáslisták
   summitX: csúcskereszt pozíció
*/
function generateLevel(n) {
    const rng = mulberry32(1337 + n * 7919);
    const meta = levelMeta(n);
    const tier = n <= 6 ? 0 : (n <= 12 ? 1 : 2); // 0: erdős, 1: alpesi, 2: havas
    const length = 2600 + n * 320;

    const lvl = {
        n, tier, length,
        name: meta.name, height: meta.height,
        baseElev: Math.max(0, Math.round(meta.height * 0.6)), // ösvény indulási magassága (m)
        segs: [], zones: [], suns: [], waters: [], caves: [],
        snakes: [], bears: [], foxes: [], trees: [], bushes: [], items: [],
        decor: [],
        summitX: 0, summitY: 0,
        sunPower: 7 + n * 0.55,          // leégés sebessége napon
        hungerRate: 1.0 + n * 0.055,     // éhezés sebessége mozgás közben
    };

    // --- zóna-sorrend összeállítása ---
    const zoneSeq = [];
    zoneSeq.push({ type: 'woods', w: 520 + rng() * 240 });
    let remaining = length - zoneSeq[0].w - 700; // 700: csúcs-szakasz
    const rockyBias = 0.25 + tier * 0.22 + n * 0.012;
    while (remaining > 400) {
        const r = rng();
        let type, w;
        if (r < rockyBias) { type = 'rocky'; w = 450 + rng() * 350; }
        else if (r < rockyBias + 0.35) { type = 'slope'; w = 420 + rng() * 320; }
        else { type = 'woods'; w = 380 + rng() * 300; }
        w = Math.min(w, remaining);
        zoneSeq.push({ type, w });
        remaining -= w;
    }
    zoneSeq.push({ type: 'rocky', w: 700 + remaining }); // csúcshoz vezető sziklák

    // --- terep felépítése zónánként ---
    let x = 0, y = 0;
    const seg = (w, ny) => { lvl.segs.push({ x, w, y: ny }); x += w; y = ny; };
    seg(160, 0); // biztonságos start

    let lastItemX = 0, waterPlaced = 0, cavePlaced = 0;
    const doubleJumpAllowed = n >= 3;

    for (const z of zoneSeq) {
        const zx0 = x, zEnd = x + z.w;
        lvl.zones.push({ type: z.type, x0: zx0, x1: zEnd });

        if (z.type === 'woods') {
            while (x < zEnd) {
                const w = 200 + rng() * 180;
                const step = rng() < 0.25 ? -(14 + rng() * 16) : (rng() < 0.12 ? (10 + rng() * 10) : 0);
                seg(Math.min(w, zEnd - x + 1), y + step);
                // fák
                const s = lvl.segs[lvl.segs.length - 1];
                let tx = s.x + 30;
                while (tx < s.x + s.w - 40) {
                    if (rng() < 0.55) {
                        const fruity = rng() < 0.28 && tier < 2;
                        // fatípus: 0 fenyő, 1 gömb lombos, 2 tölgy, 3 nyír
                        let type;
                        if (fruity) type = rng() < 0.5 ? 1 : 2;               // termő = lombos
                        else if (tier === 2) type = rng() < 0.85 ? 0 : 3;     // havas = fenyő
                        else type = rng() < 0.4 ? 0 : rng() < 0.66 ? 1 : rng() < 0.85 ? 2 : 3;
                        lvl.trees.push({ x: tx, y: s.y, type, fruity, fruits: fruity ? 2 + Math.floor(rng() * 2) : 0, shaken: false });
                    }
                    tx += 90 + rng() * 120;
                }
                // tisztás = napos sáv
                if (rng() < 0.3) {
                    const mw = 120 + rng() * 140;
                    const mx = s.x + rng() * Math.max(1, s.w - mw);
                    lvl.suns.push({ x0: mx, x1: mx + mw });
                }
                // vízátkelés (pályánként max 1-2, erdőben)
                if (waterPlaced < (n >= 8 ? 2 : 1) && rng() < 0.35 && zEnd - x > 320 && x - zx0 > 150) {
                    const ww = 130 + rng() * 90 + n * 3;
                    lvl.waters.push({ x0: x, x1: x + ww, y });
                    seg(ww, y); // víz alatti talaj ugyanazon a szinten
                    waterPlaced++;
                }
                // róka (nagyon ritka)
                if (rng() < 0.10 && lvl.foxes.length < 1) {
                    lvl.foxes.push({ x: x - 60 - rng() * 80, y: 0, state: 'idle', dir: -1, gone: false, photoDone: false });
                }
                // kígyó kis eséllyel erdőben is
                if (rng() < 0.08 + n * 0.004) lvl.snakes.push(makeSnake(x - 100, rng));
            }
        } else if (z.type === 'slope') {
            while (x < zEnd) {
                const w = 100 + rng() * 90;
                const step = 26 + rng() * 40; // sima ugrással teljesíthető
                seg(Math.min(w, zEnd - x + 1), y - step);
                if (rng() < 0.22 + n * 0.012) lvl.snakes.push(makeSnake(x - w / 2, rng));
            }
            // barlang a zóna közepére (pályánként legfeljebb 2)
            if (cavePlaced < (n >= 6 ? 2 : 1) && rng() < 0.75) {
                // sík "előtér" a bejárat előtt: ide fel lehet lépni, megállni és leguggolni,
                // mielőtt a játékos guggolva belép — enélkül a bejáratnál beragadna
                seg(120, y);
                const cw = 200 + rng() * 140 + n * 6;
                const cy = y; // plafon: talaj + 40 px rés → csak guggolva fér el
                lvl.caves.push({ x0: x, x1: x + cw, ceilY: cy - 40 });
                seg(cw, y);
                // bő sík kijárat: legyen hely felállni és elugrani a barlang után
                seg(130, y);
                // a barlang utáni ELSŐ lépcső mindig szelíd (egy ugrással vehető),
                // hogy közvetlenül kilépés után soha ne kelljen dupla ugrás
                seg(90, y - (40 + rng() * 30));
                seg(70, y);
                cavePlaced++;
            }
        } else { // rocky
            // A dupla ugrás max ~184 px-t ér el, ezért a magas lépcső ≤140 px, és
            // utána MINDIG széles sík pihenő jön, hogy legyen hely felállni és elugrani.
            // Két magas lépcső soha nem követi közvetlenül egymást.
            let lastWasBig = false;
            while (x < zEnd) {
                const w = 80 + rng() * 80;
                let step, big = false;
                const r = rng();
                if (doubleJumpAllowed && !lastWasBig && r < 0.10 + n * 0.012) {
                    step = 112 + rng() * 26; big = true;      // 112–138 px: dupla ugrással kényelmesen vehető
                } else {
                    step = 40 + rng() * 45;                    // 40–85 px: egy ugrás
                }
                seg(Math.min(w, zEnd - x + 1), y - step);
                // magas lépcső után széles, sík landolóhely (nem újabb fal azonnal)
                if (big && x < zEnd) { seg(Math.min(120, zEnd - x + 1), y); }
                lastWasBig = big;
                // napos sávok gyakran
                if (rng() < 0.45) {
                    const s = lvl.segs[lvl.segs.length - 1];
                    lvl.suns.push({ x0: s.x, x1: s.x + s.w });
                }
                if (rng() < 0.10 + n * 0.008) lvl.snakes.push(makeSnake(x - w / 2, rng));
            }
            // medve (5. pályától, egyre több)
            const maxBears = n < 5 ? 0 : Math.min(3, 1 + Math.floor((n - 5) / 6));
            if (lvl.bears.length < maxBears && rng() < 0.6 && z.w > 500) {
                lvl.bears.push({ x: zx0 + z.w * (0.4 + rng() * 0.3), y: 0, hp: 3, state: 'idle', dir: -1, hurtT: 0, dead: false });
            }
        }

        // ennivalók elhelyezése (magasabb pályán ritkábban)
        const itemGap = 380 + n * 22;
        while (x - lastItemX > itemGap) {
            lastItemX += itemGap;
            const gy = groundAtGen(lvl.segs, lastItemX);
            const r = rng();
            let kind = r < 0.55 ? 'apple' : 'sandwich';
            if (r > 0.985) kind = 'super'; // szuper szendvics: nagyon ritka
            lvl.items.push({ x: lastItemX, y: gy - 60 - rng() * 55, kind, taken: false, vy: 0, onGround: false });
        }
    }

    // --- csúcs-szakasz ---
    seg(140, y - 60);
    seg(260, y - 40); // csúcsplató
    const top = lvl.segs[lvl.segs.length - 1];
    lvl.summitX = top.x + 150;
    lvl.summitY = top.y;
    seg(120, y + 30); // kis lejtő a kereszt mögött (látvány)
    lvl.length = x;

    // garantált szuper szendvics a nehezebb pályákon néha
    if (n >= 7 && mulberry32(n * 31)( ) < 0.35) {
        const sx = lvl.length * (0.3 + 0.4 * mulberry32(n * 77)());
        lvl.items.push({ x: sx, y: groundAtGen(lvl.segs, sx) - 90, kind: 'super', taken: false, vy: 0, onGround: false });
    }

    // növényzet magasság szerint: ≤1500 m fák, 1500–2000 m bokrok, >2000 m semmi
    const keptTrees = [];
    for (const t of lvl.trees) {
        const elev = elevationAt(lvl, t.y);
        if (elev > 2000) continue;                                  // fahatár felett nincs növény
        if (elev > 1500) { lvl.bushes.push({ x: t.x, y: t.y, type: (t.x | 0) % 2 }); continue; } // csak bokor
        keptTrees.push(t);
    }
    lvl.trees = keptTrees;

    // dekoráció: sziklák, bokrok, virágok — magasság szerint szűrve
    for (let dx = 100; dx < lvl.length; dx += 60 + rng() * 160) {
        const gy = groundAtGen(lvl.segs, dx);
        const elev = elevationAt(lvl, gy);
        let kind = Math.floor(rng() * 3); // 0: kő, 1: virág, 2: bokor/hókupac
        if (elev > 2000) { if (lvl.tier === 2) { if (kind === 1) kind = 2; } else kind = 0; } // nincs növény (havason marad a hókupac)
        else if (elev > 1500 && kind === 1) kind = 2; // virág helyett bokor
        lvl.decor.push({ x: dx, y: gy, kind });
    }

    return lvl;
}

// aktuális tengerszint feletti magasság (m) egy talaj-y alapján.
// y és summitY felfelé csökken (negatív); 0 = ösvény indulása, 1 = csúcs.
function elevationAt(lvl, y) {
    const denom = lvl.summitY || -1;
    let f = y / denom;
    if (f < 0) f = 0; else if (f > 1) f = 1;
    return Math.round(lvl.baseElev + (lvl.height - lvl.baseElev) * f);
}

function makeSnake(cx, rng) {
    return {
        x: cx, y: 0,
        x0: cx - 40 - rng() * 30, x1: cx + 40 + rng() * 30,
        dir: rng() < 0.5 ? -1 : 1, speed: 34 + rng() * 26,
        dead: false, hurtT: 0,
    };
}

// talajmagasság adott x-nél a szegmenslistából
function groundAtGen(segs, x) {
    for (let i = segs.length - 1; i >= 0; i--) {
        const s = segs[i];
        if (x >= s.x && x < s.x + s.w) return s.y;
    }
    if (segs.length && x >= segs[segs.length - 1].x) return segs[segs.length - 1].y;
    return segs.length ? segs[0].y : 0;
}

// node-teszt támogatás
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { mulberry32, generateLevel, groundAtGen, elevationAt, FALLBACK_LEVELS };
}
