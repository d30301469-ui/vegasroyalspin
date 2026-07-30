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
            'title' => 'Freespin Oluştur',
            'active' => 'datatable',
            'moduleKey' => 'bgaming-settings',
            'crumbs' => 'Games | Freespin Oluştur',
            'configRow' => BgamingService::config($pdo),
            'campaigns' => BgamingService::campaigns($pdo),
            'freespinGames' => BgamingService::freespinCapableGames($pdo),
            'assignments' => BgamingService::campaignAssignments($pdo, 120, 'freespin'),
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
            'title' => 'Freespin Listesi',
            'active' => 'datatable',
            'moduleKey' => 'bgaming-settings',
            'crumbs' => 'Games | Freespin Listesi',
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
            $userIds = $_POST['user_ids'] ?? [];
            if (!is_array($userIds)) {
                $userIds = [$userIds];
            }
            $userIds = array_values(array_filter(array_map('intval', $userIds)));
            if ($userIds === []) {
                throw new BgamingCampaignException(['user_ids' => 'En az bir oyuncu seçmelisiniz.']);
            }

            $result = BgamingService::saveCampaignWithAssignments(AdminDatabase::pdo(), $_POST);
            $this->flash($this->campaignSavedMessage($result, $campaignId > 0));
        } catch (BgamingCampaignException $exception) {
            $this->flashWithInput('Kaydedilemedi: ' . $exception->getMessage(), $exception->errors());
            $redirectPath .= $campaignId > 0 ? '?id=' . $campaignId : '';
        } catch (Throwable $exception) {
            $this->flashWithInput('Kaydedilemedi: ' . $exception->getMessage(), []);
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
                'Freespin eklendi',
                is_array($result['assignments'] ?? null) ? $result['assignments'] : []
            ));
        } catch (Throwable $exception) {
            $this->flash('Eklenemedi: ' . $exception->getMessage());
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
            $this->flash($this->assignmentsMessage('Tekrar denendi', [$result]));
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
            $this->flash('Freespin iptal edildi.');
        } catch (Throwable $exception) {
            $this->flash('İptal başarısız: ' . $exception->getMessage());
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
        $name = trim((string) ($campaign['title'] ?? ''));
        $prefix = ($isUpdate ? 'Güncellendi' : 'Kaydedildi')
            . ($name !== '' ? ': ' . $name : '');

        return $this->assignmentsMessage($prefix, is_array($result['assignments'] ?? null) ? $result['assignments'] : []);
    }

    /**
     * @param list<array<string, mixed>> $assignments
     */
    private function assignmentsMessage(string $prefix, array $assignments): string
    {
        if ($assignments === []) {
            return $prefix . '. Oyuncu seçilmedi.';
        }

        $ok = [];
        $failed = [];
        foreach ($assignments as $assignment) {
            $label = (string) ($assignment['username'] ?? ('#' . (int) ($assignment['user_id'] ?? 0)));
            if (!empty($assignment['ok'])) {
                $ok[] = $label;
                continue;
            }
            $failed[] = $label . ' (' . (string) ($assignment['error'] ?? 'hata') . ')';
        }

        $parts = [$prefix . '.'];
        if ($ok !== []) {
            $parts[] = count($ok) . ' oyuncuya tanımlandı: ' . implode(', ', $ok) . '.';
        }
        if ($failed !== []) {
            $parts[] = 'Tanımlanamadı: ' . implode(' | ', $failed) . '. Listeden Tekrar ile deneyin.';
        }

        return implode(' ', $parts);
    }

    public function issueFreespins(): void
    {
        $this->requirePermission('bgaming-settings');
        $this->ensurePost();
        try {
            $result = BgamingService::issueRemoteFreespins(AdminDatabase::pdo(), $_POST);
            $this->flash('Test freespin gönderildi.');
        } catch (Throwable $exception) {
            $this->flash('Test gönderimi başarısız: ' . $exception->getMessage());
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
            $status = (string) ($result['status'] ?? 'ok');
            $this->flash('Durum güncellendi: ' . BgamingService::freespinStatusLabel($status));
        } catch (Throwable $exception) {
            $this->flash('Durum güncellenemedi: ' . $exception->getMessage());
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
            $this->flash('Freespin iptal edildi.');
        } catch (Throwable $exception) {
            $this->flash('İptal başarısız: ' . $exception->getMessage());
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
             ORDER BY id DESC
             LIMIT 200'
        );

        return $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }
}
