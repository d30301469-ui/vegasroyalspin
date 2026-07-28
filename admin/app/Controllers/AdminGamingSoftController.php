<?php

declare(strict_types=1);

final class AdminGamingSoftController extends AdminController
{
    public function settings(): void
    {
        $this->requirePermission('gamingsoft-settings');
        $pdo = AdminDatabase::pdo();
        GamingSoftService::bootstrap($pdo);

        $productsCount = 0;
        $gamesCount = 0;
        $sessionsCount = 0;
        $transactionsCount = 0;
        try {
            $productsCount = (int) $pdo->query('SELECT COUNT(*) FROM gamingsoft_products')->fetchColumn();
            $gamesCount = (int) $pdo->query('SELECT COUNT(*) FROM gamingsoft_games')->fetchColumn();
            $sessionsCount = (int) $pdo->query('SELECT COUNT(*) FROM gamingsoft_sessions')->fetchColumn();
            $transactionsCount = (int) $pdo->query('SELECT COUNT(*) FROM gamingsoft_transactions')->fetchColumn();
        } catch (Throwable) {
        }

        $this->view('gamingsoft/settings', [
            'title'             => 'Gaming Soft (GSC+) Ayarları',
            'active'            => 'datatable',
            'moduleKey'         => 'gamingsoft-settings',
            'crumbs'            => 'Games | Gaming Soft',
            'configRow'         => GamingSoftService::config($pdo),
            'callbackUrl'       => GamingSoftService::callbackBaseUrl($pdo),
            'productsCount'     => $productsCount,
            'gamesCount'        => $gamesCount,
            'activeGamesCount'  => (int) $pdo->query('SELECT COUNT(*) FROM gamingsoft_games WHERE is_active = 1')->fetchColumn(),
            'sessionsCount'     => $sessionsCount,
            'transactionsCount' => $transactionsCount,
            'flash'             => $this->pullFlash(),
        ]);
    }

    public function updateSettings(): void
    {
        $this->requirePermission('gamingsoft-settings');
        $this->ensurePost();
        GamingSoftService::updateConfig(AdminDatabase::pdo(), $_POST);
        $this->flash('Gaming Soft ayarları güncellendi.');
        $this->redirect(AdminAuth::url('/gamingsoft/settings'));
    }

    public function syncProducts(): void
    {
        $this->requirePermission('gamingsoft-settings');
        $this->ensurePost();
        try {
            $result = GamingSoftService::syncProducts(AdminDatabase::pdo());
            $this->flash('Product sync tamamlandı: ' . (int) ($result['product_count'] ?? 0) . ' ürün.');
        } catch (Throwable $exception) {
            $this->flash('Product sync hatası: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/gamingsoft/settings'));
    }

    public function syncGames(): void
    {
        $this->requirePermission('gamingsoft-settings');
        $this->ensurePost();
        try {
            $result = GamingSoftService::syncGames(AdminDatabase::pdo());
            if (class_exists('SlotGamesQuery', false)) {
                SlotGamesQuery::purgeCache();
            } elseif (is_file(dirname(__DIR__, 3) . '/services/SlotGamesQuery.php')) {
                require_once dirname(__DIR__, 3) . '/services/SlotGamesQuery.php';
                SlotGamesQuery::purgeCache();
            }
            $msg = 'Oyun sync tamamlandı: ' . (int) ($result['game_count'] ?? 0) . ' oyun, '
                . (int) ($result['product_count'] ?? 0) . ' ürün.';
            $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
            if ($errors !== []) {
                $msg .= ' Uyarı: ' . implode(' | ', array_slice($errors, 0, 3));
            }
            $this->flash($msg);
        } catch (Throwable $exception) {
            $this->flash('Oyun sync hatası: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/gamingsoft/settings'));
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
