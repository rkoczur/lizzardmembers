<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/quiz-schema.php';
requireAdmin();

verifyCsrf();

$pdo  = getDb();
$type = (string)($_POST['type'] ?? '');
$id   = (int)($_POST['id'] ?? 0);

if (!in_array($type, ['bird', 'mountain'], true) || $id <= 0) {
    flash('error', 'Érvénytelen kérdés.');
    header('Location: ' . BASE_URL . '/admin/quiz-questions.php');
    exit;
}

$redirectTo = BASE_URL . '/admin/quiz-question-edit.php?type=' . $type . '&id=' . $id;
$table      = $type === 'bird' ? 'quiz_birds' : 'quiz_mountains';
$imageDir   = $type === 'bird'
    ? __DIR__ . '/../kviz/images/'
    : __DIR__ . '/../kviz/images/mountains/';
$imagePrefix = $type === 'bird' ? 'images/' : 'images/mountains/';

$stmt = $pdo->prepare("SELECT id, name, image FROM {$table} WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();
if (!$item) {
    flash('error', 'A kérdés nem található.');
    header('Location: ' . BASE_URL . '/admin/quiz-questions.php');
    exit;
}

$name = trim((string)($_POST['name'] ?? ''));
if ($name === '') {
    flash('error', 'A név megadása kötelező.');
    header('Location: ' . $redirectTo);
    exit;
}

/** Vesszővel elválasztott extra értékek + jelölőnégyzetek összefésülése, duplikátum nélkül. */
function quizMergeMultiValues(array $checked, string $extra): array
{
    $extraValues = array_filter(array_map('trim', explode(',', $extra)), fn($v) => $v !== '');
    $all = array_merge(array_map('trim', $checked), $extraValues);
    return array_values(array_unique(array_filter($all, fn($v) => $v !== '')));
}

if ($type === 'bird') {
    $wingspan = $_POST['wingspan_cm'] ?? '';
    if (!is_numeric($wingspan) || (int)$wingspan <= 0) {
        flash('error', 'Érvénytelen fesztáv.');
        header('Location: ' . $redirectTo);
        exit;
    }
    $wingspanCm = (int)$wingspan;

    $colors = quizMergeMultiValues((array)($_POST['colors'] ?? []), (string)($_POST['colors_extra'] ?? ''));
    if (!$colors) {
        flash('error', 'Legalább egy szín megadása kötelező.');
        header('Location: ' . $redirectTo);
        exit;
    }

    $continents = quizMergeMultiValues((array)($_POST['continents'] ?? []), (string)($_POST['continents_extra'] ?? ''));
    if (!$continents) {
        flash('error', 'Legalább egy kontinens megadása kötelező.');
        header('Location: ' . $redirectTo);
        exit;
    }

    $latinName = quizNullIfEmpty($_POST['latin_name'] ?? null);
    $diet      = quizNullIfEmpty($_POST['diet'] ?? null);
    $funFact   = quizNullIfEmpty($_POST['fun_fact'] ?? null);
} else {
    $elevation = $_POST['elevation_m'] ?? '';
    if (!is_numeric($elevation) || (int)$elevation <= 0) {
        flash('error', 'Érvénytelen magasság.');
        header('Location: ' . $redirectTo);
        exit;
    }
    $elevationM = (int)$elevation;

    $country = trim((string)($_POST['country'] ?? ''));
    if ($country === '') {
        flash('error', 'Az ország megadása kötelező.');
        header('Location: ' . $redirectTo);
        exit;
    }

    $mountainRange = quizNullIfEmpty($_POST['mountain_range'] ?? null);
    $funFact       = quizNullIfEmpty($_POST['fun_fact'] ?? null);
}

// Kép csere (opcionális)
$newImage = null;
if (!empty($_FILES['image']['name'])) {
    $file = $_FILES['image'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        flash('error', 'A kép feltöltése sikertelen.');
        header('Location: ' . $redirectTo);
        exit;
    }

    $allowed  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowed, true)) {
        flash('error', 'Érvénytelen képtípus. Megengedett: JPG, PNG, GIF, WEBP.');
        header('Location: ' . $redirectTo);
        exit;
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        flash('error', 'A képnek 2 MB-nál kisebbnek kell lennie.');
        header('Location: ' . $redirectTo);
        exit;
    }

    $ext = imageMimeToExt($mimeType);
    if ($ext === null) {
        flash('error', 'Érvénytelen képtípus. Megengedett: JPG, PNG, GIF, WEBP.');
        header('Location: ' . $redirectTo);
        exit;
    }

    $filename = 'quiz_' . $type . '_' . $id . '_' . time() . '.' . $ext;

    if (!is_dir($imageDir)) {
        mkdir($imageDir, 0755, true);
    }
    if (!move_uploaded_file($file['tmp_name'], $imageDir . $filename)) {
        flash('error', 'A kép feltöltése sikertelen. Ellenőrizze a mappa engedélyeit.');
        header('Location: ' . $redirectTo);
        exit;
    }

    $newImage = $imagePrefix . $filename;
}

try {
    if ($type === 'bird') {
        $params = [
            ':name'       => $name,
            ':ws'         => $wingspanCm,
            ':colors'     => json_encode($colors, JSON_UNESCAPED_UNICODE),
            ':continents' => json_encode($continents, JSON_UNESCAPED_UNICODE),
            ':latin'      => $latinName,
            ':diet'       => $diet,
            ':fact'       => $funFact,
            ':id'         => $id,
        ];
        $sql = "UPDATE quiz_birds SET name = :name, wingspan_cm = :ws, colors = :colors,
                continents = :continents, latin_name = :latin, diet = :diet, fun_fact = :fact"
             . ($newImage !== null ? ", image = :image" : "") . " WHERE id = :id";
    } else {
        $params = [
            ':name'    => $name,
            ':el'      => $elevationM,
            ':country' => $country,
            ':range'   => $mountainRange,
            ':fact'    => $funFact,
            ':id'      => $id,
        ];
        $sql = "UPDATE quiz_mountains SET name = :name, elevation_m = :el, country = :country,
                mountain_range = :range, fun_fact = :fact"
             . ($newImage !== null ? ", image = :image" : "") . " WHERE id = :id";
    }
    if ($newImage !== null) {
        $params[':image'] = $newImage;
    }
    $upd = $pdo->prepare($sql);
    $upd->execute($params);
} catch (PDOException $e) {
    if ($newImage !== null && is_file($imageDir . basename($newImage))) {
        unlink($imageDir . basename($newImage));
    }
    if ($e->getCode() === '23000') {
        flash('error', 'Már létezik másik kérdés ezzel a névvel.');
    } else {
        flash('error', 'Hiba történt a mentés közben.');
    }
    header('Location: ' . $redirectTo);
    exit;
}

// Régi kép törlése, ha lecseréltük
if ($newImage !== null && $item['image']) {
    $oldPath = __DIR__ . '/../kviz/' . $item['image'];
    if (is_file($oldPath)) {
        unlink($oldPath);
    }
}

flash('success', 'A kérdés adatai elmentve.');
header('Location: ' . $redirectTo);
exit;
