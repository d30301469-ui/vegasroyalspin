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
