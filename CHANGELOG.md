# Changelog

A verziószám forrása: `includes/version.php`.
Major: teljesen új funkció | Minor: fő funkció módosítás vagy alfunkció hozzáadás | Patch: minden egyéb.

## [7.4.0] — 2026-09-12

### Módosítva
- A folyószámla „Nem beazonosított befizetések" blokkjából **kimaradnak a már rögzített túrához
  kötött befizetések** (`transactions.event_type = 'tour'` + `event_id`). Az ilyen túra sosem volt
  meghirdetett túra, ezért nincs hozzá jelentkezés és előírás sem — nem hiányzó összerendelésről,
  hanem lezárt tételről van szó. A meghirdetett túrához (`future_tour`) rendelt, de jelentkezés
  nélküli befizetések továbbra is megjelennek.

## [7.3.0] — 2026-09-12

### Hozzáadva
- **Egyedi részvételi díj jelentkezőnként** — az adminisztrátor a túra jelentkezőinél tagonként
  felülírhatja a fizetendő díjat. Ha van egyedi díj, arra a tagi kedvezmény már nem vonatkozik.
  - Új oszlop: `future_tour_applications.fee_override DECIMAL(10,2) NULL` (migráció:
    `includes/future-tours-schema.php`).
  - Új helper: `getApplicationFee(?float $tourFee, int $discount, $override): float`
    (`includes/functions.php`) — minden felület ezen keresztül számolja a fizetendő díjat.
  - Új kezelő: `actions/future-tour-fee-override.php` (`requireAdmin()` + `verifyCsrf()`).
    Üres mező vagy „Alapértelmezett" gomb → törli a felülírást.
  - UI: `admin/future-tour-applicants.php` „Fizetendő díj" cellája — összecsukható szerkesztő
    (`<details class="fee-edit">`), egyedi díjnál „egyedi díj" jelzés az áthúzott eredeti ár mellett.
  - Átvezetve: folyószámla (`includes/member-account.php`), nyitott tartozások
    (`admin/future-tours.php`), tag saját túraoldala (`user/future-tour-detail.php`),
    elfogadó e-mail (`actions/future-tour-accept.php`), fizetési emlékeztető
    (`actions/future-tour-payment-reminder.php`).

### Módosítva
- A tag vezérlőpultján a folyószámla kártya helyett újra a **„Tartozásaim"** lista jelenik meg:
  csak a rendezetlen tételek (hiányzó összeggel), a nullás tételek nem. Részben fizetett tételnél
  látszik a fizetendő és a már befizetett összeg is; több tétel esetén összesítő sor.

### Új CSS
- `.fee-edit`, `.fee-edit-form`, `.fee-edit-input`, `.fee-edit-row`, `.fee-edit-btn`,
  `.fee-edit-save`, `.fee-edit-clear`, `.fee-edit-hint`, `.badge-custom-fee`.

## [7.2.0] — 2026-09-12

### Módosítva
- A folyószámla **kizárólag a túra-részvételi díjakat** tartja nyilván; a tagdíj teljesen kikerült
  belőle (sem előírásként, sem befizetésként, sem „nem beazonosított” tételként nem jelenik meg).
  A tagdíj állapotát továbbra is a tagsági státusz mutatja (`getMemberStatus()` / `users.last_payment`).
  A `MEMBERSHIP_FEE_HUF` konstans megszűnt.
- A Könyvelés „Folyószámlák" fülének listájában **csak az „Aktív" tagsági státuszú tagok** szerepelnek
  (korábban a „Tagdíj elmaradás" státuszúak is). A tag saját adatlapján a kártya továbbra is látszik.
- Igazított szövegek: a kártya fejlécében „Részvételi díjak" jelzés, a Könyvelés fülön
  „Tagok folyószámlája – részvételi díjak", üres állapotban „Nincs díjas túra-jelentkezés…".
- Törölt elárvult CSS: `.acct-kind-membership`.

## [7.1.0] — 2026-09-12

### Módosítva
- A folyószámlán a **tagdíj-előírás csak akkor keletkezik, ha a tag tagsági státusza nem „Inaktív"**
  (`getMemberStatus()` ≠ `inactive`). A több éve nem fizető tagoknál így nem jelenik meg
  5 000 Ft tagdíj-tartozás; tartozás csak a „Tagdíj elmaradás" státuszúaknál (és a hibás összeget
  utaló aktív tagoknál) keletkezik.
