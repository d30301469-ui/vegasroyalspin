<?php
/** Üye API modülü — index.php tarafından include edilir. */

if (!function_exists('admin_member_reset_base_url')) {
    function admin_member_reset_base_url(): string
    {
        $candidates = [
            getenv('FRONTEND_URL') ?: '',
            getenv('SITE_URL') ?: '',
            getenv('APP_URL') ?: '',
        ];
        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return rtrim($value, '/');
            }
        }

        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '') {
            $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
            $scheme = ($https !== '' && $https !== 'off') ? 'https' : 'http';
            return $scheme . '://' . $host;
        }

        return '';
    }
}

if (!function_exists('admin_member_reset_link')) {
    function admin_member_reset_link(string $token): string
    {
        $base = admin_member_reset_base_url();
        $path = '/reset-password?token=' . rawurlencode($token);
        return $base !== '' ? ($base . $path) : $path;
    }
}

if (!function_exists('admin_member_is_valid_turkish_identity_number')) {
    function admin_member_is_valid_turkish_identity_number(string $tc): bool
    {
        if (!preg_match('/^\d{11}$/', $tc)) {
            return false;
        }
        if ($tc[0] === '0') {
            return false;
        }

        $d = array_map('intval', str_split($tc));
        $oddSum = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
        $evenSum = $d[1] + $d[3] + $d[5] + $d[7];
        $d10 = ((($oddSum * 7) - $evenSum) % 10 + 10) % 10;
        if ($d[9] !== $d10) {
            return false;
        }

        $sum10 = array_sum(array_slice($d, 0, 10));

        return ($sum10 % 10) === $d[10];
    }
}

if (!function_exists('admin_member_mail_settings')) {
    /** @return array<string,mixed> */
    function admin_member_mail_settings(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query('SELECT * FROM mail_settings ORDER BY id ASC LIMIT 1');
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            return is_array($row) ? $row : [];
        } catch (Throwable) {
            return [];
        }
    }
}

if (!function_exists('admin_member_log_outbound_mail')) {
    function admin_member_log_outbound_mail(PDO $pdo, string $toEmail, string $subject, string $bodyPreview, string $status): void
    {
        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS mail_outbound_log (
                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    admin_id INT UNSIGNED NULL,
                    to_email VARCHAR(190) NOT NULL,
                    subject VARCHAR(255) NOT NULL DEFAULT '',
                    body_preview TEXT NULL,
                    status VARCHAR(40) NOT NULL DEFAULT 'queued',
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_mail_outbound_created (created_at),
                    KEY idx_mail_outbound_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $dbName = '';
            try {
                $dbRow = $pdo->query('SELECT DATABASE() AS db_name')->fetch();
                $dbName = is_array($dbRow) ? (string) ($dbRow['db_name'] ?? '') : '';
            } catch (Throwable) {
            }
            $preview = ($dbName !== '' ? '[db=' . $dbName . '] ' : '') . $bodyPreview;
            $stmt = $pdo->prepare(
                'INSERT INTO mail_outbound_log (admin_id, to_email, subject, body_preview, status, created_at)
                 VALUES (NULL, :to_email, :subject, :body_preview, :status, NOW())'
            );
            $stmt->execute([
                'to_email' => $toEmail,
                'subject' => $subject,
                'body_preview' => substr($preview, 0, 500),
                'status' => $status,
            ]);
        } catch (Throwable) {
            // Mail log başarısız olsa da akış kesilmez.
        }
    }
}

if (!function_exists('admin_member_resolve_mail_logo_url')) {
    /** Mail şablonunda kullanılacak site favicon/logo URL'sini üretir (mutlak). */
    function admin_member_resolve_mail_logo_url(PDO $pdo, string $siteUrl): string
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
}

if (!function_exists('admin_member_resolve_display_name')) {
    /**
     * Üyenin görünen adını çözer (name/surname, first_name/last_name, username).
     *
     * @param array<string,mixed>|null $userHint
     */
    function admin_member_resolve_display_name(PDO $pdo, string $toEmail, ?array $userHint = null): string
    {
        $row = is_array($userHint) ? $userHint : null;
        if ($row === null) {
            try {
                $stmt = $pdo->prepare(
                    'SELECT * FROM users WHERE email = :email OR LOWER(email) = LOWER(:email2) LIMIT 1'
                );
                $stmt->execute(['email' => $toEmail, 'email2' => $toEmail]);
                $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($fetched)) {
                    $row = $fetched;
                }
            } catch (Throwable) {
                $row = null;
            }
        }
        if (!is_array($row)) {
            return 'Değerli Üyemiz';
        }

        $pick = static function (array $src, array $keys): string {
            foreach ($keys as $key) {
                foreach ($src as $field => $value) {
                    if (strcasecmp((string) $field, $key) === 0) {
                        $v = trim((string) $value);
                        if ($v !== '') {
                            return $v;
                        }
                    }
                }
            }
            return '';
        };

        $first = $pick($row, ['name', 'first_name', 'firstname', 'ad', 'firstName']);
        $last = $pick($row, ['surname', 'last_name', 'lastname', 'soyad', 'lastName', 'family_name']);
        $full = trim($first . ' ' . $last);
        if ($full !== '') {
            return $full;
        }

        $username = $pick($row, ['username', 'user_name', 'login']);
        return $username !== '' ? $username : 'Değerli Üyemiz';
    }
}

