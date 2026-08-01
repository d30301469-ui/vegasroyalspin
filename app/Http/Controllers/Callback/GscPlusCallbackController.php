<?php

declare(strict_types=1);

namespace App\Http\Controllers\Callback;

use App\Core\Controller;
use App\Core\Request;

final class GscPlusCallbackController extends Controller
{
    public function __invoke(Request $request, array $params = []): void
    {
        if (isset($params['any']) && is_string($params['any']) && $params['any'] !== '') {
            $_GET['endpoint'] = trim($params['any'], '/');
        }
        $this->legacyRequire('admin/api/v2/gsc_plus_callback.php');
    }
}
