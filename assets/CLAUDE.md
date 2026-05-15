# assets/ — Static Files

| Subfolder | Contents |
|---|---|
| `css/style.css` | Single stylesheet for the entire app — all admin and user styles |
| `js/app.js` | Single JS file — no frameworks, no build step |
| `img/default-avatar.svg` | Fallback avatar shown when no profile picture is set |
| `uploads/avatars/` | User-uploaded profile pictures — **not served directly in HTML, always via `getAvatarUrl()`** |

## CSS conventions
- CSS custom properties (`--primary`, `--border`, `--warning`, etc.) defined at `:root`
- Layout classes: `app-wrapper`, `sidebar`, `main-content`, `page-body`
- Component classes: `card`, `card-header`, `stats-grid`, `stat-card`, `table-wrap`
- Badge classes: `badge`, `badge-active`, `badge-inactive`
- Level badge classes: `level-badge`, `level-starter`, `level-bronze`, `level-silver`, `level-gold`, `level-platinum`
- Button classes: `btn`, `btn-primary`, `btn-ghost`, `btn-sm`, `btn-danger`

## JS conventions
- No modules, no bundler — plain `<script src="...app.js">` in footer
- Use `app.js` for shared interactive behaviors (flash auto-dismiss, avatar preview, search filtering)

## Uploads security
- `uploads/avatars/` should be web-accessible (serves images) but `.htaccess` must prevent PHP execution inside it
- Filenames are sanitized by the action handlers before storage — never trust `$_FILES['name']` directly
