<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Felhasználói útmutató — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    html { scroll-behavior: smooth; }

    .help-page { background: var(--bg); min-height: 100vh; }

    .help-layout {
      display: grid;
      grid-template-columns: 230px 1fr;
      max-width: 1180px;
      margin: 0 auto;
      padding: 0 24px;
      gap: 40px;
      align-items: start;
    }

    /* ── TOC ────────────────────────────── */
    .help-toc {
      position: sticky;
      top: 24px;
      padding: 24px 0 48px;
    }
    .help-toc-title {
      font-size: 10.5px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .09em;
      color: var(--text-muted);
      margin-bottom: 10px;
      padding-left: 12px;
    }
    .help-toc ol { list-style: none; padding: 0; margin: 0; }
    .help-toc li { margin: 2px 0; }
    .help-toc a {
      display: block;
      padding: 5px 12px;
      font-size: 13px;
      color: var(--text-muted);
      border-left: 2px solid var(--border);
      text-decoration: none;
      border-radius: 0 4px 4px 0;
      transition: color .15s, border-color .15s, background .15s;
    }
    .help-toc a:hover { color: var(--primary); background: rgba(41,119,111,.06); border-left-color: var(--primary); }
    .help-toc a.toc-active { color: var(--primary); border-left-color: var(--primary); font-weight: 600; background: rgba(41,119,111,.08); }

    /* ── Main ───────────────────────────── */
    .help-main { padding: 32px 0 80px; }

    .help-header {
      display: flex;
      align-items: center;
      gap: 18px;
      margin-bottom: 40px;
      padding-bottom: 28px;
      border-bottom: 2px solid var(--border);
    }
    .help-header-logo { width: 56px; height: 56px; object-fit: contain; flex-shrink: 0; }
    .help-header-text h1 { font-size: 24px; font-weight: 800; color: var(--primary); margin: 0 0 2px; }
    .help-header-text p { font-size: 13px; color: var(--text-muted); margin: 0; }
    .help-header-btn { margin-left: auto; flex-shrink: 0; }

    /* ── Section ────────────────────────── */
    .help-section { margin-bottom: 56px; }
    .help-section-title {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 19px;
      font-weight: 700;
      color: var(--text);
      margin: 0 0 20px;
      padding-bottom: 12px;
      border-bottom: 2px solid var(--border);
    }
    .help-section-num {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 30px; height: 30px;
      background: var(--primary);
      color: #fff;
      border-radius: 50%;
      font-size: 13px;
      font-weight: 700;
      flex-shrink: 0;
    }
    .help-section-emoji { font-size: 20px; line-height: 1; }

    h3.help-h3 {
      font-size: 15px;
      font-weight: 700;
      color: var(--text);
      margin: 24px 0 10px;
    }

    /* ── Steps ──────────────────────────── */
    .help-steps {
      list-style: none;
      padding: 0; margin: 0 0 16px;
      counter-reset: step-c;
    }
    .help-steps li {
      position: relative;
      padding: 9px 0 9px 38px;
      border-bottom: 1px solid var(--border);
      font-size: 14px;
      line-height: 1.5;
      counter-increment: step-c;
    }
    .help-steps li:last-child { border-bottom: none; }
    .help-steps li::before {
      content: counter(step-c);
      position: absolute; left: 0; top: 9px;
      width: 24px; height: 24px;
      background: rgba(41,119,111,.12);
      color: var(--primary);
      border-radius: 50%;
      font-size: 11px; font-weight: 700;
      display: flex; align-items: center; justify-content: center;
    }

    /* ── Callouts ───────────────────────── */
    .help-callout {
      display: flex;
      gap: 12px;
      padding: 12px 16px;
      border-radius: 6px;
      margin: 14px 0;
      font-size: 13.5px;
      line-height: 1.55;
    }
    .help-callout-icon { font-size: 17px; flex-shrink: 0; margin-top: 1px; }
    .help-callout.tip  { background: rgba(41,119,111,.10); border-left: 3px solid var(--primary); }
    .help-callout.warn { background: rgba(217,119,6,.10);  border-left: 3px solid #d97706; }
    .help-callout.info { background: rgba(0,0,0,.04);      border-left: 3px solid var(--border); color: var(--text-muted); }

    /* ── Level table ────────────────────── */
    .help-level-table { width: 100%; border-collapse: collapse; margin: 12px 0; font-size: 13.5px; }
    .help-level-table th {
      padding: 8px 12px;
      background: var(--bg-alt, #f5efe4);
      font-size: 11px; font-weight: 700;
      text-transform: uppercase; letter-spacing: .06em;
      color: var(--text-muted);
      border-bottom: 1px solid var(--border);
      text-align: left;
    }
    .help-level-table td { padding: 9px 12px; border-bottom: 1px solid var(--border); }
    .help-level-table tr:last-child td { border-bottom: none; }
    .help-level-table tr:hover td { background: rgba(41,119,111,.04); }
    .help-level-img { width: 28px; height: 28px; object-fit: contain; }

    /* ── Generic helpers ─────────────────── */
    .help-ul { padding-left: 20px; margin: 8px 0 14px; }
    .help-ul li { margin-bottom: 5px; font-size: 14px; line-height: 1.5; }
    .help-p { font-size: 14px; line-height: 1.6; margin: 0 0 12px; color: var(--text); }

    /* ── Back to top ─────────────────────── */
    #back-to-top {
      display: none;
      position: fixed; bottom: 24px; right: 24px;
      width: 42px; height: 42px;
      background: var(--primary); color: #fff;
      border: none; border-radius: 50%;
      cursor: pointer;
      align-items: center; justify-content: center;
      font-size: 18px; font-weight: 700;
      box-shadow: 0 4px 14px rgba(0,0,0,.25);
      z-index: 500;
      transition: opacity .2s;
    }
    #back-to-top:hover { opacity: .85; }

    /* ── Responsive ──────────────────────── */
    @media (max-width: 780px) {
      .help-layout { grid-template-columns: 1fr; padding: 0 14px; gap: 0; }
      .help-toc {
        position: static;
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 16px;
        margin: 20px 0 24px;
      }
      .help-header { flex-wrap: wrap; }
      .help-header-btn { margin-left: 0; width: 100%; }
    }
  </style>
</head>
<body class="help-page">

<div class="help-layout">

  <!-- ══ TOC ══════════════════════════════════════════ -->
  <nav class="help-toc" aria-label="Tartalomjegyzék">
    <div class="help-toc-title">Tartalomjegyzék</div>
    <ol>
      <li><a href="#sec-bejelentkezes">1. Bejelentkezés &amp; Regisztráció</a></li>
      <li><a href="#sec-tagsag">2. Tagság — Vezérlőpult</a></li>
      <li><a href="#sec-turanaplo">3. Túranapló</a></li>
      <li><a href="#sec-meghirdetett">4. Meghirdetett Túrák</a></li>
      <li><a href="#sec-profil">5. Profilszerkesztés</a></li>
      <li><a href="#sec-statisztikak">6. Statisztikák</a></li>
      <li><a href="#sec-toplista">7. Toplista</a></li>
    </ol>
  </nav>

  <!-- ══ MAIN ══════════════════════════════════════════ -->
  <main class="help-main">

    <!-- Fejléc -->
    <div class="help-header">
      <img src="<?= BASE_URL ?>/assets/img/lizzard_logo.png" alt="<?= APP_NAME ?> logó" class="help-header-logo">
      <div class="help-header-text">
        <h1>Felhasználói útmutató</h1>
        <p><?= APP_NAME ?> — tagok portálja</p>
      </div>
      <div class="help-header-btn">
        <?php if (isLoggedIn()): ?>
          <a href="<?= BASE_URL ?>/user/index.php" class="btn btn-primary btn-sm">Irányítópult</a>
        <?php else: ?>
          <a href="<?= BASE_URL ?>/login.php" class="btn btn-primary btn-sm">Bejelentkezés</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════
         1. BEJELENTKEZÉS & REGISZTRÁCIÓ
    ══════════════════════════════════════════════════ -->
    <section class="help-section" id="sec-bejelentkezes">
      <h2 class="help-section-title">
        <span class="help-section-num">1</span>
        <span class="help-section-emoji">🔐</span>
        Bejelentkezés &amp; Regisztráció
      </h2>

      <h3 class="help-h3">1.1 Bejelentkezés</h3>
      <p class="help-p">A portálra a <strong>bejelentkezési oldalon</strong> léphetsz be. A belépéshez a regisztrált felhasználónevedet <em>vagy</em> e-mail címedet, valamint a jelszavadat kell megadni.</p>
      <ol class="help-steps">
        <li>Nyisd meg a portál kezdőoldalát — automatikusan a bejelentkezési oldalra kerülsz.</li>
        <li>Írd be a <strong>felhasználónevedet</strong> vagy <strong>e-mail címedet</strong> a felső mezőbe.</li>
        <li>Írd be a <strong>jelszavadat</strong> az alsó mezőbe.</li>
        <li>Kattints a <strong>„Bejelentkezés"</strong> gombra.</li>
        <li>Sikeres bejelentkezés után a vezérlőpultra kerülsz.</li>
      </ol>
      <div class="help-callout warn">
        <span class="help-callout-icon">⚠️</span>
        <div><strong>Fiókzárolás:</strong> 3 egymást követő hibás jelszó után a fiókod automatikusan zárolásra kerül. Feloldáshoz keresd az adminisztrátort.</div>
      </div>
      <div class="help-callout info">
        <span class="help-callout-icon">ℹ️</span>
        <div>Ha az IP-címedről 3 sikertelen kísérlet érkezik (ismeretlen felhasználónévvel), az IP-cím is zárolásra kerülhet.</div>
      </div>

      <h3 class="help-h3">1.2 Belépési kérelem (Regisztráció)</h3>
      <p class="help-p">A portálra csak az egyesület tagjai léphetnek be. Ha még nem vagy tag, a bejelentkezési oldalon a <strong>„Belépés az egyesületbe"</strong> gombra kattintva beküldheted a csatlakozási kérelmedet.</p>
      <ol class="help-steps">
        <li>Kattints a <strong>„Belépés az egyesületbe"</strong> gombra a bejelentkezési oldalon.</li>
        <li>Töltsd ki az adatlapot: <strong>név, e-mail, telefonszám, születési dátum, lakcím</strong>.</li>
        <li>Adott esetben jelöld be az opcionális hozzájárulásokat (e-mail láthatóság, fényképek).</li>
        <li>Fogadd el az <strong>adatvédelmi nyilatkozatot és az alapszabályt</strong> (kötelező).</li>
        <li>Kattints a <strong>„Kérelem beküldése"</strong> gombra.</li>
        <li>Az adminisztrátor megvizsgálja a kérelmet, és e-mailben értesít a döntésről.</li>
      </ol>
      <div class="help-callout tip">
        <span class="help-callout-icon">💡</span>
        <div>A kérelem beküldése után nem tudsz azonnal belépni — várj az adminisztrátori jóváhagyásra és az aktiváló e-mailre.</div>
      </div>

      <h3 class="help-h3">1.3 Jelszó visszaállítása</h3>
      <p class="help-p">Ha elfelejtetted a jelszavadat, a bejelentkezési oldal alján az <strong>„Elfelejtetted a jelszavad?"</strong> linkre kattintva kérhetsz visszaállítási e-mailt.</p>
      <ol class="help-steps">
        <li>Kattints az <strong>„Elfelejtetted a jelszavad?"</strong> linkre.</li>
        <li>Írd be a fiókodhoz tartozó <strong>e-mail címedet</strong>.</li>
        <li>Kattints a <strong>„Visszaállítási link küldése"</strong> gombra.</li>
        <li>Ellenőrizd a postaládádat — kapsz egy e-mailt egy egyszeri visszaállítási linkkel.</li>
        <li>A linkre kattintva adj meg egy <strong>új jelszót</strong> (min. 8 karakter), majd erősítsd meg.</li>
        <li>A jelszó mentése után automatikusan visszakerülsz a bejelentkezési oldalra.</li>
      </ol>
      <div class="help-callout warn">
        <span class="help-callout-icon">⚠️</span>
        <div>A visszaállítási link <strong>korlátozott ideig érvényes</strong> és csak egyszer használható. Ha lejárt, kérj újat.</div>
      </div>
    </section>

    <!-- ══════════════════════════════════════════════════
         2. TAGSÁG — VEZÉRLŐPULT
    ══════════════════════════════════════════════════ -->
    <section class="help-section" id="sec-tagsag">
      <h2 class="help-section-title">
        <span class="help-section-num">2</span>
        <span class="help-section-emoji">🏠</span>
        Tagság — Vezérlőpult
      </h2>

      <p class="help-p">A bejelentkezés után a <strong>Vezérlőpulton</strong> találod magad. Ez az oldalad összefoglalója: látod a pontjaidat, szintedet, tagságod státuszát, és a meghirdetett túrákra leadott jelentkezéseidet.</p>

      <h3 class="help-h3">2.1 Pontok és szintrendszer</h3>
      <p class="help-p">Az egyesületi tagok <strong>Lizzardier-pontokat</strong> gyűjtenek a részt vett túrák alapján. A pontszámot az adminisztrátor jóváhagyott túrák adataiból automatikusan számítja ki a rendszer — a tagnak nincs teendője. A pontok alapján <strong>9 fokozat</strong> van:</p>

      <div class="card" style="margin-bottom:20px;">
        <div class="table-wrap">
          <table class="help-level-table">
            <thead>
              <tr>
                <th>Fokozat</th>
                <th>Rang</th>
                <th>Magyar elnevezés</th>
                <th>Minimum pontszám</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $levelThresholds = [1=>0, 2=>3, 3=>25, 4=>50, 5=>100, 6=>170, 7=>250, 8=>330, 9=>500];
              foreach ($levelThresholds as $lvl => $minPts):
                $imgFile = getLevelImageFilename($lvl);
              ?>
              <tr>
                <td>
                  <?php if ($imgFile): ?>
                    <img src="<?= BASE_URL ?>/assets/img/<?= e($imgFile) ?>" alt="<?= getLevelLabel($lvl) ?>" class="help-level-img">
                  <?php else: ?>
                    <span style="font-size:18px;">🎖️</span>
                  <?php endif; ?>
                </td>
                <td style="font-weight:600;"><?= $lvl ?>.</td>
                <td><span class="level-badge <?= getLevelClass($lvl) ?>"><?= getLevelLabel($lvl) ?></span></td>
                <td style="color:var(--primary);font-weight:600;"><?= $minPts ?> pont</td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="help-callout info">
        <span class="help-callout-icon">ℹ️</span>
        <div>A pontjaid és fokozatod automatikusan frissülnek, amint az adminisztrátor jóváhagy egy túrát, amelyen részt vettél.</div>
      </div>

      <h3 class="help-h3">2.2 Szint előrehaladás-sáv</h3>
      <p class="help-p">A vezérlőpult tetején egy <strong>előrehaladás-sáv</strong> mutatja, hány pont hiányzik a következő fokozathoz. Ha elérted a legmagasabb fokozatot (Ezredes, 500 pont), a sáv helyett egy gratulációs üzenet jelenik meg.</p>

      <h3 class="help-h3">2.3 Tagság státusza</h3>
      <p class="help-p">A vezérlőpulton egy jelvény mutatja a tagságod aktuális állapotát:</p>
      <ul class="help-ul">
        <li><span class="badge badge-active">Aktív</span> — a tagdíj rendben van.</li>
        <li><span class="badge badge-overdue">Tagdíj elmaradt</span> — idén még nem fizettél tagdíjat, de tavaly igen ezért még aktív vagy,</li>
        <li><span class="badge badge-inactive">Inaktív</span> — több mint 1 éve nem fizettél tagdíjat, így tagságod felfüggesztve.</li>
      </ul>

      <h3 class="help-h3">2.4 Részvételi díj kedvezmény fokozat alapján</h3>
      <p class="help-p">Magasabb fokozaton <strong>kedvezményt kapsz</strong> a meghirdetett túrák részvételi díjából. A kedvezmény mértéke:</p>
      <div class="card" style="margin-bottom:16px;">
        <div class="table-wrap">
          <table class="help-level-table">
            <thead>
              <tr><th>Fokozat</th><th>Kedvezmény</th></tr>
            </thead>
            <tbody>
              <?php
              $discountGroups = [
                [1, 4, '0%'],
                [5, 6, '5%'],
                [7, 8, '10%'],
                [9, 9, '15%'],
              ];
              foreach ($discountGroups as [$from, $to, $disc]):
              ?>
              <tr>
                <td>
                  <?php for ($l = $from; $l <= $to; $l++): ?>
                    <span class="level-badge <?= getLevelClass($l) ?>" style="margin-right:4px;"><?= getLevelLabel($l) ?></span>
                  <?php endfor; ?>
                </td>
                <td>
                  <strong style="color:<?= $disc === '0%' ? 'var(--text-muted)' : 'var(--primary)' ?>;">
                    <?= $disc ?>
                  </strong>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="help-callout tip">
        <span class="help-callout-icon">💡</span>
        <div>A kedvezmény automatikusan érvényesül a túra részletoldalán — az eredeti ár mellett látod az aktuális szintednek megfelelő kedvezményes árat.</div>
      </div>

      <h3 class="help-h3">2.5 Jelentkezések a vezérlőpulton</h3>
      <p class="help-p">A vezérlőpult alsó részén látod az összes <strong>aktív jelentkezésedet</strong> meghirdetett túrákra: a túra nevét, dátumát, a szabad helyek számát, és a részvételi díj befizetésének állapotát. A sor végén lévő „Részletek" linkre kattintva a túra részletoldalára jutsz.</p>
    </section>

    <!-- ══════════════════════════════════════════════════
         3. TÚRANAPLÓ
    ══════════════════════════════════════════════════ -->
    <section class="help-section" id="sec-turanaplo">
      <h2 class="help-section-title">
        <span class="help-section-num">3</span>
        <span class="help-section-emoji">🥾</span>
        Túranapló
      </h2>

      <p class="help-p">A <strong>Túrák</strong> menüpont alatt éred el az egyesület jóváhagyott túráinak naplóját. Ez a teljes túratörténet: látod, kin vett részt melyik túrán, és mennyi pontot ért.</p>

      <h3 class="help-h3">3.1 Túrák listája</h3>
      <p class="help-p">A listában az összes <strong>jóváhagyott túra</strong> szerepel, a legújabbtól visszafelé. A táblázat oszlopai:</p>
      <ul class="help-ul">
        <li><strong>Kód</strong> — az adminisztrátor által adott egyedi kód.</li>
        <li><strong>Név / Ország</strong> — a túra neve és az ország zászlója.</li>
        <li><strong>Típus</strong> — gyalogos, kerékpáros, vízitúra, síelés, barlangi, munka.</li>
        <li><strong>Dátum, Napok, Km, Szint</strong> — a teljesítmény főbb adatai.</li>
        <li><strong>Résztvevők</strong> — hány tag és hány vendég vett részt.</li>
        <li><strong>Lizzardier-pont</strong> — az adott túráért kapott pontok száma.</li>
      </ul>
      <p class="help-p">A lista fölötti <strong>„Csak amin részt vettem"</strong> gombra kattintva csak a saját túráidat látod. A keresőmezővel is szűrhetsz.</p>

      <h3 class="help-h3">3.2 Túra részletei</h3>
      <p class="help-p">Bármelyik túra sorára kattintva megnyílik a részletes nézet. Megtalálod:</p>
      <ul class="help-ul">
        <li><strong>Általános adatok:</strong> ország, régió, dátum, napszám, szállás típusa, útvonal leírása.</li>
        <li><strong>Teljesítmény:</strong> túratípus, km, szintemelkedés, időtartam.</li>
        <li><strong>Pontszámok:</strong> Lizzardier-pont és MTSZ-pont, részletes számítással.</li>
        <li><strong>Résztvevők:</strong> a részt vett tagok neve és fokozata.</li>
        <li><strong>GPX-térkép:</strong> ha az adminisztrátor feltöltött útvonalat, interaktív térkép is megjelenik az oldalon.</li>
      </ul>

      <h3 class="help-h3">3.3 Túra beküldése jóváhagyásra</h3>
      <p class="help-p">Saját szervezett túrádat a lista jobb felső sarkában lévő <strong>„Túra beküldése"</strong> gombra kattintva adhatod be az adminisztrátornak jóváhagyásra.</p>
      <ol class="help-steps">
        <li>Kattints a <strong>„Túra beküldése"</strong> gombra a Túrák oldalon.</li>
        <li>Töltsd ki a kötelező adatokat: <strong>ország, régió, dátum, napszám, túratípus</strong>.</li>
        <li>Add meg a teljesítmény-adatokat: <strong>km, szintemelkedés</strong> (vagy időtartam idő-alapú típusoknál).</li>
        <li>Opcionálisan add hozzá a többi résztvevő tagot a legördülőből, és a vendégek számát.</li>
        <li>Kattints a <strong>„Túra beküldése jóváhagyásra"</strong> gombra.</li>
        <li>Az adminisztrátor megvizsgálja és jóváhagyja vagy visszautasítja a túrát. Jóváhagyás után a pontok automatikusan jóváíródnak.</li>
      </ol>
      <div class="help-callout info">
        <span class="help-callout-icon">ℹ️</span>
        <div>Az oldalon egy <strong>valós idejű MTSZ-pont előnézet</strong> is megjelenik, ahogy töltöd ki az adatokat — ez csak tájékoztató, a végleges pontszámot az adminisztrátor hagyja jóvá.</div>
      </div>
      <div class="help-callout warn">
        <span class="help-callout-icon">⚠️</span>
        <div>Az egésznapos túráknál az MTSZ-pont minimuma 20 pont/nap. Ennél kisebb értéknél a rendszer figyelmeztet, de a beküldés nem tiltott.</div>
      </div>
    </section>

    <!-- ══════════════════════════════════════════════════
         4. MEGHIRDETETT TÚRÁK
    ══════════════════════════════════════════════════ -->
    <section class="help-section" id="sec-meghirdetett">
      <h2 class="help-section-title">
        <span class="help-section-num">4</span>
        <span class="help-section-emoji">📅</span>
        Meghirdetett Túrák
      </h2>

      <p class="help-p">Az adminisztrátor által meghirdetett, előre tervezett túrákra a <strong>Túrák → Meghirdetett Túrák</strong> fülön lehet jelentkezni. Ezek a jövőbeli programok, amelyekre foglalni kell helyet.</p>

      <h3 class="help-h3">4.1 Meghirdetett túrák listája</h3>
      <p class="help-p">A listában látod az összes <strong>nyitott</strong> (jelentkezést fogadó) meghirdetett túrát. A táblázat oszlopai:</p>
      <ul class="help-ul">
        <li><strong>Túra neve</strong> — és ha már jelentkeztél, egy <span class="badge badge-active" style="font-size:11px;">Jelentkeztem</span> vagy <span class="badge badge-overdue" style="font-size:11px;">Várólistán</span> jelvény jelzi az állapotot.</li>
        <li><strong>Ország / Régió</strong> — zászlóval együtt.</li>
        <li><strong>Dátum és Napok</strong></li>
        <li><strong>Szabad helyek</strong> — pl. „3 / 12 szabad hely" (fennmaradó / maximum).</li>
        <li><strong>Státusz</strong> — <span class="badge badge-active" style="font-size:11px;">Nyitott</span> vagy <span class="badge badge-inactive" style="font-size:11px;">Lezárt</span>.</li>
      </ul>

      <h3 class="help-h3">4.2 Túra részletek</h3>
      <p class="help-p">A túra nevére kattintva megnyílik a részletes oldal. Bal oldalon megtalálod:</p>
      <ul class="help-ul">
        <li>Az ország, régió, dátum, napszám és szabad helyek áttekintését.</li>
        <li>A részvételi díjat — és ha jogosult vagy, a szintednek megfelelő kedvezményes árat.</li>
        <li>A túra leírását.</li>
        <li>A napi programtervet (ha az adminisztrátor feltöltötte): nap, típus, km, szintemelkedés, leírás.</li>
        <li>Összecsukható infoblokkok: szállás, utazás, felszerelés, szükséges tapasztalat.</li>
      </ul>
      <p class="help-p">Jobb oldalon látod a <strong>jelentkezési panelt</strong> az aktuális állapoddal és a cselekvési gombbal.</p>

      <h3 class="help-h3">4.3 Jelentkezés menete</h3>
      <ol class="help-steps">
        <li>Nyisd meg a túra részletoldalát, és kattints a <strong>„Jelentkezés"</strong> gombra (ha van szabad hely) vagy a <strong>„Várólistára feliratkozás"</strong> gombra (ha a túra betelt).</li>
        <li>A megjelenő ablakban töltsd ki a kért adatokat:
          <ul style="margin-top:6px;padding-left:18px;font-size:13.5px;">
            <li><strong>Van-e autód?</strong> (igen/nem) — ha igen, megadhatod, hány utast tudsz vinni.</li>
            <li><strong>Szobamegosztás:</strong> igen (csak azonos neművel), igen, vagy nem.</li>
            <li><strong>Megjegyzés</strong> — egyéb kérések, észrevételek.</li>
            <li>Az adminisztrátor által meghatározott <strong>egyéni kérdések</strong> (ha van ilyen).</li>
          </ul>
        </li>
        <li>Kattints a <strong>„Jelentkezés elküldése"</strong> gombra.</li>
        <li>Visszaigazoló e-mailt kapsz a jelentkezés állapotáról.</li>
      </ol>

      <p class="help-p">A jelentkezés két állapotba kerülhet:</p>
      <ul class="help-ul">
        <li><span class="badge badge-active">Megerősített</span> — van szabad hely, a részvételed foglalt. <strong>14 napon belül kell befizetni a részvételi díjat.</strong></li>
        <li><span class="badge badge-overdue">Várólistán</span> — a túra betelt. Ha valaki lemond, automatikusan értesítést kapsz, és megerősítésre kerülsz.</li>
      </ul>

      <div class="help-callout warn">
        <span class="help-callout-icon">⚠️</span>
        <div><strong>Részvételi díj határideje: 14 nap.</strong> Ha a megerősítés után 14 napon belül nem érkezik be a befizetés, az adminisztrátor törölheti a helyedet.</div>
      </div>

      <h3 class="help-h3">4.4 Lemondás</h3>
      <p class="help-p">Ha mégsem tudsz részt venni, a túra részletoldalán lemondhatsz.</p>
      <ol class="help-steps">
        <li>Nyisd meg a túra részletoldalát a vezérlőpultról vagy a meghirdetett túrák listájából.</li>
        <li>A jobb oldali panelen kattints a <strong>„Lemondás"</strong> (piros) gombra.</li>
        <li>Erősítsd meg a megerősítő párbeszédablakban.</li>
        <li>A helyedet a várólistán következő tag automatikusan megkapja (ha van várólistás).</li>
      </ol>
      <div class="help-callout tip">
        <span class="help-callout-icon">💡</span>
        <div>Lemondás után újra jelentkezhetsz ugyanarra a túrára, de már csak a fennmaradó helyek alapján (megerősített vagy várólistás).</div>
      </div>

      <h3 class="help-h3">4.5 Publikus jelentkezési oldal (vendégek)</h3>
      <p class="help-p">Az adminisztrátor által megosztott közvetlen linkkel <strong>nem tag vendégek is jelentkezhetnek</strong> meghirdetett túrákra. A vendégjelentkezés <em>jóváhagyásra vár</em> — az adminisztrátor dönt a részvételről. A publikus oldal beágyazható WordPress weboldalba is.</p>
    </section>

    <!-- ══════════════════════════════════════════════════
         5. PROFILSZERKESZTÉS
    ══════════════════════════════════════════════════ -->
    <section class="help-section" id="sec-profil">
      <h2 class="help-section-title">
        <span class="help-section-num">5</span>
        <span class="help-section-emoji">👤</span>
        Profilszerkesztés
      </h2>

      <p class="help-p">A <strong>Saját profilom</strong> menüponton érheted el az adataidat. Az oldal bal oldalán a profilkártyád, jobb oldalán a szerkesztési űrlap jelenik meg.</p>

      <h3 class="help-h3">5.1 Személyes adatok</h3>
      <p class="help-p">Az alábbi mezőket tudod szerkeszteni:</p>
      <ul class="help-ul">
        <li><strong>Vezetéknév, Keresztnév</strong> (kötelező)</li>
        <li><strong>Felhasználónév</strong> (kötelező, egyedi)</li>
        <li><strong>E-mail cím</strong> (kötelező, egyedi)</li>
        <li>Születési dátum, pólóméret, irányítószám, város, cím, telefonszám (opcionális)</li>
      </ul>
      <div class="help-callout warn">
        <span class="help-callout-icon">⚠️</span>
        <div>A <strong>felhasználónév</strong> és az <strong>e-mail cím</strong> megváltoztatása csak akkor lehetséges, ha más tagnak nincs már ilyen adata a rendszerben.</div>
      </div>

      <h3 class="help-h3">5.2 Vészhelyzeti kapcsolat</h3>
      <p class="help-p">Megadhatod egy hozzátartozód adatait, akit szükség esetén értesíteni kell: <strong>név, kapcsolat típusa</strong> (szülő, gyermek, testvér, egyéb), <strong>telefonszám</strong>.</p>

      <h3 class="help-h3">5.3 Profilkép feltöltése</h3>
      <ol class="help-steps">
        <li>A profilkártyán kattints az avatárképre (megjelenik egy kamera ikon).</li>
        <li>Válassz ki egy képfájlt a gépedről.</li>
        <li>Kattints a <strong>„Változások mentése"</strong> gombra — a kép automatikusan frissül.</li>
      </ol>
      <div class="help-callout info">
        <span class="help-callout-icon">ℹ️</span>
        <div>Csak képfájlt tölthetsz fel (jpg, png, gif, stb.), maximum <strong>2 MB</strong> méretben.</div>
      </div>

      <h3 class="help-h3">5.4 Jelszó módosítása</h3>
      <p class="help-p">A profilszerkesztési űrlapon van egy <strong>„Jelszó módosítása"</strong> szekció. Ha nem szeretnéd megváltoztatni a jelszavadat, hagyd üresen a mezőket — a meglévő jelszó megmarad.</p>
      <ol class="help-steps">
        <li>Írd be az <strong>új jelszót</strong> az első mezőbe (minimum 6 karakter).</li>
        <li>Írd be újra a megerősítő mezőbe.</li>
        <li>Mentsd el az oldalt a <strong>„Változások mentése"</strong> gombbal.</li>
      </ol>

      <h3 class="help-h3">5.5 E-mail értesítések</h3>
      <p class="help-p">A profilon kapcsoló-gombbal engedélyezheted vagy letilthatod az e-mail értesítéseket:</p>
      <ul class="help-ul">
        <li><strong>Túra hozzárendelés</strong> — kapsz értesítő e-mailt, ha az adminisztrátor egy jóváhagyott túrához hozzáad téged.</li>
      </ul>

      <h3 class="help-h3">5.6 Hozzájárulások</h3>
      <ul class="help-ul">
        <li><strong>E-mail láthatóság</strong> — beleegyezel-e, hogy az e-mail címedet látják az eseménykoordinációs levelekben (pl. tömeges, szervező e-mailek).</li>
        <li><strong>Fénykép megjelenítés</strong> — beleegyezel-e, hogy a rólad készült fotók megjelenjenek az egyesület weboldalán és közösségi média felületein.</li>
      </ul>
      <div class="help-callout tip">
        <span class="help-callout-icon">💡</span>
        <div>A hozzájárulásokat bármikor visszavonhatod, csak mentsd el a módosítást.</div>
      </div>
    </section>

    <!-- ══════════════════════════════════════════════════
         6. STATISZTIKÁK
    ══════════════════════════════════════════════════ -->
    <section class="help-section" id="sec-statisztikak">
      <h2 class="help-section-title">
        <span class="help-section-num">6</span>
        <span class="help-section-emoji">📊</span>
        Statisztikák
      </h2>

      <p class="help-p">A <strong>Statisztikák</strong> menüpont alatt két nézetben tekintheted meg az adatokat: az <strong>egyesület összesített</strong> teljesítményét és a <strong>saját személyes</strong> adataidat. A nézetek között az oldal tetején lévő gombok segítségével válthatsz.</p>

      <h3 class="help-h3">6.1 Egyesület statisztikái</h3>
      <p class="help-p">Az alapértelmezett nézet az egész klub összesített adatait mutatja:</p>
      <ul class="help-ul">
        <li><strong>Összesített kártyák:</strong> összes túra száma, összes km, összes szintemelkedés, aktív tagok száma.</li>
        <li><strong>Éves aktivitás:</strong> évenkénti túraszám (oszlopdiagram) és km (vonaldiagram).</li>
        <li><strong>Országok évente:</strong> évenként látogatott és újonnan felfedezett országok.</li>
        <li><strong>Túrák ország szerint:</strong> a legtöbbet látogatott top 10 ország.</li>
        <li><strong>Túratípus megoszlás:</strong> gyalogos, kerékpáros, vízitúra stb. arányai.</li>
        <li><strong>Havi aktivitás:</strong> melyik hónapban volt a legtöbb túra.</li>
        <li><strong>Tagok fokozat szerint:</strong> hány tag van az egyes szinteken.</li>
      </ul>

      <h3 class="help-h3">6.2 Saját statisztikáim</h3>
      <p class="help-p">A <strong>„Saját statisztikáim"</strong> gombra kattintva a személyes adataid jelennek meg: saját túraszám, km, szintemelkedés, és az általad meglátogatott országok száma. Ha még nem vettél részt jóváhagyott túrán, üres állapotjelző üzenet fogad.</p>
      <div class="help-callout info">
        <span class="help-callout-icon">ℹ️</span>
        <div>A diagramok az adatbázisban lévő jóváhagyott túrák alapján frissülnek — a jóváhagyásra váró beküldések nem szerepelnek benne.</div>
      </div>
    </section>

    <!-- ══════════════════════════════════════════════════
         7. TOPLISTA
    ══════════════════════════════════════════════════ -->
    <section class="help-section" id="sec-toplista">
      <h2 class="help-section-title">
        <span class="help-section-num">7</span>
        <span class="help-section-emoji">🏆</span>
        Toplista
      </h2>

      <p class="help-p">A <strong>Toplista</strong> oldalon három ranglistát találsz. Asztali nézetben egymás mellett, mobilon lapfüleken (Örökös / Éves / Túrabajnok) érhető el mindhárom.</p>

      <h3 class="help-h3">7.1 Örökös toplista</h3>
      <p class="help-p">Az <strong>összes valaha szerzett</strong> Lizzardier-pont alapján rangsorolja a tagokat. Csak azok szerepelnek, akik legalább <strong>3 pontot</strong> gyűjtöttek. A lista oszlopai: helyezés, szintfotó, név, fokozatjelvény, pontszám.</p>

      <h3 class="help-h3">7.2 Éves toplista</h3>
      <p class="help-p">Az <strong>aktuális évben</strong> szerzett pontok alapján rangsorolja a tagokat (csak a legjobb 20). Csak az idei dátumú jóváhagyott túrák pontjai számítanak bele.</p>

      <h3 class="help-h3">7.3 Túrabajnok</h3>
      <p class="help-p">Ez a lista az <strong>évente megítélt Túrabajnok</strong> díjat mutatja. A díjazott meghatározásának módszertana:</p>
      <ul class="help-ul">
        <li>Az adott évben szerzett pontokból indulnak ki (<em>„idei pont"</em>).</li>
        <li>Ha a tag <strong>nem volt az előző év Túrabajnoka</strong>, az előző év pontjainak 20%-a hozzáadódik (<em>„bónuszpont"</em>) — ez segíti a feltörekvő tagokat.</li>
        <li>Az előző évi bajnoknak <strong>nincs bónusz</strong> — a sikerét az idei teljesítménnyel kell megismételni.</li>
        <li>A győztes a legmagasabb korrigált pontszámú tag.</li>
      </ul>
      <div class="help-callout tip">
        <span class="help-callout-icon">💡</span>
        <div>A Túrabajnok lista évente bővül — mindig látod, ki nyerte a díjat az egyes években.</div>
      </div>

    </section>

  </main>
</div>

<!-- Vissza a tetejére gomb -->
<button id="back-to-top" title="Vissza a tetejére">↑</button>

<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<script>
(function () {
  // ── TOC aktív kiemelés ────────────────────────────────────
  var sections = document.querySelectorAll('.help-section');
  var tocLinks = document.querySelectorAll('.help-toc a');
  if (sections.length && tocLinks.length && 'IntersectionObserver' in window) {
    var obs = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          var id = entry.target.id;
          tocLinks.forEach(function (a) {
            a.classList.toggle('toc-active', a.getAttribute('href') === '#' + id);
          });
        }
      });
    }, { rootMargin: '-15% 0px -70% 0px' });
    sections.forEach(function (s) { obs.observe(s); });
  }

  // ── Vissza a tetejére gomb ────────────────────────────────
  var topBtn = document.getElementById('back-to-top');
  if (topBtn) {
    window.addEventListener('scroll', function () {
      topBtn.style.display = window.scrollY > 320 ? 'flex' : 'none';
    });
    topBtn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }
}());
</script>
</body>
</html>
