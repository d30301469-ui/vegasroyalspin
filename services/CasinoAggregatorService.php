<?php

declare(strict_types=1);

/**
 * Casino Aggregator — multi-vendor slots & live casino (Operator API v1.0.3)
 * + Game Control API v1.0.0.
 *
 * Operator API: GetVendors, GetVendorGames, GetGameUrl, ...
 * Game Control: GetCurrentPlayers, GetCallList, CallApply, CallCancel, GetCallHistory,
 *     GetUserSetting, ChangeUserSetting, GetAgentSetting, ChangeAgentSetting
 * Wallet Callback: GetBalance, ChangeBalance, UpdateDetail
 */
final class CasinoAggregatorService
{
    public const GAME_ID_PREFIX = 'aggregator:';
    private const DEFAULT_API_BASE = '';
    private static bool $schemaBootstrapped = false;

    /** Appendix 4.12 — AgentSetting categories (key is usually empty). */
    public const AGENT_SETTING_CATEGORIES = [
        'RoundKey',
        'HideRoundId',
        'HideTournament',
        'HideBadge',
        'LowRtp',
        'HighRtp',
    ];

    /** Appendix 4.11 — UserSetting categories (key is usually empty). */
    public const USER_SETTING_CATEGORIES = [
        'LowRtp',
        'HighRtp',
    ];

    /** @deprecated use AGENT_SETTING_CATEGORIES */
    public const AGENT_SETTING_KEYS = [
        'RoundKey',
        'HideRoundId',
        'HideTournament',
        'HideBadge',
        'LowRtp',
        'HighRtp',
    ];
    /** @deprecated use USER_SETTING_CATEGORIES */
    public const USER_SETTING_KEYS = [
        'LowRtp',
        'HighRtp',
    ];

    /** Appendix 1 — API Response Codes */
    public const RESPONSE_CODES = [
        0  => ['msg' => 'SUCCESS', 'description' => 'Success'],
        1  => ['msg' => 'INTERNAL_ERROR', 'description' => 'Server internal error'],
        2  => ['msg' => 'INVALID_ACTION', 'description' => 'Request error'],
        3  => ['msg' => 'INVALID_AGENT', 'description' => 'Agent error'],
        4  => ['msg' => 'BLOCK_AGENT', 'description' => 'Blocked agent'],
        5  => ['msg' => 'INVALID_USER', 'description' => 'User error'],
        6  => ['msg' => 'BLOCK_USER', 'description' => 'Blocked user'],
        7  => ['msg' => 'DUPLICATE_USER', 'description' => 'Duplicate users'],
        8  => ['msg' => 'INSUFFICIENT_MONEY', 'description' => 'Insufficient balance'],
        12 => ['msg' => 'INVALID_VENDOR', 'description' => 'Vendor error'],
        13 => ['msg' => 'INVALID_PARAMETER', 'description' => 'Invalid request parameter'],
        14 => ['msg' => 'NETWORK_ERROR', 'description' => 'Network error'],
        15 => ['msg' => 'MAINTENANCE', 'description' => 'System under maintenance'],
        18 => ['msg' => 'INVALID_WAGER', 'description' => 'Invalid transaction or wager ID'],
        20 => ['msg' => 'INVALID_TIME', 'description' => 'Invalid time format or range'],
        21 => ['msg' => 'DUPLICATE_REQUESTKEY', 'description' => 'Duplicate request key (for duplicate prevention)'],
        22 => ['msg' => 'TIMEOUT_ERROR', 'description' => 'Request timed out'],
    ];

