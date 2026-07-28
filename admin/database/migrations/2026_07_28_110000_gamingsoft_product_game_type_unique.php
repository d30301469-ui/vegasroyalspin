<?php

declare(strict_types=1);

/**
 * GSC+ can return multiple product rows per product_code (e.g. LIVE_CASINO vs LIVE_CASINO_PREMIUM).
 */
return static function (PDO $pdo): void {
    try {
        $indexes = $pdo->query("SHOW INDEX FROM gamingsoft_products WHERE Key_name = 'uniq_gs_product_code'")?->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($indexes !== []) {
            $pdo->exec('ALTER TABLE gamingsoft_products DROP INDEX uniq_gs_product_code');
        }
    } catch (Throwable) {
    }

    try {
        $indexes = $pdo->query("SHOW INDEX FROM gamingsoft_products WHERE Key_name = 'uniq_gs_product_code_type'")?->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($indexes === []) {
            $pdo->exec('ALTER TABLE gamingsoft_products ADD UNIQUE KEY uniq_gs_product_code_type (product_code, game_type)');
        }
    } catch (Throwable) {
    }
};
