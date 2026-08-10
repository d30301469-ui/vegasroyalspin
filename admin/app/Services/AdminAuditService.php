<?php

declare(strict_types=1);

/**
 * Uyumluluk denetim kaydı — admin_audit_logs yazımı + birleşik liste.
 */
final class AdminAuditService
{
    public static function ensureTable(PDO $pdo): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

        $migration = dirname(__DIR__) . '/database/migrations/2026_06_13_000000_create_audit_and_meta_tables.php';
        if (is_readable($migration)) {
            $runner = require $migration;
            if (is_callable($runner)) {
                $runner($pdo);
            }
        } else {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS admin_audit_logs (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    admin_id INT UNSIGNED NOT NULL DEFAULT 0,
                    admin_username VARCHAR(100) NULL,
                    action VARCHAR(120) NOT NULL,
                    entity_type VARCHAR(80) NULL,
                    entity_id VARCHAR(120) NULL,
                    note VARCHAR(1000) NULL,
                    meta JSON NULL,
                    ip_address VARCHAR(64) NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_aal_admin (admin_id, created_at),
                    KEY idx_aal_action (action),
                    KEY idx_aal_entity (entity_type, entity_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        $ready = true;
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function write(
        PDO $pdo,
        string $action,
        ?string $entityType = null,
        string|int|null $entityId = null,
        string $note = '',
        array $meta = []
    ): void {
        try {
            self::ensureTable($pdo);
            $admin = class_exists('AdminAuth', false) ? AdminAuth::user() : null;
            $adminId = (int) ($admin['id'] ?? 0);
            $adminName = trim((string) ($admin['username'] ?? ''));
            if ($adminName === '' && class_exists('AdminAuth', false)) {
                $adminName = (string) AdminAuth::userName();
            }

            $pdo->prepare(
                'INSERT INTO admin_audit_logs
                    (admin_id, admin_username, action, entity_type, entity_id, note, meta, ip_address, created_at)
                 VALUES
                    (:aid, :uname, :action, :etype, :eid, :note, :meta, :ip, NOW())'
            )->execute([
                'aid' => $adminId,
                'uname' => $adminName !== '' ? $adminName : null,
                'action' => substr(trim($action), 0, 120),
                'etype' => $entityType !== null && $entityType !== '' ? substr($entityType, 0, 80) : null,
                'eid' => $entityId !== null && (string) $entityId !== '' ? substr((string) $entityId, 0, 120) : null,
                'note' => $note !== '' ? substr($note, 0, 1000) : null,
                'meta' => $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? '') ?: null,
            ]);
        } catch (Throwable) {
            // Denetim kaydı asla ana işlemi bozmaz.
        }
    }

    /**
     * @param array{q?:string,action?:string,admin?:string,source?:string,page?:int,per_page?:int} $filters
     * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int}
     */
    public static function listUnified(PDO $pdo, array $filters = []): array
    {
        self::ensureTable($pdo);

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($filters['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;
        $q = trim((string) ($filters['q'] ?? ''));
        $action = trim((string) ($filters['action'] ?? ''));
        $admin = trim((string) ($filters['admin'] ?? ''));
        $source = trim((string) ($filters['source'] ?? '')); // audit|panel|''

        $whereAudit = [];
        $wherePanel = [];
        $bind = [];
        $panelHasDescription = self::tableExists($pdo, 'admin_logs') && self::columnExists($pdo, 'admin_logs', 'description');

        if ($q !== '') {
            $whereAudit[] = '(a.action LIKE :q_a OR a.admin_username LIKE :q_a OR a.entity_type LIKE :q_a OR CAST(a.entity_id AS CHAR) LIKE :q_a OR a.note LIKE :q_a)';
            if ($panelHasDescription) {
                $wherePanel[] = '(l.action LIKE :q_p OR l.admin_username LIKE :q_p OR l.entity_type LIKE :q_p OR CAST(l.entity_id AS CHAR) LIKE :q_p OR COALESCE(l.description, l.status, \'\') LIKE :q_p)';
            } else {
                $wherePanel[] = '(l.action LIKE :q_p OR l.admin_username LIKE :q_p OR l.entity_type LIKE :q_p OR CAST(l.entity_id AS CHAR) LIKE :q_p OR COALESCE(l.status, \'\') LIKE :q_p)';
            }
            $bind['q_a'] = '%' . $q . '%';
            $bind['q_p'] = '%' . $q . '%';
        }
        if ($action !== '') {
            $whereAudit[] = 'a.action = :action_a';
            $wherePanel[] = 'l.action = :action_p';
            $bind['action_a'] = $action;
            $bind['action_p'] = $action;
        }
        if ($admin !== '') {
            $whereAudit[] = 'a.admin_username LIKE :admin_a';
            $wherePanel[] = 'l.admin_username LIKE :admin_p';
            $bind['admin_a'] = '%' . $admin . '%';
            $bind['admin_p'] = '%' . $admin . '%';
        }

        $auditWhereSql = $whereAudit !== [] ? ('WHERE ' . implode(' AND ', $whereAudit)) : '';
        $panelWhereSql = $wherePanel !== [] ? ('WHERE ' . implode(' AND ', $wherePanel)) : '';

        $hasPanelLogs = self::tableExists($pdo, 'admin_logs');
        $includeAudit = $source === '' || $source === 'audit';
        $includePanel = $hasPanelLogs && ($source === '' || $source === 'panel');

        $parts = [];
        $bindForParts = [];
        if ($includeAudit) {
            $parts[] = "SELECT a.id,
                               a.admin_id,
                               a.admin_username,
                               a.action,
                               a.entity_type,
                               CAST(a.entity_id AS CHAR) AS entity_id,
                               a.note,
                               a.ip_address,
                               a.created_at,
                               'audit' AS source
                        FROM admin_audit_logs a
                        {$auditWhereSql}";
            foreach (['q_a', 'action_a', 'admin_a'] as $key) {
                if (array_key_exists($key, $bind)) {
                    $bindForParts[$key] = $bind[$key];
                }
            }
        }
        if ($includePanel) {
            $descExpr = $panelHasDescription
                ? 'COALESCE(l.description, l.status, \'\')'
                : 'COALESCE(l.status, \'\')';
            $parts[] = "SELECT l.id,
                               l.admin_id,
                               l.admin_username,
                               l.action,
                               l.entity_type,
                               CAST(l.entity_id AS CHAR) AS entity_id,
                               {$descExpr} AS note,
                               l.ip_address,
                               l.created_at,
                               'panel' AS source
                        FROM admin_logs l
                        {$panelWhereSql}";
            foreach (['q_p', 'action_p', 'admin_p'] as $key) {
                if (array_key_exists($key, $bind)) {
                    $bindForParts[$key] = $bind[$key];
                }
            }
        }

        if ($parts === []) {
            return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'total_pages' => 1];
        }

        $union = implode(' UNION ALL ', $parts);

        try {
            $countSql = "SELECT COUNT(*) FROM ({$union}) x";
            $countStmt = $pdo->prepare($countSql);
            foreach ($bindForParts as $key => $value) {
                $countStmt->bindValue(':' . $key, $value);
            }
            $countStmt->execute();
            $total = (int) $countStmt->fetchColumn();

            $listSql = "SELECT * FROM ({$union}) x ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset";
            $listStmt = $pdo->prepare($listSql);
            foreach ($bindForParts as $key => $value) {
                $listStmt->bindValue(':' . $key, $value);
            }
            $listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $listStmt->execute();
            $items = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            try {
                $fallbackBind = [];
                foreach (['q_a' => 'q', 'action_a' => 'action', 'admin_a' => 'admin'] as $from => $to) {
                    if (isset($bind[$from])) {
                        $fallbackBind[$to] = $bind[$from];
                    }
                }
                $fallbackWhere = [];
                if (isset($fallbackBind['q'])) {
                    $fallbackWhere[] = '(a.action LIKE :q OR a.admin_username LIKE :q OR a.entity_type LIKE :q OR CAST(a.entity_id AS CHAR) LIKE :q OR a.note LIKE :q)';
                }
                if (isset($fallbackBind['action'])) {
                    $fallbackWhere[] = 'a.action = :action';
                }
                if (isset($fallbackBind['admin'])) {
                    $fallbackWhere[] = 'a.admin_username LIKE :admin';
                }
                $fallbackWhereSql = $fallbackWhere !== [] ? ('WHERE ' . implode(' AND ', $fallbackWhere)) : '';
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM admin_audit_logs a {$fallbackWhereSql}");
                $countStmt->execute($fallbackBind);
                $total = (int) $countStmt->fetchColumn();
                $listStmt = $pdo->prepare(
                    "SELECT a.id, a.admin_id, a.admin_username, a.action, a.entity_type,
                            CAST(a.entity_id AS CHAR) AS entity_id, a.note, a.ip_address, a.created_at,
                            'audit' AS source
                     FROM admin_audit_logs a {$fallbackWhereSql}
                     ORDER BY a.created_at DESC, a.id DESC
                     LIMIT :limit OFFSET :offset"
                );
                foreach ($fallbackBind as $key => $value) {
                    $listStmt->bindValue(':' . $key, $value);
                }
                $listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
                $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $listStmt->execute();
                $items = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable) {
                $items = [];
                $total = 0;
            }
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /** @return list<string> */
    public static function distinctActions(PDO $pdo, int $limit = 40): array
    {
        $actions = [];
        try {
            self::ensureTable($pdo);
            foreach ($pdo->query(
                'SELECT DISTINCT action FROM admin_audit_logs WHERE action IS NOT NULL AND action <> \'\' ORDER BY action ASC LIMIT ' . (int) $limit
            )->fetchAll(PDO::FETCH_COLUMN) ?: [] as $action) {
                $actions[] = (string) $action;
            }
            if (self::tableExists($pdo, 'admin_logs')) {
                foreach ($pdo->query(
                    'SELECT DISTINCT action FROM admin_logs WHERE action IS NOT NULL AND action <> \'\' ORDER BY action ASC LIMIT ' . (int) $limit
                )->fetchAll(PDO::FETCH_COLUMN) ?: [] as $action) {
                    $actions[] = (string) $action;
                }
            }
        } catch (Throwable) {
        }

        $actions = array_values(array_unique($actions));
        sort($actions);

        return array_slice($actions, 0, $limit);
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        try {
            // SHOW TABLES does not reliably accept prepared placeholders on MySQL/PDO.
            $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
            if ($safe === '' || $safe !== $table) {
                return false;
            }
            $st = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($safe));

            return $st !== false && (bool) $st->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        try {
            $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
            $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column) ?? '';
            if ($safeTable === '' || $safeTable !== $table || $safeColumn === '' || $safeColumn !== $column) {
                return false;
            }
            $st = $pdo->query('SHOW COLUMNS FROM `' . $safeTable . '` LIKE ' . $pdo->quote($safeColumn));

            return $st !== false && (bool) $st->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }
}
