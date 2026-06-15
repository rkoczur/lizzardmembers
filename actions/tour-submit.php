<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tours-schema.php';
requireUser();

verifyCsrf();

$pdo = getDb();
ensureToursSchema($pdo);

$name          = trim($_POST['name']          ?? '');
$route         = trim($_POST['route']         ?? '');
$country       = trim($_POST['country']       ?? '');
$region        = trim($_POST['region']        ?? '');
$tourDate      = $_POST['tour_date']          ?? '';
$days          = max(1, (int)($_POST['days']  ?? 1));
$accommodation = in_array($_POST['accommodation'] ?? '', ['sator','turistahaz','apartman','hotel'])
                   ? $_POST['accommodation'] : '';
$memberIds     = array_filter(array_map('intval', $_POST['member_ids'] ?? []));
$guestCount    = max(0, (int)($_POST['guest_count'] ?? 0));
$submissionNotes = trim($_POST['submission_notes'] ?? '');

// MTSZ mezők
$tourType     = in_array($_POST['tour_type'] ?? '', ['gyalogos','kerekparos','vizi','si','barlangi','munka'])
                  ? $_POST['tour_type'] : 'gyalogos';
$totalKm       = $tourType === 'vizi' ? ($_POST['vizi_km'] ?? '') : ($_POST['total_km'] ?? '');
$totalElev     = $_POST['total_elevation']    ?? '';
$alpineKm      = $_POST['alpine_km']          ?? '';
$alpineElev    = $_POST['alpine_elevation']   ?? '';
$subType      = trim($_POST['sub_type'] ?? '') ?: null;
$multiDayType = in_array($_POST['multi_day_type'] ?? '', ['csillag','vandor'])
                  ? $_POST['multi_day_type'] : null;
$tourHours    = $_POST['tour_hours'] ?? '';
$boatPortages = max(0, (int)($_POST['boat_portages'] ?? 0));

// Sátorozás csak sátor szállásnál számít
$campFixed  = ($accommodation === 'sator') ? max(0, (int)($_POST['camping_nights_fixed']  ?? 0)) : 0;
$campMobile = ($accommodation === 'sator') ? max(0, (int)($_POST['camping_nights_mobile'] ?? 0)) : 0;

// is_alpine levezetett érték
$isAlpine = (($alpineKm !== '' && (float)$alpineKm > 0) || ($alpineElev !== '' && (int)$alpineElev > 0)) ? 1 : 0;

$submittedBy = getCurrentUserId();

$validationError = '';
if (!$name)    $validationError = 'Az elnevezés megadása kötelező.';
elseif (!$region)   $validationError = 'A tájegység megadása kötelező.';
elseif (!$tourDate) $validationError = 'A dátum megadása kötelező.';
elseif (!$country)  $validationError = 'Az ország megadása kötelező.';

if ($validationError) {
    flash('error', $validationError);
    $_SESSION['tour_submit_old'] = [
        'name' => $name, 'route' => $route, 'country' => $country, 'region' => $region,
        'tour_date' => $tourDate, 'days' => $days, 'accommodation' => $accommodation,
        'total_km' => $totalKm, 'total_elevation' => $totalElev,
        'alpine_km' => $alpineKm, 'alpine_elevation' => $alpineElev,
        'tour_type' => $tourType, 'sub_type' => $subType,
        'multi_day_type' => $multiDayType,
        'camping_nights_fixed' => $campFixed, 'camping_nights_mobile' => $campMobile,
        'tour_hours' => $tourHours, 'boat_portages' => $boatPortages,
        'guest_count' => $guestCount,
        'member_ids' => $memberIds,
        'submission_notes' => $submissionNotes,
    ];
    header('Location: ' . BASE_URL . '/user/tour-submit.php');
    exit;
}

$tourData = [
    'tour_type'            => $tourType,
    'sub_type'             => $subType,
    'days'                 => $days,
    'total_km'             => $totalKm    !== '' ? $totalKm    : null,
    'total_elevation'      => $totalElev  !== '' ? $totalElev  : null,
    'alpine_km'            => $alpineKm   !== '' ? $alpineKm   : null,
    'alpine_elevation'     => $alpineElev !== '' ? $alpineElev : null,
    'tour_hours'           => $tourHours  !== '' ? $tourHours  : null,
    'multi_day_type'       => $multiDayType,
    'accommodation'        => $accommodation,
    'camping_nights_fixed' => $campFixed,
    'camping_nights_mobile'=> $campMobile,
    'boat_portages'        => $boatPortages,
    'tour_date'            => $tourDate ?: null,
];
$mtszPoints = calculateTourPoints($tourData);

