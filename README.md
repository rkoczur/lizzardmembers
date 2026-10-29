# LizzardMembers

A **Leguán Osztag Természetjáró Egyesület** tagságkezelő és túraszervező rendszere.
PHP / MySQL alkalmazás, XAMPP alatt fut a `/lizzardmembers` útvonalon.
Nincs JS keretrendszer: sima PHP, PDO, natív JS és saját CSS. A felhasználói felület nyelve **magyar**.

## Telepítés

1. A projekt a webszerver gyökere alá kerül (DEV: `c:\xampp\htdocs\lizzardmembers`).
2. `config.ini` — adatbázis-kapcsolat és alapbeállítások (`host`, `port`, `user`, `password`,
   `dbname`, `base_url`, `app_name`).
3. `setup.php` — első indítás varázsló: hiányzó adatbázis észlelése, séma és táblák létrehozása,
   első adminisztrátor felvétele.
4. PHP `gd` kiterjesztés (php.ini: `extension=gd`) — a profilképek 280 px-es kicsinyített példányához.
   Nélküle az eredeti kép jelenik meg (a böngésző kicsinyítése moirét okozhat).

## Belépési pontok

| Fájl | Szerep |
|---|---|
| `index.php` | Belépett felhasználót a saját vezérlőpultjára, vendéget a `login.php`-ra irányít |
| `login.php` | Felhasználónév vagy e-mail + jelszó, session alapú azonosítás |
| `logout.php` | Session megszüntetése |
| `setup.php` | Első indítás varázsló |
| `join.php` | Publikus belépési (tagfelvételi) kérelem |
| `help.php` | Felhasználói útmutató |

## Könyvtárszerkezet

| Könyvtár | Tartalom |
|---|---|
| `includes/` | Közös PHP: konfiguráció, DB, auth, segédfüggvények, sablonok, e-mail sablonok |
| `admin/` | Adminisztrátori oldalak |
| `user/` | Tagi oldalak |
| `public/` | Publikus (bejelentkezés nélküli) oldalak |
| `actions/` | POST kezelők — nincs HTML kimenet, mindig átirányítással zárnak |
| `api/` | JSON végpontok (külső űrlapok, jelentkezés, toplista) |
| `assets/` | CSS, JS, képek, feltöltött avatarok |
| `finance/` | Könyvelési import minták |
| `kviz/` | Kvízjáték forrásadatok |
| `wp-plugins/` | WordPress bővítmények |
| `telekocsi/` | Önálló telekocsi-szervező (külön app, az alaprendszert nem használja) |

## Fő funkciók

- **Tagságkezelés** — tagok, szerepkörök, avatar, éves tagdíj állapota a tranzakciós naplóból számolva.
- **Adatmódosítás jóváhagyással** — ha egy tag a profilján módosítja a lakcímét vagy telefonszámát,
  az csak admin jóváhagyás után lép életbe (Tagok → „Adatmódosítások” fül). A lakcím nem törölhető.
- **Túrák** — megtett túrák naplózása, pontszámítás, automatikus szintlépés (Újonc … Ezredes).
- **Meghirdetett túrák** — jelentkezés, várólista, részvételi díj és tagi kedvezmény, e-mail értesítők,
  fizetési emlékeztető a még nem fizető jelentkezőknek. Ha egy tag lemondja a jelentkezését, az adminok
  (admin, helyettes) és a túra létrehozója e-mail értesítőt kapnak. Az elfogadó e-mailben egy kattintással
  naptárba menthető a túra (Google Naptár, Outlook.com). Az adatlap (tagi és publikus) jobb oszlopában, a jelentkezés
  alatti kártyán látszik a szervező neve, profilképe, e-mail címe és telefonszáma; több szervező is beállítható, alapértelmezetten a túra létrehozója.
  A szervezők az adminokkal együtt e-mailt kapnak minden új jelentkezésről.
  A vendég jelentkező is azonnal a jelentkezők listájába kerül („Vendég” jelvénnyel), és a szervező egy gombbal
  fogadja el. Tagsághoz kötött túránál az elfogadás a tagfelvétel jóváhagyásáig zárolva van.
  A publikus adatlap magazinszerű: borító logóval, jelentkezés-kártya (egynapos túránál mellette a találkozó helye, ideje és térkép link), szervező, élménycímkék, galéria,
  felszerelés, központi „Fontos!” szöveg (Beállítások) és részletes program naponkénti térképpel.
  A túra „Piszkozat” státusszal is menthető: ilyenkor publikusan nem jelenik meg, csak a vezetők látják előnézetként.
