<?php

require_once __DIR__ . '/BackendApiClient.php';

/**
 * Üye API: POST /login.php (JSON envelope, api.md).
 */
final class MemberLoginService
{
    public const MSG_BACKEND_NOT_CONFIGURED = 'Backend API adresi tanımlı değil (config/backend_api.php → API_BACKEND_MAIN_BASE_URL veya API_BACKEND_SLIDER_BASE_URL).';

    public static function login(string $login, string $password): ?array
    {
        return BackendApiClient::request('POST', BackendApiClient::SVC_MAIN, '/login.php', [], [
            'login'    => $login,
            'password' => $password,
        ]);
    }

    /** GET /session.php — Bearer üye JWT (api.md). */
    public static function backendSession(string $jwt): ?array
    {
        return BackendApiClient::requestWithMemberBearer(
            'GET',
            BackendApiClient::SVC_MAIN,
            '/session.php',
            $jwt
        );
    }

    /** POST /forgot_password.php — public, JSON { "email" } (api.md). */
    public static function forgotPassword(string $email): ?array
    {
        return BackendApiClient::request(
            'POST',
            BackendApiClient::SVC_MAIN,
            '/forgot_password.php',
            [],
            ['email' => $email]
        );
    }

    /**
     * POST /reset_password.php — public, JSON { token, password, password_confirmation } (api.md).
     *
     * @param array{token:string,password:string,password_confirmation:string} $body
     */
    public static function resetPassword(array $body): ?array
    {
        return BackendApiClient::request(
            'POST',
            BackendApiClient::SVC_MAIN,
            '/reset_password.php',
            [],
            $body
        );
    }

    /**
     * POST /password_reset.php — forgot + reset tek uç; action: request|forgot | confirm|reset (api.md).
     *
     * @param array<string, mixed> $body
     */
    public static function passwordReset(array $body): ?array
    {
        return BackendApiClient::request(
            'POST',
            BackendApiClient::SVC_MAIN,
            '/password_reset.php',
            [],
            $body
        );
    }

    /**
     * GET veya POST /email_verification.php — action: request|resend|confirm|verify (api.md).
     *
     * @param array<string, string|int|float> $query GET sorgu parametreleri
     * @param array<string, mixed>|null       $body POST JSON gövdesi; GET için null
     */
    public static function emailVerification(string $httpMethod, array $query, ?array $body): ?array
    {
        $m = strtoupper($httpMethod);
        if ($m === 'GET') {
            return BackendApiClient::request(
                'GET',
                BackendApiClient::SVC_MAIN,
                '/email_verification.php',
                $query,
                null
            );
        }
        if ($m === 'POST') {
            return BackendApiClient::request(
                'POST',
                BackendApiClient::SVC_MAIN,
                '/email_verification.php',
                [],
                $body ?? []
            );
        }

        return null;
    }

    /** POST /logout.php — Bearer üye JWT (api.md). Gövde zorunlu değil; boş JSON gönderilir. */
    public static function backendLogout(string $jwt): ?array
    {
        return BackendApiClient::requestWithMemberBearer(
            'POST',
            BackendApiClient::SVC_MAIN,
            '/logout.php',
            $jwt,
            [],
            new stdClass()
        );
    }

    public static function envelopeSucceeded(?array $res, int $successCode): bool
    {
        if (!is_array($res) || empty($res['success'])) {
            return false;
        }

        return (int) ($res['code'] ?? 0) === $successCode;
    }

    public static function succeeded(?array $res): bool
    {
        return self::envelopeSucceeded($res, 200);
    }

    /**
     * Zarfda mesaj yoksa $whenEmpty döner (bağlantı hatası için null giriş).
     */
    public static function envelopeMessageOr(?array $res, string $whenEmpty): string
    {
        if ($res === null) {
            return 'Bağlantı hatası. Lütfen tekrar deneyin.';
        }
        $msg = trim((string) ($res['message'] ?? ''));

        return $msg !== '' ? $msg : $whenEmpty;
    }

    public static function applySession(array $res, string $loginFallback): void
    {
        $data = BackendApiClient::unwrap($res);
        $user = isset($data['user']) && is_array($data['user']) ? $data['user'] : [];
        $token = trim((string) ($data['token'] ?? ''));

        $_SESSION['loggedin'] = true;
        $_SESSION['username'] = (string) ($user['username'] ?? $loginFallback);
        if (isset($data['user_id'])) {
            $_SESSION['user_id'] = (int) $data['user_id'];
        }
        if ($token !== '') {
            $_SESSION['member_jwt'] = $token;
        }
        if (isset($user['email'])) {
            $_SESSION['email'] = (string) $user['email'];
        }
        if (isset($_SESSION['login_error'])) {
            unset($_SESSION['login_error']);
        }
    }

    public static function failureMessage(?array $res): string
    {
        if ($res === null) {
            return 'Bağlantı hatası. Lütfen tekrar deneyin.';
        }
        $msg = trim((string) ($res['message'] ?? ''));
        if ($msg !== '') {
            return $msg;
        }
        $code = (int) ($res['code'] ?? 0);
        if ($code === 429 || ($res['error'] ?? '') === 'RATE_LIMITED') {
            return 'Çok fazla hatalı giriş denemesi. Lütfen daha sonra tekrar deneyin.';
        }

        return 'Kullanıcı adı veya şifre hatalı!';
    }
}
