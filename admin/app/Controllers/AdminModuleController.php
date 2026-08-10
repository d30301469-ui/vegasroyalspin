<?php

declare(strict_types=1);

final class AdminModuleController extends AdminController
{
    private AdminTableRepository $tables;

    public function __construct()
    {
        parent::__construct();
        $this->tables = new AdminTableRepository();
    }

    public function show(): void
    {
        $moduleKey = trim((string) ($_GET['key'] ?? ''));
        if ($moduleKey === 'site-settings') {
            $this->redirect(AdminAuth::url('/site-settings'));
        }
        if ($moduleKey === 'promotions') {
            $this->redirect(AdminAuth::url('/promotions'));
        }
        if ($moduleKey === 'gsc-plus-settings') {
            $this->redirect(AdminAuth::url('/gsc-plus/settings'));
        }
        if ($moduleKey === 'homepage-sections') {
            $this->redirect(AdminAuth::url('/homepage-sections'));
        }
        if ($moduleKey === 'mobile-menu-settings') {
            $this->redirect(AdminAuth::url('/mobile-menu'));
        }
        if ($moduleKey === 'payment-methods') {
            $this->redirect(AdminAuth::url('/megapayz/methods'));
        }
        if ($moduleKey === 'footer-settings') {
            $this->requirePermission('footer-settings');
            (new AdminFooterController())->edit();
            return;
        }
        $this->requirePermission($moduleKey);
        if (str_starts_with($moduleKey, 'loyalty-')) {
            try {
                $loyaltyService = ADMIN_BASE_PATH . '/services/LoyaltyService.php';
                if (is_file($loyaltyService)) {
                    require_once $loyaltyService;
                }
                if (class_exists('LoyaltyService', false)) {
                    LoyaltyService::ensureStorage(AdminDatabase::pdo());
                }
            } catch (Throwable) {
            }
        }
        $module = $this->module($moduleKey);
        $table = (string) $module['table'];
        $fixedWhere = (string) ($module['where'] ?? '');
        $fixedParams = is_array($module['where_params'] ?? null) ? $module['where_params'] : [];
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));
        $search = trim((string) ($_GET['search'] ?? ''));

        $filterDefs = is_array($module['filters'] ?? null) ? $module['filters'] : [];
        $rawFilters = [];
        if (isset($_GET['f']) && is_array($_GET['f'])) {
            $rawFilters = $_GET['f'];
        } else {
            foreach ($filterDefs as $column => $_def) {
                $legacy = trim((string) ($_GET['filter_' . $column] ?? ''));
                if ($legacy !== '') {
                    $rawFilters[$column] = $legacy;
                }
            }
        }

        $activeFilters = [];
        $filterOptions = [];
        $playersFilters = [];
        $playersFilterCount = 0;
        try {
            [$filterWhere, $filterParams, $activeFilters] = $this->tables->buildColumnFilters($table, $filterDefs, $rawFilters);
            if ($filterWhere !== '') {
                $fixedWhere = $fixedWhere !== '' ? ('(' . $fixedWhere . ') AND (' . $filterWhere . ')') : $filterWhere;
                $fixedParams = array_merge($fixedParams, $filterParams);
            }

            if ($moduleKey === 'users') {
                $rawPlayersFilters = isset($_GET['pf']) && is_array($_GET['pf']) ? $_GET['pf'] : [];
                [$playersWhere, $playersParams, $playersFilters, $playersFilterCount] = PlayersListFilter::build($rawPlayersFilters);
                if ($playersWhere !== '') {
                    $fixedWhere = $fixedWhere !== '' ? ('(' . $fixedWhere . ') AND (' . $playersWhere . ')') : $playersWhere;
                    $fixedParams = array_merge($fixedParams, $playersParams);
                }
            }

            foreach ($filterDefs as $column => $def) {
                if (!is_string($column) || $column === '' || !is_array($def)) {
                    continue;
                }
                $options = $def['options'] ?? null;
                if ($options === 'distinct' || $options === true) {
                    $filterOptions[$column] = $this->tables->distinctValues($table, $column);
                    $fallback = is_array($def['fallback'] ?? null) ? $def['fallback'] : [];
                    if ($filterOptions[$column] === [] && $fallback !== []) {
                        $filterOptions[$column] = array_values(array_map('strval', $fallback));
                    }
                } elseif (is_array($options)) {
                    $normalized = [];
                    foreach ($options as $key => $label) {
                        if (is_int($key)) {
                            $normalized[(string) $label] = (string) $label;
                        } else {
                            $normalized[(string) $key] = (string) $label;
                        }
                    }
                    $filterOptions[$column] = $normalized;
                } else {
                    $filterOptions[$column] = [];
                }
            }

            $columns = $this->tables->columns($table);
            if (in_array($moduleKey, ['active-bonuses', 'bonus-claims'], true)) {
                $columns[] = [
                    'name' => 'full_name',
                    'type' => 'varchar(201)',
                    'data_type' => 'varchar',
                    'nullable' => 'YES',
                    'column_key' => '',
                    'extra' => '',
                    'column_default' => null,
                ];
            }
            $rows = $this->tables->rows($table, $page, $perPage, $search, $fixedWhere, $fixedParams);
            $total = $this->tables->countRows($table, $search, $fixedWhere, $fixedParams);
            $primaryKey = $this->tables->primaryKey($table);
            $tableError = '';
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '42S02') {
                throw $exception;
            }
            $columns = [];
            $rows = [];
            $total = 0;
            $primaryKey = null;
            $tableError = 'Bu modül için veritabanı tablosu henüz oluşturulmamış. Sunucuda `php bin/install.php --migrate` çalıştırın.';
        } catch (InvalidArgumentException $exception) {
            $columns = [];
            $rows = [];
            $total = 0;
            $primaryKey = null;
            $tableError = $exception->getMessage() === 'Tablo bulunamadı.'
                ? 'Bu modül için veritabanı tablosu henüz oluşturulmamış. Sayfayı yenileyin veya migrate çalıştırın.'
                : $exception->getMessage();
        }

        $this->view('tables/show', [
            'title' => (string) $module['title'],
            'active' => (string) ($module['active'] ?? 'datatable'),
            'crumbs' => (string) ($module['crumbs'] ?? 'Admin | Modül'),
            'moduleKey' => $moduleKey,
            'module' => $module,
            'table' => $table,
            'columns' => $columns,
            'visibleColumnNames' => $module['columns'] ?? [],
            'rows' => $rows,
            'primaryKey' => $primaryKey,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'search' => $search,
            'tableError' => $tableError ?? '',
            'flash' => $this->pullFlash(),
            'filterDefs' => $filterDefs,
            'filterOptions' => $filterOptions,
            'activeFilters' => $activeFilters,
            'playersFilters' => $playersFilters,
            'playersFilterCount' => $playersFilterCount,
        ]);
    }

    public function update(): void
    {
        $moduleKey = trim((string) ($_GET['key'] ?? $_POST['key'] ?? ''));
        if ($moduleKey === 'footer-settings') {
            $this->requirePermission('footer-settings');
            (new AdminFooterController())->update();
            return;
        }
        // Generic module update not implemented yet
        http_response_code(404);
        echo 'Bu modülün güncelleme işlevi desteklenmiyor.';
    }

    private function module(string $moduleKey): array
    {
        $modules = isset($this->config['modules']) && is_array($this->config['modules']) ? $this->config['modules'] : [];
        if (!isset($modules[$moduleKey]) || !is_array($modules[$moduleKey])) {
            throw new InvalidArgumentException('Admin modülü bulunamadı.');
        }

        return $modules[$moduleKey];
    }

    private function humanize(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }

    private function pullFlash(): string
    {
        $message = (string) ($_SESSION['admin_flash'] ?? '');
        unset($_SESSION['admin_flash']);

        return $message;
    }

    /**
    * Bekleyen/basarisiz tum yatirim-cekim islemlerini temizler.
     * POST /module/reset-pending-transactions
     */
    public function resetPendingTransactions(): void
    {
        $this->requirePermission('deposits');
        $this->requirePermission('withdrawals');

        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }

        $confirm = trim((string) ($_POST['confirm'] ?? ''));
        if ($confirm !== 'RESET_ALL_PENDING_TX') {
            $_SESSION['admin_flash'] = 'Onay kodu gerekli. Lütfen "RESET_ALL_PENDING_TX" yazın.';
            $this->redirect(AdminAuth::url('/module?key=deposits'));
            return;
        }

        $pdo = AdminDatabase::pdo();
        $adminUsername = AdminAuth::userName();

        try {
            $pdo->beginTransaction();
            $targetStatuses = "'pending','failed'";
            $deletedTx = $pdo->exec("DELETE FROM megapayz_transactions WHERE status IN ({$targetStatuses})");
            $deletedCallbacks = $pdo->exec('DELETE FROM megapayz_callbacks');
            $pdo->commit();

            AdminAuth::writeLog($adminUsername, 'reset_pending_transactions', 'system', 'success');
            $_SESSION['admin_flash'] = "Bekleyen ve başarısız işlemler temizlendi. ({$deletedTx} işlem, {$deletedCallbacks} callback silindi). Reddedilen kayıtlar korundu.";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[AdminModuleController] pending transaction cleanup failed: ' . $e->getMessage());
            $_SESSION['admin_flash'] = 'İşlem temizleme tamamlanamadı.';
        }

        $this->redirect(AdminAuth::url('/module?key=deposits'));
    }
}
