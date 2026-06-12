<?php
/**
 * Globális közösségi megosztási kép (OpenGraph) mentése (admin → Beállítások).
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/app-settings-schema.php';
requireAdmin();
verifyCsrf();

$pdo = getDb();
ensureAppSettingsSchema($pdo);

if (!is_dir(SOCIAL_DIR)) mkdir(SOCIAL_DIR, 0755, true);
$current = getSetting($pdo, 'social_default_image', '');

// Törlés
if (!empty($_POST['delete_social_image'])) {
    if ($current !== '' && file_exists(SOCIAL_DIR . $current)) @unlink(SOCIAL_DIR . $current);
    saveSetting($pdo, 'social_default_image', '');
    $current = '';
}

// Új kép feltöltése
if (!empty($_FILES['social_image']['tmp_name']) && $_FILES['social_image']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $size = (int)($_FILES['social_image']['size'] ?? 0);
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['social_image']['tmp_name']);
    if (isset($allowed[$mime]) && $size <= 5 * 1024 * 1024) {
        $newFile = 'social_default_' . time() . '.' . $allowed[$mime];
        if (move_uploaded_file($_FILES['social_image']['tmp_name'], SOCIAL_DIR . $newFile)) {
            if ($current !== '' && file_exists(SOCIAL_DIR . $current)) @unlink(SOCIAL_DIR . $current);
            saveSetting($pdo, 'social_default_image', $newFile);
        } else {
            flash('error', 'A kép feltöltése nem sikerült.');
            header('Location: ' . BASE_URL . '/admin/settings.php');
            exit;
        }
    } else {
        flash('error', 'Csak JPG, PNG vagy WebP kép tölthető fel, maximum 5 MB.');
        header('Location: ' . BASE_URL . '/admin/settings.php');
        exit;
    }
}

flash('success', 'Közösségi megosztási kép mentve.');
header('Location: ' . BASE_URL . '/admin/settings.php');
exit;
