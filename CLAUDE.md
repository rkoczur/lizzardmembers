# Basic rules
Always update version number after every modification as stated in `/includes/version.php`
Do not modify any hardcoded text if it is "Lizzard Outdoor" or "Leguán Osztag" or "Leguán Osztag Természetjáró Egyesület"

## Deployment rule — FONTOS
**Soha ne tölts fel semmit automatikusan a PROD szerverre (wh09.rackhost.hu FTP).**
Minden módosítást csak a lokális DEV környezetben (`c:\xampp\htdocs\lizzardmembers`) végezz el.
A PROD-ra feltöltést mindig a felhasználó végzi manuálisan, vagy explicit kérésre kerülhet sor.

# LizzardMembers — Root

PHP/MySQL membership management app running on XAMPP at `/lizzardmembers`.
UI language: **Hungarian**. No JS frameworks — plain PHP, PDO, plain JS, custom CSS.
All UI elements must be in **Hungarian**!

## Entry points
- `index.php` — redirects logged-in users to their dashboard, guests to `login.php`
- `login.php` — username or email + password, session-based auth
- `logout.php` — destroys session, redirects to `login.php`
- `setup.php` — first-run wizard: detects missing DB, creates schema + tables + first admin
- `config.ini` — DB connection and app settings (host, port, user, password, dbname, base_url, app_name)

## Directory map
| Folder | Purpose |
|---|---|
| `includes/` | Shared PHP: config, DB helpers, auth guards, utility functions, layout wrappers |
| `admin/` | Admin-only pages (dashboard, members, tours, profile) |
| `user/` | Member-facing pages (dashboard, profile) |
| `actions/` | POST-only form handlers — no HTML output, always redirect after processing |
| `assets/` | CSS, JS, images, uploaded avatars |
| `wp-plugins/` | Wordpress plugins |

## Constants (defined in `includes/config.php`)
- `BASE_URL` — e.g. `/lizzardmembers` — always use this for links, never hardcode paths
- `APP_NAME` — display name from config.ini
- `AVATAR_DIR` — absolute filesystem path to `assets/uploads/avatars/`
- `AVATAR_URL` — URL prefix for avatar images
- `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME`

## Security rules — follow on every change
- Always call `e()` (htmlspecialchars) on every echoed value
- Always call `verifyCsrf()` at the top of every POST handler
- Use `requireAdmin()` or `requireUser()` at the top of every protected page
- Use PDO prepared statements — never interpolate user input into SQL
- Avatar uploads: validate MIME via `finfo`, enforce 2 MB limit, generate safe filename
- Avatar megjelenítés: mindig `getAvatarThumbUrl()` (280 px-es négyzetes példány, GD-vel, `avatars/thumbs/`),
  ne az eredeti — a nagy arányú böngészős kicsinyítés moirét okoz. Csere/törlés: `deleteAvatarFiles()`.

## Member levels (computed — never set manually)
Points = sum of `tours.points` for all tours the member is assigned to via `tour_members`.
Level = derived from points via `getLevelFromPoints()`. Both are recalculated by `recalcUserStats(PDO $pdo)` after any tour change.

| Level | Hungarian label | Min points |
|---|---|---|
| 1 | Újonc | 0 |
| 2 | Közlegény | 3 |
| 3 | Tizedes | 25 |
| 4 | Őrmester | 50 |
| 5 | Hadnagy | 100 |
| 6 | Százados | 170 |
| 7 | Őrnagy | 250 |
| 8 | Alezredes | 330 |
| 9 | Ezredes | 500 |

## Egyedi részvételi díj (`fee_override`)
`future_tour_applications.fee_override DECIMAL(10,2) NULL` — egy jelentkezőre kézzel megadott díj.
Ha ki van töltve, az a mérvadó, és a tagi kedvezmény MÁR NEM vonatkozik rá.

