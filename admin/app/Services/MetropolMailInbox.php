<?php

declare(strict_types=1);

/**
 * IMAP gelen kutusu okuyucu — Billion Mail / Dovecot (993 SSL).
 */

if (!function_exists('metropol_mail_imap_available')) {
    function metropol_mail_imap_available(): bool
    {
        return function_exists('imap_open') && extension_loaded('imap');
    }
}

if (!function_exists('metropol_mail_imap_diagnostics')) {
    function metropol_mail_imap_diagnostics(): string
    {
        $lines = [];
        $lines[] = 'PHP: ' . PHP_VERSION . ' (' . PHP_SAPI . ')';
        $lines[] = 'extension_loaded(imap): ' . (extension_loaded('imap') ? 'evet' : 'hayir');
        $lines[] = 'function_exists(imap_open): ' . (function_exists('imap_open') ? 'evet' : 'hayir');
        $ini = (string) php_ini_loaded_file();
        if ($ini !== '') {
            $lines[] = 'php.ini: ' . $ini;
        }
        $disabled = (string) ini_get('disable_functions');
        if ($disabled !== '' && stripos($disabled, 'imap') !== false) {
            $lines[] = 'disable_functions icinde imap var: ' . $disabled;
        }
        $scanned = (string) php_ini_scanned_files();
        if ($scanned !== '') {
            $lines[] = 'ek ini: ' . str_replace(["\n", "\r"], ' ', $scanned);
        }
        return implode(' | ', $lines);
    }
}

if (!function_exists('metropol_mail_imap_configured')) {
    /**
     * IMAP icin gerekli alanlarin dolu olup olmadigini ag baglantisi kurmadan kontrol eder.
     *
     * @param array<string,mixed> $settings
     */
    function metropol_mail_imap_configured(array $settings): bool
    {
        if (isset($settings['imap_enabled']) && (int) $settings['imap_enabled'] === 0) {
            return false;
        }
        $host = trim((string) ($settings['imap_host'] ?? '')) !== ''
            ? trim((string) $settings['imap_host'])
            : trim((string) ($settings['smtp_host'] ?? ''));
        $user = trim((string) ($settings['imap_user'] ?? '')) !== ''
            ? trim((string) $settings['imap_user'])
            : trim((string) ($settings['smtp_user'] ?? ''));
        $pass = (string) ($settings['imap_password'] ?? '') !== ''
            ? (string) $settings['imap_password']
            : (string) ($settings['smtp_password'] ?? '');

        return $host !== '' && $user !== '' && $pass !== '';
    }
}

if (!function_exists('metropol_mail_imap_apply_timeouts')) {
    /**
     * IMAP islemlerine ust sinir koyar; aksi halde erisilemeyen bir sunucu
     * istegi PHP-FPM zaman asimina kadar bloklar ve Apache 503 dondurur.
     */
    function metropol_mail_imap_apply_timeouts(int $seconds = 6): void
    {
        if (!function_exists('imap_timeout')) {
            return;
        }
        $seconds = max(2, $seconds);
        @imap_timeout(IMAP_OPENTIMEOUT, $seconds);
        @imap_timeout(IMAP_READTIMEOUT, $seconds);
        @imap_timeout(IMAP_WRITETIMEOUT, $seconds);
        @imap_timeout(IMAP_CLOSETIMEOUT, $seconds);
    }
}

if (!function_exists('metropol_mail_imap_port_open')) {
    /** IMAP portu erisilebilir mi? imap_open'dan once hizli TCP kontrolu. */
    function metropol_mail_imap_port_open(string $host, int $port, float $timeout = 4.0): bool
    {
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($socket === false) {
            return false;
        }
        @fclose($socket);

        return true;
    }
}

