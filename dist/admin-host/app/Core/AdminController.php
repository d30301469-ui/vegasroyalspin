<?php

declare(strict_types=1);

class AdminController
{
    protected array $config;

    public function __construct()
    {
        $this->config = require ADMIN_APP_PATH . '/Config/admin.php';
    }

    protected function requireAuth(): void
    {
        if (!AdminAuth::check()) {
            $this->redirect(AdminAuth::url('/login'));
        }
    }

    protected function requirePermission(string $permissionKey): void
    {
        $this->requireAuth();
        if (!AdminAuth::can($permissionKey)) {
            $this->forbidden();
        }
    }

    protected function forbidden(string $message = 'Bu işlem için gerekli yetkiye sahip değilsiniz.'): never
    {
        http_response_code(403);
        $this->view('errors/403', [
            'title' => 'Erişim engellendi',
            'errorMessage' => $message,
        ], 'app');
        exit;
    }

    public function view(string $view, array $data = [], string $layout = 'app'): void
    {
        $config = $this->config;
        self::ensureSiteContextLoaded();
        // Giriş ekranı gibi oturum öncesi sayfalarda veritabanına gidilmez.
        $site = $data['site'] ?? ($layout === 'auth'
            ? AdminSiteContext::staticContext()
            : AdminSiteContext::globals());
        unset($data['site']);

        $viewFile = ADMIN_VIEW_PATH . '/' . $view . '.php';
        if (!is_file($viewFile)) {
            $this->renderMissingFile('view', $view . '.php', $viewFile);
            return;
        }

        $layoutFile = ADMIN_VIEW_PATH . '/layouts/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            $this->renderMissingFile('layout', 'layouts/' . $layout . '.php', $layoutFile);
            return;
        }

        extract($data);
        require $layoutFile;
    }

    /**
     * Bootstrap eksik/eski olsa bile AdminSiteContext sınıfının yüklü olmasını garanti eder.
     */
    private static function ensureSiteContextLoaded(): void
    {
        if (class_exists('AdminSiteContext', false)) {
            return;
        }
        if (defined('ADMIN_APP_PATH')) {
            $file = ADMIN_APP_PATH . '/Services/AdminSiteContext.php';
            if (is_file($file)) {
                require_once $file;
            }
        }
    }

    private function renderMissingFile(string $kind, string $relative, string $absolute): void
    {
        http_response_code(500);
        error_log(sprintf('[admin] %s not found: %s', $kind, $absolute));
        echo 'Admin ' . $kind . ' bulunamadı: ' . htmlspecialchars($relative, ENT_QUOTES, 'UTF-8')
            . '<br>Beklenen yol: ' . htmlspecialchars($absolute, ENT_QUOTES, 'UTF-8')
            . '<br>Lütfen bu dosyanın sunucuya tam olarak yüklendiğinden emin olun.';
    }

    protected function partial(string $view, array $data = []): void
    {
        $config = $this->config;
        self::ensureSiteContextLoaded();
        $site = $data['site'] ?? AdminSiteContext::globals();
        unset($data['site']);
        $viewFile = ADMIN_VIEW_PATH . '/' . $view . '.php';
        if (!is_file($viewFile)) {
            $this->renderMissingFile('partial', $view . '.php', $viewFile);
            return;
        }

        extract($data);
        require $viewFile;
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}
