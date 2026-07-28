<?php

declare(strict_types=1);

final class AdminCasinoAggregatorController extends AdminController
{
    public function settings(): void
    {
        $this->requirePermission('casino-aggregator-settings');
        $pdo = AdminDatabase::pdo();
        CasinoAggregatorService::bootstrap($pdo);

        $vendorsCount = 0;
        $gamesCount = 0;
        $sessionsCount = 0;
        $transactionsCount = 0;
        try {
            $vendorsCount = (int) $pdo->query('SELECT COUNT(*) FROM casino_aggregator_vendors')->fetchColumn();
            $gamesCount = (int) $pdo->query('SELECT COUNT(*) FROM casino_aggregator_games')->fetchColumn();
            $sessionsCount = (int) $pdo->query('SELECT COUNT(*) FROM casino_aggregator_sessions')->fetchColumn();
            $transactionsCount = (int) $pdo->query('SELECT COUNT(*) FROM casino_aggregator_transactions')->fetchColumn();
        } catch (Throwable) {
        }

        $this->view('casino-aggregator/settings', [
            'title'             => 'Casino Aggregator Ayarları',
            'active'            => 'datatable',
            'moduleKey'         => 'casino-aggregator-settings',
            'crumbs'            => 'Games | Casino Aggregator',
            'configRow'         => CasinoAggregatorService::config($pdo),
            'vendorsCount'      => $vendorsCount,
            'gamesCount'        => $gamesCount,
            'activeGamesCount'  => (int) $pdo->query('SELECT COUNT(*) FROM casino_aggregator_games WHERE is_active = 1')->fetchColumn(),
            'sessionsCount'     => $sessionsCount,
            'transactionsCount' => $transactionsCount,
            'flash'             => $this->pullFlash(),
        ]);
    }

    public function updateSettings(): void
    {
        $this->requirePermission('casino-aggregator-settings');
        $this->ensurePost();
        CasinoAggregatorService::updateConfig(AdminDatabase::pdo(), $_POST);
        $this->flash('Casino aggregator ayarları güncellendi.');
        $this->redirect(AdminAuth::url('/casino-aggregator/settings'));
    }

    public function syncVendors(): void
    {
        $this->requirePermission('casino-aggregator-settings');
        $this->ensurePost();
        try {
            $result = CasinoAggregatorService::syncVendors(AdminDatabase::pdo());
            $this->flash('Vendor sync tamamlandı: ' . (int) ($result['vendor_count'] ?? 0) . ' vendor.');
        } catch (Throwable $exception) {
            $this->flash('Vendor sync hatası: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/casino-aggregator/settings'));
    }

    public function syncGames(): void
    {
        $this->requirePermission('casino-aggregator-settings');
        $this->ensurePost();
        try {
            $result = CasinoAggregatorService::syncGames(AdminDatabase::pdo());
            if (class_exists('SlotGamesQuery', false)) {
                SlotGamesQuery::purgeCache();
            } elseif (is_file(dirname(__DIR__, 3) . '/services/SlotGamesQuery.php')) {
                require_once dirname(__DIR__, 3) . '/services/SlotGamesQuery.php';
                SlotGamesQuery::purgeCache();
            }
            $msg = 'Oyun sync tamamlandı: ' . (int) ($result['game_count'] ?? 0) . ' oyun, '
                . (int) ($result['vendor_count'] ?? 0) . ' vendor.';
            if (((int) ($result['repaired_vendors'] ?? 0)) > 0 || ((int) ($result['repaired_games'] ?? 0)) > 0) {
                $msg .= ' Etiket düzeltme: ' . (int) ($result['repaired_vendors'] ?? 0) . ' vendor, '
                    . (int) ($result['repaired_games'] ?? 0) . ' oyun.';
            }
            if (((int) ($result['egt_vip_png'] ?? 0)) > 0) {
                $msg .= ' EGT VIP PNG: ' . (int) $result['egt_vip_png'] . ' oyun.';
            }
            $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
            if ($errors !== []) {
                $msg .= ' Uyarı: ' . implode(' | ', array_slice($errors, 0, 3));
            }
            $this->flash($msg);
        } catch (Throwable $exception) {
            $this->flash('Oyun sync hatası: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/casino-aggregator/settings'));
    }

    public function agentSettings(): void
    {
        $this->requirePermission('casino-aggregator-settings');
        $pdo = AdminDatabase::pdo();
        CasinoAggregatorService::bootstrap($pdo);

        $this->view('casino-aggregator/agent-settings', [
            'title'          => 'Casino Aggregator Agent Kontrolleri',
            'active'         => 'datatable',
            'moduleKey'      => 'casino-aggregator-settings',
            'crumbs'         => 'Games | Casino Aggregator | Agent Settings',
            'configRow'      => CasinoAggregatorService::config($pdo),
            'agentSettings'  => CasinoAggregatorService::getAgentSettings($pdo),
            'responseCodes'  => CasinoAggregatorService::RESPONSE_CODES,
            'gameTypes'      => CasinoAggregatorService::GAME_TYPES,
            'flash'          => $this->pullFlash(),
        ]);
    }

