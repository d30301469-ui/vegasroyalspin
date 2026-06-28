<?php

declare(strict_types=1);

final class AdminMegaPayzController extends AdminController
{
    public function settings(): void
    {
        $this->requirePermission('payment-providers');
        $pdo = AdminDatabase::pdo();
        MegaPayzService::bootstrap($pdo);

        $stmt = $pdo->query("SELECT * FROM megapayz_config WHERE code = 'default' ORDER BY id ASC LIMIT 1");
        $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : [];

        $this->view('megapayz/settings', [
            'title' => 'MegaPayz Ayarları',
            'active' => 'datatable',
            'moduleKey' => 'payment-providers',
            'crumbs' => 'Finance | MegaPayz Settings',
            'configRow' => is_array($row) ? $row : [],
            'methodsCount' => (int) $pdo->query('SELECT COUNT(*) FROM megapayz_methods')->fetchColumn(),
            'transactionsCount' => (int) $pdo->query('SELECT COUNT(*) FROM megapayz_transactions')->fetchColumn(),
            'flash' => $this->pullFlash(),
        ]);
    }

    public function updateSettings(): void
    {
        $this->requirePermission('payment-providers');
        $this->ensurePost();

        $pdo = AdminDatabase::pdo();
        MegaPayzService::bootstrap($pdo);

        $sid = trim((string) ($_POST['sid'] ?? ''));
        $privateKey = trim((string) ($_POST['private_key'] ?? ''));
        $apiBaseUrl = trim((string) ($_POST['api_base_url'] ?? ''));
        if ($apiBaseUrl === '') {
            $apiBaseUrl = trim((string) (getenv('MEGAPAYZ_API_BASE_URL') ?: 'https://api.megapayz.net'));
        }
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $existsStmt = $pdo->query("SELECT id, private_key FROM megapayz_config WHERE code = 'default' ORDER BY id ASC LIMIT 1");
        $existing = $existsStmt !== false ? $existsStmt->fetch(PDO::FETCH_ASSOC) : false;
        if (is_array($existing)) {
            $stmt = $pdo->prepare(
                'UPDATE megapayz_config
                 SET sid = :sid,
                     private_key = :private_key,
                     api_base_url = :api_base_url,
                     is_active = :is_active
                 WHERE id = :id'
            );
            $stmt->execute([
                'sid' => $sid,
                'private_key' => $privateKey !== '' ? $privateKey : (string) ($existing['private_key'] ?? ''),
                'api_base_url' => $apiBaseUrl,
                'is_active' => $isActive,
                'id' => (int) $existing['id'],
            ]);
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO megapayz_config (code, sid, private_key, api_base_url, is_active)
                 VALUES ('default', :sid, :private_key, :api_base_url, :is_active)"
            );
            $stmt->execute([
                'sid' => $sid,
                'private_key' => $privateKey,
                'api_base_url' => $apiBaseUrl,
                'is_active' => $isActive,
            ]);
        }

        $this->flash('MegaPayz ayarları güncellendi.');
        $this->redirect(AdminAuth::url('/megapayz/settings'));
    }

    public function methods(): void
    {
        $this->requirePermission('payment-methods');
        $pdo = AdminDatabase::pdo();
        MegaPayzService::bootstrap($pdo);
        $stmt = $pdo->query('SELECT * FROM megapayz_methods ORDER BY sort_order ASC, id ASC');
        $methods = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $this->view('megapayz/methods', [
            'title' => 'Ödeme Metotları',
            'active' => 'datatable',
            'moduleKey' => 'payment-methods',
            'crumbs' => 'Finance | Payment Methods',
            'methods' => is_array($methods) ? $methods : [],
            'flash' => $this->pullFlash(),
        ]);
    }

    public function updateMethods(): void
    {
        $this->requirePermission('payment-methods');
        $this->ensurePost();
        $items = is_array($_POST['methods'] ?? null) ? $_POST['methods'] : [];
        $pdo = AdminDatabase::pdo();
        MegaPayzService::bootstrap($pdo);
        $stmt = $pdo->prepare(
            'UPDATE megapayz_methods
             SET logo_url = :logo_url,
                 min_amount = :min_amount,
                 max_amount = :max_amount,
                 deposit_enabled = :deposit_enabled,
                 withdraw_enabled = :withdraw_enabled,
                 is_active = :is_active
             WHERE id = :id'
        );

        foreach ($items as $id => $item) {
            if (!is_array($item)) {
                continue;
            }
            $methodId = max(0, (int) $id);
            $min = max(0, round((float) str_replace(',', '.', (string) ($item['min_amount'] ?? '0')), 2));
            $max = max($min, round((float) str_replace(',', '.', (string) ($item['max_amount'] ?? '0')), 2));
            $stmt->execute([
                'logo_url' => trim((string) ($item['logo_url'] ?? '')),
                'min_amount' => number_format($min, 2, '.', ''),
                'max_amount' => number_format($max, 2, '.', ''),
                'deposit_enabled' => isset($item['deposit_enabled']) ? 1 : 0,
                'withdraw_enabled' => isset($item['withdraw_enabled']) ? 1 : 0,
                'is_active' => isset($item['is_active']) ? 1 : 0,
                'id' => $methodId,
            ]);
        }

        $this->flash('Ödeme metotları güncellendi.');
        $this->redirect(AdminAuth::url('/megapayz/methods'));
    }

    public function approveWithdraw(): void
    {
        $this->requirePermission('withdrawals');
        $this->ensurePost();
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $admin = AdminAuth::userName();
        $result = MegaPayzService::approveWithdraw(AdminDatabase::pdo(), $id, $admin);
        $this->flashResult($result, 'Çekim onayı tamamlandı.');
        $this->redirect(AdminAuth::url('/module?key=withdrawals'));
    }

    public function rejectWithdraw(): void
    {
        $this->requirePermission('withdrawals');
        $this->ensurePost();
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $result = MegaPayzService::rejectWithdraw(AdminDatabase::pdo(), $id, $reason);
        $this->flashResult($result, 'Çekim reddi tamamlandı.');
        $this->redirect(AdminAuth::url('/module?key=withdrawals'));
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

    private function flashResult(array $result, string $fallback): void
    {
        $message = trim((string) ($result['message'] ?? $fallback));
        if (empty($result['success'])) {
            $message = 'Hata: ' . ($message !== '' ? $message : 'İşlem tamamlanamadı.');
        }
        $this->flash($message !== '' ? $message : $fallback);
    }

    private function pullFlash(): string
    {
        $message = (string) ($_SESSION['admin_flash'] ?? '');
        unset($_SESSION['admin_flash']);

        return $message;
    }
}
