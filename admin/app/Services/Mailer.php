<?php

declare(strict_types=1);

/**
 * Bağımsız SMTP gönderici — PHPMailer varsa onu, yoksa ham SMTP soketini kullanır.
 * Hem admin panel (test mail) hem üye API (şifre sıfırlama) tarafından paylaşılır.
 */

if (!function_exists('mail_open_basedir_allows')) {
    function mail_open_basedir_allows(string $path): bool
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

if (!function_exists('mail_load_phpmailer')) {
    function mail_load_phpmailer(): bool
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
        if (defined('APP_ROOT')) {
            $candidates[] = rtrim((string) APP_ROOT, '/\\') . '/vendor/autoload.php';
        }
        $candidates[] = dirname(__DIR__, 3) . '/vendor/autoload.php';
        $candidates[] = dirname(__DIR__, 3) . '/admin/vendor/autoload.php';

        foreach (array_values(array_unique($candidates)) as $autoload) {
            if (!mail_open_basedir_allows($autoload)) {
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

if (!function_exists('mail_send_phpmailer')) {
    function mail_send_phpmailer(array $settings, string $from, string $to, string $subject, string $body, string &$error = '', ?string $htmlBody = null, string $toName = ''): bool
    {
        $host = trim((string) ($settings['smtp_host'] ?? ''));
        $port = (int) ($settings['smtp_port'] ?? 0);
        $user = trim((string) ($settings['smtp_user'] ?? ''));
        $pass = (string) ($settings['smtp_password'] ?? '');
        if ($host === '') {
            $error = 'smtp_host_missing';
            return false;
        }
        if (!mail_load_phpmailer()) {
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
                        $mail->setFrom($from, 'Vegasroyalspin');
                        $mail->addAddress($to, trim($toName));
                        $mail->addReplyTo($from, 'Vegasroyalspin');
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

if (!function_exists('mail_send_raw_smtp')) {
    function mail_send_raw_smtp(array $settings, string $from, string $to, string $subject, string $body, string &$error = '', ?string $htmlBody = null, string $toName = ''): bool
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
                    $toHeader = mail_format_address_header($to, $toName);

                    if ($htmlBody !== null) {
                        $boundary = 'metropol-' . bin2hex(random_bytes(12));
                        $headers = [
                            'From: Vegasroyalspin <' . $from . '>',
                            'To: ' . $toHeader,
                            'Reply-To: Vegasroyalspin <' . $from . '>',
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
                            'From: Vegasroyalspin <' . $from . '>',
                            'To: ' . $toHeader,
                            'Reply-To: Vegasroyalspin <' . $from . '>',
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

if (!function_exists('mail_format_address_header')) {
    function mail_format_address_header(string $email, string $name = ''): string
    {
        $email = trim($email);
        $name = trim(preg_replace('/[\r\n]+/', ' ', $name) ?? $name);
        if ($name === '') {
            return '<' . $email . '>';
        }
        if (preg_match('/^[\x20-\x7E]+$/', $name) === 1) {
            $safe = str_replace(['\\', '"'], ['\\\\', '\\"'], $name);
            return '"' . $safe . '" <' . $email . '>';
        }

        return '=?UTF-8?B?' . base64_encode($name) . '?= <' . $email . '>';
    }
}

if (!function_exists('mail_send')) {
    /**
     * PHPMailer önce, ham SMTP fallback. İkisi de başarısızsa false; $error birleşik neden.
     *
     * @param array<string,mixed> $settings mail_settings satırı
     */
    function mail_send(array $settings, string $from, string $to, string $subject, string $body, string &$error = '', ?string $htmlBody = null, string $toName = ''): bool
    {
        $phpmailerError = '';
        if (mail_send_phpmailer($settings, $from, $to, $subject, $body, $phpmailerError, $htmlBody, $toName)) {
            return true;
        }
        $rawError = '';
        if (mail_send_raw_smtp($settings, $from, $to, $subject, $body, $rawError, $htmlBody, $toName)) {
            return true;
        }
        $error = 'phpmailer=' . ($phpmailerError !== '' ? $phpmailerError : 'n/a')
            . ' | raw=' . ($rawError !== '' ? $rawError : 'n/a');
        return false;
    }
}

if (!function_exists('mail_logo_display_dimensions')) {
    /**
     * E-posta logosu için oran koruyan width/height + inline style.
     * Geniş wordmark logoları 64x64'e sıkıştırılmaz.
     *
     * @param array<string,mixed> $options
     * @return array{width:int,height:int,style:string}
     */
    function mail_logo_display_dimensions(string $logoUrl, array $options = []): array
    {
        $forcedW = (int) ($options['logo_width'] ?? 0);
        $forcedH = (int) ($options['logo_height'] ?? 0);
        $naturalW = 0;
        $naturalH = 0;

        $path = (string) (parse_url($logoUrl, PHP_URL_PATH) ?: '');
        $basename = $path !== '' ? basename($path) : '';
        $localCandidates = [];
        if ($basename !== '') {
            $roots = [];
            if (defined('BASE_PATH')) {
                $roots[] = rtrim((string) BASE_PATH, '/\\');
            }
            if (defined('ADMIN_BASE_PATH')) {
                $roots[] = dirname(rtrim((string) ADMIN_BASE_PATH, '/\\'));
            }
            $roots[] = '/www/wwwroot/vegasroyalspin.com';
            $roots[] = dirname(__DIR__, 3);
            foreach (array_values(array_unique($roots)) as $root) {
                $localCandidates[] = $root . '/assets/images/favicons/' . $basename;
                $localCandidates[] = $root . '/public/assets/images/favicons/' . $basename;
            }
        }
        foreach ($localCandidates as $candidate) {
            if (!is_file($candidate)) {
                continue;
            }
            $info = @getimagesize($candidate);
            if (is_array($info) && (int) ($info[0] ?? 0) > 0 && (int) ($info[1] ?? 0) > 0) {
                $naturalW = (int) $info[0];
                $naturalH = (int) $info[1];
                break;
            }
        }

        // Bilinen marka wordmark'ı (1344x206)
        if ($naturalW <= 0 && str_contains($logoUrl, '_brand-logo-src.png')) {
            $naturalW = 1344;
            $naturalH = 206;
        }

        if ($forcedW > 0 && $forcedH > 0) {
            $width = max(16, min(480, $forcedW));
            $height = max(16, min(240, $forcedH));
        } elseif ($naturalW > 0 && $naturalH > 0 && $naturalW > (int) round($naturalH * 1.4)) {
            // Geniş logo: mail kartına sığacak wordmark
            $width = 220;
            $height = max(24, (int) round($width * ($naturalH / $naturalW)));
        } elseif ($naturalW > 0 && $naturalH > 0) {
            $width = 72;
            $height = max(24, (int) round($width * ($naturalH / $naturalW)));
        } else {
            $width = 64;
            $height = 64;
        }

        $isWide = $width > (int) round($height * 1.4);
        $style = $isWide
            ? sprintf(
                'display:block;width:%dpx;max-width:88%%;height:auto;border:0;outline:none;border-radius:0;',
                $width
            )
            : sprintf(
                'display:block;width:%dpx;height:%dpx;border:0;outline:none;border-radius:16px;',
                $width,
                $height
            );

        return [
            'width' => $width,
            'height' => $height,
            'style' => $style,
        ];
    }
}

if (!function_exists('mail_render_template')) {
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
    function mail_render_template(
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
            $companyName = 'Vegasroyalspin';
        }

        $supportEmail = trim((string) ($options['support_email'] ?? ''));
        if ($supportEmail === '' || filter_var($supportEmail, FILTER_VALIDATE_EMAIL) === false) {
            $host = (string) (parse_url($siteUrl, PHP_URL_HOST) ?: 'vegasroyalspin.com');
            $supportEmail = 'support@' . $host;
        }

        $companyAddress = trim((string) ($options['company_address'] ?? ''));
        if ($companyAddress === '') {
            $companyAddress = "vegasroyalspin.com";
        }

        $logoUrl = trim((string) ($options['logo_url'] ?? ''));
        if ($logoUrl === '' && $siteUrl !== '') {
            $logoUrl = $siteUrl . '/assets/images/favicons/apple-touch-icon.png';
        }

        $memberName = trim((string) ($options['member_name'] ?? ''));

        $ctaLabel = $ctaLabel !== null && trim($ctaLabel) !== '' ? $ctaLabel : 'Şifremi Sıfırla';
        $ctaUrl = $ctaUrl !== null && trim($ctaUrl) !== '' ? trim($ctaUrl) : '#';

        $safeCompany = htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8');
        $safeMember = htmlspecialchars($memberName, ENT_QUOTES, 'UTF-8');
        $safeHeading = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
        $safePreheader = htmlspecialchars($preheader, ENT_QUOTES, 'UTF-8');
        $safeCtaLabel = htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8');
        $safeCtaUrl = htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8');
        $safeSupport = htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8');
        $safeSite = htmlspecialchars($siteUrl !== '' ? $siteUrl : '#', ENT_QUOTES, 'UTF-8');
        $safeLogo = htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8');

        $logoHtml = '';
        if ($logoUrl !== '') {
            $logoDims = mail_logo_display_dimensions($logoUrl, $options);
            $logoHtml = '<a href="' . $safeSite . '" target="_blank" style="text-decoration:none;display:inline-block;max-width:100%;">'
                . '<img src="' . $safeLogo . '" alt="' . $safeCompany . '"'
                . ' width="' . $logoDims['width'] . '" height="' . $logoDims['height'] . '"'
                . ' style="' . $logoDims['style'] . '">'
                . '</a>';
        }

        $addressHtml = nl2br(htmlspecialchars($companyAddress, ENT_QUOTES, 'UTF-8'));
        $year = date('Y');

        $customTemplate = trim((string) ($options['template_html'] ?? ''));
        if ($customTemplate !== '') {
            $safeAmount = htmlspecialchars(trim((string) ($options['amount'] ?? '')), ENT_QUOTES, 'UTF-8');
            $tokens = [
                '{{PREHEADER}}' => $safePreheader,
                '{{HEADING}}' => $safeHeading,
                '{{BODY_HTML}}' => $bodyHtml,
                '{{CTA_LABEL}}' => $safeCtaLabel,
                '{{CTA_URL}}' => $safeCtaUrl,
                '{{COMPANY_NAME}}' => $safeCompany,
                '{{MEMBER_NAME}}' => $safeMember,
                '{{AMOUNT}}' => $safeAmount,
                '{{SUPPORT_EMAIL}}' => $safeSupport,
                '{{SUPPORT_EMAIL_LINK}}' => 'mailto:' . $safeSupport,
                '{{YEAR}}' => $year,
                '{{COMPANY_ADDRESS_HTML}}' => $addressHtml,
                '{{LOGO_HTML}}' => $logoHtml,
                '{{FALLBACK_URL}}' => $safeCtaUrl,
            ];
            return strtr($customTemplate, $tokens);
        }

        return '<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="x-apple-disable-message-reformatting">
<title>' . $safeCompany . ' — ' . $safeHeading . '</title>
<style type="text/css">
@media only screen and (max-width:620px){
  .vrs-wrap{padding:16px 10px !important;}
  .vrs-card{width:100% !important;max-width:100% !important;border-radius:14px !important;}
  .vrs-pad{padding-left:18px !important;padding-right:18px !important;}
  .vrs-title{font-size:22px !important;line-height:1.3 !important;}
  .vrs-brand{font-size:22px !important;}
  .vrs-btn{display:block !important;width:100% !important;box-sizing:border-box !important;text-align:center !important;padding:16px 18px !important;}
  .vrs-btn-td{width:100% !important;}
}
</style>
</head>
<body style="margin:0;padding:0;background-color:#0a0719;-webkit-text-size-adjust:100%;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . $safePreheader . '</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="vrs-wrap" style="background-color:#0a0719;padding:28px 14px;">
<tr>
<td align="center">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="vrs-card" style="max-width:560px;width:100%;background-color:#12082f;border-radius:18px;overflow:hidden;border:1px solid #6b2a78;">
    <tr>
        <td style="height:4px;background-color:#850f83;font-size:0;line-height:0;">&nbsp;</td>
    </tr>
    <tr>
        <td align="center" class="vrs-pad" style="padding:28px 22px 14px 22px;">
            ' . $logoHtml . '
            <div style="margin-top:10px;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#c9b3e6;">Güvenli hesap erişimi</div>
        </td>
    </tr>
    <tr>
        <td class="vrs-pad" style="padding:10px 22px 0 22px;font-family:Arial,Helvetica,sans-serif;">
            <p style="margin:0 0 10px 0;font-size:13px;letter-spacing:.03em;text-transform:uppercase;color:#c44bb8;font-weight:700;">Merhaba ' . $safeMember . '</p>
            <h1 class="vrs-title" style="margin:0 0 14px 0;font-size:26px;line-height:1.3;color:#ffffff;font-weight:800;">' . $safeHeading . '</h1>
            <div style="font-size:15px;line-height:1.7;color:#dcccf3;">' . $bodyHtml . '</div>
        </td>
    </tr>
    <tr>
        <td align="center" class="vrs-pad" style="padding:24px 22px 10px 22px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td align="center" class="vrs-btn-td" bgcolor="#850f83" style="border-radius:12px;background-color:#850f83;">
                        <a class="vrs-btn" href="' . $safeCtaUrl . '" target="_blank" style="display:inline-block;padding:15px 28px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.2;font-weight:700;color:#ffffff;text-decoration:none;border-radius:12px;background-color:#850f83;">' . $safeCtaLabel . '</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td class="vrs-pad" style="padding:18px 22px 28px 22px;font-family:Arial,Helvetica,sans-serif;">
            <p style="margin:0 0 12px 0;font-size:13px;line-height:1.7;color:#b9a3d6;">Sorunuz olursa bu e-postaya yanıt verin veya <a href="mailto:' . $safeSupport . '" style="color:#c44bb8;text-decoration:underline;">destek ekibimize</a> yazın.</p>
            <p style="margin:0 0 16px 0;font-size:13px;line-height:1.7;color:#dcccf3;">Saygılarımızla,<br><strong style="color:#ffffff;">' . $safeCompany . ' Ekibi</strong></p>
            <hr style="border:none;border-top:1px solid #4a2a63;margin:18px 0;">
            <p style="margin:0 0 6px 0;font-size:12px;line-height:1.6;color:#9b86b8;">Buton çalışmazsa bağlantıyı tarayıcınıza yapıştırın:</p>
            <p style="margin:0;font-size:12px;line-height:1.6;color:#b9a3d6;word-break:break-all;">' . $safeCtaUrl . '</p>
        </td>
    </tr>
    <tr>
        <td align="center" style="padding:16px 18px 22px 18px;background-color:#0a0618;">
            <p style="margin:0 0 6px 0;font-size:11px;line-height:1.6;color:#8f7aa8;font-family:Arial,Helvetica,sans-serif;">&copy; ' . $year . ' ' . $safeCompany . '. Tüm hakları saklıdır.</p>
            <p style="margin:0;font-size:11px;line-height:1.6;color:#8f7aa8;font-family:Arial,Helvetica,sans-serif;">' . $addressHtml . '</p>
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