if (!function_exists('admin_member_mail_from_address')) {
    /** @param array<string,mixed> $settings */
    function admin_member_mail_from_address(array $settings): string
    {
        $from = trim((string) ($settings['from_email'] ?? $settings['mail_from_address'] ?? ''));
        if ($from === '') {
            $from = trim((string) ($settings['smtp_user'] ?? ''));
        }
        if ($from !== '') {
            return $from;
        }

        $host = (string) (parse_url(admin_member_reset_base_url(), PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        return 'no-reply@' . $host;
    }
}

if (!function_exists('admin_member_deliver_mail')) {
    /**
     * @param array<string,mixed> $settings
     */
    function admin_member_deliver_mail(
        PDO $pdo,
        array $settings,
        string $toEmail,
        string $subject,
        string $messageText,
        ?string $htmlBody = null,
        string $toName = ''
    ): bool {
        $enabled = (int) ($settings['enabled'] ?? $settings['mail_enabled'] ?? 0) === 1;
        if (!$enabled) {
            admin_member_log_outbound_mail($pdo, $toEmail, $subject, '[mail_disabled] ' . $messageText, 'not_configured');
            return false;
        }

        $mailerFile = null;
        if (defined('ADMIN_APP_PATH')) {
            $candidate = rtrim((string) ADMIN_APP_PATH, '/\\') . '/Services/Mailer.php';
            if (is_file($candidate)) {
                $mailerFile = $candidate;
            }
        }
        if ($mailerFile === null) {
            $candidate = dirname(__DIR__, 3) . '/app/Services/Mailer.php';
            if (is_file($candidate)) {
                $mailerFile = $candidate;
            }
        }
        if ($mailerFile !== null) {
            require_once $mailerFile;
        }

        $from = admin_member_mail_from_address($settings);
        if (function_exists('mail_send')) {
            $error = '';
            $ok = mail_send($settings, $from, $toEmail, $subject, $messageText, $error, $htmlBody, $toName);
            $preview = $ok
                ? $messageText
                : ('[smtp_error] ' . ($error !== '' ? $error : 'send_failed') . "\n\n" . $messageText);
            admin_member_log_outbound_mail($pdo, $toEmail, $subject, $preview, $ok ? 'sent' : 'failed');
            return $ok;
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $from,
            'Reply-To: ' . $from,
            'X-Mailer: PHP/' . phpversion(),
        ];
        $sent = @mail($toEmail, $subject, $messageText, implode("\r\n", $headers));
        admin_member_log_outbound_mail($pdo, $toEmail, $subject, $messageText, $sent ? 'sent' : 'failed');
        return $sent;
    }
}

if (!function_exists('admin_member_send_reset_mail')) {
    /**
     * @param array<string,mixed>|null $userHint users satırı (name/surname için)
     */
    function admin_member_send_reset_mail(PDO $pdo, string $toEmail, string $token, ?array $userHint = null): bool
    {
        $settings = admin_member_mail_settings($pdo);
        $memberName = admin_member_resolve_display_name($pdo, $toEmail, $userHint);

        $companyName = trim((string) ($settings['company_name'] ?? ''));
        if ($companyName === '') {
            $companyName = 'Vegasroyalspin';
        }

        $subject = $companyName . ' — Şifre Sıfırlama';
        $link = admin_member_reset_link($token);
        $messageText = 'Merhaba ' . $memberName . ",\n\n"
            . $companyName . " hesabınız için şifre sıfırlama talebinde bulundunuz.\n"
            . "Şifrenizi sıfırlamak için aşağıdaki bağlantıyı kullanın:\n\n"
            . $link . "\n\n"
            . "Bu bağlantı 1 saat geçerlidir. Talebi siz oluşturmadıysanız bu e-postayı yok sayabilirsiniz.\n\n"
            . $companyName . ' Ekibi';

        $htmlBody = null;
        $mailerFile = dirname(__DIR__, 3) . '/app/Services/Mailer.php';
        if (is_file($mailerFile)) {
            require_once $mailerFile;
        }
        if (function_exists('mail_render_template')) {
            $supportEmail = trim((string) ($settings['support_email'] ?? ''));
            if ($supportEmail === '' || filter_var($supportEmail, FILTER_VALIDATE_EMAIL) === false) {
                $domain = (string) (parse_url(admin_member_reset_base_url(), PHP_URL_HOST) ?: 'vegasroyalspin.com');
                $supportEmail = 'support@' . $domain;
            }

            $siteUrl = admin_member_reset_base_url();
            // Eski / uyumsuz özel sablonlar ad-soyadi gostermez; markali varsayilana dus.
            $customHtml = trim((string) ($settings['reset_template_html'] ?? ''));
            if ($customHtml !== '' && (
                stripos($customHtml, '{{MEMBER_NAME}}') === false
                || stripos($customHtml, 'You recently requested') !== false
                || stripos($customHtml, 'Reset your password') !== false
                || stripos($customHtml, 'this invoice') !== false
                || stripos($customHtml, 'Cheers,') !== false
                || stripos($customHtml, 'Hi {$name}') !== false
            )) {
                $customHtml = '';
            }
            $templateOptions = [
                'template_html' => $customHtml,
                'company_name' => $companyName,
                'support_email' => $supportEmail,
                'company_address' => (string) ($settings['company_address'] ?? ''),
                'logo_url' => admin_member_resolve_mail_logo_url($pdo, $siteUrl),
                'member_name' => $memberName,
            ];

            $safeName = htmlspecialchars($memberName, ENT_QUOTES, 'UTF-8');
            $safeCompany = htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8');
            $bodyHtml = '<p style="margin:0 0 16px 0;font-size:15px;line-height:1.7;color:#dcccf3;">'
                . $safeCompany . ' hesabınız için şifre sıfırlama talebi alındı. '
                . 'Aşağıdaki butona tıklayarak yeni şifrenizi belirleyebilirsiniz.'
                . '</p>'
                . '<p style="margin:0;font-size:13px;line-height:1.7;color:#b9a3d6;">'
                . '<strong style="color:#c44bb8;">Bu bağlantı 1 saat geçerlidir.</strong> '
                . 'Talebi siz oluşturmadıysanız bu e-postayı yok sayabilirsiniz.'
                . '</p>';
            // Isim soyisim sablonda {{MEMBER_NAME}} / ust satirdan gelir; body icine de yedek olarak eklenir.
            if (stripos($customHtml, '{{MEMBER_NAME}}') === false) {
                $bodyHtml = '<p style="margin:0 0 14px 0;font-size:15px;line-height:1.7;color:#dcccf3;">Merhaba <strong style="color:#ffffff;">' . $safeName . '</strong>,</p>' . $bodyHtml;
            }
            $htmlBody = mail_render_template(
                $siteUrl,
                $companyName . ' şifre sıfırlama bağlantınız hazır',
                'Şifre Sıfırlama',
                $bodyHtml,
                'Şifremi Sıfırla',
                $link,
                $templateOptions
            );
        }

        return admin_member_deliver_mail($pdo, $settings, $toEmail, $subject, $messageText, $htmlBody, $memberName);
    }
}

if (!function_exists('admin_member_send_registration_success_mail')) {
    /**
     * Başarılı üyelik kaydından sonra bilgilendirme e-postası gönderir.
     *
     * @param array<string,mixed>|null $userHint
     */
    function admin_member_send_registration_success_mail(PDO $pdo, string $toEmail, ?array $userHint = null): bool
    {
        $settings = admin_member_mail_settings($pdo);
        $memberName = admin_member_resolve_display_name($pdo, $toEmail, $userHint);
        $companyName = trim((string) ($settings['company_name'] ?? ''));
        if ($companyName === '') {
            $companyName = 'Vegasroyalspin';
        }

        $subject = $companyName . ' — Kayıt Başarılı';
        $siteUrl = admin_member_reset_base_url();
        $messageText = 'Merhaba ' . $memberName . ",\n\n"
            . "Kayıt işleminiz başarılı bir şekilde oluşturulmuştur.\n"
            . "Hesabınız başarılı bir şekilde oluşturulmuştur.\n\n"
            . ($siteUrl !== '' ? "Siteye git: " . $siteUrl . "\n\n" : '')
            . "Güvenliğiniz için şifrenizi kimseyle paylaşmayın.\n\n"
            . $companyName . ' Ekibi';

        $htmlBody = null;
        $mailerFile = dirname(__DIR__, 3) . '/app/Services/Mailer.php';
        if (is_file($mailerFile)) {
            require_once $mailerFile;
        }
        if (function_exists('mail_render_template')) {
            $supportEmail = trim((string) ($settings['support_email'] ?? ''));
            if ($supportEmail === '' || filter_var($supportEmail, FILTER_VALIDATE_EMAIL) === false) {
                $domain = (string) (parse_url($siteUrl, PHP_URL_HOST) ?: 'vegasroyalspin.com');
                $supportEmail = 'support@' . $domain;
            }
            $safeMember = htmlspecialchars($memberName, ENT_QUOTES, 'UTF-8');
            $bodyHtml = '<p style="margin:0 0 12px 0;font-size:15px;line-height:1.7;color:#dcccf3;">'
                . 'Merhaba <strong style="color:#ffffff;">' . $safeMember . '</strong>,'
                . '</p>'
                . '<p style="margin:0 0 12px 0;font-size:15px;line-height:1.7;color:#dcccf3;">'
                . 'Kayıt işleminiz başarılı bir şekilde oluşturulmuştur.'
                . '</p>'
                . '<p style="margin:0 0 16px 0;font-size:15px;line-height:1.7;color:#dcccf3;">'
                . 'Hesabınız başarılı bir şekilde oluşturulmuştur.'
                . '</p>'
                . '<p style="margin:0;font-size:13px;line-height:1.7;color:#b9a3d6;">'
                . 'Güvenliğiniz için şifrenizi kimseyle paylaşmayın.'
                . '</p>';
            $htmlBody = mail_render_template(
                $siteUrl,
                'Kayıt işleminiz başarılı bir şekilde oluşturulmuştur',
                'Kayıt Başarılı',
                $bodyHtml,
                'Siteye Git',
                $siteUrl,
                [
                    'template_html' => trim((string) ($settings['welcome_template_html'] ?? '')),
                    'company_name' => $companyName,
                    'support_email' => $supportEmail,
                    'company_address' => (string) ($settings['company_address'] ?? ''),
                    'logo_url' => admin_member_resolve_mail_logo_url($pdo, $siteUrl),
                    'member_name' => $memberName,
                ]
            );
        }

        return admin_member_deliver_mail($pdo, $settings, $toEmail, $subject, $messageText, $htmlBody, $memberName);
    }
}

if (!function_exists('admin_member_ensure_user_table_columns')) {
    /**
     * Auto-create any missing columns on the users table to prevent login failures.
     * Runs before every login attempt — idempotent via IF NOT EXISTS / SHOW COLUMNS check.
     */
    function admin_member_ensure_user_table_columns(PDO $pdo): void
    {
        if (function_exists('frontend_runtime_migrations_allowed') && !frontend_runtime_migrations_allowed()) {
            return;
        }
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;

        try {
            $existing = array_column(
                $pdo->query('SHOW COLUMNS FROM `users`')->fetchAll(PDO::FETCH_ASSOC),
                'Field'
            );
        } catch (Throwable) {
            // Table doesn't exist yet — migration will handle it
            return;
        }

        $needed = [
            'name'             => 'VARCHAR(100) NOT NULL DEFAULT \'\'',
            'surname'          => 'VARCHAR(100) NOT NULL DEFAULT \'\'',
            'identity_number'  => 'VARCHAR(32) NULL',
            'gender'           => 'VARCHAR(16) NULL',
            'dob'              => 'DATE NULL',
            'phone'            => 'VARCHAR(32) NULL',
            'city'             => 'VARCHAR(100) NULL',
            'country'          => 'VARCHAR(8) NOT NULL DEFAULT \'TR\'',
            'address'          => 'VARCHAR(500) NULL',
            'bonus_code'       => 'VARCHAR(60) NULL',
            'referral_code'    => 'VARCHAR(40) NULL',
            'referred_by_affiliate_id' => 'INT UNSIGNED NULL',
            'referred_by_user_id'      => 'INT UNSIGNED NULL',
            'balance'          => 'DECIMAL(15,2) NOT NULL DEFAULT 0.00',
            'bonus_balance'    => 'DECIMAL(15,2) NOT NULL DEFAULT 0.00',
            'is_verified'      => 'TINYINT(1) NOT NULL DEFAULT 0',
            'is_test'          => 'TINYINT(1) NOT NULL DEFAULT 0',
            'verify_token'     => 'VARCHAR(128) NULL',
            'last_login_at'    => 'DATETIME NULL',
            'password_changed_at' => 'DATETIME NULL',
            'updated_at'       => 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        ];

        foreach ($needed as $col => $def) {
            if (in_array($col, $existing, true)) {
                continue;
            }
            try {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `{$col}` {$def}");
                error_log('[admin_member_ensure_user_table_columns] Added missing column: users.' . $col);
            } catch (Throwable $e) {
                error_log('[admin_member_ensure_user_table_columns] Failed to add ' . $col . ': ' . $e->getMessage());
            }
        }

        // Ensure critical indices exist
        $indexes = [
            'idx_users_last_login' => 'ALTER TABLE `users` ADD INDEX `idx_users_last_login` (`last_login_at`)',
            'idx_users_referred_by_affiliate' => 'ALTER TABLE `users` ADD INDEX `idx_users_referred_by_affiliate` (`referred_by_affiliate_id`)',
            'idx_users_referred_by_user' => 'ALTER TABLE `users` ADD INDEX `idx_users_referred_by_user` (`referred_by_user_id`)',
        ];
        foreach ($indexes as $idx => $sql) {
            try {
                $existingIdx = $pdo->query("SHOW INDEX FROM `users` WHERE Key_name = " . $pdo->quote($idx))->fetchColumn();
                if ($existingIdx === false) {
                    $pdo->exec($sql);
                }
            } catch (Throwable) {
                // Index may already exist or column not yet available
            }
        }
    }
}

if ($method === 'POST' && ($route === 'login.php' || $route === 'auth/login')) {
    $input = $memberInput($payload);
    $login = trim((string) ($input['login'] ?? $input['username'] ?? $input['email'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    if ($login === '' || $password === '') {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Kullanıcı adı/e-posta ve şifre zorunludur.']);
    }
    $pdo = AdminDatabase::pdo();
    require_once __DIR__ . '/../includes/member_login_rate_limit.php';
    $rateLimit = memberLoginRateLimitCheck($pdo, $login);
    if (empty($rateLimit['allowed'])) {
        $memberEnvelope(429, [
            'success' => false,
            'code' => 429,
            'message' => (string) ($rateLimit['message'] ?? 'Çok fazla deneme.'),
            'data' => ['retryAfterSec' => (int) ($rateLimit['retryAfterSec'] ?? 0)],
        ]);
    }
    // Schema migrations should run via admin/database/migrations — not on every login.
    try {
        $stmt = $pdo->prepare('SELECT id, username, email, password, name, surname, banned FROM users WHERE username = :username OR email = :email LIMIT 1');
        $stmt->execute(['username' => $login, 'email' => $login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), '42S02')) {
            $memberEnvelope(503, ['success' => false, 'code' => 503, 'message' => 'Üye servisi henüz kurulmadı. Lütfen migration çalıştırın.']);
        }
        // Also catch unknown column errors (42S22) and auto-fix
        if (str_contains($e->getMessage(), '42S22')) {
            admin_member_ensure_user_table_columns($pdo);
            try {
                $stmt = $pdo->prepare('SELECT id, username, email, password, name, surname, banned FROM users WHERE username = :username OR email = :email LIMIT 1');
                $stmt->execute(['username' => $login, 'email' => $login]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e2) {
                error_log('[member_auth/login] DB error after auto-fix: ' . $e2->getMessage());
                throw $e2;
            }
        } else {
            throw $e;
        }
    }
    if (!is_array($user)) {
        memberLoginRateLimitRecordFailure($pdo, $login);
        $memberEnvelope(401, ['success' => false, 'code' => 401, 'message' => 'Kullanıcı adı veya şifre hatalı.']);
    }
    if (!empty($user['banned'])) {
        $memberEnvelope(403, ['success' => false, 'code' => 403, 'error' => 'ACCOUNT_BANNED', 'message' => 'Hesabınız banlanmıştır. Giriş yapamazsınız.']);
    }
    $hash = (string) ($user['password'] ?? '');
    if (!$memberPasswordMatches($password, $hash)) {
        memberLoginRateLimitRecordFailure($pdo, $login);
        $memberEnvelope(401, ['success' => false, 'code' => 401, 'message' => 'Kullanıcı adı veya şifre hatalı.']);
    }
    if ($memberPasswordNeedsUpgrade($hash)
        && function_exists('frontend_app_is_production')
        && frontend_app_is_production()
        && !(function_exists('frontend_legacy_password_login_allowed') && frontend_legacy_password_login_allowed())) {
        memberLoginRateLimitRecordFailure($pdo, $login);
        $memberEnvelope(403, [
            'success' => false,
            'code' => 403,
            'error' => 'PASSWORD_UPGRADE_REQUIRED',
            'message' => 'Güvenlik güncellemesi nedeniyle şifrenizi sıfırlamanız gerekiyor. "Şifremi unuttum" bağlantısını kullanın.',
        ]);
    }
    memberLoginRateLimitClearSuccess($pdo, $login);
    if ($memberPasswordNeedsUpgrade($hash)) {
        try {
            $pdo->prepare('UPDATE users SET password = :password, password_changed_at = NOW() WHERE id = :id')
                ->execute([
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'id' => (int) ($user['id'] ?? 0),
                ]);
        } catch (Throwable) {
            // Eski hash yükseltilemese bile giriş başarısız olmamalı.
        }
    }
    if (!(defined('APP_API_NO_SESSION') && APP_API_NO_SESSION)) {
        $_SESSION['loggedin'] = true;
        $_SESSION['user_id'] = (int) ($user['id'] ?? 0);
        $_SESSION['username'] = (string) ($user['username'] ?? $login);
        $_SESSION['email'] = (string) ($user['email'] ?? '');
        unset($_SESSION['login_error']);
    }
    // Track last login time for dashboard stats
    try {
        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
            ->execute(['id' => (int) ($user['id'] ?? 0)]);
    } catch (Throwable) {
        // Non-critical — don't block login
    }
    $jwt = '';
    try {
        $jwt = $memberJwtIssue($pdo, $user);
        if (!(defined('APP_API_NO_SESSION') && APP_API_NO_SESSION)) {
            $_SESSION['member_jwt'] = $jwt;
            if (function_exists('frontend_set_member_restore_cookie')) {
                frontend_set_member_restore_cookie($jwt);
            }
        }
    } catch (Throwable $jwtError) {
        error_log('[member_auth/login] JWT issue failed: ' . $jwtError->getMessage());
        if (!(defined('APP_API_NO_SESSION') && APP_API_NO_SESSION)) {
            unset($_SESSION['member_jwt']);
            if (function_exists('frontend_clear_member_restore_cookie')) {
                frontend_clear_member_restore_cookie();
            }
        }
    }
    if ($jwt === '') {
        $memberEnvelope(503, [
            'success' => false,
            'code' => 503,
            'message' => 'Oturum servisi hazır değil. Backend kurulumunu tamamlayın (member_jwt_tokens tablosu).',
            'hint' => 'https://admin.vegasroyalspin.com/install — migration çalıştırın',
        ]);
    }
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Giriş başarılı.',
        'data' => [
            'token' => $jwt,
            'user_id' => (int) ($user['id'] ?? 0),
            'user' => [
                'id' => (int) ($user['id'] ?? 0),
                'username' => (string) ($user['username'] ?? ''),
                'email' => (string) ($user['email'] ?? ''),
                'name' => trim((string) (($user['name'] ?? '') . ' ' . ($user['surname'] ?? ''))),
            ],
        ],
    ]);
}

if ($method === 'POST' && ($route === 'auth/check-availability' || $route === 'check-availability' || $route === 'check_availability.php')) {
    $input = $memberInput($payload);
    $username = trim((string) ($input['username'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $pdo = AdminDatabase::pdo();
    $usernameAvailable = true;
    $emailAvailable = true;
    if ($username !== '') {
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => $username]);
        $usernameAvailable = !$stmt->fetchColumn();
    }
    if ($email !== '') {
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = :email OR LOWER(email) = LOWER(:email2) LIMIT 1');
        $stmt->execute(['email' => $email, 'email2' => $email]);
        $emailAvailable = !$stmt->fetchColumn();
    }
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'OK',
        'data' => [
            'username_available' => $usernameAvailable,
            'email_available' => $emailAvailable,
            'username' => $usernameAvailable,
            'email' => $emailAvailable,
        ],
    ]);
}

if ($method === 'POST' && ($route === 'register.php' || $route === 'auth/register')) {
    $input = $memberInput($payload);
    $username = trim((string) ($input['username'] ?? ''));
    $email = trim((string) ($input['email'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $passwordConfirmation = (string) ($input['password_confirmation'] ?? $input['confirm_password'] ?? '');
    $firstName = trim((string) ($input['first_name'] ?? $input['firstName'] ?? $input['name'] ?? ''));
    $surname = trim((string) ($input['surname'] ?? ''));
    $country = strtoupper(trim((string) ($input['country'] ?? 'TR')));
    $city = trim((string) ($input['city'] ?? ''));
    $dob = trim((string) ($input['birth_date'] ?? $input['dob'] ?? ''));
    $genderRaw = trim((string) ($input['gender'] ?? ''));
    $phoneRaw = trim((string) ($input['phone'] ?? ''));
    $phoneCode = preg_replace('/\D+/', '', (string) ($input['phone_country_code'] ?? ''));
    $tc = preg_replace('/\D+/', '', (string) ($input['tc'] ?? $input['tcKimlik'] ?? $input['identity_number'] ?? ''));
    $address = trim((string) ($input['address'] ?? ''));
    $bonusCode = trim((string) ($input['bonus_code'] ?? $input['bonusCode'] ?? ''));
    // Boş referral_code anahtarı ?? zincirini kırıyordu; promo alanındaki ortak kodu da kabul et.
    admin_require_project_file('services/AffiliateService.php');
    $inboundReferral = AffiliateService::pickInboundCode(is_array($input) ? $input : [], $bonusCode);

    $errors = [];
    if ($username === '') {
        $errors['username'] = 'Kullanıcı adı gerekli.';
    }
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Geçerli bir e-posta girin.';
    }
    if ($password === '') {
        $errors['password'] = 'Şifre gerekli.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Şifre en az 6 karakter olmalıdır.';
    }
    if ($passwordConfirmation !== '' && $password !== $passwordConfirmation) {
        $errors['password_confirmation'] = 'Şifreler eşleşmiyor.';
    }
    if ($firstName === '') {
        $errors['first_name'] = 'Ad gerekli.';
    }
    if ($surname === '') {
        $errors['surname'] = 'Soyad gerekli.';
    }
    if ($city === '') {
        $errors['city'] = 'Şehir gerekli.';
    }
    if ($dob === '') {
        $errors['birth_date'] = 'Doğum tarihi gerekli.';
    }
    if ($genderRaw === '') {
        $errors['gender'] = 'Cinsiyet gerekli.';
    }
    $phoneDigits = (string) preg_replace('/\D+/', '', $phoneRaw);
    if ($phoneCode !== '' && str_starts_with($phoneDigits, (string) $phoneCode)) {
        $phoneDigits = substr($phoneDigits, strlen((string) $phoneCode));
    }
    $phoneDigits = ltrim($phoneDigits, '0');
    if (strlen($phoneDigits) < 10) {
        $errors['phone'] = 'Telefon en az 10 rakam olmalıdır.';
    }
    if ($country === 'TR') {
        if (strlen((string) $tc) !== 11) {
            $errors['tc'] = 'Türkiye için 11 haneli T.C. kimlik numarası gerekli.';
        } elseif (!admin_member_is_valid_turkish_identity_number((string) $tc)) {
            $errors['tc'] = 'T.C. kimlik numarası geçersiz.';
        }
    }
    if ($errors !== []) {
        $memberEnvelope(400, [
            'success' => false,
            'code' => 400,
            'error' => 'VALIDATION_ERROR',
            'message' => 'Doğrulama hatası',
            'errors' => $errors,
        ]);
    }

    $genderMap = [
        'erkek' => 'Erkek',
        'kadın' => 'Kadın',
        'kadin' => 'Kadın',
        'diğer' => 'Diğer',
        'diger' => 'Diğer',
        'male' => 'Erkek',
        'female' => 'Kadın',
        'other' => 'Diğer',
    ];
    $genderKey = mb_strtolower($genderRaw, 'UTF-8');
    $gender = $genderMap[$genderKey] ?? 'Erkek';

    $pdo = AdminDatabase::pdo();
    $dup = $pdo->prepare('SELECT username, email, identity_number, phone FROM users WHERE username = :username OR email = :email OR (:identity_number_check <> "" AND identity_number = :identity_number) OR (:phone_check <> "" AND phone = :phone) LIMIT 1');
    $dup->execute([
        'username' => $username,
        'email' => $email,
        'identity_number_check' => (string) $tc,
        'identity_number' => (string) $tc,
        'phone_check' => $phoneDigits,
        'phone' => $phoneDigits,
    ]);
    $exists = $dup->fetch(PDO::FETCH_ASSOC);
    if (is_array($exists)) {
        $dupErrors = [];
        if (strcasecmp((string) ($exists['username'] ?? ''), $username) === 0) {
            $dupErrors['username'] = 'Bu kullanıcı adı zaten kayıtlı.';
        }
        if (strcasecmp((string) ($exists['email'] ?? ''), $email) === 0) {
            $dupErrors['email'] = 'Bu e-posta zaten kayıtlı.';
        }
        if ((string) $tc !== '' && (string) ($exists['identity_number'] ?? '') === (string) $tc) {
            $dupErrors['tc'] = 'Bu kimlik numarası zaten kayıtlı.';
        }
        if ($phoneDigits !== '' && (string) ($exists['phone'] ?? '') === $phoneDigits) {
            $dupErrors['phone'] = 'Bu telefon numarası zaten kayıtlı.';
        }
        $memberEnvelope(409, [
            'success' => false,
            'code' => 409,
            'error' => 'DUPLICATE_USER',
            'message' => 'Kullanıcı adı, e-posta, telefon veya kimlik numarası zaten kayıtlı.',
            'errors' => $dupErrors,
        ]);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $baseReferral = preg_replace('/[^a-z0-9]/i', '', strtolower($username));
    $baseReferral = is_string($baseReferral) && $baseReferral !== '' ? substr($baseReferral, 0, 18) : 'user';
    $referralCode = '';
    for ($i = 0; $i < 6; $i++) {
        $candidate = strtoupper($baseReferral . substr(bin2hex(random_bytes(4)), 0, 8));
        $check = $pdo->prepare('SELECT 1 FROM users WHERE referral_code = :code LIMIT 1');
        $check->execute(['code' => $candidate]);
        if (!$check->fetchColumn()) {
            $referralCode = $candidate;
            break;
        }
    }

    $clientIp = (string) ($input['client_ip'] ?? $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
    if (str_contains($clientIp, ',')) {
        $clientIp = trim(explode(',', $clientIp, 2)[0]);
    }

    // JWT tablosunu transaction dışında hazırla (DDL implicit commit riski).
    try {
        $memberJwtEnsureTable($pdo);
    } catch (Throwable $jwtTableError) {
        error_log('[member_auth/register] JWT table ensure failed: ' . $jwtTableError->getMessage());
        $memberEnvelope(503, [
            'success' => false,
            'code' => 503,
            'message' => 'Oturum servisi hazır değil. Backend kurulumunu tamamlayın (member_jwt_tokens tablosu).',
            'hint' => 'https://admin.vegasroyalspin.com/install — migration çalıştırın',
        ]);
    }

    $userId = 0;
    $resolvedAffiliate = null;

    try {
        $pdo->beginTransaction();
        $insert = $pdo->prepare(
            'INSERT INTO users
            (name, surname, username, email, identity_number, gender, dob, phone, city, country, password, bonus_code, referral_code, address, password_changed_at, created_at)
            VALUES
            (:name, :surname, :username, :email, :identity_number, :gender, :dob, :phone, :city, :country, :password, :bonus_code, :referral_code, :address, NOW(), NOW())'
        );
        $insert->execute([
            'name' => $firstName,
            'surname' => $surname,
            'username' => $username,
            'email' => $email,
            'identity_number' => $tc !== '' ? $tc : null,
            'gender' => $gender,
            'dob' => $dob,
            'phone' => $phoneDigits,
            'city' => $city,
            'country' => $country,
            'password' => $passwordHash,
            'bonus_code' => $bonusCode !== '' ? $bonusCode : null,
            'referral_code' => $referralCode !== '' ? $referralCode : null,
            'address' => $address !== '' ? $address : null,
        ]);
        $userId = (int) $pdo->lastInsertId();

        try {
            $resolvedAffiliate = AffiliateService::attributeRegistration(
                $pdo,
                $userId,
                $inboundReferral,
                $clientIp
            );
        } catch (Throwable $affiliateError) {
            error_log('[member_auth/register] Referral attribution failed: ' . $affiliateError->getMessage());
        }

        if (!(defined('APP_API_NO_SESSION') && APP_API_NO_SESSION)) {
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            unset($_SESSION['login_error']);
        }
        $jwt = '';
        try {
            $jwt = $memberJwtIssue($pdo, [
                'id' => $userId,
                'username' => $username,
                'email' => $email,
            ]);
            if (!(defined('APP_API_NO_SESSION') && APP_API_NO_SESSION)) {
                $_SESSION['member_jwt'] = $jwt;
                if (function_exists('frontend_set_member_restore_cookie')) {
                    frontend_set_member_restore_cookie($jwt);
                }
            }
        } catch (Throwable $jwtError) {
            error_log('[member_auth/register] JWT issue failed: ' . $jwtError->getMessage());
            if (!(defined('APP_API_NO_SESSION') && APP_API_NO_SESSION)) {
                unset($_SESSION['member_jwt']);
                if (function_exists('frontend_clear_member_restore_cookie')) {
                    frontend_clear_member_restore_cookie();
                }
            }
        }
        if ($jwt === '') {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (!(defined('APP_API_NO_SESSION') && APP_API_NO_SESSION)) {
                unset($_SESSION['loggedin'], $_SESSION['user_id'], $_SESSION['username'], $_SESSION['email'], $_SESSION['member_jwt']);
            }
            $memberEnvelope(503, [
                'success' => false,
                'code' => 503,
                'message' => 'Oturum servisi hazır değil. Backend kurulumunu tamamlayın (member_jwt_tokens tablosu).',
                'hint' => 'https://admin.vegasroyalspin.com/install — migration çalıştırın',
            ]);
        }
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
    } catch (Throwable $registerError) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[member_auth/register] Insert failed: ' . $registerError->getMessage());
        $memberEnvelope(500, [
            'success' => false,
            'code' => 500,
            'message' => 'Kayıt tamamlanamadı. Lütfen tekrar deneyin.',
        ]);
    }

    // Hoş geldin freespin kayıt TX dışında (DDL/provider riski rollback üretmesin).
    // Önce attribution sonucu, yoksa DB'deki referred_by_affiliate_id üzerinden dene.
    if (!empty($userId)) {
        try {
            admin_require_project_file('services/AffiliateWelcomeFreespinService.php');
            if (is_array($resolvedAffiliate ?? null)) {
                AffiliateWelcomeFreespinService::maybeGrantAfterAttribution($pdo, (int) $userId, $resolvedAffiliate);
            } else {
                AffiliateWelcomeFreespinService::maybeGrantForUserId($pdo, (int) $userId);
            }
        } catch (Throwable $welcomeFsError) {
            error_log('[member_auth/register] Affiliate welcome freespin failed: ' . $welcomeFsError->getMessage());
        }
    }

    try {
        admin_member_send_registration_success_mail($pdo, $email, [
            'name' => $firstName,
            'surname' => $surname,
            'username' => $username,
            'email' => $email,
        ]);
    } catch (Throwable $mailError) {
        admin_member_log_outbound_mail(
            $pdo,
            $email,
            'Vegasroyalspin — Kayıt Başarılı',
            '[unexpected_error] ' . $mailError->getMessage(),
            'failed'
        );
        error_log('[member_auth/register] Welcome mail failed: ' . $mailError->getMessage());
    }
    $memberEnvelope(201, [
        'success' => true,
        'code' => 201,
        'message' => 'Kayıt başarılı. Hoş geldiniz!',
        'data' => [
            'token' => $jwt,
            'user_id' => $userId,
            'user' => [
                'id' => $userId,
                'username' => $username,
                'email' => $email,
                'name' => trim($firstName . ' ' . $surname),
            ],
        ],
    ]);
}

if ($method === 'GET' && ($route === 'session.php' || $route === 'auth/session')) {
    $pdo = AdminDatabase::pdo();
    $userId = $memberJwtRequireUserId($pdo);
    $sessionToken = $memberJwtExtractBearer();
    $apiNoSession = defined('APP_API_NO_SESSION') && APP_API_NO_SESSION;
    if ($sessionToken === '' && !$apiNoSession) {
        $sessionToken = (string) ($_SESSION['member_jwt'] ?? '');
        if ($sessionToken === '' && !empty($_SESSION['loggedin']) && $userId > 0) {
            try {
                $sessionToken = $memberJwtIssue($pdo, [
                    'id' => $userId,
                    'username' => (string) ($_SESSION['username'] ?? ''),
                    'email' => (string) ($_SESSION['email'] ?? ''),
                ]);
                $_SESSION['member_jwt'] = $sessionToken;
                if (function_exists('frontend_set_member_restore_cookie')) {
                    frontend_set_member_restore_cookie($sessionToken);
                }
            } catch (Throwable) {
                $sessionToken = '';
            }
        }
    }
    $user = $memberUserById($pdo, $userId);
    if (!$user) {
        $memberEnvelope(401, [
            'success' => false,
            'code' => 401,
            'error' => 'UNAUTHORIZED',
            'message' => 'Geçersiz veya süresi dolmuş token',
        ]);
    }
    if (!empty($user['banned'])) {
        try {
            MemberJwtService::revokeAllForUser($pdo, $userId);
        } catch (Throwable) {
        }
        if (!(defined('APP_API_NO_SESSION') && APP_API_NO_SESSION)) {
            if (function_exists('frontend_clear_member_session')) {
                frontend_clear_member_session();
            } else {
                unset($_SESSION['loggedin'], $_SESSION['user_id'], $_SESSION['username'], $_SESSION['email'], $_SESSION['member_jwt']);
            }
        }
        $memberEnvelope(403, [
            'success' => false,
            'code' => 403,
            'error' => 'ACCOUNT_BANNED',
            'message' => 'Hesabınız banlanmıştır. Giriş yapamazsınız.',
        ]);
    }
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Oturum aktif.',
        'data' => [
            'token' => $sessionToken,
            'user_id' => (int) ($user['id'] ?? 0),
            'user' => $user,
        ],
    ]);
}

// ─── REST üye hesabı (GET /me, tercihler, limitler, oturum yenileme) ───────

if ($method === 'GET' && in_array($route, ['me', 'me/index'], true)) {
    $pdo = AdminDatabase::pdo();
    $userId = $memberRequireLogin();
    $user = $memberUserById($pdo, $userId);
    if (!$user) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Kullanıcı bulunamadı.']);
    }
    $settings = MemberAccountService::settings($pdo, $userId);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Üye profili',
        'data' => [
            'user' => $user,
            'preferences' => $settings['preferences'],
            'limits' => $settings['limits'],
        ],
    ]);
}

