<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;

final class PublicMemberApiController extends Controller
{
    public function __invoke(Request $request, array $params = []): void
    {
        $this->dispatchRoute((string) ($params['any'] ?? ''));
    }

    public function content(Request $request, array $params = []): void
    {
        $this->dispatchRoute('content/' . trim((string) ($params['any'] ?? ''), '/'));
    }

    private function dispatchRoute(string $route): void
    {
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', dirname(__DIR__, 3));
        }
        if (!defined('SERVICE_PATH')) {
            define('SERVICE_PATH', BASE_PATH . '/services');
        }

        require_once SERVICE_PATH . '/PublicApiV2Dispatcher.php';
        PublicApiV2Dispatcher::dispatch($route);
    }
}
