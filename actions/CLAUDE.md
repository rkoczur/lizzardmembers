# actions/ — POST Form Handlers

These files handle form submissions only. Rules that apply to **every** file here:

1. **No HTML output** — always end with `header('Location: ...')` + `exit`
2. **Always call `verifyCsrf()` before any other logic**
3. **Always call `requireAdmin()` or `requireUser()`** depending on who may submit
4. Validate and sanitize all `$_POST` inputs before use
5. On error: `flash('error', 'Hungarian message')` then redirect back
6. On success: `flash('success', 'Hungarian message')` then redirect forward

## Files

| File | Auth | Purpose |
|---|---|---|
| `member-add.php` | admin | Creates a new member record |
| `member-update.php` | admin | Updates member fields + optional avatar upload + optional password change |
| `profile-update.php` | user | Lets the logged-in user update their own profile |
| `tour-add.php` | admin | Creates a new tour |
| `tour-update.php` | admin | Updates an existing tour |
| `tour-delete.php` | admin | Deletes a tour |

## Avatar upload pattern (used in member-update, profile-update)
- Validate MIME type with `finfo_file()` against allowlist: `image/jpeg`, `image/png`, `image/gif`, `image/webp`
- Enforce 2 MB size limit
- Generate filename: `avatar_{userId}_{time()}.{ext}`
- Delete old avatar file after successful upload
- Store filename (not full path) in DB `profile_picture` column

## Standard file header
```php
<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin(); // or requireUser()
verifyCsrf();
```