if (!function_exists('metropol_mail_fetch_inbox')) {
    /**
     * @param array<string,mixed> $settings mail_settings satırı
     * @return array{ok:bool,error:string,messages:list<array<string,mixed>>}
     */
    function metropol_mail_fetch_inbox(array $settings, int $limit = 40, float $previewBudget = 6.0): array
    {
        $conn = metropol_mail_imap_connect($settings);
        if ($conn['ok'] !== true || !isset($conn['inbox'])) {
            return [
                'ok' => false,
                'error' => (string) ($conn['error'] ?? 'IMAP bağlantısı kurulamadı.'),
                'messages' => [],
            ];
        }
        $inbox = $conn['inbox'];
        $user = (string) ($conn['user'] ?? '');

        try {
            $check = imap_check($inbox);
            $total = is_object($check) ? (int) ($check->Nmsgs ?? 0) : 0;
            if ($total <= 0) {
                return ['ok' => true, 'error' => '', 'messages' => []];
            }

            $limit = max(1, min(100, $limit));
            $start = max(1, $total - $limit + 1);
            $overview = imap_fetch_overview($inbox, $start . ':' . $total, 0);
            if (!is_array($overview)) {
                return ['ok' => true, 'error' => '', 'messages' => []];
            }

            usort($overview, static function ($a, $b): int {
                $da = strtotime((string) ($a->date ?? '')) ?: 0;
                $db = strtotime((string) ($b->date ?? '')) ?: 0;
                return $db <=> $da;
            });

            $messages = [];
            $previewDeadline = microtime(true) + max(0.0, $previewBudget);
            foreach ($overview as $item) {
                if (!is_object($item)) {
                    continue;
                }
                $msgNo = (int) ($item->msgno ?? 0);
                $uid = (int) ($item->uid ?? 0);
                if ($uid <= 0 && $msgNo > 0) {
                    $uid = (int) @imap_uid($inbox, $msgNo);
                }
                $subject = metropol_mail_imap_decode_mime((string) ($item->subject ?? '(konu yok)'));
                $from = metropol_mail_imap_decode_mime((string) ($item->from ?? ''));
                $date = (string) ($item->date ?? '');
                $preview = '';
                // Ozet icin her mesajin govdesi ayri IMAP turu gerektirir; butce
                // dolunca listeyi ozetsiz tamamlayip sayfayi acik tutuyoruz.
                if ($msgNo > 0 && microtime(true) < $previewDeadline) {
                    $parts = metropol_mail_imap_extract_bodies($inbox, $msgNo);
                    $previewSource = $parts['text'] !== '' ? $parts['text'] : strip_tags($parts['html']);
                    $preview = trim(preg_replace('/\s+/', ' ', $previewSource) ?? '');
                    if (function_exists('mb_substr')) {
                        $preview = mb_substr($preview, 0, 180, 'UTF-8');
                    } else {
                        $preview = substr($preview, 0, 180);
                    }
                }

                $messages[] = [
                    'uid' => $uid,
                    'msgno' => $msgNo,
                    'from' => $from,
                    'subject' => $subject,
                    'date' => $date,
                    'preview' => $preview,
                    'seen' => !empty($item->seen),
                    'mailbox' => $user,
                ];
            }

            return ['ok' => true, 'error' => '', 'messages' => $messages];
        } finally {
            @imap_close($inbox);
        }
    }
}

