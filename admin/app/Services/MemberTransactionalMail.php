<?php

declare(strict_types=1);

/**
 * Onaylı para yatırma / çekme işlemleri için üye e-postası.
 * MegaPayz callback ve diğer ödeme akışlarından güvenle require edilebilir.
 */
final class MemberTransactionalMail
{
    public static function sendDepositApproved(PDO $pdo, int $userId, float $amount, string $currency = 'TRY'): bool
    {
        return self::sendPaymentApproved($pdo, $userId, 'deposit', $amount, $currency);
    }

    public static function sendWithdrawApproved(PDO $pdo, int $userId, float $amount, string $currency = 'TRY'): bool
    {
        return self::sendPaymentApproved($pdo, $userId, 'withdraw', $amount, $currency);
    }

    private static function sendPaymentApproved(
        PDO $pdo,
        int $userId,
        string $type,
        float $amount,
        string $currency
    ): bool {
        if ($userId <= 0 || !in_array($type, ['deposit', 'withdraw'], true)) {
            return false;
        }

        $user = self::loadUser($pdo, $userId);
        $toEmail = strtolower(trim((string) ($user['email'] ?? '')));
        if ($toEmail === '' || filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $settings = self::mailSettings($pdo);
        $memberName = self::displayName($user);
        $companyName = trim((string) ($settings['company_name'] ?? ''));
        if ($companyName === '') {
            $companyName = 'Vegasroyalspin';
        }

        $money = self::formatMoney($amount, $currency);
        $isDeposit = $type === 'deposit';
        $siteUrl = self::siteUrl();
        $historyUrl = $siteUrl !== '' ? ($siteUrl . '/profile/deposit-withdraw-history') : '/profile/deposit-withdraw-history';

        $subject = $companyName . ($isDeposit ? ' — Yatırım Onaylandı' : ' — Çekim Tamamlandı');
        $messageText = 'Merhaba ' . $memberName . ",\n\n"
            . ($isDeposit
                ? ($money . " tutarındaki yatırımınız onaylandı ve bakiyenize eklendi.\n")
                : ($money . " tutarındaki çekim talebiniz tamamlandı.\n"))
            . ($siteUrl !== '' ? "\nİşlem geçmişi: " . $historyUrl . "\n" : '')
            . "\n" . $companyName . ' Ekibi';

        $htmlBody = null;
        $mailerFile = dirname(__DIR__) . '/Services/MetropolMailer.php';
        if (is_file($mailerFile)) {
            require_once $mailerFile;
        }
        if (function_exists('metropol_mail_render_template')) {
            $supportEmail = trim((string) ($settings['support_email'] ?? ''));
            if ($supportEmail === '' || filter_var($supportEmail, FILTER_VALIDATE_EMAIL) === false) {
                $domain = (string) (parse_url($siteUrl, PHP_URL_HOST) ?: 'vegasroyalspin.com');
                $supportEmail = 'support@' . $domain;
            }

            $safeMoney = htmlspecialchars($money, ENT_QUOTES, 'UTF-8');
            $heading = $isDeposit ? 'Yatırım Onaylandı' : 'Çekim Tamamlandı';
            $preheader = $isDeposit
                ? ($companyName . ' — yatırımınız bakiyenize eklendi')
                : ($companyName . ' — çekim talebiniz tamamlandı');
            $bodyHtml = $isDeposit
                ? ('<p style="margin:0 0 16px 0;font-size:15px;line-height:1.7;color:#dcccf3;">'
                    . '<strong style="color:#ffffff;">' . $safeMoney . '</strong> tutarındaki yatırımınız onaylandı '
                    . 've bakiyenize eklendi.'
                    . '</p>'
                    . '<p style="margin:0;font-size:13px;line-height:1.7;color:#b9a3d6;">'
                    . 'İşlem detaylarını hesabınızdaki geçmiş sayfasından inceleyebilirsiniz.'
                    . '</p>')
                : ('<p style="margin:0 0 16px 0;font-size:15px;line-height:1.7;color:#dcccf3;">'
                    . '<strong style="color:#ffffff;">' . $safeMoney . '</strong> tutarındaki çekim talebiniz tamamlandı.'
                    . '</p>'
                    . '<p style="margin:0;font-size:13px;line-height:1.7;color:#b9a3d6;">'
                    . 'Tutar, seçtiğiniz ödeme yöntemine iletildi. İşlem geçmişinizi hesabınızdan kontrol edebilirsiniz.'
                    . '</p>');

            $templateKey = $isDeposit ? 'deposit_approved_template_html' : 'withdraw_approved_template_html';
            $logoUrl = self::resolveLogoUrl($pdo, $siteUrl);

            $htmlBody = metropol_mail_render_template(
                $siteUrl,
                $preheader,
                $heading,
                $bodyHtml,
                'İşlem Geçmişi',
                $historyUrl,
                [
                    'template_html' => trim((string) ($settings[$templateKey] ?? '')),
                    'company_name' => $companyName,
                    'support_email' => $supportEmail,
                    'company_address' => (string) ($settings['company_address'] ?? ''),
                    'logo_url' => $logoUrl,
                    'member_name' => $memberName,
                    'amount' => $money,
                ]
            );
        }

        return self::deliver($pdo, $settings, $toEmail, $subject, $messageText, $htmlBody, $memberName);
    }

    /** @return array<string,mixed> */
    private static function loadUser(PDO $pdo, int $userId): array
    {
        try {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : [];
        } catch (Throwable) {
            return [];
        }
    }

    /** @param array<string,mixed> $user */
    private static function displayName(array $user): string
    {
        $first = trim((string) ($user['name'] ?? $user['first_name'] ?? ''));
        $last = trim((string) ($user['surname'] ?? $user['last_name'] ?? ''));
        $full = trim($first . ' ' . $last);
        if ($full !== '') {
            return $full;
        }
        $username = trim((string) ($user['username'] ?? ''));
        return $username !== '' ? $username : 'Değerli Üyemiz';
    }

    private static function formatMoney(float $amount, string $currency = 'TRY'): string
    {
        $currency = strtoupper(trim($currency));
        if ($currency === '') {
            $currency = 'TRY';
        }

        return number_format($amount, 2, ',', '.') . ' ' . $currency;
    }

    private static function siteUrl(): string
    {
        foreach ([getenv('FRONTEND_URL') ?: '', getenv('SITE_URL') ?: '', getenv('APP_URL') ?: ''] as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return rtrim($value, '/');
            }
        }

        return 'https://vegasroyalspin.com';
    }

