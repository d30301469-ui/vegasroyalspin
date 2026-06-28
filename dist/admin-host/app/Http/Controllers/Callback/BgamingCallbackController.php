<?php

declare(strict_types=1);

namespace App\Http\Controllers\Callback;

use App\Core\Controller;
use App\Core\Request;

final class BgamingCallbackController extends Controller
{
    public function __invoke(Request $request, array $params = []): void
    {
        $_GET['endpoint'] = trim((string) ($params['any'] ?? ''), '/');
        $this->legacyRequire('admin/api/v2/bgaming_callback.php');
    }
}

