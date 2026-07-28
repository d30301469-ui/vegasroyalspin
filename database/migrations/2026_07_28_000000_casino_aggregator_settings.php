<?php

declare(strict_types=1);

/**
 * Game Control API v1.0.0 setting mirrors — prefer CasinoAggregatorService::bootstrap().
 */
return static function (PDO $pdo): void {
    // Legacy tables without vendor_code are rebuilt by CasinoAggregatorService::ensureSettingsTables().
    if (is_file(dirname(__DIR__, 2) . '/services/CasinoAggregatorService.php')) {
        require_once dirname(__DIR__, 2) . '/services/CasinoAggregatorService.php';
    }
    if (class_exists('CasinoAggregatorService', false)) {
        CasinoAggregatorService::bootstrap($pdo);
    }
};
