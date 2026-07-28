<?php

declare(strict_types=1);

final class AdminGamingSoftController extends AdminController
{
    public function settings(): void
    {
        $this->requirePermission('gamingsoft-settings');
        $pdo = AdminDatabase::pdo();
        GscPlusService::bootstrap($pdo);
        $cfg = GscPlusService::config($pdo);
        $backendBase = defined('BACKEND_URL')
            ? rtrim((string) BACKEND_URL, '/')
            : rtrim((string) (getenv('BACKEND_URL') ?: getenv('BACKEND_FALLBACK_URL') ?: 'https://admin.vegasroyalspin.com'), '/');

        $productsCount = 0;
        $gamesCount = 0;
        $transactionsCount = 0;
        try {
            $productsCount = (int) $pdo->query('SELECT COUNT(*) FROM gsc_products')->fetchColumn();
            $gamesCount = (int) $pdo->query('SELECT COUNT(*) FROM gsc_games WHERE is_active = 1')->fetchColumn();
            $transactionsCount = (int) $pdo->query('SELECT COUNT(*) FROM gsc_transactions')->fetchColumn();
        } catch (Throwable) {
        }

        $this->view('gamingsoft/settings', [
            'title' => 'Gaming Soft (GSC+) Ayarları',
            'active' => 'datatable',
            'moduleKey' => 'gamingsoft-settings',
            'crumbs' => 'Games | Gaming Soft',
            'configRow' => $cfg,
            'productsCount' => $productsCount,
            'gamesCount' => $gamesCount,
            'transactionsCount' => $transactionsCount,
            'callbackUrl' => $backendBase . '/api/v2/gamingsoft-wallet',
            'callbackAlias' => $backendBase . '/api/v2/gamingsoft-wallet/v1/api/seamless',
            'flash' => $this->pullFlash(),
        ]);
    }

    public function updateSettings(): void
    {
        $this->requirePermission('gamingsoft-settings');
        $this->ensurePost();
        GscPlusService::updateConfig(AdminDatabase::pdo(), $_POST);
        $this->flash('Gaming Soft ayarları güncellendi.');
        $this->redirect(AdminAuth::url('/gamingsoft/settings'));
    }

    public function syncProducts(): void
    {
        $this->requirePermission('gamingsoft-settings');
        $this->ensurePost();
        try {
            $result = GscPlusService::syncProducts(AdminDatabase::pdo());
            $this->flash('Product sync tamamlandı: ' . (int) ($result['count'] ?? 0) . ' ürün.');
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
            $result = GscPlusService::syncGames(AdminDatabase::pdo());
            $slotPath = dirname(__DIR__, 3) . '/services/SlotGamesQuery.php';
            if (!class_exists('SlotGamesQuery', false) && is_file($slotPath)) {
                require_once $slotPath;
            }
            if (class_exists('SlotGamesQuery', false) && method_exists('SlotGamesQuery', 'purgeCache')) {
                SlotGamesQuery::purgeCache();
            }
            $livePath = dirname(__DIR__, 3) . '/services/LiveCasinoQuery.php';
            if (!class_exists('LiveCasinoQuery', false) && is_file($livePath)) {
                require_once $livePath;
            }
            if (class_exists('LiveCasinoQuery', false) && method_exists('LiveCasinoQuery', 'purgeCache')) {
                LiveCasinoQuery::purgeCache();
            }
            $this->flash(
                'Oyun sync tamamlandı: '
                . (int) ($result['count'] ?? 0) . ' oyun / '
                . (int) ($result['products'] ?? 0) . ' ürün.'
            );
        } catch (Throwable $exception) {
            $this->flash('Oyun sync hatası: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/gamingsoft/settings'));
    }
}
