<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tours-schema.php';
requireAdmin();
verifyCsrf();

$back = BASE_URL . '/admin/country-add.php';

$code   = strtoupper(trim($_POST['code']   ?? ''));
$nameHu = trim($_POST['name_hu']           ?? '');
$sort   = max(0, (int)($_POST['sort_order'] ?? 0));
$active = isset($_POST['active']) ? 1 : 0;

if ($code === '' || $nameHu === '') {
    flash('error', 'Az országkód és a magyar elnevezés megadása kötelező.');
    $_SESSION['form_old'] = $_POST;
    header('Location: ' . $back);
    exit;
}
if (!preg_match('/^[A-Z]{2,10}$/', $code)) {
    flash('error', 'Az országkód csak 2–10 nagybetűt tartalmazhat (pl. HU, AT, SK).');
    $_SESSION['form_old'] = $_POST;
    header('Location: ' . $back);
    exit;
}

$pdo = getDb();
ensureToursSchema($pdo);

$dup = $pdo->prepare("SELECT id FROM countries WHERE code = ?");
$dup->execute([$code]);
if ($dup->fetch()) {
    flash('error', '"' . $code . '" kód már létezik az adatbázisban.');
    $_SESSION['form_old'] = $_POST;
    header('Location: ' . $back);
    exit;
}

$flagFilename = null;
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
        $_SESSION['form_old'] = $_POST;
        header('Location: ' . $back);
        exit;
    }
    if ($_FILES['flag_file']['size'] > 512000) {
        flash('error', 'A zászlókép nem lehet nagyobb 500 KB-nál.');
        $_SESSION['form_old'] = $_POST;
        header('Location: ' . $back);
        exit;
    }
    if (!is_dir(FLAG_DIR)) {
        mkdir(FLAG_DIR, 0755, true);
    }
    $flagFilename = 'flag_' . $code . '.' . $ext;
    move_uploaded_file($_FILES['flag_file']['tmp_name'], FLAG_DIR . $flagFilename);
}

$pdo->prepare("INSERT INTO countries (code, name_hu, flag_filename, sort_order, active) VALUES (?, ?, ?, ?, ?)")
    ->execute([$code, $nameHu, $flagFilename, $sort, $active]);

flash('success', '"' . $nameHu . '" (' . $code . ') sikeresen hozzáadva.');
header('Location: ' . BASE_URL . '/admin/settings.php');
exit;
