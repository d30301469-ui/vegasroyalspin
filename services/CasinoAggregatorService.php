<?php

declare(strict_types=1);

/**
 * Casino Aggregator — multi-vendor slots & live casino (Operator API v1.0.3).
 *
 * Operator API (we -> provider): GetVendors, GetVendorGames, GetGameUrl, ...
 * Wallet Callback (provider -> us): GetBalance, ChangeBalance, UpdateDetail
 */
final class CasinoAggregatorService
{
    public const GAME_ID_PREFIX = 'aggregator:';
    private const DEFAULT_API_BASE = '';
    private static bool $schemaBootstrapped = false;
    private const SIGN_HEADERS = [
        'HTTP_X_SIGNATURE',
        'HTTP_X_SIGN',
        'HTTP_X_CALLBACK_SIGNATURE',
        'HTTP_X_REQUEST_SIGN',
        'HTTP_SIGNATURE',
    ];

    public static function bootstrap(PDO $pdo): void
    {
        if (self::$schemaBootstrapped) {
            return;
        }
        self::createSchema($pdo);
        self::ensureSchemaUpgrades($pdo);
        self::ensureDefaultConfig($pdo);
        self::$schemaBootstrapped = true;
    }

    private static function ensureSchemaUpgrades(PDO $pdo): void
    {
        try {
            $col = $pdo->query("SHOW COLUMNS FROM casino_aggregator_games LIKE 'image_url'")->fetch(PDO::FETCH_ASSOC);
            $type = strtolower((string) ($col['Type'] ?? ''));
            if ($type !== '' && !str_starts_with($type, 'text') && !str_starts_with($type, 'longtext') && !str_starts_with($type, 'mediumtext')) {
                $pdo->exec('ALTER TABLE casino_aggregator_games MODIFY image_url TEXT NULL');
            }
        } catch (Throwable) {
        }
    }

    public static function createSchema(PDO $pdo): void
    {
        $migration = dirname(__DIR__) . '/database/migrations/2026_07_27_000000_create_casino_aggregator_tables.php';
        if (is_readable($migration)) {
            $runner = require $migration;
            if (is_callable($runner)) {
                $runner($pdo);
                return;
            }
        }

        throw new RuntimeException('Casino aggregator migration dosyası bulunamadı.');
    }

    private static function ensureDefaultConfig(PDO $pdo): void
    {
        $pdo->exec(
            "INSERT IGNORE INTO casino_aggregator_config
                (id, agent_code, api_token, api_base_url, site_endpoint, api_mode, currency, lang, is_active)
             VALUES (1, '', '', '', '', 'seamless', 'TRY', 'tr', 0)"
        );
    }

    public static function config(PDO $pdo): array
    {
        try {
            self::bootstrap($pdo);
            $row = $pdo->query('SELECT * FROM casino_aggregator_config WHERE id = 1 LIMIT 1')?->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : [];
        } catch (Throwable) {
            return [];
        }
    }

