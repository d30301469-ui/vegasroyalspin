<?php
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;

class ContentContractTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (getenv('RUN_CONTRACT_TESTS') !== '1') {
            self::markTestSkipped('Contract tests disabled. Set RUN_CONTRACT_TESTS=1 to enable.');
        }
    }

    protected function client(): Client
    {
        $base = getenv('CONTRACT_BASE_URL') ?: 'http://localhost:8000';
        return new Client(['base_uri' => $base, 'http_errors' => false]);
    }

    public function testSlidersReturnArray()
    {
        $res = $this->client()->get('/api/v2/sliders');
        $this->assertEquals(200, $res->getStatusCode());
        $body = json_decode((string)$res->getBody(), true);
        $this->assertIsArray($body);
        // Validate first item schema keys if present
        if (count($body) > 0) {
            $first = $body[0];
            $this->assertArrayHasKey('id', $first);
            $this->assertArrayHasKey('title', $first);
            $this->assertArrayHasKey('imageUrl', $first);
        }
    }

    public function testPromotionsReturnArray()
    {
        $res = $this->client()->get('/api/v2/promotions');
        $this->assertEquals(200, $res->getStatusCode());
        $body = json_decode((string)$res->getBody(), true);
        $this->assertIsArray($body);
        if (count($body) > 0) {
            $first = $body[0];
            $this->assertArrayHasKey('id', $first);
            $this->assertArrayHasKey('title', $first);
            $this->assertArrayHasKey('starts_at', $first);
        }
    }
}
