<?php

declare(strict_types=1);

/**
 * Bağımsız SMTP gönderici — PHPMailer varsa onu, yoksa ham SMTP soketini kullanır.
 * Hem admin panel (test mail) hem üye API (şifre sıfırlama) tarafından paylaşılır.
 */

if (!function_exists('metropol_mail_open_basedir_allows')) {
    function metropol_mail_open_basedir_allows(string $path): bool
    {
        $openBaseDir = trim((string) ini_get('open_basedir'));
        if ($openBaseDir === '') {
            return true;
        }
        $normalizedPath = rtrim(str_replace('\\', '/', $path), '/');
        if ($normalizedPath === '') {
            return false;
        }
        foreach (preg_split('/[;:]/', $openBaseDir) ?: [] as $part) {
            $base = rtrim(str_replace('\\', '/', trim((string) $part)), '/');
            if ($base === '') {
                continue;
            }
            if ($normalizedPath === $base || str_starts_with($normalizedPath . '/', $base . '/')) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('metropol_mail_load_phpmailer')) {
    function metropol_mail_load_phpmailer(): bool
    {
        static $loaded = null;
        if ($loaded !== null) {
            return $loaded;
        }
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return $loaded = true;
        }

        $candidates = [];
        if (defined('ADMIN_BASE_PATH')) {
            $candidates[] = rtrim((string) ADMIN_BASE_PATH, '/\\') . '/vendor/autoload.php';
        }
        if (defined('BASE_PATH')) {
            $candidates[] = rtrim((string) BASE_PATH, '/\\') . '/vendor/autoload.php';
            $candidates[] = rtrim((string) BASE_PATH, '/\\') . '/admin/vendor/autoload.php';
        }
        if (defined('METROPOL_ROOT')) {
            $candidates[] = rtrim((string) METROPOL_ROOT, '/\\') . '/vendor/autoload.php';
        }
        $candidates[] = dirname(__DIR__, 3) . '/vendor/autoload.php';
        $candidates[] = dirname(__DIR__, 3) . '/admin/vendor/autoload.php';

        foreach (array_values(array_unique($candidates)) as $autoload) {
            if (!metropol_mail_open_basedir_allows($autoload)) {
                continue;
            }
            if (@is_file($autoload)) {
                require_once $autoload;
                if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
                    return $loaded = true;
                }
            }
        }
        return $loaded = false;
    }
}