    /** Appendix 2 — Game type */
    public const GAME_TYPES = [
        1 => 'Slot',
        2 => 'Live Casino',
    ];

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
            $fallbackCol = $pdo->query("SHOW COLUMNS FROM casino_aggregator_games LIKE 'image_fallbacks'")->fetch(PDO::FETCH_ASSOC);
            if ($fallbackCol === false) {
                $pdo->exec('ALTER TABLE casino_aggregator_games ADD COLUMN image_fallbacks TEXT NULL AFTER image_url');
            }
        } catch (Throwable) {
        }

        try {
            self::ensureSettingsTables($pdo);
        } catch (Throwable $e) {
            error_log('Casino aggregator settings schema: ' . $e->getMessage());
        }
    }

    public static function createSchema(PDO $pdo): void
    {
        $migration = dirname(__DIR__) . '/database/migrations/2026_07_27_000000_create_casino_aggregator_tables.php';
        if (is_readable($migration)) {
            $runner = require $migration;
            if (is_callable($runner)) {
                $runner($pdo);
            } else {
                throw new RuntimeException('Casino aggregator migration dosyası bulunamadı.');
            }
        } else {
            throw new RuntimeException('Casino aggregator migration dosyası bulunamadı.');
        }

        try {
            self::ensureSettingsTables($pdo);
        } catch (Throwable $e) {
            error_log('Casino aggregator settings schema: ' . $e->getMessage());
        }
    }

    private static function runSettingsMigration(PDO $pdo): void
    {
        self::ensureSettingsTables($pdo);
    }

    /**
     * Create AgentSetting / UserSetting mirrors (Game Control API v1.0.0).
     * Scoped by vendorCode + gameCode + currencyCode + category (+ empty key).
     */
    private static function ensureSettingsTables(PDO $pdo): void
    {
        // Rebuild legacy global-only tables that lack vendor_code (doc requires per-game scope).
        if (self::tableExists($pdo, 'casino_aggregator_agent_settings')
            && !self::columnExists($pdo, 'casino_aggregator_agent_settings', 'vendor_code')) {
            $pdo->exec('DROP TABLE casino_aggregator_agent_settings');
        }
        if (self::tableExists($pdo, 'casino_aggregator_user_settings')
            && !self::columnExists($pdo, 'casino_aggregator_user_settings', 'vendor_code')) {
            $pdo->exec('DROP TABLE casino_aggregator_user_settings');
        }

        if (!self::tableExists($pdo, 'casino_aggregator_agent_settings')) {
            $pdo->exec(
                'CREATE TABLE casino_aggregator_agent_settings (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    vendor_code VARCHAR(120) NOT NULL DEFAULT \'\',
                    game_code VARCHAR(120) NOT NULL DEFAULT \'\',
                    currency_code VARCHAR(8) NOT NULL DEFAULT \'\',
                    category VARCHAR(64) NOT NULL,
                    setting_key VARCHAR(64) NOT NULL DEFAULT \'\',
                    setting_value VARCHAR(512) NOT NULL DEFAULT \'\',
                    synced_at DATETIME NULL DEFAULT NULL,
                    created_at DATETIME NULL DEFAULT NULL,
                    updated_at DATETIME NULL DEFAULT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_ca_agent_setting (vendor_code, game_code, currency_code, category, setting_key)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }

        if (!self::tableExists($pdo, 'casino_aggregator_user_settings')) {
            $pdo->exec(
                'CREATE TABLE casino_aggregator_user_settings (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id INT UNSIGNED NULL DEFAULT NULL,
                    user_code VARCHAR(120) NOT NULL,
                    vendor_code VARCHAR(120) NOT NULL DEFAULT \'\',
                    game_code VARCHAR(120) NOT NULL DEFAULT \'\',
                    currency_code VARCHAR(8) NOT NULL DEFAULT \'\',
                    category VARCHAR(64) NOT NULL,
                    setting_key VARCHAR(64) NOT NULL DEFAULT \'\',
                    setting_value VARCHAR(512) NOT NULL DEFAULT \'\',
                    synced_at DATETIME NULL DEFAULT NULL,
                    created_at DATETIME NULL DEFAULT NULL,
                    updated_at DATETIME NULL DEFAULT NULL,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_ca_user_setting (user_code, vendor_code, game_code, currency_code, category, setting_key),
                    KEY idx_casino_agg_user_settings_user (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }

        if (!self::tableExists($pdo, 'casino_aggregator_call_logs')) {
            $pdo->exec(
                'CREATE TABLE casino_aggregator_call_logs (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    action VARCHAR(40) NOT NULL,
                    user_code VARCHAR(120) NULL DEFAULT NULL,
                    vendor_code VARCHAR(120) NULL DEFAULT NULL,
                    game_code VARCHAR(120) NULL DEFAULT NULL,
                    call_id BIGINT NULL DEFAULT NULL,
                    call_rtp DECIMAL(12,4) NULL DEFAULT NULL,
                    bet_amount DECIMAL(14,2) NULL DEFAULT NULL,
                    money_amount DECIMAL(14,2) NULL DEFAULT NULL,
                    status_code SMALLINT NULL DEFAULT NULL,
                    request_payload TEXT NULL,
                    response_payload TEXT NULL,
                    created_at DATETIME NULL DEFAULT NULL,
                    PRIMARY KEY (id),
                    KEY idx_ca_call_logs_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1'
            );
            $stmt->execute([':t' => $table, ':c' => $column]);
            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1'
            );
            $stmt->execute([':t' => $table]);
            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            try {
                $pdo->query('SELECT 1 FROM `' . str_replace('`', '``', $table) . '` LIMIT 1');
                return true;
            } catch (Throwable) {
                return false;
            }
        }
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
                (vendor_code, game_code, game_name, game_type, image_url, image_fallbacks, raw_payload, synced_at)
             VALUES (:vendor, :game, :name, :type, :image, :fallbacks, :raw, NOW())
             ON DUPLICATE KEY UPDATE
                game_name = VALUES(game_name),
                game_type = VALUES(game_type),
                image_url = VALUES(image_url),
                image_fallbacks = VALUES(image_fallbacks),
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
                try {
                    $media = self::resolveApiGameMedia($row, $lang);
                    if (self::isEgtVipVendor($vendor)) {
                        $media = self::forceGameMediaToPng($media);
                    }
                    $insert->execute([
                        ':vendor' => $vendor,
                        ':game'   => $gameCode,
                        ':name'   => self::resolveLocalizedLabel($row['gameName'] ?? '', $lang) ?: $gameCode,
                        ':type'   => max(1, (int) ($row['gameType'] ?? 1)),
                        ':image'  => ($media['cover'] ?? '') !== '' ? $media['cover'] : null,
                        ':fallbacks' => self::encodeStoredImageFallbacks($media['cover_fallbacks'] ?? []),
                        ':raw'    => $rawJson,
                    ]);
                    $gameCount++;
                } catch (Throwable $e) {
                    $errors[] = $vendor . '/' . $gameCode . ': ' . $e->getMessage();
                }
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
            'egt_vip_png'      => (int) ($repair['egt_vip_png'] ?? 0),
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
            'UPDATE casino_aggregator_games SET game_name = :name, image_url = :image, image_fallbacks = :fallbacks WHERE id = :id'
        );
        foreach ($pdo->query('SELECT id, vendor_code, game_code, game_name, image_url, image_fallbacks, raw_payload FROM casino_aggregator_games')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $currentName = trim((string) ($row['game_name'] ?? ''));
            $currentImage = trim((string) ($row['image_url'] ?? ''));
            $currentFallbacks = self::encodeStoredImageFallbacks(
                self::decodeStoredImageFallbacks($row['image_fallbacks'] ?? null)
            );
            $newName = self::resolveLocalizedLabel($currentName, $lang) ?: $currentName;
            try {
                $media = self::resolveApiGameMedia($row, $lang);
                if (self::isEgtVipVendor((string) ($row['vendor_code'] ?? ''))) {
                    $media = self::forceGameMediaToPng($media);
                }
            } catch (Throwable) {
                continue;
            }
            $newImage = trim((string) ($media['cover'] ?? ''));
            $newFallbacks = self::encodeStoredImageFallbacks($media['cover_fallbacks'] ?? []);
            $needsName = self::looksLikeLocalizedJson($currentName) && $newName !== '' && $newName !== $currentName;
            $needsImage = $newImage !== '' && $newImage !== $currentImage;
            $needsFallbacks = $newFallbacks !== null && $newFallbacks !== $currentFallbacks;
            if (!$needsName && !$needsImage && !$needsFallbacks) {
                continue;
            }
            $gameStmt->execute([
                ':name'      => $needsName ? $newName : $currentName,
                ':image'     => $newImage !== '' ? $newImage : ($currentImage !== '' ? $currentImage : null),
                ':fallbacks' => $newFallbacks,
                ':id'        => (int) $row['id'],
            ]);
            $gameFixed++;
        }

        $egtVipPng = self::repairEgtVipImagesToPng($pdo);

        return [
            'vendors' => $vendorFixed,
            'games' => $gameFixed,
            'egt_vip_png' => (int) ($egtVipPng['updated'] ?? 0),
        ];
    }

    public static function isEgtVipVendor(string $vendorCode): bool
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '', $vendorCode) ?? '');

        return $normalized !== '' && str_contains($normalized, 'egtvip');
    }

    /**
     * Force EGT VIP lobby thumbnails to PNG in DB (image_url + image_fallbacks).
     *
     * @return array{updated: int, scanned: int}
     */
    public static function repairEgtVipImagesToPng(PDO $pdo): array
    {
        self::bootstrap($pdo);
        $updated = 0;
        $scanned = 0;
        $stmt = $pdo->prepare(
            'UPDATE casino_aggregator_games
             SET image_url = :image, image_fallbacks = :fallbacks
             WHERE id = :id'
        );

        $rows = $pdo->query(
            'SELECT id, vendor_code, image_url, image_fallbacks
             FROM casino_aggregator_games'
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row) || !self::isEgtVipVendor((string) ($row['vendor_code'] ?? ''))) {
                continue;
            }
            $scanned++;
            $media = self::forceGameMediaToPng([
                'cover' => trim((string) ($row['image_url'] ?? '')),
                'cover_fallbacks' => self::decodeStoredImageFallbacks($row['image_fallbacks'] ?? null),
                'image_fallbacks' => self::decodeStoredImageFallbacks($row['image_fallbacks'] ?? null),
            ]);
            $newImage = trim((string) ($media['cover'] ?? ''));
            $newFallbacks = self::encodeStoredImageFallbacks($media['cover_fallbacks'] ?? []);
            $oldImage = trim((string) ($row['image_url'] ?? ''));
            $oldFallbacks = self::encodeStoredImageFallbacks(
                self::decodeStoredImageFallbacks($row['image_fallbacks'] ?? null)
            );
            if ($newImage === $oldImage && $newFallbacks === $oldFallbacks) {
                continue;
            }
            if ($newImage === '' && $newFallbacks === null) {
                continue;
            }
            $stmt->execute([
                ':image' => $newImage !== '' ? $newImage : ($oldImage !== '' ? $oldImage : null),
                ':fallbacks' => $newFallbacks,
                ':id' => (int) $row['id'],
            ]);
            $updated++;
        }

        return ['updated' => $updated, 'scanned' => $scanned];
    }

    /**
     * @param array{cover?: string, cover_fallbacks?: list<string>, image_fallbacks?: list<string>} $media
     * @return array{cover: string, cover_fallbacks: list<string>, image_fallbacks: list<string>}
     */
    public static function forceGameMediaToPng(array $media): array
    {
        $cover = self::rewriteMediaUrlToPng(trim((string) ($media['cover'] ?? '')));
        $fallbacks = [];
        $sourceFallbacks = [];
        if (is_array($media['cover_fallbacks'] ?? null)) {
            $sourceFallbacks = $media['cover_fallbacks'];
        } elseif (is_array($media['image_fallbacks'] ?? null)) {
            $sourceFallbacks = $media['image_fallbacks'];
        }
        foreach ($sourceFallbacks as $url) {
            $png = self::rewriteMediaUrlToPng(trim((string) $url));
            if ($png !== '' && !in_array($png, $fallbacks, true)) {
                $fallbacks[] = $png;
            }
        }
        if ($cover !== '' && !in_array($cover, $fallbacks, true)) {
            array_unshift($fallbacks, $cover);
        }
        if ($cover === '' && $fallbacks !== []) {
            $cover = (string) $fallbacks[0];
        }

        return [
            'cover' => $cover,
            'cover_fallbacks' => $fallbacks,
            'image_fallbacks' => $fallbacks,
        ];
    }

    public static function rewriteMediaUrlToPng(string $url): string
    {
        $url = self::normalizeMediaUrl(trim($url));
        if ($url === '' || !self::isUsableMediaUrl($url) || self::looksLikeLocalizedJson($url)) {
            return '';
        }
        if (preg_match('#\.(avif|webp|jpe?g|gif|png)(\?|$)#i', $url) === 1) {
            $png = preg_replace('#\.(avif|webp|jpe?g|gif|png)(\?|$)#i', '.png$2', $url, 1);

            return is_string($png) ? $png : $url;
        }

        return $url;
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function resolveGameImage(array $row, ?string $lang = null): string
    {
        $candidates = self::collectGameImageCandidates($row, $lang, false);

        return $candidates !== [] ? (string) $candidates[0] : '';
    }

    /**
     * Resolve cover + fallbacks from GetVendorGames VendorGame.imageUrl (API spec §5.4).
     * Uses the localized JSON map as provided — no HTTP probing or URL synthesis.
     *
     * @param array<string, mixed> $apiRow GetVendorGames row or DB row with raw_payload
     * @return array{cover: string, cover_fallbacks: list<string>, image_fallbacks: list<string>}
     */
    public static function resolveApiGameMedia(array $apiRow, ?string $lang = null): array
    {
        $lang = strtolower(trim((string) ($lang ?? 'tr')));
        if ($lang === '') {
            $lang = 'tr';
        }

        $candidates = self::collectMediaUrlCandidates(
            $apiRow['imageUrl'] ?? $apiRow['image_url'] ?? '',
            $lang
        );

        if ($candidates === []) {
            $raw = $apiRow['raw_payload'] ?? null;
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    $decoded = json_decode(stripslashes($raw), true);
                }
                if (is_array($decoded)) {
                    $candidates = self::collectMediaUrlCandidates(
                        $decoded['imageUrl'] ?? $decoded['image_url'] ?? '',
                        $lang
                    );
                }
            }
        }

        $candidates = self::dedupeMediaUrls($candidates);
        $regular = [];
        $marketing = [];
        foreach ($candidates as $url) {
            if (self::isBrokenMarketingImageUrl($url)) {
                $marketing[] = $url;
            } else {
                $regular[] = $url;
            }
        }
        $candidates = array_values(array_merge($regular, $marketing));

        $cover = $candidates[0] ?? '';

        return [
            'cover'           => $cover,
            'cover_fallbacks' => $candidates,
            'image_fallbacks' => $candidates,
        ];
    }

    /**
     * Image maps look like {"en":"https://.../lobby/x.avif","lobby":"https://.../default/x.avif"}.
     * Some games only work on /lobby/, others only on /default/ — never rewrite paths.
     */
    public static function resolveMediaUrl(mixed $value, ?string $lang = null): string
    {
        $candidates = self::collectMediaUrlCandidates($value, $lang);
        return $candidates[0] ?? '';
    }

    /**
     * All usable CDN URLs from a localized image map (en, lobby, etc.), in priority order.
     *
     * @return list<string>
     */
    public static function collectMediaUrlCandidates(mixed $value, ?string $lang = null): array
    {
        $lang = strtolower(trim((string) ($lang ?? 'tr')));
        if ($lang === '') {
            $lang = 'tr';
        }

        if (is_array($value)) {
            return self::collectMediaUrlCandidatesFromMap($value, $lang);
        }

        if (!is_string($value)) {
            return [];
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return [];
        }

        if (self::isUsableMediaUrl($trimmed)) {
            return [self::normalizeMediaUrl($trimmed)];
        }

        if (self::looksLikeLocalizedJson($trimmed)) {
            foreach ([$trimmed, stripslashes($trimmed), html_entity_decode($trimmed, ENT_QUOTES, 'UTF-8')] as $candidate) {
                $decoded = json_decode($candidate, true);
                if (is_string($decoded) && self::looksLikeLocalizedJson($decoded)) {
                    $decoded = json_decode($decoded, true);
                }
                if (is_array($decoded)) {
                    $urls = self::collectMediaUrlCandidatesFromMap($decoded, $lang);
                    if ($urls !== []) {
                        return $urls;
                    }
                }
            }

            $regexUrls = [];
            foreach (['en', 'lobby', 'tr', 'default'] as $key) {
                if (preg_match('/["\']' . preg_quote($key, '/') . '["\']\s*:\s*["\']([^"\']+)["\']/i', $trimmed, $matches) === 1) {
                    $url = self::normalizeMediaUrl((string) ($matches[1] ?? ''));
                    if ($url !== '' && !in_array($url, $regexUrls, true)) {
                        $regexUrls[] = $url;
                    }
                }
            }
            if ($regexUrls !== []) {
                return $regexUrls;
            }
        }

        $fromLabel = self::resolveLocalizedLabel($trimmed, $lang);
        if (self::isUsableMediaUrl($fromLabel)) {
            return [self::normalizeMediaUrl($fromLabel)];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $decoded
     * @return list<string>
     */
    private static function collectMediaUrlCandidatesFromMap(array $decoded, string $lang): array
    {
        $urls = [];
        $seen = [];

        $add = static function (mixed $candidate) use (&$urls, &$seen): void {
            if (!is_string($candidate)) {
                return;
            }
            $url = self::normalizeMediaUrl(trim($candidate));
            if ($url === '' || !self::isUsableMediaUrl($url)) {
                return;
            }
            if (isset($seen[$url])) {
                return;
            }
            $seen[$url] = true;
            $urls[] = $url;
        };

        foreach ([$lang, 'en', 'tr', 'lobby', 'default'] as $key) {
            $add(self::arrayValueByKeyInsensitive($decoded, $key));
        }
        foreach ($decoded as $candidate) {
            $add($candidate);
        }

        return self::dedupeMediaUrls($urls);
    }

    /**
     * Preserve discovery order; never reorder by file extension heuristics.
     *
     * @param list<string> $urls
     * @return list<string>
     */
    public static function dedupeMediaUrls(array $urls): array
    {
        $unique = [];
        foreach ($urls as $url) {
            $url = self::normalizeMediaUrl(trim((string) $url));
            if ($url !== '' && self::isUsableMediaUrl($url) && !in_array($url, $unique, true)) {
                $unique[] = $url;
            }
        }

        return $unique;
    }

    /**
     * @deprecated Use dedupeMediaUrls() — extension-based ordering breaks mixed CDN games.
     * @param list<string> $urls
     * @return list<string>
     */
    public static function prioritizeMediaUrls(array $urls): array
    {
        return self::dedupeMediaUrls($urls);
    }

    private static function mediaProbeCacheDir(): string
    {
        $base = defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__);

        return rtrim(str_replace('\\', '/', $base), '/') . '/storage/cache/media-probes';
    }

    public static function probeMediaUrl(string $url): bool
    {
        $url = self::normalizeMediaUrl(trim($url));
        if ($url === '' || !self::isUsableMediaUrl($url)) {
            return false;
        }
        if (!self::shouldProbeMediaUrl($url)) {
            return true;
        }

        $cacheDir = self::mediaProbeCacheDir();
        $cacheFile = $cacheDir . '/' . sha1($url) . '.json';
        if (is_file($cacheFile)) {
            $cached = json_decode((string) @file_get_contents($cacheFile), true);
            if (is_array($cached) && isset($cached['ok'], $cached['ts']) && (time() - (int) $cached['ts']) < 604800) {
                return (bool) $cached['ok'];
            }
        }

        try {
            $ok = self::probeMediaUrlLive($url);
        } catch (Throwable) {
            $ok = false;
        }

        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        @file_put_contents($cacheFile, json_encode(['ok' => $ok, 'ts' => time()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);

        return $ok;
    }

    private static function probeMediaUrlLive(string $url): bool
    {
        $previousTimeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', '2');

        try {
            if (function_exists('curl_init')) {
                $body = self::probeMediaUrlViaCurl($url);

                return $body !== false && self::isValidImageSample((string) $body);
            }

            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 2,
                    'ignore_errors' => true,
                    'header' => "User-Agent: VegasRoyalSpin-MediaProbe/1.0\r\nRange: bytes=0-63\r\n",
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $body = @file_get_contents($url, false, $context);
            if ($body === false || $body === '') {
                return false;
            }

            $headers = $GLOBALS['http_response_header'] ?? [];
            foreach ($headers as $headerLine) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $headerLine, $matches) === 1) {
                    $status = (int) ($matches[1] ?? 0);
                    if ($status < 200 || $status >= 400) {
                        return false;
                    }
                    break;
                }
            }

            return self::isValidImageSample((string) $body);
        } catch (Throwable) {
            return false;
        } finally {
            if ($previousTimeout !== false && $previousTimeout !== '') {
                ini_set('default_socket_timeout', (string) $previousTimeout);
            }
        }
    }

    private static function isValidImageSample(string $body): bool
    {
        $sample = substr($body, 0, 64);
        if ($sample === '') {
            return false;
        }
        if (str_starts_with($sample, '<!DOCTYPE') || str_starts_with($sample, '<html') || str_starts_with($sample, '<HTML')) {
            return false;
        }

        return self::looksLikeImageBytes($sample);
    }

    private static function probeMediaUrlViaCurl(string $url): string|false
    {
        $handle = curl_init($url);
        if ($handle === false) {
            return false;
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 2,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_RANGE => '0-63',
            CURLOPT_USERAGENT => 'VegasRoyalSpin-MediaProbe/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($handle);
        $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($body === false || $httpCode < 200 || $httpCode >= 400) {
            return false;
        }

        return $body;
    }

    private static function looksLikeImageBytes(string $sample): bool
    {
        if (str_starts_with($sample, "\x89PNG\r\n\x1a\n")) {
            return true;
        }
        if (str_starts_with($sample, "GIF87a") || str_starts_with($sample, "GIF89a")) {
            return true;
        }
        if (str_starts_with($sample, "\xFF\xD8\xFF")) {
            return true;
        }
        if (str_starts_with($sample, 'RIFF') && str_contains(substr($sample, 0, 16), 'WEBP')) {
            return true;
        }
        if (strlen($sample) >= 12 && substr($sample, 4, 4) === 'ftyp') {
            return true;
        }

        return preg_match('#\.(png|jpe?g|webp|gif|avif|svg)(\?|$)#i', $sample) === 1;
    }

    private static function isBrokenMarketingImageUrl(string $url): bool
    {
        return preg_match('#egt-digital\.com/wp-content/#i', $url) === 1;
    }

    /**
     * Only probe known aggregator lobby CDNs. Other hosts (duel.com, etc.) often
     * timeout or block server-side requests and must never abort catalog sync.
     */
    private static function shouldProbeMediaUrl(string $url): bool
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        if ($host === '') {
            return false;
        }

        return preg_match('#(?:mhjbneijrtonline|ohjaieijyg)\.org$#i', $host) === 1;
    }

    /**
     * Probe CDN candidates and return working URLs first.
     *
     * @param list<string> $urls
     * @return list<string>
     */
    public static function validateMediaUrlCandidates(array $urls, int $maxProbes = 20): array
    {
        $working = [];
        $unprobed = [];
        $broken = [];
        $probes = 0;

        foreach (self::dedupeMediaUrls($urls) as $url) {
            if (self::isBrokenMarketingImageUrl($url)) {
                $broken[] = $url;
                continue;
            }
            if (!self::shouldProbeMediaUrl($url)) {
                $unprobed[] = $url;
                continue;
            }
            if ($probes >= $maxProbes) {
                $unprobed[] = $url;
                continue;
            }
            $probes++;
            try {
                if (self::probeMediaUrl($url)) {
                    $working[] = $url;
                } else {
                    $broken[] = $url;
                }
            } catch (Throwable) {
                $broken[] = $url;
            }
        }

        return array_values(array_merge($working, $unprobed, $broken));
    }

    /**
     * @param array<string, mixed> $row
     * @return list<string>
     */
    private static function resolveGameCode(array $row): string
    {
        $gameCode = trim((string) ($row['game_code'] ?? ''));
        if ($gameCode !== '') {
            return $gameCode;
        }
        $gameId = trim((string) ($row['game_id'] ?? ''));
        if ($gameId !== '' && preg_match('#:([^:]+)$#', $gameId, $matches) === 1) {
            return trim((string) ($matches[1] ?? ''));
        }

        return '';
    }

    /**
     * @return list<string>
     */
    private static function knownLobbyCdnHosts(): array
    {
        return [
            'v1j674k0rsrc.mhjbneijrtonline.org',
            '3787r8es.ohjaieijyg.org',
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return list<string>
     */
    private static function extractLobbyCdnHosts(array $row): array
    {
        $hosts = [];
        $addHost = static function (string $url) use (&$hosts): void {
            $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
            if ($host !== '' && preg_match('#(?:mhjbneijrtonline|ohjaieijyg)\.org$#i', $host) === 1 && !in_array($host, $hosts, true)) {
                $hosts[] = $host;
            }
        };

        foreach (['image_url', 'imageUrl', 'thumbnail_url', 'thumbnailUrl', 'cover'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                $addHost($value);
            }
        }

        $raw = $row['raw_payload'] ?? null;
        if (is_string($raw) && $raw !== '') {
            foreach (self::extractMediaUrlsDeep($raw) as $candidate) {
                $addHost($candidate);
            }
        }

        foreach (self::knownLobbyCdnHosts() as $host) {
            if (!in_array($host, $hosts, true)) {
                $hosts[] = $host;
            }
        }

        return $hosts;
    }

    /**
     * @param array<string, mixed> $row
     * @return list<string>
     */
    public static function synthesizeLobbyMediaCandidates(array $row): array
    {
        $gameCode = self::resolveGameCode($row);
        if ($gameCode === '') {
            return [];
        }

        $urls = [];
        foreach (self::extractLobbyCdnHosts($row) as $host) {
            foreach (['/lobby/', '/default/'] as $path) {
                foreach (['avif', 'png', 'webp', 'jpg'] as $ext) {
                    $urls[] = 'https://' . $host . $path . $gameCode . '.' . $ext;
                }
            }
        }

        return self::dedupeMediaUrls($urls);
    }

    /**
     * @param list<string> $preferred
     * @param list<string> $extra
     * @return list<string>
     */
    private static function mergeMediaCandidates(array $preferred, array $extra): array
    {
        return self::dedupeMediaUrls(array_merge($preferred, $extra));
    }

    public static function mediaUrlQualityScore(string $url): int
    {
        return self::probeMediaUrl($url) ? 100 : 0;
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private static function pickBestMediaCandidate(array $decoded, string $lang): string
    {
        return self::collectMediaUrlCandidatesFromMap($decoded, $lang)[0] ?? '';
    }

    /**
     * @deprecated Do not rewrite CDN paths — games may require /lobby/ or /default/ as provided.
     */
    public static function preferLobbyPath(string $url): string
    {
        return trim($url);
    }

    /**
     * Generate alternate CDN URLs (/lobby/ vs /default/, extension swaps).
     *
     * @return list<string>
     */
    public static function expandMediaUrlVariants(string $url): array
    {
        $url = self::normalizeMediaUrl($url);
        if ($url === '' || !self::isUsableMediaUrl($url)) {
            return [];
        }

        $variants = [];
        $add = static function (string $candidate) use (&$variants): void {
            $candidate = self::normalizeMediaUrl($candidate);
            if ($candidate !== '' && self::isUsableMediaUrl($candidate) && !in_array($candidate, $variants, true)) {
                $variants[] = $candidate;
            }
        };

        $add($url);

        $pathVariants = [$url];
        if (stripos($url, '/lobby/') !== false) {
            $swapped = preg_replace('#/lobby/#i', '/default/', $url, 1);
            if (is_string($swapped) && $swapped !== '') {
                $pathVariants[] = $swapped;
            }
        }
        if (stripos($url, '/default/') !== false) {
            $swapped = preg_replace('#/default/#i', '/lobby/', $url, 1);
            if (is_string($swapped) && $swapped !== '') {
                $pathVariants[] = $swapped;
            }
        }

        foreach ($pathVariants as $pathVariant) {
            $add($pathVariant);
            if (preg_match('#\.(avif|webp|png|jpe?g|gif)(\?|$)#i', $pathVariant) !== 1) {
                continue;
            }
            foreach (['png', 'webp', 'jpg', 'jpeg', 'avif'] as $ext) {
                $extVariant = preg_replace('#\.(avif|webp|png|jpe?g|gif)(\?|$)#i', '.' . $ext . '$2', $pathVariant);
                if (!is_string($extVariant) || $extVariant === '') {
                    continue;
                }
                $add($extVariant);
                if (stripos($extVariant, '/lobby/') !== false) {
                    $add((string) preg_replace('#/lobby/#i', '/default/', $extVariant, 1));
                }
                if (stripos($extVariant, '/default/') !== false) {
                    $add((string) preg_replace('#/default/#i', '/lobby/', $extVariant, 1));
                }
            }
        }

        return self::dedupeMediaUrls($variants);
    }

    /**
     * Recursively collect image-like URLs from nested API payloads.
     *
     * @return list<string>
     */
    public static function extractMediaUrlsDeep(mixed $value, ?string $lang = null): array
    {
        $urls = [];
        $seen = [];
        $walk = static function (mixed $node) use (&$walk, &$urls, &$seen, $lang): void {
            if (is_string($node)) {
                $trimmed = trim($node);
                if ($trimmed === '') {
                    return;
                }
                if (self::looksLikeLocalizedJson($trimmed)) {
                    foreach (self::collectMediaUrlCandidates($trimmed, $lang) as $candidate) {
                        if (!isset($seen[$candidate])) {
                            $seen[$candidate] = true;
                            $urls[] = $candidate;
                        }
                    }
                    return;
                }
                if (self::isUsableMediaUrl($trimmed)) {
                    $normalized = self::normalizeMediaUrl($trimmed);
                } else {
                    $normalized = self::extractMediaUrl($trimmed);
                }
                if ($normalized !== '' && self::isUsableMediaUrl($normalized) && !isset($seen[$normalized])) {
                    $seen[$normalized] = true;
                    $urls[] = $normalized;
                }
                return;
            }
            if (!is_array($node)) {
                return;
            }
            foreach ($node as $child) {
                $walk($child);
            }
        };

        $walk($value);
        return $urls;
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
        return $value;
    }

    /**
     * Keep provider CDN URLs as-is (including .avif). Do not rewrite extensions.
     */
    public static function preferCompatibleMediaUrl(string $url): string
    {
        return self::normalizeMediaUrl($url);
    }

    /**
     * Ordered fallbacks for frontend onerror chains (alternate CDN URLs first).
     *
     * @param array<string, mixed>|null $row
     * @return list<string>
     */
    public static function mediaUrlFallbacks(string $url, ?array $row = null, ?string $lang = null): array
    {
        $out = [];
        $add = static function (string $candidate) use (&$out): void {
            $candidate = self::normalizeMediaUrl($candidate);
            if ($candidate !== '' && self::isUsableMediaUrl($candidate) && !in_array($candidate, $out, true)) {
                $out[] = $candidate;
            }
        };

        $add($url);

        if ($row !== null) {
            foreach (self::collectGameImageCandidates($row, $lang, false) as $candidate) {
                $add($candidate);
            }
        }

        return self::dedupeMediaUrls($out);
    }

    /**
     * @param array<string, mixed> $row
     * @return list<string>
     */
    public static function collectGameImageCandidates(array $row, ?string $lang = null, bool $includeSynthesized = false): array
    {
        $urls = [];
        $seen = [];

        $merge = static function (array $candidates) use (&$urls, &$seen): void {
            foreach ($candidates as $candidate) {
                if ($candidate === '' || isset($seen[$candidate])) {
                    continue;
                }
                $seen[$candidate] = true;
                $urls[] = $candidate;
            }
        };

        $raw = $row['raw_payload'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decodedRaw = json_decode($raw, true);
            if (!is_array($decodedRaw)) {
                $decodedRaw = json_decode(stripslashes($raw), true);
            }
            $raw = is_array($decodedRaw) ? $decodedRaw : null;
        }
        if (is_array($raw)) {
            foreach (['imageUrl', 'image_url'] as $key) {
                if (array_key_exists($key, $raw)) {
                    $merge(self::collectMediaUrlCandidates($raw[$key], $lang));
                }
            }
        }

        foreach (['image_url', 'imageUrl', 'cover'] as $key) {
            if (!empty($row[$key])) {
                $merge(self::collectMediaUrlCandidates($row[$key], $lang));
            }
        }

        if ($includeSynthesized) {
            $merge(self::synthesizeLobbyMediaCandidates($row));
        }

        return self::dedupeMediaUrls($urls);
    }

    /**
     * @param array<string, mixed> $row
     * @return list<string>
     */
    public static function resolveGameImageFallbacks(array $row, ?string $lang = null): array
    {
        return self::mediaUrlFallbacks(self::resolveGameImage($row, $lang), $row, $lang);
    }

    /**
     * @return list<string>
     */
    public static function decodeStoredImageFallbacks(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_unique(array_filter(array_map(
                static fn ($item): string => self::normalizeMediaUrl(trim((string) $item)),
                $value
            ), static fn (string $url): bool => $url !== '' && self::isUsableMediaUrl($url))));
        }
        if (!is_string($value)) {
            return [];
        }
        $trimmed = trim($value);
        if ($trimmed === '') {
            return [];
        }
        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            return [];
        }

        return self::decodeStoredImageFallbacks($decoded);
    }

    /**
     * @param list<string> $fallbacks
     */
    public static function encodeStoredImageFallbacks(array $fallbacks): ?string
    {
        $clean = [];
        foreach ($fallbacks as $fallback) {
            $url = self::normalizeMediaUrl(trim((string) $fallback));
            if ($url !== '' && self::isUsableMediaUrl($url) && !in_array($url, $clean, true)) {
                $clean[] = $url;
            }
        }
        if ($clean === []) {
            return null;
        }

        return json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }

    /**
     * Single source of truth for frontend/admin/API game thumbnails.
     *
     * @param array<string, mixed> $row
     * @return array{cover: string, cover_fallbacks: list<string>, image_fallbacks: list<string>}
     */
    public static function hydrateGameMedia(array $row, ?string $lang = null, bool $probeCandidates = false): array
    {
        unset($probeCandidates);

        $storedFallbacks = self::decodeStoredImageFallbacks($row['image_fallbacks'] ?? null);
        if ($storedFallbacks === [] && !empty($row['cover_fallbacks']) && is_array($row['cover_fallbacks'])) {
            $storedFallbacks = self::decodeStoredImageFallbacks($row['cover_fallbacks']);
        }

        $storedCover = self::normalizeMediaUrl(trim((string) ($row['image_url'] ?? '')));
        if (!self::isUsableMediaUrl($storedCover) || self::looksLikeLocalizedJson($storedCover)) {
            $storedCover = '';
        }
        if ($storedCover === '') {
            $storedCover = self::normalizeMediaUrl(trim((string) ($row['cover'] ?? '')));
            if (!self::isUsableMediaUrl($storedCover) || self::looksLikeLocalizedJson($storedCover)) {
                $storedCover = '';
            }
        }

        if ($storedCover !== '') {
            if (self::isBrokenMarketingImageUrl($storedCover) && $storedFallbacks !== []) {
                foreach ($storedFallbacks as $candidate) {
                    if (!self::isBrokenMarketingImageUrl($candidate)) {
                        $storedCover = $candidate;
                        break;
                    }
                }
            }
            $fallbacks = $storedFallbacks !== [] ? $storedFallbacks : [$storedCover];
            if (!in_array($storedCover, $fallbacks, true)) {
                array_unshift($fallbacks, $storedCover);
            }

            $media = [
                'cover'           => $storedCover,
                'cover_fallbacks' => $fallbacks,
                'image_fallbacks' => $fallbacks,
            ];
            if (self::isEgtVipVendor(self::rowVendorCode($row))) {
                return self::forceGameMediaToPng($media);
            }

            return $media;
        }

        $media = self::resolveApiGameMedia($row, $lang);
        if (self::isEgtVipVendor(self::rowVendorCode($row))) {
            return self::forceGameMediaToPng($media);
        }

        return $media;
    }

    private static function rowVendorCode(array $row): string
    {
        $vendor = trim((string) ($row['vendor_code'] ?? ''));
        if ($vendor !== '') {
            return $vendor;
        }

        return trim((string) ($row['provider_code'] ?? ''));
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
        $media = self::hydrateGameMedia($row);
        $row['image_url'] = (string) ($media['cover'] ?? '');
        $row['banner'] = (string) ($media['cover'] ?? '');
        $row['cover'] = (string) ($media['cover'] ?? '');
        $row['image_fallbacks'] = $media['image_fallbacks'] ?? [];
        $row['cover_fallbacks'] = $media['cover_fallbacks'] ?? [];
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

    // ── Game Control API v1.0.0 ─────────────────────────────────────────────

    /**
     * @param array{vendor_code?:string,game_code?:string,currency_code?:string,vendorCode?:string,gameCode?:string,currencyCode?:string} $context
     * @return array{vendor_code: string, game_code: string, currency_code: string}
     */
    public static function normalizeGameControlContext(PDO $pdo, array $context): array
    {
        $cfg = self::config($pdo);
        $vendor = trim((string) ($context['vendor_code'] ?? $context['vendorCode'] ?? ''));
        $game = trim((string) ($context['game_code'] ?? $context['gameCode'] ?? ''));
        $currency = strtoupper(trim((string) ($context['currency_code'] ?? $context['currencyCode'] ?? ($cfg['currency'] ?? 'TRY'))));
        if ($currency === '') {
            $currency = 'TRY';
        }
        if ($vendor === '' || $game === '') {
            throw new RuntimeException('vendorCode ve gameCode zorunludur (Game Control API).');
        }
        return [
            'vendor_code'   => $vendor,
            'game_code'     => $game,
            'currency_code' => $currency,
        ];
    }

    /** @return array<string, string> category => value */
    public static function getAgentSettings(PDO $pdo, array $context = []): array
    {
        self::bootstrap($pdo);
        $out = [];
        foreach (self::AGENT_SETTING_CATEGORIES as $category) {
            $out[$category] = '';
        }
        try {
            if ($context !== []) {
                $ctx = self::normalizeGameControlContext($pdo, $context);
                $stmt = $pdo->prepare(
                    'SELECT category, setting_value FROM casino_aggregator_agent_settings
                     WHERE vendor_code = :v AND game_code = :g AND currency_code = :c AND setting_key = \'\''
                );
                $stmt->execute([
                    ':v' => $ctx['vendor_code'],
                    ':g' => $ctx['game_code'],
                    ':c' => $ctx['currency_code'],
                ]);
            } else {
                $stmt = $pdo->query(
                    'SELECT category, setting_value FROM casino_aggregator_agent_settings
                     WHERE setting_key = \'\' ORDER BY updated_at DESC, id DESC'
                );
            }
            foreach (($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : []) ?: [] as $row) {
                $category = (string) ($row['category'] ?? '');
                if ($category !== '' && array_key_exists($category, $out) && $out[$category] === '') {
                    $out[$category] = (string) ($row['setting_value'] ?? '');
                }
            }
        } catch (Throwable) {
        }
        return $out;
    }

    /**
     * ChangeAgentSetting for each category (Appendix 4.12).
     *
     * @param array<string, mixed> $data category => value
     * @return array{saved: int, api_ok: int, errors: list<string>, context: array<string,string>}
     */
    public static function setAgentSettings(PDO $pdo, array $data, bool $pushApi = true, array $context = []): array
    {
        self::bootstrap($pdo);
        $ctx = self::normalizeGameControlContext($pdo, $context !== [] ? $context : $data);
        $normalized = self::normalizeAgentSettingInput($data);
        $saved = 0;
        $apiOk = 0;
        $errors = [];
        $cfg = $pushApi ? self::configuredConfig($pdo) : [];

        $stmt = $pdo->prepare(
            'INSERT INTO casino_aggregator_agent_settings
                (vendor_code, game_code, currency_code, category, setting_key, setting_value, synced_at, created_at, updated_at)
             VALUES (:v, :g, :c, :cat, \'\', :val, NOW(), NOW(), NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), synced_at = NOW(), updated_at = NOW()'
        );

        foreach ($normalized as $category => $value) {
            $stmt->execute([
                ':v'   => $ctx['vendor_code'],
                ':g'   => $ctx['game_code'],
                ':c'   => $ctx['currency_code'],
                ':cat' => $category,
                ':val' => $value,
            ]);
            $saved++;

            if (!$pushApi) {
                continue;
            }
            try {
                $response = self::requestWithConfig($cfg, [
                    'method'       => 'ChangeAgentSetting',
                    'token'        => (string) $cfg['api_token'],
                    'agentCode'    => (string) $cfg['agent_code'],
                    'gameCode'     => $ctx['game_code'],
                    'currencyCode' => $ctx['currency_code'],
                    'vendorCode'   => $ctx['vendor_code'],
                    'category'     => $category,
                    'key'          => '',
                    'value'        => $value,
                ], 15);
                self::assertSuccess($response, 'ChangeAgentSetting:' . $category);
                $apiOk++;
            } catch (Throwable $e) {
                $errors[] = $category . ': ' . $e->getMessage();
            }
        }

        return ['saved' => $saved, 'api_ok' => $apiOk, 'errors' => $errors, 'context' => $ctx];
    }

    /**
     * @return array{updated: int, values: array<string, string>, errors: list<string>, context: array<string,string>}
     */
    public static function pullAgentSettings(PDO $pdo, array $context = []): array
    {
        self::bootstrap($pdo);
        $ctx = self::normalizeGameControlContext($pdo, $context);
        $cfg = self::configuredConfig($pdo);
        $updated = 0;
        $values = self::getAgentSettings($pdo, $ctx);
        $errors = [];

        foreach (self::AGENT_SETTING_CATEGORIES as $category) {
            try {
                $response = self::requestWithConfig($cfg, [
                    'method'       => 'GetAgentSetting',
                    'token'        => (string) $cfg['api_token'],
                    'agentCode'    => (string) $cfg['agent_code'],
                    'gameCode'     => $ctx['game_code'],
                    'currencyCode' => $ctx['currency_code'],
                    'vendorCode'   => $ctx['vendor_code'],
                    'category'     => $category,
                    'key'          => '',
                ], 15);
                self::assertSuccess($response, 'GetAgentSetting:' . $category);
                $remote = self::extractSettingValue($response, $category);
                if ($remote === null) {
                    continue;
                }
                $remote = self::normalizeSettingValue($category, $remote);
                $pdo->prepare(
                    'INSERT INTO casino_aggregator_agent_settings
                        (vendor_code, game_code, currency_code, category, setting_key, setting_value, synced_at, created_at, updated_at)
                     VALUES (:v, :g, :c, :cat, \'\', :val, NOW(), NOW(), NOW())
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), synced_at = NOW(), updated_at = NOW()'
                )->execute([
                    ':v'   => $ctx['vendor_code'],
                    ':g'   => $ctx['game_code'],
                    ':c'   => $ctx['currency_code'],
                    ':cat' => $category,
                    ':val' => $remote,
                ]);
                $values[$category] = $remote;
                $updated++;
            } catch (Throwable $e) {
                $errors[] = $category . ': ' . $e->getMessage();
            }
        }

        return ['updated' => $updated, 'values' => $values, 'errors' => $errors, 'context' => $ctx];
    }

    /** @return array<string, string> */
    public static function getUserSettings(PDO $pdo, string $userCode, array $context = []): array
    {
        self::bootstrap($pdo);
        $userCode = trim($userCode);
        $out = [];
        foreach (self::USER_SETTING_CATEGORIES as $category) {
            $out[$category] = '';
        }
        if ($userCode === '') {
            return $out;
        }
        try {
            if ($context !== []) {
                $ctx = self::normalizeGameControlContext($pdo, $context);
                $stmt = $pdo->prepare(
                    'SELECT category, setting_value FROM casino_aggregator_user_settings
                     WHERE user_code = :u AND vendor_code = :v AND game_code = :g AND currency_code = :c AND setting_key = \'\''
                );
                $stmt->execute([
                    ':u' => $userCode,
                    ':v' => $ctx['vendor_code'],
                    ':g' => $ctx['game_code'],
                    ':c' => $ctx['currency_code'],
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'SELECT category, setting_value FROM casino_aggregator_user_settings
                     WHERE user_code = :u AND setting_key = \'\' ORDER BY updated_at DESC, id DESC'
                );
                $stmt->execute([':u' => $userCode]);
            }
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $category = (string) ($row['category'] ?? '');
                if ($category !== '' && array_key_exists($category, $out) && $out[$category] === '') {
                    $out[$category] = (string) ($row['setting_value'] ?? '');
                }
            }
        } catch (Throwable) {
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{saved: int, api_ok: int, errors: list<string>, user_code: string, user_id: ?int, context: array<string,string>}
     */
    public static function setUserSettings(PDO $pdo, string $userCode, array $data, bool $pushApi = true, array $context = []): array
    {
        self::bootstrap($pdo);
        $resolved = self::resolveUserCode($pdo, $userCode);
        if ($resolved === null) {
            throw new RuntimeException('Kullanıcı bulunamadı: ' . $userCode);
        }
        $code = $resolved['user_code'];
        $userId = $resolved['user_id'];
        $ctx = self::normalizeGameControlContext($pdo, $context !== [] ? $context : $data);
        $normalized = self::normalizeUserSettingInput($data);
        $saved = 0;
        $apiOk = 0;
        $errors = [];
        $cfg = $pushApi ? self::configuredConfig($pdo) : [];

        $stmt = $pdo->prepare(
            'INSERT INTO casino_aggregator_user_settings
                (user_id, user_code, vendor_code, game_code, currency_code, category, setting_key, setting_value, synced_at, created_at, updated_at)
             VALUES (:uid, :ucode, :v, :g, :c, :cat, \'\', :val, NOW(), NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                setting_value = VALUES(setting_value),
                synced_at = NOW(),
                updated_at = NOW()'
        );

        foreach ($normalized as $category => $value) {
            $stmt->execute([
                ':uid'   => $userId,
                ':ucode' => $code,
                ':v'     => $ctx['vendor_code'],
                ':g'     => $ctx['game_code'],
                ':c'     => $ctx['currency_code'],
                ':cat'   => $category,
                ':val'   => $value,
            ]);
            $saved++;

            if (!$pushApi) {
                continue;
            }
            try {
                $response = self::requestWithConfig($cfg, [
                    'method'       => 'ChangeUserSetting',
                    'token'        => (string) $cfg['api_token'],
                    'agentCode'    => (string) $cfg['agent_code'],
                    'userCode'     => $code,
                    'gameCode'     => $ctx['game_code'],
                    'currencyCode' => $ctx['currency_code'],
                    'vendorCode'   => $ctx['vendor_code'],
                    'category'     => $category,
                    'key'          => '',
                    'value'        => $value,
                ], 15);
                self::assertSuccess($response, 'ChangeUserSetting:' . $category);
                $apiOk++;
            } catch (Throwable $e) {
                $errors[] = $category . ': ' . $e->getMessage();
            }
        }

        return [
            'saved'     => $saved,
            'api_ok'    => $apiOk,
            'errors'    => $errors,
            'user_code' => $code,
            'user_id'   => $userId,
            'context'   => $ctx,
        ];
    }

    /**
     * @return array{updated: int, values: array<string, string>, errors: list<string>, user_code: string, user_id: ?int, context: array<string,string>}
     */
    public static function pullUserSettings(PDO $pdo, string $userCode, array $context = []): array
    {
        self::bootstrap($pdo);
        $resolved = self::resolveUserCode($pdo, $userCode);
        if ($resolved === null) {
            throw new RuntimeException('Kullanıcı bulunamadı: ' . $userCode);
        }
        $code = $resolved['user_code'];
        $userId = $resolved['user_id'];
        $ctx = self::normalizeGameControlContext($pdo, $context);
        $cfg = self::configuredConfig($pdo);
        $updated = 0;
        $values = self::getUserSettings($pdo, $code, $ctx);
        $errors = [];

        foreach (self::USER_SETTING_CATEGORIES as $category) {
            try {
                $response = self::requestWithConfig($cfg, [
                    'method'       => 'GetUserSetting',
                    'token'        => (string) $cfg['api_token'],
                    'agentCode'    => (string) $cfg['agent_code'],
                    'userCode'     => $code,
                    'gameCode'     => $ctx['game_code'],
                    'currencyCode' => $ctx['currency_code'],
                    'vendorCode'   => $ctx['vendor_code'],
                    'category'     => $category,
                    'key'          => '',
                ], 15);
                self::assertSuccess($response, 'GetUserSetting:' . $category);
                $remote = self::extractSettingValue($response, $category);
                if ($remote === null) {
                    continue;
                }
                $remote = self::normalizeSettingValue($category, $remote);
                $pdo->prepare(
                    'INSERT INTO casino_aggregator_user_settings
                        (user_id, user_code, vendor_code, game_code, currency_code, category, setting_key, setting_value, synced_at, created_at, updated_at)
                     VALUES (:uid, :ucode, :v, :g, :c, :cat, \'\', :val, NOW(), NOW(), NOW())
                     ON DUPLICATE KEY UPDATE
                        user_id = VALUES(user_id),
                        setting_value = VALUES(setting_value),
                        synced_at = NOW(),
                        updated_at = NOW()'
                )->execute([
                    ':uid'   => $userId,
                    ':ucode' => $code,
                    ':v'     => $ctx['vendor_code'],
                    ':g'     => $ctx['game_code'],
                    ':c'     => $ctx['currency_code'],
                    ':cat'   => $category,
                    ':val'   => $remote,
                ]);
                $values[$category] = $remote;
                $updated++;
            } catch (Throwable $e) {
                $errors[] = $category . ': ' . $e->getMessage();
            }
        }

        return [
            'updated'   => $updated,
            'values'    => $values,
            'errors'    => $errors,
            'user_code' => $code,
            'user_id'   => $userId,
            'context'   => $ctx,
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function recentUserSettings(PDO $pdo, int $limit = 50): array
    {
        self::bootstrap($pdo);
        $limit = max(1, min(200, $limit));
        try {
            $sql = "SELECT s.user_id, s.user_code, s.vendor_code, s.game_code, s.currency_code,
                           s.category, s.setting_key, s.setting_value, s.synced_at, s.updated_at,
                           u.username
                    FROM casino_aggregator_user_settings s
                    LEFT JOIN users u ON u.id = s.user_id
                    ORDER BY s.updated_at DESC, s.id DESC
                    LIMIT {$limit}";
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /** @return list<array{vendor_code:string,vendor_name:string}> */
    public static function listVendors(PDO $pdo, bool $activeOnly = true): array
    {
        self::bootstrap($pdo);
        try {
            $sql = 'SELECT vendor_code, vendor_name FROM casino_aggregator_vendors';
            if ($activeOnly) {
                $sql .= ' WHERE is_active = 1';
            }
            $sql .= ' ORDER BY vendor_name ASC, vendor_code ASC';
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /** @return list<array{vendor_code:string,game_code:string,game_name:string}> */
    public static function listGamesForVendor(PDO $pdo, string $vendorCode, int $limit = 500): array
    {
        self::bootstrap($pdo);
        $vendorCode = trim($vendorCode);
        if ($vendorCode === '') {
            return [];
        }
        $limit = max(1, min(2000, $limit));
        try {
            $stmt = $pdo->prepare(
                "SELECT vendor_code, game_code, game_name FROM casino_aggregator_games
                 WHERE vendor_code = :v AND is_active = 1
                 ORDER BY game_name ASC LIMIT {$limit}"
            );
            $stmt->execute([':v' => $vendorCode]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /** GetCurrentPlayers */
    public static function getCurrentPlayers(PDO $pdo, string $vendorCode): array
    {
        self::bootstrap($pdo);
        $vendorCode = trim($vendorCode);
        if ($vendorCode === '') {
            throw new RuntimeException('vendorCode zorunludur.');
        }
        $cfg = self::configuredConfig($pdo);
        $response = self::requestWithConfig($cfg, [
            'method'     => 'GetCurrentPlayers',
            'token'      => (string) $cfg['api_token'],
            'agentCode'  => (string) $cfg['agent_code'],
            'vendorCode' => $vendorCode,
        ], 20);
        self::assertSuccess($response, 'GetCurrentPlayers');
        $players = is_array($response['playerInfos'] ?? null) ? $response['playerInfos'] : [];
        return ['players' => $players, 'raw' => $response];
    }

    /** GetCallList */
    public static function getCallList(PDO $pdo, string $vendorCode, string $gameCode, string $callType): array
    {
        self::bootstrap($pdo);
        $cfg = self::configuredConfig($pdo);
        $response = self::requestWithConfig($cfg, [
            'method'     => 'GetCallList',
            'token'      => (string) $cfg['api_token'],
            'agentCode'  => (string) $cfg['agent_code'],
            'vendorCode' => trim($vendorCode),
            'gameCode'   => trim($gameCode),
            'callType'   => trim($callType),
        ], 20);
        self::assertSuccess($response, 'GetCallList');
        $calls = is_array($response['calls'] ?? null) ? $response['calls'] : [];
        $normalized = [];
        foreach ($calls as $call) {
            if (is_numeric($call)) {
                $normalized[] = (float) $call;
            }
        }
        return ['calls' => $normalized, 'raw' => $response, 'call_type' => trim($callType)];
    }

    /**
     * Normalize GetCurrentPlayers.requestType → GetCallList.callType.
     * Spec examples use "0"/"1"; vendors may send "action=doSpin".
     */
    public static function normalizeCallType(string $requestType): string
    {
        $t = trim($requestType);
        if ($t === '0' || $t === '1') {
            return $t;
        }
        $lower = strtolower($t);
        if (str_contains($lower, 'free')) {
            return '1';
        }
        return '0';
    }

    /**
     * Try GetCallList with normalized type, then raw, then 0/1 fallbacks.
     *
     * @return array{calls: list<float>, call_type: string, error: string}
     */
    public static function resolveCallListOptions(
        PDO $pdo,
        string $vendorCode,
        string $gameCode,
        string $requestType
    ): array {
        $candidates = [];
        $normalized = self::normalizeCallType($requestType);
        $raw = trim($requestType);
        foreach ([$normalized, $raw, '0', '1'] as $type) {
            if ($type === '' || in_array($type, $candidates, true)) {
                continue;
            }
            $candidates[] = $type;
        }

        $lastError = '';
        foreach ($candidates as $type) {
            try {
                $result = self::getCallList($pdo, $vendorCode, $gameCode, $type);
                $calls = is_array($result['calls'] ?? null) ? $result['calls'] : [];
                if ($calls !== []) {
                    return [
                        'calls'     => $calls,
                        'call_type' => $type,
                        'error'     => '',
                    ];
                }
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        return [
            'calls'     => [],
            'call_type' => $normalized,
            'error'     => $lastError !== '' ? $lastError : 'GetCallList boş döndü.',
        ];
    }

    /** CallApply */
    public static function callApply(PDO $pdo, array $input): array
    {
        self::bootstrap($pdo);
        $cfg = self::configuredConfig($pdo);
        $moneyAmount = (float) ($input['moneyAmount'] ?? $input['money_amount'] ?? $input['calledMoney'] ?? $input['called_money'] ?? 0);
        $payload = [
            'method'       => 'CallApply',
            'token'        => (string) $cfg['api_token'],
            'agentCode'    => (string) $cfg['agent_code'],
            'userCode'     => trim((string) ($input['userCode'] ?? $input['user_code'] ?? '')),
            'gameCode'     => trim((string) ($input['gameCode'] ?? $input['game_code'] ?? '')),
            'currencyCode' => strtoupper(trim((string) ($input['currencyCode'] ?? $input['currency_code'] ?? ($cfg['currency'] ?? 'TRY')))),
            'vendorCode'   => trim((string) ($input['vendorCode'] ?? $input['vendor_code'] ?? '')),
            'callRtp'      => (float) ($input['callRtp'] ?? $input['call_rtp'] ?? 0),
            'betAmount'    => (float) ($input['betAmount'] ?? $input['bet_amount'] ?? 0),
            'callType'     => trim((string) ($input['callType'] ?? $input['call_type'] ?? '0')),
        ];
        foreach (['userCode', 'gameCode', 'vendorCode'] as $req) {
            if ($payload[$req] === '') {
                throw new RuntimeException($req . ' zorunludur.');
            }
        }
        if ($payload['callRtp'] <= 0) {
            throw new RuntimeException('callRtp zorunludur.');
        }
        if ($payload['betAmount'] < 0) {
            throw new RuntimeException('betAmount geçersiz.');
        }
        if ($moneyAmount <= 0) {
            throw new RuntimeException('Kullanıcıya verilecek kazanç miktarı zorunludur.');
        }
        $response = self::requestWithConfig($cfg, $payload, 20);
        self::assertSuccess($response, 'CallApply');
        $calledMoney = (float) ($response['calledMoney'] ?? 0);
        if ($calledMoney <= 0) {
            $calledMoney = $moneyAmount;
        }
        self::logCallAction($pdo, 'CallApply', $payload, $response, (int) ($response['callId'] ?? 0) ?: null, $calledMoney);
        return [
            'called_money' => $calledMoney,
            'call_id'      => (int) ($response['callId'] ?? 0),
            'raw'          => $response,
        ];
    }

    /** CallCancel */
    public static function callCancel(PDO $pdo, array $input): array
    {
        self::bootstrap($pdo);
        $cfg = self::configuredConfig($pdo);
        $payload = [
            'method'       => 'CallCancel',
            'token'        => (string) $cfg['api_token'],
            'agentCode'    => (string) $cfg['agent_code'],
            'userCode'     => trim((string) ($input['userCode'] ?? $input['user_code'] ?? '')),
            'gameCode'     => trim((string) ($input['gameCode'] ?? $input['game_code'] ?? '')),
            'currencyCode' => strtoupper(trim((string) ($input['currencyCode'] ?? $input['currency_code'] ?? ($cfg['currency'] ?? 'TRY')))),
            'vendorCode'   => trim((string) ($input['vendorCode'] ?? $input['vendor_code'] ?? '')),
            'callRtp'      => (float) ($input['callRtp'] ?? $input['call_rtp'] ?? 0),
            'betAmount'    => (float) ($input['betAmount'] ?? $input['bet_amount'] ?? 0),
            'callId'       => (int) ($input['callId'] ?? $input['call_id'] ?? 0),
        ];
        if ($payload['callId'] <= 0) {
            throw new RuntimeException('callId zorunludur.');
        }
        $response = self::requestWithConfig($cfg, $payload, 20);
        self::assertSuccess($response, 'CallCancel');
        self::logCallAction($pdo, 'CallCancel', $payload, $response, $payload['callId'], (float) ($response['canceledMoney'] ?? 0));
        return [
            'canceled_money' => (float) ($response['canceledMoney'] ?? 0),
            'raw'            => $response,
        ];
    }

    /** GetCallHistory */
    public static function getCallHistory(PDO $pdo, array $input): array
    {
        self::bootstrap($pdo);
        $cfg = self::configuredConfig($pdo);
        $payload = [
            'method'     => 'GetCallHistory',
            'token'      => (string) $cfg['api_token'],
            'agentCode'  => (string) $cfg['agent_code'],
            'vendorCode' => trim((string) ($input['vendorCode'] ?? $input['vendor_code'] ?? '')),
            'startTime'  => trim((string) ($input['startTime'] ?? $input['start_time'] ?? '')),
            'endTime'    => trim((string) ($input['endTime'] ?? $input['end_time'] ?? '')),
            'offset'     => max(0, (int) ($input['offset'] ?? 0)),
            'limit'      => max(1, min(200, (int) ($input['limit'] ?? 10))),
        ];
        if ($payload['vendorCode'] === '' || $payload['startTime'] === '' || $payload['endTime'] === '') {
            throw new RuntimeException('vendorCode, startTime ve endTime zorunludur (UTC+0).');
        }
        $response = self::requestWithConfig($cfg, $payload, 25);
        self::assertSuccess($response, 'GetCallHistory');
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        return ['data' => $data, 'raw' => $response];
    }

    /** @return list<array<string, mixed>> */
    public static function recentCallLogs(PDO $pdo, int $limit = 40): array
    {
        self::bootstrap($pdo);
        $limit = max(1, min(200, $limit));
        try {
            return $pdo->query(
                "SELECT * FROM casino_aggregator_call_logs ORDER BY id DESC LIMIT {$limit}"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /** @param array<string, mixed> $request @param array<string, mixed> $response */
    private static function logCallAction(
        PDO $pdo,
        string $action,
        array $request,
        array $response,
        ?int $callId,
        float $money
    ): void {
        try {
            $pdo->prepare(
                'INSERT INTO casino_aggregator_call_logs
                    (action, user_code, vendor_code, game_code, call_id, call_rtp, bet_amount, money_amount,
                     status_code, request_payload, response_payload, created_at)
                 VALUES (:a, :u, :v, :g, :cid, :rtp, :bet, :money, :st, :req, :res, NOW())'
            )->execute([
                ':a'     => $action,
                ':u'     => (string) ($request['userCode'] ?? ''),
                ':v'     => (string) ($request['vendorCode'] ?? ''),
                ':g'     => (string) ($request['gameCode'] ?? ''),
                ':cid'   => $callId,
                ':rtp'   => isset($request['callRtp']) ? (float) $request['callRtp'] : null,
                ':bet'   => isset($request['betAmount']) ? (float) $request['betAmount'] : null,
                ':money' => $money,
                ':st'    => isset($response['status']) ? (int) $response['status'] : null,
                ':req'   => json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':res'   => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable) {
        }
    }

    /** @return array{user_code: string, user_id: ?int}|null */
    public static function resolveUserCode(PDO $pdo, string $input): ?array
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }
        try {
            if (ctype_digit($input)) {
                $stmt = $pdo->prepare('SELECT id, username FROM users WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => (int) $input]);
            } else {
                $stmt = $pdo->prepare('SELECT id, username FROM users WHERE username = :u LIMIT 1');
                $stmt->execute([':u' => $input]);
            }
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return null;
            }
            return [
                'user_code' => (string) (int) $row['id'],
                'user_id'   => (int) $row['id'],
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Map aggregator userCode values to local profile fields (name/surname/username).
     *
     * @param list<string|int> $userCodes
     * @return array<string, array{id:int,username:string,name:string,surname:string,full_name:string}>
     */
    public static function mapLocalUsersByCodes(PDO $pdo, array $userCodes): array
    {
        $ids = [];
        $usernames = [];
        foreach ($userCodes as $code) {
            $code = trim((string) $code);
            if ($code === '') {
                continue;
            }
            if (ctype_digit($code)) {
                $ids[(int) $code] = true;
            } else {
                $usernames[$code] = true;
            }
        }
        if ($ids === [] && $usernames === []) {
            return [];
        }

        $rows = [];
        try {
            if ($ids !== []) {
                $idList = array_keys($ids);
                $placeholders = implode(',', array_fill(0, count($idList), '?'));
                $stmt = $pdo->prepare(
                    "SELECT id, username, name, surname FROM users WHERE id IN ({$placeholders})"
                );
                $stmt->execute($idList);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if (is_array($row)) {
                        $rows[] = $row;
                    }
                }
            }
            if ($usernames !== []) {
                $nameList = array_keys($usernames);
                $placeholders = implode(',', array_fill(0, count($nameList), '?'));
                $stmt = $pdo->prepare(
                    "SELECT id, username, name, surname FROM users WHERE username IN ({$placeholders})"
                );
                $stmt->execute($nameList);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if (is_array($row)) {
                        $rows[] = $row;
                    }
                }
            }
        } catch (Throwable) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $username = trim((string) ($row['username'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $surname = trim((string) ($row['surname'] ?? ''));
            $fullName = trim($name . ' ' . $surname);
            $profile = [
                'id'        => $id,
                'username'  => $username,
                'name'      => $name,
                'surname'   => $surname,
                'full_name' => $fullName !== '' ? $fullName : ($username !== '' ? $username : (string) $id),
            ];
            $map[(string) $id] = $profile;
            if ($username !== '') {
                $map[$username] = $profile;
            }
        }

        return $map;
    }

    /**
     * @param list<array<string, mixed>> $players
     * @return list<array<string, mixed>>
     */
    public static function attachLocalUserProfiles(PDO $pdo, array $players): array
    {
        $codes = [];
        foreach ($players as $player) {
            if (!is_array($player)) {
                continue;
            }
            $code = trim((string) ($player['userCode'] ?? $player['user_code'] ?? ''));
            if ($code !== '') {
                $codes[] = $code;
            }
        }
        $map = self::mapLocalUsersByCodes($pdo, $codes);
        foreach ($players as $idx => $player) {
            if (!is_array($player)) {
                continue;
            }
            $code = trim((string) ($player['userCode'] ?? $player['user_code'] ?? ''));
            $profile = $map[$code] ?? null;
            $players[$idx]['_local_user'] = is_array($profile) ? $profile : null;
        }

        return $players;
    }

    /**
     * @param list<array{vendor?:string,vendor_code?:string,vendorCode?:string,game?:string,game_code?:string,gameCode?:string}> $pairs
     * @return array<string, array{vendor_code:string,game_code:string,game_name:string}>
     */
    public static function mapLocalGamesByPairs(PDO $pdo, array $pairs): array
    {
        $wanted = [];
        foreach ($pairs as $pair) {
            if (!is_array($pair)) {
                continue;
            }
            $vendor = trim((string) ($pair['vendor_code'] ?? $pair['vendorCode'] ?? $pair['vendor'] ?? ''));
            $game = trim((string) ($pair['game_code'] ?? $pair['gameCode'] ?? $pair['game'] ?? ''));
            if ($vendor === '' || $game === '') {
                continue;
            }
            $wanted[$vendor . "\0" . $game] = [$vendor, $game];
        }
        if ($wanted === []) {
            return [];
        }

        $map = [];
        try {
            $vendors = array_values(array_unique(array_map(static fn (array $p): string => $p[0], $wanted)));
            $placeholders = implode(',', array_fill(0, count($vendors), '?'));
            $stmt = $pdo->prepare(
                "SELECT vendor_code, game_code, game_name
                 FROM casino_aggregator_games
                 WHERE vendor_code IN ({$placeholders})"
            );
            $stmt->execute($vendors);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!is_array($row)) {
                    continue;
                }
                $vendor = trim((string) ($row['vendor_code'] ?? ''));
                $game = trim((string) ($row['game_code'] ?? ''));
                if ($vendor === '' || $game === '' || !isset($wanted[$vendor . "\0" . $game])) {
                    continue;
                }
                $name = self::resolveLocalizedLabel($row['game_name'] ?? $game) ?: $game;
                $map[$vendor . '|' . $game] = [
                    'vendor_code' => $vendor,
                    'game_code'   => $game,
                    'game_name'   => $name,
                ];
            }
        } catch (Throwable) {
            return [];
        }

        return $map;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function attachLocalGameNames(PDO $pdo, array $rows): array
    {
        $pairs = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $pairs[] = [
                'vendor_code' => (string) ($row['vendorCode'] ?? $row['vendor_code'] ?? ''),
                'game_code'   => (string) ($row['gameCode'] ?? $row['game_code'] ?? ''),
            ];
        }
        $map = self::mapLocalGamesByPairs($pdo, $pairs);
        foreach ($rows as $idx => $row) {
            if (!is_array($row)) {
                continue;
            }
            $vendor = trim((string) ($row['vendorCode'] ?? $row['vendor_code'] ?? ''));
            $game = trim((string) ($row['gameCode'] ?? $row['game_code'] ?? ''));
            $key = $vendor . '|' . $game;
            $rows[$idx]['_local_game'] = $map[$key] ?? null;
            if (isset($map[$key]['game_name'])) {
                $rows[$idx]['_game_name'] = $map[$key]['game_name'];
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private static function normalizeAgentSettingInput(array $data): array
    {
        $out = [];
        foreach (self::AGENT_SETTING_CATEGORIES as $category) {
            if (!array_key_exists($category, $data) && !array_key_exists(strtolower($category), $data)) {
                continue;
            }
            $raw = $data[$category] ?? $data[strtolower($category)] ?? '';
            if (is_bool($raw)) {
                $raw = $raw ? '1' : '0';
            }
            $out[$category] = self::normalizeSettingValue($category, (string) $raw);
        }
        if ($out === []) {
            throw new RuntimeException('Kaydedilecek agent ayarı yok.');
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private static function normalizeUserSettingInput(array $data): array
    {
        $out = [];
        foreach (self::USER_SETTING_CATEGORIES as $category) {
            if (!array_key_exists($category, $data) && !array_key_exists(strtolower($category), $data)) {
                continue;
            }
            $raw = $data[$category] ?? $data[strtolower($category)] ?? '';
            $out[$category] = self::normalizeSettingValue($category, (string) $raw);
        }
        if ($out === []) {
            throw new RuntimeException('Kaydedilecek kullanıcı ayarı yok.');
        }
        return $out;
    }

    private static function normalizeSettingValue(string $category, string $value): string
    {
        $value = trim($value);
        if (in_array($category, ['HideRoundId', 'HideTournament', 'HideBadge'], true)) {
            return in_array($value, ['1', 'true', 'on', 'yes'], true) ? '1' : '0';
        }
        if (in_array($category, ['LowRtp', 'HighRtp', 'TargetRtp'], true)) {
            if ($value === '') {
                return '';
            }
            if (!is_numeric($value)) {
                throw new RuntimeException($category . ' sayısal olmalıdır (0–1).');
            }
            $num = (float) $value;
            if ($num < 0 || $num > 1) {
                throw new RuntimeException($category . ' 0 ile 1 arasında olmalıdır.');
            }
            return rtrim(rtrim(number_format($num, 4, '.', ''), '0'), '.') ?: '0';
        }
        if ($category === 'RoundKey') {
            $parts = preg_split('/\s*,\s*/', $value) ?: [];
            $clean = [];
            foreach ($parts as $part) {
                $part = trim((string) $part);
                if ($part !== '') {
                    $clean[] = $part;
                }
            }
            return implode(',', $clean);
        }
        return $value;
    }

    /** @param array<string, mixed> $response */
    private static function extractSettingValue(array $response, string $category): ?string
    {
        if (array_key_exists('value', $response) && $response['value'] !== null) {
            return trim((string) $response['value']);
        }
        foreach (['settingValue', 'setting_value'] as $field) {
            if (array_key_exists($field, $response) && $response[$field] !== null && $response[$field] !== '') {
                return trim((string) $response[$field]);
            }
        }
        $map = self::extractSettingMap($response);
        if (array_key_exists($category, $map)) {
            return $map[$category];
        }
        return null;
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, string>
     */
    private static function extractSettingMap(array $response): array
    {
        $candidates = [
            $response['settings'] ?? null,
            $response['setting'] ?? null,
            $response['data'] ?? null,
            $response['agentSetting'] ?? null,
            $response['userSetting'] ?? null,
        ];
        $map = [];
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            if (array_is_list($candidate)) {
                foreach ($candidate as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $k = trim((string) ($row['category'] ?? $row['key'] ?? $row['setting_key'] ?? ''));
                    if ($k === '') {
                        continue;
                    }
                    $map[$k] = trim((string) ($row['value'] ?? $row['setting_value'] ?? ''));
                }
                continue;
            }
            foreach ($candidate as $k => $v) {
                if (!is_string($k) || is_array($v)) {
                    continue;
                }
                $map[$k] = trim((string) $v);
            }
        }
        return $map;
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
        $status = (int) ($response['status'] ?? -1);
        if ($status === 0) {
            return;
        }
        $msg = trim((string) ($response['msg'] ?? ''));
        if ($msg === '' && isset(self::RESPONSE_CODES[$status])) {
            $msg = self::RESPONSE_CODES[$status]['msg'];
        }
        if ($msg === '') {
            $msg = 'API hatası';
        }
        throw new RuntimeException($context . ': ' . $msg . ' (status ' . $status . ')');
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
        if ((int) ($user['banned'] ?? 0) === 1) {
            return ['status' => 6, 'msg' => 'BLOCK_USER', '__user_id' => (int) $user['id']];
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
        if ((int) ($user['banned'] ?? 0) === 1) {
            return ['status' => 6, 'msg' => 'BLOCK_USER', '__user_id' => (int) $user['id']];
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
            $stmt = $pdo->prepare('SELECT id, username, balance, banned FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
            $stmt->execute([':id' => $userId]);
            $locked = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($locked)) {
                $pdo->rollBack();
                return ['status' => 5, 'msg' => 'INVALID_USER', '__user_id' => $userId];
            }
            if ((int) ($locked['banned'] ?? 0) === 1) {
                $pdo->rollBack();
                return ['status' => 6, 'msg' => 'BLOCK_USER', '__user_id' => $userId];
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
            $stmt = $pdo->prepare("SELECT id, username, balance, banned FROM users WHERE {$column} = :v LIMIT 1");
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
