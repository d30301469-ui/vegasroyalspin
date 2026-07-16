<?php

require_once SERVICE_PATH . '/BackendApiClient.php';
require_once SERVICE_PATH . '/MemberLoginService.php';
require_once SERVICE_PATH . '/MemberRegisterService.php';
require_once SERVICE_PATH . '/MemberRegisterPayload.php';

/**
 * Kayıt ve AJAX giriş API.
 */
class ApiAuthController
{
    private static function allowLocalAuthFallback(): bool
    {
        if (function_exists('frontend_is_api_only') && frontend_is_api_only()) {
            return false;
        }

        if (function_exists('frontend_app_is_production') && frontend_app_is_production()) {
            return false;
        }

        return function_exists('frontend_database_allowed') && frontend_database_allowed();
    }

    private static function registerWriteLog(string $message, string $type = 'ERROR'): void
    {
        $logDir = BASE_PATH . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        if (!is_dir($logDir) || !is_writable($logDir)) {
            return;
        }
        $logFile   = $logDir . '/register_errors.log';
        if (file_exists($logFile) && !is_writable($logFile)) {
            return;
        }
        $timestamp = date('Y-m-d H:i:s');
        @file_put_contents($logFile, "[$timestamp] [$type] $message" . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private static function jsonHeaders(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
    }

    /** @param array<string, mixed> $body */
    private static function respondJson(int $code, array $body): void
    {
        self::jsonHeaders();
        http_response_code($code);
        echo json_encode($body, JSON_UNESCAPED_UNICODE);
    }

    private static function assertPostOr405(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            self::respondJson(405, [
                'success' => false,
                'code'    => 405,
                'message' => 'Yalnızca POST desteklenir.',
            ]);

            return false;
        }

        return true;
    }

    private static function assertJsonContentTypeOr400(): bool
    {
        $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (stripos($ct, 'application/json') === false) {
            self::respondJson(400, [
                'success' => false,
                'code'    => 400,
                'message' => 'Content-Type: application/json beklenir.',
            ]);

            return false;
        }

        return true;
    }

    /** @return array<string, mixed>|null */
    private static function decodeJsonObjectOr400(): ?array
    {
        $raw = file_get_contents('php://input');
        $row = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : null;
        if (!is_array($row)) {
            self::respondJson(400, [
                'success' => false,
                'code'    => 400,
                'message' => 'Geçersiz veya boş JSON.',
            ]);

            return null;
        }

        return $row;
    }

    private static function assertMainBackendOr503(): bool
    {
        if (BackendApiClient::effectiveMainBaseUrl() !== '') {
            return true;
        }
        self::respondJson(503, [
            'success' => false,
            'code'    => 503,
            'message' => MemberLoginService::MSG_BACKEND_NOT_CONFIGURED,
        ]);

        return false;
    }

    private static function finishBackendEnvelope(?array $res, int $nullCode, string $nullMsg, int $defaultCode = 200): void
    {
        if ($res === null) {
            self::respondJson($nullCode, [
                'success' => false,
                'code'    => $nullCode,
                'message' => $nullMsg,
            ]);

            return;
        }
        $code = (int) ($res['code'] ?? $defaultCode);
        if ($code < 100 || $code > 599) {
            $code = $defaultCode;
        }
        self::jsonHeaders();
        http_response_code($code);
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Backend farklı DB'de olsa bile admin panelde reset mail denemelerini izlemek için
     * yerel mail_outbound_log tablosuna best-effort kayıt düşer.
     */
    private static function logPasswordResetMailAttempt(string $email, string $source, ?array $res): void
    {
        $to = trim($email);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            $pdo = AdminDatabase::pdo();
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

            $isSuccess = is_array($res) && !empty($res['success']) && (int) ($res['code'] ?? 0) === 200;
            $status = $isSuccess ? 'sent' : 'failed';
            $message = is_array($res) ? trim((string) ($res['message'] ?? '')) : 'backend_response_null';
            $code = is_array($res) ? (int) ($res['code'] ?? 0) : 0;
            $preview = '[source=' . $source . '] [code=' . $code . '] ' . ($message !== '' ? $message : 'no_message');

            $stmt = $pdo->prepare(
                'INSERT INTO mail_outbound_log (admin_id, to_email, subject, body_preview, status, created_at)
                 VALUES (NULL, :to_email, :subject, :body_preview, :status, NOW())'
            );
            $stmt->execute([
                'to_email' => $to,
                'subject' => 'Sifre Sifirlama Baglantiniz',
                'body_preview' => substr($preview, 0, 500),
                'status' => $status,
            ]);
        } catch (Throwable) {
            // Log yazımı başarısız olsa da API akışı kesilmez.
        }
    }

    private static function requestPath(): string
    {
        $apiRoute = isset($_GET['api_route']) && is_string($_GET['api_route']) ? $_GET['api_route'] : '';
        if ($apiRoute !== '' && str_starts_with($apiRoute, '/api/')) {
            return rtrim($apiRoute, '/') ?: '/';
        }

        $p = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        return rtrim(is_string($p) ? $p : '/', '/') ?: '/';
    }

    private static function isPublicJsonRegisterPath(string $path): bool
    {
        return in_array($path, [
            '/api/auth/register',
            '/api/auth/register.php',
            '/api/v2/auth/register',
            '/api/v2/auth/register.php',
        ], true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function readJsonBody(): ?array
    {
        $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (stripos($ct, 'application/json') === false) {
            return null;
        }
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $src
     * @return array<string, mixed>
     */
    private static function rowFromPost(array $src): array
    {
        return [
            'username'              => $src['username'] ?? '',
            'email'                 => $src['email'] ?? '',
            'password'              => $src['password'] ?? '',
            'password_confirmation' => $src['confirm_password'] ?? '',
            'first_name'            => $src['firstName'] ?? '',
            'surname'               => $src['surname'] ?? '',
            'country'               => $src['country'] ?? '',
            'currency'              => $src['currency'] ?? '',
            'city'                  => $src['city'] ?? '',
            'birth_date'            => $src['dob'] ?? '',
            'gender'                => $src['gender'] ?? '',
            'phone'                 => $src['phone'] ?? '',
            'phone_country_code'    => $src['phone_country_code'] ?? '',
            'tc'                    => $src['tcKimlik'] ?? '',
            'address'               => $src['address'] ?? '',
            'terms_accepted'        => $src['terms_accepted'] ?? null,
            'bonus_code'            => $src['bonusCode'] ?? '',
        ];
    }

    public function register(): void
    {
        self::jsonHeaders();

        $path = self::requestPath();
        $ct = (string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        $isJsonRequest = stripos($ct, 'application/json') !== false;
        $publicJsonPath = self::isPublicJsonRegisterPath($path);
        $publicJsonRegister = $publicJsonPath && $isJsonRequest;
        $jsonBody = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $publicJsonRegister) ? self::readJsonBody() : null;

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !$publicJsonRegister) {
            if (
                !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
                !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
            ) {
                self::respondJson(403, [
                    'success' => false,
                    'code'    => 403,
                    'message' => 'Güvenlik doğrulaması başarısız (CSRF). Lütfen sayfayı yenileyip tekrar deneyin.',
                ]);

                return;
            }
        }

        self::registerWriteLog('Backend API kullanılıyor (register)', 'INFO');

        $referral_code            = '';
        $referred_by_affiliate_id = '';

        try {
            $ip = function_exists('metropol_cloudflare_client_ip')
                ? metropol_cloudflare_client_ip()
                : (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            if ($ip === '') {
                $ip = '0.0.0.0';
            }
            self::registerWriteLog("IP adresi: $ip", 'INFO');

            $resolve = BackendApiClient::request('GET', BackendApiClient::SVC_AFFILIATE, '/resolve-referral', ['ip' => $ip]);
            $r       = BackendApiClient::unwrap($resolve);
            if ($r !== [] && !empty($r['referral_code'])) {
                $referral_code            = (string) $r['referral_code'];
                $referred_by_affiliate_id = (string) ($r['affiliate_id'] ?? '');
                self::registerWriteLog("IP'den referral: $referral_code", 'INFO');
            }

            if ($referral_code === '' && isset($_GET['ref']) && $_GET['ref'] !== '') {
                $ref_code = trim((string) $_GET['ref']);
                self::registerWriteLog("URL ref: $ref_code", 'INFO');
                $aff = BackendApiClient::request('GET', BackendApiClient::SVC_AFFILIATE, '/affiliate/by-code', ['code' => $ref_code]);
                $ar  = BackendApiClient::unwrap($aff);
                if ($ar !== [] && isset($ar['id'])) {
                    $referred_by_affiliate_id = (string) $ar['id'];
                    $referral_code            = (string) ($ar['referral_code'] ?? $ref_code);
                    self::registerWriteLog("URL'den referral: $referral_code", 'INFO');
                }
            }

            self::registerWriteLog("Final referral: $referral_code, affiliate: $referred_by_affiliate_id", 'INFO');
        } catch (Exception $e) {
            self::registerWriteLog('Referans çözümleme: ' . $e->getMessage(), 'ERROR');
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['ajax_check']) && $_POST['ajax_check'] === 'true') {
            $response = ['success' => true, 'username' => true, 'email' => true];
            $username = trim($_POST['username'] ?? '');
            $email    = trim($_POST['email'] ?? '');

            $check = BackendApiClient::request('POST', BackendApiClient::SVC_MAIN, '/auth/check-availability', [], [
                'username' => $username,
                'email'    => $email,
            ]);
            if ($check === null) {
                // Mevcut admin/member API'de ayrı availability ucu yok; kayıt ucu nihai validasyonu yapar.
                self::registerWriteLog('AJAX kontrol: availability ucu yok veya yanıt vermedi; kayıt validasyonuna bırakıldı', 'INFO');
            } else {
                $c = BackendApiClient::unwrap($check);
                if (isset($c['username_available'])) {
                    $response['username'] = (bool) $c['username_available'];
                }
                if (isset($c['email_available'])) {
                    $response['email'] = (bool) $c['email_available'];
                }
            }

            echo json_encode($response, JSON_UNESCAPED_UNICODE);

            return;
        }

        $doRegister = false;
        $inputRow   = [];

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['register_submit'])) {
            $doRegister = true;
            $inputRow   = self::rowFromPost($_POST);
            $inputRow['bonus_code'] = trim((string) ($_POST['bonusCode'] ?? ''));
        } elseif ($publicJsonRegister) {
            if ($jsonBody === null) {
                self::respondJson(400, [
                    'success' => false,
                    'code'    => 400,
                    'message' => 'Geçersiz veya boş JSON.',
                ]);

                return;
            }
            $doRegister = true;
            $inputRow   = $jsonBody;
            if ($referral_code !== '' && empty($inputRow['referral_code'])) {
                $inputRow['referral_code'] = $referral_code;
            }
        }

        if ($doRegister) {
            self::registerWriteLog('Kayıt başladı', 'INFO');

            $requireTerms = isset($_POST['register_submit']);
            $bonusCode    = trim((string) ($inputRow['bonus_code'] ?? $inputRow['bonusCode'] ?? ''));
            if ($bonusCode === '' && isset($_POST['bonusCode'])) {
                $bonusCode = trim((string) $_POST['bonusCode']);
            }

            $refForBody = trim((string) ($inputRow['referral_code'] ?? ''));
            if ($refForBody === '') {
                $refForBody = $referral_code;
            }

            $prepared    = MemberRegisterPayload::prepare($inputRow);
            $fieldErrors = MemberRegisterPayload::collectFieldErrors($prepared, $requireTerms);
            if ($fieldErrors !== []) {
                self::respondJson(400, [
                    'success' => false,
                    'code'    => 400,
                    'error'   => 'VALIDATION_ERROR',
                    'message' => 'Doğrulama hatası',
                    'errors'  => $fieldErrors,
                ]);

                return;
            }

            $body = MemberRegisterPayload::buildBackendBody($prepared, $refForBody, $bonusCode);
            $backendBaseUrl = BackendApiClient::effectiveMainBaseUrl();
            $res = null;
            $allowLocalFallback = self::allowLocalAuthFallback();

            if ($backendBaseUrl !== '') {
                $res = MemberRegisterService::register($body);
            } elseif ($allowLocalFallback) {
                self::registerWriteLog('MAIN base URL boş, local register fallback deneniyor', 'INFO');
            }

            if ($res === null) {
                if (!$allowLocalFallback) {
                    self::respondJson(503, [
                        'success' => false,
                        'code' => 503,
                        'message' => 'Backend kayıt servisine erişilemiyor. Lütfen backend API bağlantısını kontrol edin.',
                    ]);

                    return;
                }

                self::registerWriteLog('Kayıt API yanıt vermedi, local register fallback deneniyor', 'INFO');
                $local = self::localRegisterAttempt(
                    $prepared,
                    $refForBody,
                    $bonusCode,
                    $referred_by_affiliate_id
                );
                http_response_code((int) ($local['code'] ?? 500));
                echo json_encode($local, JSON_UNESCAPED_UNICODE);

                return;
            }

            if (MemberRegisterService::succeeded($res)) {
                $usernameForSession = trim((string) ($inputRow['username'] ?? ''));
                MemberRegisterService::applySession($res, $usernameForSession);
                $msg = trim((string) ($res['message'] ?? ''));
                if ($msg === '') {
                    $msg = 'Kayıt başarılı. Hoş geldiniz!';
                }
                http_response_code(201);
                $data = BackendApiClient::unwrap($res);
                echo json_encode([
                    'success' => true,
                    'code'    => 201,
                    'message' => $msg,
                    'data'    => [
                        'token'   => (string) ($data['token'] ?? ''),
                        'user_id' => (int) ($data['user_id'] ?? 0),
                        'user'    => isset($data['user']) && is_array($data['user']) ? $data['user'] : [
                            'username' => $usernameForSession,
                            'email'    => (string) ($inputRow['email'] ?? ''),
                        ],
                    ],
                ], JSON_UNESCAPED_UNICODE);

                return;
            }

            $code = (int) ($res['code'] ?? 400);
            if ($code < 400 || $code > 599) {
                $code = 400;
            }
            http_response_code($code);
            $out = [
                'success' => false,
                'code'    => $code,
                'message' => MemberRegisterService::failureMessage($res),
            ];
            if (!empty($res['error'])) {
                $out['error'] = $res['error'];
            }
            if (isset($res['errors']) && is_array($res['errors'])) {
                $out['errors'] = $res['errors'];
            }
            echo json_encode($out, JSON_UNESCAPED_UNICODE);

            return;
        }

        http_response_code(400);
        echo json_encode(['success' => false, 'code' => 400, 'message' => 'Geçersiz istek.'], JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST /api/v2/auth/forgot-password — Backend şifre sıfırlama isteği (JSON envelope proxy).
     */
    public function forgotPassword(): void
    {
        self::jsonHeaders();
        if (!self::assertPostOr405()) {
            return;
        }
        if (!self::assertJsonContentTypeOr400()) {
            return;
        }
        $row = self::decodeJsonObjectOr400();
        if ($row === null) {
            return;
        }

        $email = trim((string) ($row['email'] ?? ''));
        if ($email === '') {
            self::respondJson(400, ['success' => false, 'code' => 400, 'message' => 'E-posta adresi gerekli.']);

            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::respondJson(400, ['success' => false, 'code' => 400, 'message' => 'Geçerli bir e-posta adresi girin.']);

            return;
        }
        if (!self::assertMainBackendOr503()) {
            return;
        }

        $res = MemberLoginService::forgotPassword($email);
        self::logPasswordResetMailAttempt($email, 'auth/forgot-password', $res);
        self::finishBackendEnvelope(
            $res,
            502,
            'Şifre sıfırlama isteği şu anda işlenemiyor. Lütfen tekrar deneyin.'
        );
    }

    /**
     * POST /api/v2/auth/reset-password — Yeni şifre (token + password; JSON envelope proxy).
     */
    public function resetPassword(): void
    {
        self::jsonHeaders();
        if (!self::assertPostOr405()) {
            return;
        }
        if (!self::assertJsonContentTypeOr400()) {
            return;
        }
        $row = self::decodeJsonObjectOr400();
        if ($row === null) {
            return;
        }

        $token                = trim((string) ($row['token'] ?? $row['reset_token'] ?? ''));
        $password             = (string) ($row['password'] ?? '');
        $passwordConfirmation = (string) ($row['password_confirmation'] ?? $row['confirm_password'] ?? '');

        if ($token === '') {
            self::respondJson(400, ['success' => false, 'code' => 400, 'message' => 'Sıfırlama anahtarı (token) gerekli.']);

            return;
        }
        if ($password === '') {
            self::respondJson(400, ['success' => false, 'code' => 400, 'message' => 'Yeni şifre gerekli.']);

            return;
        }
        if ($passwordConfirmation === '') {
            self::respondJson(400, ['success' => false, 'code' => 400, 'message' => 'Şifre tekrarı gerekli.']);

            return;
        }
        if ($password !== $passwordConfirmation) {
            self::respondJson(400, ['success' => false, 'code' => 400, 'message' => 'Şifre ve şifre tekrarı eşleşmiyor.']);

            return;
        }
        if (!self::assertMainBackendOr503()) {
            return;
        }

        $body = [
            'token'                 => $token,
            'password'              => $password,
            'password_confirmation' => $passwordConfirmation,
        ];
        self::finishBackendEnvelope(
            MemberLoginService::resetPassword($body),
            502,
            'Şifre güncellenemedi. Lütfen tekrar deneyin.'
        );
    }

    /**
     * POST /api/v2/auth/password-reset — forgot_password + reset_password tek uç (action ile mod, api.md).
     */
    public function passwordReset(): void
    {
        self::jsonHeaders();
        if (!self::assertPostOr405()) {
            return;
        }
        if (!self::assertJsonContentTypeOr400()) {
            return;
        }
        $row = self::decodeJsonObjectOr400();
        if ($row === null) {
            return;
        }

        $actionRaw      = $row['action'] ?? '';
        $action         = is_string($actionRaw) ? strtolower(trim($actionRaw)) : '';
        $requestModes   = ['request', 'forgot'];
        $confirmModes   = ['confirm', 'reset'];
        $allowedActions = array_merge($requestModes, $confirmModes);

        if (!in_array($action, $allowedActions, true)) {
            self::respondJson(400, [
                'success' => false,
                'code'    => 400,
                'message' => 'Geçersiz action. request, forgot, confirm veya reset kullanın.',
            ]);

            return;
        }

        $payload = ['action' => $action];

        if (in_array($action, $requestModes, true)) {
            $email = trim((string) ($row['email'] ?? ''));
            if ($email === '') {
                self::respondJson(400, ['success' => false, 'code' => 400, 'message' => 'E-posta adresi gerekli.']);

                return;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                self::respondJson(400, ['success' => false, 'code' => 400, 'message' => 'Geçerli bir e-posta adresi girin.']);

                return;
            }
            $payload['email'] = $email;
        } else {
            $token                = trim((string) ($row['token'] ?? $row['reset_token'] ?? ''));
            $password             = (string) ($row['password'] ?? '');
            $passwordConfirmation = (string) ($row['password_confirmation'] ?? $row['confirm_password'] ?? '');

            if ($token === '') {
                self::respondJson(400, ['success' => false, 'code' => 400, 'message' => 'Sıfırlama anahtarı (token) gerekli.']);

                return;
            }
            if ($password === '') {
                self::respondJson(400, ['success' => false, 'code' => 400, 'message' => 'Yeni şifre gerekli.']);

                return;
            }
            if ($passwordConfirmation === '') {
                self::respondJson(400, ['success' => false, 'code' => 400, 'message' => 'Şifre tekrarı gerekli.']);

                return;
            }
            if ($password !== $passwordConfirmation) {
                self::respondJson(400, ['success' => false, 'code' => 400, 'message' => 'Şifre ve şifre tekrarı eşleşmiyor.']);

                return;
            }
            $payload['token']                 = $token;
            $payload['password']              = $password;
            $payload['password_confirmation'] = $passwordConfirmation;
        }

        if (!self::assertMainBackendOr503()) {
            return;
        }

        $res = MemberLoginService::passwordReset($payload);
        if (in_array($action, $requestModes, true) && isset($payload['email']) && is_string($payload['email'])) {
            self::logPasswordResetMailAttempt($payload['email'], 'auth/password-reset', $res);
        }
        self::finishBackendEnvelope(
            $res,
            502,
            'Şifre sıfırlama isteği şu anda işlenemiyor. Lütfen tekrar deneyin.'
        );
    }

    /**
     * GET/POST /api/v2/auth/email-verification — E-posta doğrulama (api.md).
     */
    public function emailVerification(): void
    {
        self::jsonHeaders();
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'GET') {
            $action              = strtolower(trim((string) ($_GET['action'] ?? '')));
            $email               = trim((string) ($_GET['email'] ?? ''));
            $token               = trim((string) ($_GET['token'] ?? ''));
            $verificationToken = trim((string) ($_GET['verification_token'] ?? ''));

            if ($action === '' && ($token !== '' || $verificationToken !== '')) {
                $action = 'confirm';
            }

            $err = self::emailVerificationValidationError($action, $email, $token, $verificationToken);
            if ($err !== null) {
                http_response_code($err['code']);
                echo json_encode($err['body'], JSON_UNESCAPED_UNICODE);

                return;
            }

            $query = ['action' => $action];
            if ($email !== '') {
                $query['email'] = $email;
            }
            if ($token !== '') {
                $query['token'] = $token;
            }
            if ($verificationToken !== '') {
                $query['verification_token'] = $verificationToken;
            }

            self::emailVerificationRespondBackend('GET', $query, null);

            return;
        }

        if ($method === 'POST') {
            if (!self::assertJsonContentTypeOr400()) {
                return;
            }

            $raw = file_get_contents('php://input');
            $row = [];
            if ($raw !== false && trim((string) $raw) !== '') {
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    self::respondJson(400, ['success' => false, 'code' => 400, 'message' => 'Geçersiz JSON.']);

                    return;
                }
                $row = $decoded;
            }

            foreach (['action', 'email', 'token', 'verification_token'] as $k) {
                if (!isset($row[$k]) || trim((string) $row[$k]) === '') {
                    if (isset($_GET[$k])) {
                        $v = trim((string) $_GET[$k]);
                        if ($v !== '') {
                            $row[$k] = $v;
                        }
                    }
                }
            }

            $action              = strtolower(trim((string) ($row['action'] ?? '')));
            $email               = trim((string) ($row['email'] ?? ''));
            $token               = trim((string) ($row['token'] ?? ''));
            $verificationToken = trim((string) ($row['verification_token'] ?? ''));

            if ($action === '' && ($token !== '' || $verificationToken !== '')) {
                $action = 'confirm';
            }

            $err = self::emailVerificationValidationError($action, $email, $token, $verificationToken);
            if ($err !== null) {
                http_response_code($err['code']);
                echo json_encode($err['body'], JSON_UNESCAPED_UNICODE);

                return;
            }

            $payload = ['action' => $action];
            if ($email !== '') {
                $payload['email'] = $email;
            }
            if ($token !== '') {
                $payload['token'] = $token;
            }
            if ($verificationToken !== '') {
                $payload['verification_token'] = $verificationToken;
            }

            self::emailVerificationRespondBackend('POST', [], $payload);

            return;
        }

        self::respondJson(405, [
            'success' => false,
            'code'    => 405,
            'message' => 'Yalnızca GET veya POST desteklenir.',
        ]);
    }

    /**
     * @return array{code: int, body: array<string, mixed>}|null
     */
    private static function emailVerificationValidationError(
        string $action,
        string $email,
        string $token,
        string $verificationToken
    ): ?array {
        $allowed = ['request', 'resend', 'confirm', 'verify'];
        if ($action === '' || !in_array($action, $allowed, true)) {
            return [
                'code' => 400,
                'body' => [
                    'success' => false,
                    'code'    => 400,
                    'message' => 'Geçersiz veya eksik action. request, resend, confirm veya verify kullanın.',
                ],
            ];
        }

        if (in_array($action, ['request', 'resend'], true)) {
            if ($email === '') {
                return [
                    'code' => 400,
                    'body' => [
                        'success' => false,
                        'code'    => 400,
                        'message' => 'E-posta adresi gerekli.',
                    ],
                ];
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'code' => 400,
                    'body' => [
                        'success' => false,
                        'code'    => 400,
                        'message' => 'Geçerli bir e-posta adresi girin.',
                    ],
                ];
            }
        }

        if (in_array($action, ['confirm', 'verify'], true) && $token === '' && $verificationToken === '') {
            return [
                'code' => 400,
                'body' => [
                    'success' => false,
                    'code'    => 400,
                    'message' => 'Doğrulama anahtarı (token veya verification_token) gerekli.',
                ],
            ];
        }

        return null;
    }