- **Telekocsi-szervező** — a meghirdetett túra jelentkezői oldalán egy gombbal egyedi link készül,
  ahol a résztvevők (bejelentkezés nélkül, a jelentkezéskor megadott e-mail címükkel azonosítva)
  sofőrként helyet kínálnak, vagy utasként helyet foglalnak egy autóban. A link egy gombbal
  e-mailben is kiküldhető az összes visszaigazolt résztvevőnek (egy levél, mindenki BCC-ben).
  A résztvevők értesítőt kérhetnek, ha új sofőr regisztrál vagy hely szabadul fel.
  A tagok profilképe (ha van) megjelenik a sofőr- és utasüléseken, egyébként monogram.
- **Önálló telekocsi-szervező** (`/telekocsi`) — bárki létrehozhat egy szervezőt név + e-mail címmel.
  A rendszer egyedi kódot generál (pl. `ABCD-2345`), ez az oldal tetején és az URL-ben is szerepel
  (`/telekocsi/ABCD2345`). A résztvevők név + e-mail címmel csatlakoznak, majd sofőrként helyet kínálnak
  vagy utasként helyet foglalnak. Saját design, saját `tk_*` táblák; az alaprendszer kódját nem használja.
  E-mail értesítő: a szervező minden sofőr/utas változásról, a sofőr az autójába érkező (és onnan távozó)
  utasokról levelet kap. SMTP: `telekocsi/config.ini [smtp]`, vagy a közös `settings` tábla `smtp_*` értékei.
- **Könyvelés** — bevétel/kiadás nyilvántartás, CSV import/export, eseményhez kötés.
- **Egyéni folyószámla** — tagonként az előírt és a ténylegesen befizetett túra-részvételi díj,
  valamint az egyenleg (a tagdíj nem része). A túrához rendelt befizetés automatikusan
  „Fizetett”-re állítja a jelentkezőt, és megmutatja a fizetendőtől való eltérést.
- **Tartozásaim** — a tag vezérlőpultján és profilján a rendezetlen részvételi díjak, valamint a
  tagdíj elmaradás (ha a tagsági státusz nem „Aktív”).
- **Ranglétra** — a tag minden elért Lizzardier rangjánál látja a felvarrót, és hogy melyik túrával
  (név, helyszín, dátum) szerezte meg. A rangot a túrák időrendi pontösszesítéséből számoljuk.
  Az admin a tag adatlapján látja ugyanezt, a túra megtekintésekor pedig azt, ki szerzett rangot azon a túrán. A meghirdetett túrák
  jelentkezőinél előre jelzi, ki lép szintet a túrával, és milyen rangot ér el.
- **MTSZ jelvényes minősítések** — bronz / ezüst / arany / érdemes / kiváló fokozatok nyilvántartása.
- **Kvízjáték** — madár- és hegycsúcs-felismerő játék toplistával.
- **Publikus honlap** — hírek, túranaptár, tagsági információk, kapcsolat.

## Fejlesztési szabályok

- Minden módosítás után emelni kell a verziószámot: `includes/version.php`.
- Minden kiírt értékre `e()` (htmlspecialchars), minden POST kezelő elején `verifyCsrf()`,
  minden védett oldal elején `requireAdmin()` vagy `requireUser()`.
- Kizárólag PDO prepared statement — felhasználói input soha nem kerül SQL-be interpolálva.
- Útvonalakhoz mindig a `BASE_URL` konstans használandó.
- Átirányítás a POST kezelők után: ha az elem megmarad, vissza ugyanarra az oldalra (fül / szűrő is);
  törlés után a szülő listára. Az indító URL-t a `back` rejtett mező viheti, a kezelő a `postBackUrl()`-lel olvassa.
- A stílusok az `assets/css/` alatti CSS fájlokba kerülnek. Kivétel az e-mail sablonok:
  ott az inline stílus kötelező, mert a levelezőprogramok nem töltenek be külső CSS-t.

## Telepítési szabály

A fejlesztés kizárólag a lokális DEV környezetben történik. Az éles (PROD) szerverre a feltöltést
mindig kézzel végzi a felhasználó.
