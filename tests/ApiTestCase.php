<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Base test case with common setup for API tests.
 */
abstract class ApiTestCase extends TestCase
{
    /**
     * Mock HTTP request with method and path.
     */
    protected function mockRequest(string $method = 'GET', string $path = '/', array $query = []): void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $path . (!empty($query) ? '?' . http_build_query($query) : '');
        $_SERVER['PATH_INFO'] = $path;
    }

    /**
     * Mock JSON request body.
     */
    protected function mockJsonBody(array $data): void
    {
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $GLOBALS['HTTP_RAW_POST_DATA'] = json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Assert JSON response structure for API success.
     */
    protected function assertJsonSuccessResponse(array $response, string $message = ''): void
    {
        $this->assertArrayHasKey('success', $response, 'Response missing "success" key. ' . $message);
        $this->assertTrue($response['success'], 'Response "success" is not true. ' . $message);
        $this->assertArrayHasKey('code', $response, 'Response missing "code" key. ' . $message);
        $this->assertArrayHasKey('data', $response, 'Response missing "data" key. ' . $message);
    }

    /**
     * Assert JSON response structure for API error.
     */
    protected function assertJsonErrorResponse(array $response, int $expectedCode = null, string $message = ''): void
    {
        $this->assertArrayHasKey('success', $response, 'Response missing "success" key. ' . $message);
        $this->assertFalse($response['success'], 'Response "success" is not false. ' . $message);
        $this->assertArrayHasKey('code', $response, 'Response missing "code" key. ' . $message);
        $this->assertArrayHasKey('message', $response, 'Response missing "message" key. ' . $message);

        if ($expectedCode !== null) {
            $this->assertEquals($expectedCode, $response['code'], 'Response code mismatch. ' . $message);
        }
    }

    /**
     * Generate mock JWT token.
     */
    protected function generateMockJwt(array $claims = []): string
    {
        $defaultClaims = [
            'iat' => time(),
            'exp' => time() + 3600,
            'user_id' => 1,
            'member_id' => 1,
        ];

        $claims = array_merge($defaultClaims, $claims);

        // Simple mock JWT (not production-safe)
        $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = base64_encode(json_encode($claims));
        $signature = base64_encode(hash_hmac('sha256', "$header.$payload", 'test-secret', true));

        return "$header.$payload.$signature";
    }

    /**
     * Set Authorization header with Bearer token.
     */
    protected function setAuthorizationHeader(string $token): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
    }

    /**
     * Setup method run before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Reset superglobals
        $_SERVER = array_merge($_SERVER, [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'localhost',
            'HTTPS' => '',
        ]);

        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
    }

    /**
     * Teardown method run after each test.
     */
    protected function tearDown(): void
    {
        // Cleanup
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            unset($_SERVER['HTTP_AUTHORIZATION']);
        }

        parent::tearDown();
    }
}
