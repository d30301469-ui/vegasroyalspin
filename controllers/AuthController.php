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

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        session_destroy();
        $this->redirect('/?logout=1');
    }
}
