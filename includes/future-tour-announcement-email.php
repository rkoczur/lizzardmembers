<?php
/**
 * Meghirdetett (jövőbeli) túra értesítő e-mail sablonja.
 * Az opt-in tagoknak küldjük, amikor egy túrát meghirdetünk.
 */
function buildFutureTourAnnouncementEmailHtml(
    string $firstname,
    string $tourName,
    string $shortIntro,
    string $countryName,
    string $region,
    string $formattedDate,
    int $numDays,
    string $feeText,
    string $applyUrl,
    string $appName = 'Lizzard Egyesület'
): string {
    $f     = htmlspecialchars($firstname,     ENT_QUOTES, 'UTF-8');
    $tn    = htmlspecialchars($tourName,      ENT_QUOTES, 'UTF-8');
    $cn    = htmlspecialchars($countryName,   ENT_QUOTES, 'UTF-8');
    $rg    = htmlspecialchars($region,        ENT_QUOTES, 'UTF-8');
    $dt    = htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8');
    $fee   = htmlspecialchars($feeText,       ENT_QUOTES, 'UTF-8');
    $url   = htmlspecialchars($applyUrl,      ENT_QUOTES, 'UTF-8');
    $a     = htmlspecialchars($appName,       ENT_QUOTES, 'UTF-8');
    $days  = max(1, $numDays);
    $place = $cn . ($rg !== '' ? ' – ' . $rg : '');

    $introBlock = '';
    if (trim($shortIntro) !== '') {
        $intro = nl2br(htmlspecialchars($shortIntro, ENT_QUOTES, 'UTF-8'));
        $introBlock = '<p style="font-size:14px;color:#555555;line-height:1.75;margin:0 0 24px 0;">' . $intro . '</p>';
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Új meghirdetett túra – {$tn}</title>
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
        <p style="font-size:14px;color:#555555;line-height:1.75;margin:0 0 20px 0;">
          Új túrát hirdettünk meg, amelyre mostantól jelentkezhetsz:
        </p>

        {$introBlock}

        <!-- Tour info box -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
               style="background:#f5efe4;border:1px solid #ddd5c5;border-radius:8px;margin:0 0 26px 0;">
          <tr>
            <td style="padding:20px 24px;">
              <div style="font-size:16px;font-weight:700;color:#1a3d39;margin-bottom:14px;border-bottom:1px solid #ddd5c5;padding-bottom:12px;">{$tn}</div>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;border-collapse:collapse;">
                <tr>
                  <td style="color:#7a7269;padding:0 16px 8px 0;white-space:nowrap;vertical-align:top;">Helyszín</td>
                  <td style="color:#333333;padding-bottom:8px;font-weight:600;">{$place}</td>
                </tr>
                <tr>
                  <td style="color:#7a7269;padding:0 16px 8px 0;white-space:nowrap;vertical-align:top;">Időpont</td>
                  <td style="color:#333333;padding-bottom:8px;font-weight:600;">{$dt}</td>
                </tr>
                <tr>
                  <td style="color:#7a7269;padding:0 16px 8px 0;white-space:nowrap;vertical-align:top;">Időtartam</td>
                  <td style="color:#333333;padding-bottom:8px;font-weight:600;">{$days} nap</td>
                </tr>
                <tr>
                  <td style="color:#7a7269;padding:0 16px 0 0;white-space:nowrap;vertical-align:top;">Részvételi díj</td>
                  <td style="color:#333333;font-weight:600;">{$fee}</td>
                </tr>
              </table>
            </td>
          </tr>
        </table>

        <!-- CTA Button -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 26px 0;">
          <tr>
            <td align="center">
              <a href="{$url}" style="display:inline-block;background:#29776F;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;padding:13px 36px;border-radius:8px;letter-spacing:.02em;">
                Részletek és jelentkezés
              </a>
            </td>
          </tr>
        </table>

        <p style="font-size:12px;color:#7a7269;line-height:1.75;margin:0;">
          <strong><span style="color:#29776F;">Ha nem szeretnél több ilyen értesítőt kapni, lépj be a rendszerbe, és a Saját Profilom menüpontban kapcsold ki az „Új meghirdetett túrák” értesítést.</span></strong>
        </p>
      </td>
    </tr>

    <!-- Footer -->
    <tr>
      <td style="background:#f5efe4;border-top:1px solid #ddd5c5;padding:20px 40px;text-align:center;">
        <p style="font-size:12px;color:#7a7269;margin:0;line-height:1.6;">
          Üdvözlettel,<br>
          <strong style="color:#1a3d39;">{$a} Vezetősége</strong>
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