if ($method === 'POST' && $route === 'auth/refresh') {
    $pdo = AdminDatabase::pdo();
    $userId = $memberJwtRequireUserId($pdo);
    $user = $memberUserById($pdo, $userId);
    if (!$user) {
        $memberEnvelope(401, ['success' => false, 'code' => 401, 'error' => 'UNAUTHORIZED', 'message' => 'Oturum yenilenemedi.']);
    }
    $memberJwtRevokeCurrent($pdo);
    $jwt = $memberJwtIssue($pdo, $user);
    if (!(defined('APP_API_NO_SESSION') && APP_API_NO_SESSION)) {
        $_SESSION['member_jwt'] = $jwt;
        if (function_exists('frontend_set_member_restore_cookie')) {
            frontend_set_member_restore_cookie($jwt);
        }
    }
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Token yenilendi.',
        'data' => [
            'token' => $jwt,
            'user_id' => $userId,
            'expires_in' => 2592000,
        ],
    ]);
}

if (in_array($method, ['GET', 'PATCH', 'PUT'], true) && in_array($route, ['me/preferences', 'me/preferences.php'], true)) {
    $pdo = AdminDatabase::pdo();
    $userId = $memberRequireLogin();
    if ($method === 'GET') {
        $prefs = MemberAccountService::settings($pdo, $userId)['preferences'];
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Üye tercihleri',
            'data' => ['preferences' => $prefs],
        ]);
    }
    $updated = MemberAccountService::updatePreferences($pdo, $userId, $memberInput($payload));
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Tercihler güncellendi.',
        'data' => ['preferences' => $updated],
    ]);
}

