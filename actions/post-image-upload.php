<?php
/**
 * TinyMCE képfeltöltés a bejegyzés-szerkesztőhöz (hírek + élményblog).
 * A szerkesztő FormData-ban küldi a fájlt ('file') és a CSRF tokent.
 * Válasz: JSON { "location": "<url>" } sikeres feltöltéskor, különben { "error": "..." }.
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isLoggedIn() || !canCreatePosts()) {
    http_response_code(403);
    echo json_encode(['error' => 'Nincs jogosultságod a feltöltéshez.']);
    exit;
}

// CSRF — a TinyMCE handler a tokent a FormData-ban küldi
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Érvénytelen munkamenet (CSRF).']);
    exit;
}

if (empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Nem érkezett fájl.']);
    exit;
}

$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
$mime    = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['file']['tmp_name']);
if (!isset($allowed[$mime])) {
    http_response_code(400);
    echo json_encode(['error' => 'Csak JPG, PNG, WebP vagy GIF tölthető fel.']);
    exit;
}
if ((int)$_FILES['file']['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'A kép legfeljebb 5 MB lehet.']);
    exit;
}

$dir = __DIR__ . '/../assets/uploads/posts/';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$filename = 'postimg_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
if (!move_uploaded_file($_FILES['file']['tmp_name'], $dir . $filename)) {
    http_response_code(500);
    echo json_encode(['error' => 'A fájl mentése nem sikerült.']);
    exit;
}

echo json_encode(['location' => BASE_URL . '/assets/uploads/posts/' . $filename]);
exit;
