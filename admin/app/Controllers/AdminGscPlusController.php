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

        $agentWallet = null;
        $agentWalletError = '';
        if (GscPlusService::isConfigured($pdo)) {
            try {
                $agentWallet = GscPlusService::agentWalletBalance($pdo);
            } catch (Throwable $exception) {
                $agentWalletError = $exception->getMessage();
            }
        }

        $this->view('gsc-plus/settings', [
            'title' => 'GSC+ Ayarları',
            'active' => 'datatable',
            'moduleKey' => 'gsc-plus-settings',
            'crumbs' => 'Games | GSC+',
            'configRow' => $cfg,
            'productsCount' => $productsCount,
            'gamesCount' => $gamesCount,
            'transactionsCount' => $transactionsCount,
            'callbackUrl' => $backendBase . '/api/v2/gsc-plus-wallet',
            'callbackAlias' => $backendBase . '/api/v2/gsc-plus-wallet/v1/api/seamless',
            'agentWallet' => $agentWallet,
            'agentWalletError' => $agentWalletError,
            'flash' => $this->pullFlash(),
        ]);
    }

    public function updateSettings(): void
    {
        $this->requirePermission('gsc-plus-settings');
        $this->ensurePost();
        // Checkbox is absent from $_POST when unchecked — make the flag explicit.
        GscPlusService::updateConfig(AdminDatabase::pdo(), $_POST + ['is_active' => !empty($_POST['is_active']) ? 1 : 0]);
        $this->flash('GSC+ ayarları güncellendi.');
        $this->redirect(AdminAuth::url('/gsc-plus/settings'));
    }

    public function syncProducts(): void
    {
        $this->requirePermission('gsc-plus-settings');
        $this->ensurePost();
        try {
            $result = GscPlusService::syncProducts(AdminDatabase::pdo());
            $this->flash('Product sync tamamlandı: ' . (int) ($result['count'] ?? 0) . ' ürün.');
        } catch (Throwable $exception) {
            $this->flash('Product sync hatası: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/gsc-plus/settings'));
    }

    public function syncGames(): void
    {
        $this->requirePermission('gsc-plus-settings');
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
        $this->redirect(AdminAuth::url('/gsc-plus/settings'));
    }

    public function autoDeposit(): void
    {
        $this->requirePermission('gsc-plus-settings');
        $wantsJson = str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json')
            || strcasecmp((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'XMLHttpRequest') === 0
            || (string) ($_POST['ajax'] ?? '') === '1';

        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            if ($wantsJson) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(419);
                echo json_encode(['ok' => false, 'message' => 'Oturum doğrulaması başarısız.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }

        try {
            $amount = (float) ($_POST['amount'] ?? 0);
            $paymentCurrency = strtoupper(trim((string) ($_POST['payment_currency'] ?? 'USDT')));
            $depositCurrency = strtoupper(trim((string) ($_POST['deposit_currency'] ?? '')));
            $result = GscPlusService::autoDepositOrder(
                AdminDatabase::pdo(),
                $amount,
                $paymentCurrency !== '' ? $paymentCurrency : 'USDT',
                $depositCurrency !== '' ? $depositCurrency : null
            );
            if ($wantsJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'data' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
            $this->flash('Auto Deposit URL (900 sn geçerli): ' . (string) ($result['url'] ?? ''));
        } catch (Throwable $exception) {
            if ($wantsJson) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(422);
                echo json_encode(['ok' => false, 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $this->flash('Auto Deposit hatası: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/gsc-plus/settings'));
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
