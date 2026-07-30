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
        $editCampaign = $editId > 0 ? BgamingService::campaignById($pdo, $editId) : null;
        $formState = $this->pullFormState();

        $this->view('bgaming/campaigns', [
            'title' => 'BGaming Freespin Kampanyaları',
            'active' => 'datatable',
            'moduleKey' => 'bgaming-settings',
            'crumbs' => 'Games | BGaming Freespin Campaigns',
            'configRow' => BgamingService::config($pdo),
            'campaigns' => BgamingService::campaigns($pdo),
            'freespinGames' => BgamingService::freespinCapableGames($pdo),
            'assignments' => BgamingService::campaignAssignments($pdo, 120),
            'users' => $this->assignableUsers($pdo),
            'editCampaign' => $editCampaign,
            'oldInput' => is_array($formState['input'] ?? null) ? $formState['input'] : [],
            'errors' => is_array($formState['errors'] ?? null) ? $formState['errors'] : [],
            'flash' => $this->pullFlash(),
        ]);
    }

    /**
     * Eski "Kampanya Ekle" adresi artık tek birleşik ekrana yönlenir.
     */
    public function campaignAssignments(): void
    {
        $this->requirePermission('bgaming-settings');
        $this->redirect(AdminAuth::url('/bgaming/campaigns'));
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
            'freespinGames' => BgamingService::freespinCapableGames($pdo),
            'assignments' => BgamingService::campaignAssignments($pdo, 120, 'freespin'),
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

    /**
     * Tek akış: kampanyayı kaydet ve aynı istekte seçilen kullanıcılara ekle.
     */
    public function storeCampaign(): void
    {
        $this->requirePermission('bgaming-settings');
        $this->ensurePost();

        $campaignId = max(0, (int) ($_POST['id'] ?? 0));
        $redirectPath = '/bgaming/campaigns';
        try {
            $result = BgamingService::saveCampaignWithAssignments(AdminDatabase::pdo(), $_POST);
            $this->flash($this->campaignSavedMessage($result, $campaignId > 0));
        } catch (BgamingCampaignException $exception) {
            $this->flashWithInput('Kampanya kaydedilemedi: ' . $exception->getMessage(), $exception->errors());
            $redirectPath .= $campaignId > 0 ? '?id=' . $campaignId : '';
        } catch (Throwable $exception) {
            $this->flashWithInput('Kampanya kaydedilemedi: ' . $exception->getMessage(), []);
            $redirectPath .= $campaignId > 0 ? '?id=' . $campaignId : '';
        }

        $this->redirect(AdminAuth::url($redirectPath));
    }

    /**
     * Mevcut kampanyaya yeni kullanıcı ekleme.
     */
    public function assignCampaign(): void
    {
        $this->requirePermission('bgaming-settings');
        $this->ensurePost();
        try {
            $result = BgamingService::assignCampaign(AdminDatabase::pdo(), $_POST);
            $this->flash($this->assignmentsMessage(
                'Kampanya ' . (string) ($result['campaign_code'] ?? ''),
                is_array($result['assignments'] ?? null) ? $result['assignments'] : []
            ));
        } catch (Throwable $exception) {
            $this->flash('Kullanıcı eklenemedi: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/bgaming/campaigns'));
    }

    public function retryAssignment(): void
    {
        $this->requirePermission('bgaming-settings');
        $this->ensurePost();
        $assignmentId = max(0, (int) ($_POST['assignment_id'] ?? 0));
        try {
            $result = BgamingService::retryFreespinAssignment(AdminDatabase::pdo(), $assignmentId);
            $this->flash($this->assignmentsMessage('Tekrar deneme', [$result]));
        } catch (Throwable $exception) {
            $this->flash('Tekrar deneme başarısız: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url($this->assignmentReturnPath()));
    }

    public function cancelAssignment(): void
    {
        $this->requirePermission('bgaming-settings');
        $this->ensurePost();
        $assignmentId = max(0, (int) ($_POST['assignment_id'] ?? 0));
        try {
            $result = BgamingService::cancelFreespinAssignment(AdminDatabase::pdo(), $assignmentId);
            $this->flash('Freespin iptal edildi: ' . (string) ($result['issue_id'] ?? ''));
        } catch (Throwable $exception) {
            $this->flash('Freespin iptali başarısız: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url($this->assignmentReturnPath()));
    }

    private function assignmentReturnPath(): string
    {
        return trim((string) ($_POST['return'] ?? '')) === 'freespins'
            ? '/bgaming/freespins'
            : '/bgaming/campaigns';
    }

    /**
     * @param array{campaign: array<string, mixed>, assignments: list<array<string, mixed>>} $result
     */
    private function campaignSavedMessage(array $result, bool $isUpdate): string
    {
        $campaign = is_array($result['campaign'] ?? null) ? $result['campaign'] : [];
        $prefix = ($isUpdate ? 'Kampanya güncellendi' : 'Kampanya oluşturuldu')
            . ': ' . (string) ($campaign['title'] ?? '')
            . ' [' . (string) ($campaign['campaign_code'] ?? '') . ']';

        return $this->assignmentsMessage($prefix, is_array($result['assignments'] ?? null) ? $result['assignments'] : []);
    }

    /**
     * @param list<array<string, mixed>> $assignments
     */
    private function assignmentsMessage(string $prefix, array $assignments): string
    {
        if ($assignments === []) {
            return $prefix . '. Kullanıcı seçilmedi, kampanya şablon olarak kaydedildi.';
        }

        $ok = [];
        $failed = [];
        foreach ($assignments as $assignment) {
            $label = (string) ($assignment['username'] ?? ('#' . (int) ($assignment['user_id'] ?? 0)));
            if (!empty($assignment['ok'])) {
                $ok[] = $label;
                continue;
            }
            $failed[] = $label . ' (' . (string) ($assignment['error'] ?? 'bilinmeyen hata') . ')';
        }

        $parts = [$prefix . '.'];
        if ($ok !== []) {
            $parts[] = count($ok) . ' kullanıcıya eklendi: ' . implode(', ', $ok) . '.';
        }
        if ($failed !== []) {
            $parts[] = 'Başarısız: ' . implode(' | ', $failed) . '. Atamalar tablosundan tekrar deneyebilirsiniz.';
        }

        return implode(' ', $parts);
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

    /**
     * Hatalı gönderimde form verisini ve alan hatalarını koruyarak forma geri döner.
     *
     * @param array<string, string> $errors
     */
    private function flashWithInput(string $message, array $errors): void
    {
        $this->flash($message);
        $input = $_POST;
        unset($input['_token']);
        $_SESSION['admin_bgaming_form'] = ['input' => $input, 'errors' => $errors];
    }

    /**
     * @return array{input: array<string, mixed>, errors: array<string, string>}
     */
    private function pullFormState(): array
    {
        $state = is_array($_SESSION['admin_bgaming_form'] ?? null) ? $_SESSION['admin_bgaming_form'] : [];
        unset($_SESSION['admin_bgaming_form']);

        return [
            'input' => is_array($state['input'] ?? null) ? $state['input'] : [],
            'errors' => is_array($state['errors'] ?? null) ? $state['errors'] : [],
        ];
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
