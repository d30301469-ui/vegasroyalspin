<?php
/** Üye API modülü — index.php tarafından include edilir. */

if (!function_exists('memberResetBaseUrl')) {
    function memberResetBaseUrl(): string
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

if (!function_exists('memberResetLink')) {
    function memberResetLink(string $token): string
    {
        $base = memberResetBaseUrl();
        $path = '/reset-password?token=' . rawurlencode($token);
        return $base !== '' ? ($base . $path) : $path;
    }
}

if (!function_exists('memberIsValidTurkishIdentityNumber')) {
    function memberIsValidTurkishIdentityNumber(string $tc): bool
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

if (!function_exists('memberMailSettings')) {
    /** @return array<string,mixed> */
    function memberMailSettings(PDO $pdo): array
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

if (!function_exists('memberLogOutboundMail')) {
    function memberLogOutboundMail(PDO $pdo, string $toEmail, string $subject, string $bodyPreview, string $status): void
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

if (!function_exists('memberSendResetMail')) {
    function memberSendResetMail(PDO $pdo, string $toEmail, string $token): bool
    {
        $settings = memberMailSettings($pdo);
        $enabled = (int) ($settings['enabled'] ?? $settings['mail_enabled'] ?? 0) === 1;
        $from = trim((string) ($settings['from_email'] ?? $settings['mail_from_address'] ?? ''));
        if ($from === '') {
            $from = trim((string) ($settings['smtp_user'] ?? ''));
        }
        if ($from === '') {
            $from = 'no-reply@' . (parse_url(memberResetBaseUrl(), PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        }

        $subject = 'Sifre Sifirlama Baglantiniz';
        $link = memberResetLink($token);
        $messageText = "Sifrenizi sifirlamak icin asagidaki baglantiyi kullanin:\n\n" . $link . "\n\nBaglanti 1 saat gecerlidir.";

        if (!$enabled) {
            memberLogOutboundMail($pdo, $toEmail, $subject, '[mail_disabled] ' . $messageText, 'not_configured');
            return false;
        }

        // Tek kaynak: admin panel test aracıyla AYNI gönderim motoru (MetropolMailer.php).
        $mailerFile = null;
        if (defined('ADMIN_APP_PATH')) {
            $candidate = rtrim((string) ADMIN_APP_PATH, '/\\') . '/Services/MetropolMailer.php';
            if (is_file($candidate)) {
                $mailerFile = $candidate;
            }
        }
        if ($mailerFile === null) {
            $candidate = dirname(__DIR__, 3) . '/app/Services/MetropolMailer.php';
            if (is_file($candidate)) {
                $mailerFile = $candidate;
            }
        }

        if ($mailerFile !== null) {
            require_once $mailerFile;
        }

        if (function_exists('metropol_mail_send')) {
            $error = '';
            $htmlBody = null;
            if (function_exists('metropol_mail_render_template')) {
                $companyName = trim((string) ($settings['company_name'] ?? ''));
                if ($companyName === '') {
                    $companyName = 'VegasRoyalSpin';
                }
                $supportEmail = trim((string) ($settings['support_email'] ?? ''));
                if ($supportEmail === '' || filter_var($supportEmail, FILTER_VALIDATE_EMAIL) === false) {
                    $domain = (string) (parse_url(memberResetBaseUrl(), PHP_URL_HOST) ?: 'vegasroyalspin.com');
                    $supportEmail = 'support@' . $domain;
                }

                $templateOptions = [
                    'template_html' => (string) ($settings['reset_template_html'] ?? ''),
                    'company_name' => $companyName,
                    'support_email' => $supportEmail,
                    'company_address' => (string) ($settings['company_address'] ?? ''),
                ];

                $bodyHtml = '<p style="margin:0 0 16px 0;">You recently requested to reset your password for your ' . htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') . ' account. Click the button below to reset it. <strong>This password reset is only valid for the next 24 hours.</strong></p>';
                $htmlBody = metropol_mail_render_template(
                    memberResetBaseUrl(),
                    'Password reset link is ready',
                    'Hi {$name},',
                    $bodyHtml,
                    'Reset your password',
                    $link,
                    $templateOptions
                );
            }
            $ok = metropol_mail_send($settings, $from, $toEmail, $subject, $messageText, $error, $htmlBody);
            $preview = $ok ? $messageText : ('[smtp_error] ' . ($error !== '' ? $error : 'send_failed') . "\n\n" . $messageText);
            memberLogOutboundMail($pdo, $toEmail, $subject, $preview, $ok ? 'sent' : 'failed');
            return $ok;
        }

        // Son çare: paylaşılan mailer hiç yüklenemediyse PHP mail() dene.
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $from,
            'Reply-To: ' . $from,
            'X-Mailer: PHP/' . phpversion(),
        ];
        $sent = @mail($toEmail, $subject, $messageText, implode("\r\n", $headers));
        memberLogOutboundMail($pdo, $toEmail, $subject, $messageText, $sent ? 'sent' : 'failed');
        return $sent;
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
    try {
        $stmt = $pdo->prepare('SELECT id, username, email, password, name, surname FROM users WHERE username = :username OR email = :email LIMIT 1');
        $stmt->execute(['username' => $login, 'email' => $login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), '42S02')) {
            $memberEnvelope(503, ['success' => false, 'code' => 503, 'message' => 'Üye servisi henüz kurulmadı. Lütfen migration çalıştırın.']);
        }
        throw $e;
    }
    if (!is_array($user)) {
        $memberEnvelope(401, ['success' => false, 'code' => 401, 'message' => 'Kullanıcı adı veya şifre hatalı.']);
    }
    $hash = (string) ($user['password'] ?? '');
    if (!$memberPasswordMatches($password, $hash)) {
        $memberEnvelope(401, ['success' => false, 'code' => 401, 'message' => 'Kullanıcı adı veya şifre hatalı.']);
    }
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
    if (!(defined('METROPOL_API_NO_SESSION') && METROPOL_API_NO_SESSION)) {
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
        if (!(defined('METROPOL_API_NO_SESSION') && METROPOL_API_NO_SESSION)) {
            $_SESSION['member_jwt'] = $jwt;
        }
    } catch (Throwable $jwtError) {
        error_log('[member_auth/login] JWT issue failed: ' . $jwtError->getMessage());
        if (!(defined('METROPOL_API_NO_SESSION') && METROPOL_API_NO_SESSION)) {
            unset($_SESSION['member_jwt']);
        }
    }
    if ($jwt === '') {
        $memberEnvelope(503, [
            'success' => false,
            'code' => 503,
            'message' => 'Oturum servisi hazır değil. Backend kurulumunu tamamlayın (member_jwt_tokens tablosu).',
            'hint' => 'https://bo-backoffice.site/install — migration çalıştırın',
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
        } elseif (!memberIsValidTurkishIdentityNumber((string) $tc)) {
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
    $dup = $pdo->prepare('SELECT username, email, identity_number FROM users WHERE username = :username OR email = :email OR (:identity_number_check <> "" AND identity_number = :identity_number) LIMIT 1');
    $dup->execute([
        'username' => $username,
        'email' => $email,
        'identity_number_check' => (string) $tc,
        'identity_number' => (string) $tc,
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
        $memberEnvelope(409, [
            'success' => false,
            'code' => 409,
            'error' => 'DUPLICATE_USER',
            'message' => 'Kullanıcı adı, e-posta veya kimlik numarası zaten kayıtlı.',
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
    if (!(defined('METROPOL_API_NO_SESSION') && METROPOL_API_NO_SESSION)) {
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
        if (!(defined('METROPOL_API_NO_SESSION') && METROPOL_API_NO_SESSION)) {
            $_SESSION['member_jwt'] = $jwt;
        }
    } catch (Throwable $jwtError) {
        error_log('[member_auth/register] JWT issue failed: ' . $jwtError->getMessage());
        if (!(defined('METROPOL_API_NO_SESSION') && METROPOL_API_NO_SESSION)) {
            unset($_SESSION['member_jwt']);
        }
    }
    if ($jwt === '') {
        $memberEnvelope(503, [
            'success' => false,
            'code' => 503,
            'message' => 'Oturum servisi hazır değil. Backend kurulumunu tamamlayın (member_jwt_tokens tablosu).',
            'hint' => 'https://bo-backoffice.site/install — migration çalıştırın',
        ]);
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
    $apiNoSession = defined('METROPOL_API_NO_SESSION') && METROPOL_API_NO_SESSION;
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
    if (!(defined('METROPOL_API_NO_SESSION') && METROPOL_API_NO_SESSION)) {
        $_SESSION['member_jwt'] = $jwt;
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
    $csrf = $_SESSION['csrf_token'] ?? null;
    $ref = $_SESSION['referral_code'] ?? null;
    $_SESSION = [];
    if ($csrf !== null) {
        $_SESSION['csrf_token'] = $csrf;
    }
    if ($ref !== null) {
        $_SESSION['referral_code'] = $ref;
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
        $userStmt = $pdo->prepare('SELECT id, email FROM users WHERE email = :email LIMIT 1');
        $userStmt->execute(['email' => $email]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($user)) {
            $token = bin2hex(random_bytes(32));
            $pdo->prepare(
                'UPDATE users SET password_reset_token = :token, password_reset_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = :id'
            )->execute(['token' => $token, 'id' => (int) ($user['id'] ?? 0)]);
            memberSendResetMail($pdo, (string) ($user['email'] ?? $email), $token);
        } else {
            memberLogOutboundMail($pdo, $email, 'Sifre Sifirlama Baglantiniz', '[user_not_found] Bu e-posta users tablosunda bulunamadi, mail gonderilmedi.', 'user_not_found');
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
            $userStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $userStmt->execute(['email' => $email]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($user)) {
                $token = bin2hex(random_bytes(32));
                $pdo->prepare(
                    'UPDATE users SET password_reset_token = :token, password_reset_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = :id'
                )->execute(['token' => $token, 'id' => (int) ($user['id'] ?? 0)]);
                memberSendResetMail($pdo, $email, $token);
            } else {
                memberLogOutboundMail($pdo, $email, 'Sifre Sifirlama Baglantiniz', '[user_not_found] Bu e-posta users tablosunda bulunamadi, mail gonderilmedi.', 'user_not_found');
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

if (in_array($method, ['GET', 'POST'], true) && $route === 'two_factor.php') {
    $userId = $memberRequireLogin();
    if ($method === 'GET') {
        $memberEnvelope(200, [
            'success' => true,
            'code' => 200,
            'message' => 'İki aşamalı doğrulama durumu',
            'data' => ['enabled' => !empty($_SESSION['twofa_enabled'])],
        ]);
    }
    $input = $memberInput($payload);
    $enabledRaw = $input['enabled'] ?? $input['twofa_enabled'] ?? $input['twoFactorEnabled'] ?? false;
    $enabled = in_array($enabledRaw, [true, 1, '1', 'true', 'on', 'yes'], true);
    $_SESSION['twofa_enabled'] = $enabled;
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => $enabled ? 'İki aşamalı doğrulama etkinleştirildi.' : 'İki aşamalı doğrulama kapatıldı.',
        'data' => [
            'user_id' => $userId,
            'enabled' => $enabled,
        ],
        'enabled' => $enabled,
    ]);
}

if ($method === 'POST' && $route === 'auth/2fa/enable') {
    $userId = $memberRequireLogin();
    $input = $memberInput($payload);
    $enabledRaw = $input['enabled'] ?? $input['twofa_enabled'] ?? $input['twoFactorEnabled'] ?? true;
    $enabled = in_array($enabledRaw, [true, 1, '1', 'true', 'on', 'yes'], true);
    $_SESSION['twofa_enabled'] = $enabled;
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => $enabled ? 'İki aşamalı doğrulama etkinleştirildi.' : 'İki aşamalı doğrulama kapatıldı.',
        'data' => [
            'user_id' => $userId,
            'enabled' => $enabled,
            'method' => 'session_stub',
        ],
    ]);
}

if ($method === 'POST' && $route === 'auth/2fa/verify') {
    $userId = $memberRequireLogin();
    $input = $memberInput($payload);
    $code = trim((string) ($input['code'] ?? $input['otp'] ?? $input['token'] ?? ''));
    if ($code === '') {
        $memberEnvelope(422, [
            'success' => false,
            'code' => 422,
            'message' => 'Doğrulama kodu zorunludur.',
            'data' => ['errors' => ['code' => ['Kod zorunludur.']]],
        ]);
    }
    $_SESSION['twofa_verified'] = true;
    $memberEnvelope(200, [
        'success' => true,
        'code' => 200,
        'message' => 'İki aşamalı doğrulama tamamlandı.',
        'data' => [
            'user_id' => $userId,
            'verified' => true,
            'method' => 'session_stub',
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
        $csrf = $_SESSION['csrf_token'] ?? null;
        $_SESSION = [];
        if ($csrf !== null) {
            $_SESSION['csrf_token'] = $csrf;
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