    /** @return array<string,mixed> */
    private static function mailSettings(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query('SELECT * FROM mail_settings ORDER BY id ASC LIMIT 1');
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            return is_array($row) ? $row : [];
        } catch (Throwable) {
            return [];
        }
    }

    private static function resolveLogoUrl(PDO $pdo, string $siteUrl): string
    {
        $siteUrl = rtrim($siteUrl, '/');
        $favicon = '';
        try {
            $stmt = $pdo->query('SELECT favicon_url, logo_url FROM site_ayarlar ORDER BY id ASC LIMIT 1');
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            if (is_array($row)) {
                $favicon = trim((string) ($row['favicon_url'] ?? ''));
                if ($favicon === '') {
                    $favicon = trim((string) ($row['logo_url'] ?? ''));
                }
            }
        } catch (Throwable) {
            $favicon = '';
        }
        if ($favicon === '') {
            $favicon = '/assets/images/favicons/apple-touch-icon.png';
        }
        if (preg_match('#^https?://#i', $favicon) === 1) {
            return $favicon;
        }
        if ($favicon[0] !== '/') {
            $favicon = '/' . $favicon;
        }

        return $siteUrl !== '' ? ($siteUrl . $favicon) : $favicon;
    }

    /**
     * @param array<string,mixed> $settings
     */
    private static function deliver(
        PDO $pdo,
        array $settings,
        string $toEmail,
        string $subject,
        string $messageText,
        ?string $htmlBody,
        string $toName
    ): bool {
        $enabled = (int) ($settings['enabled'] ?? $settings['mail_enabled'] ?? 0) === 1;
        if (!$enabled) {
            self::logOutbound($pdo, $toEmail, $subject, '[mail_disabled] ' . $messageText, 'not_configured');
            return false;
        }

        $mailerFile = dirname(__DIR__) . '/Services/MetropolMailer.php';
        if (is_file($mailerFile)) {
            require_once $mailerFile;
        }

        $from = trim((string) ($settings['from_email'] ?? $settings['mail_from_address'] ?? $settings['smtp_user'] ?? ''));
        if ($from === '') {
            $from = 'no-reply@vegasroyalspin.com';
        }

        if (function_exists('metropol_mail_send')) {
            $error = '';
            $ok = metropol_mail_send($settings, $from, $toEmail, $subject, $messageText, $error, $htmlBody, $toName);
            $preview = $ok
                ? $messageText
                : ('[smtp_error] ' . ($error !== '' ? $error : 'send_failed') . "\n\n" . $messageText);
            self::logOutbound($pdo, $toEmail, $subject, $preview, $ok ? 'sent' : 'failed');
            return $ok;
        }

        self::logOutbound($pdo, $toEmail, $subject, '[mailer_missing] ' . $messageText, 'failed');
        return false;
    }

    private static function logOutbound(PDO $pdo, string $toEmail, string $subject, string $bodyPreview, string $status): void
    {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO mail_outbound_log (admin_id, to_email, subject, body_preview, status, created_at)
                 VALUES (NULL, :to_email, :subject, :body_preview, :status, NOW())'
            );
            $stmt->execute([
                'to_email' => $toEmail,
                'subject' => $subject,
                'body_preview' => substr($bodyPreview, 0, 500),
                'status' => $status,
            ]);
        } catch (Throwable) {
        }
    }
}
