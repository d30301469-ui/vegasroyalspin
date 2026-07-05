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

    public function campaigns(): void
    {
        $this->requirePermission('bgaming-settings');
        $pdo = AdminDatabase::pdo();
        BgamingService::bootstrap($pdo);

        $editId = max(0, (int) ($_GET['id'] ?? 0));
        $this->view('bgaming/campaigns', [
            'title' => 'BGaming Kampanyaları',
            'active' => 'datatable',
            'moduleKey' => 'bgaming-settings',
            'crumbs' => 'Games | BGaming Campaign Create',
            'configRow' => BgamingService::config($pdo),
            'campaigns' => BgamingService::campaigns($pdo),
            'editCampaign' => $editId > 0 ? BgamingService::campaignById($pdo, $editId) : null,
            'flash' => $this->pullFlash(),
        ]);
    }

    public function campaignAssignments(): void
    {
        $this->requirePermission('bgaming-settings');
        $pdo = AdminDatabase::pdo();
        BgamingService::bootstrap($pdo);

        $this->view('bgaming/campaigns_assign', [
            'title' => 'BGaming Kampanya Atamaları',
            'active' => 'datatable',
            'moduleKey' => 'bgaming-settings',
            'crumbs' => 'Games | BGaming Campaign Assign',
            'campaigns' => BgamingService::campaigns($pdo),
            'assignments' => BgamingService::campaignAssignments($pdo, 120),
            'users' => $this->assignableUsers($pdo),
            'flash' => $this->pullFlash(),
        ]);
    }

    public function freespins(): void
    {
        $this->requirePermission('bgaming-settings');
        $pdo = AdminDatabase::pdo();
        BgamingService::bootstrap($pdo);

        $remoteUserId = max(0, (int) ($_GET['user_id'] ?? 0));
        $remoteStatus = trim((string) ($_GET['status'] ?? ''));
        $remotePage = max(1, (int) ($_GET['page'] ?? 1));

        $remoteData = ['data' => [], 'meta' => []];
        $remoteError = '';
        try {
            $remoteData = BgamingService::listRemoteFreespins($pdo, [
                'user_id' => $remoteUserId,
                'status' => $remoteStatus,
                'page' => $remotePage,
            ]);
        } catch (Throwable $exception) {
            $remoteError = $exception->getMessage();
        }

        $this->view('bgaming/freespins', [
            'title' => 'BGaming Freespin Yönetimi',
            'active' => 'datatable',
            'moduleKey' => 'bgaming-settings',
            'crumbs' => 'Games | BGaming Freespins',
            'configRow' => BgamingService::config($pdo),
            'users' => $this->assignableUsers($pdo),
            'localCampaigns' => array_values(array_filter(
                BgamingService::campaigns($pdo),
                static fn (array $row): bool => (string) ($row['campaign_type'] ?? '') === 'freespin'
            )),
            'remoteData' => $remoteData,
            'remoteError' => $remoteError,
            'remoteFilter' => [
                'user_id' => $remoteUserId,
                'status' => $remoteStatus,
                'page' => $remotePage,
            ],
            'flash' => $this->pullFlash(),
        ]);
    }

    public function storeCampaign(): void
    {
        $this->requirePermission('bgaming-settings');
        $this->ensurePost();
        try {
            $result = BgamingService::saveCampaign(AdminDatabase::pdo(), $_POST);
            $this->flash('BGaming kampanyası kaydedildi: ' . (string) ($result['campaign_code'] ?? ''));
        } catch (Throwable $exception) {
            $this->flash('BGaming kampanyası kaydedilemedi: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/bgaming/campaigns'));
    }

    public function assignCampaign(): void
    {
        $this->requirePermission('bgaming-settings');
        $this->ensurePost();
        try {
            $result = BgamingService::assignCampaign(AdminDatabase::pdo(), $_POST);
            $this->flash('Kampanya kullanıcıya atandı: ' . (string) ($result['campaign_code'] ?? ''));
        } catch (Throwable $exception) {
            $this->flash('Kampanya ataması başarısız: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/bgaming/campaigns/assignments'));
    }

    public function issueFreespins(): void
    {
        $this->requirePermission('bgaming-settings');
        $this->ensurePost();
        try {
            $result = BgamingService::issueRemoteFreespins(AdminDatabase::pdo(), $_POST);
            $this->flash('Freespin issue başarılı: ' . (string) ($result['issue_id'] ?? ''));
        } catch (Throwable $exception) {
            $this->flash('Freespin issue başarısız: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/bgaming/freespins'));
    }

    public function syncFreespinStatus(): void
    {
        $this->requirePermission('bgaming-settings');
        $this->ensurePost();
        $issueId = trim((string) ($_POST['issue_id'] ?? ''));
        try {
            $result = BgamingService::syncRemoteFreespinStatus(AdminDatabase::pdo(), $issueId);
            $this->flash('Freespin status sync başarılı: ' . (string) ($result['status'] ?? 'ok'));
        } catch (Throwable $exception) {
            $this->flash('Freespin status sync başarısız: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/bgaming/freespins'));
    }

    public function cancelFreespin(): void
    {
        $this->requirePermission('bgaming-settings');
        $this->ensurePost();
        $issueId = trim((string) ($_POST['issue_id'] ?? ''));
        try {
            BgamingService::cancelRemoteFreespins(AdminDatabase::pdo(), $issueId);
            $this->flash('Freespin iptal edildi: ' . $issueId);
        } catch (Throwable $exception) {
            $this->flash('Freespin iptal başarısız: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/bgaming/freespins'));
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

    private function assignableUsers(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT id, username, email, name, surname, banned
             FROM users
             ORDER BY id DESC'
        );

        return $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
}
