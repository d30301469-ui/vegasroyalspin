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
        $migration = dirname(__DIR__) . '/database/migrations/2026_06_12_000000_create_compliance_tables.php';
        if (is_readable($migration)) {
            $runner = require $migration;
            if (is_callable($runner)) {
                $runner($pdo);
            }
        }
        $ready = true;
    }

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public static function listAmlAlerts(PDO $pdo, int $page = 1, int $perPage = 25, string $status = 'open'): array
    {
        self::ensureTables($pdo);

        return self::listFromTable($pdo, 'aml_alerts', $page, $perPage, $status);
    }

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public static function listRiskAlerts(PDO $pdo, int $page = 1, int $perPage = 25, string $status = 'open'): array
    {
        self::ensureTables($pdo);

        return self::listFromTable($pdo, 'risk_alerts', $page, $perPage, $status);
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

    /** @return array{items: list<array<string, mixed>>, total: int} */
    private static function listFromTable(PDO $pdo, string $table, int $page, int $perPage, string $status): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(10, $perPage));
        $offset = ($page - 1) * $perPage;
        $where = $status !== '' ? 'WHERE a.status = :status' : '';
        $bind = $status !== '' ? ['status' => $status] : [];
        try {
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} a {$where}");
            $countStmt->execute($bind);
            $total = (int) $countStmt->fetchColumn();
            $sql = "SELECT a.*, u.username, u.email FROM {$table} a LEFT JOIN users u ON u.id = a.user_id {$where}
                    ORDER BY a.created_at DESC LIMIT :limit OFFSET :offset";
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
        $stmt = $pdo->prepare(
            "UPDATE {$table} SET status = 'resolved', resolved_by = :resolved_by, resolved_at = NOW(),
             resolution_note = :note, updated_at = NOW() WHERE id = :id AND status <> 'resolved'"
        );
        $stmt->execute(['id' => $id, 'resolved_by' => $adminName, 'note' => $note !== '' ? $note : null]);

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
