<?php

/**
 * Ana sayfa vitrin alanları.
 *
 * Admin panel kayıtları DB'de JSON payload olarak tutar; frontend aynı kaynağı
 * server-side render ve /api/v2/content/homepage-sections endpointi için kullanır.
 */
final class ApiHomepageSections
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function fetch(array $query = []): array
    {
        $surface = self::normalizeSurface((string) ($query['surface'] ?? 'all'));
        $sectionKey = trim((string) ($query['section_key'] ?? ''));

        if (!ApiCmsRemote::canUseLocalDatabase()) {
            $remoteQuery = ['surface' => $surface];
            if ($sectionKey !== '') {
                $remoteQuery['section_key'] = $sectionKey;
            }
            $cacheKey = ApiCmsRemote::cacheKey('homepage_sections', $remoteQuery);
            $remote = null;
            if (!(function_exists('should_skip_remote_backend') && should_skip_remote_backend())) {
                $remote = ApiCmsRemote::getMain(['/content/homepage-sections', '/homepage_sections.php'], $remoteQuery);
                if ($remote !== null) {
                    ApiCmsRemote::writePayloadCache($cacheKey, $remote, $remoteQuery);
                    ApiCmsRemote::recordFetch($cacheKey, 'remote');
                }
            }
            if ($remote === null) {
                $remote = ApiCmsRemote::readPayloadCache($cacheKey, ApiCmsRemote::cacheStaleMaxAge(), true);
                if ($remote !== null) {
                    ApiCmsRemote::recordFetch($cacheKey, 'stale');
                }
            }
            if ($remote !== null) {
                $sections = is_array($remote['sections'] ?? null) ? $remote['sections'] : [];

                return self::mapRows($sections);
            }

            ApiCmsRemote::recordFetch($cacheKey, 'default');

            return self::defaultSections($surface, $sectionKey);
        }

        try {
            $pdo = self::pdo();

            $today = date('Y-m-d H:i:s');
            $where = [
                'is_active = 1',
                "(surface = :surface OR surface = 'all')",
                '(start_date IS NULL OR start_date <= :today_start)',
                '(end_date IS NULL OR end_date >= :today_end)',
            ];
            $params = [
                'surface' => $surface,
                'today_start' => $today,
                'today_end' => $today,
            ];

            if ($sectionKey !== '') {
                $where[] = 'section_key = :section_key';
                $params['section_key'] = $sectionKey;
            }

            $sql = 'SELECT id, section_key, title, type, surface, payload, sort_order, is_active, start_date, end_date, updated_at
                    FROM homepage_sections
                    WHERE ' . implode(' AND ', $where) . '
                    ORDER BY sort_order ASC, id ASC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return self::mapRows(is_array($rows) ? $rows : []);
        } catch (Throwable) {
            return self::defaultSections($surface, $sectionKey);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function fetchForSurface(string $surface = 'all'): array
    {
        return self::fetch(['surface' => $surface]);
    }

    public static function findSection(string $sectionKey, string $surface = 'all'): ?array
    {
        $sections = self::fetch([
            'surface' => $surface,
            'section_key' => $sectionKey,
        ]);

        return $sections[0] ?? null;
    }

    public static function ensureStorage(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

        $pdo = self::pdo();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS homepage_sections (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                section_key VARCHAR(80) NOT NULL,
                title VARCHAR(255) NULL,
                type ENUM('games','banner') NOT NULL DEFAULT 'games',
                surface ENUM('all','desktop','mobile') NOT NULL DEFAULT 'all',
                payload JSON NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                start_date DATETIME NULL,
                end_date DATETIME NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_homepage_sections_key_surface (section_key, surface),
                KEY idx_homepage_sections_lookup (section_key, surface, is_active, sort_order),
                KEY idx_homepage_sections_dates (start_date, end_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $count = (int) $pdo->query('SELECT COUNT(*) FROM homepage_sections')->fetchColumn();
        if ($count === 0) {
            foreach (self::defaultSections('all') as $section) {
                $payload = json_encode($section['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (!is_string($payload)) {
                    continue;
                }
                $stmt = $pdo->prepare(
                    'INSERT INTO homepage_sections
                        (section_key, title, type, surface, payload, sort_order, is_active)
                     VALUES
                        (:section_key, :title, :type, :surface, :payload, :sort_order, :is_active)'
                );
                $stmt->execute([
                    'section_key' => $section['section_key'],
                    'title' => $section['title'],
                    'type' => $section['type'],
                    'surface' => $section['surface'],
                    'payload' => $payload,
                    'sort_order' => $section['sort_order'],
                    'is_active' => $section['is_active'] ? 1 : 0,
                ]);
            }
        }

        $ready = true;
    }

    public static function normalizeSection(array $section, bool $onlyActiveItems = true): array
    {
        $normalized = self::sectionForAdminEdit($section, $onlyActiveItems);
        $payload = $normalized['payload'];
        if ($normalized['type'] === 'banner') {
            $payload = self::normalizeBannerPayload(is_array($payload) ? $payload : []);
        } elseif ($normalized['type'] === 'games' && is_array($payload)) {
            $payload = self::hydrateGamesPayloadWithCatalog(
                $payload,
                (string) ($normalized['section_key'] ?? '')
            );
        }
        $normalized['payload'] = ApiMediaUrl::resolveHomepagePayload($payload);

        return $normalized;
    }

    /**
     * Admin düzenleme formu için ham DB kaydı — URL çözümleme ve banner fallback yok.
     *
     * @param array<string, mixed> $section
     * @return array<string, mixed>
     */
    public static function sectionForAdminEdit(array $section, bool $onlyActiveItems = false): array
    {
        $type = (string) ($section['type'] ?? 'games');
        $type = in_array($type, ['games', 'banner'], true) ? $type : 'games';
        $surface = self::normalizeSurface((string) ($section['surface'] ?? 'all'));
        $payload = is_array($section['payload'] ?? null)
            ? $section['payload']
            : self::decodePayload($section['payload'] ?? null);

        if ($type === 'banner') {
            $payload = [
                'image_url' => trim((string) ($payload['image_url'] ?? '')),
                'alt' => trim((string) ($payload['alt'] ?? '')),
                'href' => trim((string) ($payload['href'] ?? '')),
                'onclick' => trim((string) ($payload['onclick'] ?? '')),
            ];
        } else {
            $payload = self::normalizeGamesPayload($payload, $onlyActiveItems);
        }

        return [
            'id' => (int) ($section['id'] ?? 0),
            'section_key' => trim((string) ($section['section_key'] ?? '')),
            'title' => trim((string) ($section['title'] ?? '')),
            'type' => $type,
            'surface' => $surface,
            'payload' => $payload,
            'sort_order' => (int) ($section['sort_order'] ?? 0),
            'is_active' => self::toBool($section['is_active'] ?? true),
            'start_date' => self::nullableString($section['start_date'] ?? null),
            'end_date' => self::nullableString($section['end_date'] ?? null),
            'updated_at' => self::nullableString($section['updated_at'] ?? null),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function defaultSections(string $surface = 'all', string $sectionKey = '', bool $forAdmin = false): array
    {
        $sections = [
            [
                'id' => 0,
                'section_key' => 'withdrawal-banner',
                'title' => 'Çekim Banner',
                'type' => 'banner',
                'surface' => 'all',
                'payload' => [
                    'image_url' => 'assets/images/artikcekimlerdelimitleretakilmayok.webp',
                    'alt' => 'Çekimlerinizde limitlere takılmak yok',
                    'href' => '',
                    'onclick' => '',
                ],
                'sort_order' => 10,
                'is_active' => true,
                'start_date' => null,
                'end_date' => null,
                'updated_at' => null,
            ],
            [
                'id' => 0,
                'section_key' => 'casino',
                'title' => 'Casino',
                'type' => 'games',
                'surface' => 'all',
                'payload' => [
                    'href' => '/slot',
                    'items' => [
                        ['game_id' => 23846, 'title' => 'Sweet Bonanza 1000', 'image_url' => 'assets/games-img/sweet-bonanza-1000.svg', 'alt' => 'Sweet Bonanza 1000', 'size' => 'featured', 'link' => '', 'sort_order' => 10, 'is_active' => true],
                        ['game_id' => 20072, 'title' => '40 Super Hot', 'image_url' => 'assets/games-img/40-super-hot.gif', 'alt' => '40 Super Hot', 'size' => 'normal', 'link' => '', 'sort_order' => 20, 'is_active' => true],
                        ['game_id' => 6793, 'title' => 'Extra Chilli', 'image_url' => 'assets/games-img/game-img3.jpg', 'alt' => 'Extra Chilli', 'size' => 'normal', 'link' => '', 'sort_order' => 30, 'is_active' => true],
                        ['game_id' => 27016, 'title' => 'Big Buffalo', 'image_url' => 'assets/games-img/game-img4.svg', 'alt' => 'Big Buffalo', 'size' => 'normal', 'link' => '', 'sort_order' => 40, 'is_active' => true],
                        ['game_id' => 27616, 'title' => "Gonzo's Quest™", 'image_url' => 'assets/games-img/game-img5.svg', 'alt' => "Gonzo's Quest™", 'size' => 'normal', 'link' => '', 'sort_order' => 50, 'is_active' => true],
                        ['game_id' => 23975, 'title' => 'Wolf Gold™', 'image_url' => 'assets/games-img/game-img6.jpeg', 'alt' => 'Wolf Gold', 'size' => 'normal', 'link' => '', 'sort_order' => 60, 'is_active' => true],
                        ['game_id' => 15234, 'title' => 'Vikings Go Berzerk', 'image_url' => 'assets/games-img/game-img7.jpeg', 'alt' => 'Vikings Go Berzerk', 'size' => 'normal', 'link' => '', 'sort_order' => 70, 'is_active' => true],
                        ['game_id' => 1008, 'title' => 'Legacy of Dead', 'image_url' => 'assets/games-img/40-super-hot.gif', 'alt' => 'Legacy of Dead', 'size' => 'normal', 'link' => '', 'sort_order' => 80, 'is_active' => true],
                        ['game_id' => 1009, 'title' => "Finn's Golden Tavern™", 'image_url' => 'assets/games-img/game-img9.svg', 'alt' => "Finn's Golden Tavern", 'size' => 'normal', 'link' => '', 'sort_order' => 90, 'is_active' => true],
                    ],
                ],
                'sort_order' => 20,
                'is_active' => true,
                'start_date' => null,
                'end_date' => null,
                'updated_at' => null,
            ],
            [
                'id' => 0,
                'section_key' => 'live-casino',
                'title' => 'Canlı Casino',
                'type' => 'games',
                'surface' => 'all',
                'payload' => [
                    'href' => '/livecasino',
                    'items' => [
                        // Live-casino tiles launch via Casino Aggregator (`aggregator:`).
                        // Empty game_id is resolved from the aggregator live catalogue by title.
                        ['game_id' => '', 'title' => 'Treasure Island', 'image_url' => 'assets/games-img/30cdd01c09eb84e33c798041023d2856_casinoGameIcon3.svg', 'alt' => 'Treasure Island', 'size' => 'featured', 'link' => '', 'sort_order' => 10, 'is_active' => true],
                        ['game_id' => '', 'title' => 'Dream Catcher', 'image_url' => 'assets/games-img/game-img4.svg', 'alt' => 'Dream Catcher', 'size' => 'normal', 'link' => '', 'sort_order' => 20, 'is_active' => true],
                        ['game_id' => '', 'title' => 'Lightning Roulette', 'image_url' => 'assets/games-img/game-img5.svg', 'alt' => 'Lightning Roulette', 'size' => 'normal', 'link' => '', 'sort_order' => 30, 'is_active' => true],
                        ['game_id' => '', 'title' => 'First Person Lightning Roulette', 'image_url' => 'assets/games-img/game2-img4.jpg', 'alt' => 'First Person Lightning Roulette', 'size' => 'normal', 'link' => '', 'sort_order' => 40, 'is_active' => true],
                        ['game_id' => '', 'title' => 'Spin a Win', 'image_url' => 'assets/games-img/game2-img5.jpeg', 'alt' => 'Spin a Win', 'size' => 'normal', 'link' => '', 'sort_order' => 50, 'is_active' => true],
                        ['game_id' => '', 'title' => 'Blackjack', 'image_url' => 'assets/games-img/game2-img6.jpg', 'alt' => 'Blackjack', 'size' => 'normal', 'link' => '', 'sort_order' => 60, 'is_active' => true],
                        ['game_id' => '', 'title' => 'Quantum Roulette', 'image_url' => 'assets/games-img/game2-img7.gif', 'alt' => 'Quantum Roulette', 'size' => 'normal', 'link' => '', 'sort_order' => 70, 'is_active' => true],
                        ['game_id' => '', 'title' => 'Super Six Baccarat', 'image_url' => 'assets/games-img/game2-img8.jpg', 'alt' => 'Super Six Baccarat', 'size' => 'normal', 'link' => '', 'sort_order' => 80, 'is_active' => true],
                        ['game_id' => '', 'title' => 'Roulette Live', 'image_url' => 'assets/games-img/game2-img9.jpg', 'alt' => 'Roulette Live', 'size' => 'normal', 'link' => '', 'sort_order' => 90, 'is_active' => true],
                    ],
                ],
                'sort_order' => 30,
                'is_active' => true,
                'start_date' => null,
                'end_date' => null,
                'updated_at' => null,
            ],
        ];

        $surface = self::normalizeSurface($surface);
        $sectionKey = trim($sectionKey);
        $filtered = [];
        foreach ($sections as $section) {
            if ($sectionKey !== '' && $section['section_key'] !== $sectionKey) {
                continue;
            }
            if ($surface !== 'all' && !in_array($section['surface'], ['all', $surface], true)) {
                continue;
            }
            $filtered[] = $forAdmin
                ? self::sectionForAdminEdit($section, false)
                : self::normalizeSection($section);
        }

        return $filtered;
    }

    private static function pdo(): PDO
    {
        return ApiCmsRemote::pdo();
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function mapRows(array $rows): array
    {
        $sections = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $row['payload'] = self::decodePayload($row['payload'] ?? null);
            $section = self::normalizeSection($row);
            if ($section['section_key'] !== '') {
                $sections[] = $section;
            }
        }

        return $sections;
    }

    /**
     * JSON sütunu / API yanıtı: payload string veya zaten decode edilmiş dizi olabilir.
     *
     * @return array<string, mixed>
     */
    private static function decodePayload(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value)) {
            return [];
        }

        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function normalizeSurface(string $surface): string
    {
        $surface = trim($surface);
        return in_array($surface, ['all', 'desktop', 'mobile'], true) ? $surface : 'all';
    }

    private static function normalizeGamesPayload(array $payload, bool $onlyActiveItems): array
    {
        $items = [];
        foreach ((array) ($payload['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $title = trim((string) ($item['title'] ?? ''));
            $image = trim((string) ($item['image_url'] ?? ''));
            if ($title === '' || $image === '') {
                continue;
            }
            $size = (string) ($item['size'] ?? 'normal');
            $imageFit = (string) ($item['image_fit'] ?? 'fill');
            $imageFit = in_array($imageFit, ['cover', 'fill'], true) ? $imageFit : 'fill';
            $imageScale = (int) ($item['image_scale'] ?? 100);
            $items[] = [
                'game_id' => self::normalizeGameIdValue($item['game_id'] ?? ''),
                'title' => $title,
                'image_url' => $image,
                'alt' => trim((string) ($item['alt'] ?? $title)),
                'size' => $size === 'featured' ? 'featured' : 'normal',
                'image_fit' => $imageFit,
                'image_scale' => max(40, min(120, $imageScale)),
                'link' => trim((string) ($item['link'] ?? '')),
                'sort_order' => (int) ($item['sort_order'] ?? 0),
                'is_active' => self::toBool($item['is_active'] ?? true),
            ];
        }
        usort($items, static fn (array $a, array $b): int => ((int) $a['sort_order']) <=> ((int) $b['sort_order']));

        return [
            'href' => trim((string) ($payload['href'] ?? '')),
            'items' => $onlyActiveItems
                ? array_values(array_filter($items, static fn (array $item): bool => (bool) $item['is_active']))
                : $items,
        ];
    }

    /**
     * Homepage casino tiles must launch via aggregator/bgaming/gsc string IDs
     * (e.g. aggregator:slot-pragmatic:vs20olympx or gsc:1006:vs20olympx), not SoftSwiss integers.
     *
     * IMPORTANT: never cast to int — (int)"vs20fruitswx" === 0 in PHP.
     */
    public static function normalizeGameIdValue(mixed $value): string
    {
        if (is_bool($value)) {
            return '';
        }
        if (is_int($value) || is_float($value)) {
            $asInt = (int) $value;
            return $asInt > 0 ? (string) $asInt : '';
        }

        $id = trim((string) $value);
        if ($id === '' || $id === '0') {
            return '';
        }

        return $id;
    }

    /**
     * Resolve curated CMS cards to playable catalog IDs.
     * Live-casino section → Casino Aggregator live only.
     * Casino (slots) section → aggregator/bgaming slots.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function hydrateGamesPayloadWithCatalog(array $payload, string $sectionKey = ''): array
    {
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        if ($items === []) {
            return $payload;
        }

        try {
            $pdo = self::pdo();
        } catch (Throwable) {
            return $payload;
        }

        $useAggregatorLive = self::isLiveCasinoSectionKey($sectionKey);

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $gameId = self::normalizeGameIdValue($item['game_id'] ?? '');
            $title = trim((string) ($item['title'] ?? ''));
            $resolved = $useAggregatorLive
                ? self::resolveAggregatorLiveGameId($pdo, $gameId, $title)
                : self::resolveCatalogGameId($pdo, $gameId, $title);
            if ($resolved !== '') {
                $items[$index]['game_id'] = $resolved;
            } elseif ($useAggregatorLive) {
                // Drop stale GSC+/numeric IDs so tiles never launch outside aggregator.
                $items[$index]['game_id'] = '';
            }

            $image = trim((string) ($item['image_url'] ?? ''));
            $playId = self::normalizeGameIdValue($items[$index]['game_id'] ?? '');
            if ($image === '' || self::isFragileHomepageImageUrl($image)) {
                $catalogImage = $playId !== '' ? self::lookupCatalogImageUrl($pdo, $playId) : '';
                if ($catalogImage === '' && $title !== '') {
                    $catalogImage = self::lookupCatalogImageUrlByTitle($pdo, $title, $useAggregatorLive);
                }
                if ($catalogImage !== '') {
                    $items[$index]['image_url'] = $catalogImage;
                } elseif (self::isFragileHomepageImageUrl($image)) {
                    $local = self::localGamesImgFallbackFromFragileUrl($image);
                    if ($local !== '') {
                        $items[$index]['image_url'] = $local;
                    }
                }
            }
        }

        $payload['items'] = $items;

        return $payload;
    }

    /**
     * CMS sometimes stores ImageKit / third-party thumbs that were never uploaded (404).
     */
    private static function isFragileHomepageImageUrl(string $url): bool
    {
        $lower = strtolower($url);
        if ($lower === '') {
            return false;
        }
        if (str_contains($lower, 'home-sections/games-img')) {
            return true;
        }
        if (str_contains($lower, 'gator.drakon.casino')) {
            return true;
        }
        if (str_contains($lower, 'drakon.casino/storage/')) {
            return true;
        }

        return false;
    }

    private static function localGamesImgFallbackFromFragileUrl(string $url): string
    {
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: $url);
        $base = strtolower(pathinfo($path, PATHINFO_FILENAME));
        if ($base === '') {
            return '';
        }

        $map = [
            'gonzos-quest-megaways' => 'assets/games-img/game-img5.svg',
            'gonzos-quest' => 'assets/games-img/game-img5.svg',
            'wolf-gold' => 'assets/games-img/game-img6.jpeg',
            'gates-of-olympus-1000' => 'assets/games-img/sweet-bonanza-1000.svg',
            'sweet-bonanza-1000' => 'assets/games-img/sweet-bonanza-1000.svg',
            '40-super-hot' => 'assets/games-img/40-super-hot.gif',
        ];
        if (isset($map[$base])) {
            return $map[$base];
        }

        return '';
    }

    private static function lookupCatalogImageUrl(PDO $pdo, string $gameId): string
    {
        $idLower = strtolower($gameId);

        if (str_starts_with($idLower, 'aggregator:')) {
            $rest = substr($gameId, strlen('aggregator:'));
            $parts = explode(':', $rest, 2);
            if (count($parts) === 2) {
                try {
                    $stmt = $pdo->prepare(
                        'SELECT image_url FROM casino_aggregator_games
                         WHERE vendor_code = :v AND game_code = :g AND is_active = 1
                         LIMIT 1'
                    );
                    $stmt->execute([':v' => $parts[0], ':g' => $parts[1]]);
                    $url = trim((string) $stmt->fetchColumn());
                    if ($url !== '' && !self::isFragileHomepageImageUrl($url)) {
                        return $url;
                    }
                } catch (Throwable) {
                }
            }
        }

        if (str_starts_with($idLower, 'bgaming:')) {
            $ident = substr($gameId, strlen('bgaming:'));
            try {
                $stmt = $pdo->prepare(
                    'SELECT thumbnail_url FROM bgaming_games WHERE identifier = :id LIMIT 1'
                );
                $stmt->execute([':id' => $ident]);
                $url = trim((string) $stmt->fetchColumn());
                if ($url !== '' && !self::isFragileHomepageImageUrl($url)) {
                    return $url;
                }
            } catch (Throwable) {
            }
        }

        if (str_starts_with($idLower, 'gsc:')) {
            $rest = substr($gameId, strlen('gsc:'));
            $parts = explode(':', $rest, 2);
            if (count($parts) === 2) {
                try {
                    $stmt = $pdo->prepare(
                        'SELECT image_url FROM gsc_games
                         WHERE product_code = :p AND game_code = :g AND is_active = 1
                         LIMIT 1'
                    );
                    $stmt->execute([':p' => $parts[0], ':g' => $parts[1]]);
                    $url = trim((string) $stmt->fetchColumn());
                    if ($url !== '' && !self::isFragileHomepageImageUrl($url)) {
                        return $url;
                    }
                } catch (Throwable) {
                }
            }
        }

        return '';
    }

    private static function lookupCatalogImageUrlByTitle(PDO $pdo, string $title, bool $liveOnly): string
    {
        $title = trim($title);
        if ($title === '') {
            return '';
        }
        try {
            if ($liveOnly) {
                $liveFilter = self::aggregatorLiveSqlFilter('vendor_code', 'game_type');
                $stmt = $pdo->prepare(
                    "SELECT image_url FROM casino_aggregator_games
                     WHERE is_active = 1 AND {$liveFilter}
                       AND LOWER(game_name) LIKE LOWER(:title)
                     ORDER BY CHAR_LENGTH(game_name) ASC, id ASC LIMIT 1"
                );
            } else {
                $stmt = $pdo->prepare(
                    'SELECT image_url FROM casino_aggregator_games
                     WHERE is_active = 1 AND LOWER(game_name) LIKE LOWER(:title)
                     ORDER BY CHAR_LENGTH(game_name) ASC, id ASC LIMIT 1'
                );
            }
            $stmt->execute([':title' => $title . '%']);
            $url = trim((string) $stmt->fetchColumn());
            if ($url !== '' && !self::isFragileHomepageImageUrl($url)) {
                return $url;
            }
        } catch (Throwable) {
        }

        return '';
    }

    public static function isLiveCasinoSectionKey(string $sectionKey): bool
    {
        $key = strtolower(trim($sectionKey));

        return in_array($key, ['live-casino', 'live_casino', 'livecasino', 'live'], true);
    }

    private static function ensureCasinoAggregatorServiceLoaded(): void
    {
        if (class_exists('CasinoAggregatorService', false)) {
            return;
        }
        foreach ([
            dirname(__DIR__) . '/services/CasinoAggregatorService.php',
            shared_project_root() . '/admin/services/CasinoAggregatorService.php',
        ] as $path) {
            if (is_file($path)) {
                require_once $path;
                return;
            }
        }
    }

    /**
     * SQL filter: Casino Aggregator live tables only (excludes slot-only vendors).
     */
    private static function aggregatorLiveSqlFilter(string $vendorColumn = 'vendor_code', string $gameTypeColumn = 'game_type'): string
    {
        self::ensureCasinoAggregatorServiceLoaded();
        if (!class_exists('CasinoAggregatorService', false)) {
            return "{$gameTypeColumn} = 2";
        }

        $liveMatch = CasinoAggregatorService::liveVendorSqlMatch($vendorColumn);
        $slotOnly = CasinoAggregatorService::slotOnlyVendorSqlMatch($vendorColumn);

        return "({$gameTypeColumn} = 2 OR {$liveMatch}) AND NOT ({$slotOnly})";
    }

    /**
     * Title fallbacks when curated homepage names only exist on inactive vendors
     * (e.g. Evolution) — map to playable Casino Aggregator live titles.
     *
     * @return list<string>
     */
    private static function liveTitleSearchCandidates(string $title): array
    {
        $titleNorm = preg_replace('/[™®©]+/u', '', $title) ?? $title;
        $titleNorm = trim(preg_replace('/\s+/u', ' ', $titleNorm) ?? $titleNorm);
        if ($titleNorm === '') {
            return [];
        }

        $candidates = [$titleNorm];
        if (preg_match('/^(.+?)\s+live$/iu', $titleNorm, $m)) {
            $candidates[] = trim((string) $m[1]);
        }

        $key = strtolower($titleNorm);
        $aliases = [
            'monopoly roulette' => ['Mega Roulette', 'Fortune Roulette'],
            'monopoly roulette live' => ['Mega Roulette', 'Fortune Roulette'],
            'dream catcher' => ['Mega Wheel', 'Gravity Wheel'],
            'xxxtreme lightning roulette' => ['PowerUP Roulette', 'Mega Roulette', 'Auto Mega Roulette'],
            'xxxtreme lightning roulette live' => ['PowerUP Roulette', 'Mega Roulette'],
            'lightning roulette' => ['PowerUP Roulette', 'Mega Roulette'],
            'first person lightning roulette' => ['PowerUP Roulette', 'Auto Mega Roulette'],
            'vip live roulette' => ['VIP Roulette', 'Vip Roulette', 'VIP Roulette - The Club'],
            'live vip roulette' => ['VIP Roulette', 'Vip Roulette', 'VIP Roulette - The Club'],
            'vip roulette' => ['VIP Roulette', 'Vip Roulette', 'VIP Roulette - The Club'],
        ];
        if (isset($aliases[$key])) {
            foreach ($aliases[$key] as $alias) {
                $candidates[] = $alias;
            }
        }

        return array_values(array_unique(array_filter($candidates, static fn (string $v): bool => $v !== '')));
    }

    /**
     * Resolve a live-casino homepage tile to an aggregator launch ID
     * (`aggregator:{vendor}:{code}`). Stale gsc:/drakon: IDs are remapped.
     */
    private static function resolveAggregatorLiveGameId(PDO $pdo, string $gameId, string $title): string
    {
        $liveFilter = self::aggregatorLiveSqlFilter('vendor_code', 'game_type');
        $active = 'is_active = 1';
        $buildId = static function (string $vendor, string $code): string {
            return 'aggregator:' . $vendor . ':' . $code;
        };

        $lookupByGameCode = static function (string $code) use ($pdo, $liveFilter, $active, $buildId): string {
            $code = trim($code);
            if ($code === '') {
                return '';
            }
            try {
                $stmt = $pdo->prepare(
                    "SELECT vendor_code, game_code FROM casino_aggregator_games
                     WHERE {$active} AND {$liveFilter} AND game_code = :g
                     ORDER BY id ASC LIMIT 1"
                );
                $stmt->execute([':g' => $code]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($row)
                    && trim((string) ($row['vendor_code'] ?? '')) !== ''
                    && trim((string) ($row['game_code'] ?? '')) !== ''
                ) {
                    return $buildId((string) $row['vendor_code'], (string) $row['game_code']);
                }
            } catch (Throwable) {
            }

            return '';
        };

        $lookupVendorCode = static function (string $vendor, string $code) use ($pdo, $liveFilter, $active, $buildId): string {
            $vendor = trim($vendor);
            $code = trim($code);
            if ($vendor === '' || $code === '') {
                return '';
            }
            try {
                $stmt = $pdo->prepare(
                    "SELECT vendor_code, game_code FROM casino_aggregator_games
                     WHERE {$active} AND {$liveFilter}
                       AND vendor_code = :v AND game_code = :g
                     LIMIT 1"
                );
                $stmt->execute([':v' => $vendor, ':g' => $code]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($row)
                    && trim((string) ($row['vendor_code'] ?? '')) !== ''
                    && trim((string) ($row['game_code'] ?? '')) !== ''
                ) {
                    return $buildId((string) $row['vendor_code'], (string) $row['game_code']);
                }
            } catch (Throwable) {
            }

            return '';
        };

        $idLower = strtolower($gameId);
        if ($gameId !== '' && str_starts_with($idLower, 'aggregator:')) {
            $rest = substr($gameId, strlen('aggregator:'));
            $parts = explode(':', $rest, 2);
            if (count($parts) === 2) {
                $found = $lookupVendorCode($parts[0], $parts[1]);
                if ($found !== '') {
                    return $found;
                }
            }
        } elseif ($gameId !== ''
            && !str_starts_with($idLower, 'gsc:')
            && !str_starts_with($idLower, 'bgaming:')
        ) {
            if (substr_count($gameId, ':') === 1) {
                [$vendor, $code] = explode(':', $gameId, 2);
                $found = $lookupVendorCode($vendor, $code);
                if ($found !== '') {
                    return $found;
                }
                // Legacy prefixes (e.g. drakon:56849) — match by bare game code.
                $found = $lookupByGameCode($code);
                if ($found !== '') {
                    return $found;
                }
            }
            $found = $lookupByGameCode($gameId);
            if ($found !== '') {
                return $found;
            }
        }

        foreach (self::liveTitleSearchCandidates($title) as $candidate) {
            try {
                $stmt = $pdo->prepare(
                    "SELECT vendor_code, game_code FROM casino_aggregator_games
                     WHERE {$active} AND {$liveFilter}
                       AND LOWER(game_name) = LOWER(:title)
                     ORDER BY id ASC LIMIT 1"
                );
                $stmt->execute([':title' => $candidate]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!is_array($row)) {
                    $stmt = $pdo->prepare(
                        "SELECT vendor_code, game_code FROM casino_aggregator_games
                         WHERE {$active} AND {$liveFilter}
                           AND LOWER(game_name) LIKE LOWER(:title)
                         ORDER BY CHAR_LENGTH(game_name) ASC, id ASC LIMIT 1"
                    );
                    $stmt->execute([':title' => $candidate . '%']);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                if (!is_array($row)) {
                    $stmt = $pdo->prepare(
                        "SELECT vendor_code, game_code FROM casino_aggregator_games
                         WHERE {$active} AND {$liveFilter}
                           AND LOWER(game_name) LIKE LOWER(:title)
                         ORDER BY CHAR_LENGTH(game_name) ASC, id ASC LIMIT 1"
                    );
                    $stmt->execute([':title' => '%' . $candidate . '%']);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                if (is_array($row)
                    && trim((string) ($row['vendor_code'] ?? '')) !== ''
                    && trim((string) ($row['game_code'] ?? '')) !== ''
                ) {
                    return $buildId((string) $row['vendor_code'], (string) $row['game_code']);
                }
            } catch (Throwable) {
            }
        }

        return '';
    }

    private static function resolveCatalogGameId(PDO $pdo, string $gameId, string $title): string
    {
        if ($gameId !== '') {
            if (str_starts_with($gameId, 'aggregator:') || str_starts_with($gameId, 'bgaming:')) {
                return $gameId;
            }

            // Bare "vendor:gameCode" from aggregator catalog.
            if (substr_count($gameId, ':') === 1 && !ctype_digit($gameId)) {
                [$vendor, $code] = explode(':', $gameId, 2);
                $vendor = trim($vendor);
                $code = trim($code);
                if ($vendor !== '' && $code !== '') {
                    try {
                        $stmt = $pdo->prepare(
                            'SELECT vendor_code, game_code
                             FROM casino_aggregator_games
                             WHERE is_active = 1 AND vendor_code = :v AND game_code = :g
                             LIMIT 1'
                        );
                        $stmt->execute([':v' => $vendor, ':g' => $code]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        if (is_array($row)) {
                            return 'aggregator:' . $row['vendor_code'] . ':' . $row['game_code'];
                        }
                    } catch (Throwable) {
                    }
                }
            }

            // Bare game_code match in aggregator catalog.
            if (!ctype_digit($gameId)) {
                try {
                    $stmt = $pdo->prepare(
                        'SELECT vendor_code, game_code
                         FROM casino_aggregator_games
                         WHERE is_active = 1 AND game_code = :g
                         ORDER BY id ASC
                         LIMIT 1'
                    );
                    $stmt->execute([':g' => $gameId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (is_array($row)) {
                        return 'aggregator:' . $row['vendor_code'] . ':' . $row['game_code'];
                    }
                } catch (Throwable) {
                }

                try {
                    $stmt = $pdo->prepare(
                        'SELECT identifier FROM bgaming_games WHERE is_active = 1 AND identifier = :g LIMIT 1'
                    );
                    $stmt->execute([':g' => $gameId]);
                    $identifier = trim((string) $stmt->fetchColumn());
                    if ($identifier !== '') {
                        return 'bgaming:' . $identifier;
                    }
                } catch (Throwable) {
                }
            }
        }

        if ($title === '') {
            return $gameId;
        }

        // Match curated homepage titles to aggregator games (exact then prefix).
        $titleNorm = preg_replace('/[™®©]+/u', '', $title) ?? $title;
        $titleNorm = trim(preg_replace('/\s+/u', ' ', $titleNorm) ?? $titleNorm);
        try {
            $stmt = $pdo->prepare(
                'SELECT vendor_code, game_code, game_name
                 FROM casino_aggregator_games
                 WHERE is_active = 1 AND LOWER(game_name) = LOWER(:title)
                 ORDER BY id ASC
                 LIMIT 1'
            );
            $stmt->execute([':title' => $titleNorm]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                $stmt = $pdo->prepare(
                    'SELECT vendor_code, game_code, game_name
                     FROM casino_aggregator_games
                     WHERE is_active = 1 AND LOWER(game_name) LIKE LOWER(:title)
                     ORDER BY CHAR_LENGTH(game_name) ASC, id ASC
                     LIMIT 1'
                );
                $stmt->execute([':title' => $titleNorm . '%']);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if (!is_array($row) && $titleNorm !== '') {
                $stmt = $pdo->prepare(
                    'SELECT vendor_code, game_code, game_name
                     FROM casino_aggregator_games
                     WHERE is_active = 1 AND LOWER(game_name) LIKE LOWER(:title)
                     ORDER BY CHAR_LENGTH(game_name) ASC, id ASC
                     LIMIT 1'
                );
                $stmt->execute([':title' => '%' . $titleNorm . '%']);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if (is_array($row) && trim((string) ($row['vendor_code'] ?? '')) !== '' && trim((string) ($row['game_code'] ?? '')) !== '') {
                return 'aggregator:' . $row['vendor_code'] . ':' . $row['game_code'];
            }
        } catch (Throwable) {
        }

        return $gameId;
    }

    private static function normalizeBannerPayload(array $payload): array
    {
        $image = trim((string) ($payload['image_url'] ?? ''));
        $fallback = 'assets/images/artikcekimlerdelimitleretakilmayok.webp';
        // Eski Android PWA promo SVG'si çekim banner'ı olarak kullanılmıştı; doğru kampanya görseline yönlendir.
        $relative = ltrim(str_replace('\\', '/', $image), '/');
        if ($relative !== '' && str_ends_with(strtolower($relative), 'androiduygulamaindir.svg')) {
            $image = $fallback;
        }
        if ($image === '' || self::isMissingLocalAsset($image)) {
            $image = self::isMissingLocalAsset($fallback) ? '' : $fallback;
        }

        return [
            'image_url' => $image,
            'alt' => trim((string) ($payload['alt'] ?? '')),
            'href' => trim((string) ($payload['href'] ?? '')),
            'onclick' => trim((string) ($payload['onclick'] ?? '')),
        ];
    }

    private static function isMissingLocalAsset(string $path): bool
    {
        if (preg_match('#^https?://#i', $path) || str_starts_with($path, '//')) {
            return false;
        }

        $base = defined('BASE_PATH') ? rtrim((string) BASE_PATH, '/\\') : shared_package_root();
        $relative = ltrim(str_replace('\\', '/', $path), '/');

        return $relative === '' || !is_file($base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value !== '' ? $value : null;
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value === 1;
        }
        $value = strtolower(trim((string) $value));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}
