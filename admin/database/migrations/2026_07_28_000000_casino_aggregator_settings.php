<?php

declare(strict_types=1);

/**
 * Game Control API v1.0.0 setting mirrors (admin copy).
 */
return static function (PDO $pdo): void {
    $rootService = dirname(__DIR__, 3) . '/services/CasinoAggregatorService.php';
    if (is_file($rootService)) {
        require_once $rootService;
    }
    if (class_exists('CasinoAggregatorService', false)) {
        CasinoAggregatorService::bootstrap($pdo);
    }
};
