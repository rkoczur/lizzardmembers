<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

verifyCsrf();

$pdo    = getDb();
$tourId = (int)($_POST['tour_id'] ?? 0);

$tourStmt = $pdo->prepare("
    SELECT t.*, c.name_hu AS country_name, u.firstname, u.lastname, u.email, u.notification_prefs
    FROM tours t
    LEFT JOIN countries c ON c.code = t.country
    LEFT JOIN users u ON u.id = t.submitted_by
    WHERE t.id = ? AND t.status = 'pending'
    LIMIT 1
");
$tourStmt->execute([$tourId]);
$tour = $tourStmt->fetch();

if (!$tour) {
    flash('error', 'A túra nem található vagy nem vár jóváhagyásra.');
    header('Location: ' . BASE_URL . '/admin/tours.php');
    exit;
}

$pdo->prepare("DELETE FROM tours WHERE id = ? AND status = 'pending'")->execute([$tourId]);

// E-mail értesítő a beküldőnek
if (!empty($tour['email'])) {
    try {
        require_once __DIR__ . '/../includes/app-settings-schema.php';
        require_once __DIR__ . '/../includes/mailer.php';
        require_once __DIR__ . '/../includes/email-log-schema.php';

        ensureAppSettingsSchema($pdo);
        ensureEmailLogSchema($pdo);
        $smtp = getSmtpConfig($pdo);

        if ($smtp['host'] !== '') {
            $prefs = json_decode($tour['notification_prefs'] ?? '{}', true) ?? [];
            if (($prefs['tour_rejected'] ?? 1) != 0) {
                $firstname     = $tour['firstname'] ?? '';
                $recipientName = trim(($tour['lastname'] ?? '') . ' ' . $firstname);
                $countryName   = $tour['country_name'] ?: ($tour['country'] ?? '—');
                $tourDisplay   = $tour['name'] ?: $countryName;
                $formattedDate = $tour['tour_date'] ? (new DateTime($tour['tour_date']))->format('Y.m.d') : '—';
                $emailSubject  = 'Túra beküldése elutasítva: ' . $tourDisplay;

                $f  = htmlspecialchars($firstname,    ENT_QUOTES, 'UTF-8');
                $tn = htmlspecialchars($tourDisplay,  ENT_QUOTES, 'UTF-8');
                $cn = htmlspecialchars($countryName,  ENT_QUOTES, 'UTF-8');
                $dt = htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8');

                $proto      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $absBaseUrl = $proto . '://' . $_SERVER['HTTP_HOST'] . BASE_URL;

                $html = <<<HTML
<!DOCTYPE html>
<html lang="hu">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Túra elutasítva – {$tn}</title>
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
          Sajnálattal értesítünk, hogy az alábbi beküldött túrát az adminisztrátor <strong>elutasította</strong>.
        </p>

        <!-- Tour info box -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
               style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;margin:0 0 26px 0;">
          <tr>
            <td style="padding:20px 24px;">
              <div style="font-size:15px;font-weight:700;color:#991b1b;margin-bottom:14px;border-bottom:1px solid #fecaca;padding-bottom:12px;">{$tn}</div>
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:13px;border-collapse:collapse;">
                <tr>
                  <td style="color:#7a7269;padding:0 16px 8px 0;white-space:nowrap;vertical-align:top;">Ország</td>
                  <td style="color:#333333;padding-bottom:8px;font-weight:600;">{$cn}</td>
                </tr>
                <tr>
                  <td style="color:#7a7269;padding:0 16px 0 0;white-space:nowrap;vertical-align:top;">Dátum</td>
                  <td style="color:#333333;font-weight:600;">{$dt}</td>
                </tr>
              </table>
            </td>
          </tr>
        </table>

        <p style="font-size:13px;color:#555555;line-height:1.75;margin:0 0 8px 0;">
          Ha kérdésed van az elutasítás okával kapcsolatban, kérjük vedd fel a kapcsolatot az egyesület adminisztrátorával.
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

                $mailer = new SmtpMailer($smtp);
                try {
                    $mailer->send($tour['email'], $recipientName, $emailSubject, $html);
                    logEmailEntry($pdo, (int)$tour['submitted_by'], $tour['email'], $recipientName, $emailSubject, $html, 'tour_rejected', 'sent');
                } catch (Throwable $ex) {
                    error_log('Tour reject notification email: ' . $ex->getMessage());
                    logEmailEntry($pdo, (int)$tour['submitted_by'], $tour['email'], $recipientName, $emailSubject, $html, 'tour_rejected', 'failed', $ex->getMessage());
                }
            }
        }
    } catch (Throwable $ex) {
        error_log('Tour reject notification setup error: ' . $ex->getMessage());
    }
}

flash('success', 'A beküldött túra el lett utasítva.');
header('Location: ' . BASE_URL . '/admin/tours.php');
exit;
