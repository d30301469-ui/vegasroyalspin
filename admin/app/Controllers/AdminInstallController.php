<?php

declare(strict_types=1);

/**
 * Admin web kurulum sihirbazı — /install
 */
final class AdminInstallController
{
    private string $root;

    public function __construct(string $root)
    {
        $this->root = AdminInstallGate::root($root);
    }

    public function dispatch(string $method, string $path = '/install'): void
    {
        $path = '/' . trim($path, '/');
        if ($path === '/install/complete') {
            $this->showComplete();
            return;
        }

        if (AdminInstallGate::isInstalled($this->root)) {
            header('Location: /login');
            exit;
        }

        if ($method === 'POST' && ($_POST['action'] ?? '') === 'test-db') {
            $this->jsonTestDatabase();
            return;
        }

        if ($method === 'POST' && ($_POST['action'] ?? '') === 'install') {
            $this->handleInstall();
            return;
        }

        $this->showWizard();
    }

    private function showWizard(?string $error = null, ?string $success = null): void
    {
        $installer = new AdminInstaller($this->root);
        $checks = $installer->checkRequirements();
        $requirementsOk = $installer->requirementsPassed();

        $host = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
        if (is_readable($this->root . '/config/cloudflare.php')) {
            require_once $this->root . '/config/cloudflare.php';
        }
        $backendUrlDefault = function_exists('metropol_build_public_origin_url')
            ? metropol_build_public_origin_url($host)
            : (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
                ? 'https'
                : 'http') . '://' . $host;
        $defaults = [
            'db_host' => '127.0.0.1',
            'db_port' => '3306',
            'backend_url' => function_exists('metropol_coerce_public_https_url')
                ? metropol_coerce_public_https_url($backendUrlDefault)
                : $backendUrlDefault,
            'frontend_url' => function_exists('metropol_coerce_public_https_url')
                ? metropol_coerce_public_https_url('https://vegasroyalspin.com')
                : 'https://vegasroyalspin.com',
            'site_name' => 'Vegas Royal Spin',
        ];

        $csrf = AdminInstallGate::csrfToken($this->root);
        $title = 'Admin Kurulum';
        $panelName = getenv('ADMIN_PANEL_NAME') ?: 'Backoffice';
        $siteName = $defaults['site_name'];
        $seedAvailable = SqlSeedImporter::isAvailable($this->root);
        $seedSize = SqlSeedImporter::humanSize($this->root);

        require $this->root . '/app/Views/install/wizard.php';
    }

    private function handleInstall(): void
    {
        if (!AdminInstallGate::verifyCsrfToken($this->root, isset($_POST['_token']) ? (string) $_POST['_token'] : null)) {
            $this->showWizard('Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.');
            return;
        }

        $installer = new AdminInstaller($this->root);
        $result = $installer->run($_POST);

        if (!$result['success']) {
            $this->showWizard($result['message']);
            return;
        }

        if (!empty($result['member_jwt_secret']) && is_string($result['member_jwt_secret'])) {
            $_SESSION['admin_install_jwt_hint'] = $result['member_jwt_secret'];
        }
        if (!empty($result['frontend_cms_purge_secret']) && is_string($result['frontend_cms_purge_secret'])) {
            $_SESSION['admin_install_purge_hint'] = $result['frontend_cms_purge_secret'];
        }
        if (!empty($result['admin_email']) && is_string($result['admin_email'])) {
            $_SESSION['admin_install_admin_email'] = $result['admin_email'];
        }
        if (!empty($result['admin_username']) && is_string($result['admin_username'])) {
            $_SESSION['admin_install_admin_username'] = $result['admin_username'];
        }

        header('Location: /install/complete');
        exit;
    }

    private function showComplete(): void
    {
        if (!AdminInstallGate::isInstalled($this->root)) {
            header('Location: /install');
            exit;
        }

        $adminEmail = trim((string) ($_SESSION['admin_install_admin_email'] ?? ''));
        $adminUsername = trim((string) ($_SESSION['admin_install_admin_username'] ?? ''));
        $jwtHint = trim((string) ($_SESSION['admin_install_jwt_hint'] ?? ''));
        $purgeHint = trim((string) ($_SESSION['admin_install_purge_hint'] ?? ''));
        unset(
            $_SESSION['admin_install_admin_email'],
            $_SESSION['admin_install_admin_username'],
            $_SESSION['admin_install_jwt_hint'],
            $_SESSION['admin_install_purge_hint']
        );

        $title = 'Kurulum Tamamlandı';
        require $this->root . '/app/Views/install/complete.php';
    }

    private function jsonTestDatabase(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if (!AdminInstallGate::verifyCsrfToken($this->root, isset($_POST['_token']) ? (string) $_POST['_token'] : null)) {
            echo json_encode(['ok' => false, 'message' => 'CSRF doğrulaması başarısız. Sayfayı yenileyin.'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $installer = new AdminInstaller($this->root);
            $installer->testDatabase([
                'host' => trim((string) ($_POST['db_host'] ?? '127.0.0.1')),
                'port' => trim((string) ($_POST['db_port'] ?? '3306')),
                'database' => trim((string) ($_POST['db_database'] ?? '')),
                'username' => trim((string) ($_POST['db_username'] ?? '')),
                'password' => (string) ($_POST['db_password'] ?? ''),
            ]);
            echo json_encode(['ok' => true, 'message' => 'Veritabanı bağlantısı başarılı.'], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $exception) {
            echo json_encode(['ok' => false, 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }
}
