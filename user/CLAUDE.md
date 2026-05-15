# user/ — Member-Facing Pages

All pages here require `requireUser()`. Uses `user-header.php` / `user-footer.php` wrappers (simpler sidebar than admin layout). UI language is Hungarian.

## Pages

| File | Description |
|---|---|
| `index.php` | Member dashboard — shows points, level badge, progress bar toward next level, and upcoming/joined tours |
| `profile.php` | Member's own profile edit — POST goes to `../actions/profile-update.php` |

## Points & level progress display
Progress toward the next level is calculated from the `points` column:
- Level 1 → 2: 200 pts threshold
- Level 2 → 3: 500 pts threshold
- Level 3 → 4: 1000 pts threshold
- Level 5 (Platina): max level, show 100% bar

## Constraints
- Members can only view and edit their **own** data — use `getCurrentUserId()` and always filter queries by it
- Members cannot change their own `role`, `level` (manual), `points`, `active`, or `member_since` — those are admin-only fields
- Avatar upload follows same rules as admin actions (MIME check, 2 MB limit)
