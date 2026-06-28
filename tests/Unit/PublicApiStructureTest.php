<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PublicApiStructureTest extends TestCase
{
    public function testRouteModuleCoverageScriptPasses(): void
    {
        $script = BASE_PATH . '/scripts/verify-route-module-coverage.php';
        $this->assertFileExists($script);

        $output = [];
        $code = 0;
        exec(PHP_BINARY . ' ' . escapeshellarg($script) . ' 2>&1', $output, $code);

        $this->assertSame(0, $code, implode("\n", $output));
        $this->assertStringContainsString('Route module coverage OK', implode("\n", $output));
    }
}