    public static function updateConfig(PDO $pdo, array $data): void
    {
        self::bootstrap($pdo);
        $allowed = [
            'agent_code', 'api_token', 'api_base_url', 'site_endpoint', 'api_mode',
            'sign_private_key', 'verify_public_key', 'currency', 'lang', 'callback_allowed_ips',
        ];
        $secrets = ['api_token', 'sign_private_key', 'verify_public_key'];
        $sets = [];
        $params = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = trim((string) $data[$key]);
            if (in_array($key, $secrets, true) && $value === '') {
                continue;
            }
            if ($key === 'api_mode') {
                $value = in_array($value, ['seamless', 'transfer'], true) ? $value : 'seamless';
            }
            if ($key === 'currency') {
                $value = strtoupper($value) ?: 'TRY';
            }
            $sets[] = "`{$key}` = :{$key}";
            $params[":{$key}"] = $value;
        }
        $sets[] = 'is_active = :is_active';
        $params[':is_active'] = (!empty($data['is_active']) && $data['is_active'] !== '0') ? 1 : 0;
        if ($sets === []) {
            return;
        }
        $pdo->prepare('UPDATE casino_aggregator_config SET ' . implode(', ', $sets) . ' WHERE id = 1')->execute($params);
    }

    public static function isConfigured(PDO $pdo): bool
    {
        $cfg = self::config($pdo);
        return !empty($cfg['agent_code']) && !empty($cfg['api_token']) && !empty($cfg['api_base_url']);
    }

    public static function ownsGameId(string $gameId): bool
    {
        return str_starts_with(strtolower(trim($gameId)), strtolower(self::GAME_ID_PREFIX));
    }

    /** @return array{vendor_code: string, game_code: string}|null */
    public static function parseGameId(string $gameId): ?array
    {
        $gameId = trim($gameId);
        if (!self::ownsGameId($gameId)) {
            return null;
        }
        $rest = substr($gameId, strlen(self::GAME_ID_PREFIX));
        $parts = explode(':', $rest, 2);
        if (count($parts) !== 2) {
            return null;
        }
        $vendor = trim($parts[0]);
        $game = trim($parts[1]);
        if ($vendor === '' || $game === '') {
            return null;
        }
        return ['vendor_code' => $vendor, 'game_code' => $game];
    }

    public static function buildGameId(string $vendorCode, string $gameCode): string
    {
        return self::GAME_ID_PREFIX . trim($vendorCode) . ':' . trim($gameCode);
    }

    /** @return array{vendor_count: int} */
    public static function syncVendors(PDO $pdo): array
    {
        self::bootstrap($pdo);
        $cfg = self::configuredConfig($pdo);
        $response = self::requestWithConfig($cfg, [
            'method'    => 'GetVendors',
            'token'     => (string) $cfg['api_token'],
            'agentCode' => (string) $cfg['agent_code'],
        ], 20);
        self::assertSuccess($response, 'GetVendors');
        $vendors = is_array($response['vendors'] ?? null) ? $response['vendors'] : [];
        $count = 0;
        $stmt = $pdo->prepare(
            'INSERT INTO casino_aggregator_vendors (vendor_code, vendor_name, game_type, synced_at)
             VALUES (:code, :name, :type, NOW())
             ON DUPLICATE KEY UPDATE
                vendor_name = VALUES(vendor_name),
                game_type = VALUES(game_type),
                synced_at = NOW()'
        );
        $lang = strtolower(trim((string) ($cfg['lang'] ?? 'tr')));
        foreach ($vendors as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = trim((string) ($row['vendorCode'] ?? ''));
            if ($code === '') {
                continue;
            }
            $stmt->execute([
                ':code' => $code,
                ':name' => self::resolveLocalizedLabel($row['vendorName'] ?? '', $lang) ?: $code,
                ':type' => max(1, (int) ($row['gameType'] ?? 1)),
            ]);
            $count++;
        }
        $pdo->exec('UPDATE casino_aggregator_config SET vendors_synced_at = NOW() WHERE id = 1');
        self::repairCatalogLabels($pdo, $lang);
        return ['vendor_count' => $count];
    }

    /** @return array{vendor_count: int, game_count: int, errors: list<string>} */
    public static function syncGames(PDO $pdo, ?string $vendorCode = null): array
    {
        self::bootstrap($pdo);
        $cfg = self::configuredConfig($pdo);
        $sql = 'SELECT vendor_code FROM casino_aggregator_vendors WHERE is_active = 1';
        $params = [];
        if ($vendorCode !== null && trim($vendorCode) !== '') {
            $sql .= ' AND vendor_code = :vendor';
            $params[':vendor'] = trim($vendorCode);
        }
        $sql .= ' ORDER BY vendor_code ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $vendors = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        if ($vendors === []) {
            self::syncVendors($pdo);
            $stmt->execute($params);
            $vendors = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }

        $gameCount = 0;
        $errors = [];
        $insert = $pdo->prepare(
            'INSERT INTO casino_aggregator_games
                (vendor_code, game_code, game_name, game_type, image_url, raw_payload, synced_at)
             VALUES (:vendor, :game, :name, :type, :image, :raw, NOW())
             ON DUPLICATE KEY UPDATE
                game_name = VALUES(game_name),
                game_type = VALUES(game_type),
                image_url = VALUES(image_url),
                raw_payload = VALUES(raw_payload),
                synced_at = NOW()'
        );

        foreach ($vendors as $vendor) {
            $vendor = trim((string) $vendor);
            if ($vendor === '') {
                continue;
            }
            usleep(1_000_000);
            try {
                $response = self::requestWithConfig($cfg, [
                    'method'     => 'GetVendorGames',
                    'token'      => (string) $cfg['api_token'],
                    'agentCode'  => (string) $cfg['agent_code'],
                    'vendorCode' => $vendor,
                ], 30);
                self::assertSuccess($response, 'GetVendorGames:' . $vendor);
            } catch (Throwable $e) {
                $errors[] = $vendor . ': ' . $e->getMessage();
                continue;
            }
            $games = is_array($response['vendorGames'] ?? null) ? $response['vendorGames'] : [];
            foreach ($games as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $gameCode = trim((string) ($row['gameCode'] ?? ''));
                if ($gameCode === '') {
                    continue;
                }
                $lang = strtolower(trim((string) ($cfg['lang'] ?? 'tr')));
                $rawJson = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $image = self::resolveGameImage([
                    'image_url' => $row['imageUrl'] ?? ($row['image_url'] ?? ''),
                    'raw_payload' => is_string($rawJson) ? $rawJson : null,
                ], $lang);
                $insert->execute([
                    ':vendor' => $vendor,
                    ':game'   => $gameCode,
                    ':name'   => self::resolveLocalizedLabel($row['gameName'] ?? '', $lang) ?: $gameCode,
                    ':type'   => max(1, (int) ($row['gameType'] ?? 1)),
                    ':image'  => $image !== '' ? $image : null,
                    ':raw'    => $rawJson,
                ]);
                $gameCount++;
            }
        }

        $pdo->exec('UPDATE casino_aggregator_config SET games_synced_at = NOW() WHERE id = 1');
        $repair = self::repairCatalogLabels($pdo);
        return [
            'vendor_count' => count($vendors),
            'game_count'   => $gameCount,
            'errors'       => $errors,
            'repaired_vendors' => (int) ($repair['vendors'] ?? 0),
            'repaired_games'   => (int) ($repair['games'] ?? 0),
        ];
    }

    /** @return array{vendors: int, games: int} */
    public static function repairCatalogLabels(PDO $pdo, ?string $lang = null): array
    {
        self::bootstrap($pdo);
        $cfg = self::config($pdo);
        $lang = strtolower(trim((string) ($lang ?? $cfg['lang'] ?? 'tr')));
        if ($lang === '') {
            $lang = 'tr';
        }

        $vendorFixed = 0;
        $vendorStmt = $pdo->prepare('UPDATE casino_aggregator_vendors SET vendor_name = :name WHERE id = :id');
        foreach ($pdo->query('SELECT id, vendor_code, vendor_name FROM casino_aggregator_vendors')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $raw = trim((string) ($row['vendor_name'] ?? ''));
            if ($raw === '' || !self::looksLikeLocalizedJson($raw)) {
                continue;
            }
            $resolved = self::resolveLocalizedLabel($raw, $lang);
            if ($resolved === '' || $resolved === $raw) {
                continue;
            }
            $vendorStmt->execute([':name' => $resolved, ':id' => (int) $row['id']]);
            $vendorFixed++;
        }

        $gameFixed = 0;
        $gameStmt = $pdo->prepare(
            'UPDATE casino_aggregator_games SET game_name = :name, image_url = :image WHERE id = :id'
        );
        foreach ($pdo->query('SELECT id, game_code, game_name, image_url, raw_payload FROM casino_aggregator_games')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $currentName = trim((string) ($row['game_name'] ?? ''));
            $currentImage = trim((string) ($row['image_url'] ?? ''));
            $newName = self::resolveLocalizedLabel($currentName, $lang) ?: $currentName;
            $newImage = self::resolveGameImage($row, $lang);
            $needsName = self::looksLikeLocalizedJson($currentName) && $newName !== '' && $newName !== $currentName;
            $needsImage = $newImage !== '' && $newImage !== $currentImage
                && ($currentImage === ''
                    || !self::isUsableMediaUrl($currentImage)
                    || self::looksLikeLocalizedJson($currentImage)
                    || stripos($currentImage, '/default/') !== false);
            if (!$needsName && !$needsImage) {
                continue;
            }
            $gameStmt->execute([
                ':name'  => $needsName ? $newName : $currentName,
                ':image' => $needsImage ? $newImage : ($currentImage !== '' ? $currentImage : null),
                ':id'    => (int) $row['id'],
            ]);
            $gameFixed++;
        }

        return ['vendors' => $vendorFixed, 'games' => $gameFixed];
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function resolveGameImage(array $row, ?string $lang = null): string
    {
        $candidates = [];

        $raw = $row['raw_payload'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decodedRaw = json_decode($raw, true);
            if (!is_array($decodedRaw)) {
                $decodedRaw = json_decode(stripslashes($raw), true);
            }
            $raw = is_array($decodedRaw) ? $decodedRaw : null;
        }
        if (is_array($raw)) {
            // Prefer raw payload — image_url column may be truncated JSON leftovers.
            foreach (['imageUrl', 'image_url', 'thumbnailUrl', 'thumbnail_url', 'thumbnail', 'iconUrl', 'icon_url', 'icon', 'img', 'cover', 'banner'] as $key) {
                if (array_key_exists($key, $raw)) {
                    $candidates[] = $raw[$key];
                }
            }
        }

        $candidates[] = $row['image_url'] ?? null;
        $candidates[] = $row['imageUrl'] ?? null;
        $candidates[] = $row['thumbnail_url'] ?? null;
        $candidates[] = $row['thumbnailUrl'] ?? null;
        $candidates[] = $row['cover'] ?? null;

        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }
            $resolved = self::resolveMediaUrl($candidate, $lang);
            if (!self::isUsableMediaUrl($resolved)) {
                $resolved = self::extractMediaUrl($resolved !== '' ? $resolved : (is_string($candidate) ? $candidate : ''));
            }
            if (self::isUsableMediaUrl($resolved)) {
                return self::normalizeMediaUrl($resolved);
            }
        }

        return '';
    }

    /**
     * Image maps look like {"en":"https://.../lobby/x.avif","lobby":"https://.../default/x.avif"}.
     * Prefer en /lobby/ assets; never use raw JSON as the final URL.
     */
    public static function resolveMediaUrl(mixed $value, ?string $lang = null): string
    {
        $lang = strtolower(trim((string) ($lang ?? 'tr')));
        if ($lang === '') {
            $lang = 'tr';
        }

        if (is_array($value)) {
            return self::normalizeMediaUrl(self::pickBestMediaCandidate($value, $lang));
        }

        if (!is_string($value)) {
            return '';
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (self::isUsableMediaUrl($trimmed)) {
            return self::normalizeMediaUrl(self::preferLobbyPath($trimmed));
        }

        if (self::looksLikeLocalizedJson($trimmed)) {
            foreach ([$trimmed, stripslashes($trimmed), html_entity_decode($trimmed, ENT_QUOTES, 'UTF-8')] as $candidate) {
                $decoded = json_decode($candidate, true);
                if (is_string($decoded) && self::looksLikeLocalizedJson($decoded)) {
                    $decoded = json_decode($decoded, true);
                }
                if (is_array($decoded)) {
                    $picked = self::pickBestMediaCandidate($decoded, $lang);
                    if ($picked !== '') {
                        return self::normalizeMediaUrl($picked);
                    }
                }
            }

            if (preg_match('/["\']en["\']\s*:\s*["\']([^"\']+)["\']/i', $trimmed, $matches)) {
                return self::normalizeMediaUrl((string) ($matches[1] ?? ''));
            }
            if (preg_match('/["\']lobby["\']\s*:\s*["\']([^"\']+)["\']/i', $trimmed, $matches)) {
                return self::normalizeMediaUrl(self::preferLobbyPath((string) ($matches[1] ?? '')));
            }
        }

        $fromLabel = self::resolveLocalizedLabel($trimmed, $lang);
        if (self::isUsableMediaUrl($fromLabel)) {
            return self::normalizeMediaUrl(self::preferLobbyPath($fromLabel));
        }

        return '';
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private static function pickBestMediaCandidate(array $decoded, string $lang): string
    {
        $urls = [];
        foreach ([$lang, 'en', 'tr', 'default', 'lobby'] as $key) {
            $match = self::arrayValueByKeyInsensitive($decoded, $key);
            if (is_string($match)) {
                $url = trim($match);
                if ($url !== '' && (self::isUsableMediaUrl($url) || str_contains($url, '://'))) {
                    $urls[] = $url;
                }
            }
        }
        foreach ($decoded as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }
            $url = trim($candidate);
            if ($url !== '' && (self::isUsableMediaUrl($url) || str_contains($url, '://')) && !in_array($url, $urls, true)) {
                $urls[] = $url;
            }
        }

        if ($urls === []) {
            return '';
        }

        foreach ($urls as $url) {
            if (stripos($url, '/lobby/') !== false) {
                return $url;
            }
        }

        return self::preferLobbyPath($urls[0]);
    }

    /**
     * Lobby CDN often serves working thumbs under /lobby/ instead of /default/.
     */
    public static function preferLobbyPath(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (stripos($url, '/default/') !== false && stripos($url, '/lobby/') === false) {
            $rewritten = preg_replace('#/default/#i', '/lobby/', $url, 1);
            if (is_string($rewritten) && $rewritten !== '') {
                return $rewritten;
            }
        }
        return $url;
    }

    public static function isUsableMediaUrl(string $value): bool
    {
        $value = trim($value);
        if ($value === '' || self::looksLikeLocalizedJson($value)) {
            return false;
        }
        if (preg_match('#^(https?:)?//[^\s<>"\']+#i', $value) === 1) {
            return true;
        }
        if (str_starts_with($value, '/') && !str_starts_with($value, '//') && preg_match('#\.(png|jpe?g|webp|gif|svg|avif)(\?.*)?$#i', $value) === 1) {
            return true;
        }
        return false;
    }

    public static function normalizeMediaUrl(string $value): string
    {
        $value = trim($value);
        $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        $value = rtrim($value, " \t\n\r\0\x0B},)]\"'");
        if (str_starts_with($value, '//')) {
            $value = 'https:' . $value;
        }
        return self::preferLobbyPath($value);
    }

    /**
     * Keep provider CDN URLs as-is (including .avif). Do not rewrite extensions.
     */
    public static function preferCompatibleMediaUrl(string $url): string
    {
        return self::normalizeMediaUrl($url);
    }

    /**
     * Ordered fallbacks for frontend onerror chains.
     *
     * @return list<string>
     */
    public static function mediaUrlFallbacks(string $url): array
    {
        $url = self::normalizeMediaUrl($url);
        if ($url === '') {
            return [];
        }

        $out = [$url];
        if (preg_match('#\.(avif|webp|png|jpe?g|gif)(\?|$)#i', $url) !== 1) {
            return $out;
        }

        foreach (['avif', 'webp', 'png', 'jpg', 'jpeg'] as $ext) {
            $candidate = preg_replace('#\.(avif|webp|png|jpe?g|gif)(\?|$)#i', '.' . $ext . '$2', $url);
            if (is_string($candidate) && $candidate !== '' && !in_array($candidate, $out, true)) {
                $out[] = $candidate;
            }
        }
        return $out;
    }

    public static function extractMediaUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('#https?://[^\s<>"\']+#i', $value, $matches) === 1) {
            return self::normalizeMediaUrl((string) $matches[0]);
        }
        if (preg_match('#//[^\s<>"\']+#i', $value, $matches) === 1) {
            return self::normalizeMediaUrl((string) $matches[0]);
        }
        return '';
    }

    /**
     * @param list<string> $filters
     * @return array{names: list<string>, codes: list<string>}
     */
    public static function expandProviderFilter(PDO $pdo, array $filters, ?string $lang = null): array
    {
        self::bootstrap($pdo);
        $cfg = self::config($pdo);
        $lang = strtolower(trim((string) ($lang ?? $cfg['lang'] ?? 'tr')));
        if ($lang === '') {
            $lang = 'tr';
        }

        $names = [];
        $codes = [];
        foreach ($filters as $filter) {
            $filter = trim((string) $filter);
            if ($filter === '' || strtolower($filter) === 'hepsi') {
                continue;
            }
            $names[$filter] = true;
            $resolved = self::resolveLocalizedLabel($filter, $lang);
            if ($resolved !== '') {
                $names[$resolved] = true;
            }
        }

        if ($names === []) {
            return ['names' => [], 'codes' => []];
        }

        try {
            foreach ($pdo->query('SELECT vendor_code, vendor_name FROM casino_aggregator_vendors')->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $code = trim((string) ($row['vendor_code'] ?? ''));
                $rawName = trim((string) ($row['vendor_name'] ?? ''));
                $resolvedName = self::resolveLocalizedLabel($rawName, $lang) ?: $code;
                foreach (array_keys($names) as $filterName) {
                    if (strcasecmp($resolvedName, $filterName) === 0
                        || strcasecmp($rawName, $filterName) === 0
                        || strcasecmp($code, $filterName) === 0) {
                        $names[$resolvedName] = true;
                        if ($rawName !== '') {
                            $names[$rawName] = true;
                        }
                        if ($code !== '') {
                            $codes[$code] = true;
                        }
                    }
                }
            }
        } catch (Throwable) {
        }

        return [
            'names' => array_values(array_keys($names)),
            'codes' => array_values(array_keys($codes)),
        ];
    }

    /**
     * @param array<string, mixed> $row DB row from casino_aggregator_transactions (+ optional game/vendor joins)
     * @return array<string, mixed>
     */
    public static function buildMemberGameHistoryRow(array $row): array
    {
        $rawTxnType = strtolower((string) ($row['txn_type'] ?? 'bet'));
        $normalizedTxnType = match ($rawTxnType) {
            'win' => 'win',
            'cancel' => 'refund',
            default => 'bet',
        };
        $amount = abs((float) ($row['amount'] ?? 0));
        $vendorCode = trim((string) ($row['vendor_code'] ?? ''));
        $gameCode = trim((string) ($row['game_code'] ?? ''));
        $gameType = (int) ($row['game_type'] ?? 1);
        $isLive = $gameType === 2;
        $providerName = self::resolveLocalizedLabel($row['provider_name'] ?? $vendorCode) ?: $vendorCode;
        $providerCode = $vendorCode !== '' ? $vendorCode : 'aggregator';
        $gameName = self::resolveLocalizedLabel($row['game_name'] ?? $gameCode) ?: $gameCode;
        $gameId = ($vendorCode !== '' && $gameCode !== '') ? self::buildGameId($vendorCode, $gameCode) : $gameCode;
        $localId = (string) ($row['id'] ?? '');

        return [
            'id' => 'aggregator:' . $localId,
            'history_id' => 'aggregator:' . $localId,
            'transactionId' => (string) ($row['txn_code'] ?? ''),
            'transaction_id' => (string) ($row['txn_code'] ?? ''),
            'providerTxnId' => (string) ($row['txn_code'] ?? ''),
            'provider_txn_id' => (string) ($row['txn_code'] ?? ''),
            'relatedTransactionId' => (string) ($row['pair_code'] ?? ''),
            'related_transaction_id' => (string) ($row['pair_code'] ?? ''),
            'sessionToken' => (string) ($row['wager_id'] ?? ''),
            'session_id' => (string) ($row['wager_id'] ?? ''),
            'roundId' => (string) ($row['round_id'] ?? ''),
            'round_id' => (string) ($row['round_id'] ?? ''),
            'gameId' => $gameId,
            'game_id' => $gameId,
            'gameName' => $gameName,
            'game_name' => $gameName,
            'providerCode' => $providerCode,
            'provider_code' => $providerCode,
            'providerName' => $providerName,
            'provider_name' => $providerName,
            'category' => $isLive ? 'live_casino' : 'slot',
            'source' => $isLive ? 'live_casino' : 'slot',
            'txnType' => $normalizedTxnType,
            'txn_type' => $normalizedTxnType,
            'status' => 'completed',
            'betAmount' => $normalizedTxnType === 'bet' ? $amount : 0.0,
            'bet_amount' => $normalizedTxnType === 'bet' ? $amount : 0.0,
            'winAmount' => $normalizedTxnType !== 'bet' ? $amount : 0.0,
            'win_amount' => $normalizedTxnType !== 'bet' ? $amount : 0.0,
            'balanceAfter' => (float) ($row['after_balance'] ?? 0),
            'balance_after' => (float) ($row['after_balance'] ?? 0),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'wallet' => 'casino',
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function normalizeWinnerDisplayRow(array $row): array
    {
        $row['provider_name'] = self::resolveLocalizedLabel($row['provider_name'] ?? '');
        $row['game_name'] = self::resolveLocalizedLabel($row['game_name'] ?? '');
        $image = self::resolveGameImage($row);
        $row['image_url'] = $image;
        $row['banner'] = $image;
        unset($row['raw_payload']);
        return $row;
    }

    public static function resolveLocalizedLabel(mixed $value, ?string $lang = null): string
    {
        $lang = strtolower(trim((string) ($lang ?? 'tr')));
        if ($lang === '') {
            $lang = 'tr';
        }

        if (is_array($value)) {
            return self::pickLocalized($value, $lang, self::arrayLooksLikeMediaMap($value));
        }

        if (!is_string($value)) {
            return '';
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        // Double-encoded JSON string: "{\"en\":\"...\"}"
        if (($trimmed[0] === '"' || $trimmed[0] === "'") && str_contains($trimmed, '{')) {
            $unquoted = json_decode($trimmed, true);
            if (is_string($unquoted) && $unquoted !== '') {
                $trimmed = trim($unquoted);
            } elseif (is_array($unquoted)) {
                return self::pickLocalized($unquoted, $lang, self::arrayLooksLikeMediaMap($unquoted));
            }
        }

        if (!self::looksLikeLocalizedJson($trimmed)) {
            return $trimmed;
        }

        foreach ([$trimmed, stripslashes($trimmed), html_entity_decode($trimmed, ENT_QUOTES, 'UTF-8')] as $candidate) {
            $decoded = json_decode($candidate, true);
            if (is_string($decoded) && self::looksLikeLocalizedJson($decoded)) {
                $decoded = json_decode($decoded, true);
            }
            if (is_array($decoded)) {
                $picked = self::pickLocalized($decoded, $lang, self::arrayLooksLikeMediaMap($decoded));
                if ($picked !== '') {
                    return $picked;
                }
            }
        }

        // Prefer en URL explicitly when JSON parse fails but payload is media map text.
        if (preg_match('/["\']en["\']\s*:\s*["\']([^"\']+)["\']/i', $trimmed, $matches)) {
            return trim((string) ($matches[1] ?? ''));
        }
        if (preg_match('/["\']?(?:tr|' . preg_quote($lang, '/') . ')["\']?\s*:\s*["\']([^"\']+)["\']/i', $trimmed, $matches)) {
            return trim((string) ($matches[1] ?? ''));
        }

        $extracted = self::extractMediaUrl($trimmed);
        if ($extracted !== '') {
            return $extracted;
        }

        return $trimmed;
    }

    public static function looksLikeLocalizedJson(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }
        if ($trimmed[0] === '{' || $trimmed[0] === '[') {
            return true;
        }
        return str_contains($trimmed, '{"') || str_contains($trimmed, '{\"') || str_contains($trimmed, '"en"');
    }

    /** @param array<string, mixed> $decoded */
    private static function arrayLooksLikeMediaMap(array $decoded): bool
    {
        foreach ($decoded as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }
            if (self::isUsableMediaUrl($candidate) || (str_contains($candidate, 'http') && str_contains($candidate, '://'))) {
                return true;
            }
        }
        return array_key_exists('lobby', $decoded) || array_key_exists('en', $decoded);
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private static function pickLocalized(array $decoded, string $lang, bool $preferMediaKeys = false): string
    {
        // Media maps: {"en":"...avif","lobby":"...avif"} — always prefer en over lobby.
        $priority = $preferMediaKeys
            ? [$lang, 'en', 'tr', 'default', 'lobby']
            : [$lang, 'en', 'tr', 'default'];

        $tried = [];
        foreach ($priority as $key) {
            $key = strtolower(trim((string) $key));
            if ($key === '' || isset($tried[$key])) {
                continue;
            }
            $tried[$key] = true;
            $match = self::arrayValueByKeyInsensitive($decoded, $key);
            if (is_string($match)) {
                $picked = trim($match);
                if ($picked !== '') {
                    return $picked;
                }
            }
        }

        foreach ($decoded as $mapKey => $candidate) {
            $mapKeyNorm = strtolower(trim((string) $mapKey));
            if ($preferMediaKeys && $mapKeyNorm === 'lobby') {
                continue;
            }
            if (isset($tried[$mapKeyNorm])) {
                continue;
            }
            if (is_string($candidate)) {
                $picked = trim($candidate);
                if ($picked !== '') {
                    return $picked;
                }
            }
        }

        if ($preferMediaKeys) {
            $lobby = self::arrayValueByKeyInsensitive($decoded, 'lobby');
            if (is_string($lobby) && trim($lobby) !== '') {
                return trim($lobby);
            }
        }

        return '';
    }

    /** @param array<string, mixed> $decoded */
    private static function arrayValueByKeyInsensitive(array $decoded, string $key): mixed
    {
        if (array_key_exists($key, $decoded)) {
            return $decoded[$key];
        }
        foreach ($decoded as $mapKey => $value) {
            if (strtolower((string) $mapKey) === $key) {
                return $value;
            }
        }
        return null;
    }

    public static function launch(PDO $pdo, ?array $user, array $input): array
    {
        try {
            $cfg = self::activeConfig($pdo);
        } catch (Throwable $e) {
            return ['success' => false, 'code' => 503, 'message' => $e->getMessage()];
        }

        $parsed = self::parseGameId(trim((string) ($input['game_id'] ?? $input['gameId'] ?? '')));
        if ($parsed === null) {
            return ['success' => false, 'code' => 404, 'message' => 'Geçersiz aggregator oyun kimliği.'];
        }

        $gameStmt = $pdo->prepare(
            'SELECT g.*, v.vendor_name, v.is_active AS vendor_active
             FROM casino_aggregator_games g
             INNER JOIN casino_aggregator_vendors v ON v.vendor_code = g.vendor_code
             WHERE g.vendor_code = :vendor AND g.game_code = :game
             LIMIT 1'
        );
        $gameStmt->execute([':vendor' => $parsed['vendor_code'], ':game' => $parsed['game_code']]);
        $gameRow = $gameStmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($gameRow) || (int) ($gameRow['is_active'] ?? 0) !== 1 || (int) ($gameRow['vendor_active'] ?? 0) !== 1) {
            return ['success' => false, 'code' => 404, 'message' => 'Oyun bulunamadı veya pasif.'];
        }

        $isGuest = !is_array($user) || (int) ($user['id'] ?? 0) <= 0;
        $userId = $isGuest ? 0 : (int) $user['id'];
        if ($isGuest) {
            $seed = session_id();
            if ($seed === '') {
                $seed = (string) ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . (string) ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . date('Ymd');
            }
            $userCode = 'guest_' . substr(hash('sha256', $seed), 0, 24);
            $nickname = 'guest';
        } else {
            $userCode = (string) $userId;
            $nickname = trim((string) ($user['username'] ?? ('user_' . $userId)));
        }

        $currency = strtoupper(trim((string) ($cfg['currency'] ?? 'TRY')));
        $lang = strtolower(trim((string) ($input['lang'] ?? $cfg['lang'] ?? 'tr')));
        $channel = strtolower(trim((string) ($input['channel'] ?? 'desktop')));
        $channel = in_array($channel, ['desktop', 'mobile'], true) ? $channel : 'desktop';

        if (!$isGuest && strtolower((string) ($cfg['api_mode'] ?? 'seamless')) === 'transfer') {
            try {
                self::request($pdo, [
                    'method'    => 'CreateUser',
                    'token'     => (string) $cfg['api_token'],
                    'agentCode' => (string) $cfg['agent_code'],
                    'userCode'  => $userCode,
                ]);
            } catch (Throwable) {
            }
        }

        $payload = [
            'method'       => 'GetGameUrl',
            'token'        => (string) $cfg['api_token'],
            'agentCode'    => (string) $cfg['agent_code'],
            'userCode'     => $userCode,
            'nickname'     => $nickname,
            'vendorCode'   => $parsed['vendor_code'],
            'gameCode'     => $parsed['game_code'],
            'currencyCode' => $currency,
            'language'     => $lang,
            'channel'      => $channel,
            'isDemo'       => $isGuest,
        ];
        $homeUrl = trim((string) ($input['home_url'] ?? ''));
        if ($homeUrl === '') {
            $homeUrl = defined('SITE_URL') && trim((string) SITE_URL) !== ''
                ? rtrim((string) SITE_URL, '/')
                : ('https://' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        }
        $payload['homeUrl'] = $homeUrl;

        try {
            usleep(6_000_000);
            $response = self::request($pdo, $payload, 25);
        } catch (Throwable $e) {
            return ['success' => false, 'code' => 503, 'message' => 'Aggregator API bağlantı hatası: ' . $e->getMessage()];
        }

        $status = (int) ($response['status'] ?? -1);
        $launchUrl = trim((string) ($response['launchUrl'] ?? ''));
        if ($launchUrl === '' || $status !== 0) {
            $providerMsg = (string) ($response['msg'] ?? ('status ' . $status));
            return [
                'success' => false,
                'code'    => 422,
                'message' => 'Oyun URL döndürmedi: ' . $providerMsg,
                'raw'     => $response,
            ];
        }

        try {
            $pdo->prepare(
                'INSERT INTO casino_aggregator_sessions
                    (user_id, username, user_code, vendor_code, game_code, currency, lang, channel, launch_url, request_payload, response_payload)
                 VALUES (:uid, :uname, :ucode, :vendor, :game, :cur, :lang, :chan, :url, :req, :res)'
            )->execute([
                ':uid'    => $userId > 0 ? $userId : null,
                ':uname'  => $nickname,
                ':ucode'  => $userCode,
                ':vendor' => $parsed['vendor_code'],
                ':game'   => $parsed['game_code'],
                ':cur'    => $currency,
                ':lang'   => $lang,
                ':chan'   => $channel,
                ':url'    => $launchUrl,
                ':req'    => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':res'    => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable) {
        }

        return [
            'success'  => true,
            'code'     => 200,
            'message'  => 'Oyun başlatıldı.',
            'data'     => [
                'game_url'   => $launchUrl,
                'launch_url' => $launchUrl,
                'open_mode'  => 'iframe',
                'mode'       => $isGuest ? 'guest' : 'real',
            ],
            'game_url' => $launchUrl,
        ];
    }

    public static function wallet(PDO $pdo, array $payload, string $rawBody, string $signature = ''): array
    {
        $payload = self::normalizeWalletPayload($payload);
        $start = microtime(true);
        $method = self::normalizeWalletMethod(trim((string) ($payload['method'] ?? '')));
        $userId = null;
        $txnCode = trim((string) ($payload['txnCode'] ?? ''));
        $status = 200;
        $result = ['status' => 2, 'msg' => 'INVALID_ACTION'];

        try {
            if (!self::verifyCallback($pdo, $rawBody, $signature)) {
                $result = ['status' => 2, 'msg' => 'INVALID_SIGNATURE'];
            } elseif (!self::verifyToken($pdo, $payload)) {
                $result = ['status' => 3, 'msg' => 'INVALID_AGENT'];
            } else {
                $result = match ($method) {
                    'GetBalance'    => self::walletGetBalance($pdo, $payload),
                    'ChangeBalance' => self::walletChangeBalance($pdo, $payload),
                    'UpdateDetail'  => self::walletUpdateDetail($pdo, $payload),
                    default         => ['status' => 2, 'msg' => 'INVALID_ACTION'],
                };
                $userId = isset($result['__user_id']) ? (int) $result['__user_id'] : null;
            }
        } catch (Throwable $e) {
            error_log('Casino aggregator wallet error: ' . $e->getMessage());
            $result = ['status' => 1, 'msg' => 'INTERNAL_ERROR'];
        }

        $statusCode = isset($result['status']) ? (int) $result['status'] : null;
        unset($result['__user_id']);

        try {
            self::logWallet($pdo, $method, $userId, $txnCode, $status, $statusCode,
                is_string($result['msg'] ?? null) && ($statusCode ?? 0) !== 0 ? (string) $result['msg'] : null,
                (int) round((microtime(true) - $start) * 1000), $payload, $result);
        } catch (Throwable) {
        }

        return ['status' => $status, 'body' => $result];
    }

    public static function resolveGameFromDb(PDO $pdo, string $vendorCode, string $gameCode): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT vendor_code, game_code FROM casino_aggregator_games
             WHERE vendor_code = :vendor AND game_code = :game AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([':vendor' => $vendorCode, ':game' => $gameCode]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private static function configuredConfig(PDO $pdo): array
    {
        $cfg = self::config($pdo);
        if ($cfg === []) {
            throw new RuntimeException('Casino aggregator yapılandırması bulunamadı.');
        }
        foreach (['agent_code', 'api_token', 'api_base_url'] as $k) {
            if (trim((string) ($cfg[$k] ?? '')) === '') {
                throw new RuntimeException('Casino aggregator yapılandırması eksik: ' . $k);
            }
        }
        return $cfg;
    }

    private static function activeConfig(PDO $pdo): array
    {
        $cfg = self::configuredConfig($pdo);
        if ((int) ($cfg['is_active'] ?? 0) !== 1) {
            throw new RuntimeException('Casino aggregator entegrasyonu pasif.');
        }
        return $cfg;
    }

    /** @param array<string, mixed> $cfg */
    private static function requestWithConfig(array $cfg, array $payload, int $timeout = 15): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $headers = ['Content-Type: application/json', 'Accept: application/json'];
        $signature = self::signMessage($body, (string) ($cfg['sign_private_key'] ?? ''));
        if ($signature !== '') {
            $headers[] = 'X-Signature: ' . $signature;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => rtrim((string) $cfg['api_base_url'], '/'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => max(10, $timeout),
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        foreach ([defined('BASE_PATH') ? BASE_PATH . '/config/cacert.pem' : ''] as $caInfo) {
            if ($caInfo !== '' && is_readable($caInfo)) {
                curl_setopt($ch, CURLOPT_CAINFO, $caInfo);
                break;
            }
        }

        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            throw new RuntimeException('Aggregator API cURL hatası: ' . $err);
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Aggregator API geçersiz JSON (HTTP ' . $code . '): ' . substr((string) $raw, 0, 200));
        }
        return $decoded;
    }

    private static function request(PDO $pdo, array $payload, int $timeout = 15): array
    {
        return self::requestWithConfig(self::activeConfig($pdo), $payload, $timeout);
    }

    private static function assertSuccess(array $response, string $context): void
    {
        if ((int) ($response['status'] ?? -1) === 0) {
            return;
        }
        throw new RuntimeException($context . ': ' . (string) ($response['msg'] ?? 'API hatası'));
    }

    private static function signMessage(string $message, string $privateKeyB64): string
    {
        if (!function_exists('sodium_crypto_sign_seed_keypair')) {
            return '';
        }
        $seed = base64_decode(trim($privateKeyB64), true);
        if (!is_string($seed) || strlen($seed) !== SODIUM_CRYPTO_SIGN_SEEDBYTES) {
            return '';
        }
        $keypair = sodium_crypto_sign_seed_keypair($seed);
        $secret = sodium_crypto_sign_secretkey($keypair);
        return base64_encode(sodium_crypto_sign_detached($message, $secret));
    }

    private static function verifyMessage(string $message, string $signatureB64, string $publicKeyB64): bool
    {
        if (!function_exists('sodium_crypto_sign_verify_detached')) {
            return false;
        }
        $public = base64_decode(trim($publicKeyB64), true);
        $sig = base64_decode(trim($signatureB64), true);
        if (!is_string($public) || strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }
        if (!is_string($sig) || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }
        try {
            return sodium_crypto_sign_verify_detached($sig, $message, $public);
        } catch (Throwable) {
            return false;
        }
    }

    private static function callbackSignature(): string
    {
        foreach (self::SIGN_HEADERS as $key) {
            $value = trim((string) ($_SERVER[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    private static function verifyCallback(PDO $pdo, string $rawBody, string $signature): bool
    {
        $cfg = self::config($pdo);
        $public = trim((string) ($cfg['verify_public_key'] ?? ''));
        if ($public === '') {
            return true;
        }
        $strict = (string) getenv('CASINO_AGGREGATOR_STRICT_SIGNATURE') === '1';
        $signature = $signature !== '' ? $signature : self::callbackSignature();
        if ($signature === '') {
            return !$strict;
        }
        return self::verifyMessage($rawBody, $signature, $public) || !$strict;
    }

    private static function verifyToken(PDO $pdo, array $payload): bool
    {
        $cfg = self::config($pdo);
        $token = trim((string) ($cfg['api_token'] ?? ''));
        if ($token === '') {
            return false;
        }
        $payloadToken = trim((string) ($payload['token'] ?? ''));
        return hash_equals($token, $payloadToken);
    }

    private static function normalizeWalletPayload(array $payload): array
    {
        return [
            'method'       => $payload['method'] ?? $payload['action'] ?? '',
            'token'        => $payload['token'] ?? $payload['api_token'] ?? '',
            'userCode'     => $payload['userCode'] ?? $payload['user_code'] ?? $payload['username'] ?? '',
            'txnCode'      => $payload['txnCode'] ?? $payload['txn_code'] ?? '',
            'txnType'      => $payload['txnType'] ?? $payload['txn_type'] ?? '',
            'amount'       => $payload['amount'] ?? '',
            'pairCode'     => $payload['pairCode'] ?? $payload['pair_code'] ?? '',
            'wagerId'      => $payload['wagerId'] ?? $payload['wager_id'] ?? '',
            'vendorCode'   => $payload['vendorCode'] ?? $payload['vendor_code'] ?? '',
            'gameCode'     => $payload['gameCode'] ?? $payload['game_code'] ?? '',
            'currencyCode' => $payload['currencyCode'] ?? $payload['currency_code'] ?? '',
            'detail'       => $payload['detail'] ?? '',
            'isFreeRound'  => $payload['isFreeRound'] ?? $payload['is_free_round'] ?? 0,
            'isFinished'   => $payload['isFinished'] ?? $payload['is_finished'] ?? 0,
            'gameRoundId'  => $payload['gameRoundId'] ?? $payload['game_round_id'] ?? '',
        ] + $payload;
    }

    private static function normalizeWalletMethod(string $method): string
    {
        return match (strtolower(trim($method))) {
            'getbalance', 'get_balance' => 'GetBalance',
            'changebalance', 'change_balance' => 'ChangeBalance',
            'updatedetail', 'update_detail' => 'UpdateDetail',
            default => $method,
        };
    }

    private static function walletGetBalance(PDO $pdo, array $payload): array
    {
        $user = self::userByCode($pdo, (string) ($payload['userCode'] ?? ''));
        if ($user === null) {
            return ['status' => 5, 'msg' => 'INVALID_USER'];
        }
        return [
            'status'    => 0,
            'msg'       => 'SUCCESS',
            'balance'   => round((float) ($user['balance'] ?? 0), 2),
            '__user_id' => (int) $user['id'],
        ];
    }

    private static function walletChangeBalance(PDO $pdo, array $payload): array
    {
        $user = self::userByCode($pdo, (string) ($payload['userCode'] ?? ''));
        if ($user === null) {
            return ['status' => 5, 'msg' => 'INVALID_USER'];
        }
        $userId = (int) $user['id'];
        $txnCode = trim((string) ($payload['txnCode'] ?? ''));
        if ($txnCode === '') {
            return ['status' => 13, 'msg' => 'INVALID_PARAMETER', '__user_id' => $userId];
        }

        $existing = $pdo->prepare('SELECT after_balance FROM casino_aggregator_transactions WHERE txn_code = :c LIMIT 1');
        $existing->execute([':c' => $txnCode]);
        $prev = $existing->fetchColumn();
        if ($prev !== false) {
            return ['status' => 0, 'msg' => 'SUCCESS', 'balance' => round((float) $prev, 2), '__user_id' => $userId];
        }

        $txnType = (int) ($payload['txnType'] ?? -1);
        $amount = round((float) ($payload['amount'] ?? 0), 2);
        $type = match ($txnType) {
            0 => 'bet',
            1 => 'win',
            2 => 'cancel',
            default => '',
        };
        if ($type === '') {
            return ['status' => 13, 'msg' => 'INVALID_PARAMETER', '__user_id' => $userId];
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id, username, balance FROM users WHERE id = :id AND banned = 0 LIMIT 1 FOR UPDATE');
            $stmt->execute([':id' => $userId]);
            $locked = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($locked)) {
                $pdo->rollBack();
                return ['status' => 5, 'msg' => 'INVALID_USER', '__user_id' => $userId];
            }

            $before = round((float) $locked['balance'], 2);
            $after = round($before + $amount, 2);
            if ($after < 0) {
                $pdo->rollBack();
                return ['status' => 8, 'msg' => 'INSUFFICIENT_MONEY', 'balance' => $before, '__user_id' => $userId];
            }

            $pdo->prepare('UPDATE users SET balance = :bal WHERE id = :id')->execute([':bal' => $after, ':id' => $userId]);

            if (class_exists('WageringService', false)) {
                if ($type === 'bet' && $amount < 0) {
                    WageringService::registerBet($pdo, $userId, abs($amount));
                } elseif ($type === 'cancel' && $amount > 0) {
                    WageringService::reverseBet($pdo, $userId, abs($amount));
                }
            }

            $pdo->prepare(
                'INSERT INTO casino_aggregator_transactions
                    (user_id, username, txn_code, pair_code, wager_id, round_id, vendor_code, game_code,
                     txn_type, amount, before_balance, after_balance, currency, is_free_round, is_finished, detail, raw_payload)
                 VALUES (:uid, :uname, :txn, :pair, :wager, :round, :vendor, :game, :type, :amt, :before, :after,
                         :cur, :free, :fin, :detail, :raw)'
            )->execute([
                ':uid'    => $userId,
                ':uname'  => (string) ($locked['username'] ?? ''),
                ':txn'    => $txnCode,
                ':pair'   => trim((string) ($payload['pairCode'] ?? '')) ?: null,
                ':wager'  => trim((string) ($payload['wagerId'] ?? '')) ?: null,
                ':round'  => trim((string) ($payload['gameRoundId'] ?? '')) ?: null,
                ':vendor' => trim((string) ($payload['vendorCode'] ?? '')) ?: null,
                ':game'   => trim((string) ($payload['gameCode'] ?? '')) ?: null,
                ':type'   => $type,
                ':amt'    => $amount,
                ':before' => $before,
                ':after'  => $after,
                ':cur'    => strtoupper((string) ($payload['currencyCode'] ?? 'TRY')),
                ':free'   => !empty($payload['isFreeRound']) ? 1 : 0,
                ':fin'    => !empty($payload['isFinished']) ? 1 : 0,
                ':detail' => trim((string) ($payload['detail'] ?? '')) ?: null,
                ':raw'    => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $pdo->commit();
            return ['status' => 0, 'msg' => 'SUCCESS', 'balance' => $after, '__user_id' => $userId];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (str_contains($e->getMessage(), '1062') || stripos($e->getMessage(), 'Duplicate') !== false) {
                $balStmt = $pdo->prepare('SELECT balance FROM users WHERE id = :id LIMIT 1');
                $balStmt->execute([':id' => $userId]);
                return ['status' => 0, 'msg' => 'SUCCESS', 'balance' => round((float) ($balStmt->fetchColumn() ?: 0), 2), '__user_id' => $userId];
            }
            throw $e;
        }
    }

    private static function walletUpdateDetail(PDO $pdo, array $payload): array
    {
        $wagerId = trim((string) ($payload['wagerId'] ?? ''));
        $detail = (string) ($payload['detail'] ?? '');
        if ($wagerId === '') {
            return ['status' => 18, 'msg' => 'INVALID_WAGER'];
        }
        $sql = 'UPDATE casino_aggregator_transactions SET detail = :d';
        $params = [':d' => $detail, ':w' => $wagerId];
        if (!empty($payload['isFinished'])) {
            $sql .= ', is_finished = 1';
        }
        $sql .= ' WHERE wager_id = :w';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->rowCount() === 0) {
            return ['status' => 18, 'msg' => 'INVALID_WAGER'];
        }
        return ['status' => 0, 'msg' => 'SUCCESS'];
    }

    private static function userByCode(PDO $pdo, string $userCode): ?array
    {
        $userCode = trim($userCode);
        if ($userCode === '') {
            return null;
        }
        $column = ctype_digit($userCode) ? 'id' : 'username';
        try {
            $stmt = $pdo->prepare("SELECT id, username, balance FROM users WHERE {$column} = :v AND banned = 0 LIMIT 1");
            $stmt->execute([':v' => $userCode]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function logWallet(
        PDO $pdo,
        string $method,
        ?int $userId,
        string $txnCode,
        int $httpStatus,
        ?int $statusCode,
        ?string $errCode,
        int $duration,
        array $request,
        array $response
    ): void {
        $pdo->prepare(
            'INSERT INTO casino_aggregator_wallet_logs
                (method, user_id, txn_code, http_status, status_code, error_code, duration_ms, request_payload, response_payload)
             VALUES (:m, :u, :t, :h, :s, :e, :d, :req, :res)'
        )->execute([
            ':m'   => $method !== '' ? $method : null,
            ':u'   => $userId,
            ':t'   => $txnCode !== '' ? $txnCode : null,
            ':h'   => $httpStatus,
            ':s'   => $statusCode,
            ':e'   => $errCode,
            ':d'   => $duration,
            ':req' => json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':res' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
