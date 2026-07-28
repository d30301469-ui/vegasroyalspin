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

if (!function_exists('metropol_mail_fetch_inbox')) {
    /**
     * @param array<string,mixed> $settings mail_settings satırı
     * @return array{ok:bool,error:string,messages:list<array<string,mixed>>}
     */
    function metropol_mail_fetch_inbox(array $settings, int $limit = 40): array
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
                        . 'aaPanel → App Store → PHP 8.3 → Settings → Disable Function(s) listesinden '
                        . 'imap_open satırını sil → Save → PHP-FPM 83 Restart. '
                        . 'Tanı: ' . metropol_mail_imap_diagnostics(),
                    'messages' => [],
                ];
            }
            return [
                'ok' => false,
                'error' => 'PHP imap eklentisi bu site PHP sürecinde yok. '
                    . 'aaPanel > App Store > PHP (sitenin sürümü) > Settings > Install extensions > imap. '
                    . 'Sonra o sürüme ait PHP-FPM restart. Tanı: ' . metropol_mail_imap_diagnostics(),
                'messages' => [],
            ];
        }

        if (isset($settings['imap_enabled']) && (int) $settings['imap_enabled'] === 0) {
            return [
                'ok' => false,
                'error' => 'IMAP gelen kutusu pasif. E-posta → Ayarlar bölümünden IMAP’i aktif edin.',
                'messages' => [],
            ];
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
            return [
                'ok' => false,
                'error' => 'IMAP Host, Kullanıcı ve Şifre gerekli. E-posta → Ayarlar → Gelen kutusu (IMAP) alanlarını doldurun.',
                'messages' => [],
            ];
        }

        $host = preg_replace('/^(ssl|tls):\/\//i', '', $host) ?: $host;
        if ($encryption === 'ssl') {
            $flags = '/imap/ssl/novalidate-cert';
        } elseif ($encryption === 'tls') {
            $flags = '/imap/tls/novalidate-cert';
        } else {
            $flags = '/imap/notls';
        }
        $mailbox = '{' . $host . ':' . $port . $flags . '}INBOX';

        $inbox = @imap_open($mailbox, $user, $pass, 0, 1);
        if ($inbox === false) {
            $err = trim((string) imap_last_error());
            return [
                'ok' => false,
                'error' => 'IMAP bağlantısı kurulamadı' . ($err !== '' ? ': ' . $err : '.')
                    . ' Host=' . $host . ' User=' . $user . ' Port=' . $port . ' Enc=' . $encryption,
                'messages' => [],
            ];
        }

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
            foreach ($overview as $item) {
                if (!is_object($item)) {
                    continue;
                }
                $msgNo = (int) ($item->msgno ?? 0);
                $uid = (int) ($item->uid ?? 0);
                $subject = metropol_mail_imap_decode_mime((string) ($item->subject ?? '(konu yok)'));
                $from = metropol_mail_imap_decode_mime((string) ($item->from ?? ''));
                $date = (string) ($item->date ?? '');
                $preview = '';
                if ($msgNo > 0) {
                    $body = (string) @imap_fetchbody($inbox, $msgNo, '1');
                    if ($body === '') {
                        $body = (string) @imap_body($inbox, $msgNo);
                    }
                    $structure = @imap_fetchstructure($inbox, $msgNo);
                    if (is_object($structure) && (int) ($structure->encoding ?? 0) === 3) {
                        $decoded = base64_decode($body, true);
                        if (is_string($decoded)) {
                            $body = $decoded;
                        }
                    } elseif (is_object($structure) && (int) ($structure->encoding ?? 0) === 4) {
                        $body = quoted_printable_decode($body);
                    }
                    $preview = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '');
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
