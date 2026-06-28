<?php

declare(strict_types=1);

final class AdminTableController extends AdminController
{
    private AdminTableRepository $tables;

    public function __construct()
    {
        parent::__construct();
        $this->tables = new AdminTableRepository();
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->redirect(AdminAuth::url('/module?key=users'));
    }

    public function show(): void
    {
        $this->requireAuth();
        $table = $this->tableName('view');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $search = trim((string) ($_GET['search'] ?? ''));
        $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));

        $columns = $this->tables->columns($table);
        $rows = $this->tables->rows($table, $page, $perPage, $search);
        $total = $this->tables->countRows($table, $search);

        $this->view('tables/show', [
            'title' => $table,
            'active' => 'datatable',
            'crumbs' => 'Admin | ' . $table,
            'table' => $table,
            'columns' => $columns,
            'rows' => $rows,
            'primaryKey' => $this->tables->primaryKey($table),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'search' => $search,
            'flash' => $this->pullFlash(),
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $table = $this->tableName('write');
        $this->denyReadOnlyModule($table, trim((string) ($_GET['module'] ?? '')));
        $data = [
            'title' => $table . ' Ekle',
            'active' => 'forms',
            'crumbs' => 'Admin | ' . $table . ' | Ekle',
            'moduleKey' => trim((string) ($_GET['module'] ?? '')),
            'table' => $table,
            'columns' => $this->tables->columns($table),
            'primaryKey' => $this->tables->primaryKey($table),
            'row' => [],
            'mode' => 'create',
            'flash' => $this->pullFlash(),
        ];

        if ($this->isModalRequest()) {
            $data['isModal'] = true;
            $this->partial('tables/_form', $data);
            return;
        }

        $this->view('tables/form', $data);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->ensurePost();
        $table = $this->tableName('write');
        $this->denyReadOnlyModule($table, trim((string) ($_POST['module'] ?? '')));
        $this->validatePasswordConfirmation($table, $_POST);
        $this->ensureSliderSchema($table);
        try {
            $this->tables->insert($table, $_POST);
        } catch (Throwable $e) {
            AdminAuth::writeLog(AdminAuth::userName(), 'table_insert', $table, 'fail', $e->getMessage());
            $this->flash('Kayıt eklenemedi: ' . $e->getMessage());
            $this->redirect(AdminAuth::url('/table/create?name=' . rawurlencode($table) . '&module=' . rawurlencode(trim((string) ($_POST['module'] ?? '')))));
        }
        AdminAuth::writeLog(AdminAuth::userName(), 'table_insert', $table, 'success');
        $this->flash('Kayıt eklendi.');
        $this->redirect($this->listUrl($table, trim((string) ($_POST['module'] ?? ''))));
    }

    public function edit(): void
    {
        $this->requireAuth();
        $table = $this->tableName('write');
        $this->denyReadOnlyModule($table, trim((string) ($_GET['module'] ?? '')));
        $primaryKey = $this->tables->primaryKey($table);
        if ($primaryKey === null) {
            $this->flash('Bu tabloda birincil anahtar olmadığı için düzenleme yapılamaz.');
            $this->redirect(AdminAuth::url('/table?name=' . rawurlencode($table)));
        }

        $id = trim((string) ($_GET['id'] ?? ''));
        $row = $this->tables->find($table, $primaryKey, $id);
        if ($row === null) {
            $this->flash('Kayıt bulunamadı.');
            $this->redirect(AdminAuth::url('/table?name=' . rawurlencode($table)));
        }

        $data = [
            'title' => $table . ' Düzenle',
            'active' => 'forms',
            'crumbs' => 'Admin | ' . $table . ' | Düzenle',
            'moduleKey' => trim((string) ($_GET['module'] ?? '')),
            'table' => $table,
            'columns' => $this->tables->columns($table),
            'primaryKey' => $primaryKey,
            'row' => $row,
            'mode' => 'edit',
            'flash' => $this->pullFlash(),
        ];

        if ($this->isModalRequest()) {
            $data['isModal'] = true;
            $this->partial('tables/_form', $data);
            return;
        }

        $this->view('tables/form', $data);
    }

    public function viewRecord(): void
    {
        $this->requireAuth();
        $table = $this->tableName('view');
        $primaryKey = $this->tables->primaryKey($table);
        if ($primaryKey === null) {
            $this->flash('Bu tabloda birincil anahtar olmadığı için görüntüleme yapılamaz.');
            $this->redirect(AdminAuth::url('/table?name=' . rawurlencode($table)));
        }

        $id = trim((string) ($_GET['id'] ?? ''));
        $row = $this->tables->find($table, $primaryKey, $id);
        if ($row === null) {
            $this->flash('Kayıt bulunamadı.');
            $this->redirect(AdminAuth::url('/table?name=' . rawurlencode($table)));
        }

        $data = [
            'title' => $table . ' Görüntüle',
            'active' => 'datatable',
            'crumbs' => 'Admin | ' . $table . ' | Görüntüle',
            'moduleKey' => trim((string) ($_GET['module'] ?? '')),
            'table' => $table,
            'columns' => $this->tables->columns($table),
            'primaryKey' => $primaryKey,
            'row' => $row,
        ];

        if ($this->isModalRequest()) {
            $this->partial('tables/_view', $data);
            return;
        }

        $this->view('tables/view', $data);
    }

    public function update(): void
    {
        $this->requireAuth();
        $this->ensurePost();
        $table = $this->tableName('write');
        $this->denyReadOnlyModule($table, trim((string) ($_POST['module'] ?? '')));
        $primaryKey = $this->tables->primaryKey($table);
        if ($primaryKey === null) {
            $this->flash('Bu tabloda birincil anahtar yok.');
            $this->redirect(AdminAuth::url('/table?name=' . rawurlencode($table)));
        }

        $id = trim((string) ($_POST['_id'] ?? ''));
        $this->validatePasswordConfirmation($table, $_POST, $id);
        $this->ensureSliderSchema($table);
        try {
            $this->tables->update($table, $primaryKey, $id, $_POST);
        } catch (Throwable $e) {
            AdminAuth::writeLog(AdminAuth::userName(), 'table_update', $table, 'fail', $e->getMessage(), $id);
            $this->flash('Kayıt güncellenemedi: ' . $e->getMessage());
            $this->redirect(AdminAuth::url('/table/edit?name=' . rawurlencode($table) . '&id=' . rawurlencode($id) . '&module=' . rawurlencode(trim((string) ($_POST['module'] ?? '')))));
        }
        AdminAuth::writeLog(AdminAuth::userName(), 'table_update', $table, 'success', $id);
        $this->flash('Kayıt güncellendi.');
        if ($table === 'users' && $id !== '') {
            $this->redirect(AdminAuth::url('/user?id=' . rawurlencode($id)));
        }
        $this->redirect($this->listUrl($table, trim((string) ($_POST['module'] ?? ''))));
    }

    public function delete(): void
    {
        $this->requireAuth();
        $this->ensurePost();
        $table = $this->tableName('delete');
        $this->denyReadOnlyModule($table, trim((string) ($_POST['module'] ?? '')));
        $primaryKey = $this->tables->primaryKey($table);
        if ($primaryKey !== null) {
            $deleteId = trim((string) ($_POST['_id'] ?? ''));
            $this->tables->delete($table, $primaryKey, $deleteId);
            AdminAuth::writeLog(AdminAuth::userName(), 'table_delete', $table, 'success', $deleteId);
            $this->flash('Kayıt silindi.');
        }

        $this->redirect($this->listUrl($table, trim((string) ($_POST['module'] ?? ''))));
    }

    private function tableName(string $action = 'view'): string
    {
        $table = trim((string) ($_GET['name'] ?? $_POST['name'] ?? ''));
        $this->tables->assertTable($table);
        $this->assertAllowedTableAccess($table, $action);

        return $table;
    }

    private function assertAllowedTableAccess(string $table, string $action): void
    {
        $moduleKey = $this->moduleKeyForTable($table, trim((string) ($_GET['module'] ?? $_POST['module'] ?? '')));
        if ($moduleKey === '') {
            throw new InvalidArgumentException('Bu tablo admin allowlist içinde değil.');
        }
        $this->requirePermission($moduleKey);

        if ($action !== 'view' && in_array($table, $this->writeProtectedTables(), true)) {
            http_response_code(403);
            echo 'Bu tablo doğrudan düzenlenemez.';
            exit;
        }
    }

    private function moduleKeyForTable(string $table, string $requestedModule): string
    {
        $modules = isset($this->config['modules']) && is_array($this->config['modules']) ? $this->config['modules'] : [];
        if ($requestedModule !== '' && isset($modules[$requestedModule]) && (string) ($modules[$requestedModule]['table'] ?? '') === $table) {
            return $requestedModule;
        }
        foreach ($modules as $key => $module) {
            if (is_array($module) && (string) ($module['table'] ?? '') === $table) {
                return (string) $key;
            }
        }

        return '';
    }

    /**
     * Tables with financial, auth, provider or audit impact must use dedicated controllers.
     *
     * @return list<string>
     */
    private function writeProtectedTables(): array
    {
        return [
            'users',
            'admin_permissions',
            'admin_sessions',
            'admin_logs',
            'megapayz_config',
            'megapayz_transactions',
            'megapayz_callbacks',
            'drakon_config',
            'drakon_transactions',
            'drakon_webhook_logs',
            'bgaming_config',
            'bgaming_transactions',
            'bgaming_wallet_logs',
        ];
    }

    private function denyReadOnlyModule(string $table, string $moduleKey): void
    {
        if (in_array($moduleKey, ['deposits', 'withdrawals'], true) && $table === 'megapayz_transactions') {
            $message = $moduleKey === 'withdrawals'
                ? 'Çekim kayıtları salt okunurdur; düzenleme ve silme kapalıdır.'
                : 'Yatırım kayıtları salt okunurdur; düzenleme ve silme kapalıdır.';
            $this->flash($message);
            $this->redirect(AdminAuth::url('/module?key=' . rawurlencode($moduleKey)));
        }
    }

    private function ensurePost(): void
    {
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }
    }

    private function validatePasswordConfirmation(string $table, array $input, string $id = ''): void
    {
        if (!in_array($table, ['users', 'admins'], true) || !array_key_exists('password', $input)) {
            return;
        }

        $password = trim((string) ($input['password'] ?? ''));
        $confirmation = trim((string) ($input['password_confirmation'] ?? ''));
        if ($password === '' && $confirmation === '') {
            return;
        }

        if (strlen($password) < 6) {
            $this->flash('Şifre en az 6 karakter olmalıdır.');
            $this->redirect($this->passwordRedirectUrl($table, $id));
        }

        if ($password !== $confirmation) {
            $this->flash('Şifre tekrarı eşleşmiyor.');
            $this->redirect($this->passwordRedirectUrl($table, $id));
        }
    }

    private function passwordRedirectUrl(string $table, string $id): string
    {
        if ($id !== '') {
            return AdminAuth::url('/table/edit?name=' . rawurlencode($table) . '&id=' . rawurlencode($id));
        }

        return AdminAuth::url('/table/create?name=' . rawurlencode($table));
    }

    private function listUrl(string $table, string $moduleKey): string
    {
        if ($moduleKey !== '') {
            return AdminAuth::url('/module?key=' . rawurlencode($moduleKey));
        }

        return AdminAuth::url('/table?name=' . rawurlencode($table));
    }

    private function isModalRequest(): bool
    {
        return (string) ($_GET['modal'] ?? '') === '1'
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
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

    private function featuredTables(): array
    {
        $out = [];
        $modules = isset($this->config['modules']) && is_array($this->config['modules']) ? $this->config['modules'] : [];
        foreach ($modules as $module) {
            if (!is_array($module) || empty($module['table']) || empty($module['title'])) {
                continue;
            }
            $out[(string) $module['table']] = (string) $module['title'];
        }

        return $out;
    }

    private function ensureSliderSchema(string $table): void
    {
        if ($table !== 'sliders') {
            return;
        }
        $slidersApi = defined('BASE_PATH')
            ? BASE_PATH . '/api/Sliders.php'
            : dirname(__DIR__, 2) . '/api/Sliders.php';
        if (!is_readable($slidersApi)) {
            return;
        }
        require_once $slidersApi;
        if (class_exists('ApiSliders', false)) {
            ApiSliders::ensureCategoryColumnSupportsBgaming(AdminDatabase::pdo());
        }
    }
}
