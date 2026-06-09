<?php
/**
 * Megosztott segédfüggvények a tömeges e-mail küldéshez.
 * Használja: getLevelLabel(), formatDate() (functions.php) és az APP_NAME konstans.
 */

/** Egy taghoz tartozó behelyettesítési térkép ({{nev}} stb.). */
function bulkEmailMergeMap(array $m): array
{
    $name = trim(($m['lastname'] ?? '') . ' ' . ($m['firstname'] ?? ''));
    return [
        '{{nev}}'            => $name,
        '{{vezeteknev}}'     => $m['lastname']  ?? '',
        '{{keresztnev}}'     => $m['firstname'] ?? '',
        '{{email}}'          => $m['email']     ?? '',
        '{{felhasznalonev}}' => $m['username']  ?? '',
        '{{szint}}'          => getLevelLabel((int)($m['level'] ?? 1)),
        '{{pontok}}'         => number_format((int)($m['points'] ?? 0)),
        '{{varos}}'          => $m['city'] ?? '',
        '{{tagsag_kezdete}}' => formatDate($m['member_since'] ?? null),
    ];
}

/** Behelyettesíti a {{...}} mezőket a sablonba. */
function applyBulkEmailMerge(string $template, array $member): string
{
    $map = bulkEmailMergeMap($member);
    return str_replace(array_keys($map), array_values($map), $template);
}

/**
 * Branded HTML e-mail váz a tömeges levélhez.
 * Ha a tartalom már HTML-t tartalmaz, változatlanul beágyazza; egyébként
 * a sortöréseket <br>-re alakítja és escape-eli.
 */
function buildBulkEmailHtml(string $subject, string $content): string
{
    if (!preg_match('/<[a-z][\s\S]*>/i', $content)) {
        $inner = '<p style="font-size:14px;color:#555555;line-height:1.75;margin:0;">'
               . nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'))
               . '</p>';
    } else {
        $inner = '<div style="font-size:14px;color:#555555;line-height:1.75;">' . $content . '</div>';
    }

    $appEsc  = htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8');
    $subjEsc = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');

    return <<<MAIL
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>{$subjEsc}</title>
</head>
<body style="margin:0;padding:0;background:#f0ebe0;font-family:system-ui,-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f0ebe0;padding:32px 16px;">
  <tr><td align="center">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.12);">
    <tr>
      <td style="background:#1a3d39;padding:32px 40px;text-align:center;">
        <div style="font-size:26px;font-weight:800;color:#F4E7CF;letter-spacing:.05em;">Lizzard Outdoor</div>
      </td>
    </tr>
    <tr>
      <td style="padding:36px 40px 32px;">
        {$inner}
      </td>
    </tr>
    <tr>
      <td style="background:#f5efe4;border-top:1px solid #ddd5c5;padding:20px 40px;text-align:center;">
        <p style="font-size:12px;color:#7a7269;margin:0;line-height:1.6;">
          Üdvözlettel,<br>
          <strong style="color:#1a3d39;">{$appEsc} Vezetősége</strong>
        </p>
      </td>
    </tr>
  </table>
  </td></tr>
</table>
</body>
</html>
MAIL;
}
