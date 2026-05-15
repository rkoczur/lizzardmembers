# assets/js/

Single file: `app.js` — plain JavaScript, no frameworks, no modules, no build step.

Included via `<script src="<?= BASE_URL ?>/assets/js/app.js">` in the footer of both header wrappers.
Keep all interactive behaviors here (flash dismissal, avatar preview, live search, etc.).
Use `document.addEventListener('DOMContentLoaded', ...)` to wrap DOM-dependent code.
