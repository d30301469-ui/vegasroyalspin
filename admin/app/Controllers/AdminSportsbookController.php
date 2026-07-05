<?php

declare(strict_types=1);

final class AdminSportsbookController extends AdminController
{
    public function settings(): void
    {
        $this->requirePermission('sportsbook-settings');
        $pdo = AdminDatabase::pdo();
        SportsbookService::bootstrap($pdo);

        $sessionsCount     = 0;
        $transactionsCount = 0;
        try {
            $sessionsCount     = (int) $pdo->query('SELECT COUNT(*) FROM sportsbook_sessions')->fetchColumn();
            $transactionsCount = (int) $pdo->query('SELECT COUNT(*) FROM sportsbook_transactions')->fetchColumn();
        } catch (Throwable) {
        }

        $this->view('sportsbook/settings', [
            'title'             => 'Sportsbook Ayarları',
            'active'            => 'datatable',
            'moduleKey'         => 'sportsbook-settings',
            'crumbs'            => 'Spor | Sportsbook Settings',
            'configRow'         => SportsbookService::config($pdo),
            'sessionsCount'     => $sessionsCount,
            'transactionsCount' => $transactionsCount,
            'flash'             => $this->pullFlash(),
        ]);
    }

    public function updateSettings(): void
    {
        $this->requirePermission('sportsbook-settings');
        $this->ensurePost();
        SportsbookService::updateConfig(AdminDatabase::pdo(), $_POST);
        $this->flash('Sportsbook ayarları güncellendi.');
        $this->redirect(AdminAuth::url('/sportsbook/settings'));
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
