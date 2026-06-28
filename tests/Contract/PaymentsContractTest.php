<?php

declare(strict_types=1);

namespace Tests\Contract;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for Payments endpoints. Skipped by default.
 */
class PaymentsContractTest extends TestCase
{
    private Client $http;

    protected function setUp(): void
    {
        parent::setUp();

        if (getenv('RUN_CONTRACT_TESTS') !== '1') {
            $this->markTestSkipped('Contract tests disabled.');
        }

        if (!class_exists(Client::class)) {
            $this->markTestSkipped('Guzzle not installed.');
        }

        $base = getenv('CONTRACT_BASE_URL') ?: getenv('API_BASE_URL') ?: 'http://localhost:8000';
        $this->http = new Client(['base_uri' => $base, 'http_errors' => false]);
    }

    public function testGetPaymentMethods(): void
    {
        $resp = $this->http->get('/api/v2/payment-methods');
        $this->assertEquals(200, $resp->getStatusCode());
        $body = json_decode((string) $resp->getBody(), true);
        $this->assertArrayHasKey('data', $body);
    }
}
