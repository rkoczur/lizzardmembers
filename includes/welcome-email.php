<?php
function buildWelcomeEmailHtml(
    string $firstname,
    string $username,
    string $password,
    string $loginUrl,
    string $appName = 'Lizzard Egyesület'
): string {
    $f = htmlspecialchars($firstname, ENT_QUOTES, 'UTF-8');
    $u = htmlspecialchars($username,  ENT_QUOTES, 'UTF-8');
    $p = htmlspecialchars($password,  ENT_QUOTES, 'UTF-8');
    $l = htmlspecialchars($loginUrl,  ENT_QUOTES, 'UTF-8');
    $a = htmlspecialchars($appName,   ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Üdvözlünk tagjaink között!</title>
</head>
<body style="margin:0;padding:0;background:#f0ebe0;font-family:system-ui,-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f0ebe0;padding:32px 16px;">
  <tr><td align="center">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.12);">

    <!-- Header -->
    <tr>
      <td style="background:#1a3d39;padding:32px 40px;text-align:center;">
        <div style="font-size:26px;font-weight:800;color:#F4E7CF;letter-spacing:.05em;">LIZZARD</div>
        <div style="font-size:11px;color:#8fb5b2;margin-top:6px;letter-spacing:.15em;text-transform:uppercase;">Leguán Osztag Természetjáró Egyesület</div>
      </td>
    </tr>

    <!-- Body -->
    <tr>
      <td style="padding:36px 40px 28px;">
        <p style="font-size:16px;color:#333333;margin:0 0 14px 0;">Kedves <strong>{$f}</strong>!</p>
        <p style="font-size:14px;color:#555555;line-height:1.75;margin:0 0 24px 0;">
          Örömmel értesítünk, hogy sikeresen regisztráltak a<strong>Leguán Osztag Természetjáró Egyesületbe</strong>. Az alábbiakban találod a tagsági rendszerhez szükséges bejelentkezési
          adataidat — kérjük, tartsd ezeket biztonságos helyen!
        </p>

        <!-- Credentials box -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
               style="background:#f5efe4;border:1px solid #ddd5c5;border-radius:8px;margin:0 0 26px 0;">
          <tr>
            <td style="padding:20px 24px;">
              <div style="margin-bottom:14px;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:#7a7269;margin-bottom:5px;">Felhasználónév</div>
                <div style="font-size:17px;font-weight:700;color:#1a3d39;font-family:'Courier New',Courier,monospace;">{$u}</div>
              </div>
              <div style="border-top:1px solid #ddd5c5;padding-top:14px;">
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:#7a7269;margin-bottom:5px;">Jelszó</div>
                <div style="font-size:17px;font-weight:700;color:#1a3d39;font-family:'Courier New',Courier,monospace;">{$p}</div>
              </div>
            </td>
          </tr>
        </table>

        <!-- CTA Button -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 26px 0;">
          <tr>
            <td align="center">
              <a href="{$l}" style="display:inline-block;background:#29776F;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;padding:13px 36px;border-radius:8px;letter-spacing:.02em;">
                Belépés a tagsági rendszerbe
              </a>
            </td>
          </tr>
        </table>

        <p style="font-size:13px;color:#7a7269;line-height:1.75;margin:0 0 10px 0;">
          A jelszavadat és egyéb adataidat belépés után, a <strong>Saját profilom</strong>
          menüpontban bármikor módosíthatod.
        </p>
        <p style="font-size:13px;color:#7a7269;line-height:1.75;margin:0;">
          Reméljük, hamarosan viszontlátunk egy túránkon!
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