if (in_array($method, ['GET', 'PATCH', 'PUT'], true) && in_array($route, ['me/limits', 'me/limits.php'], true)) {
    $pdo = AdminDatabase::pdo();
    $userId = $memberRequireLogin();
    if ($method === 'GET') {
        $limits = MemberAccountService::settings($pdo, $userId)['limits'];
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Sorumlu oyun limitleri',
            'data' => ['limits' => $limits],
        ]);
    }
    $updated = MemberAccountService::updateLimits($pdo, $userId, $memberInput($payload));
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Limitler güncellendi.',
        'data' => ['limits' => $updated],
    ]);
}

if ($method === 'GET' && in_array($route, ['me/security-sessions', 'me/security-sessions/index'], true)) {
    $pdo = AdminDatabase::pdo();
    $userId = $memberRequireLogin();
    $sessions = MemberAccountService::securitySessions($pdo, $userId);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Aktif oturumlar',
        'data' => ['sessions' => $sessions, 'total' => count($sessions)],
    ]);
}

if ($method === 'POST' && ($route === 'logout.php' || $route === 'auth/logout')) {
    $pdo = AdminDatabase::pdo();
    $memberJwtRevokeCurrent($pdo);
    // Admin paneli ile aynı PHP session cookie'sini paylaşabilir; tüm $_SESSION'ı
    // silmek bo_backoffice_admin_user dahil admin oturumunu düşürür.
    if (function_exists('frontend_clear_member_session')) {
        frontend_clear_member_session();
    } else {
        foreach ([
            'loggedin', 'user_id', 'username', 'email', 'ana_bakiye',
            'first_name', 'surname', 'member_jwt', '__header_member_cache',
            '__member_jwt_proxy_synced', 'login_error',
        ] as $memberSessionKey) {
            unset($_SESSION[$memberSessionKey]);
        }
        if (function_exists('frontend_clear_member_restore_cookie')) {
            frontend_clear_member_restore_cookie();
        }
    }
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Çıkış başarılı. Güle güle!',
        'data' => new stdClass(),
    ]);
}

