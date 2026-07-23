<?php

declare(strict_types=1);

final class AdminAuthController extends AdminController
{
    public function login(): void
    {
        if (AdminAuth::check()) {
            $this->redirect(AdminAuth::url(AdminAuth::postLoginPath()));
        }

        $jwtHint = $_SESSION['admin_install_jwt_hint'] ?? null;
        $purgeHint = $_SESSION['admin_install_purge_hint'] ?? null;
        unset($_SESSION['admin_install_jwt_hint'], $_SESSION['admin_install_purge_hint']);

        $this->view('auth/login', [
            'title' => 'Admin Giriş',
            'error' => $_SESSION['admin_login_error'] ?? null,
            'installed' => isset($_GET['installed']) && (string) $_GET['installed'] === '1',
            'jwtHint' => is_string($jwtHint) ? $jwtHint : null,
            'purgeHint' => is_string($purgeHint) ? $purgeHint : null,
        ], 'auth');
        unset($_SESSION['admin_login_error']);
    }

    public function authenticate(): void
    {
        if (!AdminRequest::isPost()) {
            $_SESSION['admin_login_error'] = 'Oturum doğrulaması başarısız. Lütfen tekrar deneyin.';
            $this->redirect(AdminAuth::url('/login'));
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (AdminAuth::attempt($email, $password)) {
            $this->redirect(AdminAuth::url(AdminAuth::postLoginPath()));
        }

        if (!isset($_SESSION['admin_login_error'])) {
            $_SESSION['admin_login_error'] = 'Email veya şifre hatalı.';
        }
        $this->redirect(AdminAuth::url('/login'));
    }

    public function logout(): void
    {
        if (AdminRequest::isPost() && AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            AdminAuth::logout();
        }

        $this->redirect(AdminAuth::url('/login'));
    }
}