if (!function_exists('metropol_mail_imap_connect')) {
    /**
     * @param array<string,mixed> $settings
     * @return array{ok:bool,error:string,inbox?:resource|\IMAP\Connection,user?:string}
     */
    function metropol_mail_imap_connect(array $settings): array
    {
        if (!metropol_mail_imap_available()) {
            $disabled = (string) ini_get('disable_functions');
            $imapDisabled = extension_loaded('imap')
                && (
                    stripos($disabled, 'imap_open') !== false
                    || !function_exists('imap_open')
                );
            if ($imapDisabled) {
                return [
                    'ok' => false,
                    'error' => 'IMAP eklentisi yüklü ama imap_open disable_functions ile engellenmiş. '
                        . 'aaPanel → PHP 8.3 → Disable Function(s) listesinden imap_open silin. '
                        . 'Tanı: ' . metropol_mail_imap_diagnostics(),
                ];
            }
            return [
                'ok' => false,
                'error' => 'PHP imap eklentisi yok. Tanı: ' . metropol_mail_imap_diagnostics(),
            ];
        }
        if (isset($settings['imap_enabled']) && (int) $settings['imap_enabled'] === 0) {
            return ['ok' => false, 'error' => 'IMAP gelen kutusu pasif.'];
        }

        $host = trim((string) ($settings['imap_host'] ?? ''));
        if ($host === '') {
            $host = trim((string) ($settings['smtp_host'] ?? ''));
        }
        $port = (int) ($settings['imap_port'] ?? 0);
        if ($port <= 0) {
            $port = 993;
        }
        $user = trim((string) ($settings['imap_user'] ?? ''));
        if ($user === '') {
            $user = trim((string) ($settings['smtp_user'] ?? ''));
        }
        if ($user === '') {
            $user = trim((string) ($settings['from_email'] ?? $settings['mail_from_address'] ?? ''));
        }
        $pass = (string) ($settings['imap_password'] ?? '');
        if ($pass === '') {
            $pass = (string) ($settings['smtp_password'] ?? '');
        }
        $encryption = strtolower(trim((string) ($settings['imap_encryption'] ?? 'ssl')));
        if ($encryption === '') {
            $encryption = 'ssl';
        }
        if ($host === '' || $user === '' || $pass === '') {
            return ['ok' => false, 'error' => 'IMAP Host/Kullanıcı/Şifre eksik.'];
        }

        $host = preg_replace('/^(ssl|tls):\/\//i', '', $host) ?: $host;
        if ($encryption === 'ssl') {
            $flags = '/imap/ssl/novalidate-cert';
        } elseif ($encryption === 'tls') {
            $flags = '/imap/tls/novalidate-cert';
        } else {
            $flags = '/imap/notls';
        }
        metropol_mail_imap_apply_timeouts(6);
        if (!metropol_mail_imap_port_open($host, $port, 4.0)) {
            return [
                'ok' => false,
                'error' => 'IMAP sunucusuna ulasilamadi (' . $host . ':' . $port . ').'
                    . ' Host/port bilgisini ve sunucunun giden IMAP baglantilarina izin verdigini kontrol edin.',
            ];
        }

        $mailbox = '{' . $host . ':' . $port . $flags . '}INBOX';
        $inbox = @imap_open($mailbox, $user, $pass, 0, 1);
        if ($inbox === false) {
            $err = trim((string) imap_last_error());
            return [
                'ok' => false,
                'error' => 'IMAP bağlantısı kurulamadı' . ($err !== '' ? ': ' . $err : '.')
                    . ' Host=' . $host . ' User=' . $user . ' Port=' . $port,
            ];
        }

        return ['ok' => true, 'error' => '', 'inbox' => $inbox, 'user' => $user];
    }
}

if (!function_exists('metropol_mail_fetch_message')) {
    /**
     * @param array<string,mixed> $settings
     * @return array{ok:bool,error:string,message?:array<string,mixed>}
     */
    function metropol_mail_fetch_message(array $settings, int $uid): array
    {
        if ($uid <= 0) {
            return ['ok' => false, 'error' => 'Geçersiz mesaj UID.'];
        }
        $conn = metropol_mail_imap_connect($settings);
        if ($conn['ok'] !== true || !isset($conn['inbox'])) {
            return ['ok' => false, 'error' => (string) ($conn['error'] ?? 'IMAP bağlantısı kurulamadı.')];
        }
        $inbox = $conn['inbox'];
        try {
            $msgNo = @imap_msgno($inbox, $uid);
            if ($msgNo <= 0) {
                return ['ok' => false, 'error' => 'Mesaj bulunamadı (UID=' . $uid . ').'];
            }
            $overview = @imap_fetch_overview($inbox, (string) $msgNo, 0);
            $item = is_array($overview) && isset($overview[0]) && is_object($overview[0]) ? $overview[0] : null;
            $parts = metropol_mail_imap_extract_bodies($inbox, $msgNo);
            @imap_setflag_full($inbox, (string) $msgNo, '\\Seen');

            return [
                'ok' => true,
                'error' => '',
                'message' => [
                    'uid' => $uid,
                    'msgno' => $msgNo,
                    'from' => metropol_mail_imap_decode_mime((string) ($item->from ?? '')),
                    'to' => metropol_mail_imap_decode_mime((string) ($item->to ?? '')),
                    'subject' => metropol_mail_imap_decode_mime((string) ($item->subject ?? '(konu yok)')),
                    'date' => (string) ($item->date ?? ''),
                    'text' => $parts['text'],
                    'html' => $parts['html'],
                    'mailbox' => (string) ($conn['user'] ?? ''),
                ],
            ];
        } finally {
            @imap_close($inbox);
        }
    }
}

