<?php

declare(strict_types=1);

/**
 * Performans indeksleri.
 *
 * Dashboard KPI'ları ve winners/raporlama sorguları tarih aralığı + tür/durum
 * filtreleri kullanıyordu ancak ilgili kolonlarda composite indeks yoktu, bu da
 * büyük transaction tablolarında full/range scan'e yol açıyordu. Bu migration
 * eksik indeksleri idempotent şekilde ekler (tablo/kolon/indeks yoksa atlar).
 */
return static function (PDO $pdo): void {
    $tableExists = static function (PDO $pdo, string $table): bool {
        try {
            return $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    };

    $columnExists = static function (PDO $pdo, string $table, string $column): bool {
        try {
            $cols = array_column(
                $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC),
                'Field'
            );
            return in_array($column, $cols, true);
        } catch (Throwable) {
            return false;
        }
    };

    $indexExists = static function (PDO $pdo, string $table, string $index): bool {
        try {
            $stmt = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $pdo->quote($index));
            return $stmt->fetchColumn() !== false;
        } catch (Throwable) {
            return false;
        }
    };

    $addIndex = static function (PDO $pdo, string $table, string $index, array $columns) use ($tableExists, $columnExists, $indexExists): void {
        if (!$tableExists($pdo, $table)) {
            return;
        }
        foreach ($columns as $col) {
            if (!$columnExists($pdo, $table, $col)) {
                return;
            }
        }
        if ($indexExists($pdo, $table, $index)) {
            return;
        }
        $colList = implode(', ', array_map(static fn ($c) => "`{$c}`", $columns));
        try {
            $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$index}` ({$colList})");
        } catch (Throwable) {
            // İndeks zaten varsa veya tablo kilitliyse sessizce geç.
        }
    };

    $addIndex($pdo, 'megapayz_transactions', 'idx_megapayz_type_status_created', ['type', 'status', 'created_at']);
    $addIndex($pdo, 'drakon_transactions', 'idx_drakon_txntype_created', ['txn_type', 'created_at']);
    $addIndex($pdo, 'bgaming_transactions', 'idx_bgaming_txntype_processed', ['txn_type', 'processed_at']);
    $addIndex($pdo, 'kyc_requests', 'idx_kyc_status_submitted', ['status', 'submitted_at']);
    $addIndex($pdo, 'user_active_bonuses', 'idx_active_bonus_status_created', ['status', 'created_at']);
    $addIndex($pdo, 'bonus_claim_requests', 'idx_bonus_claim_status_created', ['status', 'created_at']);
    $addIndex($pdo, 'admin_balance_adjustments', 'idx_balance_adj_action_created', ['action', 'created_at']);
    $addIndex($pdo, 'users', 'idx_users_last_login', ['last_login_at']);
};
