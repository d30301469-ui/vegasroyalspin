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
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));
        $search = trim((string) ($_GET['search'] ?? ''));

        try {
            $columns = $this->tables->columns($table);
            $rows = $this->tables->rows($table, $page, $perPage, $search, $fixedWhere);
            $total = $this->tables->countRows($table, $search, $fixedWhere);
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
     * Bekleyen/basarisiz tum yatirim-cekim islemlerini temizler, ID'leri sifirlar.
     * POST /module/reset-pending-transactions
     */
    public function resetPendingTransactions(): void
    {
        $this->requirePermission('deposits');

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

            // ALTER TABLE causes implicit commit in MySQL — must run outside transaction
            $pdo->exec('ALTER TABLE megapayz_transactions AUTO_INCREMENT = 1');
            $pdo->exec('ALTER TABLE megapayz_callbacks AUTO_INCREMENT = 1');

            AdminAuth::writeLog($adminUsername, 'reset_pending_transactions', 'system', 'success');
            $_SESSION['admin_flash'] = "Tüm bekleyen ve başarısız işlemler temizlendi. ({$deletedTx} işlem, {$deletedCallbacks} callback silindi, ID'ler sıfırlandı)";
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['admin_flash'] = 'Hata: ' . $e->getMessage();
        }

        $this->redirect(AdminAuth::url('/module?key=deposits'));
    }
}
