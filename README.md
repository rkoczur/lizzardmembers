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

## Fő funkciók

- **Tagságkezelés** — tagok, szerepkörök, avatar, éves tagdíj állapota a tranzakciós naplóból számolva.
- **Túrák** — megtett túrák naplózása, pontszámítás, automatikus szintlépés (Újonc … Ezredes).
- **Meghirdetett túrák** — jelentkezés, várólista, részvételi díj és tagi kedvezmény, e-mail értesítők.
- **Könyvelés** — bevétel/kiadás nyilvántartás, CSV import/export, eseményhez kötés.
- **MTSZ jelvényes minősítések** — bronz / ezüst / arany / érdemes / kiváló fokozatok nyilvántartása.
- **Kvízjáték** — madár- és hegycsúcs-felismerő játék toplistával.
- **Publikus honlap** — hírek, túranaptár, tagsági információk, kapcsolat.

## Fejlesztési szabályok

- Minden módosítás után emelni kell a verziószámot: `includes/version.php`.
- Minden kiírt értékre `e()` (htmlspecialchars), minden POST kezelő elején `verifyCsrf()`,
  minden védett oldal elején `requireAdmin()` vagy `requireUser()`.
- Kizárólag PDO prepared statement — felhasználói input soha nem kerül SQL-be interpolálva.
- Útvonalakhoz mindig a `BASE_URL` konstans használandó.
- A stílusok az `assets/css/` alatti CSS fájlokba kerülnek. Kivétel az e-mail sablonok:
  ott az inline stílus kötelező, mert a levelezőprogramok nem töltenek be külső CSS-t.

## Telepítési szabály

A fejlesztés kizárólag a lokális DEV környezetben történik. Az éles (PROD) szerverre a feltöltést
mindig kézzel végzi a felhasználó.
