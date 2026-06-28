<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Services\Api\PublicMemberApiDispatcher;
use Tests\ApiTestCase;

/**
 * Auth and public API allowlist / dispatch standard tests (no live HTTP).
 */
final class AuthApiTest extends ApiTestCase
{
    /**
     * @return list<string>
     */
    private function authRoutes(): array
    {
        return [
            'auth/login',
            'auth/register',
            'auth/logout',
            'auth/session',
            'auth/refresh',
            'auth/forgot-password',
            'auth/password-reset',
            'auth/reset-password',
            'auth/email-verification',
            'auth/verify-email',
            'auth/verify-phone',
            'auth/2fa/enable',
            'auth/2fa/verify',
        ];
    }

    public function testAuthRoutesAreAllowlisted(): void
    {
        foreach ($this->authRoutes() as $route) {
            $this->assertTrue(
                PublicMemberApiDispatcher::isAllowed($route),
                "Expected auth route to be allowlisted: {$route}"
            );
        }
    }

    public function testProtectedMemberRoutesAreAllowlisted(): void
    {
        foreach (['me', 'balance', 'profile/detail', 'wallet/balance', 'deposit-history'] as $route) {
            $this->assertTrue(
                PublicMemberApiDispatcher::isAllowed($route),
                "Expected protected route to be allowlisted: {$route}"
            );
        }
    }

    public function testUnknownRouteIsNotAllowlisted(): void
    {
        $this->assertFalse(PublicMemberApiDispatcher::isAllowed('internal/admin/users'));
        $this->assertFalse(PublicMemberApiDispatcher::isAllowed('drakon/sync-games'));
    }

    public function testPublicApiUsesTransparentProxyOnApiOnlyHost(): void
    {
        $src = $this->readProjectFile('services/PublicApiV2Dispatcher.php');

        $this->assertStringContainsString('BackendMemberApiProxy::forward', $src);
        $this->assertStringContainsString('frontend_database_allowed', $src);
        $this->assertStringNotContainsString('tryDispatchApiOnlyAuth', $src);
    }

    public function testProxyForwardsSessionOrAuthorizationHeader(): void
    {
        $src = $this->readProjectFile('services/BackendMemberApiProxy.php');

        $this->assertStringContainsString("\$_SESSION['member_jwt']", $src);
        $this->assertStringContainsString('HTTP_AUTHORIZATION', $src);
    }

    public function testRouteModulesAreDefaultDispatchPath(): void
    {
        $src = $this->readProjectFile('app/Services/Api/PublicMemberApiDispatcher.php');

        $this->assertStringContainsString('member_local.php', $src);
        $this->assertStringNotContainsString('PublicMemberApiRuntime.php', $src);
        $this->assertFileExists(BASE_PATH . '/admin/api/v2/member_local.php');
        $this->assertFileExists(BASE_PATH . '/admin/api/v2/includes/member_route_loader.php');
    }

    public function testAuthHandlersExistInBackendRouteModules(): void
    {
        $authModule = $this->readProjectFile('admin/api/v2/routes/member_auth.php');

        foreach (['auth/login', 'auth/register', 'auth/session', 'auth/logout'] as $route) {
            $this->assertStringContainsString("'{$route}'", $authModule);
        }
    }

    private function readProjectFile(string $relativePath): string
    {
        $path = rtrim((string) BASE_PATH, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        $contents = file_get_contents($path);
        $this->assertIsString($contents, 'Unable to read ' . $relativePath);

        return $contents;
    }
}
