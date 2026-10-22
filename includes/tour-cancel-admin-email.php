<?php
// Admin értesítő: egy tag lemondta a jelentkezését egy meghirdetett túráról.
// Címzettek: admin + helyettes szerepkörű aktív tagok, valamint a túra létrehozója.

function notifyAdminsOfTourCancel(PDO $pdo, int $tourId, int $userId, string $oldStatus, ?string $promotedName): void
{
    $tourStmt = $pdo->prepare("SELECT id, name, start_date, created_by FROM future_tours WHERE id = ? LIMIT 1");
    $tourStmt->execute([$tourId]);
    $tour = $tourStmt->fetch();
    $userStmt = $pdo->prepare("SELECT firstname, lastname, email FROM users WHERE id = ? LIMIT 1");
    $userStmt->execute([$userId]);
    $member = $userStmt->fetch();
    if (!$tour || !$member) return;

    $rcptStmt = $pdo->prepare("SELECT id, email, firstname, lastname FROM users
        WHERE active = 1 AND email IS NOT NULL AND email <> ''
          AND (role IN ('admin','helyettes') OR id = ?)");
    $rcptStmt->execute([(int)$tour['created_by']]);
    $recipients = $rcptStmt->fetchAll();
    if (!$recipients) return;

    $memberName = $member['lastname'] . ' ' . $member['firstname'];
    $subject    = 'Lemondás: ' . $memberName . ' – ' . $tour['name'];
    $html       = buildTourCancelAdminEmailHtml($tour, $member, $oldStatus, $promotedName);
    $mailer     = new SmtpMailer(getSmtpConfig($pdo));

    foreach ($recipients as $r) {
        $name = $r['lastname'] . ' ' . $r['firstname'];
        try {
            $mailer->send($r['email'], $name, $subject, $html);
            logEmailEntry($pdo, (int)$r['id'], $r['email'], $name, $subject, $html, 'future_tour_cancel_admin_notify', 'sent');
        } catch (Throwable $ex) {
            error_log('Tour cancel admin notify: ' . $ex->getMessage());
        }
    }
}

function buildTourCancelAdminEmailHtml(array $tour, array $member, string $oldStatus, ?string $promotedName): string
{
    $statusLabels = ['pending' => 'Jóváhagyásra vár', 'confirmed' => 'Visszaigazolt', 'waitlist' => 'Várólistás'];
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $url   = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL
           . '/admin/future-tour-applicants.php?id=' . (int)$tour['id'];

    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $app      = $h(APP_NAME);
    $name     = $h($member['lastname'] . ' ' . $member['firstname']);
    $email    = $h($member['email'] ?: '—');
    $tourName = $h($tour['name']);
    $date     = $h(date('Y.m.d.', strtotime($tour['start_date'])));
    $status   = $h($statusLabels[$oldStatus] ?? $oldStatus);
    $safeUrl  = $h($url);
    $promoted = $promotedName
        ? '<p style="font-size:13px;color:#29776F;margin:0 0 20px;">A felszabadult helyre a várólistáról automatikusan bekerült: <strong>' . $h($promotedName) . '</strong>.</p>'
        : '';

    return <<<HTML
<!DOCTYPE html>
<html lang="hu">
<head><meta charset="UTF-8"><title>Túra lemondás</title></head>
<body style="margin:0;padding:0;background:#f0ebe0;font-family:system-ui,-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f0ebe0;padding:32px 16px;">
  <tr><td align="center">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:540px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.12);">
    <tr>
      <td style="background:#1a3d39;padding:28px 40px;text-align:center;">
        <div style="font-size:22px;font-weight:800;color:#F4E7CF;letter-spacing:.05em;">{$app}</div>
        <div style="font-size:11px;color:#8fb5b2;margin-top:4px;letter-spacing:.12em;text-transform:uppercase;">Admin értesítő</div>
      </td>
    </tr>
    <tr>
      <td style="padding:32px 40px;">
        <p style="font-size:15px;font-weight:700;color:#1a3d39;margin:0 0 6px;">Lemondás egy meghirdetett túráról</p>
        <p style="font-size:13px;color:#666;margin:0 0 24px;">Az alábbi tag lemondta a jelentkezését.</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5efe4;border:1px solid #ddd5c5;border-radius:8px;margin:0 0 20px;">
          <tr><td style="padding:18px 22px;">
            <table width="100%" cellpadding="4" cellspacing="0" style="font-size:13px;color:#444;">
              <tr><td style="color:#7a7269;width:130px;">Túra:</td><td><strong>{$tourName}</strong></td></tr>
              <tr><td style="color:#7a7269;">Indulás:</td><td>{$date}</td></tr>
              <tr><td style="color:#7a7269;">Tag:</td><td><strong>{$name}</strong></td></tr>
              <tr><td style="color:#7a7269;">E-mail:</td><td>{$email}</td></tr>
              <tr><td style="color:#7a7269;">Korábbi állapot:</td><td>{$status}</td></tr>
            </table>
          </td></tr>
        </table>
        {$promoted}
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
          <tr><td align="center">
            <a href="{$safeUrl}" style="display:inline-block;background:#29776F;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;padding:12px 32px;border-radius:8px;">
              Jelentkezők megtekintése
            </a>
          </td></tr>
        </table>
      </td>
    </tr>
    <tr>
      <td style="background:#f5efe4;border-top:1px solid #ddd5c5;padding:16px 40px;text-align:center;">
        <p style="font-size:12px;color:#7a7269;margin:0;">{$app} — automatikus rendszerüzenet</p>
      </td>
    </tr>
  </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}
