# assets/uploads/

Runtime upload storage. Contents are user-generated and not committed to version control.

- `avatars/` — profile pictures uploaded by admin or members via action handlers

Never reference filenames from this folder directly in PHP. Always use `getAvatarUrl($filename)` from `includes/functions.php`, which handles missing files and path construction via `AVATAR_DIR` / `AVATAR_URL` constants.
