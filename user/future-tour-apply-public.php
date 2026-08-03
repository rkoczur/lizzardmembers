<?php
/**
 * ELAVULT — a korábbi önálló publikus jelentkezési oldal megszűnt.
 * A túrák publikus felülete: /public/tour-detail.php (jelentkezés: /public/tour-apply.php).
 * Ez a fájl csak azért maradt meg, hogy a korábban kiküldött e-mailekben és
 * külső oldalakon szereplő régi linkek ne törjenek el.
 */
require_once __DIR__ . '/../includes/config.php';

$id = (int)($_GET['id'] ?? 0);

$target = $id > 0
    ? BASE_URL . '/public/tour-detail.php?id=' . $id
    : BASE_URL . '/public/turanyptar.php';

header('Location: ' . $target, true, 301);
exit;
