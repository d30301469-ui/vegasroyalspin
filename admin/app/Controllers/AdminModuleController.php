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
        if ($moduleKey === 'footer-settings') {
            $this->requirePermission('footer-settings');
            (new AdminFooterController())->edit();
            return;
        }
        $this->requirePermission($moduleKey);
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
        try {
            [$filterWhere, $filterParams, $activeFilters] = $this->tables->buildColumnFilters($table, $filterDefs, $rawFilters);
            if ($filterWhere !== '') {
                $fixedWhere = $fixedWhere !== '' ? ('(' . $fixedWhere . ') AND (' . $filterWhere . ')') : $filterWhere;
                $fixedParams = array_merge($fixedParams, $filterParams);
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
            if ($moduleKey === 'active-bonuses') {
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
            $targetStatuses = "'pending','failed','rejected'";
            $deletedTx = $pdo->exec("DELETE FROM megapayz_transactions WHERE status IN ({$targetStatuses})");
            $deletedCallbacks = $pdo->exec('DELETE FROM megapayz_callbacks');
            $pdo->commit();

            AdminAuth::writeLog($adminUsername, 'reset_pending_transactions', 'system', 'success');
            $_SESSION['admin_flash'] = "Tüm bekleyen ve başarısız işlemler temizlendi. ({$deletedTx} işlem, {$deletedCallbacks} callback silindi)";
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