$stmt = $pdo->prepare("INSERT INTO tours
    (status, submitted_by, name, route, submission_notes, country, region, tour_date, days, accommodation,
     total_km, alpine_km, total_elevation, alpine_elevation,
     tour_type, sub_type, is_alpine, multi_day_type,
     camping_nights_fixed, camping_nights_mobile, tour_hours, boat_portages,
     guest_count, points, mtsz_points)
    VALUES ('pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)");

$stmt->execute([
    $submittedBy,
    $name ?: null,
    $route ?: null,
    $submissionNotes ?: null,
    $country,
    $region ?: null,
    $tourDate ?: null,
    $days,
    $accommodation ?: null,
    $totalKm    !== '' ? (float)$totalKm    : null,
    $alpineKm   !== '' ? (float)$alpineKm   : null,
    $totalElev  !== '' ? (int)$totalElev    : null,
    $alpineElev !== '' ? (int)$alpineElev   : null,
    $tourType,
    $subType,
    $isAlpine,
    $multiDayType,
    $campFixed,
    $campMobile,
    $tourHours !== '' ? (float)$tourHours : null,
    $boatPortages,
    $guestCount,
    $mtszPoints,
]);

$newId = (int)$pdo->lastInsertId();

$allMemberIds = array_unique(array_merge([$submittedBy], $memberIds));
$ins = $pdo->prepare("INSERT IGNORE INTO tour_members (tour_id, user_id) VALUES (?, ?)");
foreach ($allMemberIds as $uid) {
    $ins->execute([$newId, $uid]);
}

// GPX feltöltés (opcionális, max 1 MB) — hiba esetén nem buktatja meg a beküldést, csak jelez
$gpxNote = '';
if (isset($_FILES['gpx_file']) && ($_FILES['gpx_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $gpxTmp  = $_FILES['gpx_file']['tmp_name'];
    $gpxExt  = strtolower(pathinfo($_FILES['gpx_file']['name'] ?? '', PATHINFO_EXTENSION));
    $gpxSize = (int)($_FILES['gpx_file']['size'] ?? 0);
    $mime    = (new finfo(FILEINFO_MIME_TYPE))->file($gpxTmp);
    $allowedMimes = ['text/xml', 'application/xml', 'application/gpx+xml', 'text/plain', 'application/octet-stream'];
    if ($gpxExt !== 'gpx') {
        $gpxNote = ' A GPX fájl nem lett csatolva (csak .gpx tölthető fel).';
    } elseif ($gpxSize > 1 * 1024 * 1024) {
        $gpxNote = ' A GPX fájl nem lett csatolva (a méret meghaladta az 1 MB-ot).';
    } elseif (!in_array($mime, $allowedMimes, true)) {
        $gpxNote = ' A GPX fájl nem lett csatolva (érvénytelen formátum).';
    } else {
        if (!is_dir(GPX_DIR)) mkdir(GPX_DIR, 0755, true);
        $gpxFile = 'gpx_' . $newId . '_' . time() . '.gpx';
        if (move_uploaded_file($gpxTmp, GPX_DIR . $gpxFile)) {
            $pdo->prepare("INSERT IGNORE INTO tour_gpx_files (tour_id, filename) VALUES (?, ?)")->execute([$newId, $gpxFile]);
        } else {
            $gpxNote = ' A GPX fájl feltöltése nem sikerült.';
        }
    }
}

// Admin értesítő e-mail
try {
    require_once __DIR__ . '/../includes/app-settings-schema.php';
    require_once __DIR__ . '/../includes/mailer.php';
    require_once __DIR__ . '/../includes/email-log-schema.php';

    ensureAppSettingsSchema($pdo);
    ensureEmailLogSchema($pdo);
    $smtp = getSmtpConfig($pdo);

    if ($smtp['host'] !== '') {
        $admins = $pdo->query("SELECT id, firstname, lastname, email FROM users WHERE role IN ('admin','vezeto') AND active = 1")->fetchAll();

        if ($admins) {
            $submitterStmt = $pdo->prepare("SELECT firstname, lastname FROM users WHERE id = ? LIMIT 1");
            $submitterStmt->execute([$submittedBy]);
            $submitter = $submitterStmt->fetch();
            $submitterName = $submitter ? $submitter['lastname'] . ' ' . $submitter['firstname'] : 'Ismeretlen tag';

            $countryStmt = $pdo->prepare("SELECT name_hu FROM countries WHERE code = ? LIMIT 1");
            $countryStmt->execute([$country]);
            $countryName = $countryStmt->fetchColumn() ?: $country;

            $proto      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $absBaseUrl = $proto . '://' . $_SERVER['HTTP_HOST'] . BASE_URL;
            $tourUrl    = $absBaseUrl . '/admin/tour-detail.php?id=' . $newId;

            $tourDisplay   = $name ?: ($countryName . ($region ? ' – ' . $region : ''));
            $formattedDate = $tourDate ? (new DateTime($tourDate))->format('Y.m.d') : '—';
            $emailSubject  = 'Új túra beküldve jóváhagyásra: ' . $tourDisplay;

            $html = '<div style="background:#f0ebe0;padding:20px;">'
                . '<div style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 3px 16px rgba(0,0,0,.1);">'
                  . '<div style="background:#1a3d39;padding:24px 32px;text-align:center;">'
                    . '<div style="font-size:22px;font-weight:800;color:#F4E7CF;letter-spacing:.05em;">LIZZARD</div>'
                    . '<div style="font-size:11px;color:#8fb5b2;margin-top:4px;letter-spacing:.14em;text-transform:uppercase;">Természetjáró Egyesület</div>'
                  . '</div>'
                  . '<div style="padding:28px 32px 20px;">'
                    . '<p style="font-size:15px;color:#333;margin:0 0 8px 0;">Új túra érkezett jóváhagyásra!</p>'
                    . '<p style="font-size:13px;color:#555;line-height:1.7;margin:0 0 16px;"><strong>' . htmlspecialchars($submitterName, ENT_QUOTES, 'UTF-8') . '</strong> tag beküldött egy túrát, amely adminisztrátori jóváhagyásra vár.</p>'
                    . '<div style="background:#f5efe4;border:1px solid #ddd5c5;border-radius:7px;padding:16px 20px;margin:0 0 20px;">'
                      . '<div style="font-size:13px;font-weight:700;color:#1a3d39;margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid #ddd5c5;">' . htmlspecialchars($tourDisplay, ENT_QUOTES, 'UTF-8') . '</div>'
                      . '<table style="font-size:12px;width:100%;border-collapse:collapse;">'
                        . '<tr><td style="color:#7a7269;padding:0 12px 5px 0;white-space:nowrap;">Beküldő</td><td style="color:#333;font-weight:600;padding-bottom:5px;">' . htmlspecialchars($submitterName, ENT_QUOTES, 'UTF-8') . '</td></tr>'
                        . '<tr><td style="color:#7a7269;padding:0 12px 5px 0;white-space:nowrap;">Ország</td><td style="color:#333;font-weight:600;padding-bottom:5px;">' . htmlspecialchars($countryName, ENT_QUOTES, 'UTF-8') . '</td></tr>'
                        . '<tr><td style="color:#7a7269;padding:0 12px 5px 0;white-space:nowrap;">Tájegység</td><td style="color:#333;font-weight:600;padding-bottom:5px;">' . htmlspecialchars($region ?: '—', ENT_QUOTES, 'UTF-8') . '</td></tr>'
                        . '<tr><td style="color:#7a7269;padding:0 12px 0 0;white-space:nowrap;">Dátum</td><td style="color:#333;font-weight:600;">' . htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8') . '</td></tr>'
                      . '</table>'
                    . '</div>'
                    . '<div style="text-align:center;">'
                      . '<a href="' . htmlspecialchars($tourUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;background:#29776F;color:#fff;font-size:12px;font-weight:700;padding:10px 24px;border-radius:7px;text-decoration:none;">Túra megtekintése és jóváhagyása</a>'
                    . '</div>'
                  . '</div>'
                  . '<div style="background:#f5efe4;border-top:1px solid #ddd5c5;padding:14px 32px;text-align:center;">'
                    . '<p style="font-size:11px;color:#7a7269;margin:0;">Üdvözlettel,<br><strong style="color:#1a3d39;">Lizzard Outdoor Rendszer</strong></p>'
                  . '</div>'
                . '</div>'
              . '</div>';

            $mailer = new SmtpMailer($smtp);
            foreach ($admins as $admin) {
                $recipientName = $admin['lastname'] . ' ' . $admin['firstname'];
                try {
                    $mailer->send($admin['email'], $recipientName, $emailSubject, $html);
                    logEmailEntry($pdo, (int)$admin['id'], $admin['email'], $recipientName, $emailSubject, $html, 'tour_submitted', 'sent');
                } catch (Throwable $ex) {
                    error_log('Tour submit admin notification uid=' . $admin['id'] . ': ' . $ex->getMessage());
                    logEmailEntry($pdo, (int)$admin['id'], $admin['email'], $recipientName, $emailSubject, $html, 'tour_submitted', 'failed', $ex->getMessage());
                }
            }
        }
    }
} catch (Throwable $ex) {
    error_log('Tour submit admin notification setup error: ' . $ex->getMessage());
}

flash('success', 'Túrád sikeresen beküldve, jóváhagyásra vár.' . $gpxNote);
header('Location: ' . BASE_URL . '/user/tours.php');
exit;