if (!function_exists('metropol_mail_send_phpmailer')) {
    function metropol_mail_send_phpmailer(array $settings, string $from, string $to, string $subject, string $body, string &$error = '', ?string $htmlBody = null): bool
    {
        $host = trim((string) ($settings['smtp_host'] ?? ''));
        $port = (int) ($settings['smtp_port'] ?? 0);
        $user = trim((string) ($settings['smtp_user'] ?? ''));
        $pass = (string) ($settings['smtp_password'] ?? '');
        if ($host === '') {
            $error = 'smtp_host_missing';
            return false;
        }
        if (!metropol_mail_load_phpmailer()) {
            $error = 'phpmailer_not_loaded';
            return false;
        }
        if ($port <= 0) {
            $port = 465;
        }
        if ($from === '' && $user !== '' && filter_var($user, FILTER_VALIDATE_EMAIL) !== false) {
            $from = $user;
        }

        $ports = [$port];
        foreach ([465, 587, 2525] as $p) {
            if (!in_array($p, $ports, true)) {
                $ports[] = $p;
            }
        }

        $lastError = 'smtp_send_failed';
        foreach ($ports as $tryPort) {
            $strategies = $tryPort === 465
                ? [\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS, '']
                : [\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS, ''];
            foreach (array_values(array_unique($strategies)) as $secureMode) {
                foreach ([false, true] as $allowSelfSigned) {
                    try {
                        $debugLines = [];
                        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                        $mail->CharSet = 'UTF-8';
                        $mail->isSMTP();
                        $mail->Host = preg_replace('/^(ssl|tls):\/\//i', '', $host) ?: $host;
                        $mail->Port = $tryPort;
                        $mail->Timeout = 20;
                        $mail->SMTPAutoTLS = true;
                        $mail->SMTPDebug = 2;
                        $mail->Debugoutput = static function (string $line) use (&$debugLines): void {
                            if (count($debugLines) < 25) {
                                $debugLines[] = trim($line);
                            }
                        };
                        $mail->SMTPAuth = $user !== '';
                        if ($mail->SMTPAuth) {
                            $mail->AuthType = 'LOGIN';
                            $mail->Username = $user;
                            $mail->Password = $pass;
                        }
                        $mail->SMTPSecure = $secureMode;
                        if ($secureMode === '') {
                            $mail->SMTPAutoTLS = false;
                        }
                        if ($allowSelfSigned) {
                            $mail->SMTPOptions = ['ssl' => [
                                'verify_peer' => false,
                                'verify_peer_name' => false,
                                'allow_self_signed' => true,
                            ]];
                        }
                        $mail->setFrom($from, 'VegasRoyalSpin');
                        $mail->addAddress($to);
                        $mail->addReplyTo($from, 'VegasRoyalSpin');
                        $fromDomainForId = strpos($from, '@') !== false ? substr($from, strpos($from, '@') + 1) : 'vegasroyalspin.com';
                        $mail->MessageID = '<' . bin2hex(random_bytes(16)) . '@' . $fromDomainForId . '>';
                        $mail->Subject = $subject;
                        if ($htmlBody !== null) {
                            $mail->isHTML(true);
                            $mail->Body = $htmlBody;
                            $mail->AltBody = $body;
                        } else {
                            $mail->Body = $body;
                            $mail->AltBody = $body;
                        }
                        if ($mail->send()) {
                            return true;
                        }
                        $info = trim((string) $mail->ErrorInfo);
                        $tail = trim(implode(' | ', array_filter($debugLines)));
                        $lastError = sprintf(
                            'phpmailer(port=%d,secure=%s,self_signed=%s)%s%s',
                            $tryPort,
                            $secureMode !== '' ? $secureMode : 'none',
                            $allowSelfSigned ? '1' : '0',
                            $info !== '' ? ' ' . $info : '',
                            $tail !== '' ? ' :: ' . $tail : ''
                        );
                    } catch (Throwable $e) {
                        $lastError = 'phpmailer_exception(port=' . $tryPort . '): ' . trim($e->getMessage());
                    }
                }
            }
        }
        $error = $lastError;
        return false;
    }
}

