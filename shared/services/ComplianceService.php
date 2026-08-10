<?php

declare(strict_types=1);

final class ComplianceService
{
    public static function ensureTables(PDO $pdo): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $migration = shared_package_root() . '/database/migrations/2026_06_12_000000_create_compliance_tables.php';
        if (!is_readable($migration)) {
            $migration = shared_project_root() . '/admin/database/migrations/2026_06_12_000000_create_compliance_tables.php';
        }
        if (is_readable($migration)) {
            $runner = require $migration;
            if (is_callable($runner)) {
                $runner($pdo);
            }
        }
        self::ensureRiskScoreStorage($pdo);
        $ready = true;
    }

    public static function ensureRiskScoreStorage(PDO $pdo): void
    {
        static $scoreReady = false;
        if ($scoreReady) {
            return;
        }
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS user_risk_scores (
                user_id INT UNSIGNED NOT NULL,
                score TINYINT UNSIGNED NOT NULL DEFAULT 0,
                level VARCHAR(20) NOT NULL DEFAULT 'clear',
                factors_json JSON NULL,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (user_id),
                KEY idx_user_risk_level (level, score)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $scoreReady = true;
    }

    /**
     * @param array{status?:string,severity?:string,q?:string,rule?:string,page?:int,per_page?:int} $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public static function listAmlAlerts(PDO $pdo, int $page = 1, int $perPage = 25, string $status = 'open', array $filters = []): array
    {
        self::ensureTables($pdo);
        $filters['status'] = $filters['status'] ?? $status;

        return self::listFromTable($pdo, 'aml_alerts', $page, $perPage, $filters);
    }

    /**
     * @param array{status?:string,severity?:string,q?:string,rule?:string,page?:int,per_page?:int} $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public static function listRiskAlerts(PDO $pdo, int $page = 1, int $perPage = 25, string $status = 'open', array $filters = []): array
    {
        self::ensureTables($pdo);
        $filters['status'] = $filters['status'] ?? $status;

        return self::listFromTable($pdo, 'risk_alerts', $page, $perPage, $filters);
    }

    public static function resolveAml(PDO $pdo, int $id, string $adminName, string $note = ''): bool
    {
        self::ensureTables($pdo);

        return self::resolveInTable($pdo, 'aml_alerts', $id, $adminName, $note);
    }

    public static function resolveRisk(PDO $pdo, int $id, string $adminName, string $note = ''): bool
    {
        self::ensureTables($pdo);

        return self::resolveInTable($pdo, 'risk_alerts', $id, $adminName, $note);
    }

    public static function ignoreAml(PDO $pdo, int $id, string $adminName, string $note = ''): bool
    {
        self::ensureTables($pdo);

        return self::setStatusInTable($pdo, 'aml_alerts', $id, 'ignored', $adminName, $note);
    }

    public static function ignoreRisk(PDO $pdo, int $id, string $adminName, string $note = ''): bool
    {
        self::ensureTables($pdo);

        return self::setStatusInTable($pdo, 'risk_alerts', $id, 'ignored', $adminName, $note);
    }

    public static function countOpen(PDO $pdo, string $table): int
    {
        self::ensureTables($pdo);
        if (!in_array($table, ['aml_alerts', 'risk_alerts'], true)) {
            return 0;
        }
        try {
            return (int) $pdo->query("SELECT COUNT(*) FROM {$table} WHERE status = 'open'")->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    /** @return array{open:int,critical:int,high:int,resolved_today:int} */
    public static function summary(PDO $pdo, string $table): array
    {
        self::ensureTables($pdo);
        $out = ['open' => 0, 'critical' => 0, 'high' => 0, 'resolved_today' => 0];
        if (!in_array($table, ['aml_alerts', 'risk_alerts'], true)) {
            return $out;
        }
        try {
            $out['open'] = (int) $pdo->query("SELECT COUNT(*) FROM {$table} WHERE status = 'open'")->fetchColumn();
            $out['critical'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM {$table} WHERE status = 'open' AND severity = 'critical'"
            )->fetchColumn();
            $out['high'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM {$table} WHERE status = 'open' AND severity = 'high'"
            )->fetchColumn();
            $out['resolved_today'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM {$table} WHERE status = 'resolved' AND DATE(resolved_at) = CURDATE()"
            )->fetchColumn();
        } catch (Throwable) {
        }

        return $out;
    }

    /**
     * Chart.js için AML/Risk grafik paketleri.
     *
     * @return array{
     *   severity: array{labels:list<string>,data:list<int>,colors:list<string>},
     *   status: array{labels:list<string>,data:list<int>,colors:list<string>},
     *   rules: array{labels:list<string>,data:list<int>},
     *   trend: array{labels:list<string>,created:list<int>,resolved:list<int>}
     * }
     */
    public static function chartBundle(PDO $pdo, string $table): array
    {
        self::ensureTables($pdo);
        $empty = [
            'severity' => [
                'labels' => ['critical', 'high', 'medium', 'low'],
                'data' => [0, 0, 0, 0],
                'colors' => ['#ef4444', '#f97316', '#eab308', '#38bdf8'],
            ],
            'status' => [
                'labels' => ['Açık', 'Çözülmüş', 'Yoksayılan'],
                'data' => [0, 0, 0],
                'colors' => ['#f59e0b', '#22c55e', '#64748b'],
            ],
            'rules' => ['labels' => [], 'data' => []],
            'trend' => ['labels' => [], 'created' => [], 'resolved' => []],
        ];
        if (!in_array($table, ['aml_alerts', 'risk_alerts'], true)) {
            return $empty;
        }

        $codeCol = $table === 'aml_alerts' ? 'rule_code' : 'alert_type';

        try {
            $sevMap = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
            foreach ($pdo->query(
                "SELECT severity, COUNT(*) AS c FROM {$table} WHERE status = 'open' GROUP BY severity"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $key = strtolower((string) ($row['severity'] ?? ''));
                if (isset($sevMap[$key])) {
                    $sevMap[$key] = (int) ($row['c'] ?? 0);
                }
            }
            $empty['severity']['data'] = array_values($sevMap);

            $stMap = ['open' => 0, 'resolved' => 0, 'ignored' => 0];
            foreach ($pdo->query(
                "SELECT status, COUNT(*) AS c FROM {$table} GROUP BY status"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $key = strtolower((string) ($row['status'] ?? ''));
                if (isset($stMap[$key])) {
                    $stMap[$key] = (int) ($row['c'] ?? 0);
                }
            }
            $empty['status']['data'] = array_values($stMap);

            $ruleRows = $pdo->query(
                "SELECT {$codeCol} AS code, COUNT(*) AS c
                 FROM {$table}
                 WHERE status = 'open'
                 GROUP BY {$codeCol}
                 ORDER BY c DESC
                 LIMIT 8"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($ruleRows as $row) {
                $empty['rules']['labels'][] = (string) ($row['code'] ?? '—');
                $empty['rules']['data'][] = (int) ($row['c'] ?? 0);
            }

            $days = [];
            for ($i = 13; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime('-' . $i . ' day'));
                $days[$d] = ['created' => 0, 'resolved' => 0];
                $empty['trend']['labels'][] = date('d.m', strtotime($d));
            }
            foreach ($pdo->query(
                "SELECT DATE(created_at) AS d, COUNT(*) AS c
                 FROM {$table}
                 WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
                 GROUP BY DATE(created_at)"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $d = (string) ($row['d'] ?? '');
                if (isset($days[$d])) {
                    $days[$d]['created'] = (int) ($row['c'] ?? 0);
                }
            }
            foreach ($pdo->query(
                "SELECT DATE(resolved_at) AS d, COUNT(*) AS c
                 FROM {$table}
                 WHERE resolved_at IS NOT NULL
                   AND resolved_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
                   AND status IN ('resolved','ignored')
                 GROUP BY DATE(resolved_at)"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $d = (string) ($row['d'] ?? '');
                if (isset($days[$d])) {
                    $days[$d]['resolved'] = (int) ($row['c'] ?? 0);
                }
            }
            foreach ($days as $vals) {
                $empty['trend']['created'][] = $vals['created'];
                $empty['trend']['resolved'][] = $vals['resolved'];
            }
        } catch (Throwable) {
        }

        return $empty;
    }

    /** @param array<string, mixed> $data */
    public static function createAmlAlert(PDO $pdo, array $data): int
    {
        self::ensureTables($pdo);
        if (self::hasRecentOpenAlert($pdo, 'aml_alerts', (int) ($data['user_id'] ?? 0), (string) ($data['rule_code'] ?? 'manual'))) {
            return 0;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO aml_alerts (user_id, rule_code, severity, status, title, description, payload_json)
             VALUES (:user_id, :rule_code, :severity, :status, :title, :description, :payload_json)'
        );
        $stmt->execute([
            'user_id' => isset($data['user_id']) ? (int) $data['user_id'] : null,
            'rule_code' => trim((string) ($data['rule_code'] ?? 'manual')),
            'severity' => self::normalizeSeverity((string) ($data['severity'] ?? 'medium')),
            'status' => 'open',
            'title' => trim((string) ($data['title'] ?? 'AML uyarısı')),
            'description' => trim((string) ($data['description'] ?? '')),
            'payload_json' => isset($data['payload']) ? json_encode($data['payload'], JSON_UNESCAPED_UNICODE) : null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public static function createRiskAlert(PDO $pdo, array $data): int
    {
        self::ensureTables($pdo);
        $alertType = trim((string) ($data['alert_type'] ?? 'general'));
        if (self::hasRecentOpenAlert($pdo, 'risk_alerts', (int) ($data['user_id'] ?? 0), $alertType)) {
            return 0;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO risk_alerts (user_id, alert_type, severity, status, title, description, payload_json)
             VALUES (:user_id, :alert_type, :severity, :status, :title, :description, :payload_json)'
        );
        $stmt->execute([
            'user_id' => isset($data['user_id']) ? (int) $data['user_id'] : null,
            'alert_type' => $alertType,
            'severity' => self::normalizeSeverity((string) ($data['severity'] ?? 'medium')),
            'status' => 'open',
            'title' => trim((string) ($data['title'] ?? 'Risk uyarısı')),
            'description' => trim((string) ($data['description'] ?? '')),
            'payload_json' => isset($data['payload']) ? json_encode($data['payload'], JSON_UNESCAPED_UNICODE) : null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    /** @param array<string, mixed> $factors */
    public static function upsertRiskScore(PDO $pdo, int $userId, int $score, string $level, array $factors = []): void
    {
        if ($userId <= 0) {
            return;
        }
        self::ensureRiskScoreStorage($pdo);
        $stmt = $pdo->prepare(
            'INSERT INTO user_risk_scores (user_id, score, level, factors_json, updated_at)
             VALUES (:uid, :score, :level, :factors, NOW())
             ON DUPLICATE KEY UPDATE score = VALUES(score), level = VALUES(level),
               factors_json = VALUES(factors_json), updated_at = NOW()'
        );
        $stmt->execute([
            'uid' => $userId,
            'score' => max(0, min(100, $score)),
            'level' => in_array($level, ['clear', 'low', 'medium', 'high', 'critical'], true) ? $level : 'clear',
            'factors' => $factors !== [] ? json_encode($factors, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    /** @return array{score:int,level:string,factors:array<string,mixed>}|null */
    public static function getRiskScore(PDO $pdo, int $userId): ?array
    {
        self::ensureRiskScoreStorage($pdo);
        try {
            $st = $pdo->prepare('SELECT score, level, factors_json FROM user_risk_scores WHERE user_id = :uid LIMIT 1');
            $st->execute(['uid' => $userId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return null;
            }
            $factors = [];
            if (!empty($row['factors_json'])) {
                $decoded = json_decode((string) $row['factors_json'], true);
                $factors = is_array($decoded) ? $decoded : [];
            }

            return [
                'score' => (int) ($row['score'] ?? 0),
                'level' => (string) ($row['level'] ?? 'clear'),
                'factors' => $factors,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array{status?:string,severity?:string,q?:string,rule?:string} $filters
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    private static function listFromTable(PDO $pdo, string $table, int $page, int $perPage, array $filters): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(10, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $bind = [];
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'a.status = :status';
            $bind['status'] = $status;
        }
        $severity = trim((string) ($filters['severity'] ?? ''));
        if ($severity !== '' && in_array($severity, ['low', 'medium', 'high', 'critical'], true)) {
            $where[] = 'a.severity = :severity';
            $bind['severity'] = $severity;
        }
        $rule = trim((string) ($filters['rule'] ?? ''));
        if ($rule !== '') {
            if ($table === 'aml_alerts') {
                $where[] = 'a.rule_code = :rule';
            } else {
                $where[] = 'a.alert_type = :rule';
            }
            $bind['rule'] = $rule;
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(u.username LIKE :q OR u.name LIKE :q OR u.surname LIKE :q OR CAST(a.user_id AS CHAR) = :q_exact)';
            $bind['q'] = '%' . $q . '%';
            $bind['q_exact'] = $q;
        }
        $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

        try {
            $countStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM {$table} a LEFT JOIN users u ON u.id = a.user_id {$whereSql}"
            );
            $countStmt->execute($bind);
            $total = (int) $countStmt->fetchColumn();

            $codeCol = $table === 'aml_alerts' ? 'a.rule_code' : 'a.alert_type';
            $sql = "SELECT a.*, u.username, u.email, u.name, u.surname,
                           rs.score AS risk_score, rs.level AS risk_level,
                           {$codeCol} AS rule_or_type
                    FROM {$table} a
                    LEFT JOIN users u ON u.id = a.user_id
                    LEFT JOIN user_risk_scores rs ON rs.user_id = a.user_id
                    {$whereSql}
                    ORDER BY
                      FIELD(a.severity, 'critical','high','medium','low') ASC,
                      a.created_at DESC
                    LIMIT :limit OFFSET :offset";
            $stmt = $pdo->prepare($sql);
            foreach ($bind as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            $items = [];
            $total = 0;
        }

        return ['items' => is_array($items) ? $items : [], 'total' => $total];
    }

    private static function resolveInTable(PDO $pdo, string $table, int $id, string $adminName, string $note): bool
    {
        return self::setStatusInTable($pdo, $table, $id, 'resolved', $adminName, $note);
    }

    private static function setStatusInTable(PDO $pdo, string $table, int $id, string $status, string $adminName, string $note): bool
    {
        if (!in_array($table, ['aml_alerts', 'risk_alerts'], true)) {
            return false;
        }
        if (!in_array($status, ['resolved', 'ignored'], true)) {
            return false;
        }
        $stmt = $pdo->prepare(
            "UPDATE {$table} SET status = :status, resolved_by = :resolved_by, resolved_at = NOW(),
             resolution_note = :note, updated_at = NOW()
             WHERE id = :id AND status = 'open'"
        );
        $stmt->execute([
            'status' => $status,
            'id' => $id,
            'resolved_by' => $adminName,
            'note' => $note !== '' ? $note : null,
        ]);

        return $stmt->rowCount() > 0;
    }

    private static function hasRecentOpenAlert(PDO $pdo, string $table, int $userId, string $code): bool
    {
        if ($userId <= 0 || $code === '') {
            return false;
        }
        $column = $table === 'aml_alerts' ? 'rule_code' : 'alert_type';
        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = :user_id AND {$column} = :code
                 AND status = 'open' AND created_at >= DATE_SUB(NOW(), INTERVAL 6 HOUR)"
            );
            $stmt->execute(['user_id' => $userId, 'code' => $code]);

            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private static function normalizeSeverity(string $severity): string
    {
        return in_array($severity, ['low', 'medium', 'high', 'critical'], true) ? $severity : 'medium';
    }
}
