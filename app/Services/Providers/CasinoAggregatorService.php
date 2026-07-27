<?php

declare(strict_types=1);

namespace App\Services\Providers;

final class CasinoAggregatorService
{
    public static function legacy(): string
    {
        require_once BASE_PATH . '/services/CasinoAggregatorService.php';
        return \CasinoAggregatorService::class;
    }
}
