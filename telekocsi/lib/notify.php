<?php
/**
 * E-mail értesítők.
 * Szervező: új sofőr, sofőr visszalépett, új utas, utas lemondta / elhagyta a helyét.
 * Sofőr: új utas ült be hozzá, ill. egy utasa lemondta a helyét.
 * Aki a változást okozta, nem kap levelet; aki szervező és sofőr is, csak egy levelet kap.
 */

const TK_EVENTS = [
    'driver_new'       => 'Új sofőr',
    'driver_removed'   => 'Sofőr visszalépett',
    'passenger_booked' => 'Új utas',
    'passenger_left'   => 'Utas lemondta a helyét',
];

function tkGetOwner(PDO $pdo, int $boardId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM tk_people WHERE board_id = ? AND is_owner = 1 ORDER BY id LIMIT 1");
    $stmt->execute([$boardId]);
    return $stmt->fetch() ?: null;
}

/** Friss autóadat (utasokkal) azonosító alapján. */
function tkFindCar(PDO $pdo, int $boardId, int $driverId): ?array {
    foreach (tkGetDrivers($pdo, $boardId) as $d) {
        if ((int)$d['id'] === $driverId) return $d;
    }
    return null;
}

/**
 * Értesítés egy autót érintő eseményről. $actor: aki a változást okozta.
 * $toOwner = false: csak a sofőr kap levelet (pl. átülésnél a régi autó sofőrje).
 */
function tkNotify(PDO $pdo, array $board, string $event, array $car, array $actor, bool $toOwner = true): void {
    $owner      = tkGetOwner($pdo, (int)$board['id']);
    $isPassEvt  = str_starts_with($event, 'passenger_');
    $recipients = [];
    if ($isPassEvt) $recipients[(int)$car['person_id']] = ['name' => $car['name'], 'email' => $car['email'], 'as' => 'driver'];
    if ($toOwner && $owner && !isset($recipients[(int)$owner['id']])) {
        $recipients[(int)$owner['id']] = ['name' => $owner['name'], 'email' => $owner['email'], 'as' => 'owner'];
    }
    unset($recipients[(int)$actor['id']]);

    foreach ($recipients as $r) {
        $subject = 'Telekocsi: ' . mb_strtolower(TK_EVENTS[$event]) . ' – ' . ($board['title'] ?: tkFormatCode($board['code']));
        tkSendMail($pdo, $r['email'], $r['name'], $subject, tkNotifyHtml($board, $event, $car, $actor, $r));
    }
}

function tkNotifySentence(string $event, array $car, array $actor, string $as): string {
    $who = '<strong>' . e($actor['name']) . '</strong>';
    if ($as === 'driver') {
        return $event === 'passenger_booked'
            ? "$who helyet foglalt az autódban."
            : "$who lemondta a helyét az autódban.";
    }
    $driver = '<strong>' . e($car['name']) . '</strong>';
    return match ($event) {
        'driver_new'       => "$who sofőrként jelentkezett, " . (int)$car['seats'] . ' szabad hellyel.',
        'driver_removed'   => "$who visszalépett sofőrként — az autójában ülők foglalása törlődött.",
        'passenger_booked' => "$who helyet foglalt $driver autójában.",
        default            => "$who lemondta a helyét $driver autójában.",
    };
}

function tkNotifyHtml(array $board, string $event, array $car, array $actor, array $r): string {
    $link  = e(tkBoardUrl($board['code'], true));
    $title = e($board['title'] ?: 'Közös utazás');
    $free  = $event === 'driver_removed' ? '' : '<tr><td style="color:#6d6a63;width:120px;">Szabad hely:</td><td><strong>' . (int)$car['free'] . ' / ' . (int)$car['seats'] . '</strong></td></tr>';
    $time  = $car['departure_time'] ? '<tr><td style="color:#6d6a63;">Indulás:</td><td><strong>' . e($car['departure_time']) . '</strong></td></tr>' : '';
    $list  = '';
    if ($r['as'] === 'driver' && $car['passengers']) {
        $names = array_map(fn($p) => e($p['name']) . ' (' . e($p['email']) . ')', $car['passengers']);
        $list  = '<tr><td style="color:#6d6a63;vertical-align:top;">Utasaid:</td><td>' . implode('<br>', $names) . '</td></tr>';
    }
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#1c1e21;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px;">
<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#f3efe6;border:3px solid #16181a;border-radius:12px;overflow:hidden;">
  <tr><td style="background:#ffc61a;padding:18px 28px;border-bottom:3px solid #16181a;">
    <p style="margin:0;font-size:12px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:#16181a;">Telekocsi · ' . e(TK_EVENTS[$event]) . '</p>
    <h1 style="margin:6px 0 0;font-size:22px;color:#16181a;">' . $title . '</h1>
  </td></tr>
  <tr><td style="padding:24px 28px;color:#16181a;">
    <p style="font-size:15px;line-height:1.6;margin:0 0 18px;">Szia ' . e($r['name']) . '! ' . tkNotifySentence($event, $car, $actor, $r['as']) . '</p>
    <table width="100%" cellpadding="4" cellspacing="0" style="font-size:14px;background:#fff;border:2px solid #16181a;border-radius:8px;padding:10px;margin:0 0 22px;">
      <tr><td style="color:#6d6a63;width:120px;">Sofőr:</td><td><strong>' . e($car['name']) . '</strong></td></tr>
      <tr><td style="color:#6d6a63;">Honnan:</td><td><strong>' . e($car['departure']) . '</strong></td></tr>
      ' . $time . $free . $list . '
    </table>
    <p style="text-align:center;margin:0 0 18px;">
      <a href="' . $link . '" style="display:inline-block;background:#16181a;color:#ffc61a;text-decoration:none;padding:12px 26px;border-radius:999px;font-weight:bold;font-size:14px;">Szervező megnyitása</a>
    </p>
    <p style="font-size:12px;color:#6d6a63;margin:0;text-align:center;">Kód: <strong>' . e(tkFormatCode($board['code'])) . '</strong> · ' . $link . '</p>
  </td></tr>
</table></td></tr></table></body></html>';
}
