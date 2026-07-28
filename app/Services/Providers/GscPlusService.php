<?php

declare(strict_types=1);

namespace App\Services\Providers;

/**
 * PSR-4 wrapper — loads legacy global GscPlusService.
 */
final class GscPlusService
{
    public static function legacy(): string
    {
        $path = dirname(__DIR__, 3) . '/services/GscPlusService.php';
        if (!class_exists(\GscPlusService::class, false) && is_file($path)) {
            require_once $path;
        }
        return \GscPlusService::class;
    }
}
