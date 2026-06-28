<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Request;
use App\Core\Response;

final class AdminHostMiddleware
{
    public function __invoke(Request $request, callable $next): void
    {
        if (!$request->isAdminHost()) {
            Response::html('<h1>404 - Sayfa bulunamadı</h1>', 404);
            return;
        }

        $next($request);
    }
}

