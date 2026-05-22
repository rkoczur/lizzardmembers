<?php
class SmtpMailer
{
    private string $host;
    private int    $port;
    private string $user;
    private string $pass;
    private string $enc;        // '', 'ssl', 'tls'
    private string $fromEmail;
    private string $fromName;

    public function __construct(array $cfg)
    {
        $this->host      = $cfg['host']       ?? '';
        $this->port      = (int)($cfg['port'] ?? 587);
        $this->user      = $cfg['user']       ?? '';
        $this->pass      = $cfg['pass']       ?? '';
        $this->enc       = strtolower($cfg['encryption'] ?? 'tls');
        $this->fromEmail = $cfg['from_email'] ?? $cfg['user'] ?? '';
        $this->fromName  = $cfg['from_name']  ?? '';
    }

    public function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): void
    {
        if (!$this->host) {
            throw new RuntimeException('Az SMTP szerver nincs beállítva.');
        }

        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ]);

        $prefix = $this->enc === 'ssl' ? 'ssl://' : 'tcp://';
        $sock   = @stream_socket_client(
            $prefix . $this->host . ':' . $this->port,
            $errno, $errstr, 15,
            STREAM_CLIENT_CONNECT, $ctx
        );
        if (!$sock) {
            throw new RuntimeException("SMTP: Nem sikerült csatlakozni ({$this->host}:{$this->port}) — $errstr ($errno)");
        }
        stream_set_timeout($sock, 15);

        $this->expect($sock, 220);
        $this->cmd($sock, 'EHLO ' . (gethostname() ?: 'localhost'), 250);

        if ($this->enc === 'tls') {
            $this->cmd($sock, 'STARTTLS', 220);
            $method = defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')
                ? STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
                : STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (!stream_socket_enable_crypto($sock, true, $method)) {
                throw new RuntimeException('SMTP: STARTTLS titkosítás sikertelen.');
            }
            $this->cmd($sock, 'EHLO ' . (gethostname() ?: 'localhost'), 250);
        }

        if ($this->user !== '') {
            $this->cmd($sock, 'AUTH LOGIN', 334);
            $this->cmd($sock, base64_encode($this->user), 334);
            $this->cmd($sock, base64_encode($this->pass), 235);
        }

        $this->cmd($sock, "MAIL FROM:<{$this->fromEmail}>", 250);
        $this->cmd($sock, "RCPT TO:<{$toEmail}>", [250, 251]);
        $this->cmd($sock, 'DATA', 354);

        fwrite($sock, $this->buildMessage($toEmail, $toName, $subject, $htmlBody, $textBody) . "\r\n.\r\n");
        $this->expect($sock, 250);

        @$this->cmd($sock, 'QUIT', 221);
        fclose($sock);
    }

    // ----------------------------------------------------------------
    private function cmd($sock, string $cmd, $expect): string
    {
        fwrite($sock, $cmd . "\r\n");
        return $this->expect($sock, $expect);
    }

    private function expect($sock, $codes): string
    {
        $codes = (array)$codes;
        $resp  = '';
        while ($line = fgets($sock, 512)) {
            $resp .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        $code = (int)substr(ltrim($resp), 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException("SMTP hiba $code: " . trim($resp));
        }
        return $resp;
    }

    private function buildMessage(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody): string
    {
        $boundary = '====LZMB_' . bin2hex(random_bytes(8)) . '====';

        $from = $this->fromName !== ''
            ? '=?UTF-8?B?' . base64_encode($this->fromName) . '?= <' . $this->fromEmail . '>'
            : $this->fromEmail;
        $to = $toName !== ''
            ? '=?UTF-8?B?' . base64_encode($toName) . '?= <' . $toEmail . '>'
            : $toEmail;

        if ($textBody === '') {
            $textBody = strip_tags(str_replace(
                ['<br>', '<br/>', '<br />', '</p>', '</li>', '</h1>', '</h2>', '</h3>'],
                "\n", $htmlBody
            ));
        }

        $fromDomain = substr(strrchr($this->fromEmail, '@'), 1) ?: 'localhost';
        $messageId  = '<' . bin2hex(random_bytes(16)) . '.' . time() . '@' . $fromDomain . '>';

        $m  = "From: $from\r\n";
        $m .= "To: $to\r\n";
        $m .= 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
        $m .= "Message-ID: $messageId\r\n";
        $m .= 'Date: ' . date('r') . "\r\n";
        $m .= "MIME-Version: 1.0\r\n";
        $m .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
        $m .= "X-Mailer: LizzardMembers\r\n";
        $m .= "\r\n";

        $m .= "--$boundary\r\n";
        $m .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $m .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $m .= chunk_split(base64_encode($textBody)) . "\r\n";

        $m .= "--$boundary\r\n";
        $m .= "Content-Type: text/html; charset=UTF-8\r\n";
        $m .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $m .= chunk_split(base64_encode($htmlBody)) . "\r\n";

        $m .= "--$boundary--\r\n";
        return $m;
    }
}
