<?php
function buildTourNotificationEmailHtml(
    string $firstname,
    string $tourDisplayName,
    string $countryName,
    string $formattedDate,
    string $tourTypeLabel,
    string $kmText,
    string $elevText,
    int $lizzardPoints,
    int $mtszPoints,
    string $tourCode,
    int $newLevel,
    int $oldLevel,
    string $tourUrl,
    string $absBaseUrl,
    string $appName = 'Lizzard Egyesület'
): string {
    $f    = htmlspecialchars($firstname,       ENT_QUOTES, 'UTF-8');
    $tn   = htmlspecialchars($tourDisplayName, ENT_QUOTES, 'UTF-8');
    $cn   = htmlspecialchars($countryName,     ENT_QUOTES, 'UTF-8');
    $dt   = htmlspecialchars($formattedDate,   ENT_QUOTES, 'UTF-8');
    $tt   = htmlspecialchars($tourTypeLabel,   ENT_QUOTES, 'UTF-8');
    $km   = htmlspecialchars($kmText,          ENT_QUOTES, 'UTF-8');
    $elev = htmlspecialchars($elevText,        ENT_QUOTES, 'UTF-8');
    $code = htmlspecialchars($tourCode,        ENT_QUOTES, 'UTF-8');
    $url  = htmlspecialchars($tourUrl,         ENT_QUOTES, 'UTF-8');
    $a    = htmlspecialchars($appName,         ENT_QUOTES, 'UTF-8');
    $base = rtrim($absBaseUrl, '/');

    $leveledUp  = $newLevel > $oldLevel;
    $levelLabel = getLevelLabel($newLevel);
    $ell        = htmlspecialchars($levelLabel, ENT_QUOTES, 'UTF-8');

    // Build points box before heredoc — PHP tags don't work inside heredoc
    $mtszCell = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                       style="background:#fef3e2;border:1px solid #fcd99a;border-radius:8px;">
                  <tr>
                    <td style="padding:16px 20px;text-align:center;">
                      <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#d97706;margin-bottom:10px;">MTSZ pont</div>
                      <div style="font-size:30px;font-weight:800;color:#d97706;line-height:1;">' . $mtszPoints . '</div>
                      <div style="font-size:12px;font-weight:600;color:#d97706;margin-top:4px;">pont</div>
                    </td>
                  </tr>
                </table>';

    if ($lizzardPoints > 0) {
        $pointsBox = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 26px 0;">
          <tr>
            <td width="50%" style="padding-right:6px;vertical-align:top;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                     style="background:#eaf3f2;border:1px solid #b8d8d5;border-radius:8px;">
                <tr>
                  <td style="padding:16px 20px;text-align:center;">
                    <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#5a8a87;margin-bottom:10px;">Lizzardier pont</div>
                    <div style="font-size:30px;font-weight:800;color:#29776F;line-height:1;">' . $lizzardPoints . '</div>
                    <div style="font-size:12px;font-weight:600;color:#29776F;margin-top:4px;">pont</div>
                  </td>
                </tr>
              </table>
            </td>
            <td width="50%" style="padding-left:6px;vertical-align:top;">' . $mtszCell . '</td>
          </tr>
        </table>';
    } else {
        $pointsBox = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 26px 0;">
          <tr>
            <td style="padding:0 80px;vertical-align:top;">' . $mtszCell . '</td>
          </tr>
        </table>';
    }

    $levelUpSection = '';
    if ($leveledUp) {
        $imgFile = getLevelImageFilename($newLevel);
        $imgTag  = '';
        if ($imgFile) {
            $imgUrl = htmlspecialchars($base . '/assets/img/' . $imgFile, ENT_QUOTES, 'UTF-8');
            $imgTag = "<img src=\"{$imgUrl}\" alt=\"{$ell}\" width=\"110\" style=\"display:block;width:110px;height:auto;margin:10px auto 0;\">";
        }
        $levelUpSection = <<<HTML

        <!-- Level-up box -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
               style="background:#fef9ec;border:2px solid #f0d060;border-radius:8px;margin:0 0 26px 0;">
          <tr>
            <td style="padding:20px 24px;text-align:center;">
              <div style="font-size:28px;line-height:1;margin-bottom:8px;">&#127894;</div>
              <div style="font-size:16px;font-weight:700;color:#7a5800;margin-bottom:4px;">Szintet léptél!</div>
              <div style="font-size:13px;color:#9a6e00;">Gratulálunk! Mostantól <strong>{$ell}</strong> vagy.</div>
              {$imgTag}
            </td>
          </tr>
        </table>
HTML;
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Új túra – {$tn}</title>
</head>
<body style="margin:0;padding:0;background:#f0ebe0;font-family:system-ui,-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f0ebe0;padding:32px 16px;">
  <tr><td align="center">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.12);">

    <!-- Header -->
    <tr>
      <td style="background:#1a3d39;padding:32px 40px;text-align:center;">
        <div style="font-size:26px;font-weight:800;color:#F4E7CF;letter-spacing:.05em;">Lizzard Outdoor</div>
        <div style="font-size:11px;color:#8fb5b2;margin-top:6px;letter-spacing:.15em;text-transform:uppercase;">Leguán Osztag Természetjáró Egyesület</div>
      </td>
    </tr>

    <!-- Body -->
    <tr>
      <td style="padding:36px 40px 28px;">
        <p style="font-size:16px;color:#333333;margin:0 0 8px 0;">Kedves <strong>{$f}</strong>!</p>
        <p style="font-size:14px;color:#555555;line-height:1.75;margin:0 0 24px 0;">
          Új túrához adtak hozzá a <strong>Lizzard Outdoor</strong> nyilvántartásában.
        </p>

        <!-- Tour info box -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
               style="background:#f5efe4;border:1px solid #ddd5c5;border-radius:8px;margin:0 0 26px 0;">
          <tr>
            <td style="padding:20px 24px;">
              <div style="font-size:15px;font-weight:700;color:#1a3d39;margin-bottom:14px;border-bottom:1px solid #ddd5c5;padding-bottom:12px;">{$tn}</div>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;border-collapse:collapse;">
                <tr>
                  <td style="color:#7a7269;padding:0 16px 8px 0;white-space:nowrap;vertical-align:top;">Ország</td>
                  <td style="color:#333333;padding-bottom:8px;font-weight:600;">{$cn}</td>
                </tr>
                <tr>
                  <td style="color:#7a7269;padding:0 16px 8px 0;white-space:nowrap;vertical-align:top;">Dátum</td>
                  <td style="color:#333333;padding-bottom:8px;font-weight:600;">{$dt}</td>
                </tr>
                <tr>
                  <td style="color:#7a7269;padding:0 16px 8px 0;white-space:nowrap;vertical-align:top;">Típus</td>
                  <td style="color:#333333;padding-bottom:8px;font-weight:600;">{$tt}</td>
                </tr>
                <tr>
                  <td style="color:#7a7269;padding:0 16px 8px 0;white-space:nowrap;vertical-align:top;">Távolság</td>
                  <td style="color:#333333;padding-bottom:8px;font-weight:600;">{$km}</td>
                </tr>
                <tr>
                  <td style="color:#7a7269;padding:0 16px 8px 0;white-space:nowrap;vertical-align:top;">Szintemelkedés</td>
                  <td style="color:#333333;padding-bottom:8px;font-weight:600;">{$elev}</td>
                </tr>
                <tr>
                  <td style="color:#7a7269;padding:0 16px 0 0;white-space:nowrap;vertical-align:top;">Túra azonosítója</td>
                  <td style="color:#333333;font-family:'Courier New',Courier,monospace;font-weight:700;">{$code}</td>
                </tr>
              </table>
            </td>
          </tr>
        </table>

        {$pointsBox}

        {$levelUpSection}

        <!-- CTA Button -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 26px 0;">
          <tr>
            <td align="center">
              <a href="{$url}" style="display:inline-block;background:#29776F;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;padding:13px 36px;border-radius:8px;letter-spacing:.02em;">
                Túra megtekintése a rendszerben
              </a>
            </td>
          </tr>
        </table>

        <p style="font-size:12px;color:#7a7269;line-height:1.75;margin:0;">
          Az összes pontszámod, szinted és a részletes számítási módszert a Lizzard tagsági rendszerében tekintheted meg. 
          <strong><span style="color:#29776F;">Ha nem szeretnél ilyen értesítőket kapni, akkor lépj be a rendszerbe és a Saját Profilom menüpont alatt tudod módosítani milyen e-maileket küldhetünk neked!</span></strong>
        </p>
        
      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td style="background:#f5efe4;border-top:1px solid #ddd5c5;padding:20px 40px;text-align:center;">
        <p style="font-size:12px;color:#7a7269;margin:0;line-height:1.6;">
          Üdvözlettel,<br>
          <strong style="color:#1a3d39;">Lizzard Outdoor Vezetősége</strong>
        </p>
      </td>
    </tr>

  </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}
