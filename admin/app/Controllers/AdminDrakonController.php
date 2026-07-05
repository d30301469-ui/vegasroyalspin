<?php

declare(strict_types=1);

final class AdminDrakonController extends AdminController
{
    public function settings(): void
    {
        $this->requirePermission('drakon-settings');
        $pdo = AdminDatabase::pdo();
        DrakonService::bootstrap($pdo);

        $gamesCount = 0;
        $transactionsCount = 0;
        try {
            $gamesCount        = (int) $pdo->query('SELECT COUNT(*) FROM drakon_games')->fetchColumn();
            $transactionsCount = (int) $pdo->query('SELECT COUNT(*) FROM drakon_transactions')->fetchColumn();
        } catch (Throwable) {}

        $this->view('drakon/settings', [
            'title'             => 'Drakon Ayarları',
            'active'            => 'datatable',
            'moduleKey'         => 'drakon-settings',
            'crumbs'            => 'Games | Drakon Settings',
            'configRow'         => DrakonService::config($pdo),
            'gamesCount'        => $gamesCount,
            'transactionsCount' => $transactionsCount,
            'flash'             => $this->pullFlash(),
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
        @set_time_limit(0);
        try {
            $result = DrakonService::syncProviders(AdminDatabase::pdo());
            $this->flash('Drakon sağlayıcı sync tamamlandı: ' . (int) ($result['count'] ?? 0) . ' kayıt.');
        } catch (Throwable $exception) {
            $this->flash('Drakon sağlayıcı sync hatası: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/drakon/settings'));
    }

    public function syncGames(): void
    {
        $this->requirePermission('drakon-settings');
        $this->ensurePost();
        @set_time_limit(0);
        try {
            $result = DrakonService::syncGames(AdminDatabase::pdo());
            $this->flash('Drakon oyun sync tamamlandı: ' . (int) ($result['count'] ?? 0) . ' kayıt.');
        } catch (Throwable $exception) {
            $this->flash('Drakon oyun sync hatası: ' . $exception->getMessage());
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
