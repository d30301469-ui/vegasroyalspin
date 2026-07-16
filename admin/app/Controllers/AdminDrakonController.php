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

    public function campaigns(): void
    {
        $this->requirePermission('drakon-settings');
        $pdo = AdminDatabase::pdo();
        DrakonService::bootstrap($pdo);
        DrakonService::ensureCampaignSchema($pdo);

        $vendors     = [];
        $vendorError = '';
        try {
            $vendorResult = DrakonService::campaignVendors($pdo);
            if (!empty($vendorResult['success']) && is_array($vendorResult['data'] ?? null)) {
                $vendors = $vendorResult['data'];
            } else {
                $vendorError = (string) ($vendorResult['message'] ?? 'Sağlayıcılar alınamadı.');
            }
        } catch (Throwable $exception) {
            $vendorError = $exception->getMessage();
        }

        $limitVendor = trim((string) ($_GET['vendor'] ?? ''));
        $limitGames  = trim((string) ($_GET['games'] ?? ''));
        $limits      = [];
        $limitsError = '';
        if ($limitVendor !== '') {
            try {
                $limitResult = DrakonService::campaignVendorLimits($pdo, [
                    'vendors' => $limitVendor,
                    'games'   => $limitGames,
                ]);
                if (!empty($limitResult['success']) && is_array($limitResult['data'] ?? null)) {
                    $limits = $limitResult['data'];
                } else {
                    $limitsError = (string) ($limitResult['message'] ?? 'Limitler alınamadı.');
                }
            } catch (Throwable $exception) {
                $limitsError = $exception->getMessage();
            }
        }

        $this->view('drakon/campaigns', [
            'title'       => 'Drakon Freespin / Kampanya',
            'active'      => 'datatable',
            'moduleKey'   => 'drakon-settings',
            'crumbs'      => 'Games | Drakon Freespins',
            'configRow'   => DrakonService::config($pdo),
            'vendors'     => $vendors,
            'vendorError' => $vendorError,
            'limits'      => $limits,
            'limitsError' => $limitsError,
            'limitVendor' => $limitVendor,
            'limitGames'  => $limitGames,
            'campaigns'   => DrakonService::localCampaigns($pdo, 100),
            'flash'       => $this->pullFlash(),
        ]);
    }

    public function createCampaign(): void
    {
        $this->requirePermission('drakon-settings');
        $this->ensurePost();
        try {
            $result = DrakonService::createCampaign(AdminDatabase::pdo(), $_POST);
            if (!empty($result['success'])) {
                $this->flash('Drakon kampanyası oluşturuldu: ' . (string) ($result['campaign_code'] ?? ''));
            } else {
                $this->flash('Kampanya oluşturulamadı: ' . (string) ($result['message'] ?? 'Bilinmeyen hata.'));
            }
        } catch (Throwable $exception) {
            $this->flash('Kampanya oluşturulamadı: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/drakon/campaigns'));
    }

    public function cancelCampaign(): void
    {
        $this->requirePermission('drakon-settings');
        $this->ensurePost();
        $code = trim((string) ($_POST['campaign_code'] ?? ''));
        try {
            $result = DrakonService::cancelCampaign(AdminDatabase::pdo(), $code);
            if (!empty($result['success'])) {
                $this->flash('Kampanya iptal edildi: ' . $code);
            } else {
                $this->flash('Kampanya iptal edilemedi: ' . (string) ($result['message'] ?? 'Bilinmeyen hata.'));
            }
        } catch (Throwable $exception) {
            $this->flash('Kampanya iptal edilemedi: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/drakon/campaigns'));
    }

    public function addPlayers(): void
    {
        $this->requirePermission('drakon-settings');
        $this->ensurePost();
        $code    = trim((string) ($_POST['campaign_code'] ?? ''));
        $players = (string) ($_POST['players'] ?? '');
        try {
            $result = DrakonService::addCampaignPlayers(AdminDatabase::pdo(), $code, $players);
            if (!empty($result['success'])) {
                $this->flash('Oyuncular kampanyaya eklendi: ' . $code);
            } else {
                $this->flash('Oyuncu eklenemedi: ' . (string) ($result['message'] ?? 'Bilinmeyen hata.'));
            }
        } catch (Throwable $exception) {
            $this->flash('Oyuncu eklenemedi: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/drakon/campaigns'));
    }

    public function removePlayers(): void
    {
        $this->requirePermission('drakon-settings');
        $this->ensurePost();
        $code    = trim((string) ($_POST['campaign_code'] ?? ''));
        $players = (string) ($_POST['players'] ?? '');
        try {
            $result = DrakonService::removeCampaignPlayers(AdminDatabase::pdo(), $code, $players);
            if (!empty($result['success'])) {
                $this->flash('Oyuncular kampanyadan çıkarıldı: ' . $code);
            } else {
                $this->flash('Oyuncu çıkarılamadı: ' . (string) ($result['message'] ?? 'Bilinmeyen hata.'));
            }
        } catch (Throwable $exception) {
            $this->flash('Oyuncu çıkarılamadı: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/drakon/campaigns'));
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
