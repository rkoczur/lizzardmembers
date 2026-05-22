# Basic rules
Always update version number after every modification as stated in `/includes/version.php`

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
