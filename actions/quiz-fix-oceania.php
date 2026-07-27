<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

verifyCsrf();

$pdo = getDb();

// Az összes madarat átnézzük (nem csak a LIKE-mintára illőket), hogy az esetleges
// eltérő írásmódot (szóköz, kis/nagybetű) is elkapjuk.
$rows = $pdo->query("SELECT id, name, continents FROM quiz_birds")->fetchAll();

$upd      = $pdo->prepare("UPDATE quiz_birds SET continents = ? WHERE id = ?");
$changed  = 0;
$leftover = [];

foreach ($rows as $row) {
    $arr = json_decode((string)$row['continents'], true);
    if (!is_array($arr)) {
        continue;
    }

    $rowChanged = false;
    $newArr = array_map(function ($v) use (&$rowChanged) {
        $norm = mb_strtolower(trim((string)$v), 'UTF-8');
        if ($norm === 'óceánia') {
            $rowChanged = true;
            return 'Ausztrália-Óceánia';
        }
        return $v;
    }, $arr);

    if ($rowChanged) {
        $upd->execute([json_encode($newArr, JSON_UNESCAPED_UNICODE), $row['id']]);
        $changed++;
    } else {
        // Gyanús, még mindig "óceánia"-t tartalmazó, de a fenti szabállyal nem egyező érték.
        foreach ($newArr as $v) {
            if (mb_stripos((string)$v, 'óceánia', 0, 'UTF-8') !== false && mb_strtolower((string)$v, 'UTF-8') !== 'ausztrália-óceánia') {
                $leftover[] = $row['name'] . ' (' . $v . ')';
                break;
            }
        }
    }
}

$msg = $changed > 0
    ? "Javítva {$changed} madár rekordban: „Óceánia” → „Ausztrália-Óceánia”."
    : 'Nem található javítandó „Óceánia” érték.';
if ($leftover) {
    $msg .= ' Figyelem, ellenőrizendő (eltérő formátum): ' . implode(', ', $leftover) . '.';
}

flash('success', $msg);
header('Location: ' . BASE_URL . '/admin/quiz-questions.php');
exit;
