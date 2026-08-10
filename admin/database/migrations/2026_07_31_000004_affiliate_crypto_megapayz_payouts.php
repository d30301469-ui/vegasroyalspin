<?php
declare(strict_types=1);

/**
 * Affiliate kripto ödemelerini MegaPayz çekim akışına bağlar.
 * - affiliate_payouts: sağlayıcı trx/id alanları
 * - megapayz_transactions: affiliate_payout_id köprüsü (callback'te users.balance dokunulmaz)
 */
return static function (PDO $pdo): void {
    $addColumn = static function (PDO $pdo, string $table, string $column, string $definition): void {
        try {
            $cols = array_column(
                $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`')->fetchAll(PDO::FETCH_ASSOC),
                'Field'
            );
        } catch (Throwable $e) {
            error_log('[migration affiliate_crypto_mp] columns ' . $table . ': ' . $e->getMessage());
            return;
        }
        if (!in_array($column, $cols, true)) {
            $pdo->exec('ALTER TABLE `' . str_replace('`', '', $table) . '` ADD COLUMN ' . $definition);
        }
    };

    $addIndex = static function (PDO $pdo, string $table, string $name, string $sql): void {
        try {
            $exists = $pdo->query(
                "SHOW INDEX FROM `" . str_replace('`', '', $table) . "` WHERE Key_name = " . $pdo->quote($name)
            )->fetchColumn();
            if ($exists === false) {
                $pdo->exec($sql);
            }
        } catch (Throwable $e) {
            error_log('[migration affiliate_crypto_mp] index ' . $name . ': ' . $e->getMessage());
        }
    };

    $addColumn(
        $pdo,
        'affiliate_payouts',
        'megapayz_trx',
        '`megapayz_trx` VARCHAR(64) NULL AFTER `admin_notes`'
    );
    $addColumn(
        $pdo,
        'affiliate_payouts',
        'megapayz_transaction_id',
        '`megapayz_transaction_id` VARCHAR(120) NULL AFTER `megapayz_trx`'
    );

    $addIndex(
        $pdo,
        'affiliate_payouts',
        'uniq_aff_payout_mp_trx',
        'ALTER TABLE `affiliate_payouts` ADD UNIQUE KEY `uniq_aff_payout_mp_trx` (`megapayz_trx`)'
    );

    $addColumn(
        $pdo,
        'megapayz_transactions',
        'affiliate_payout_id',
        '`affiliate_payout_id` INT UNSIGNED NULL AFTER `user_id`'
    );
    $addIndex(
        $pdo,
        'megapayz_transactions',
        'idx_megapayz_affiliate_payout',
        'ALTER TABLE `megapayz_transactions` ADD INDEX `idx_megapayz_affiliate_payout` (`affiliate_payout_id`)'
    );
};
