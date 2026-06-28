<?php
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;

class MemberContractTest extends TestCase
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

    public function testGetProfileRequiresAuth()
    {
        $res = $this->client()->get('/api/v2/me');
        $this->assertEquals(401, $res->getStatusCode(), 'Unauthenticated requests must return 401');
    }

    public function testProfileSchemaWhenAuthenticated()
    {
        $token = getenv('CONTRACT_TEST_JWT');
        if (!$token) {
            $this->markTestSkipped('No CONTRACT_TEST_JWT provided for authenticated contract checks');
        }

        $res = $this->client()->get('/api/v2/me', [
            'headers' => ['Authorization' => 'Bearer ' . $token]
        ]);

        $this->assertEquals(200, $res->getStatusCode(), 'Authenticated profile should return 200');
        $body = json_decode((string)$res->getBody(), true);
        $this->assertIsArray($body);
        $this->assertArrayHasKey('id', $body);
        $this->assertArrayHasKey('username', $body);
    }
}