if (!function_exists('metropol_mail_send_raw_smtp')) {
    function metropol_mail_send_raw_smtp(array $settings, string $from, string $to, string $subject, string $body, string &$error = '', ?string $htmlBody = null): bool
    {
        $host = trim((string) ($settings['smtp_host'] ?? ''));
        $port = (int) ($settings['smtp_port'] ?? 0);
        $user = trim((string) ($settings['smtp_user'] ?? ''));
        $pass = (string) ($settings['smtp_password'] ?? '');
        if ($host === '') {
            $error = 'smtp_host_missing';
            return false;
        }
        if ($port <= 0) {
            $port = 465;
        }
        if ($from === '' && $user !== '' && filter_var($user, FILTER_VALIDATE_EMAIL) !== false) {
            $from = $user;
        }

        $read = static function ($fp): string {
            $data = '';
            while (($line = fgets($fp, 515)) !== false) {
                $data .= $line;
                if (strlen($line) >= 4 && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $code = static fn (string $resp): int => (int) substr(trim($resp), 0, 3);

        $attempts = [];
        $attempts[] = [$port, ($port === 465) ? 'ssl' : 'starttls'];
        if ($port !== 465) {
            $attempts[] = [465, 'ssl'];
        }
        if ($port !== 587) {
            $attempts[] = [587, 'starttls'];
        }

        $lastError = 'raw_smtp_failed';
        foreach ($attempts as [$tryPort, $transport]) {
            foreach (['LOGIN', 'PLAIN'] as $authMethod) {
                $fp = null;
                try {
                    $context = stream_context_create(['ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ]]);
                    $remote = ($transport === 'ssl' ? 'ssl://' : '') . $host . ':' . $tryPort;
                    $errno = 0;
                    $errstr = '';
                    $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $context);
                    if (!$fp) {
                        $lastError = sprintf('connect_failed(port=%d,%s) %s', $tryPort, $transport, $errstr !== '' ? $errstr : (string) $errno);
                        continue;
                    }
                    stream_set_timeout($fp, 20);

                    $resp = $read($fp);
                    if ($code($resp) !== 220) {
                        $lastError = 'greeting_failed: ' . trim($resp);
                        fclose($fp);
                        continue;
                    }
                    $ehloHost = (string) (parse_url((string) (getenv('FRONTEND_URL') ?: getenv('SITE_URL') ?: ''), PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? 'localhost'));
                    $send = static function (string $cmd) use ($fp, $read): string {
                        fwrite($fp, $cmd . "\r\n");
                        return $read($fp);
                    };
                    $resp = $send('EHLO ' . $ehloHost);
                    if ($code($resp) !== 250) {
                        $lastError = 'ehlo_failed: ' . trim($resp);
                        fclose($fp);
                        continue;
                    }
                    $capabilities = $resp;
                    if ($transport === 'starttls') {
                        $resp = $send('STARTTLS');
                        if ($code($resp) !== 220) {
                            $lastError = 'starttls_failed: ' . trim($resp);
                            fclose($fp);
                            continue;
                        }
                        $crypto = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT);
                        if ($crypto !== true) {
                            $lastError = 'tls_handshake_failed';
                            fclose($fp);
                            continue;
                        }
                        $resp = $send('EHLO ' . $ehloHost);
                        if ($code($resp) !== 250) {
                            $lastError = 'ehlo2_failed: ' . trim($resp);
                            fclose($fp);
                            continue;
                        }
                        $capabilities = $resp;
                    }
                    $authLine = '';
                    foreach (preg_split('/\r\n/', trim($capabilities)) ?: [] as $capLine) {
                        if (stripos($capLine, 'AUTH') !== false) {
                            $authLine = trim($capLine);
                        }
                    }
                    if ($user !== '') {
                        if ($authMethod === 'PLAIN') {
                            $resp = $send('AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $pass));
                            if ($code($resp) !== 235) {
                                $lastError = 'auth_plain_failed: ' . trim($resp) . ($authLine !== '' ? ' [server: ' . $authLine . ']' : '');
                                fclose($fp);
                                continue;
                            }
                        } else {
                            $resp = $send('AUTH LOGIN');
                            if ($code($resp) !== 334) {
                                $lastError = 'auth_not_supported: ' . trim($resp) . ($authLine !== '' ? ' [server: ' . $authLine . ']' : '');
                                fclose($fp);
                                continue;
                            }
                            $resp = $send(base64_encode($user));
                            if ($code($resp) !== 334) {
                                $lastError = 'auth_user_rejected: ' . trim($resp) . ($authLine !== '' ? ' [server: ' . $authLine . ']' : '');
                                fclose($fp);
                                continue;
                            }
                            $resp = $send(base64_encode($pass));
                            if ($code($resp) !== 235) {
                                $lastError = 'auth_failed: ' . trim($resp) . ($authLine !== '' ? ' [server: ' . $authLine . ']' : '');
                                fclose($fp);
                                continue;
                            }
                        }
                    }
                    $resp = $send('MAIL FROM:<' . $from . '>');
                    if ((int) substr(trim($resp), 0, 1) !== 2) {
                        $lastError = 'mail_from_rejected: ' . trim($resp);
                        fclose($fp);
                        continue;
                    }
                    $resp = $send('RCPT TO:<' . $to . '>');
                    if ((int) substr(trim($resp), 0, 1) !== 2) {
                        $lastError = 'rcpt_rejected: ' . trim($resp);
                        fclose($fp);
                        continue;
                    }
                    $resp = $send('DATA');
                    if ($code($resp) !== 354) {
                        $lastError = 'data_rejected: ' . trim($resp);
                        fclose($fp);
                        continue;
                    }
                    $fromDomainForId = strpos($from, '@') !== false ? substr($from, strpos($from, '@') + 1) : 'vegasroyalspin.com';
                    $messageIdHeader = 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $fromDomainForId . '>';

                    if ($htmlBody !== null) {
                        $boundary = 'metropol-' . bin2hex(random_bytes(12));
                        $headers = [
                            'From: VegasRoyalSpin <' . $from . '>',
                            'To: <' . $to . '>',
                            'Reply-To: VegasRoyalSpin <' . $from . '>',
                            'Subject: ' . $subject,
                            'MIME-Version: 1.0',
                            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
                            'Date: ' . date('r'),
                            $messageIdHeader,
                        ];
                        $plainPart = str_replace("\n.", "\n..", str_replace(["\r\n", "\n"], "\r\n", $body));
                        $htmlPart = str_replace("\n.", "\n..", str_replace(["\r\n", "\n"], "\r\n", $htmlBody));
                        $mime = "--{$boundary}\r\n"
                            . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
                            . $plainPart . "\r\n"
                            . "--{$boundary}\r\n"
                            . "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n"
                            . $htmlPart . "\r\n"
                            . "--{$boundary}--";
                        fwrite($fp, implode("\r\n", $headers) . "\r\n\r\n" . $mime . "\r\n.\r\n");
                    } else {
                        $headers = [
                            'From: VegasRoyalSpin <' . $from . '>',
                            'To: <' . $to . '>',
                            'Reply-To: VegasRoyalSpin <' . $from . '>',
                            'Subject: ' . $subject,
                            'MIME-Version: 1.0',
                            'Content-Type: text/plain; charset=UTF-8',
                            'Content-Transfer-Encoding: 8bit',
                            'Date: ' . date('r'),
                            $messageIdHeader,
                        ];
                        $data = str_replace("\n.", "\n..", str_replace(["\r\n", "\n"], "\r\n", $body));
                        fwrite($fp, implode("\r\n", $headers) . "\r\n\r\n" . $data . "\r\n.\r\n");
                    }
                    $resp = $read($fp);
                    if ((int) substr(trim($resp), 0, 1) !== 2) {
                        $lastError = 'data_send_rejected: ' . trim($resp);
                        fclose($fp);
                        continue;
                    }
                    @fwrite($fp, "QUIT\r\n");
                    fclose($fp);
                    return true;
                } catch (Throwable $e) {
                    $lastError = 'raw_smtp_exception(port=' . $tryPort . ',' . $transport . ',' . $authMethod . '): ' . trim($e->getMessage());
                    if (is_resource($fp)) {
                        @fclose($fp);
                    }
                }
                if ($user === '') {
                    // No auth configured; no point trying a second auth method.
                    break;
                }
            }
        }
        $error = $lastError;
        return false;
    }
}

if (!function_exists('metropol_mail_send')) {
    /**
     * PHPMailer önce, ham SMTP fallback. İkisi de başarısızsa false; $error birleşik neden.
     *
     * @param array<string,mixed> $settings mail_settings satırı
     */
    function metropol_mail_send(array $settings, string $from, string $to, string $subject, string $body, string &$error = '', ?string $htmlBody = null): bool
    {
        $phpmailerError = '';
        if (metropol_mail_send_phpmailer($settings, $from, $to, $subject, $body, $phpmailerError, $htmlBody)) {
            return true;
        }
        $rawError = '';
        if (metropol_mail_send_raw_smtp($settings, $from, $to, $subject, $body, $rawError, $htmlBody)) {
            return true;
        }
        $error = 'phpmailer=' . ($phpmailerError !== '' ? $phpmailerError : 'n/a')
            . ' | raw=' . ($rawError !== '' ? $rawError : 'n/a');
        return false;
    }
}

if (!function_exists('metropol_mail_render_template')) {
    /**
     * Reset maili icin referans tasarima yakin, e-posta istemcileriyle uyumlu
     * (tablo tabanli, inline stil) HTML sablon.
     *
     * $options ile admin panelinden duzenlenebilir alanlar desteklenir:
     * - template_html (placeholder destekli ozel HTML)
     * - company_name
     * - support_email
     * - company_address
     * - logo_url
     */
    function metropol_mail_render_template(
        string $siteUrl,
        string $preheader,
        string $heading,
        string $bodyHtml,
        ?string $ctaLabel = null,
        ?string $ctaUrl = null,
        ?array $options = null
    ): string {
        $options = is_array($options) ? $options : [];
        $siteUrl = rtrim($siteUrl, '/');
        $companyName = trim((string) ($options['company_name'] ?? ''));
        if ($companyName === '') {
            $companyName = 'Company';
        }

        $supportEmail = trim((string) ($options['support_email'] ?? ''));
        if ($supportEmail === '' || filter_var($supportEmail, FILTER_VALIDATE_EMAIL) === false) {
            $host = (string) (parse_url($siteUrl, PHP_URL_HOST) ?: 'vegasroyalspin.com');
            $supportEmail = 'support@' . $host;
        }

        $companyAddress = trim((string) ($options['company_address'] ?? ''));
        if ($companyAddress === '') {
            $companyAddress = "1234 Street Rd.\nSuite 1234\nCity, State, ZIP Code";
        }

        $logoUrl = trim((string) ($options['logo_url'] ?? ''));

        $ctaLabel = $ctaLabel !== null && trim($ctaLabel) !== '' ? $ctaLabel : 'Reset your password';
        $ctaUrl = $ctaUrl !== null && trim($ctaUrl) !== '' ? trim($ctaUrl) : '#';

        $greetingLine = '<h1 style="margin:0 0 24px 0;font-size:52px;line-height:1.1;color:#10131a;font-weight:800;">' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</h1>';
        if (stripos($heading, 'hi ') === 0 || stripos($heading, 'merhaba') === 0) {
            $greetingLine = '<h1 style="margin:0 0 24px 0;font-size:52px;line-height:1.1;color:#10131a;font-weight:800;">' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</h1>';
        }

        $logoHtml = '';
        if ($logoUrl !== '') {
            $logoHtml = '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') . '" width="180" style="display:block;max-width:180px;height:auto;border:0;outline:none;text-decoration:none;">';
        } else {
            $logoHtml = '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
                . '<td style="padding-right:10px;vertical-align:middle;">'
                . '<span style="display:inline-block;width:10px;height:10px;background:#1553d6;border-radius:1px;transform:rotate(45deg);"></span>'
                . '<span style="display:inline-block;width:10px;height:10px;background:#1553d6;border-radius:1px;transform:rotate(45deg);margin-left:4px;"></span><br>'
                . '<span style="display:inline-block;width:10px;height:10px;background:#1553d6;border-radius:1px;transform:rotate(45deg);margin-top:4px;"></span>'
                . '<span style="display:inline-block;width:10px;height:10px;background:#1553d6;border-radius:1px;transform:rotate(45deg);margin-left:4px;margin-top:4px;"></span>'
                . '</td>'
                . '<td style="vertical-align:middle;font-family:Arial,Helvetica,sans-serif;font-size:24px;line-height:1;color:#10131a;font-weight:700;">' . htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr></table>';
        }

        $addressHtml = nl2br(htmlspecialchars($companyAddress, ENT_QUOTES, 'UTF-8'));
        $year = date('Y');

        $customTemplate = trim((string) ($options['template_html'] ?? ''));
        if ($customTemplate !== '') {
            $tokens = [
                '{{PREHEADER}}' => htmlspecialchars($preheader, ENT_QUOTES, 'UTF-8'),
                '{{HEADING}}' => htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'),
                '{{BODY_HTML}}' => $bodyHtml,
                '{{CTA_LABEL}}' => htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8'),
                '{{CTA_URL}}' => htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8'),
                '{{COMPANY_NAME}}' => htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8'),
                '{{SUPPORT_EMAIL}}' => htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8'),
                '{{SUPPORT_EMAIL_LINK}}' => 'mailto:' . htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8'),
                '{{YEAR}}' => $year,
                '{{COMPANY_ADDRESS_HTML}}' => $addressHtml,
                '{{LOGO_HTML}}' => $logoHtml,
                '{{FALLBACK_URL}}' => htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8'),
            ];
            return strtr($customTemplate, $tokens);
        }

        return '<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</title>
</head>
<body style="margin:0;padding:0;background-color:#dce0e6;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . htmlspecialchars($preheader, ENT_QUOTES, 'UTF-8') . '</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#dce0e6;padding:46px 18px;">
<tr>
<td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:760px;background-color:#f7f8fa;border-radius:12px;overflow:hidden;border:1px solid #d6dbe3;">
    <tr>
        <td align="center" bgcolor="#f7f8fa" style="padding:50px 32px 34px 32px;background-color:#f7f8fa;border-radius:4px 4px 0 0;">
            ' . $logoHtml . '
        </td>
    </tr>
    <tr>
        <td style="padding:0 58px 0 58px;font-family:Arial,Helvetica,sans-serif;">
            ' . $greetingLine . '
            <div style="font-size:16px;line-height:1.7;color:#4a5568;font-weight:400;">' . $bodyHtml . '</div>
        </td>
    <tr>
        <td align="center" style="padding:36px 58px 44px 58px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td align="center" bgcolor="#1553d6" style="border-radius:8px;box-shadow:0 4px 10px rgba(0,0,0,.12);">
                        <a href="' . htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank" style="display:inline-block;padding:18px 42px;font-family:Arial,Helvetica,sans-serif;font-size:20px;line-height:1;font-weight:700;color:#ffffff;text-decoration:none;border-radius:8px;background-color:#1553d6;">' . htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8') . '</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding:0 58px 42px 58px;font-family:Arial,Helvetica,sans-serif;">
            <p style="margin:0 0 18px 0;font-size:16px;line-height:1.7;color:#4a5568;">If you have any questions about this invoice, simply reply to this email or reach out to our <a href="mailto:' . htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') . '" style="color:#1553d6;text-decoration:underline;">support team</a> for help.</p>
            <p style="margin:0 0 18px 0;font-size:16px;line-height:1.7;color:#4a5568;">Cheers,<br>The ' . htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') . ' Team</p>
            <hr style="border:none;border-top:1px solid #d7dde6;margin:42px 0 34px 0;">
            <p style="margin:0 0 14px 0;font-size:15px;line-height:1.7;color:#4a5568;">If you are having trouble with the button above, copy and paste the URL below into your web browser.</p>
            <p style="margin:0;font-size:14px;line-height:1.7;color:#4a5568;word-break:break-all;">' . htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8') . '</p>
        </td>
    </tr>
    <tr>
        <td align="center" style="padding:36px 24px 44px 24px;background-color:#dce0e6;">
            <p style="margin:0 0 12px 0;font-size:13px;line-height:1.6;color:#8a97aa;font-family:Arial,Helvetica,sans-serif;">&copy; ' . $year . ' ' . htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') . '. All rights reserved.</p>
            <p style="margin:0;font-size:13px;line-height:1.65;color:#8a97aa;font-family:Arial,Helvetica,sans-serif;">' . $addressHtml . '</p>
        </td>
    </tr>
</table>
</td>
</tr>
</table>
</body>
</html>';
    }
}
