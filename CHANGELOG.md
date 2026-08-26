# Changelog

A verziószám forrása: `includes/version.php`.
Major: teljesen új funkció | Minor: fő funkció módosítás vagy alfunkció hozzáadás | Patch: minden egyéb.

## [6.12.0] — 2026-08-15

### Hozzáadva
- Publikus túra részletoldal (`public/tour-detail.php`): ha a belépett tag már jelentkezett a túrára,
  a „Jelentkezés” gomb helyett a jelentkezés státusza jelenik meg (Elfogadásra vár / Befizetésre vár /
  Elfogadva / Várólista), „Jelentkezésem kezelése” linkkel. „Befizetésre vár” állapotban a bankszámla
  blokk is látszik. Új CSS: `.tour-my-app-*`.
- Új semleges jelvény: `.badge-pending` (Elfogadásra vár).

### Módosítva
- A „Tartozásaim” kártya a vezérlőpult tetejére került, a rácson kívülre (`user/index.php`).
- A „Jelentkezéseim a meghirdetett túrákra” listából kikerült a fizetendő összeg és minden piros
  kiemelés (sorháttér, mobil kártya háttér, fejléc darabszám-jelvény). Helyette egységes
  státuszjelvény: Elfogadásra vár / Befizetésre vár / Elfogadva / Várólista.
  A `$renderPaymentStatus` helyére `$renderApplicationStatus` lépett.

### Eltávolítva
- `.dash-tour-card.is-unpaid` CSS és a `$hasUnpaidTours` változó (használatlanná váltak).

## [6.11.2] — 2026-08-15

### Módosítva
- A „Tartozásaim” kártya bankszámla blokkja visszafogottabb lett: a sötét, nagy kontrasztú
  megjelenés helyett finom vörös átfedés (`--danger` alapú áttetsző háttér és bal szegély),
  mérsékelt betűméretekkel — kiemelt, de illeszkedik a kártya designjához.

## [6.11.1] — 2026-08-15

### Módosítva
- A vezérlőpult „Jelentkezéseim a meghirdetett túrákra” listájából kikerült a bankszámla blokk
  (a „Fizetendő” cella zsúfolt lett tőle).
- A „Tartozásaim” kártyán a bankszámla kiemelt megjelenést kapott: sötét háttér, arany bal szegély,
  nagyobb számlaszám. Új CSS változat: `.bank-info-strong` (`bankInfoBox('strong')`).
- A már nem használt `.bank-info-sm` változat eltávolítva.

## [6.11.0] — 2026-08-15

### Hozzáadva
- Bankszámlaszám (Leguán Osztag Természetjáró Egyesület — 16200120-18542675) megjelenítése
  minden olyan helyen, ahol befizetésre szólítjuk fel a tagokat.
- `includes/functions.php`: `BANK_ACCOUNT_NAME` / `BANK_ACCOUNT_NUMBER` konstansok,
  `bankInfoBox()` (oldalakhoz, CSS class alapú) és `bankInfoEmailHtml()` (e-mailekhez, inline stílus).
- `assets/css/style.css`: `.bank-info` blokk stílusai, `.bank-info-sm` szűk táblázatcellához.

**Oldalak:** tagi vezérlőpult „Tartozásaim” kártya és „Fizetendő” cella (`user/index.php`),
meghirdetett túra részletoldal fizetési figyelmeztetése és jelentkezési űrlapja
(`user/future-tour-detail.php`), publikus túrajelentkezés tagsági doboza (`public/tour-apply.php`),
súgó „Részvételi díj határideje” callout (`help.php`).

**E-mailek:** jelentkezés elfogadva (`actions/future-tour-accept.php`), tag jelentkezése
(`actions/future-tour-apply.php`), API-s jelentkezés (`api/submit-application.php`), vendég
jóváhagyása (`actions/future-tour-approve-guest.php`), tagsági kérelem jóváhagyása
(`actions/application-process.php`), „Hely felszabadult” értesítők
(`actions/future-tour-cancel.php`, `actions/future-tour-remove-applicant.php`), tagfelvételi
visszaigazoló (`actions/join-submit.php`), üdvözlő e-mail (`includes/welcome-email.php`).

### Módosítva
- `buildWelcomeEmailHtml()` új, opcionális `$showFee` paramétert kapott (alapértelmezés: `true`).
  A jelszó-újragenerálás (`actions/member-generate-password.php`) `false` értékkel hívja, így ott
  nem jelenik meg a tagdíjfelhívás.
