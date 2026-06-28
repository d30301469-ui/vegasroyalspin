<?php

declare(strict_types=1);

namespace App\Services\Providers;

final class BgamingService
{
    public static function legacy(): string
    {
        require_once BASE_PATH . '/services/BgamingService.php';
        return \BgamingService::class;
    }
}

