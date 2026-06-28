<?php
use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;

class GamesContractTest extends TestCase
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

    public function testGamesListReturnsArray()
    {
        $res = $this->client()->get('/api/v2/games');
        $this->assertEquals(200, $res->getStatusCode());
        $body = json_decode((string)$res->getBody(), true);
        $this->assertIsArray($body);
    }

    public function testGameLaunchRequiresAuth()
    {
        $res = $this->client()->post('/api/v2/game-launch', ['json' => ['game_id' => 101]]);
        $this->assertTrue(in_array($res->getStatusCode(), [401, 403]));
    }
}
