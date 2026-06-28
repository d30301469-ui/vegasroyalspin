<?php

declare(strict_types=1);

/**
 * Frontend web kurulum sihirbazı — /install
 */
final class FrontendInstallController
{
    private string $root;

    public function __construct(string $root)
    {
        $this->root = FrontendInstallGate::root($root);
    }

    public function dispatch(string $method, string $path = '/install'): void
    {
        $path = '/' . trim($path, '/');
        if ($path === '/install/complete') {
            $this->showComplete();
            return;
        }

        if (FrontendInstallGate::isInstalled($this->root)) {
            header('Location: /install/complete');
            exit;
        }

        if ($method === 'POST' && ($_POST['action'] ?? '') === 'test-backend') {
            $this->jsonTestBackend();
            return;
        }

        if ($method === 'POST' && ($_POST['action'] ?? '') === 'install') {
            $this->handleInstall();
            return;
        }

        $this->showWizard();
    }

    private function showWizard(?string $error = null): void
    {
        $installer = new FrontendInstaller($this->root);
        $checks = $installer->checkRequirements();
        $requirementsOk = $installer->requirementsPassed();

        $host = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
        if (!is_readable($this->root . '/config/cloudflare.php')) {
            require_once $this->root . '/config/cloudflare.php';
        }
        $frontendUrl = function_exists('metropol_build_public_origin_url')
            ? metropol_build_public_origin_url($host)
            : (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
                ? 'https'
                : 'http') . '://' . $host;
        $frontendHost = strtolower($host);
        $baseHost = $frontendHost;
        if (str_starts_with($baseHost, 'www.')) {
            $baseHost = substr($baseHost, 4);
        }
        if (str_starts_with($baseHost, 'm.')) {
            $baseHost = substr($baseHost, 2);
        }

        $defaults = [
            'frontend_url' => $frontendUrl,
            'backend_url' => function_exists('metropol_coerce_public_https_url')
                ? metropol_coerce_public_https_url('https://bo-nexthub.site')
                : 'https://bo-nexthub.site',
            'live_support_url' => 'https://direct.lc.chat/19301899/',
            'telegram_url' => 'https://t.me',
            'whatsapp_url' => '',
            'session_cookie_domain' => FrontendInstaller::sessionCookieDomain($frontendHost),
            'public_url_hosts' => implode(', ', FrontendInstaller::frontendHostVariants($frontendUrl)),
            'app_key' => FrontendInstaller::generateSecret(48),
        ];

        $csrf = FrontendInstallGate::csrfToken($this->root);
        $title = 'Frontend Kurulum';
        $siteName = 'Vegas Royal Spin';

        require $this->root . '/app/Views/install/wizard.php';
    }

    private function handleInstall(): void
    {
        if (!FrontendInstallGate::verifyCsrfToken($this->root, isset($_POST['_token']) ? (string) $_POST['_token'] : null)) {
            $this->showWizard('Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.');
            return;
        }

        $installer = new FrontendInstaller($this->root);
        $result = $installer->run($_POST);

        if (!$result['success']) {
            $this->showWizard($result['message']);
            return;
        }

        $_SESSION['frontend_install_frontend_url'] = trim((string) ($_POST['frontend_url'] ?? ''));
        $_SESSION['frontend_install_backend_url'] = trim((string) ($_POST['backend_url'] ?? ''));
        $_SESSION['frontend_install_backend_verified'] = empty($_POST['skip_backend_test']) ? '1' : '0';
        $_SESSION['frontend_install_message'] = (string) ($result['message'] ?? '');

        header('Location: /install/complete');
        exit;
    }

    private function showComplete(): void
    {
        if (!FrontendInstallGate::isInstalled($this->root)) {
            header('Location: /install');
            exit;
        }

        $frontendUrl = trim((string) ($_SESSION['frontend_install_frontend_url'] ?? ''));
        $backendUrl = trim((string) ($_SESSION['frontend_install_backend_url'] ?? ''));
        $backendVerified = (string) ($_SESSION['frontend_install_backend_verified'] ?? '1') === '1';
        $installMessage = trim((string) ($_SESSION['frontend_install_message'] ?? ''));
        unset(
            $_SESSION['frontend_install_frontend_url'],
            $_SESSION['frontend_install_backend_url'],
            $_SESSION['frontend_install_backend_verified'],
            $_SESSION['frontend_install_message']
        );

        if ($frontendUrl === '' && is_readable($this->root . '/.env')) {
            FrontendInstallGate::loadEnv($this->root);
            $frontendUrl = trim((string) (getenv('FRONTEND_URL') ?: getenv('SITE_URL') ?: ''));
            $backendUrl = trim((string) (getenv('BACKEND_URL') ?: ''));
        }

        $title = 'Kurulum Tamamlandı';
        require $this->root . '/app/Views/install/complete.php';
    }

    private function jsonTestBackend(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if (!FrontendInstallGate::verifyCsrfToken($this->root, isset($_POST['_token']) ? (string) $_POST['_token'] : null)) {
            echo json_encode(['ok' => false, 'message' => 'CSRF doğrulaması başarısız. Sayfayı yenileyin.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $installer = new FrontendInstaller($this->root);
        $result = $installer->testBackendConnection($_POST);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    }
}
