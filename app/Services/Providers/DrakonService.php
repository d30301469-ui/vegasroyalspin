<?php

declare(strict_types=1);

namespace App\Services\Providers;

final class DrakonService
{
    public static function legacy(): string
    {
        require_once BASE_PATH . '/services/DrakonService.php';
        return \DrakonService::class;
    }
}

