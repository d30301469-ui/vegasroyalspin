<?php

declare(strict_types=1);

final class AdminTableRepository
{
    public function tables(): array
    {
        $stmt = AdminDatabase::pdo()->prepare(
            "SELECT TABLE_NAME AS name, TABLE_ROWS AS rows_count
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
             ORDER BY TABLE_NAME"
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function columns(string $table): array
    {
        $this->assertTable($table);
        $stmt = AdminDatabase::pdo()->prepare(
            "SELECT COLUMN_NAME AS name, COLUMN_TYPE AS type, DATA_TYPE AS data_type,
                    IS_NULLABLE AS nullable, COLUMN_KEY AS column_key, EXTRA AS extra,
                    COLUMN_DEFAULT AS column_default
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
             ORDER BY ORDINAL_POSITION"
        );
        $stmt->execute(['table' => $table]);

        return $stmt->fetchAll();
    }

    public function primaryKey(string $table): ?string
    {
        foreach ($this->columns($table) as $column) {
            if (($column['column_key'] ?? '') === 'PRI') {
                return (string) $column['name'];
            }
        }

        return null;
    }

    public function countRows(string $table, string $search = '', string $fixedWhere = '', array $fixedParams = []): int
    {
        [$where, $params] = $this->searchWhere($table, $search);
        [$where, $params] = $this->mergeWhere($where, $params, $fixedWhere, $fixedParams);
        $sql = 'SELECT COUNT(*) FROM ' . $this->quoteIdentifier($table) . $where;
        $stmt = AdminDatabase::pdo()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function rows(string $table, int $page = 1, int $perPage = 25, string $search = '', string $fixedWhere = '', array $fixedParams = []): array
    {
        $this->assertTable($table);
        $page = max(1, $page);
        $perPage = min(100, max(10, $perPage));
        $offset = ($page - 1) * $perPage;
        $primaryKey = $this->primaryKey($table);
        [$where, $params] = $this->searchWhere($table, $search);
        [$where, $params] = $this->mergeWhere($where, $params, $fixedWhere, $fixedParams);
        $order = $primaryKey !== null ? ' ORDER BY ' . $this->quoteIdentifier($primaryKey) . ' DESC' : '';

        $sql = 'SELECT * FROM ' . $this->quoteIdentifier($table) . $where . $order . ' LIMIT :limit OFFSET :offset';
        $stmt = AdminDatabase::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . ltrim((string) $key, ':'), $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return $table === 'megapayz_transactions' ? $this->withUserFullNames($rows) : $rows;
    }

    public function find(string $table, string $primaryKey, string $id): ?array
    {
        $this->assertTable($table);
        $sql = 'SELECT * FROM ' . $this->quoteIdentifier($table)
            . ' WHERE ' . $this->quoteIdentifier($primaryKey) . ' = :id LIMIT 1';
        $stmt = AdminDatabase::pdo()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return null;
        }

        if ($table === 'megapayz_transactions') {
            $rows = $this->withUserFullNames([$row]);
            return $rows[0] ?? $row;
        }

        return $row;
    }

    public function insert(string $table, array $input): void
    {
        if ($table === 'sliders') {
            $this->prepareSliderCategoryColumn();
        }

        $columns = $this->writableColumns($table, true);
        $data = $this->filterInput($columns, $input, $table);
        if ($data === []) {
            return;
        }

        $names = array_keys($data);
        $sql = 'INSERT INTO ' . $this->quoteIdentifier($table)
            . ' (' . implode(', ', array_map([$this, 'quoteIdentifier'], $names)) . ') VALUES ('
            . implode(', ', array_map(static fn (string $name): string => ':' . $name, $names)) . ')';

        try {
            AdminDatabase::pdo()->prepare($sql)->execute($data);
        } catch (PDOException $e) {
            if ($table === 'sliders' && $this->isSliderCategoryError($e)) {
                $this->prepareSliderCategoryColumn();
                AdminDatabase::pdo()->prepare($sql)->execute($data);

                return;
            }

            throw $e;
        }
    }

    public function update(string $table, string $primaryKey, string $id, array $input): void
    {
        if ($table === 'sliders') {
            $this->prepareSliderCategoryColumn();
        }

        $columns = array_filter(
            $this->writableColumns($table, false),
            static fn (array $column): bool => (string) $column['name'] !== $primaryKey
        );
        $data = $this->filterInput($columns, $input, $table);
        if ($data === []) {
            return;
        }

        $assignments = array_map(
            fn (string $name): string => $this->quoteIdentifier($name) . ' = :' . $name,
            array_keys($data)
        );
        $data['_id'] = $id;
        $sql = 'UPDATE ' . $this->quoteIdentifier($table)
            . ' SET ' . implode(', ', $assignments)
            . ' WHERE ' . $this->quoteIdentifier($primaryKey) . ' = :_id';

        try {
            AdminDatabase::pdo()->prepare($sql)->execute($data);
        } catch (PDOException $e) {
            if ($table === 'sliders' && $this->isSliderCategoryError($e)) {
                $this->prepareSliderCategoryColumn();
                AdminDatabase::pdo()->prepare($sql)->execute($data);

                return;
            }

            throw $e;
        }
    }

    public function delete(string $table, string $primaryKey, string $id): void
    {
        $this->assertTable($table);
        $sql = 'DELETE FROM ' . $this->quoteIdentifier($table)
            . ' WHERE ' . $this->quoteIdentifier($primaryKey) . ' = :id LIMIT 1';
        AdminDatabase::pdo()->prepare($sql)->execute(['id' => $id]);
    }

    public function assertTable(string $table): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new InvalidArgumentException('Geçersiz tablo adı.');
        }

        if ($table === 'footer_settings') {
            $this->ensureFooterSettingsTable();
        }
        if ($table === 'footer_pages') {
            $this->ensureFooterPagesTable();
        }
        if ($table === 'auth_sliders') {
            $this->ensureAuthSlidersTable();
        }
        if ($table === 'sliders') {
            $this->ensureSlidersTable();
        }
        if ($table === 'homepage_sections') {
            $this->ensureHomepageSectionsTable();
        }
        if ($table === 'site_ayarlar') {
            $this->ensureSiteSettingsTable();
        }
        if (in_array($table, ['loyalty_levels', 'user_loyalty_accounts', 'loyalty_point_transactions'], true)) {
            $this->ensureLoyaltyTables();
        }
        if (in_array($table, ['mail_outbound_log', 'mail_settings'], true)) {
            $this->ensureMailTables();
        }

        $stmt = AdminDatabase::pdo()->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
        );
        $stmt->execute(['table' => $table]);
        if ((int) $stmt->fetchColumn() === 0) {
            throw new InvalidArgumentException('Tablo bulunamadı.');
        }
    }

    private function ensureFooterSettingsTable(): void
    {
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', admin_project_root());
        }
        $apiBootstrap = admin_project_path('api/bootstrap.php');
        if (is_file($apiBootstrap)) {
            require_once $apiBootstrap;
            if (class_exists('ApiFooter')) {
                ApiFooter::fetch();
            }
        }
    }

    private function ensureFooterPagesTable(): void
    {
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', admin_project_root());
        }
        $apiBootstrap = admin_project_path('api/bootstrap.php');
        if (is_file($apiBootstrap)) {
            require_once $apiBootstrap;
            if (class_exists('ApiFooterPages')) {
                ApiFooterPages::ensureStorage();
            }
        }
    }

    private function ensureAuthSlidersTable(): void
    {
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', admin_project_root());
        }
        $apiBootstrap = admin_project_path('api/bootstrap.php');
        if (is_file($apiBootstrap)) {
            require_once $apiBootstrap;
            if (class_exists('ApiAuthSliders')) {
                ApiAuthSliders::ensureStorage();
            }
        }
    }

    private function ensureSlidersTable(): void
    {
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', admin_project_root());
        }
        $apiBootstrap = admin_project_path('api/bootstrap.php');
        if (is_file($apiBootstrap)) {
            require_once $apiBootstrap;
            if (class_exists('ApiSliders')) {
                ApiSliders::ensureStorage();
            }
        }
    }

    private function ensureHomepageSectionsTable(): void
    {
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', admin_project_root());
        }
        $apiBootstrap = admin_project_path('api/bootstrap.php');
        if (is_file($apiBootstrap)) {
            require_once $apiBootstrap;
            if (class_exists('ApiHomepageSections')) {
                ApiHomepageSections::ensureStorage();
            }
        }
    }

    private function ensureSiteSettingsTable(): void
    {
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', admin_project_root());
        }
        $apiBootstrap = admin_project_path('api/bootstrap.php');
        if (is_file($apiBootstrap)) {
            require_once $apiBootstrap;
            if (class_exists('ApiSiteSettings')) {
                ApiSiteSettings::ensureStorage();
            }
        }
    }

    private function ensureLoyaltyTables(): void
    {
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', admin_project_root());
        }
        $apiBootstrap = admin_project_path('api/bootstrap.php');
        if (is_file($apiBootstrap)) {
            require_once $apiBootstrap;
            if (class_exists('ApiLoyalty')) {
                ApiLoyalty::ensureStorage(AdminDatabase::pdo());
            }
        }
    }

    private function ensureMailTables(): void
    {
        try {
            $migration = admin_project_path('database/migrations/2026_06_10_000001_create_mail_tables.php');
            if (is_file($migration)) {
                (require $migration)(AdminDatabase::pdo());
            }
        } catch (Throwable) {
            // Mail table auto-create best-effort; assertTable will fail later if still unavailable.
        }
    }

    private function writableColumns(string $table, bool $forInsert): array
    {
        return array_values(array_filter($this->columns($table), static function (array $column) use ($forInsert): bool {
            $extra = strtolower((string) ($column['extra'] ?? ''));
            if (str_contains($extra, 'auto_increment') || str_contains($extra, 'generated')) {
                return false;
            }
            if ($forInsert && (string) ($column['column_key'] ?? '') === 'PRI' && str_contains($extra, 'auto_increment')) {
                return false;
            }

            return true;
        }));
    }

    private function filterInput(array $columns, array $input, string $table): array
    {
        $data = [];
        $passwordChanged = false;
        foreach ($columns as $column) {
            $name = (string) $column['name'];
            if (!array_key_exists($name, $input)) {
                continue;
            }
            $value = is_array($input[$name]) ? '' : trim((string) $input[$name]);
            if ($value === '' && preg_match('/password|secret|token|api_key|key/i', $name) === 1) {
                continue;
            }
            if ($name === 'password' && in_array($table, ['users', 'admins'], true)) {
                $data[$name] = password_hash($value, PASSWORD_DEFAULT);
                $passwordChanged = true;
                continue;
            }
            if ($table === 'sliders' && $name === 'category') {
                if (!defined('BASE_PATH')) {
                    define('BASE_PATH', admin_project_root());
                }
                $apiBootstrap = admin_project_path('api/bootstrap.php');
                if (is_file($apiBootstrap)) {
                    require_once $apiBootstrap;
                }
                $value = class_exists('ApiSliders') ? ApiSliders::normalizeCategory($value) : $value;
                if ($value === '') {
                    $value = 'home';
                }
            }
            $data[$name] = $this->normalizeColumnValue($column, $value);
        }

        if ($passwordChanged && $table === 'users' && !array_key_exists('password_changed_at', $data)) {
            foreach ($columns as $column) {
                if ((string) ($column['name'] ?? '') === 'password_changed_at') {
                    $data['password_changed_at'] = date('Y-m-d H:i:s');
                    break;
                }
            }
        }

        return $data;
    }

    private function normalizeColumnValue(array $column, string $value): mixed
    {
        $type = strtolower((string) ($column['data_type'] ?? ''));
        $nullable = (string) ($column['nullable'] ?? 'NO') === 'YES';
        $default = $column['column_default'] ?? null;

        if (in_array($type, ['int', 'bigint', 'tinyint', 'smallint', 'mediumint'], true)) {
            if ($value === '') {
                if ($nullable) {
                    return null;
                }
                if ($default !== null && is_numeric((string) $default)) {
                    return (int) $default;
                }

                return 0;
            }

            return (int) $value;
        }

        if (in_array($type, ['decimal', 'float', 'double'], true)) {
            if ($value === '') {
                if ($nullable) {
                    return null;
                }
                if ($default !== null && is_numeric((string) $default)) {
                    return (float) $default;
                }

                return 0.0;
            }

            return (float) $value;
        }

        if ($value === '' && $nullable) {
            return null;
        }

        if ($type === 'date' && $value !== '') {
            $timestamp = strtotime($value);
            return $timestamp === false ? $value : date('Y-m-d', $timestamp);
        }

        if (in_array($type, ['datetime', 'timestamp'], true) && $value !== '') {
            $normalized = str_replace('T', ' ', $value);
            $timestamp = strtotime($normalized);
            return $timestamp === false ? $normalized : date('Y-m-d H:i:s', $timestamp);
        }

        return $value;
    }

    private function searchWhere(string $table, string $search): array
    {
        $search = trim($search);
        if ($search === '') {
            return ['', []];
        }

        $parts = [];
        $params = [];
        $index = 0;
        foreach ($this->columns($table) as $column) {
            $name = (string) $column['name'];
            $type = strtolower((string) ($column['data_type'] ?? ''));
            if (in_array($type, ['char', 'varchar', 'text', 'tinytext', 'mediumtext', 'longtext', 'enum'], true)) {
                $param = 'search_' . $index++;
                $parts[] = $this->quoteIdentifier($name) . ' LIKE :' . $param;
                $params[$param] = '%' . $search . '%';
                continue;
            }
            if (is_numeric($search) && in_array($type, ['int', 'bigint', 'tinyint', 'smallint', 'mediumint', 'decimal', 'float', 'double'], true)) {
                $param = 'search_' . $index++;
                $parts[] = $this->quoteIdentifier($name) . ' = :' . $param;
                $params[$param] = $search;
            }
        }
        if ($table === 'megapayz_transactions') {
            try {
                $stmt = AdminDatabase::pdo()->prepare(
                    "SELECT id
                     FROM users
                     WHERE CONCAT(COALESCE(name, ''), ' ', COALESCE(surname, '')) LIKE :search
                        OR name LIKE :search
                        OR surname LIKE :search
                     LIMIT 100"
                );
                $stmt->execute(['search' => '%' . $search . '%']);
                $userIds = array_values(array_filter(
                    array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)),
                    static fn (int $id): bool => $id > 0
                ));
                if ($userIds !== []) {
                    $in = [];
                    foreach ($userIds as $userId) {
                        $param = 'search_user_' . $index++;
                        $in[] = ':' . $param;
                        $params[$param] = $userId;
                    }
                    $parts[] = $this->quoteIdentifier('user_id') . ' IN (' . implode(',', $in) . ')';
                }
            } catch (Throwable) {
                // Keep the generic table search available even if the users table is missing.
            }
        }
        if ($parts === []) {
            return ['', []];
        }

        return [' WHERE (' . implode(' OR ', $parts) . ')', $params];
    }

    private function mergeWhere(string $where, array $params, string $fixedWhere, array $fixedParams): array
    {
        $fixedWhere = trim($fixedWhere);
        if ($fixedWhere === '') {
            return [$where, $params];
        }
        $fixed = '(' . $fixedWhere . ')';
        $mergedWhere = $where === '' ? ' WHERE ' . $fixed : $where . ' AND ' . $fixed;

        return [$mergedWhere, array_merge($params, $fixedParams)];
    }

    private function withUserFullNames(array $rows): array
    {
        $userIds = [];
        foreach ($rows as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId > 0) {
                $userIds[$userId] = $userId;
            }
        }

        if ($userIds === []) {
            return $rows;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $stmt = AdminDatabase::pdo()->prepare(
                'SELECT id, name, surname, username FROM users WHERE id IN (' . $placeholders . ')'
            );
            $stmt->execute(array_values($userIds));
            $users = [];
            foreach ($stmt->fetchAll() as $user) {
                $fullName = trim((string) ($user['name'] ?? '') . ' ' . (string) ($user['surname'] ?? ''));
                $users[(int) ($user['id'] ?? 0)] = $fullName !== '' ? $fullName : (string) ($user['username'] ?? '');
            }

            foreach ($rows as &$row) {
                $userId = (int) ($row['user_id'] ?? 0);
                if ($userId > 0 && isset($users[$userId]) && trim($users[$userId]) !== '') {
                    $row['username'] = $users[$userId];
                }
            }
            unset($row);
        } catch (Throwable) {
            return $rows;
        }

        return $rows;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function prepareSliderCategoryColumn(): void
    {
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', admin_project_root());
        }
        $apiBootstrap = admin_project_path('api/bootstrap.php');
        if (is_file($apiBootstrap)) {
            require_once $apiBootstrap;
        }
        if (class_exists('ApiSliders', false)) {
            ApiSliders::ensureCategoryColumnSupportsBgaming(AdminDatabase::pdo());
        }
    }

    private function isSliderCategoryError(PDOException $e): bool
    {
        $msg = strtolower($e->getMessage());

        return str_contains($msg, 'category')
            && (
                str_contains($msg, 'truncated')
                || str_contains($msg, '1265')
                || str_contains($msg, 'enum')
                || str_contains($msg, 'incorrect')
            );
    }
}
