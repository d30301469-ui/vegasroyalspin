<?php

declare(strict_types=1);

namespace App\Http\Controllers\Callback;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

final class MegaPayzCallbackController extends Controller
{
    public function __invoke(Request $request, array $params = []): void
    {
        if ($request->method() !== 'POST') {
            Response::json(['status' => false, 'code' => 405, 'message' => 'METHOD_NOT_ALLOWED'], 405);
            return;
        }

        require_once BASE_PATH . '/services/MegaPayzService.php';
        $transport = \MegaPayzService::verifyCallbackTransport($_SERVER);
        if (empty($transport['valid'])) {
            Response::json([
                'status' => false,
                'code' => (int) ($transport['code'] ?? 403),
                'message' => (string) ($transport['error'] ?? 'FORBIDDEN'),
            ], (int) ($transport['code'] ?? 403));
            return;
        }

        $payload = json_decode($request->rawBody(), true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }

        Response::json(\MegaPayzService::handleUnifiedCallback(Database::pdo(), $payload), 200);
    }
}

