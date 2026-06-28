<?php

declare(strict_types=1);

final class AdminDrakonController extends AdminController
{
    public function settings(): void
    {
        $this->requirePermission('drakon-settings');
        $pdo = AdminDatabase::pdo();
        $providersCount = 0;
        $gamesCount = 0;
        try {
            $providersCount = (int) $pdo->query('SELECT COUNT(*) FROM drakon_providers')->fetchColumn();
            $gamesCount = (int) $pdo->query('SELECT COUNT(*) FROM drakon_games')->fetchColumn();
        } catch (Throwable) {
        }
        $this->view('drakon/settings', [
            'title' => 'Drakon Ayarları',
            'active' => 'datatable',
            'moduleKey' => 'drakon-settings',
            'crumbs' => 'Games | Drakon Settings',
            'configRow' => DrakonService::config($pdo),
            'integrationDiagnostics' => DrakonService::integrationDiagnostics($pdo),
            'providersCount' => $providersCount,
            'gamesCount' => $gamesCount,
            'flash' => $this->pullFlash(),
        ]);
    }

    public function updateSettings(): void
    {
        $this->requirePermission('drakon-settings');
        $this->ensurePost();
        DrakonService::updateConfig(AdminDatabase::pdo(), $_POST);
        $this->flash('Drakon ayarları güncellendi.');
        $this->redirect(AdminAuth::url('/drakon/settings'));
    }

    public function syncProviders(): void
    {
        $this->requirePermission('drakon-settings');
        $this->ensurePost();
        try {
            $result = DrakonService::syncProviders(AdminDatabase::pdo());
            $this->flash('Provider sync tamamlandı: ' . (int) ($result['count'] ?? 0) . ' kayıt.');
        } catch (Throwable $exception) {
            $this->flash('Provider sync hatası: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/drakon/settings'));
    }

    public function syncGames(): void
    {
        $this->requirePermission('drakon-settings');
        $this->ensurePost();
        try {
            $result = DrakonService::syncGames(AdminDatabase::pdo());
            $this->flash('Oyun sync tamamlandı: ' . (int) ($result['count'] ?? 0) . ' kayıt.');
        } catch (Throwable $exception) {
            $this->flash('Oyun sync hatası: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/drakon/settings'));
    }

    public function testWebhook(): void
    {
        $this->requirePermission('drakon-settings');
        $this->ensurePost();
        $result = DrakonService::testWebhookIntegration(AdminDatabase::pdo(), 1);
        if (!empty($result['ok'])) {
            $this->flash('Webhook testi başarılı: ' . (string) ($result['probe_url'] ?? ''));
        } else {
            $this->flash('Webhook testi başarısız: ' . (string) ($result['message'] ?? 'Bilinmeyen hata'));
        }
        $this->redirect(AdminAuth::url('/drakon/settings'));
    }

    private function ensurePost(): void
    {
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }
    }

    private function flash(string $message): void
    {
        $_SESSION['admin_flash'] = $message;
    }

    private function pullFlash(): string
    {
        $message = (string) ($_SESSION['admin_flash'] ?? '');
        unset($_SESSION['admin_flash']);
        return $message;
    }
}