    public function updateAgentSettings(): void
    {
        $this->requirePermission('casino-aggregator-settings');
        $this->ensurePost();
        $pdo = AdminDatabase::pdo();

        $payload = [
            'RoundKey'       => (string) ($_POST['RoundKey'] ?? ''),
            'HideRoundId'    => isset($_POST['HideRoundId']) ? '1' : '0',
            'HideTournament' => isset($_POST['HideTournament']) ? '1' : '0',
            'HideBadge'      => isset($_POST['HideBadge']) ? '1' : '0',
            'LowRtp'         => (string) ($_POST['LowRtp'] ?? ''),
            'HighRtp'        => (string) ($_POST['HighRtp'] ?? ''),
        ];

        try {
            $result = CasinoAggregatorService::setAgentSettings($pdo, $payload, true);
            $msg = 'Agent ayarları kaydedildi (' . (int) ($result['saved'] ?? 0) . ' alan).'
                . ' API: ' . (int) ($result['api_ok'] ?? 0) . ' başarılı.';
            $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
            if ($errors !== []) {
                $msg .= ' Uyarı: ' . implode(' | ', array_slice($errors, 0, 3));
            }
            $this->flash($msg);
        } catch (Throwable $exception) {
            $this->flash('Agent ayarı kaydedilemedi: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/casino-aggregator/agent-settings'));
    }

    public function pullAgentSettings(): void
    {
        $this->requirePermission('casino-aggregator-settings');
        $this->ensurePost();
        try {
            $result = CasinoAggregatorService::pullAgentSettings(AdminDatabase::pdo());
            $msg = 'Agent ayarları çekildi: ' . (int) ($result['updated'] ?? 0) . ' alan güncellendi.';
            $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
            if ($errors !== []) {
                $msg .= ' Uyarı: ' . implode(' | ', array_slice($errors, 0, 3));
            }
            $this->flash($msg);
        } catch (Throwable $exception) {
            $this->flash('Agent ayarı çekilemedi: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/casino-aggregator/agent-settings'));
    }

    public function userSettings(): void
    {
        $this->requirePermission('casino-aggregator-settings');
        $pdo = AdminDatabase::pdo();
        CasinoAggregatorService::bootstrap($pdo);

        $lookup = trim((string) ($_GET['user'] ?? $_GET['user_code'] ?? ''));
        $resolved = $lookup !== '' ? CasinoAggregatorService::resolveUserCode($pdo, $lookup) : null;
        $userCode = is_array($resolved) ? (string) $resolved['user_code'] : '';
        $userRow = null;
        if ($userCode !== '') {
            try {
                $stmt = $pdo->prepare('SELECT id, username, email, banned, balance FROM users WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => (int) $userCode]);
                $fetched = $stmt->fetch(PDO::FETCH_ASSOC);
                $userRow = is_array($fetched) ? $fetched : null;
            } catch (Throwable) {
                $userRow = null;
            }
        }

        $this->view('casino-aggregator/user-settings', [
            'title'         => 'Casino Aggregator Kullanıcı RTP',
            'active'        => 'datatable',
            'moduleKey'     => 'casino-aggregator-settings',
            'crumbs'        => 'Games | Casino Aggregator | User Settings',
            'configRow'     => CasinoAggregatorService::config($pdo),
            'lookup'        => $lookup,
            'userRow'       => $userRow,
            'userCode'      => $userCode,
            'userSettings'  => $userCode !== '' ? CasinoAggregatorService::getUserSettings($pdo, $userCode) : ['LowRtp' => '', 'HighRtp' => ''],
            'recentRows'    => CasinoAggregatorService::recentUserSettings($pdo, 60),
            'flash'         => $this->pullFlash(),
        ]);
    }

    public function updateUserSettings(): void
    {
        $this->requirePermission('casino-aggregator-settings');
        $this->ensurePost();
        $userCode = trim((string) ($_POST['user_code'] ?? $_POST['user'] ?? ''));
        $payload = [
            'LowRtp'  => (string) ($_POST['LowRtp'] ?? ''),
            'HighRtp' => (string) ($_POST['HighRtp'] ?? ''),
        ];

        try {
            $result = CasinoAggregatorService::setUserSettings(AdminDatabase::pdo(), $userCode, $payload, true);
            $msg = 'Kullanıcı ayarları kaydedildi (userCode=' . (string) ($result['user_code'] ?? '') . ').'
                . ' API: ' . (int) ($result['api_ok'] ?? 0) . ' başarılı.';
            $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
            if ($errors !== []) {
                $msg .= ' Uyarı: ' . implode(' | ', array_slice($errors, 0, 3));
            }
            $this->flash($msg);
            $redirectUser = (string) ($result['user_code'] ?? $userCode);
        } catch (Throwable $exception) {
            $this->flash('Kullanıcı ayarı kaydedilemedi: ' . $exception->getMessage());
            $redirectUser = $userCode;
        }
        $this->redirect(AdminAuth::url('/casino-aggregator/user-settings?user=' . rawurlencode($redirectUser)));
    }

    public function pullUserSettings(): void
    {
        $this->requirePermission('casino-aggregator-settings');
        $this->ensurePost();
        $userCode = trim((string) ($_POST['user_code'] ?? $_POST['user'] ?? ''));
        try {
            $result = CasinoAggregatorService::pullUserSettings(AdminDatabase::pdo(), $userCode);
            $msg = 'Kullanıcı ayarları çekildi: ' . (int) ($result['updated'] ?? 0) . ' alan.'
                . ' userCode=' . (string) ($result['user_code'] ?? '');
            $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
            if ($errors !== []) {
                $msg .= ' Uyarı: ' . implode(' | ', array_slice($errors, 0, 3));
            }
            $this->flash($msg);
            $redirectUser = (string) ($result['user_code'] ?? $userCode);
        } catch (Throwable $exception) {
            $this->flash('Kullanıcı ayarı çekilemedi: ' . $exception->getMessage());
            $redirectUser = $userCode;
        }
        $this->redirect(AdminAuth::url('/casino-aggregator/user-settings?user=' . rawurlencode($redirectUser)));
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
