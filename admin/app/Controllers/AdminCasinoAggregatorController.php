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
            'catalogJob'        => CasinoAggregatorService::catalogJobStatus(),
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

    public function rebuildCatalog(): void
    {
        $this->requirePermission('casino-aggregator-settings');
        $this->ensurePost();
        $start = CasinoAggregatorService::startCatalogJob('rebuild');
        $this->flash((string) ($start['message'] ?? 'Katalog işlemi başlatıldı.'));
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
        $start = CasinoAggregatorService::startCatalogJob('sync-games');
        $this->flash((string) ($start['message'] ?? 'Oyun sync başlatıldı.'));
        $this->redirect(AdminAuth::url('/casino-aggregator/settings'));
    }

    public function agentSettings(): void
    {
        $this->requirePermission('casino-aggregator-settings');
        $pdo = AdminDatabase::pdo();
        CasinoAggregatorService::bootstrap($pdo);
        $config = CasinoAggregatorService::config($pdo);
        $context = $this->readGameContext($_GET, $config);
        $vendors = CasinoAggregatorService::listVendors($pdo);
        $games = $context['vendor_code'] !== ''
            ? CasinoAggregatorService::listGamesForVendor($pdo, $context['vendor_code'])
            : [];

        $this->view('casino-aggregator/agent-settings', [
            'title'          => 'Casino Aggregator Agent Kontrolleri',
            'active'         => 'datatable',
            'moduleKey'      => 'casino-aggregator-settings',
            'crumbs'         => 'Games | Casino Aggregator | Agent Settings',
            'configRow'      => $config,
            'context'        => $context,
            'vendors'        => $vendors,
            'games'          => $games,
            'agentSettings'  => ($context['vendor_code'] !== '' && $context['game_code'] !== '')
                ? CasinoAggregatorService::getAgentSettings($pdo, $context)
                : array_fill_keys(CasinoAggregatorService::AGENT_SETTING_CATEGORIES, ''),
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
        $context = $this->readGameContext($_POST, CasinoAggregatorService::config($pdo));
        $payload = [
            'RoundKey'       => (string) ($_POST['RoundKey'] ?? ''),
            'HideRoundId'    => isset($_POST['HideRoundId']) ? '1' : '0',
            'HideTournament' => isset($_POST['HideTournament']) ? '1' : '0',
            'HideBadge'      => isset($_POST['HideBadge']) ? '1' : '0',
            'LowRtp'         => (string) ($_POST['LowRtp'] ?? ''),
            'HighRtp'        => (string) ($_POST['HighRtp'] ?? ''),
        ];

        try {
            $result = CasinoAggregatorService::setAgentSettings($pdo, $payload, true, $context);
            $msg = 'ChangeAgentSetting tamamlandı (' . (int) ($result['saved'] ?? 0) . ' kategori).'
                . ' API: ' . (int) ($result['api_ok'] ?? 0) . ' başarılı.';
            $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
            if ($errors !== []) {
                $msg .= ' Uyarı: ' . implode(' | ', array_slice($errors, 0, 3));
            }
            $this->flash($msg);
        } catch (Throwable $exception) {
            $this->flash('Agent ayarı kaydedilemedi: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/casino-aggregator/agent-settings?' . http_build_query([
            'vendor_code' => $context['vendor_code'],
            'game_code' => $context['game_code'],
            'currency_code' => $context['currency_code'],
        ])));
    }

    public function pullAgentSettings(): void
    {
        $this->requirePermission('casino-aggregator-settings');
        $this->ensurePost();
        $pdo = AdminDatabase::pdo();
        $context = $this->readGameContext($_POST, CasinoAggregatorService::config($pdo));
        try {
            $result = CasinoAggregatorService::pullAgentSettings($pdo, $context);
            $msg = 'GetAgentSetting: ' . (int) ($result['updated'] ?? 0) . ' kategori güncellendi.';
            $errors = is_array($result['errors'] ?? null) ? $result['errors'] : [];
            if ($errors !== []) {
                $msg .= ' Uyarı: ' . implode(' | ', array_slice($errors, 0, 3));
            }
            $this->flash($msg);
        } catch (Throwable $exception) {
            $this->flash('Agent ayarı çekilemedi: ' . $exception->getMessage());
        }
        $this->redirect(AdminAuth::url('/casino-aggregator/agent-settings?' . http_build_query([
            'vendor_code' => $context['vendor_code'],
            'game_code' => $context['game_code'],
            'currency_code' => $context['currency_code'],
        ])));
    }

    public function userSettings(): void
    {
        $this->requirePermission('casino-aggregator-settings');
        $pdo = AdminDatabase::pdo();
        CasinoAggregatorService::bootstrap($pdo);
        $config = CasinoAggregatorService::config($pdo);
        $context = $this->readGameContext($_GET, $config);

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

        $hasScope = $context['vendor_code'] !== '' && $context['game_code'] !== '';
        $this->view('casino-aggregator/user-settings', [
            'title'         => 'Casino Aggregator Kullanıcı RTP',
            'active'        => 'datatable',
            'moduleKey'     => 'casino-aggregator-settings',
            'crumbs'        => 'Games | Casino Aggregator | User Settings',
            'configRow'     => $config,
            'context'       => $context,
            'vendors'       => CasinoAggregatorService::listVendors($pdo),
            'games'         => $context['vendor_code'] !== ''
                ? CasinoAggregatorService::listGamesForVendor($pdo, $context['vendor_code'])
                : [],
            'lookup'        => $lookup,
            'userRow'       => $userRow,
            'userCode'      => $userCode,
            'userSettings'  => ($userCode !== '' && $hasScope)
                ? CasinoAggregatorService::getUserSettings($pdo, $userCode, $context)
                : array_fill_keys(CasinoAggregatorService::USER_SETTING_CATEGORIES, ''),
            'recentRows'    => CasinoAggregatorService::recentUserSettings($pdo, 60),
            'flash'         => $this->pullFlash(),
        ]);
    }

    public function updateUserSettings(): void
    {
        $this->requirePermission('casino-aggregator-settings');
        $this->ensurePost();
        $pdo = AdminDatabase::pdo();
        $userCode = trim((string) ($_POST['user_code'] ?? $_POST['user'] ?? ''));
        $context = $this->readGameContext($_POST, CasinoAggregatorService::config($pdo));
        $payload = [
            'LowRtp'  => (string) ($_POST['LowRtp'] ?? ''),
            'HighRtp' => (string) ($_POST['HighRtp'] ?? ''),
        ];

        try {
            $result = CasinoAggregatorService::setUserSettings($pdo, $userCode, $payload, true, $context);
            $msg = 'ChangeUserSetting tamamlandı (userCode=' . (string) ($result['user_code'] ?? '') . ').'
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
        $this->redirect(AdminAuth::url('/casino-aggregator/user-settings?' . http_build_query([
            'user' => $redirectUser,
            'vendor_code' => $context['vendor_code'],
            'game_code' => $context['game_code'],
            'currency_code' => $context['currency_code'],
        ])));
    }

    public function pullUserSettings(): void
    {
        $this->requirePermission('casino-aggregator-settings');
        $this->ensurePost();
        $pdo = AdminDatabase::pdo();
        $userCode = trim((string) ($_POST['user_code'] ?? $_POST['user'] ?? ''));
        $context = $this->readGameContext($_POST, CasinoAggregatorService::config($pdo));
        try {
            $result = CasinoAggregatorService::pullUserSettings($pdo, $userCode, $context);
            $msg = 'GetUserSetting: ' . (int) ($result['updated'] ?? 0) . ' kategori.'
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
        $this->redirect(AdminAuth::url('/casino-aggregator/user-settings?' . http_build_query([
            'user' => $redirectUser,
            'vendor_code' => $context['vendor_code'],
            'game_code' => $context['game_code'],
            'currency_code' => $context['currency_code'],
        ])));
    }

    public function gameControl(): void
    {
        $this->requirePermission('casino-aggregator-settings');
        $pdo = AdminDatabase::pdo();
        CasinoAggregatorService::bootstrap($pdo);
        $config = CasinoAggregatorService::config($pdo);
        $vendorCode = trim((string) ($_GET['vendor_code'] ?? ''));
        $players = [];
        $playersError = '';
        $callOptions = [];
        if ($vendorCode !== '') {
            try {
                $players = CasinoAggregatorService::getCurrentPlayers($pdo, $vendorCode)['players'] ?? [];
                if (!is_array($players)) {
                    $players = [];
                }
                foreach ($players as $idx => $player) {
                    if (!is_array($player)) {
                        continue;
                    }
                    $pVendor = trim((string) ($player['vendorCode'] ?? $vendorCode));
                    $pGame = trim((string) ($player['gameCode'] ?? ''));
                    $pType = (string) ($player['requestType'] ?? '0');
                    $cacheKey = $pVendor . '|' . $pGame . '|' . $pType;
                    if (!isset($callOptions[$cacheKey])) {
                        $callOptions[$cacheKey] = CasinoAggregatorService::resolveCallListOptions(
                            $pdo,
                            $pVendor,
                            $pGame,
                            $pType
                        );
                    }
                    $players[$idx]['_call_options'] = $callOptions[$cacheKey];
                    $players[$idx]['_call_type'] = (string) ($callOptions[$cacheKey]['call_type'] ?? CasinoAggregatorService::normalizeCallType($pType));
                }
                $players = CasinoAggregatorService::attachLocalUserProfiles($pdo, $players);
                $players = CasinoAggregatorService::attachLocalGameNames($pdo, $players);
            } catch (Throwable $e) {
                $playersError = $e->getMessage();
            }
        }

        $history = [];
        $historyError = '';
        $histVendor = trim((string) ($_GET['hist_vendor'] ?? $vendorCode));
        $startTime = trim((string) ($_GET['start_time'] ?? gmdate('Y-m-d H:i:s', time() - 86400)));
        $endTime = trim((string) ($_GET['end_time'] ?? gmdate('Y-m-d H:i:s')));
        if ($histVendor !== '' && isset($_GET['load_history'])) {
            try {
                $history = CasinoAggregatorService::getCallHistory($pdo, [
                    'vendor_code' => $histVendor,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'offset' => 0,
                    'limit' => 50,
                ])['data'] ?? [];
                if (is_array($history)) {
                    $history = CasinoAggregatorService::attachLocalUserProfiles($pdo, $history);
                    $history = CasinoAggregatorService::attachLocalGameNames($pdo, $history);
                }
            } catch (Throwable $e) {
                $historyError = $e->getMessage();
            }
        }

        $callLogs = CasinoAggregatorService::recentCallLogs($pdo, 40);
        if (!is_array($callLogs)) {
            $callLogs = [];
        }
        $logUserMap = CasinoAggregatorService::mapLocalUsersByCodes(
            $pdo,
            array_map(static fn ($row): string => (string) ($row['user_code'] ?? ''), $callLogs)
        );
        $logGameMap = CasinoAggregatorService::mapLocalGamesByPairs(
            $pdo,
            array_map(static fn ($row): array => [
                'vendor_code' => (string) ($row['vendor_code'] ?? ''),
                'game_code'   => (string) ($row['game_code'] ?? ''),
            ], $callLogs)
        );

        $this->view('casino-aggregator/game-control', [
            'title'         => 'Casino Aggregator Game Control',
            'active'        => 'datatable',
            'moduleKey'     => 'casino-aggregator-settings',
            'crumbs'        => 'Games | Casino Aggregator | Game Control',
            'configRow'     => $config,
            'vendors'       => CasinoAggregatorService::listVendors($pdo),
            'vendorCode'    => $vendorCode,
            'players'       => is_array($players) ? $players : [],
            'playersError'  => $playersError,
            'history'       => is_array($history) ? $history : [],
            'historyError'  => $historyError,
            'histVendor'    => $histVendor,
            'startTime'     => $startTime,
            'endTime'       => $endTime,
            'callLogs'      => $callLogs,
            'logUserMap'    => $logUserMap,
            'logGameMap'    => $logGameMap,
            'flash'         => $this->pullFlash(),
        ]);
    }

    public function callApply(): void
    {
        $this->requirePermission('casino-aggregator-settings');
        $this->ensurePost();
        $vendor = trim((string) ($_POST['vendor_code'] ?? ''));
        try {
            $result = CasinoAggregatorService::callApply(AdminDatabase::pdo(), $_POST);
            $this->flash('CallApply başarılı — callId=' . (int) ($result['call_id'] ?? 0)
                . ', calledMoney=' . (string) ($result['called_money'] ?? 0));
        } catch (Throwable $e) {
            $this->flash('CallApply başarısız: ' . $e->getMessage());
        }
        $this->redirect(AdminAuth::url('/casino-aggregator/game-control?vendor_code=' . rawurlencode($vendor)));
    }

    public function callCancel(): void
    {
        $this->requirePermission('casino-aggregator-settings');
        $this->ensurePost();
        $vendor = trim((string) ($_POST['vendor_code'] ?? ''));
        try {
            $result = CasinoAggregatorService::callCancel(AdminDatabase::pdo(), $_POST);
            $this->flash('CallCancel başarılı — canceledMoney=' . (string) ($result['canceled_money'] ?? 0));
        } catch (Throwable $e) {
            $this->flash('CallCancel başarısız: ' . $e->getMessage());
        }
        $this->redirect(AdminAuth::url('/casino-aggregator/game-control?vendor_code=' . rawurlencode($vendor)));
    }

    public function callList(): void
    {
        $this->requirePermission('casino-aggregator-settings');
        header('Content-Type: application/json; charset=utf-8');
        try {
            if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
                http_response_code(419);
                echo json_encode(['ok' => false, 'message' => 'CSRF'], JSON_UNESCAPED_UNICODE);
                return;
            }
            $result = CasinoAggregatorService::resolveCallListOptions(
                AdminDatabase::pdo(),
                (string) ($_POST['vendor_code'] ?? ''),
                (string) ($_POST['game_code'] ?? ''),
                (string) ($_POST['call_type'] ?? $_POST['request_type'] ?? '0')
            );
            echo json_encode([
                'ok'        => true,
                'calls'     => $result['calls'] ?? [],
                'call_type' => $result['call_type'] ?? '0',
                'error'     => $result['error'] ?? '',
            ], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        }
    }

    /** @param array<string, mixed> $src @param array<string, mixed> $config */
    private function readGameContext(array $src, array $config): array
    {
        return [
            'vendor_code'   => trim((string) ($src['vendor_code'] ?? $src['vendorCode'] ?? '')),
            'game_code'     => trim((string) ($src['game_code'] ?? $src['gameCode'] ?? '')),
            'currency_code' => strtoupper(trim((string) ($src['currency_code'] ?? $src['currencyCode'] ?? ($config['currency'] ?? 'TRY')))),
        ];
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