if (!function_exists('metropol_mail_imap_decode_part')) {
    function metropol_mail_imap_decode_part(string $body, int $encoding): string
    {
        if ($encoding === 3) {
            $decoded = base64_decode($body, true);
            return is_string($decoded) ? $decoded : $body;
        }
        if ($encoding === 4) {
            return quoted_printable_decode($body);
        }
        return $body;
    }
}

if (!function_exists('metropol_mail_imap_extract_bodies')) {
    /**
     * @param resource|\IMAP\Connection $inbox
     * @return array{text:string,html:string}
     */
    function metropol_mail_imap_extract_bodies($inbox, int $msgNo): array
    {
        $text = '';
        $html = '';
        $structure = @imap_fetchstructure($inbox, $msgNo);
        if (!is_object($structure)) {
            $raw = (string) @imap_body($inbox, $msgNo);
            return ['text' => trim($raw), 'html' => ''];
        }

        $walk = static function ($struct, string $prefix) use (&$walk, &$text, &$html, $inbox, $msgNo): void {
            $type = (int) ($struct->type ?? 0);
            $subtype = strtoupper((string) ($struct->subtype ?? ''));
            $encoding = (int) ($struct->encoding ?? 0);
            $parts = is_array($struct->parts ?? null) ? $struct->parts : [];

            if ($parts !== []) {
                foreach ($parts as $i => $part) {
                    if (!is_object($part)) {
                        continue;
                    }
                    $partNo = $prefix === '' ? (string) ($i + 1) : ($prefix . '.' . ($i + 1));
                    $walk($part, $partNo);
                }
                return;
            }

            $partNo = $prefix !== '' ? $prefix : '1';
            $body = (string) @imap_fetchbody($inbox, $msgNo, $partNo);
            $body = metropol_mail_imap_decode_part($body, $encoding);

            $charset = 'UTF-8';
            if (isset($struct->parameters) && is_array($struct->parameters)) {
                foreach ($struct->parameters as $param) {
                    if (is_object($param) && strtolower((string) ($param->attribute ?? '')) === 'charset') {
                        $charset = strtoupper((string) ($param->value ?? 'UTF-8'));
                        break;
                    }
                }
            }
            if ($charset !== '' && $charset !== 'UTF-8' && $charset !== 'DEFAULT' && function_exists('mb_convert_encoding')) {
                $converted = @mb_convert_encoding($body, 'UTF-8', $charset);
                if (is_string($converted)) {
                    $body = $converted;
                }
            }

            if ($type === 0 && $subtype === 'HTML' && $html === '') {
                $html = $body;
            } elseif ($type === 0 && ($subtype === 'PLAIN' || $subtype === '') && $text === '') {
                $text = $body;
            } elseif ($type === 0 && $text === '' && $html === '') {
                $text = $body;
            }
        };

        $walk($structure, '');

        if ($text === '' && $html === '') {
            $raw = (string) @imap_body($inbox, $msgNo);
            $encoding = (int) ($structure->encoding ?? 0);
            $text = trim(metropol_mail_imap_decode_part($raw, $encoding));
        }

        return ['text' => trim($text), 'html' => trim($html)];
    }
}

if (!function_exists('metropol_mail_imap_decode_mime')) {
    function metropol_mail_imap_decode_mime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (!function_exists('imap_mime_header_decode')) {
            return $value;
        }
        $parts = @imap_mime_header_decode($value);
        if (!is_array($parts) || $parts === []) {
            return $value;
        }
        $out = '';
        foreach ($parts as $part) {
            $charset = strtoupper((string) ($part->charset ?? 'UTF-8'));
            $text = (string) ($part->text ?? '');
            if ($charset !== '' && $charset !== 'DEFAULT' && $charset !== 'UTF-8' && function_exists('mb_convert_encoding')) {
                $converted = @mb_convert_encoding($text, 'UTF-8', $charset);
                $out .= is_string($converted) ? $converted : $text;
            } else {
                $out .= $text;
            }
        }
        return trim($out);
    }
}
