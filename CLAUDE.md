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
  A `user/index.php` NEM ezt a kártyát használja: ott csak a rendezetlen tételek
  (`$row['diff'] <= -1`) jelennek meg a „Tartozásaim" `.debt-table` listában.
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