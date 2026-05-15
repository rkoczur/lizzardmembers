<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tours-schema.php';
requireAdmin();
verifyCsrf();

$id = (int)($_POST['id'] ?? 0);
if (!$id) {
    header('Location: ' . BASE_URL . '/admin/settings.php');
    exit;
}

$back = BASE_URL . '/admin/country-detail.php?id=' . $id;

$nameHu = trim($_POST['name_hu']           ?? '');
$sort   = max(0, (int)($_POST['sort_order'] ?? 0));
$active = isset($_POST['active']) ? 1 : 0;

if ($nameHu === '') {
    flash('error', 'A magyar elnevezés megadása kötelező.');
    header('Location: ' . $back);
    exit;
}

$pdo = getDb();
ensureToursSchema($pdo);

$stmt = $pdo->prepare("SELECT * FROM countries WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$country = $stmt->fetch();
if (!$country) {
    header('Location: ' . BASE_URL . '/admin/settings.php');
    exit;
}

$flagFilename = $country['flag_filename'];

// Zászló törlése jelölőnégyzet
if (isset($_POST['remove_flag']) && $flagFilename) {
    $oldPath = FLAG_DIR . $flagFilename;
    if (file_exists($oldPath)) @unlink($oldPath);
    $flagFilename = null;
}

// Új zászló feltöltése
if (!empty($_FILES['flag_file']['tmp_name']) && $_FILES['flag_file']['error'] === UPLOAD_ERR_OK) {
    $mime = mime_content_type($_FILES['flag_file']['tmp_name']);
    $ext  = match($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default      => null,
    };
    if (!$ext) {
        flash('error', 'Zászló képnek csak JPG, PNG vagy WebP formátum engedélyezett.');
        header('Location: ' . $back);
        exit;
    }
    if ($_FILES['flag_file']['size'] > 512000) {
        flash('error', 'A zászlókép nem lehet nagyobb 500 KB-nál.');
        header('Location: ' . $back);
        exit;
    }
    if (!is_dir(FLAG_DIR)) {
        mkdir(FLAG_DIR, 0755, true);
    }
    // Régi zászló törlése ha más kiterjesztéssel volt
    if ($flagFilename && file_exists(FLAG_DIR . $flagFilename)) {
        @unlink(FLAG_DIR . $flagFilename);
    }
    $flagFilename = 'flag_' . $country['code'] . '.' . $ext;
    move_uploaded_file($_FILES['flag_file']['tmp_name'], FLAG_DIR . $flagFilename);
}

$pdo->prepare("UPDATE countries SET name_hu = ?, flag_filename = ?, sort_order = ?, active = ? WHERE id = ?")
    ->execute([$nameHu, $flagFilename, $sort, $active, $id]);

flash('success', '"' . $nameHu . '" (' . $country['code'] . ') sikeresen mentve.');
header('Location: ' . $back);
exit;
