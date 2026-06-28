<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;

final class LegacyAdminController extends Controller
{
    public function __invoke(Request $request, array $params = []): void
    {
        $this->legacyRequire('admin/index.php');
    }
}

