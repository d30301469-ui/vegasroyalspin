<?php

declare(strict_types=1);

final class AdminGscPlusController extends AdminController
{
    public function settings(): void
    {
        $this->requirePermission('gsc-plus-settings');
        $pdo = AdminDatabase::pdo();
        GscPlusService::bootstrap($pdo);
        $cfg = GscPlusService::config($pdo);
        $backendBase = defined('BACKEND_URL')
            ? rtrim((string) BACKEND_URL, '/')
            : rtrim((string) (getenv('BACKEND_URL') ?: getenv('BACKEND_FALLBACK_URL') ?: ''), '/');

        $this->view('gsc-plus/settings', [
            'title' => 'GSC+ Ayarları',
            'active' => 'datatable',
            'moduleKey' => 'gsc-plus-settings',
            'crumbs' => 'Games | GSC+ Settings',
            'configRow' => $cfg,
            'productsCount' => (int) $pdo->query('SELECT COUNT(*) FROM gsc_products')->fetchColumn(),
            'gamesCount' => (int) $pdo->query('SELECT COUNT(*) FROM gsc_games WHERE is_active = 1')->fetchColumn(),
            'transactionsCount' => (int) $pdo->query('SELECT COUNT(*) FROM gsc_transactions')->fetchColumn(),
            'callbackUrl' => $backendBase . '/api/v2/gamingsoft-wallet',
            'callbackAlias' => $backendBase . '/api/v2/gamingsoft-wallet/v1/api/seamless',
            'flash' => $this->pullFlash(),
        ]);
    }

    public function updateSettings(): void
    {
        $this->requirePermission('gsc-plus-settings');
        $this->ensurePost();
        GscPlusService::updateConfig(AdminDatabase::pdo(), $_POST);
        $this->flash('GSC+ ayarları güncellendi.');
        $this->redirect(AdminAuth::url('/gsc-plus/settings'));
    }

    public function syncProducts(): void
    {
        $this->requirePermission('gsc-plus-settings');
        $this->ensurePost();
        try {
            $result = GscPlusService::syncProducts(AdminDatabase::pdo());
            $this->flash('GSC+ ürün sync tamamlandı: ' . (int) ($result['count'] ?? 0) . ' kayıt.');
        } catch (Throwable $exception) {
            $this->flash('GSC+ ürün sync hatası: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/gsc-plus/settings'));
    }

    public function syncGames(): void
    {
        $this->requirePermission('gsc-plus-settings');
        $this->ensurePost();
        try {
            $result = GscPlusService::syncGames(AdminDatabase::pdo());
            $this->flash(
                'GSC+ oyun sync tamamlandı: '
                . (int) ($result['count'] ?? 0) . ' oyun / '
                . (int) ($result['products'] ?? 0) . ' ürün.'
            );
            if (class_exists('SlotGamesQuery', false) || class_exists('SlotGamesQuery')) {
                $slotPath = dirname(__DIR__, 3) . '/services/SlotGamesQuery.php';
                if (!class_exists('SlotGamesQuery', false) && is_file($slotPath)) {
                    require_once $slotPath;
                }
                if (method_exists('SlotGamesQuery', 'purgeCache')) {
                    SlotGamesQuery::purgeCache();
                }
            }
        } catch (Throwable $exception) {
            $this->flash('GSC+ oyun sync hatası: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/gsc-plus/settings'));
    }
}