- A Könyvelés „Folyószámlák" fülének listájából **kimaradnak az „Inaktív" státuszú tagok**, és így az
  összesített tartozás / túlfizetés értékekbe sem számítanak bele. A tag saját adatlapján
  (`admin/member-detail.php`) a folyószámla kártya továbbra is megjelenik.
- `admin/bookkeeping.php` (accounts fül): a lista előtt lefut a `recalcMembershipPayments()`, hogy a
  szűréshez használt tagsági státusz a tranzakciós naplóval szinkronban legyen.

## [7.0.0] — 2026-09-12

### Hozzáadva
- **Egyéni folyószámla** — tagonként látszik, mennyit kellett volna fizetnie (idei tagdíj +
  megerősített túra-jelentkezések részvételi díja, tagi kedvezménnyel) és mennyit fizetett be
  valójában. Az egyenleg nullától eltérő értéke jelzi az alul- vagy túlfizetést.
  - `includes/member-account.php` — `getMemberAccount()`, `getAllMemberAccounts()`,
    `syncTourPaymentsFromTransactions()`, `MEMBERSHIP_FEE_HUF`.
  - `includes/member-account-card.php` — közös kártya-megjelenítés (admin és tagi felület).
  - `admin/member-detail.php` — új „Folyószámla” kártya (`#folyoszamla`), csak pénzügyi jogosultsággal.
  - `admin/bookkeeping.php` — új „Folyószámlák” fül: minden aktív tag egyenlege, összesített
    tartozás / túlfizetés / nem beazonosított befizetés.
  - `user/index.php` — a „Tartozásaim” kártya helyére a „Folyószámlám” kártya került, amely az
    előírás melletti tényleges befizetést és az egyenleget is mutatja.
- **Tranzakcióból származó fizetési státusz** — ha egy bevételi tranzakció egy adott túrához van
  rendelve (`event_type = 'future_tour'`, `event_id`) és a partnere az adott jelentkező
  (tagnál a teljes név, vendégnél a megadott név), a jelentkező `paid_at` mezője automatikusan
  beáll. A `admin/future-tour-applicants.php` „Fizetés” oszlopa ilyenkor a befizetett összeget és
  a fizetendőtől való eltérést („Hiányzik” / „Túlfizetés”) mutatja, kézi átállítás nélkül.
  A szinkron a tranzakció mentésekor, módosításakor és importálásakor is lefut.

### Megjegyzés
- A tranzakció és a tag összekapcsolása a `transactions.partner` mező alapján történik
  (partner = „Vezetéknév Keresztnév”), a meglévő `recalcMembershipPayments()` mintájára.
- Az olyan bevételek, amelyekhez nincs előírás (pl. túrához nem rendelt részvételi díj), külön
  „Nem beazonosított befizetések” blokkban jelennek meg, és **nem** számítanak bele az egyenlegbe.
  A hozzárendelés a tranzakció „Esemény” mezőjével végezhető el.
- Az éves tagdíj előírása csak az aktuális naptári évre keletkezik, és csak aktív tagnál.

### Új CSS
- `.pay-cell`, `.pay-tx-badge`, `.pay-tx-note`, `.pay-amount`, `.pay-diff`, `.pay-diff-under`,
  `.pay-diff-over`, `.acct-*` osztályok az `assets/css/style.css` végén.

## [6.15.3] — 2026-09-08

### Javítva
- A meghirdetett túrák jelentkezői között a vendég jelentkezőknél nem jelent meg a
  „Jelentkezés elfogadása” gomb, így nem kaphattak elfogadó (visszaigazoló) e-mailt.
  Ok: `admin/future-tour-applicants.php` az „Elfogadás” oszlopot `$app['user_id']`-hez kötötte,
  az `actions/future-tour-accept.php` pedig `JOIN users` + `user_id IS NOT NULL` szűréssel
  kizárta a vendégeket.
- `actions/future-tour-accept.php`: `LEFT JOIN users`, a vendég neve/e-mail címe a `guest_*`
  mezőkből jön, az e-mail napló `user_id`-je vendégnél `NULL`, a „Túra részletei” gomb
  vendégnél a nyilvános `public/tour-detail.php` oldalra mutat. Vendég mindig a teljes
  részvételi díjat fizeti (nincs tagi kedvezmény).
- `admin/future-tours.php` „Elfogadásra váró jelentkezések” kártyája a vendégeket is listázza
  (`LEFT JOIN users` + `COALESCE(guest_name/guest_email)`), „Vendég” jelzéssel.

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
