<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Router;
use App\Http\Controllers\Admin\LegacyAdminController;
use App\Http\Controllers\Api\PublicMemberApiController;
use App\Http\Controllers\Site\LegacyPublicController;
use App\Http\Middleware\AdminHostMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;

return static function (Router $router): void {
    $security = [new SecurityHeadersMiddleware()];
    $backend = [new SecurityHeadersMiddleware(), new AdminHostMiddleware()];

    // Provider/payment callbacks live on backend host only (excluded from frontend bundle).
    if (class_exists(\App\Http\Controllers\Callback\BgamingCallbackController::class)) {
        $bgaming = new \App\Http\Controllers\Callback\BgamingCallbackController();
        $router->any('/api/v2/bgaming-wallet/{any}', [$bgaming, '__invoke'], $backend);
        $router->any('/api/v2/bgaming/{any}', [$bgaming, '__invoke'], $backend);
    }
    if (class_exists(\App\Http\Controllers\Callback\MegaPayzCallbackController::class)) {
        $megaPayzCallback = [new \App\Http\Controllers\Callback\MegaPayzCallbackController(), '__invoke'];
        $router->any('/api/v2/megapayz-callback', $megaPayzCallback, $backend);
        $router->any('/MegaPayz/deposit', $megaPayzCallback, $backend);
        $router->any('/megapayz/deposit', $megaPayzCallback, $backend);
    }
    if (class_exists(\App\Http\Controllers\Callback\CasinoCallbackController::class)) {
        $router->any('/api/v2/casino-callback', [new \App\Http\Controllers\Callback\CasinoCallbackController(), '__invoke'], $backend);
    }

    $router->any('/api/v2/{any}', [new PublicMemberApiController(), '__invoke'], $security);
    $router->any('/api/member/{any}', [new PublicMemberApiController(), '__invoke'], $security);
    $router->any('/api/content/{any}', [new PublicMemberApiController(), 'content'], $security);

    // Catch-all: Router::match() returns [] for '/{any}' on every path including '/',
    // so a separate '/' route would never be reached — one handler is enough.
    $router->any('/{any}', static function (Request $request): void {
        if ($request->isAdminHost()) {
            (new LegacyAdminController())($request);
            return;
        }

        (new LegacyPublicController())($request);
    }, $security);
};

