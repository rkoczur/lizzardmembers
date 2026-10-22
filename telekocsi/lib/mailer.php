<?php
/**
 * Önálló SMTP levélküldő a telekocsi-szervezőhöz.
 * Beállítás: config.ini [smtp] (host, port, user, password, encryption, from_email, from_name),
 * ennek hiányában a közös adatbázis `settings` táblájának smtp_* értékei.
 * [mail] log_only = 1 → a levelek nem mennek ki, a data/mail.log fájlba íródnak (fejlesztéshez).
 */

class TkMailer {
    public function __construct(private array $cfg) {}

    /** Egy levél egy címzettnek. Hiba esetén RuntimeException. */
    public function send(string $to, string $toName, string $subject, string $html): void {
        $c    = $this->cfg;
        $ctx  = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $pre  = $c['encryption'] === 'ssl' ? 'ssl://' : 'tcp://';
        $sock = @stream_socket_client($pre . $c['host'] . ':' . $c['port'], $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$sock) throw new RuntimeException("SMTP: nem sikerült csatlakozni — $errstr ($errno)");
        stream_set_timeout($sock, 15);

        $this->expect($sock, [220]);
        $this->cmd($sock, 'EHLO ' . (gethostname() ?: 'localhost'), [250]);
        if ($c['encryption'] === 'tls') {
            $this->cmd($sock, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
                throw new RuntimeException('SMTP: STARTTLS sikertelen.');
            }
            $this->cmd($sock, 'EHLO ' . (gethostname() ?: 'localhost'), [250]);
        }
        if ($c['user'] !== '') {
            $this->cmd($sock, 'AUTH LOGIN', [334]);
            $this->cmd($sock, base64_encode($c['user']), [334]);
            $this->cmd($sock, base64_encode($c['pass']), [235]);
        }
        $this->cmd($sock, "MAIL FROM:<{$c['from_email']}>", [250]);
        $this->cmd($sock, "RCPT TO:<$to>", [250, 251]);
        $this->cmd($sock, 'DATA', [354]);
        fwrite($sock, tkBuildMessage($c, $to, $toName, $subject, $html) . "\r\n.\r\n");
        $this->expect($sock, [250]);
        @fwrite($sock, "QUIT\r\n");
        fclose($sock);
    }

    private function cmd($sock, string $cmd, array $codes): void {
        fwrite($sock, $cmd . "\r\n");
        $this->expect($sock, $codes);
    }

    private function expect($sock, array $codes): void {
        $resp = '';
        while ($line = fgets($sock, 512)) {
            $resp .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break;
        }
        if (!in_array((int)substr($resp, 0, 3), $codes, true)) throw new RuntimeException('SMTP hiba: ' . trim($resp));
    }
}

function tkBuildMessage(array $c, string $to, string $toName, string $subject, string $html): string {
    $enc      = fn(string $s): string => '=?UTF-8?B?' . base64_encode($s) . '?=';
    $boundary = 'TK_' . bin2hex(random_bytes(8));
    $domain   = substr(strrchr($c['from_email'], '@'), 1) ?: 'localhost';
    $text     = html_entity_decode(strip_tags(preg_replace('#<br\s*/?>|</p>|</tr>#i', "\n", $html)), ENT_QUOTES, 'UTF-8');

    return implode("\r\n", [
        'From: ' . $enc($c['from_name']) . " <{$c['from_email']}>",
        'To: ' . ($toName !== '' ? $enc($toName) . " <$to>" : $to),
        'Subject: ' . $enc($subject),
        'Message-ID: <' . bin2hex(random_bytes(16)) . "@$domain>",
        'Date: ' . date('r'),
        'MIME-Version: 1.0',
        "Content-Type: multipart/alternative; boundary=\"$boundary\"",
        '',
        "--$boundary",
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        '',
        chunk_split(base64_encode(trim(preg_replace("/\n\s+/", "\n", $text)))),
        "--$boundary",
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        '',
        chunk_split(base64_encode($html)),
        "--$boundary--",
    ]);
}

/** SMTP-beállítás: config.ini [smtp], vagy a közös `settings` tábla smtp_* kulcsai. Nincs → null. */
function tkMailConfig(PDO $pdo): ?array {
    $s = tkIni()['smtp'] ?? null;
    if (!$s) {
        try {
            $rows = $pdo->query("SELECT `key`, `value` FROM settings WHERE `key` LIKE 'smtp\\_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Throwable) {
            $rows = [];
        }
        $s = [];
        foreach ($rows as $k => $v) $s[substr($k, 5)] = $v;
        $s['password'] = $s['pass'] ?? '';
    }
    if (empty($s['host'])) return null;
    return [
        'host'       => $s['host'],
        'port'       => (int)($s['port'] ?? 587),
        'user'       => (string)($s['user'] ?? ''),
        'pass'       => (string)($s['password'] ?? ''),
        'encryption' => strtolower((string)($s['encryption'] ?? 'tls')),
        'from_email' => (string)(($s['from_email'] ?? '') ?: ($s['user'] ?? '')),
        'from_name'  => 'Telekocsi-szervező',
    ];
}

/** Levél küldése; a hibát csak naplózza, hogy a felhasználói művelet ne bukjon el. */
function tkSendMail(PDO $pdo, string $to, string $toName, string $subject, string $html): bool {
    if (!empty(tkIni()['mail']['log_only'])) {
        $dir = __DIR__ . '/../data';
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        $entry = '[' . date('Y-m-d H:i:s') . "] To: $toName <$to>\nSubject: $subject\n" . $html . "\n" . str_repeat('-', 60) . "\n";
        return file_put_contents($dir . '/mail.log', $entry, FILE_APPEND) !== false;
    }
    $cfg = tkMailConfig($pdo);
    if (!$cfg) return false;
    try {
        (new TkMailer($cfg))->send($to, $toName, $subject, $html);
        return true;
    } catch (Throwable $ex) {
        error_log('Telekocsi e-mail hiba (' . $to . '): ' . $ex->getMessage());
        return false;
    }
}
