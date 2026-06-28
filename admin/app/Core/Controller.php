<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function legacyRequire(string $path): void
    {
        require BASE_PATH . '/' . ltrim($path, '/');
    }
}

