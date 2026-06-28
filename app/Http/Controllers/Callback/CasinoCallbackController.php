<?php

declare(strict_types=1);

namespace App\Http\Controllers\Callback;

use App\Core\Controller;
use App\Core\Request;

final class CasinoCallbackController extends Controller
{
    public function __invoke(Request $request, array $params = []): void
    {
        $this->legacyRequire('controllers/Api/ApiCasinoCallbackController.php');
        (new \ApiCasinoCallbackController())->index();
    }
}

