# assets/uploads/avatars/

Stores user profile pictures. Files are written here by `actions/member-update.php` and `actions/profile-update.php`.

Filename pattern: `avatar_{userId}_{timestamp}.{ext}`

Do not write to this directory manually. Always go through the upload handler which validates MIME type, enforces 2 MB limit, and deletes the old file before writing the new one.
