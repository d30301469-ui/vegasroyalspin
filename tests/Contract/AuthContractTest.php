<?php

declare(strict_types=1);

namespace Tests\Contract;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for Auth endpoints. These tests are skipped by default.
 * To run them set environment variable `RUN_CONTRACT_TESTS=1` and
 * `CONTRACT_BASE_URL` to your test server (e.g. http://localhost:8000).
 */
class AuthContractTest extends TestCase
{
    private Client $http;
    private string $baseUrl;

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('RUN_CONTRACT_TESTS') !== '1') {
            $this->markTestSkipped('Contract tests disabled. Set RUN_CONTRACT_TESTS=1 to enable.');
        }

        $this->baseUrl = getenv('CONTRACT_BASE_URL') ?: getenv('API_BASE_URL') ?: 'http://localhost:8000';

        if (!class_exists(Client::class)) {
            $this->markTestSkipped('Guzzle is not available. Run composer install.');
        }

        $this->http = new Client(['base_uri' => $this->baseUrl, 'http_errors' => false]);
    }

    public function testLoginReturnsExpectedShape(): void
    {
        $resp = $this->http->post('/api/v2/auth/login', [
            'json' => [
                'username' => 'contract@example.com',
                'password' => 'password123',
            ],
        ]);

        $this->assertIsInt($resp->getStatusCode());
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertIsArray($body);

        // Basic contract assertions (adjust to your API semantics)
        $this->assertArrayHasKey('success', $body);
        $this->assertArrayHasKey('code', $body);
        $this->assertArrayHasKey('data', $body);
    }
}
