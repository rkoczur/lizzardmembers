<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/future-tours-schema.php';

$requestKey = $_SERVER['HTTP_X_LOTE_KEY'] ?? '';
// Ha az API_KEY nincs beállítva (üres), a kérés engedélyezett (fejlesztői/helyi környezet).
// Éles használatnál állíts be api_key értéket a config.ini [app] szekciójában.
if (defined('API_KEY') && API_KEY !== '' && !hash_equals(API_KEY, $requestKey)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=60');

try {
    $pdo = getDb();
    ensureFutureToursSchema($pdo);

    $tours = $pdo->query("
        SELECT
            ft.id, ft.name, ft.description, ft.start_date, ft.num_days,
            ft.country, ft.region, ft.participation_fee, ft.max_attendees,
            ft.disabled_standard_fields, ft.requires_membership,
            c.name_hu AS country_name,
            (SELECT COUNT(*) FROM future_tour_applications fta
             WHERE fta.future_tour_id = ft.id AND fta.status = 'confirmed') AS confirmed_count,
            (SELECT COUNT(*) FROM future_tour_applications fta
             WHERE fta.future_tour_id = ft.id AND fta.status = 'waitlist') AS waitlist_count
        FROM future_tours ft
        LEFT JOIN countries c ON c.code = ft.country
        WHERE ft.status = 'open'
        ORDER BY ft.start_date ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $cfStmt = $pdo->prepare("SELECT id, field_name, field_type, field_options FROM future_tour_custom_fields WHERE future_tour_id = ? ORDER BY sort_order ASC, id ASC");

    foreach ($tours as &$t) {
        $t['id']               = (int)$t['id'];
        $t['num_days']         = (int)$t['num_days'];
        $t['max_attendees']    = (int)$t['max_attendees'];
        $t['confirmed_count']  = (int)$t['confirmed_count'];
        $t['waitlist_count']   = (int)$t['waitlist_count'];
        $t['spots_left']       = max(0, $t['max_attendees'] - $t['confirmed_count']);
        $t['participation_fee']= $t['participation_fee'] !== null ? (float)$t['participation_fee'] : null;
        $t['apply_path']              = '/user/future-tour-apply-public.php?id=' . $t['id'];
        $t['disabled_standard_fields'] = json_decode($t['disabled_standard_fields'] ?? '[]', true) ?: [];
        $t['requires_membership']      = (bool)$t['requires_membership'];

        $cfStmt->execute([$t['id']]);
        $cfs = $cfStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cfs as &$cf) { $cf['id'] = (int)$cf['id']; }
        unset($cf);
        $t['custom_fields'] = $cfs;
    }
    unset($t);

    echo json_encode(['tours' => $tours], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Adatbázis hiba'], JSON_UNESCAPED_UNICODE);
}
