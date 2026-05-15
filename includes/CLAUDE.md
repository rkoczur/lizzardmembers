# includes/ — Shared PHP Library

All files here are `require_once`'d by pages and action handlers. Never output HTML directly from these files (except the header/footer wrappers).

## Files

### config.php
Parses `config.ini` and defines all global constants (`BASE_URL`, `APP_NAME`, `AVATAR_DIR`, `AVATAR_URL`, `DB_*`). Must be the first include in every PHP file.

### db.php
PDO helpers:
- `getDb(): PDO` — returns singleton PDO connection (throws on failure)
- `databaseExists(): bool`
- `canConnectToServer(): bool`

### auth.php
Session-based auth guards and helpers:
- `requireAdmin()` — redirects non-admins to user dashboard
- `requireUser()` — redirects guests to login
- `requireLogin()` — base redirect to login
- `isLoggedIn(): bool`
- `isAdmin(): bool`
- `getCurrentUserId(): int`
- `setUserSession(array $user): void` — sets `user_id`, `user_role`, `user_name`, `username` in `$_SESSION`

### functions.php
Utility functions:
- `e(string $value): string` — `htmlspecialchars` wrapper, **use on every echoed variable**
- `formatDate(?string $date): string` — formats as `d.m.Y`, returns `—` for null/invalid
- `getLevelLabel(int $level): string` — Hungarian level name (Kezdő…Platina)
- `getLevelClass(int $level): string` — CSS class (`level-starter`…`level-platinum`)
- `getAvatarUrl(?string $filename): string` — returns avatar URL or default SVG
- `flash(string $key, string $message): void` — stores one-time session message
- `getFlash(string $key): ?string` — retrieves and removes flash message
- `csrfToken(): string` — generates/returns session CSRF token
- `verifyCsrf(): void` — validates `$_POST['csrf_token']`, dies with 403 on mismatch

### tours-schema.php
SQL schema definitions for the tours table — used by `setup.php`.

### admin-header.php / admin-footer.php
Full HTML page wrapper for admin pages. Requires `$pageTitle` and `$activePage` to be set before including. Sidebar nav keys: `dashboard`, `members`, `tours`, `profile`.

### user-header.php / user-footer.php
Simplified HTML page wrapper for member-facing pages.

## Adding a new helper function
Add it to `functions.php`. Keep functions stateless and side-effect-free unless they explicitly manipulate `$_SESSION` (like `flash`/`getFlash`).
