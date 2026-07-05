<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

final class SecurityHardeningStaticTest extends TestCase
{
    public function testRootHtaccessBlocksPrivateRuntimePaths(): void
    {
        $contents = $this->readProjectFile('.htaccess');

        foreach (['app', 'config', 'database', 'docs', 'logs', 'services', 'storage', 'tests', 'vendor'] as $path) {
            $this->assertStringContainsString($path, $contents);
        }

        $this->assertStringContainsString('RewriteRule ^(?:app|bin|config|database|docs|logs|repositories|scripts|services|storage|tests|tools|vendor)(?:/|$) - [F,L]', $contents);
        $this->assertStringContainsString('RewriteRule (^|/)\\.(?!well-known(?:/|$)) - [F,L,NC]', $contents);
    }

    public function testPublicAdminApiDirectAccessIsBackendOnly(): void
    {
        $rootHtaccess = $this->readProjectFile('.htaccess');
        $router = $this->readProjectFile('router.php');

        $this->assertStringContainsString('RewriteRule ^admin/api/v2(?:/|$) - [F,L]', $rootHtaccess);
        $this->assertStringContainsString("str_starts_with(\$trimmedUri, '/admin/api/v2') && !\$isBackendHost", $router);
    }

    public function testProviderCallbackTransportGuardsArePresent(): void
    {
        $megaPayz = $this->readProjectFile('services/MegaPayzService.php');
        $casino = $this->readProjectFile('controllers/Api/ApiCasinoCallbackController.php');
        $bgaming = $this->readProjectFile('services/BgamingService.php');

        $this->assertStringContainsString('MEGAPAYZ_CALLBACK_ALLOWED_IPS', $megaPayz);
        $this->assertStringContainsString('MEGAPAYZ_CALLBACK_TOKEN', $megaPayz);
        $this->assertStringContainsString('CASINO_CALLBACK_ALLOWED_IPS', $casino);
        $this->assertStringContainsString('bgaming_token_rotation_nonces', $bgaming);
    }

    public function testProductionProviderSecretGuardsArePresent(): void
    {
        $config = $this->readProjectFile('config/app.php');
        $envExample = $this->readProjectFile('ENV.example');

        $this->assertStringContainsString("frontend_assert_active_provider_secret('BGaming'", $config);
        $this->assertStringContainsString("frontend_assert_active_provider_secret('MegaPayz'", $config);
        $this->assertStringContainsString('MEGAPAYZ_CALLBACK_TOKEN=', $envExample);
        $this->assertStringContainsString('CASINO_CALLBACK_ALLOWED_IPS=', $envExample);
    }

    private function readProjectFile(string $relativePath): string
    {
        $path = rtrim((string) BASE_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        $contents = file_get_contents($path);
        $this->assertIsString($contents, 'Unable to read ' . $relativePath);

        return $contents;
    }
}
