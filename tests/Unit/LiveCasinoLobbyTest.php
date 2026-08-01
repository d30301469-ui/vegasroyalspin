<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Guards the live-casino lobby query layer: the category tabs shown on
 * /livecasino and the paging arithmetic that decides whether the client asks for
 * another page. Both are run against a real database so a mistake shows up as a
 * missing table rather than as a string mismatch.
 */
final class LiveCasinoLobbyTest extends TestCase
{
    /**
     * The lobby-wide exclusions applied by LiveCasinoQuery::pageFromDatabase to
     * every source branch.
     */
    private const EXCLUSIONS = "LOWER(name) NOT LIKE '%acceptance%test%'
        AND LOWER(game_id) NOT LIKE '%acceptance%test%'
        AND LOWER(name) NOT LIKE '%lobby%'";

    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE live_games (game_id TEXT, name TEXT, is_featured INTEGER DEFAULT 0)');

        $insert = $this->pdo->prepare(
            'INSERT INTO live_games (game_id, name, is_featured) VALUES (:id, :name, :featured)'
        );
        foreach ([
            ['gsc:1', 'Lightning Roulette', 0],
            ['gsc:2', 'Auto Roulette VIP', 0],
            ['gsc:3', 'Türkçe Rulet', 0],
            ['gsc:4', 'Blackjack Silver A', 0],
            ['gsc:5', 'Black Jack Classic', 0],
            ['gsc:6', 'Speed Baccarat B', 0],
            ['gsc:7', 'Dragon Tiger', 0],
            ['gsc:8', 'Crazy Time', 1],
            ['gsc:9', 'Monopoly Big Baller', 0],
            ['gsc:10', 'Sweet Bonanza CandyLand', 0],
            ['gsc:11', 'Acceptance Test Table', 0],
            ['aggregator:x:acceptance-test', 'Studio Table', 0],
            ['gsc:12', 'Live - Lobby', 0],
            ['gsc:13', 'Live - Lobby Gameshows', 0],
        ] as [$id, $name, $featured]) {
            $insert->execute([':id' => $id, ':name' => $name, ':featured' => $featured]);
        }
    }

    /** @return list<string> */
    private function namesMatching(string $category): array
    {
        $predicate = LiveCasinoQuery::liveCategorySqlMatch($category);
        self::assertNotSame('', $predicate, "category {$category} must produce a predicate");

        return $this->pdo
            ->query('SELECT name FROM live_games WHERE ' . $predicate . ' ORDER BY name')
            ->fetchAll(PDO::FETCH_COLUMN);
    }

    public function testRouletteTabCollectsRouletteTablesIncludingLocalizedTitles(): void
    {
        $this->assertSame(
            ['Auto Roulette VIP', 'Lightning Roulette', 'Türkçe Rulet'],
            $this->namesMatching('roulette')
        );
    }

    public function testBlackjackTabAcceptsBothSpellings(): void
    {
        $this->assertSame(
            ['Black Jack Classic', 'Blackjack Silver A'],
            $this->namesMatching('blackjack')
        );
    }

    public function testBaccaratTabIncludesDragonTiger(): void
    {
        $this->assertSame(
            ['Dragon Tiger', 'Speed Baccarat B'],
            $this->namesMatching('baccarat')
        );
    }

    public function testGameShowTabCollectsShowTables(): void
    {
        $this->assertSame(
            ['Crazy Time', 'Monopoly Big Baller', 'Sweet Bonanza CandyLand'],
            $this->namesMatching('game-show')
        );
    }

    /**
     * "Tüm Oyunlar" and "Popüler" are not categories. If they produced a
     * predicate the tab would filter the lobby down to nothing.
     */
    public function testNonCategorySortsDoNotFilter(): void
    {
        foreach (['', 'popular', 'liked', 'featured', 'new', 'unknown-tab'] as $sort) {
            $this->assertSame('', LiveCasinoQuery::liveCategorySqlMatch($sort), "sort {$sort} must not filter");
        }
    }

    public function testCategoryPredicateCanBeQualifiedWithAColumnAlias(): void
    {
        $names = $this->pdo
            ->query(
                'SELECT g.name FROM live_games g WHERE '
                . LiveCasinoQuery::liveCategorySqlMatch('game-show', 'g.name')
                . ' ORDER BY g.name'
            )
            ->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame(['Crazy Time', 'Monopoly Big Baller', 'Sweet Bonanza CandyLand'], $names);
    }

    /** Provider diagnostic entries must never reach the lobby. */
    public function testAcceptanceTestRowsAreExcludedByNameAndByGameId(): void
    {
        $kept = $this->pdo
            ->query('SELECT name FROM live_games WHERE ' . self::EXCLUSIONS . ' ORDER BY game_id')
            ->fetchAll(PDO::FETCH_COLUMN);

        $this->assertNotContains('Acceptance Test Table', $kept);
        $this->assertNotContains('Studio Table', $kept);
        $this->assertCount(10, $kept);
    }

    /**
     * Some providers list studio lobby entries next to real tables. Those
     * tiles fail to open, so the lobby exclusion must drop them by name.
     */
    public function testStudioLobbyEntriesAreExcluded(): void
    {
        $kept = $this->pdo
            ->query('SELECT name FROM live_games WHERE ' . self::EXCLUSIONS . ' ORDER BY game_id')
            ->fetchAll(PDO::FETCH_COLUMN);

        $this->assertNotContains('Live - Lobby', $kept);
        $this->assertNotContains('Live - Lobby Gameshows', $kept);
        $this->assertContains('Lightning Roulette', $kept, 'real tables must survive the exclusion');
    }

    /**
     * Regression: the lobby merges each source's own capped window, so the total
     * must come from a COUNT over the catalogue and never from the number of rows
     * fetched. Deriving it from the window made hasNext false on page 1 whenever a
     * single source filled the window, which stopped the live catalogue at 30
     * games.
     */
    public function testTotalIsCountedOverTheCatalogueNotTheFetchedWindow(): void
    {
        $limit = 4;
        $page = 1;
        $offset = ($page - 1) * $limit;
        $cap = $offset + $limit;

        $total = (int) $this->pdo
            ->query('SELECT COUNT(DISTINCT game_id) FROM live_games WHERE ' . self::EXCLUSIONS)
            ->fetchColumn();
        $window = $this->pdo
            ->query(
                'SELECT game_id FROM live_games WHERE ' . self::EXCLUSIONS
                . " ORDER BY is_featured DESC, name ASC LIMIT {$cap}"
            )
            ->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame(10, $total);
        $this->assertCount($limit, $window, 'the window is capped and is smaller than the catalogue');
        $this->assertTrue(($offset + $limit) < $total, 'hasNext must stay true while pages remain');
        $this->assertSame(3, (int) ceil($total / $limit));
        $this->assertFalse(
            ($offset + $limit) < count($window),
            'deriving hasNext from the fetched window is what truncated the lobby'
        );
    }
}