    /**
     * @param array<string, string>     $query
     * @param array<string, mixed>|null $body
     */
    private static function emailVerificationRespondBackend(string $httpMethod, array $query, ?array $body): void
    {
        if (!self::assertMainBackendOr503()) {
            return;
        }

        self::finishBackendEnvelope(
            MemberLoginService::emailVerification($httpMethod, $query, $body),
            502,
            'E-posta doğrulama isteği şu anda işlenemiyor. Lütfen tekrar deneyin.'
        );
    }

    public function login(): void
    {
        self::jsonHeaders();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Geçersiz istek.']);

            return;
        }

        $username_input = trim((string) ($_POST['username'] ?? ''));
        $password_input = (string) ($_POST['password'] ?? '');

        if ($username_input === '' || $password_input === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Kullanıcı adı ve şifre gerekli.']);

            return;
        }

        $allowLocalFallback = self::allowLocalAuthFallback();
        $local = ['success' => false, 'message' => 'Kullanıcı adı veya şifre hatalı.'];
        if ($allowLocalFallback) {
            $local = self::localLoginAttempt($username_input, $password_input);
            if ($local['success'] === true) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Giriş başarılı.',
                    'source' => 'local_db',
                    'data' => [
                        'token' => (string) ($local['token'] ?? ''),
                        'user_id' => (int) ($local['user_id'] ?? 0),
                        'user' => is_array($local['user'] ?? null) ? $local['user'] : new stdClass(),
                    ],
                ], JSON_UNESCAPED_UNICODE);

                return;
            }
        }

        $res = MemberLoginService::login($username_input, $password_input);

        if (MemberLoginService::succeeded($res)) {
            MemberLoginService::applySession($res, $username_input);
            $msg = trim((string) ($res['message'] ?? ''));
            echo json_encode([
                'success' => true,
                'message' => $msg !== '' ? $msg : 'Giriş başarılı.',
                'source' => 'member_api',
            ]);

            return;
        }

        $fallbackMessage = $local['message'] ?? 'Kullanıcı adı veya şifre hatalı.';
        if ($res === null) {
            if (!$allowLocalFallback) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Backend giriş servisine erişilemiyor. Lütfen backend API bağlantısını kontrol edin.',
                ], JSON_UNESCAPED_UNICODE);

                return;
            }
            echo json_encode(['success' => false, 'message' => $fallbackMessage], JSON_UNESCAPED_UNICODE);
            return;
        }

        echo json_encode(['success' => false, 'message' => MemberLoginService::failureMessage($res)], JSON_UNESCAPED_UNICODE);
    }

    /**
     * GET /api/auth/session — PHP oturumundaki üye JWT ile backend session.php proxy (heartbeat).
     */
    public function session(): void
    {
        self::jsonHeaders();

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
            self::respondJson(405, [
                'success' => false,
                'code'    => 405,
                'message' => 'Yalnızca GET desteklenir.',
            ]);

            return;
        }

        if (empty($_SESSION['loggedin']) || empty($_SESSION['member_jwt'])) {
            self::respondJson(401, [
                'success' => false,
                'code'    => 401,
                'error'   => 'UNAUTHORIZED',
                'message' => 'Geçersiz veya süresi dolmuş token',
            ]);

            return;
        }

        $jwt = (string) $_SESSION['member_jwt'];
        $res = MemberLoginService::backendSession($jwt);

        if ($res === null) {
            self::respondJson(503, [
                'success' => false,
                'code'    => 503,
                'message' => 'Oturum doğrulanamadı. Bağlantıyı kontrol edin.',
            ]);

            return;
        }

        $code   = (int) ($res['code'] ?? 200);
        $ok     = !empty($res['success']);
        $unauth = ($code === 401) || (($res['error'] ?? '') === 'UNAUTHORIZED');

        if (!$ok && $unauth) {
            self::stripMemberAuthKeepingCsrf();
            self::respondJson(401, [
                'success' => false,
                'code'    => 401,
                'error'   => (string) ($res['error'] ?? 'UNAUTHORIZED'),
                'message' => (string) ($res['message'] ?? 'Geçersiz veya süresi dolmuş token'),
            ]);

            return;
        }

        if (!$ok) {
            $hc = ($code >= 400 && $code < 600) ? $code : 502;
            http_response_code($hc);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);

            return;
        }

        http_response_code(200);
        echo json_encode($res, JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST /api/auth/logout — Backend logout.php + yerel oturumu sonlandırır (JSON zarf).
     */
    public function logoutMember(): void
    {
        self::jsonHeaders();

        if (!self::assertPostOr405()) {
            return;
        }

        if (!empty($_SESSION['member_jwt'])) {
            MemberLoginService::backendLogout((string) $_SESSION['member_jwt']);
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'code'    => 200,
            'message' => 'Çıkış başarılı. Güle güle!',
            'data'    => new stdClass(),
        ], JSON_UNESCAPED_UNICODE);
    }

    private static function stripMemberAuthKeepingCsrf(): void
    {
        $csrf = $_SESSION['csrf_token'] ?? null;
        $ref  = $_SESSION['referral_code'] ?? null;
        $_SESSION = [];
        if ($csrf !== null) {
            $_SESSION['csrf_token'] = $csrf;
        }
        if ($ref !== null) {
            $_SESSION['referral_code'] = $ref;
        }
    }

    /**
     * @param array<string, mixed> $prepared
     * @return array<string, mixed>
     */
    private static function localRegisterAttempt(array $prepared, string $referralCode, string $bonusCode, string $referredByAffiliateId): array
    {
        try {
            $pdo = self::localDbPdo();
            if (!($pdo instanceof \PDO)) {
                return [
                    'success' => false,
                    'code'    => 503,
                    'message' => 'Kayıt servisi şu anda kullanılamıyor.',
                ];
            }

            $username = trim((string) ($prepared['username'] ?? ''));
            $email = trim((string) ($prepared['email'] ?? ''));
            $password = (string) ($prepared['password'] ?? '');
            $firstName = trim((string) ($prepared['first_name'] ?? ''));
            $surname = trim((string) ($prepared['surname'] ?? ''));
            $country = strtoupper(trim((string) ($prepared['country'] ?? '')));
            $city = trim((string) ($prepared['city'] ?? ''));
            $birthDate = trim((string) ($prepared['birth_date'] ?? ''));
            $identityNumber = trim((string) ($prepared['tc'] ?? ''));
            $phone = trim((string) ($prepared['phone_norm'] ?? ''));
            $address = trim((string) ($prepared['address'] ?? ''));
            $genderApi = trim((string) ($prepared['gender_api'] ?? ''));
            $gender = self::genderLabelFromApiValue($genderApi);

            $check = $pdo->prepare('SELECT username, email, identity_number FROM users WHERE username = :username OR email = :email OR (:identity_number_check <> "" AND identity_number = :identity_number) LIMIT 1');
            $check->execute([
                'username' => $username,
                'email' => $email,
                'identity_number_check' => $identityNumber,
                'identity_number' => $identityNumber,
            ]);
            $exists = $check->fetch(\PDO::FETCH_ASSOC);
            if (is_array($exists)) {
                $errors = [];
                if (strcasecmp((string) ($exists['username'] ?? ''), $username) === 0) {
                    $errors['username'] = 'Bu kullanıcı adı zaten kayıtlı.';
                }
                if (strcasecmp((string) ($exists['email'] ?? ''), $email) === 0) {
                    $errors['email'] = 'Bu e-posta zaten kayıtlı.';
                }
                if ($identityNumber !== '' && (string) ($exists['identity_number'] ?? '') === $identityNumber) {
                    $errors['tc'] = 'Bu kimlik numarası zaten kayıtlı.';
                }

                return [
                    'success' => false,
                    'code' => 409,
                    'error' => 'DUPLICATE_USER',
                    'message' => 'Kullanıcı adı, e-posta veya kimlik numarası zaten kayıtlı.',
                    'errors' => $errors,
                ];
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            if (!is_string($passwordHash) || $passwordHash === '') {
                return [
                    'success' => false,
                    'code' => 500,
                    'message' => 'Şifre işlenemedi. Lütfen tekrar deneyin.',
                ];
            }

            $generatedReferralCode = self::generateUniqueReferralCode($pdo, $username);
            $affiliateId = trim($referredByAffiliateId) !== '' ? (int) $referredByAffiliateId : null;

            $insert = $pdo->prepare(
                'INSERT INTO users
                (name, surname, username, email, identity_number, gender, dob, phone, city, country, password, bonus_code, referral_code, referred_by_affiliate_id, address, password_changed_at)
                VALUES
                (:name, :surname, :username, :email, :identity_number, :gender, :dob, :phone, :city, :country, :password, :bonus_code, :referral_code, :referred_by_affiliate_id, :address, NOW())'
            );
            $insert->execute([
                'name' => $firstName,
                'surname' => $surname,
                'username' => $username,
                'email' => $email,
                'identity_number' => $identityNumber !== '' ? $identityNumber : null,
                'gender' => $gender,
                'dob' => $birthDate,
                'phone' => $phone,
                'city' => $city,
                'country' => $country,
                'password' => $passwordHash,
                'bonus_code' => $bonusCode !== '' ? $bonusCode : null,
                'referral_code' => $generatedReferralCode !== '' ? $generatedReferralCode : null,
                'referred_by_affiliate_id' => $affiliateId,
                'address' => $address !== '' ? $address : null,
            ]);

            $userId = (int) $pdo->lastInsertId();
            require_once SERVICE_PATH . '/MemberJwtService.php';
            $jwt = MemberJwtService::issue($pdo, [
                'id' => $userId,
                'username' => $username,
                'email' => $email,
            ]);
            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $_SESSION['member_jwt'] = $jwt;
            if ($referralCode !== '') {
                $_SESSION['referral_code'] = $referralCode;
            }

            return [
                'success' => true,
                'code' => 201,
                'message' => 'Kayıt başarılı. Hoş geldiniz!',
                'source' => 'local_db',
                'data' => [
                    'token' => $jwt,
                    'user_id' => $userId,
                    'user' => [
                        'username' => $username,
                        'email' => $email,
                    ],
                ],
            ];
        } catch (\PDOException $e) {
            self::registerWriteLog('Local register PDOException: ' . $e->getMessage(), 'ERROR');

            return [
                'success' => false,
                'code' => 500,
                'message' => 'Kayıt sırasında veritabanı hatası oluştu.',
            ];
        } catch (Throwable $e) {
            self::registerWriteLog('Local register exception: ' . $e->getMessage(), 'ERROR');

            return [
                'success' => false,
                'code' => 500,
                'message' => 'Kayıt sırasında beklenmeyen bir hata oluştu.',
            ];
        }
    }

    private static function genderLabelFromApiValue(string $genderApi): string
    {
        $g = strtolower(trim($genderApi));
        if ($g === 'female') {
            return 'Kadın';
        }
        if ($g === 'other') {
            return 'Diğer';
        }

        return 'Erkek';
    }

    private static function generateUniqueReferralCode(\PDO $pdo, string $username): string
    {
        $base = preg_replace('/[^a-z0-9]/i', '', strtolower($username));
        $base = is_string($base) ? $base : '';
        if ($base === '') {
            $base = 'user';
        }
        $base = substr($base, 0, 18);

        for ($i = 0; $i < 5; $i++) {
            $candidate = strtoupper($base . substr(bin2hex(random_bytes(4)), 0, 8));
            $stmt = $pdo->prepare('SELECT 1 FROM users WHERE referral_code = :code LIMIT 1');
            $stmt->execute(['code' => $candidate]);
            if (!$stmt->fetchColumn()) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @return array{success: bool, message: string}
     */
    private static function localLoginAttempt(string $login, string $password): array
    {
        try {
            $pdo = self::localDbPdo();
            if (!($pdo instanceof \PDO)) {
                return ['success' => false, 'message' => 'Kullanıcı adı veya şifre hatalı.'];
            }

            $stmt = $pdo->prepare('SELECT id, username, email, password FROM users WHERE username = :username OR email = :email LIMIT 1');
            $stmt->execute([
                'username' => $login,
                'email' => $login,
            ]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!is_array($user)) {
                return ['success' => false, 'message' => 'Kullanıcı adı veya şifre hatalı.'];
            }

            $hash = (string) ($user['password'] ?? '');
            if ($hash === '') {
                return ['success' => false, 'message' => 'Kullanıcı adı veya şifre hatalı.'];
            }
            $valid = password_verify($password, $hash);
            if (!$valid) {
                return ['success' => false, 'message' => 'Kullanıcı adı veya şifre hatalı.'];
            }

            $_SESSION['loggedin'] = true;
            $_SESSION['user_id'] = (int) ($user['id'] ?? 0);
            $_SESSION['username'] = (string) ($user['username'] ?? $login);
            $_SESSION['email'] = (string) ($user['email'] ?? '');
            require_once SERVICE_PATH . '/MemberJwtService.php';
            $jwt = MemberJwtService::issue($pdo, [
                'id' => (int) ($user['id'] ?? 0),
                'username' => (string) ($user['username'] ?? $login),
                'email' => (string) ($user['email'] ?? ''),
            ]);
            $_SESSION['member_jwt'] = $jwt;
            unset($_SESSION['login_error']);

            return [
                'success' => true,
                'message' => 'Giriş başarılı.',
                'token' => $jwt,
                'user_id' => (int) ($user['id'] ?? 0),
                'user' => [
                    'id' => (int) ($user['id'] ?? 0),
                    'username' => (string) ($user['username'] ?? $login),
                    'email' => (string) ($user['email'] ?? ''),
                ],
            ];
        } catch (Throwable) {
            return ['success' => false, 'message' => 'Kullanıcı adı veya şifre hatalı.'];
        }
    }

    private static function localDbPdo(): ?PDO
    {
        if (!function_exists('frontend_database_allowed') || !frontend_database_allowed()) {
            return null;
        }

        $configPath = BASE_PATH . '/admin/app/Config/admin.php';
        if (!is_file($configPath)) {
            return null;
        }
        $config = require $configPath;
        if (!is_array($config) || !is_array($config['db'] ?? null)) {
            return null;
        }

        return AdminDatabase::connectWithParams($config['db']);
    }
}