if ($method === 'POST' && ($route === 'forgot_password.php' || $route === 'auth/forgot-password')) {
    $input = $memberInput($payload);
    $email = trim((string) ($input['email'] ?? ''));
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $memberEnvelope(422, [
            'success' => false,
            'code' => 422,
            'message' => 'Geçerli bir e-posta adresi girin.',
        ]);
    }
    try {
        $pdo = AdminDatabase::pdo();
        $userStmt = $pdo->prepare('SELECT id, email, name, surname, username FROM users WHERE email = :email OR LOWER(email) = LOWER(:email2) LIMIT 1');
        $userStmt->execute(['email' => $email, 'email2' => $email]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($user)) {
            $token = bin2hex(random_bytes(32));
            $pdo->prepare(
                'UPDATE users SET password_reset_token = :token, password_reset_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = :id'
            )->execute(['token' => $token, 'id' => (int) ($user['id'] ?? 0)]);
            admin_member_send_reset_mail($pdo, (string) ($user['email'] ?? $email), $token, $user);
        } else {
            admin_member_log_outbound_mail($pdo, $email, 'Vegasroyalspin — Şifre Sıfırlama', '[user_not_found] Bu e-posta users tablosunda bulunamadi, mail gonderilmedi.', 'user_not_found');
        }
    } catch (Throwable $forgotPasswordError) {
        error_log('[member_auth/forgot_password] ' . $forgotPasswordError->getMessage());
        $debug = (string) (getenv('APP_DEBUG') ?: '') === '1' || (defined('APP_DEBUG') && APP_DEBUG);
        $memberEnvelope(503, [
            'success' => false,
            'code' => 503,
            'message' => 'Şifre sıfırlama servisi geçici olarak kullanılamıyor.',
            'meta' => $debug ? ['reason' => $forgotPasswordError->getMessage()] : [],
        ]);
    }
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Eğer e-posta sistemde kayıtlıysa şifre sıfırlama bağlantısı gönderilecektir.',
    ]);
}

