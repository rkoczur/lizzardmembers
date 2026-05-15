# admin/ — Admin Pages

All pages here require `requireAdmin()`. UI language is Hungarian. Each page sets `$pageTitle` and `$activePage` then wraps content between `admin-header.php` and `admin-footer.php`.

## Pages

| File | `$activePage` | Description |
|---|---|---|
| `index.php` | `dashboard` | Stats grid (total/active/new/overdue) + recent members table |
| `members.php` | `members` | Searchable/filterable member list |
| `member-detail.php` | `members` | View + edit a single member; POST goes to `../actions/member-update.php` |
| `member-add.php` | `members` | Form to create a new member; POST goes to `../actions/member-add.php` |
| `profile.php` | `profile` | Admin's own profile edit; POST goes to `../actions/profile-update.php` |
| `tours.php` | `tours` | Tour list |
| `tour-detail.php` | `tours` | View + edit a single tour |
| `tour-add.php` | `tours` | Form to create a new tour |

## Patterns
- Flash messages rendered via `getFlash('error')` / `getFlash('success')` at the top of page body
- All user-supplied data echoed through `e()`
- CSRF token included in every form: `<input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">`
- Level display: `<span class="level-badge <?= getLevelClass($level) ?>"><?= getLevelLabel($level) ?></span>`
- Active/inactive badge: `<span class="badge <?= $active ? 'badge-active' : 'badge-inactive' ?>">`
- Links always built with `BASE_URL` constant, never hardcoded paths

## Sidebar nav keys
`dashboard`, `members`, `tours`, `profile` — match the `$activePage` value to highlight the correct sidebar link.
