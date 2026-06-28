<?php

declare(strict_types=1);

namespace App\Services\Payments;

final class MegaPayzService
{
    public static function legacy(): string
    {
        require_once BASE_PATH . '/services/MegaPayzService.php';
        return \MegaPayzService::class;
    }
}

