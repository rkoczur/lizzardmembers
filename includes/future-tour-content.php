<?php
/**
 * Meghirdetett túra tartalmi segédfüggvényei (szerkesztő mentése és a publikus adatlap).
 * Napok, naphoz kötött GPX térképek, felszerelés lista, élménycímkék, díj megjegyzés.
 */

const TOUR_FEE_NOTE_DEFAULT = 'A részvételi díj a jelentkezéstől számított 14 napon belül fizetendő. '
    . 'Elkerülendő a későbbi kellemetlenségeket, amennyiben ez nem történik meg, a helyet automatikusan felszabadítjuk.';

const TOUR_COVER_LOGOS = ['light' => 'Világos', 'dark' => 'Sötét', 'none' => 'Nincs'];

/** Szöveg → nem üres sorok listája; a sor eleji felsorolásjel (-, *, •) lekerül. */
function tourTextLines(?string $text): array
{
    $lines = array_map(fn($l) => trim(preg_replace('/^\s*[-*•]\s*/u', '', $l)), explode("\n", (string)$text));
    return array_values(array_filter($lines, fn($l) => $l !== ''));
}

/** Lista → tárolt szöveg (soronként egy elem), üresen null. */
function tourLinesToText(array $items, int $maxLen = 255): ?string
{
    $clean = [];
    foreach ($items as $item) {
        $item = mb_substr(trim((string)$item), 0, $maxLen);
        if ($item !== '' && !in_array(mb_strtolower($item), array_map('mb_strtolower', $clean), true)) $clean[] = $item;
    }
    return $clean ? implode("\n", $clean) : null;
}

/** A túra díj megjegyzése: egyedi, vagy a központi alapszöveg. */
function tourFeeNoteDefault(PDO $pdo): string
{
    return function_exists('getSetting') ? getSetting($pdo, 'tour_fee_note_default', TOUR_FEE_NOTE_DEFAULT) : TOUR_FEE_NOTE_DEFAULT;
}

/** A szerkesztő új tartalmi mezői a POST-ból (add és update közös). */
function tourContentFromPost(array $post): array
{
    $logo = $post['cover_logo'] ?? 'light';
    return [
        'cover_logo'      => array_key_exists($logo, TOUR_COVER_LOGOS) ? $logo : 'light',
        'fee_note'        => trim($post['fee_note'] ?? '') ?: null,
        'equipment'       => tourLinesToText((array)($post['equipment_items'] ?? [])),
        'experience_tags' => tourLinesToText((array)($post['experience_tags'] ?? []), 40),
    ];
}

/** Az összes túrán használt élménycímke (a szerkesztő ajánlásához), ábécérendben. */
function getUsedExperienceTags(PDO $pdo): array
{
    $tags = [];
    foreach ($pdo->query("SELECT experience_tags FROM future_tours WHERE experience_tags IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $text) {
        foreach (tourTextLines($text) as $tag) $tags[mb_strtolower($tag)] = $tag;
    }
    natcasesort($tags);
    return array_values($tags);
}

/**
 * Napok mentése (törlés + újra beszúrás), majd a naphoz kötött GPX térképek követik a napjukat:
 * a `day_orig[]` a sor eredeti nap-száma (új sornál 0). Törölt nap térképe az egész túrához kerül.
 */
function saveFutureTourDays(PDO $pdo, int $tourId, array $post): void
{
    $pdo->prepare("DELETE FROM future_tour_days WHERE future_tour_id = ?")->execute([$tourId]);

    $ins = $pdo->prepare("INSERT INTO future_tour_days (future_tour_id, day_number, title, tour_type, km, elevation, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $map = [];
    foreach ($post['day_number'] ?? [] as $i => $dayNum) {
        $dayNum = max(1, (int)$dayNum);
        $ins->execute([
            $tourId,
            $dayNum,
            mb_substr(trim($post['day_title'][$i] ?? ''), 0, 255) ?: null,
            trim($post['day_tour_type'][$i] ?? '') ?: null,
            ($post['day_km'][$i] ?? '') !== '' ? (float)$post['day_km'][$i] : null,
            ($post['day_elevation'][$i] ?? '') !== '' ? (int)$post['day_elevation'][$i] : null,
            trim($post['day_description'][$i] ?? '') ?: null,
        ]);
        $orig = (int)($post['day_orig'][$i] ?? 0);
        if ($orig > 0) $map[$orig] = $dayNum;
    }

    // Térképek átszámozása: CASE-szel egy lépésben, hogy a cserék ne írják felül egymást
    $args = [];
    foreach ($map as $old => $new) array_push($args, $old, $new);
    $case = $map ? 'CASE day_number ' . str_repeat('WHEN ? THEN ? ', count($map)) . 'ELSE NULL END' : 'NULL';
    $pdo->prepare("UPDATE future_tour_gpx_files SET day_number = $case WHERE future_tour_id = ? AND day_number IS NOT NULL")
        ->execute(array_merge($args, [$tourId]));
}

/** Egy feltöltött GPX ellenőrzése és mentése; siker esetén true. */
function storeFutureTourGpx(PDO $pdo, int $tourId, string $tmp, string $origName, int $size, ?int $dayNumber, string $suffix): bool
{
    $allowedMimes = ['text/xml', 'application/xml', 'application/gpx+xml', 'text/plain', 'application/octet-stream'];
    $ext  = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if ($ext !== 'gpx' || $size > 1024 * 1024 || !in_array($mime, $allowedMimes, true)) return false;

    if (!is_dir(GPX_DIR)) mkdir(GPX_DIR, 0755, true);
    $newFile = 'gpx_ft_' . $tourId . '_' . time() . '_' . $suffix . '.gpx';
    if (!move_uploaded_file($tmp, GPX_DIR . $newFile)) return false;

    $sort = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0) + 1 FROM future_tour_gpx_files WHERE future_tour_id = " . $tourId)->fetchColumn();
    $pdo->prepare("INSERT IGNORE INTO future_tour_gpx_files (future_tour_id, filename, day_number, sort_order) VALUES (?, ?, ?, ?)")
        ->execute([$tourId, $newFile, $dayNumber, $sort]);
    return true;
}

/** A napok sorainál feltöltött GPX-ek (`day_gpx[]`, index = nap sor) mentése a nap-számhoz kötve. */
function saveFutureTourDayGpx(PDO $pdo, int $tourId, array $post, array $files): void
{
    if (empty($files['day_gpx']['tmp_name'])) return;
    foreach ($files['day_gpx']['tmp_name'] as $i => $tmp) {
        if (($files['day_gpx']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        $dayNum = max(1, (int)($post['day_number'][$i] ?? 1));
        storeFutureTourGpx($pdo, $tourId, $tmp, $files['day_gpx']['name'][$i] ?? '', (int)($files['day_gpx']['size'][$i] ?? 0), $dayNum, 'd' . $i);
    }
}