if ($method === 'POST' && ($route === 'reset_password.php' || $route === 'auth/reset-password')) {
    $input = $memberInput($payload);
    $token = trim((string) ($input['token'] ?? $input['reset_token'] ?? ''));
    $password = (string) ($input['password'] ?? '');
    $passwordConfirmation = (string) ($input['password_confirmation'] ?? $input['confirm_password'] ?? '');
    if ($token === '') {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Sıfırlama anahtarı gerekli.']);
    }
    if ($password === '' || strlen($password) < 6) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Şifre en az 6 karakter olmalıdır.']);
    }
    if ($passwordConfirmation !== '' && $password !== $passwordConfirmation) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Şifre tekrarı eşleşmiyor.']);
    }
    $pdo = AdminDatabase::pdo();
    $stmt = $pdo->prepare(
        'SELECT id FROM users WHERE password_reset_token = :token AND password_reset_expires_at > NOW() LIMIT 1'
    );
    $stmt->execute(['token' => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($user)) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Geçersiz veya süresi dolmuş token.']);
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare(
        'UPDATE users SET password = :password, password_changed_at = NOW(), password_reset_token = NULL, password_reset_expires_at = NULL WHERE id = :id'
    )->execute(['password' => $hash, 'id' => (int) ($user['id'] ?? 0)]);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Şifreniz başarıyla güncellendi.',
    ]);
}