Mindig a `getApplicationFee(?float $tourFee, int $discount, $override): float` helperrel (functions.php)
számold — soha ne szorozz kézzel a kedvezménnyel. Beállítás: `actions/future-tour-fee-override.php`
(csak `requireAdmin()`), UI az `admin/future-tour-applicants.php` „Fizetendő díj" cellájában
(`<details class="fee-edit">`, üres mező vagy „Alapértelmezett" gomb → NULL).

Felhasználók: `admin/future-tour-applicants.php`, `admin/future-tours.php`, `includes/member-account.php`,
`user/future-tour-detail.php`, `actions/future-tour-accept.php`, `actions/future-tour-payment-reminder.php`.

## Tour participation fee discount (`getTourFeeDiscount`)
Defined in `includes/functions.php`. Returns the percentage discount based on member level:

| Member level | Discount |
|---|---|
| 1–4 | 0% |
| 5–6 | 5% |
| 7–8 | 10% |
| 9 | 15% |

Usage: `$discount = getTourFeeDiscount((int)$user['level']);`
Effective fee: `$tour['participation_fee'] * (1 - $discount / 100)`
- Guests (no `user_id`) always pay the full fee — pass `0` as level or skip the call.
- Query pattern: always include `COALESCE(u.level, 1) AS user_level` when joining users to applications.

## MTSZ jelvényes minősítések
Stored in the `mtsz_qualifications` table (`includes/mtsz-schema.php` → `ensureMtszSchema()`).
One row per earned grade; `UNIQUE (user_id, grade)` — a grade can only be earned once per member.
Fields: `grade`, `reg_number` (nyilvántartási szám), `awarded_on` (dátum).

Grade keys → labels via `mtszGradeLabels()` in `includes/functions.php`:
`bronz`, `ezust`, `arany` (…jelvényes természetjáró), `erdemes`, `kivalo` (…természetjáró).
Helpers: `mtszGradeLabel()`, `mtszGradeShortLabel()`, `mtszGradeClass()`, `mtszGradeImageUrl()`,
`getMtszQualifications($pdo, $userId)`.

Badge images: `assets/img/mtsz/{grade}.png` — 240×212 transparent PNGs, always rendered via
`mtszGradeImageUrl()` (returns `null` if the file is missing, so callers fall back to the text badge).
Originals live in `mtsz-kituzo/` at the repo root (450 px, one is a JPG) — not referenced by the app.

- **Only** `canManageMtsz()` (admin / helyettes / szakszövetségi vezető) may record, edit or delete a qualification —
  never the member themselves. Enforced in `actions/mtsz-qualification-save.php` and `-delete.php`.
- Admin UI: `admin/member-detail.php` (`#mtsz` card, same column/width as the „Tag adatai" card).
- Member UI: `user/profile.php` — read-only list in the left sidebar card (next to the Lizzardier rank);
  `user/index.php` — `.dash-card-mtsz` tile in the left dashboard column, only when the member has a grade.
- CSS: `.mtsz-badge`, `.mtsz-badge-img`, `.mtsz-side-list`, `.mtsz-dash-list` in `assets/css/style.css`.
  `.mtsz-side-item img` must override `.profile-avatar-card img` (circle crop) — keep the selector specific.

## Adatmódosítás jóváhagyással (`includes/profile-change.php`)
Tag (nem vezető, `!isAnyLeader()`) a `user/profile.php`-n nem írhatja közvetlenül a `zipcode`, `city`, `address`,
`phone` mezőket: az `actions/profile-update.php` `saveProfileChangeRequest()`-tel függő `profile_change_requests` sort
ment (tagonként max. 1 `pending`), a `users` sorban a régi érték marad. A lakcím kötelező (üres nem menthető).
A profil űrlap a függő kért értékeket mutatja (`$contact`), különben újramentéskor törlődne a kérelem.
Jóváhagyás / elutasítás: `admin/members.php?tab=changes` (`includes/profile-change-list.php`) →
`actions/profile-change-process.php` (`requireAdmin()`, audit napló). Sidebar „Tagok” jelvény ezeket is számolja. CSS: `.pchg-*`.

## Egyéni folyószámla
`includes/member-account.php` — a tag előírt és ténylegesen befizetett **részvételi díjai**.
A tagdíj NEM része a folyószámlának (a tagdíj állapotát a `getMemberStatus()` / `last_payment` adja).
A tranzakció és a tag kapcsolata a `transactions.partner` mező alapján („Vezetéknév Keresztnév”),
ugyanaz a minta, mint a `recalcMembershipPayments()`-nél. A túra-kapcsolat forrása a tranzakció
`event_type = 'future_tour'` + `event_id` mezője.

- `getMemberAccount($pdo, $userId)` → `rows` (előírás–befizetés párok), `unassigned`
  (előírás nélküli idei bevételek — **nem** számítanak az egyenlegbe), `charged`, `paid`, `balance`.
  Az `unassigned`-ből kimarad a már rögzített túrához kötött befizetés (`event_type = 'tour'`):
  az ilyen túra sosem volt meghirdetett túra, nincs hozzá jelentkezés és előírás sem.
  `balance < 0` → tartozás, `balance > 0` → túlfizetés.
- `getAllMemberAccounts($pdo)` — minden aktív tag összegzése (a Könyvelés „Folyószámlák” füléhez).
- `syncTourPaymentsFromTransactions($pdo, ?$tourId)` — a túrához rendelt bevételek alapján beállítja a
  `future_tour_applications.paid_at` mezőt (soha nem törli), és visszaadja jelentkezőnként a befizetett
  összeget. Meghívva: `admin/future-tour-applicants.php`, `admin/bookkeeping.php` (accounts fül),
  `actions/transaction-save.php`, `-update.php`, `-import.php`.
- Előírás: kizárólag a `confirmed` jelentkezések részvételi díja, `getApplicationFee()`-vel számolva
  (egyedi díj, vagy a `getTourFeeDiscount()` kedvezmény).
  A `Tagdíj` kategóriájú tranzakciók teljesen kimaradnak (sem előírás, sem befizetés, sem `unassigned`).
- `getAllMemberAccounts()` csak az „Aktív” tagsági státuszúakat listázza
  (`getMemberStatus()` === `'active'` ÉS `users.active = 1`). A státusz a `users.last_payment`-ből jön,
  ezért a lista előtt `recalcMembershipPayments()` kell. A tag saját adatlapján a kártya mindig látszik.
- Megjelenítés: `includes/member-account-card.php` (közös kártya) — `admin/member-detail.php`
  (`#folyoszamla`, `canManageFinances()`), `admin/bookkeeping.php?tab=accounts`.
  A `user/index.php` és a `user/profile.php` NEM ezt a kártyát használja: ott a közös
  `includes/member-debts-card.php` részleg jelenik meg, amely csak a rendezetlen tételeket
  (`$row['diff'] <= -1`) listázza, kiegészítve a tagdíj elmaradással
  (`getMembershipDebt()`, `MEMBERSHIP_FEE = 5000`) — a tagdíj továbbra sem része a folyószámlának.
  CSS: `.acct-*` és `.pay-*` osztályok az `assets/css/style.css`-ben.

## Page boilerplate pattern
```php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin(); // or requireUser()

$pageTitle  = 'Page title in Hungarian';
$activePage = 'sidebar-key';
include __DIR__ . '/../includes/admin-header.php'; // or user-header.php
// ... HTML ...
include __DIR__ . '/../includes/admin-footer.php'; // or user-footer.php
```

# Guest application

1. At the tour details, make a checkbox: need membership.
2. In the public application form (both the API and the native) there should be a new logic: If the tour needs membership, then after clicking on the "jelentkezés" button, display the user registration form - same as the join.php - and tell the user, that this tour is only for members, and here you can register yourself as a member. If the tour is not required membership, then the logic stays the same as now!
3. A tagfelvételi űrlapon is meg kell jelenniük a túra-specifikus mezőknek és az egyedi kérdéseknek.
   Natív oldal: `public/tour-apply.php` — mindhárom űrlap (tag / vendég / tagfelvétel) a közös
   `includes/tour-apply-fields.php` részleget használja. WP plugin: `j_custom_field_N` mezőnevek,
   amelyeket a JS `custom_field_N`-re nevez át küldés előtt.
4. Státusz: minden új jelentkezés (tag, vendég, tagfelvételes) azonnal `confirmed`/`waitlist` a férőhely szerint
   (`getNewApplicationStatus($pdo, $tourId)`) — `pending` csak régi sorokon maradhat. A vendég a fő listában
   „Vendég” jelvénnyel szerepel, és a `future-tour-accept.php` (`requireAdminOrVezeto()`) fogadja el (ez küldi a levelet).
   Tagfelvételes sor (`user_id` NULL + `member_application_id`): `isApplicantNotYetMember()` → az elfogadás tiltva
   (inaktív gomb, „Még nem tag”). Tagfelvétel jóváhagyásakor (`actions/application-process.php`) a sor a tagé lesz,
   státusza marad, túra-levél NEM megy. Elutasításkor a kapcsolt jelentkezés `cancelled`.
## Meghirdetett túra publikus adatlapja (`public/tour-detail.php`, `includes/future-tour-content.php`)
Kártyamentes, egyoszlopos elrendezés (`.ftd-*` CSS), egyetlen kártya a jelentkezés (`.ftd-apply`). A tagi nézet
(`user/future-tour-detail.php`) még a régi rácsot használja — később ugyanerre a szerkezetre kerül.
- Lizzardier pont a borító jobb alsó sarkában: `.ftd-crest` (eredeti `ures_small.png` címer, türkiz szám), nem az adatsávban.
- `future_tours.cover_logo` (light/dark/none) + `cover_logo_pos` (left/right, `.ftd-hero-logo--right`) → `assets/img/logo_collision_y_small.png` (világos) / `_b_small` (sötét).
- `fee_note` (üres → `tourFeeNoteDefault()`), `experience_tags` és `equipment` soronként (`tourTextLines()` olvas,
  `tourContentFromPost()` ment; a szerkesztő `equipment_items[]` / `experience_tags[]` tömböt küld).
- „Fontos!” szöveg csak központilag (admin → Beállítások, `tour_important_text`), a szervező nem módosíthatja;
  `tourImportantHtml()` a részvételi feltételek / magatartási iránytű kifejezést linkeli.
- Státusz: `draft` (Piszkozat) / `open` / `closed` / `cancelled`. Piszkozat csak az admin listában és vezetőknek a publikus
  adatlapon (előnézet, `.ftd-draft`) látszik; publikus/tagi lekérdezésnél mindig zárd ki (`status NOT IN ('cancelled','draft')`
  vagy `status = 'open'`), értesítő (announce) nem küldhető róla.
- Találkozó (csak `num_days = 1`): `meeting_place`, `meeting_time` (TIME) → `.ftd-meet` doboz a jelentkezés kártya mellett
  (`.ftd-apply-row`), Google Térkép keresés link. Szerkesztőben `#meeting-group`, JS rejti több napnál.
- `accommodation` = „Szállás és utazás” (a `travel` oszlop már nem használt, mentéskor NULL).
- Napok: `future_tour_days.title`; mentés mindig `saveFutureTourDays()` (törlés + újra beszúrás).
  Térkép a naphoz: `future_tour_gpx_files.day_number` (NULL = egész túra). A napsor `day_orig[]` mezője alapján a
  térképek követik a napjukat; törölt nap térképe NULL lesz. Feltöltés: `storeFutureTourGpx()`, `saveFutureTourDayGpx()`.
- Szerkesztő napsor: `includes/future-tour-day-row.php` (+ `<template id="day-row-template">` az új sorhoz).

## Meghirdetett túra szervezői (`includes/future-tour-organizers.php`)
`future_tour_organizers` (future_tour_id, user_id, sort_order) — ha egy túrához nincs sor, a `future_tours.created_by` a szervező.
`getFutureTourOrganizers($pdo, $tourId)` mindig ezt a visszaesést adja; `saveFutureTourOrganizers($pdo, $tourId, $ids)` felülír
(üres lista → létrehozó). Mentés: `actions/future-tour-add.php`, `-update.php` (`organizer_ids[]`), UI: `admin/future-tour-detail.php`
„Szervezők” (`initMemberPicker('org-picker', 'organizer_ids[]')`). Megjelenítés: `includes/tour-organizers.php` (`$organizers`, `.tour-org-card` „Szervező(k)” kártya a jobb oszlopban, a jelentkezés alatt; név, profilkép, e-mail, telefon) —
`user/future-tour-detail.php`, `public/tour-detail.php`. CSS: `.to-*`.
Jelentkezési értesítő: `getTourApplicationNotifyRecipients($pdo, $tourId, $roles = ['admin'])` — aktív adminok + aktív szervezők,
duplikáció nélkül. Hívják: `actions/future-tour-apply.php`, `-apply-guest.php`, `api/submit-application.php`,
`api/join-tour-submit.php`, `actions/join-submit.php` (túrához kötött tagfelvételnél, `['admin','vezeto']`).

## Naptár-meghívó (`includes/calendar-invite.php`)
`getTourCalendarEvent($pdo, $tourId, $absBaseUrl)` → egész napos esemény (`start_date` … +`num_days`, kizárólagos vég),
null ha törölt / nincs dátum. `calendarGoogleUrl()`, `calendarOutlookUrl($ev, 'outlook.live.com'|'outlook.office.com')`,
`calendarIcs()` (RFC 5545, 75 oktetes sortörés), `calendarButtonsEmailHtml()` — e-mail blokk (inline stílus, mint a többi levél).
.ics letöltés: `public/tour-calendar.php?id=` (publikus). Használja: `actions/future-tour-accept.php`, `future-tour-approve-guest.php`.

## Telekocsi-szervező (`includes/carpool.php`)
Túránként egy `carpools` sor egyedi 32 hex karakteres `token`-nel (`createCarpool()`); link: `carpoolUrl()` →
`public/telekocsi.php?t=<token>`. Létrehozás: `actions/carpool-create.php` (`requireAdminOrVezeto()`),
gomb/link az `admin/future-tour-applicants.php` `#telekocsi` sávjában.
- Azonosítás bejelentkezés nélkül: `findCarpoolApplicationByEmail()` (tag `users.email` vagy `guest_email`,
  `status != 'cancelled'`), az eredmény a `$_SESSION['carpool'][$token]`-ben. Bejelentkezett tagot automatikusan azonosít.
- `carpool_drivers` (jelentkezésenként max. 1, `seats` 1–`CARPOOL_MAX_SEATS`, `phone`/`contact_extra` közül legalább egy,
  `departure` kötelező), `carpool_passengers` (jelentkezésenként max. 1 hely). Sofőr nem lehet utas.
- Minden publikus művelet: `actions/carpool-action.php` (`op`: identify, forget, driver_save, driver_remove, book, unbook).
- Részlegek: `includes/carpool-identify.php`, `-me.php`, `-car.php`. Profilkép: `carpoolAvatarUrl()` (null → monogram, `.cp-photo`). JS: `assets/js/carpool.js`. CSS: `.cp-*`.
- Vezető (admin/helyettes/túravezető) azonosítás nélkül is látja az autókat („vezetői nézet”).
- Értesítő (`includes/carpool-notify.php`): `carpool_watchers` (jelentkezésenként max. 1), `op=watch` kapcsolja.
  `notifyCarpoolWatchers($pdo, $carpool, 'new_driver'|'seat_freed', $driverId, $actorAppId)` — BCC levél,
  az okozó kimarad, csak ha az autóban van szabad hely. Hívják: `carpool-action.php`, `future-tour-cancel.php`.
- Link kiküldése: `actions/carpool-mail.php` — egy e-mail, a `confirmed` jelentkezők BCC-ben
  (`SmtpMailer::send(..., $bcc)`), Reply-To a küldő; ideje: `carpools.mailed_at`.

## Ranglétra (`includes/rank-history.php`)
A rang megszerzése nincs tárolva — `getRankHistory($pdo, $userId)` számolja: a tag túráit
(`tour_members`) időrendben (`tour_date IS NULL, tour_date, id`) összesíti, és ahol a pontösszeg átlépi
a `getLevelMinPoints()` küszöbét, az a túra hozta a rangot. Egy túra több szintet is hozhat.
Az 1. szint (Újonc) mindig az első elem, túra nélkül. A legmagasabb elem egyezik a `users.level`-lel.
Megjelenítés: `includes/rank-history-card.php` (`$ranks`, `$rankPoints`, `$rankTourUrl`) —
`user/ranks.php` (menü: „Ranglétra”, `$activePage = 'ranks'`, `.rank-page` szélességkorlát) és
`admin/member-detail.php` (`#rangletra`, az MTSZ kártya alatt). CSS: `.rank-*`.
`getTourRankAchievers($pdo, $tourId)` — a túrán rangot szerzett tagok; megjelenítés: `admin/tour-view.php`
(szem ikon az `admin/tours.php` listában). Ez az oldal a `user/tour-detail.php`-t tölti be `$adminView = true`
 mellett — a tagi túranézetet ezért mindig úgy módosítsd, hogy admin keretben is működjön. CSS: `.achiever-*`, `.btn-view`.
`getRankUpLevel($points, $level, $tourPoints)` — a meghirdetett túrával elért új szint (vagy null);
`admin/future-tour-applicants.php` „Szintlépés” jelvénye (`.rankup-badge`) használja.

## Önálló telekocsi-szervező (`telekocsi/`)
Külön app, az alaprendszer kódját (includes/, assets/) NEM használja — ne is kösd hozzá. URL: `BASE_URL/telekocsi`.
- DB: `telekocsi/config.ini` [database], ennek hiányában a gyökér `config.ini`. Táblák: `tk_boards` (8 karakteres `code`),
  `tk_people` (táblánként egyedi e-mail, titkos `secret` a sütihez, `is_owner`), `tk_drivers`, `tk_passengers`.
- `index.php` (nyitóoldal / `?k=KOD`, `.htaccess` rewrite: `/telekocsi/KOD`), `action.php` (minden POST, `op`:
  create, join, forget, driver_save, driver_remove, book, unbook). Logika: `lib/core.php`, `lib/boards.php`, `lib/rides.php`.
  Nézetek: `views/`. CSS/JS: `telekocsi/assets/style.css`, `app.js` (`.tk-*` osztályok, `data-tk-*` attribútumok).
- E-mail: `lib/mailer.php` (`TkMailer`, `tkMailConfig()`, `tkSendMail()`), `lib/notify.php` (`tkNotify($pdo, $board, $event, $car, $actor, $toOwner)`,
  események: driver_new, driver_removed, passenger_booked, passenger_left). Címzett: szervező (`is_owner`) + passenger_* eseménynél
  az autó sofőrje; az okozó kimarad, duplikáció nincs. SMTP: `config.ini [smtp]` → közös `settings` smtp_*. `[mail] log_only = 1` → `data/mail.log`.
- Azonosítás: süti `tk_<KOD>` = a résztvevő `secret`-je (180 nap). CSRF: `tkCsrf()` / `tkVerifyCsrf()`.
