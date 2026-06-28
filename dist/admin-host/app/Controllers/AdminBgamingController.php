<?php

declare(strict_types=1);

final class AdminBgamingController extends AdminController
{
    public function settings(): void
    {
        $this->requirePermission('bgaming-settings');
        $pdo = AdminDatabase::pdo();
        BgamingService::bootstrap($pdo);
        $this->view('bgaming/settings', [
            'title' => 'BGaming Ayarları',
            'active' => 'datatable',
            'moduleKey' => 'bgaming-settings',
            'crumbs' => 'Games | BGaming Settings',
            'configRow' => BgamingService::config($pdo),
            'gamesCount' => (int) $pdo->query('SELECT COUNT(*) FROM bgaming_games')->fetchColumn(),
            'transactionsCount' => (int) $pdo->query('SELECT COUNT(*) FROM bgaming_transactions')->fetchColumn(),
            'flash' => $this->pullFlash(),
        ]);
    }

    public function updateSettings(): void
    {
        $this->requirePermission('bgaming-settings');
        $this->ensurePost();
        BgamingService::updateConfig(AdminDatabase::pdo(), $_POST);
        $this->flash('BGaming ayarları güncellendi.');
        $this->redirect(AdminAuth::url('/bgaming/settings'));
    }

    public function syncGames(): void
    {
        $this->requirePermission('bgaming-settings');
        $this->ensurePost();
        try {
            $result = BgamingService::syncGames(AdminDatabase::pdo());
            $this->flash('BGaming oyun sync tamamlandı: ' . (int) ($result['count'] ?? 0) . ' kayıt.');
        } catch (Throwable $exception) {
            $this->flash('BGaming oyun sync hatası: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/bgaming/settings'));
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