if ($method === 'POST' && ($route === 'password_reset.php' || $route === 'auth/password-reset')) {
    $input = $memberInput($payload);
    $action = strtolower(trim((string) ($input['action'] ?? '')));
    if ($action === 'request' || $action === 'forgot') {
        $email = trim((string) ($input['email'] ?? ''));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Geçerli bir e-posta adresi girin.']);
        }
        try {
            $pdo = AdminDatabase::pdo();
            $userStmt = $pdo->prepare('SELECT id, email, name, surname, username FROM users WHERE email = :email OR LOWER(email) = LOWER(:email2) LIMIT 1');
            $userStmt->execute(['email' => $email, 'email2' => $email]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($user)) {
                $token = bin2hex(random_bytes(32));
                $pdo->prepare(
                    'UPDATE users SET password_reset_token = :token, password_reset_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = :id'
                )->execute(['token' => $token, 'id' => (int) ($user['id'] ?? 0)]);
                admin_member_send_reset_mail($pdo, (string) ($user['email'] ?? $email), $token, $user);
            } else {
                admin_member_log_outbound_mail($pdo, $email, 'Vegasroyalspin — Şifre Sıfırlama', '[user_not_found] Bu e-posta users tablosunda bulunamadi, mail gonderilmedi.', 'user_not_found');
            }
        } catch (Throwable $passwordResetRequestError) {
            error_log('[member_auth/password_reset.request] ' . $passwordResetRequestError->getMessage());
            $debug = (string) (getenv('APP_DEBUG') ?: '') === '1' || (defined('APP_DEBUG') && APP_DEBUG);
            $memberEnvelope(503, [
                'success' => false,
                'code' => 503,
                'message' => 'Şifre sıfırlama servisi geçici olarak kullanılamıyor.',
                'meta' => $debug ? ['reason' => $passwordResetRequestError->getMessage()] : [],
            ]);
        }
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Eğer e-posta sistemde kayıtlıysa şifre sıfırlama bağlantısı gönderilecektir.',
        ]);
    }

    if ($action === 'confirm' || $action === 'reset') {
        $token = trim((string) ($input['token'] ?? $input['reset_token'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $passwordConfirmation = (string) ($input['password_confirmation'] ?? $input['confirm_password'] ?? '');
        if ($token === '') {
            $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Sıfırlama anahtarı gerekli.']);
        }
        if ($password === '' || strlen($password) < 6) {
            $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Şifre en az 6 karakter olmalıdır.']);
        }
        if ($passwordConfirmation !== '' && $password !== $passwordConfirmation) {
            $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Şifre tekrarı eşleşmiyor.']);
        }
        $pdo = AdminDatabase::pdo();
        $stmt = $pdo->prepare(
            'SELECT id FROM users WHERE password_reset_token = :token AND password_reset_expires_at > NOW() LIMIT 1'
        );
        $stmt->execute(['token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($user)) {
            $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Geçersiz veya süresi dolmuş token.']);
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare(
            'UPDATE users SET password = :password, password_changed_at = NOW(), password_reset_token = NULL, password_reset_expires_at = NULL WHERE id = :id'
        )->execute(['password' => $hash, 'id' => (int) ($user['id'] ?? 0)]);
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Şifreniz başarıyla güncellendi.',
        ]);
    }

    $memberEnvelope(422, [
        'success' => false,
        'code' => 422,
        'message' => 'Geçersiz action. request|forgot|confirm|reset kullanın.',
    ]);
}

if ($method === 'POST' && in_array($route, ['password_update.php', 'account/password', 'account/password-update', 'user/password'], true)) {
    $userId = $memberRequireLogin();
    $input = $memberInput($payload);
    $currentPassword = (string) ($input['current_password'] ?? $input['old_password'] ?? $input['currentPassword'] ?? $input['oldPassword'] ?? '');
    $newPassword = (string) ($input['password'] ?? $input['new_password'] ?? $input['newPassword'] ?? '');
    $confirmPassword = (string) ($input['password_confirmation'] ?? $input['confirm_password'] ?? $input['passwordConfirmation'] ?? $input['confirmPassword'] ?? '');
    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Tüm şifre alanları zorunludur.']);
    }
    if (strlen($newPassword) < 6) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Yeni şifre en az 6 karakter olmalıdır.']);
    }
    if ($newPassword !== $confirmPassword) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Şifre tekrarı eşleşmiyor.']);
    }
    $pdo = AdminDatabase::pdo();
    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $hash = (string) $stmt->fetchColumn();
    if (!$memberPasswordMatches($currentPassword, $hash)) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Mevcut şifre hatalı.']);
    }
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE users SET password = :password, password_changed_at = NOW() WHERE id = :id')
        ->execute(['password' => $newHash, 'id' => $userId]);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Şifre güncellendi.',
        'data' => [
            'updated' => true,
            'redirect' => null,
        ],
    ]);
}

