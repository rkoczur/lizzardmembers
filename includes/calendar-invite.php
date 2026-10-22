<?php
/**
 * Naptár-meghívó a meghirdetett túrákhoz: egész napos esemény (start_date … +num_days).
 * Google / Outlook.com / Microsoft 365 linkek és .ics fájl (Outlook asztali, Apple Naptár).
 */

/** Az esemény adatai; null, ha a túra nem létezik, törölt, vagy nincs kezdő dátuma. */
function getTourCalendarEvent(PDO $pdo, int $tourId, string $absBaseUrl): ?array
{
    $stmt = $pdo->prepare("
        SELECT ft.id, ft.name, ft.start_date, ft.num_days, ft.region, ft.short_intro, c.name_hu AS country_name
        FROM future_tours ft
        LEFT JOIN countries c ON c.code = ft.country
        WHERE ft.id = ? AND ft.status != 'cancelled'
        LIMIT 1
    ");
    $stmt->execute([$tourId]);
    $tour = $stmt->fetch();
    if (!$tour || empty($tour['start_date'])) {
        return null;
    }

    $start = new DateTimeImmutable($tour['start_date']);
    $days  = max(1, (int)$tour['num_days']);
    $url   = $absBaseUrl . '/public/tour-detail.php?id=' . $tourId;

    return [
        'id'       => $tourId,
        'title'    => (string)$tour['name'],
        'start'    => $start,
        'end'      => $start->modify('+' . $days . ' days'), // kizárólagos vége (egész napos esemény)
        'location' => implode(', ', array_filter([trim((string)$tour['region']), trim((string)$tour['country_name'])])),
        'details'  => trim(trim((string)$tour['short_intro']) . "\n\n" . $url),
        'url'      => $url,
        'ics_url'  => $absBaseUrl . '/public/tour-calendar.php?id=' . $tourId,
    ];
}

function calendarGoogleUrl(array $ev): string
{
    return 'https://calendar.google.com/calendar/render?' . http_build_query([
        'action'   => 'TEMPLATE',
        'text'     => $ev['title'],
        'dates'    => $ev['start']->format('Ymd') . '/' . $ev['end']->format('Ymd'),
        'details'  => $ev['details'],
        'location' => $ev['location'],
    ], '', '&', PHP_QUERY_RFC3986);
}

/** $host: 'outlook.live.com' (személyes) vagy 'outlook.office.com' (Microsoft 365) */
function calendarOutlookUrl(array $ev, string $host = 'outlook.live.com'): string
{
    return 'https://' . $host . '/calendar/0/action/compose?' . http_build_query([
        'rru'      => 'addevent',
        'subject'  => $ev['title'],
        'startdt'  => $ev['start']->format('Y-m-d'),
        'enddt'    => $ev['end']->format('Y-m-d'),
        'allday'   => 'true',
        'body'     => $ev['details'],
        'location' => $ev['location'],
    ], '', '&', PHP_QUERY_RFC3986);
}

/** RFC 5545 szöveg-escape */
function icsEscape(string $s): string
{
    return str_replace(["\\", ";", ",", "\r\n", "\n"], ["\\\\", "\\;", "\\,", "\\n", "\\n"], $s);
}

/** 75 oktettnél hosszabb sorok tördelése (UTF-8 karakter nem vágódik ketté) */
function icsFold(string $line): string
{
    $out = '';
    $max = 75; // a folytatósorok vezető szóköze is beleszámít
    while (strlen($line) > $max) {
        $cut = $max;
        while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) $cut--;
        $out  .= substr($line, 0, $cut) . "\r\n ";
        $line  = substr($line, $cut);
        $max   = 74;
    }
    return $out . $line;
}

function calendarIcs(array $ev, string $uidDomain): string
{
    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//LizzardMembers//Tura naptar//HU',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:future-tour-' . $ev['id'] . '@' . $uidDomain,
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'DTSTART;VALUE=DATE:' . $ev['start']->format('Ymd'),
        'DTEND;VALUE=DATE:' . $ev['end']->format('Ymd'),
        'SUMMARY:' . icsEscape($ev['title']),
        'DESCRIPTION:' . icsEscape($ev['details']),
        'LOCATION:' . icsEscape($ev['location']),
        'URL:' . $ev['url'],
        'TRANSP:OPAQUE',
        'END:VEVENT',
        'END:VCALENDAR',
    ];
    return implode("\r\n", array_map('icsFold', $lines)) . "\r\n";
}

/** „Mentsd a naptáradba” blokk az e-mailekhez (e-mail kliensek miatt inline stílussal) */
function calendarButtonsEmailHtml(array $ev): string
{
    $btn = static fn(string $href, string $label): string =>
        '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '" style="display:inline-block;margin:2px 3px;background:#fff;color:#1a3d39;'
        . 'border:1px solid #29776F;text-decoration:none;padding:4px 10px;border-radius:5px;font-size:12px;font-weight:600;">'
        . $label . '</a>';

    return '<div style="border:1px dashed #29776F;border-radius:6px;padding:8px 10px;margin-top:16px;text-align:center;">
      <p style="margin:0 0 4px;font-size:12px;color:#1a3d39;font-weight:600;">Mentsd el a túrát a naptáradba:</p>'
        . $btn(calendarGoogleUrl($ev), 'Google Naptár')
        . $btn(calendarOutlookUrl($ev, 'outlook.live.com'), 'Outlook.com')
        . '</div>';
}
