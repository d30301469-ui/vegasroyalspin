<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Core\Controller;
use App\Core\Request;

final class LegacyPublicController extends Controller
{
    public function __invoke(Request $request, array $params = []): void
    {
        $this->legacyRequire('core/bootstrap.php');
        $this->legacyRequire('core/legacy_dispatch.php');
    }
}