if (
    (in_array($method, ['GET', 'POST'], true) && $route === 'two_factor.php')
    || ($method === 'POST' && in_array($route, ['auth/2fa/enable', 'auth/2fa/verify'], true))
) {
    $memberRequireLogin();
    $memberEnvelope(501, [
        'success' => false,
        'code' => 501,
        'message' => 'İki aşamalı doğrulama henüz kullanıma hazır değildir.',
        'data' => [
            'enabled' => false,
            'available' => false,
        ],
    ]);
}

if ($method === 'POST' && ($route === 'account_freeze.php' || $route === 'account_unfreeze.php')) {
    $userId = $memberRequireLogin();
    $pdo = AdminDatabase::pdo();
    if ($route === 'account_freeze.php') {
        $input = $memberInput($payload);
        $password = (string) ($input['password'] ?? $input['current_password'] ?? $input['currentPassword'] ?? '');
        if ($password === '') {
            $memberEnvelope(422, [
                'success' => false,
                'code' => 422,
                'message' => 'Hesabınızı dondurmak için şifrenizi girin.',
                'data' => ['errors' => ['password' => ['Şifre zorunludur.']]],
            ]);
        }
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $hash = (string) $stmt->fetchColumn();
        if (!$memberPasswordMatches($password, $hash)) {

            $memberEnvelope(422, [
                'success' => false,
                'code' => 422,
                'message' => 'Şifre hatalı.',
                'data' => ['errors' => ['password' => ['Şifre hatalı.']]],
            ]);
        }
        $pdo->prepare('INSERT INTO user_account_freeze (user_id, frozen_at) VALUES (:user_id, NOW()) ON DUPLICATE KEY UPDATE frozen_at = VALUES(frozen_at)')
            ->execute(['user_id' => $userId]);
        $memberJwtRevokeCurrent($pdo);
        if (function_exists('frontend_clear_member_session')) {
            frontend_clear_member_session();
        } else {
            foreach ([
                'loggedin', 'user_id', 'username', 'email', 'ana_bakiye',
                'first_name', 'surname', 'member_jwt', '__header_member_cache',
                '__member_jwt_proxy_synced', 'login_error',
            ] as $memberSessionKey) {
                unset($_SESSION[$memberSessionKey]);
            }
        }
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Hesap donduruldu.',
            'data' => ['redirect' => '/login?account_frozen=1'],
        ]);
    }
    $pdo->prepare('DELETE FROM user_account_freeze WHERE user_id = :user_id')->execute(['user_id' => $userId]);
    $memberEnvelope(200, ['success' => true, 'code' => 200, 'message' => 'Hesap dondurması kaldırıldı.']);
}
if (in_array($method, ['GET', 'POST'], true) && $route === 'email_verification.php') {
    $input = $memberInput($payload);
    if ($method === 'GET') {
        $input = array_merge($input, $_GET);
    }
    $action = strtolower(trim((string) ($input['action'] ?? 'request')));
    if ($action === '' && (trim((string) ($input['token'] ?? '')) !== '' || trim((string) ($input['verification_token'] ?? '')) !== '')) {
        $action = 'confirm';
    }
    if (!in_array($action, ['request', 'resend', 'confirm', 'verify'], true)) {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Geçersiz action.']);
    }
    if (in_array($action, ['request', 'resend'], true)) {
        $email = trim((string) ($input['email'] ?? ''));
        if ($email === '') {
            $optionalUserId = $memberJwtOptionalUserId(AdminDatabase::pdo());
            if (($optionalUserId ?? 0) > 0) {
                $user = $memberUserById(AdminDatabase::pdo(), (int) $optionalUserId);
                $email = is_array($user) ? trim((string) ($user['email'] ?? '')) : '';
            }
        }
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Geçerli bir e-posta adresi girin.']);
        }
        $token = bin2hex(random_bytes(32));
        $pdo = AdminDatabase::pdo();
        $stmt = $pdo->prepare('UPDATE users SET verify_token = :token WHERE email = :email');
        $stmt->execute(['token' => $token, 'email' => $email]);
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'Doğrulama e-postası gönderildi.',
            'data' => ['sent' => true],
        ]);
    }
    $token = trim((string) ($input['token'] ?? $input['verification_token'] ?? ''));
    if ($token === '') {
        $memberEnvelope(422, ['success' => false, 'code' => 422, 'message' => 'Token zorunludur.']);
    }
    $pdo = AdminDatabase::pdo();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE verify_token = :token LIMIT 1');
    $stmt->execute(['token' => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($user)) {
        $memberEnvelope(404, ['success' => false, 'code' => 404, 'message' => 'Geçersiz token.']);
    }
    $pdo->prepare('UPDATE users SET is_verified = 1, verify_token = NULL WHERE id = :id')->execute(['id' => (int) ($user['id'] ?? 0)]);
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'E-posta doğrulandı.',
        'data' => ['verified' => true],
    ]);
}

if ($method === 'POST' && $route === 'auth/verify-phone') {
    $memberRequireLogin();
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'Telefon doğrulama henüz yapılandırılmadı.',
        'data' => ['verified' => false],
    ]);
}
