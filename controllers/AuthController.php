<?php

require_once SERVICE_PATH . '/BackendApiClient.php';
require_once SERVICE_PATH . '/MemberLoginService.php';

class AuthController extends Controller
{
    public function login(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
            $this->processLogin();
            return;
        }
        $this->view('pages/login');
    }

    private function processLogin(): void
    {
        $username_input = trim((string) $_POST['username']);
        $password_input = (string) $_POST['password'];

        if ($username_input === '' && $password_input === '') {
            $this->view('pages/login');
            return;
        }

        $res = MemberLoginService::login($username_input, $password_input);

        if (MemberLoginService::succeeded($res)) {
            MemberLoginService::applySession($res, $username_input);
            $this->redirect('/');
            return;
        }

        if ($res === null && BackendApiClient::effectiveMainBaseUrl() === '') {
            $_SESSION['login_error'] = MemberLoginService::MSG_BACKEND_NOT_CONFIGURED;
        } else {
            $_SESSION['login_error'] = MemberLoginService::failureMessage($res);
        }
        $this->view('pages/login');
    }

    public function register(): void
    {
        $this->view('pages/register');
    }

    /** E-postadaki bağlantı: /reset-password?token=... veya ?reset_token=... */
    public function resetPasswordPage(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
        $resetToken = trim((string) ($_GET['token'] ?? $_GET['reset_token'] ?? ''));
        $this->view('pages/reset-password', compact('resetToken'));
    }

    public function logout(): void
    {
        if (!empty($_SESSION['member_jwt'])) {
            MemberLoginService::backendLogout((string) $_SESSION['member_jwt']);
        }

        if (is_readable(CONFIG_PATH . '/member_api_public.php')) {
            require_once CONFIG_PATH . '/member_api_public.php';
        }
        if (function_exists('frontend_clear_member_session')) {
            frontend_clear_member_session();
        }

        // Yalnızca FRONTSESSID yok edilir; ADMINSESSID (admin paneli) korunur.
        $frontendName = function_exists('app_frontend_session_name') ? app_frontend_session_name() : 'FRONTSESSID';
        $hasAdmin = function_exists('app_session_has_admin_user') && app_session_has_admin_user();
        if (session_name() === $frontendName && !$hasAdmin) {
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
        }
        $this->redirect('/?logout=1');
    }

    /**
     * Pazarlama e-postası abonelikten çıkma: /unsubscribe?e=...&t=...
     * One-click (RFC 8058): aynı URL'ye POST List-Unsubscribe=One-Click
     */
    public function unsubscribe(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        require_once BASE_PATH . '/admin/app/Services/Mailer.php';
        if (!class_exists('AdminDatabase', false)) {
            require_once BASE_PATH . '/admin/app/Core/AdminDatabase.php';
        }

        $email = strtolower(trim((string) ($_GET['e'] ?? $_POST['e'] ?? '')));
        $token = trim((string) ($_GET['t'] ?? $_POST['t'] ?? ''));
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $oneClick = false;
        if ($method === 'POST') {
            $listUnsub = (string) ($_POST['List-Unsubscribe'] ?? $_POST['list-unsubscribe'] ?? '');
            if (strcasecmp($listUnsub, 'One-Click') === 0) {
                $oneClick = true;
            } else {
                $rawBody = (string) @file_get_contents('php://input');
                $oneClick = stripos($rawBody, 'List-Unsubscribe=One-Click') !== false;
            }
        }

        $valid = mail_verify_unsubscribe_token($email, $token);
        $done = false;
        $error = '';

        if ($valid && ($method === 'POST' || $oneClick || isset($_POST['confirm']))) {
            try {
                $pdo = AdminDatabase::pdo();
                $done = mail_mark_unsubscribed($pdo, $email, $oneClick ? 'one-click' : 'link');
                if (!$done) {
                    $error = 'Kayıt yapılamadı. Lütfen daha sonra tekrar deneyin.';
                }
            } catch (Throwable $e) {
                $error = 'Sistem geçici olarak kullanılamıyor.';
            }
        } elseif (!$valid) {
            $error = 'Geçersiz veya süresi dolmuş abonelikten çıkma bağlantısı.';
        }

        // One-click sağlayıcıları için kısa 200 yanıtı yeterli.
        if ($oneClick) {
            http_response_code($done ? 200 : ($valid ? 500 : 400));
            header('Content-Type: text/plain; charset=UTF-8');
            echo $done ? 'OK' : ($error !== '' ? $error : 'FAILED');
            return;
        }

        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safeToken = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Abonelikten Çık</title>'
            . '<style>body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#0a0719;color:#dcccf3;}'
            . '.box{max-width:480px;margin:48px auto;padding:28px 22px;background:#12082f;border:1px solid #6b2a78;border-radius:16px;}'
            . 'h1{margin:0 0 12px;font-size:22px;color:#fff;}p{line-height:1.6;font-size:14px;}'
            . 'button{margin-top:12px;background:#850f83;color:#fff;border:0;border-radius:10px;padding:12px 18px;font-weight:700;cursor:pointer;width:100%;}'
            . '.ok{color:#9dffa8;}.err{color:#ffb4c0;}</style></head><body><div class="box">';
        if ($done) {
            echo '<h1>Abonelik iptal edildi</h1><p class="ok">'
                . htmlspecialchars($email !== '' ? ($email . ' için pazarlama e-postaları durduruldu.') : 'Pazarlama e-postaları durduruldu.', ENT_QUOTES, 'UTF-8')
                . '</p>';
        } elseif ($error !== '') {
            echo '<h1>Abonelikten çıkılamadı</h1><p class="err">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';
        } else {
            echo '<h1>Abonelikten çık</h1><p>Pazarlama e-postalarını durdurmak için onaylayın'
                . ($safeEmail !== '' ? ' (<strong>' . $safeEmail . '</strong>)' : '')
                . '.</p>'
                . '<form method="post" action="/unsubscribe?e=' . rawurlencode($email) . '&t=' . rawurlencode($token) . '">'
                . '<input type="hidden" name="e" value="' . $safeEmail . '">'
                . '<input type="hidden" name="t" value="' . $safeToken . '">'
                . '<input type="hidden" name="confirm" value="1">'
                . '<button type="submit">Abonelikten çık</button></form>';
        }
        echo '<p style="margin-top:18px;font-size:12px;color:#8f7aa8;">Vegasroyalspin</p></div></body></html>';
    }
}
