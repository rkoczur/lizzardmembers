# Changelog

A verziószám forrása: `includes/version.php`.
Major: teljesen új funkció | Minor: fő funkció módosítás vagy alfunkció hozzáadás | Patch: minden egyéb.

## [6.15.2] — 2026-09-03

### Módosítva
- A vezérlőpult „Folyamatban lévő könyvelési tételek” kártyája `.queue-mod` szekcióba került,
  „Pénzügy” címsorral és „Könyvelés” hivatkozással — ugyanaz a `.queue-head` szerkezet, mint az
  alatta lévő két szekciónál.

### Hozzáadva
- Új CSS: `.queue-mod > .queue-stat { margin-bottom: 0 }` — a szekció adja az alsó térközt,
  így nem duplázódik.

## [6.15.1] — 2026-09-03

### Javítva
- Admin vezérlőpult „Elfogadásra váró túrajelentkezések” szekciója mindig üres maradt.
  Ok: a lekérdezés csak a `future_tour_applications.status = 'pending'` sorokat vette figyelembe,
  de ezt a státuszt kizárólag a vendég jelentkezések kapják
  (`actions/future-tour-apply-guest.php`, `api/join-tour-submit.php`, `actions/join-submit.php`);
  a bejelentkezett tag jelentkezése azonnal `confirmed`/`waitlist` lesz
  (`actions/future-tour-apply.php`, `api/submit-application.php`), így soha nem jelent meg a listán.
  Az új szűrés a nyitott túrák minden, admin által még el nem fogadott jelentkezését mutatja:
  `status = 'pending'` VAGY (`status IN ('confirmed','waitlist')` ÉS `accepted_at IS NULL`).

### Módosítva
- A szekció sorai a tag nevét és e-mail címét is megjelenítik (`users` LEFT JOIN,
  `COALESCE(guest_name, ...)`), valamint új címkék: „Vendég” és „Várólista”.
- `admin/index.php` meghívja az `ensureFutureToursSchema()`-t, hogy a friss telepítéseken se
  bukjon el csendben a lekérdezés hiányzó oszlop miatt.

### Hozzáadva
- Új CSS: `.queue-tag-wait` (várólista címke).

## [6.15.0] — 2026-08-28

### Módosítva
- Admin vezérlőpult (`admin/index.php`) átalakítva: a személyes tagsági modulok („Saját tagságom”
  statisztikák és a „Szint előrehaladás” kártya) törölve — ezek csak a tagnézetben (`user/index.php`,
  `user/profile.php`) láthatók.
- A taglétszám statisztikák és a „Tagfelvételi kérelmek” kártya változatlanul a helyén maradt;
  a bennük lévő soron belüli stílusok CSS osztályokba kerültek (`.stat-value-warning`,
  `.stat-value-danger`, `.td-nowrap-sm`, `.td-muted-sm`).

### Hozzáadva
- Szerepkörhöz kötött vezérlőpult-modulok:
  - „Folyamatban lévő könyvelési tételek” számláló-kártya (kiemelt tranzakciók,
    `transactions.highlighted = 1`), link a szűrt könyvelésre — egyesületvezető / egyesületvezető-helyettes /
    pénzügyi vezető (`canManageFinances()`).
  - „Jóváhagyásra váró túrajelentések” lista (beküldött túrák, `tours.status = 'pending'`) beküldővel,
    túradátummal és „Áttekintés” gombbal — egyesületvezető / egyesületvezető-helyettes /
    szakszövetségi vezető (`canManageTours()`).
  - „Elfogadásra váró túrajelentkezések” lista (`future_tour_applications.status = 'pending'`) a túrával,
    elérhetőséggel és „Kezelés” gombbal — egyesületvezető / egyesületvezető-helyettes (`isAdmin()`).
- Új CSS: `.queue-stat`, `.queue-stat-icon`, `.queue-stat-body`, `.queue-stat-label`, `.queue-stat-hint`,
  `.queue-stat-value`, `.queue-mod`, `.queue-head`, `.queue-section-head`, `.queue-title`, `.queue-count`,
  `.queue-all`, `.queue-empty`, `.queue-list`, `.queue-row`, `.queue-row-main`, `.queue-row-title`,
  `.queue-row-meta`, `.queue-tag`.

## [6.14.0] — 2026-08-26

### Hozzáadva
- „Elfogadásra váró jelentkezések" blokk a Meghirdetett Túrák lista fölött (`admin/future-tours.php`):
  azok a helyet kapott tagok, akiknek a jelentkezése még nem került elfogadásra (`accepted_at IS NULL`),
  a nem lezárt és nem törölt túrákról. Soronként jelentkező, túra (link a jelentkezők oldalára),
  jelentkezés dátuma, fizetési állapot és „Elfogadás" gomb.
- Új CSS: `.pending-accept-card`, `.pending-accept-row`, `.pending-accept-name`, `.pending-accept-tour`,
  `.pending-accept-meta`, `.pending-accept-when`, `.pending-accept-pay`, `.pending-accept-note`.

### Módosítva
- `actions/future-tour-accept.php`: opcionális `back=list` paraméterrel a túralistára tér vissza,
  egyébként változatlanul a jelentkezők oldalára.

## [6.13.0] — 2026-08-26

### Hozzáadva
- Fizetési emlékeztető a meghirdetett túrák jelentkezőinek (`actions/future-tour-payment-reminder.php`).
  A jelentkezők oldalán (`admin/future-tour-applicants.php`) soronként küldhető emlékeztető a még nem
  fizető, helyet kapott jelentkezőknek, illetve a kártya fejlécében egy gombbal az összesnek.
  Tagoknak és vendégeknek egyaránt megy, a levélben a tagi kedvezménnyel számolt fizetendő díjjal
  és a bankszámla blokkal.
- Ha a túrán van várólistás jelentkező, az emlékeztető 1 hetes fizetési határidőt közöl (a túra
  kezdeténél nem későbbi dátummal), és jelzi, hogy utána a helyet a várólistán következő jelentkező
  kapja meg. Az admin oldalon erről figyelmeztető sáv is megjelenik.
- Új adatbázis oszlop: `future_tour_applications.payment_reminder_at` — az utolsó emlékeztető ideje,
  a jelentkező sorában megjelenítve.
- Új CSS: `.btn-remind`, `.btn-remind-bulk`, `.remind-note`, `.remind-cell`, `.remind-hint`.

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
