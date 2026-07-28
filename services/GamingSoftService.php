<?php

declare(strict_types=1);

/**
 * Gaming Soft / GSC+ API v2.0.6 — seamless wallet + operator APIs.
 *
 * Callback base: /api/v2/gamingsoft-wallet
 *   POST .../v1/api/seamless/balance|withdraw|deposit|pushbetdata
 *
 * Operator API (outbound):
 *   launch-game, available-products, provider-games, ...
 */
final class GamingSoftService
{
    public const GAME_ID_PREFIX = 'gamingsoft:';

    /** Product that pays out via pushbetdata instead of /deposit. */
    private const WBET_PRODUCT_CODE = 1040;

    private static bool $schemaBootstrapped = false;

    /** @var array<string, mixed> */
    private static array $cachedConfig = [];

    /** Currencies where provider amount is scaled (provider unit → real money divisor). */
    private const CURRENCY_RATIOS = [
        'BDT2' => 1000, 'BRL2' => 1000, 'CDF2' => 1000, 'CNY2' => 1000, 'COP2' => 1000,
        'EUR2' => 1000, 'HKD2' => 1000, 'IDR2' => 1000, 'IDR3' => 100, 'INR2' => 1000,
        'IRR2' => 1000, 'JPY2' => 1000, 'KHR2' => 1000, 'KRW2' => 1000, 'LAK2' => 1000,
        'LBP2' => 1000, 'MAD2' => 1000, 'MMK2' => 1000, 'MMK3' => 100, 'MNT2' => 1000,
        'MXN2' => 1000, 'MYR2' => 1000, 'MYR3' => 100, 'NGN2' => 1000, 'NPR2' => 1000,
        'PHP2' => 1000, 'PKR2' => 1000, 'PYG2' => 1000, 'SGD2' => 1000, 'THB2' => 1000,
        'TRY2' => 1000, 'TWD2' => 1000, 'TWD5' => 130, 'TZS2' => 1000, 'UGX2' => 1000, 'USD2' => 1000,
        'USDT2' => 1000, 'UZS2' => 1000, 'VND2' => 1000, 'VND3' => 100,
    ];

    private const LANG_MAP = [
        'en' => 0, 'zh-tw' => 1, 'zh-hant' => 1, 'zh' => 2, 'zh-cn' => 2, 'zh-hans' => 2,
        'th' => 3, 'id' => 4, 'in' => 4, 'ja' => 5, 'ko' => 6, 'vi' => 7, 'de' => 8,
        'es' => 9, 'fr' => 10, 'ru' => 11, 'pt' => 12, 'my' => 13, 'da' => 14, 'fi' => 15,
        'it' => 16, 'nl' => 17, 'no' => 18, 'pl' => 19, 'ro' => 20, 'sv' => 21, 'tr' => 0,
        'uk' => 31, 'hi' => 39, 'ms' => 36,
    ];

    public const STAGING_OPERATOR_CODE = 'VGY1';
    public const STAGING_API_BASE_URL = 'https://staging.gsimw.com';
    public const STAGING_SITE_ENDPOINT = 'https://admin.vegasroyalspin.com';
    public const STAGING_CURRENCY = 'IDR';
    /** Site member wallet currency (display/deposit currency). */
    public const SITE_WALLET_CURRENCY = 'TRY';
    /** Default TRY→IDR multiplier for VGY1 staging when site wallet is TRY but GSC+ game wallet is IDR. */
    private const DEFAULT_TRY_TO_IDR_RATE = 500.0;
    /** Staging currencies enabled by GSC+ for VGY1. */
    public const STAGING_CURRENCIES = ['IDR', 'IDR2', 'CNY', 'VND', 'VND2'];

    public static function bootstrap(PDO $pdo): void
    {
        if (self::$schemaBootstrapped) {
            return;
        }
        $migration = dirname(__DIR__) . '/database/migrations/2026_07_28_100000_create_gamingsoft_tables.php';
        if (!is_readable($migration)) {
            $migration = dirname(__DIR__) . '/admin/database/migrations/2026_07_28_100000_create_gamingsoft_tables.php';
        }
        if (!is_readable($migration)) {
            throw new RuntimeException('GamingSoft migration dosyası bulunamadı.');
        }
        $runner = require $migration;
        if (is_callable($runner)) {
            $runner($pdo);
        }
        $upgrade = dirname(__DIR__) . '/database/migrations/2026_07_28_110000_gamingsoft_product_game_type_unique.php';
        if (!is_readable($upgrade)) {
            $upgrade = dirname(__DIR__) . '/admin/database/migrations/2026_07_28_110000_gamingsoft_product_game_type_unique.php';
        }
        if (is_readable($upgrade)) {
            $upgradeRunner = require $upgrade;
            if (is_callable($upgradeRunner)) {
                $upgradeRunner($pdo);
            }
        }
        self::ensureStagingDefaults($pdo);
        self::ensureSchemaUpgrades($pdo);
        self::$schemaBootstrapped = true;
    }

    private static function ensureSchemaUpgrades(PDO $pdo): void
    {
        try {
            $pdo->exec(
                'ALTER TABLE gamingsoft_config
                 ADD COLUMN IF NOT EXISTS try_to_idr_rate DECIMAL(12,4) NOT NULL DEFAULT 500.0000'
            );
        } catch (Throwable) {
            try {
                $cols = $pdo->query("SHOW COLUMNS FROM gamingsoft_config LIKE 'try_to_idr_rate'")?->fetchAll(PDO::FETCH_ASSOC) ?: [];
                if ($cols === []) {
                    $pdo->exec(
                        'ALTER TABLE gamingsoft_config
                         ADD COLUMN try_to_idr_rate DECIMAL(12,4) NOT NULL DEFAULT 500.0000'
                    );
                }
            } catch (Throwable) {
            }
        }

        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS gamingsoft_member_accounts (
                    user_id          INT UNSIGNED NOT NULL,
                    member_account   VARCHAR(50) NOT NULL,
                    launch_password  CHAR(32) NOT NULL,
                    created_at       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at       TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (user_id),
                    UNIQUE KEY uniq_gs_member_account (member_account)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable) {
        }
    }

    /**
     * Apply VGY1 staging credentials when config row is still empty.
     * Secret can also be provided via GAMINGSOFT_SECRET_KEY env.
     */
    private static function ensureStagingDefaults(PDO $pdo): void
    {
        try {
            $row = $pdo->query('SELECT * FROM gamingsoft_config WHERE id = 1 LIMIT 1')?->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return;
            }

            $envSecret = trim((string) (getenv('GAMINGSOFT_SECRET_KEY') ?: ''));
            $stagingSecret = $envSecret !== '' ? $envSecret : 'zS5CzH7U224nMVgMaghYsY';

            $operator = trim((string) ($row['operator_code'] ?? ''));
            $secret = trim((string) ($row['secret_key'] ?? ''));
            $apiBase = trim((string) ($row['api_base_url'] ?? ''));
            $site = trim((string) ($row['site_endpoint'] ?? ''));
            $currency = strtoupper(trim((string) ($row['currency'] ?? '')));

            // Only seed when not yet configured (empty operator or empty secret).
            if ($operator !== '' && $secret !== '') {
                return;
            }

            $pdo->prepare(
                'UPDATE gamingsoft_config SET
                    operator_code = :op,
                    secret_key = :sk,
                    api_base_url = :api,
                    site_endpoint = :site,
                    currency = :cur,
                    language_code = :lang,
                    channel_code = :ch,
                    is_active = 1
                 WHERE id = 1'
            )->execute([
                ':op'   => $operator !== '' ? $operator : self::STAGING_OPERATOR_CODE,
                ':sk'   => $secret !== '' ? $secret : $stagingSecret,
                ':api'  => $apiBase !== '' ? rtrim($apiBase, '/') : self::STAGING_API_BASE_URL,
                ':site' => $site !== '' ? rtrim($site, '/') : self::STAGING_SITE_ENDPOINT,
                ':cur'  => $currency !== '' && $currency !== 'TRY' ? $currency : self::STAGING_CURRENCY,
                ':lang' => (int) ($row['language_code'] ?? 0),
                ':ch'   => trim((string) ($row['channel_code'] ?? '')) !== ''
                    ? trim((string) $row['channel_code'])
                    : 'gscp',
            ]);
        } catch (Throwable $e) {
            error_log('GamingSoft staging defaults: ' . $e->getMessage());
        }
    }

    public static function config(PDO $pdo): array
    {
        try {
            self::bootstrap($pdo);
            $row = $pdo->query('SELECT * FROM gamingsoft_config WHERE id = 1 LIMIT 1')?->fetch(PDO::FETCH_ASSOC);
            self::$cachedConfig = is_array($row) ? $row : [];

            return self::$cachedConfig;
        } catch (Throwable) {
            return [];
        }
    }

    public static function updateConfig(PDO $pdo, array $data): void
    {
        self::bootstrap($pdo);
        $allowed = [
            'operator_code', 'secret_key', 'api_base_url', 'site_endpoint',
            'currency', 'language_code', 'channel_code', 'callback_allowed_ips', 'try_to_idr_rate',
        ];
        $secrets = ['secret_key'];
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
            if ($key === 'currency') {
                $value = strtoupper($value) ?: 'TRY';
            }
            if ($key === 'language_code') {
                $value = (string) max(0, (int) $value);
            }
            if ($key === 'try_to_idr_rate') {
                $rate = (float) str_replace(',', '.', $value);
                $value = (string) ($rate > 0 ? round($rate, 4) : self::DEFAULT_TRY_TO_IDR_RATE);
            }
            if ($key === 'api_base_url' || $key === 'site_endpoint') {
                $value = rtrim($value, '/');
            }
            $sets[] = "`{$key}` = :{$key}";
            $params[":{$key}"] = $value;
        }
        $sets[] = 'is_active = :is_active';
        $params[':is_active'] = (!empty($data['is_active']) && $data['is_active'] !== '0') ? 1 : 0;
        if ($sets === []) {
            return;
        }
        $pdo->prepare('UPDATE gamingsoft_config SET ' . implode(', ', $sets) . ' WHERE id = 1')->execute($params);
    }

    public static function isConfigured(PDO $pdo): bool
    {
        $cfg = self::config($pdo);

        return trim((string) ($cfg['operator_code'] ?? '')) !== ''
            && trim((string) ($cfg['secret_key'] ?? '')) !== ''
            && trim((string) ($cfg['api_base_url'] ?? '')) !== '';
    }

    /** Currency used for catalog sync and launch (VGY1 staging default: IDR). */
    public static function catalogCurrency(PDO $pdo): string
    {
        self::bootstrap($pdo);

        return self::resolveOperatorCurrency(self::config($pdo));
    }

    public static function callbackBaseUrl(PDO $pdo): string
    {
        $cfg = self::config($pdo);
        $site = rtrim(trim((string) ($cfg['site_endpoint'] ?? '')), '/');
        if ($site === '') {
            $site = self::backendEndpoint();
        }

        return $site . '/api/v2/gamingsoft-wallet';
    }

    public static function ownsGameId(string $gameId): bool
    {
        return str_starts_with(strtolower(trim($gameId)), strtolower(self::GAME_ID_PREFIX));
    }

    public static function buildGameId(int|string $productCode, string $gameCode): string
    {
        return self::GAME_ID_PREFIX . (int) $productCode . ':' . trim($gameCode);
    }

    /** @return array{product_code:int, game_code:string}|null */
    public static function parseGameId(string $gameId): ?array
    {
        $gameId = trim($gameId);
        if (!self::ownsGameId($gameId)) {
            return null;
        }
        $rest = substr($gameId, strlen(self::GAME_ID_PREFIX));
        $parts = explode(':', $rest, 2);
        if (count($parts) !== 2 || !is_numeric($parts[0]) || trim($parts[1]) === '') {
            return null;
        }

        return ['product_code' => (int) $parts[0], 'game_code' => trim($parts[1])];
    }

    public static function normalizeGameTypeToCatalog(string $gameType): int
    {
        $t = strtoupper(trim($gameType));
        if (self::isLiveGameType($t)) {
            return 2;
        }

        return 1;
    }

    public static function isLiveGameType(string $gameType): bool
    {
        $t = strtoupper(trim($gameType));
        if ($t === '') {
            return false;
        }

        return $t === 'LIVE_CASINO'
            || $t === 'LIVE_CASINO_PREMIUM'
            || $t === 'LC'
            || $t === 'LIVE'
            || $t === 'LIVE CASINO'
            || $t === 'LIVE-CASINO'
            || str_starts_with($t, 'LIVE_CASINO')
            || str_contains($t, 'LIVE_CASINO')
            || str_contains($t, 'LIVE CASINO');
    }

    // ─── Operator outbound ───────────────────────────────────────────

    public static function syncProducts(PDO $pdo): array
    {
        self::bootstrap($pdo);
        $cfg = self::activeConfig($pdo);
        $response = self::operatorGet($cfg, '/api/operators/available-products', [
            'operator_code' => (string) $cfg['operator_code'],
            'sign'          => self::operatorSign($cfg, 'productlist'),
            'request_time'  => self::requestTimeSeconds(),
        ]);

        $products = [];
        if (isset($response[0]) && is_array($response[0])) {
            $products = $response;
        } elseif (is_array($response['data'] ?? null)) {
            $products = $response['data'];
        } elseif (is_array($response['products'] ?? null)) {
            $products = $response['products'];
        }

        $stmt = $pdo->prepare(
            'INSERT INTO gamingsoft_products
                (product_code, product_id, provider_id, provider, product_name, game_type, currency, status, entry_type, is_active, synced_at)
             VALUES (:pc, :pid, :prid, :prov, :pname, :gtype, :cur, :status, :entry, 1, NOW())
             ON DUPLICATE KEY UPDATE
                product_id = VALUES(product_id),
                provider_id = VALUES(provider_id),
                provider = VALUES(provider),
                product_name = VALUES(product_name),
                currency = VALUES(currency),
                status = VALUES(status),
                entry_type = VALUES(entry_type),
                is_active = IF(VALUES(status) = \'ACTIVATED\', 1, is_active),
                synced_at = NOW()'
        );

        $targetCurrency = self::resolveOperatorCurrency($cfg);
        $count = 0;
        $skipped = 0;
        foreach ($products as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = (int) ($row['product_code'] ?? 0);
            if ($code <= 0) {
                continue;
            }
            $rowCurrency = strtoupper(trim((string) ($row['currency'] ?? '')));
            if (!self::isOperatorProductCurrency($rowCurrency, $targetCurrency)) {
                $skipped++;
                continue;
            }
            $gameType = strtoupper(trim((string) ($row['game_type'] ?? '')));
            if ($gameType === '') {
                $skipped++;
                continue;
            }
            $status = strtoupper(trim((string) ($row['status'] ?? '')));
            $stmt->execute([
                ':pc'    => $code,
                ':pid'   => isset($row['product_id']) ? (int) $row['product_id'] : null,
                ':prid'  => isset($row['provider_id']) ? (int) $row['provider_id'] : null,
                ':prov'  => trim((string) ($row['provider'] ?? '')),
                ':pname' => trim((string) ($row['product_name'] ?? $row['provider'] ?? '')),
                ':gtype' => $gameType,
                ':cur'   => $rowCurrency !== '' ? $rowCurrency : $targetCurrency,
                ':status'=> $status,
                ':entry' => (int) ($row['entry_type'] ?? 1),
            ]);
            $count++;
        }

        $pdo->exec('UPDATE gamingsoft_config SET products_synced_at = NOW() WHERE id = 1');

        self::purgeStaleCatalog($pdo, $targetCurrency);

        return ['product_count' => $count, 'skipped_other_currency' => $skipped, 'currency' => $targetCurrency];
    }

    public static function syncGames(PDO $pdo, ?int $productCode = null): array
    {
        self::bootstrap($pdo);
        $cfg = self::activeConfig($pdo);
        $targetCurrency = self::resolveOperatorCurrency($cfg);

        $sql = 'SELECT product_code, game_type, entry_type, product_name, provider, currency
                FROM gamingsoft_products
                WHERE is_active = 1 AND currency = :cur';
        $params = [':cur' => $targetCurrency];
        if ($productCode !== null && $productCode > 0) {
            $sql .= ' AND product_code = :pc';
            $params[':pc'] = $productCode;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($products === []) {
            self::syncProducts($pdo);
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $insert = $pdo->prepare(
            'INSERT INTO gamingsoft_games
                (product_code, game_code, game_name, game_type, image_url, support_currency, status, allow_free_round, entry_type, raw_payload, is_active, synced_at)
             VALUES (:pc, :gc, :name, :gtype, :img, :cur, :status, :fr, :entry, :raw, :active, NOW())
             ON DUPLICATE KEY UPDATE
                game_name = VALUES(game_name),
                game_type = VALUES(game_type),
                image_url = VALUES(image_url),
                support_currency = VALUES(support_currency),
                status = VALUES(status),
                allow_free_round = VALUES(allow_free_round),
                entry_type = VALUES(entry_type),
                raw_payload = VALUES(raw_payload),
                is_active = VALUES(is_active),
                synced_at = NOW()'
        );

        $gameCount = 0;
        $errors = [];
        foreach ($products as $product) {
            $pc = (int) ($product['product_code'] ?? 0);
            if ($pc <= 0) {
                continue;
            }
            try {
                $productGameType = strtoupper(trim((string) ($product['game_type'] ?? '')));
                $query = [
                    'product_code'  => $pc,
                    'operator_code' => (string) $cfg['operator_code'],
                    'sign'          => self::operatorSign($cfg, 'gamelist'),
                    'request_time'  => self::requestTimeSeconds(),
                ];
                if ($productGameType !== '') {
                    $query['game_type'] = $productGameType;
                }
                $response = self::operatorGet($cfg, '/api/operators/provider-games', $query);
                $games = is_array($response['provider_games'] ?? null) ? $response['provider_games'] : [];
                $entryType = (int) ($product['entry_type'] ?? 1);
                $syncedForProduct = 0;
                foreach ($games as $game) {
                    if (!is_array($game)) {
                        continue;
                    }
                    if (!self::gameSupportsOperatorCurrency($game, $targetCurrency)) {
                        continue;
                    }
                    $gameCode = trim((string) ($game['game_code'] ?? ''));
                    if ($gameCode === '') {
                        continue;
                    }
                    $status = strtoupper(trim((string) ($game['status'] ?? 'ACTIVATED')));
                    $active = str_starts_with($status, 'ACTIVAT') ? 1 : 0;
                    $insert->execute([
                        ':pc'     => (int) ($game['product_code'] ?? $pc),
                        ':gc'     => $gameCode,
                        ':name'   => trim((string) ($game['game_name'] ?? $gameCode)),
                        ':gtype'  => strtoupper(trim((string) ($game['game_type'] ?? $productGameType))),
                        ':img'    => trim((string) ($game['image_url'] ?? '')) ?: null,
                        ':cur'    => strtoupper(trim((string) ($game['support_currency'] ?? ''))),
                        ':status' => $status,
                        ':fr'     => !empty($game['allow_free_round']) ? 1 : 0,
                        ':entry'  => $entryType,
                        ':raw'    => json_encode($game, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ':active' => $active,
                    ]);
                    $gameCount++;
                    $syncedForProduct++;
                }

                // Lobby-only products (entry_type=2) need a launchable catalog tile.
                // Also seed a lobby tile for live products that returned no individual games.
                if ($entryType === 2 || ($syncedForProduct === 0 && self::isLiveGameType($productGameType))) {
                    $lobbyName = trim((string) ($product['product_name'] ?? $product['provider'] ?? '')) ?: ('Product ' . $pc);
                    $insert->execute([
                        ':pc'     => $pc,
                        ':gc'     => '__lobby__',
                        ':name'   => $lobbyName . ($entryType === 2 ? ' Lobby' : ' Lobby'),
                        ':gtype'  => $productGameType !== '' ? $productGameType : 'LIVE_CASINO',
                        ':img'    => null,
                        ':cur'    => '',
                        ':status' => 'ACTIVATED',
                        ':fr'     => 0,
                        ':entry'  => max(2, $entryType),
                        ':raw'    => json_encode([
                            'synthetic' => true,
                            'entry_type' => max(2, $entryType),
                            'product_code' => $pc,
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ':active' => 1,
                    ]);
                    $gameCount++;
                }
            } catch (Throwable $e) {
                $errors[] = $pc . ': ' . $e->getMessage();
            }
        }

        $pdo->exec('UPDATE gamingsoft_config SET games_synced_at = NOW() WHERE id = 1');

        self::purgeStaleCatalog($pdo, $targetCurrency);

        return [
            'game_count'    => $gameCount,
            'product_count' => count($products),
            'errors'        => $errors,
        ];
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
            return ['success' => false, 'code' => 404, 'message' => 'Geçersiz GamingSoft oyun kimliği.'];
        }

        $gameStmt = $pdo->prepare(
            'SELECT g.*, p.product_name, p.provider, p.currency AS product_currency,
                    p.entry_type AS product_entry_type, p.is_active AS product_active, p.status AS product_status
             FROM gamingsoft_games g
             LEFT JOIN gamingsoft_products p ON p.product_code = g.product_code
             WHERE g.product_code = :pc AND g.game_code = :gc
             LIMIT 1'
        );
        $gameStmt->execute([':pc' => $parsed['product_code'], ':gc' => $parsed['game_code']]);
        $gameRow = $gameStmt->fetch(PDO::FETCH_ASSOC);

        // Synthetic lobby tiles may exist only as products (LiveCasinoQuery).
        $isLobbyCode = strcasecmp($parsed['game_code'], '__lobby__') === 0
            || strcasecmp($parsed['game_code'], 'lobby') === 0;
        if ((!is_array($gameRow) || (int) ($gameRow['is_active'] ?? 0) !== 1) && $isLobbyCode) {
            $prodStmt = $pdo->prepare(
                'SELECT product_code, product_name, provider, game_type, currency, entry_type, is_active, status
                 FROM gamingsoft_products
                 WHERE product_code = :pc
                 LIMIT 1'
            );
            $prodStmt->execute([':pc' => $parsed['product_code']]);
            $product = $prodStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($product) && (int) ($product['is_active'] ?? 0) === 1) {
                $gameRow = [
                    'product_code' => (int) $product['product_code'],
                    'game_code' => '__lobby__',
                    'game_name' => trim((string) ($product['product_name'] ?? $product['provider'] ?? '')) . ' Lobby',
                    'game_type' => (string) ($product['game_type'] ?? 'LIVE_CASINO'),
                    'entry_type' => 2,
                    'product_entry_type' => (int) ($product['entry_type'] ?? 2),
                    'product_active' => 1,
                    'product_name' => (string) ($product['product_name'] ?? ''),
                    'provider' => (string) ($product['provider'] ?? ''),
                    'product_currency' => (string) ($product['currency'] ?? ''),
                    'is_active' => 1,
                ];
            }
        }

        if (!is_array($gameRow) || (int) ($gameRow['is_active'] ?? 0) !== 1) {
            return ['success' => false, 'code' => 404, 'message' => 'Oyun bulunamadı veya pasif.'];
        }
        if (isset($gameRow['product_active']) && (int) $gameRow['product_active'] !== 1) {
            return ['success' => false, 'code' => 503, 'message' => 'Ürün bakımda veya pasif.'];
        }

        $isGuest = !is_array($user) || (int) ($user['id'] ?? 0) <= 0;
        if ($isGuest) {
            return ['success' => false, 'code' => 401, 'message' => 'GamingSoft için giriş gerekli.'];
        }

        $userId = (int) $user['id'];
        $memberAccount = self::memberAccountFromUser($user);
        $nickname = trim((string) ($user['username'] ?? ('user_' . $userId)));
        $memberAccountCandidates = [$memberAccount];
        if ($nickname !== '' && $nickname !== $memberAccount && strlen($nickname) <= 50) {
            $memberAccountCandidates[] = $nickname;
        }
        $launchPassword = self::memberLaunchPassword($pdo, $userId, $memberAccount, (string) $cfg['secret_key']);
        $launchPasswordCandidatesByAccount = [];
        foreach ($memberAccountCandidates as $accountCandidate) {
            $launchPasswordCandidatesByAccount[$accountCandidate] = self::memberLaunchPasswordCandidates(
                $pdo,
                $userId,
                $accountCandidate,
                (string) $cfg['secret_key']
            );
        }

        // Product row is authoritative for launch mode (entry_type) and game_type.
        $targetCurrency = self::resolveOperatorCurrency($cfg);
        $preferredGameType = self::gameTypeFromRow(is_array($gameRow) ? $gameRow : [], []);
        $productRow = self::resolveLaunchProduct(
            $pdo,
            (int) $parsed['product_code'],
            $targetCurrency,
            is_array($gameRow) ? $gameRow : [],
            $preferredGameType
        );
        $productCurrency = strtoupper(trim((string) ($productRow['currency'] ?? '')));
        if ($productRow === []
            || (int) ($productRow['is_active'] ?? 0) !== 1
            || ($productCurrency !== '' && $productCurrency !== $targetCurrency)
        ) {
            return [
                'success' => false,
                'code'    => 422,
                'message' => 'Bu oyun VGY1 staging '
                    . $targetCurrency
                    . ' kapsamında değil (product_code='
                    . (int) $parsed['product_code']
                    . ($productCurrency !== '' ? ', currency=' . $productCurrency : '')
                    . '). Admin → GSC+ Ayarları Currency='
                    . $targetCurrency
                    . ', ardından Product Sync + Oyun Sync çalıştırın.',
            ];
        }
        $launchProductCode = (int) ($productRow['product_code'] ?? $parsed['product_code']);

        $productGameType = strtoupper(trim((string) ($productRow['game_type'] ?? '')));
        $gameRowType = self::gameTypeFromRow(is_array($gameRow) ? $gameRow : [], $productRow);
        $gameType = $gameRowType !== '' ? $gameRowType : ($productGameType !== '' ? $productGameType : 'SLOT');

        $productEntryType = (int) ($productRow['entry_type'] ?? 0);
        $gameEntryType = (int) ($gameRow['entry_type'] ?? $gameRow['product_entry_type'] ?? 0);
        $entryType = $productEntryType > 0 ? $productEntryType : ($gameEntryType > 0 ? $gameEntryType : 1);

        $rawGameCode = trim((string) ($gameRow['game_code'] ?? $parsed['game_code']));
        $isLobbyCode = $rawGameCode === ''
            || strcasecmp($rawGameCode, '__lobby__') === 0
            || strcasecmp($rawGameCode, 'lobby') === 0;

        // entry_type=2 => lobby only (omit game_code). Never send synthetic __lobby__ codes to GSC+.
        $useLobbyLaunch = $entryType === 2 || $isLobbyCode;
        $gameCode = $useLobbyLaunch ? null : $rawGameCode;
        if ($gameCode !== null && $gameCode === '') {
            $gameCode = null;
        }

        $currency = self::pickLaunchCurrency(
            (string) ($productRow['currency'] ?? $gameRow['product_currency'] ?? ''),
            (string) ($gameRow['support_currency'] ?? ''),
            $targetCurrency
        );

        $channel = strtolower(trim((string) ($input['channel'] ?? 'desktop')));
        $platform = match (true) {
            in_array($channel, ['mobile', 'm'], true) => 'MOBILE',
            in_array($channel, ['desktop', 'pc'], true) => 'DESKTOP',
            default => 'WEB',
        };

        $langInput = strtolower(trim((string) ($input['lang'] ?? '')));
        $languageCode = (int) ($cfg['language_code'] ?? 0);
        if ($langInput !== '' && isset(self::LANG_MAP[$langInput])) {
            $languageCode = self::LANG_MAP[$langInput];
        }

        $lobbyUrl = trim((string) ($input['home_url'] ?? $input['operator_lobby_url'] ?? ''));
        if ($lobbyUrl === '') {
            $lobbyUrl = defined('SITE_URL') && trim((string) SITE_URL) !== ''
                ? rtrim((string) SITE_URL, '/')
                : ('https://' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        }

        $ip = trim((string) ($input['ip'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'));
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        $requestTime = self::requestTimeSeconds();
        $gameTypeCandidates = self::launchGameTypeCandidates($gameType, $productGameType);
        $isLiveLaunch = self::isLiveGameType($gameType) || self::isLiveGameType($productGameType);
        $siteWalletBalance = self::memberWalletBalance($pdo, $user);
        $providerWalletBalance = self::toProviderAmount($siteWalletBalance, $currency);
        if ($providerWalletBalance <= 0) {
            return [
                'success' => false,
                'code'    => 422,
                'message' => 'Oyun başlatılamadı: GSC+ oyun cüzdanı bakiyeniz 0 '
                    . $currency
                    . '. Site bakiyeniz: '
                    . number_format($siteWalletBalance, 2, ',', '.')
                    . ' '
                    . self::siteWalletCurrency()
                    . '. TRY→IDR kurunu Admin → GSC+ Ayarlarından kontrol edin.',
            ];
        }

        $launchAttempts = [];
        foreach ($gameTypeCandidates as $typeCandidate) {
            $launchAttempts[] = ['game_code' => $gameCode, 'game_type' => $typeCandidate];
        }
        // Lobby fallback only when product supports it (entry_type=2) or no direct table was requested.
        if ($gameCode === null || $useLobbyLaunch || $entryType === 2) {
            foreach ($gameTypeCandidates as $typeCandidate) {
                $launchAttempts[] = ['game_code' => null, 'game_type' => $typeCandidate];
            }
        } elseif (!$isLiveLaunch) {
            foreach ($gameTypeCandidates as $typeCandidate) {
                $launchAttempts[] = ['game_code' => null, 'game_type' => $typeCandidate];
            }
        }
        $launchAttempts = self::dedupeLaunchAttempts($launchAttempts);

        $response = null;
        $code = -1;
        $providerMessage = '';
        $payload = [];
        $resolvedMemberAccount = $memberAccount;
        $resolvedLaunchPassword = $launchPassword;
        foreach ($memberAccountCandidates as $accountCandidate) {
            $passwordCandidates = $launchPasswordCandidatesByAccount[$accountCandidate] ?? [md5($accountCandidate . (string) $cfg['secret_key'])];
            foreach ($passwordCandidates as $memberAttemptPassword) {
                foreach ($launchAttempts as $attempt) {
                    $requestTime = self::requestTimeSeconds();
                    $payload = self::buildLaunchPayload(
                        $cfg,
                        $accountCandidate,
                        $memberAttemptPassword,
                        $nickname,
                        $currency,
                        $attempt['game_code'],
                        $launchProductCode,
                        $attempt['game_type'],
                        $languageCode,
                        $ip !== '' ? $ip : '127.0.0.1',
                        $platform,
                        $lobbyUrl,
                        $requestTime
                    );
                    try {
                        $response = self::operatorPost($cfg, '/api/operators/launch-game', $payload, 25);
                    } catch (Throwable $e) {
                        return ['success' => false, 'code' => 503, 'message' => 'GSC+ API bağlantı hatası: ' . $e->getMessage()];
                    }

                    $code = (int) ($response['code'] ?? $response['Code'] ?? -1);
                    $providerMessage = trim((string) ($response['message'] ?? $response['Message'] ?? ''));
                    if ($code === 200 || $code === 0) {
                        $gameType = $attempt['game_type'];
                        $gameCode = $attempt['game_code'];
                        $resolvedMemberAccount = $accountCandidate;
                        $resolvedLaunchPassword = $memberAttemptPassword;
                        self::persistLaunchPassword($pdo, $userId, $resolvedMemberAccount, $resolvedLaunchPassword);
                        break 3;
                    }
                    if (self::isRecordNotFoundMessage($providerMessage)) {
                        continue;
                    }
                    if (self::isInvalidGameCodeMessage($providerMessage)) {
                        continue;
                    }
                    if (self::isRetriableProviderLaunchError($providerMessage)) {
                        continue;
                    }
                    if (self::isNotLoggedInMessage($providerMessage)) {
                        continue;
                    }
                    // Hard provider error for this account/password — try next account.
                    break 2;
                }
            }
        }

        // Agent wallet funded under config currency (IDR) — retry once if launch used another code path.
        if (($code !== 200 && $code !== 0)
            && self::isAgentBalanceMessage($providerMessage)
            && $currency !== $targetCurrency
        ) {
            $requestTime = self::requestTimeSeconds();
            $payload = self::buildLaunchPayload(
                $cfg,
                $resolvedMemberAccount,
                $resolvedLaunchPassword,
                $nickname,
                $targetCurrency,
                $gameCode,
                $launchProductCode,
                $gameType,
                $languageCode,
                $ip !== '' ? $ip : '127.0.0.1',
                $platform,
                $lobbyUrl,
                $requestTime
            );
            try {
                $response = self::operatorPost($cfg, '/api/operators/launch-game', $payload, 25);
            } catch (Throwable $e) {
                return ['success' => false, 'code' => 503, 'message' => 'GSC+ API bağlantı hatası: ' . $e->getMessage()];
            }
            $code = (int) ($response['code'] ?? $response['Code'] ?? -1);
            $providerMessage = trim((string) ($response['message'] ?? $response['Message'] ?? ''));
            $currency = $targetCurrency;
        }

        $launchUrl = self::extractLaunchUrlFromResponse(is_array($response) ? $response : []);
        $content = trim((string) ($response['content'] ?? $response['Content'] ?? ''));
        if ($code !== 200 && $code !== 0) {
            return [
                'success' => false,
                'code'    => 422,
                'message' => self::mapLaunchErrorMessage(
                    $providerMessage !== '' ? $providerMessage : ('code ' . $code),
                    $currency,
                    $siteWalletBalance
                ),
                'raw'     => $response,
            ];
        }
        if ($launchUrl !== '' && !self::isLaunchUrlValid($launchUrl)) {
            return [
                'success' => false,
                'code'    => 422,
                'message' => self::mapLaunchErrorMessage(
                    $providerMessage !== '' ? $providerMessage : json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    $currency,
                    $siteWalletBalance
                ),
                'raw'     => $response,
            ];
        }
        if ($launchUrl === '' && $content === '') {
            return ['success' => false, 'code' => 422, 'message' => 'Oyun URL/content döndürmedi.', 'raw' => $response];
        }

        try {
            $pdo->prepare(
                'INSERT INTO gamingsoft_sessions
                    (user_id, username, member_account, product_code, game_code, game_type, currency, platform, launch_url, request_payload, response_payload)
                 VALUES (:uid, :uname, :ma, :pc, :gc, :gt, :cur, :plat, :url, :req, :res)'
            )->execute([
                ':uid'  => $userId,
                ':uname'=> $nickname,
                ':ma'   => $resolvedMemberAccount,
                ':pc'   => $launchProductCode,
                ':gc'   => $gameCode,
                ':gt'   => $gameType,
                ':cur'  => $currency,
                ':plat' => $platform,
                ':url'  => $launchUrl !== '' ? $launchUrl : null,
                ':req'  => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':res'  => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable) {
        }

        $data = [
            'open_mode' => 'iframe',
            'mode'      => 'real',
        ];
        if ($launchUrl !== '') {
            $data['game_url'] = $launchUrl;
            $data['launch_url'] = $launchUrl;
        }
        if ($content !== '') {
            $data['content'] = $content;
            if ($launchUrl === '') {
                $data['open_mode'] = 'html';
            }
        }

        return [
            'success'  => true,
            'code'     => 200,
            'message'  => 'Oyun başlatıldı.',
            'data'     => $data,
            'game_url' => $launchUrl,
        ];
    }

    // ─── Seamless wallet callbacks ───────────────────────────────────

    public static function wallet(PDO $pdo, string $endpoint, array $payload, string $rawBody = ''): array
    {
        $start = microtime(true);
        $endpoint = strtolower(trim($endpoint, '/'));
        $endpoint = preg_replace('#^v1/api/seamless/#', '', $endpoint) ?? $endpoint;
        $endpoint = match ($endpoint) {
            'getbalance', 'get_balance' => 'balance',
            'push-bet-data', 'push_bet_data' => 'pushbetdata',
            default => $endpoint,
        };

        $httpStatus = 200;
        $body = ['code' => 999, 'message' => 'Internal Server Error'];

        try {
            self::bootstrap($pdo);
            if (!self::verifySeamlessSign($pdo, $payload, $endpoint)) {
                error_log('GamingSoft wallet invalid sign: endpoint=' . $endpoint . ' operator=' . (string) self::payloadValue($payload, ['operator_code', 'Operator_Code']));
                $body = $endpoint === 'pushbetdata'
                    ? ['code' => 1004, 'message' => 'API signature is invalid']
                    : self::batchErrorResponse($payload, 1004, 'API signature is invalid');
            } elseif (!self::isOperatorMatch($pdo, $payload)) {
                $body = $endpoint === 'pushbetdata'
                    ? ['code' => 1002, 'message' => 'API proxy key error']
                    : self::batchErrorResponse($payload, 1002, 'API proxy key error');
            } else {
                $body = match ($endpoint) {
                    'balance' => self::walletBalance($pdo, $payload),
                    'withdraw' => self::walletWithdraw($pdo, $payload),
                    'deposit' => self::walletDeposit($pdo, $payload),
                    'pushbetdata' => self::walletPushBetData($pdo, $payload),
                    default => ['code' => 999, 'message' => 'Unknown endpoint'],
                };
            }
        } catch (Throwable $e) {
            error_log('GamingSoft wallet error: ' . $e->getMessage());
            $body = $endpoint === 'pushbetdata'
                ? ['code' => 999, 'message' => 'Internal Server Error']
                : self::batchErrorResponse($payload, 999, 'Internal Server Error');
            $httpStatus = 500;
        }

        $logStatusCode = (int) ($body['code'] ?? 0);
        if ($logStatusCode === 0 && isset($body['data'][0]) && is_array($body['data'][0])) {
            $logStatusCode = (int) ($body['data'][0]['code'] ?? 0);
        }
        $logError = null;
        if ($logStatusCode !== 0) {
            $logError = (string) ($body['message'] ?? ($body['data'][0]['message'] ?? 'error_' . $logStatusCode));
        }

        try {
            self::logWallet($pdo, $endpoint, null, '', $httpStatus, $logStatusCode, $logError,
                (int) round((microtime(true) - $start) * 1000), $payload, $body);
        } catch (Throwable) {
        }

        return ['status' => $httpStatus, 'body' => $body];
    }

    private static function walletBalance(PDO $pdo, array $payload): array
    {
        $cfg = self::config($pdo);
        $currency = strtoupper(trim((string) ($payload['currency'] ?? '')));
        if ($currency === '') {
            $currency = self::resolveOperatorCurrency($cfg);
        }

        $data = [];
        $batch = is_array($payload['batch_requests'] ?? null) ? $payload['batch_requests'] : [];
        foreach ($batch as $req) {
            if (!is_array($req)) {
                continue;
            }
            $member = trim((string) ($req['member_account'] ?? ''));
            $productCode = (int) ($req['product_code'] ?? $req['Product_code'] ?? 0);
            $user = self::userByMemberAccount($pdo, $member);
            if ($user === null) {
                $data[] = [
                    'member_account' => $member,
                    'product_code'   => $productCode,
                    'balance'        => 0,
                    'code'           => 1000,
                    'message'        => 'API member does not exist',
                ];
                continue;
            }
            if ((int) ($user['banned'] ?? 0) === 1) {
                $data[] = [
                    'member_account' => $member,
                    'product_code'   => $productCode,
                    'balance'        => 0,
                    'code'           => 1000,
                    'message'        => 'API member does not exist',
                ];
                continue;
            }
            $balance = self::formatProviderBalance(
                self::toProviderAmount(self::memberWalletBalance($pdo, $user), $currency),
                $currency
            );
            $data[] = [
                'member_account' => $member,
                'product_code'   => $productCode,
                'balance'        => $balance,
                'code'           => 0,
                'message'        => '',
            ];
        }

        return ['data' => $data];
    }

    private static function walletWithdraw(PDO $pdo, array $payload): array
    {
        return self::walletMoneyBatch($pdo, $payload, 'withdraw');
    }

    private static function walletDeposit(PDO $pdo, array $payload): array
    {
        return self::walletMoneyBatch($pdo, $payload, 'deposit');
    }

    private static function walletMoneyBatch(PDO $pdo, array $payload, string $endpoint): array
    {
        $currency = strtoupper(trim((string) ($payload['currency'] ?? '')));
        $cfg = self::config($pdo);
        if ($currency === '') {
            $currency = self::resolveOperatorCurrency($cfg);
        }

        $data = [];
        $batch = is_array($payload['batch_requests'] ?? null) ? $payload['batch_requests'] : [];
        foreach ($batch as $req) {
            if (!is_array($req)) {
                continue;
            }
            $data[] = self::applyMemberTransactions($pdo, $req, $currency, $endpoint);
        }

        return ['data' => $data];
    }

    /** @param array<string, mixed> $req */
    private static function applyMemberTransactions(PDO $pdo, array $req, string $currency, string $endpoint): array
    {
        $member = trim((string) ($req['member_account'] ?? ''));
        $productCode = (int) ($req['product_code'] ?? $req['Product_code'] ?? 0);
        $gameType = strtoupper(trim((string) ($req['game_type'] ?? '')));
        $transactions = is_array($req['transactions'] ?? null) ? $req['transactions'] : [];

        $user = self::userByMemberAccount($pdo, $member);
        if ($user === null) {
            return [
                'member_account' => $member,
                'product_code'   => $productCode,
                'before_balance' => 0,
                'balance'        => 0,
                'code'           => 1000,
                'message'        => 'API member does not exist',
            ];
        }
        if ((int) ($user['banned'] ?? 0) === 1) {
            return [
                'member_account' => $member,
                'product_code'   => $productCode,
                'before_balance' => 0,
                'balance'        => 0,
                'code'           => 1000,
                'message'        => 'API member does not exist',
            ];
        }

        $userId = (int) $user['id'];
        $username = (string) ($user['username'] ?? $member);

        // WBET wins are not deposited via /deposit — accept and no-op credit for prize settlements.
        if ($endpoint === 'deposit' && $productCode === self::WBET_PRODUCT_CODE) {
            $balance = self::formatProviderBalance(
                self::toProviderAmount(self::memberWalletBalance($pdo, $user), $currency),
                $currency
            );

            return [
                'member_account' => $member,
                'product_code'   => $productCode,
                'before_balance' => $balance,
                'balance'        => $balance,
                'code'           => 0,
                'message'        => '',
            ];
        }

        if ($transactions === []) {
            $balance = self::formatProviderBalance(
                self::toProviderAmount(self::memberWalletBalance($pdo, $user), $currency),
                $currency
            );

            return [
                'member_account' => $member,
                'product_code'   => $productCode,
                'before_balance' => $balance,
                'balance'        => $balance,
                'code'           => 0,
                'message'        => '',
            ];
        }

        $pdo->beginTransaction();
        try {
            $walletColumn = self::userWalletColumnSql(self::walletSourceColumn($pdo, $userId));
            $lock = $pdo->prepare(
                "SELECT id, username, `{$walletColumn}` AS wallet_balance, banned
                 FROM users WHERE id = :id LIMIT 1 FOR UPDATE"
            );
            $lock->execute([':id' => $userId]);
            $locked = $lock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($locked)) {
                $pdo->rollBack();

                return [
                    'member_account' => $member,
                    'product_code'   => $productCode,
                    'before_balance' => 0,
                    'balance'        => 0,
                    'code'           => 1000,
                    'message'        => 'API member does not exist',
                ];
            }

            $beforeReal = round((float) ($locked['wallet_balance'] ?? 0), 4);
            $currentReal = $beforeReal;
            $applied = 0;
            $duplicates = 0;
            $lastDuplicateBalances = null;

            foreach ($transactions as $tx) {
                if (!is_array($tx)) {
                    continue;
                }
                $txnId = trim((string) ($tx['id'] ?? ''));
                if ($txnId === '') {
                    $pdo->rollBack();

                    return [
                        'member_account' => $member,
                        'product_code'   => $productCode,
                        'before_balance' => self::toProviderAmount($beforeReal, $currency),
                        'balance'        => self::toProviderAmount($beforeReal, $currency),
                        'code'           => 999,
                        'message'        => 'Missing transaction id',
                    ];
                }

                $existing = $pdo->prepare(
                    'SELECT before_balance, after_balance FROM gamingsoft_transactions WHERE txn_id = :id LIMIT 1'
                );
                $existing->execute([':id' => $txnId]);
                $prev = $existing->fetch(PDO::FETCH_ASSOC);
                if (is_array($prev)) {
                    $duplicates++;
                    $lastDuplicateBalances = $prev;
                    continue;
                }

                $action = strtoupper(trim((string) ($tx['action'] ?? '')));
                $providerAmount = (float) ($tx['amount'] ?? 0);
                $deltaReal = self::deltaFromAction($endpoint, $action, $providerAmount, $currency);
                $afterReal = round($currentReal + $deltaReal, 4);
                if ($afterReal < 0) {
                    $pdo->rollBack();

                    return [
                        'member_account' => $member,
                        'product_code'   => $productCode,
                        'before_balance' => self::toProviderAmount($beforeReal, $currency),
                        'balance'        => self::toProviderAmount($beforeReal, $currency),
                        'code'           => 1001,
                        'message'        => 'API member balance is insufficient',
                    ];
                }

                $pdo->prepare(
                    'INSERT INTO gamingsoft_transactions
                        (user_id, username, member_account, txn_id, wager_code, round_id, product_code, game_code, game_type,
                         action, wager_status, wager_type, amount, bet_amount, valid_bet_amount, prize_amount, tip_amount,
                         before_balance, after_balance, currency, endpoint, settled_at, raw_payload)
                     VALUES
                        (:uid, :uname, :ma, :txn, :wager, :round, :pc, :gc, :gt,
                         :action, :wstatus, :wtype, :amt, :bet, :vbet, :prize, :tip,
                         :before, :after, :cur, :ep, :settled, :raw)'
                )->execute([
                    ':uid'     => $userId,
                    ':uname'   => $username,
                    ':ma'      => $member,
                    ':txn'     => $txnId,
                    ':wager'   => trim((string) ($tx['wager_code'] ?? '')) ?: null,
                    ':round'   => trim((string) ($tx['round_id'] ?? '')) ?: null,
                    ':pc'      => $productCode ?: null,
                    ':gc'      => trim((string) ($tx['game_code'] ?? '')) ?: null,
                    ':gt'      => $gameType !== '' ? $gameType : (strtoupper(trim((string) ($tx['game_type'] ?? ''))) ?: null),
                    ':action'  => $action,
                    ':wstatus' => strtoupper(trim((string) ($tx['wager_status'] ?? ''))) ?: null,
                    ':wtype'   => strtoupper(trim((string) ($tx['wager_type'] ?? ''))) ?: null,
                    ':amt'     => round($deltaReal, 4),
                    ':bet'     => self::fromProviderAmount((float) ($tx['bet_amount'] ?? 0), $currency),
                    ':vbet'    => self::fromProviderAmount((float) ($tx['valid_bet_amount'] ?? 0), $currency),
                    ':prize'   => self::fromProviderAmount((float) ($tx['prize_amount'] ?? 0), $currency),
                    ':tip'     => self::fromProviderAmount((float) ($tx['tip_amount'] ?? 0), $currency),
                    ':before'  => $currentReal,
                    ':after'   => $afterReal,
                    ':cur'     => $currency,
                    ':ep'      => $endpoint,
                    ':settled' => isset($tx['settled_at']) ? (int) $tx['settled_at'] : null,
                    ':raw'     => json_encode($tx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);

                if (class_exists('WageringService', false)) {
                    if ($endpoint === 'withdraw' && $deltaReal < 0 && in_array($action, ['BET', 'TIP'], true)) {
                        WageringService::registerBet($pdo, $userId, abs($deltaReal));
                    } elseif (in_array($action, ['CANCEL', 'ROLLBACK'], true) && $deltaReal > 0) {
                        WageringService::reverseBet($pdo, $userId, abs($deltaReal));
                    }
                }

                $currentReal = $afterReal;
                $applied++;
            }

            if ($applied > 0) {
                $pdo->prepare("UPDATE users SET `{$walletColumn}` = :bal WHERE id = :id")->execute([
                    ':bal' => $currentReal,
                    ':id'  => $userId,
                ]);
            }
            $pdo->commit();

            if ($applied === 0 && $duplicates > 0) {
                $dupBefore = (float) ($lastDuplicateBalances['before_balance'] ?? $beforeReal);
                $dupAfter = (float) ($lastDuplicateBalances['after_balance'] ?? $beforeReal);

                return [
                    'member_account' => $member,
                    'product_code'   => $productCode,
                    'before_balance' => self::toProviderAmount($dupBefore, $currency),
                    'balance'        => self::toProviderAmount($dupAfter, $currency),
                    'code'           => 1003,
                    'message'        => 'Duplicate API transactions',
                ];
            }

            return [
                'member_account' => $member,
                'product_code'   => $productCode,
                'before_balance' => self::toProviderAmount($beforeReal, $currency),
                'balance'        => self::toProviderAmount($currentReal, $currency),
                'code'           => 0,
                'message'        => '',
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (str_contains($e->getMessage(), '1062') || stripos($e->getMessage(), 'Duplicate') !== false) {
                $walletColumn = self::userWalletColumnSql(self::walletSourceColumn($pdo, $userId));
                $balStmt = $pdo->prepare("SELECT `{$walletColumn}` FROM users WHERE id = :id LIMIT 1");
                $balStmt->execute([':id' => $userId]);
                $bal = (float) ($balStmt->fetchColumn() ?: 0);

                return [
                    'member_account' => $member,
                    'product_code'   => $productCode,
                    'before_balance' => self::toProviderAmount($bal, $currency),
                    'balance'        => self::toProviderAmount($bal, $currency),
                    'code'           => 1003,
                    'message'        => 'Duplicate API transactions',
                ];
            }
            throw $e;
        }
    }

    private static function walletPushBetData(PDO $pdo, array $payload): array
    {
        $wagers = is_array($payload['wagers'] ?? null) ? $payload['wagers'] : [];
        $upsert = $pdo->prepare(
            'INSERT INTO gamingsoft_wagers
                (user_id, member_account, wager_code, round_id, product_code, game_code, game_type, wager_type, wager_status,
                 bet_amount, valid_bet_amount, prize_amount, tip_amount, currency, channel_code, settled_at, created_at_ms, payload, raw_payload)
             VALUES
                (:uid, :ma, :wc, :rid, :pc, :gc, :gt, :wt, :ws,
                 :bet, :vbet, :prize, :tip, :cur, :ch, :settled, :created, :payload, :raw)
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                round_id = VALUES(round_id),
                product_code = VALUES(product_code),
                game_code = VALUES(game_code),
                game_type = VALUES(game_type),
                wager_type = VALUES(wager_type),
                wager_status = VALUES(wager_status),
                bet_amount = VALUES(bet_amount),
                valid_bet_amount = VALUES(valid_bet_amount),
                prize_amount = VALUES(prize_amount),
                tip_amount = VALUES(tip_amount),
                currency = VALUES(currency),
                channel_code = VALUES(channel_code),
                settled_at = VALUES(settled_at),
                payload = VALUES(payload),
                raw_payload = VALUES(raw_payload)'
        );

        foreach ($wagers as $wager) {
            if (!is_array($wager)) {
                continue;
            }
            $member = trim((string) ($wager['member_account'] ?? ''));
            $wagerCode = trim((string) ($wager['wager_code'] ?? ''));
            if ($member === '' || $wagerCode === '') {
                continue;
            }
            $user = self::userByMemberAccount($pdo, $member);
            $currency = strtoupper(trim((string) ($wager['currency'] ?? ''))) ?: strtoupper((string) (self::config($pdo)['currency'] ?? 'TRY'));
            $productCode = (int) ($wager['product_code'] ?? 0);
            $status = strtoupper(trim((string) ($wager['wager_status'] ?? '')));
            $prize = self::fromProviderAmount((float) ($wager['prize_amount'] ?? 0), $currency);

            $upsert->execute([
                ':uid'     => $user['id'] ?? null,
                ':ma'      => $member,
                ':wc'      => $wagerCode,
                ':rid'     => trim((string) ($wager['round_id'] ?? '')) ?: null,
                ':pc'      => $productCode ?: null,
                ':gc'      => trim((string) ($wager['game_code'] ?? '')) ?: null,
                ':gt'      => strtoupper(trim((string) ($wager['game_type'] ?? ''))) ?: null,
                ':wt'      => strtoupper(trim((string) ($wager['wager_type'] ?? ''))) ?: null,
                ':ws'      => $status ?: null,
                ':bet'     => self::fromProviderAmount((float) ($wager['bet_amount'] ?? 0), $currency),
                ':vbet'    => self::fromProviderAmount((float) ($wager['valid_bet_amount'] ?? 0), $currency),
                ':prize'   => $prize,
                ':tip'     => self::fromProviderAmount((float) ($wager['tip_amount'] ?? 0), $currency),
                ':cur'     => $currency,
                ':ch'      => trim((string) ($wager['channel_code'] ?? '')) ?: null,
                ':settled' => isset($wager['settled_at']) ? (int) $wager['settled_at'] : null,
                ':created' => isset($wager['created_at']) ? (int) $wager['created_at'] : null,
                ':payload' => json_encode($wager['payload'] ?? new stdClass(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':raw'     => json_encode($wager, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            // WBET: credit prize on SETTLED via pushbetdata (no /deposit).
            if (
                $productCode === self::WBET_PRODUCT_CODE
                && is_array($user)
                && in_array($status, ['SETTLED', 'RESETTLED'], true)
                && $prize > 0
            ) {
                self::creditWbetPrize($pdo, $user, $wagerCode, $prize, $currency, $wager);
            }
        }

        return ['code' => 0, 'message' => ''];
    }

    /** @param array<string, mixed> $user */
    private static function creditWbetPrize(PDO $pdo, array $user, string $wagerCode, float $prizeReal, string $currency, array $wager): void
    {
        $txnId = 'wbet-push:' . $wagerCode;
        $exists = $pdo->prepare('SELECT 1 FROM gamingsoft_transactions WHERE txn_id = :id LIMIT 1');
        $exists->execute([':id' => $txnId]);
        if ($exists->fetchColumn()) {
            return;
        }

        $userId = (int) $user['id'];
        $walletColumn = self::userWalletColumnSql(self::walletSourceColumn($pdo, $userId));
        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare(
                "SELECT id, username, `{$walletColumn}` AS wallet_balance FROM users WHERE id = :id LIMIT 1 FOR UPDATE"
            );
            $lock->execute([':id' => $userId]);
            $locked = $lock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($locked)) {
                $pdo->rollBack();

                return;
            }
            $before = round((float) ($locked['wallet_balance'] ?? 0), 4);
            $after = round($before + $prizeReal, 4);
            $pdo->prepare("UPDATE users SET `{$walletColumn}` = :bal WHERE id = :id")->execute([':bal' => $after, ':id' => $userId]);
            $pdo->prepare(
                'INSERT INTO gamingsoft_transactions
                    (user_id, username, member_account, txn_id, wager_code, round_id, product_code, game_code, game_type,
                     action, wager_status, amount, prize_amount, before_balance, after_balance, currency, endpoint, raw_payload)
                 VALUES
                    (:uid, :uname, :ma, :txn, :wager, :round, :pc, :gc, :gt,
                     :action, :wstatus, :amt, :prize, :before, :after, :cur, :ep, :raw)'
            )->execute([
                ':uid'     => $userId,
                ':uname'   => (string) ($locked['username'] ?? ''),
                ':ma'      => trim((string) ($wager['member_account'] ?? '')),
                ':txn'     => $txnId,
                ':wager'   => $wagerCode,
                ':round'   => trim((string) ($wager['round_id'] ?? '')) ?: null,
                ':pc'      => self::WBET_PRODUCT_CODE,
                ':gc'      => trim((string) ($wager['game_code'] ?? '')) ?: null,
                ':gt'      => strtoupper(trim((string) ($wager['game_type'] ?? ''))) ?: null,
                ':action'  => 'SETTLED',
                ':wstatus' => 'SETTLED',
                ':amt'     => $prizeReal,
                ':prize'   => $prizeReal,
                ':before'  => $before,
                ':after'   => $after,
                ':cur'     => $currency,
                ':ep'      => 'pushbetdata',
                ':raw'     => json_encode($wager, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (!str_contains($e->getMessage(), '1062') && stripos($e->getMessage(), 'Duplicate') === false) {
                error_log('GamingSoft WBET credit failed: ' . $e->getMessage());
            }
        }
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private static function deltaFromAction(string $endpoint, string $action, float $providerAmount, string $currency): float
    {
        $real = self::fromProviderAmount(abs($providerAmount), $currency);
        $signedProvider = self::fromProviderAmount($providerAmount, $currency);

        if (in_array($action, ['ROLLBACK', 'ADJUSTMENT'], true)) {
            return round($signedProvider, 4);
        }

        if ($endpoint === 'withdraw') {
            if (in_array($action, ['CANCEL', 'PRESERVE_REFUND', 'FREEBET'], true)) {
                return round($real, 4);
            }

            // BET, TIP, BET_PRESERVE, default → debit
            return round(-$real, 4);
        }

        // deposit: SETTLED, JACKPOT, BONUS, PROMO, LEADERBOARD, CANCEL, ...
        return round($real, 4);
    }

    /**
     * GSC+ scaled currencies (IDR2 1:1000, VND3 1:100, TWD5 130:1, …).
     * Doc: operator must convert before returning balances when ratio ≠ 1:1.
     */
    private static function currencyRatio(string $currency): int
    {
        $currency = strtoupper(trim($currency));

        return self::CURRENCY_RATIOS[$currency] ?? 1;
    }

    /** IDR2→IDR, VND3→VND, TRY2→TRY, TWD5→TWD (base ISO for FX). */
    private static function providerBaseCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        if (preg_match('/^([A-Z]{3})\d+$/', $currency, $m) === 1) {
            return (string) $m[1];
        }

        return $currency;
    }

    /**
     * Site wallet (TRY) → GSC+ provider units (IDR / IDR2 / …).
     * Example: 100 TRY × 500 = 50_000 IDR; IDR2 → 50.0 (÷1000).
     */
    private static function toProviderAmount(float $siteWalletAmount, string $providerCurrency): float
    {
        $providerCurrency = strtoupper(trim($providerCurrency));
        $baseAmount = self::siteBalanceToBase($siteWalletAmount, $providerCurrency);
        $ratio = self::currencyRatio($providerCurrency);
        $value = $ratio > 1 ? ($baseAmount / $ratio) : $baseAmount;

        return self::formatProviderBalance(round($value, 4), $providerCurrency);
    }

    /**
     * GSC+ provider units → site wallet (TRY).
     * Example: 50 IDR2 × 1000 = 50_000 IDR ÷ 500 = 100 TRY.
     */
    private static function fromProviderAmount(float $providerAmount, string $providerCurrency): float
    {
        $providerCurrency = strtoupper(trim($providerCurrency));
        $ratio = self::currencyRatio($providerCurrency);
        $baseAmount = $ratio > 1 ? ($providerAmount * $ratio) : $providerAmount;

        return round(self::baseBalanceToSite($baseAmount, $providerCurrency), 4);
    }

    private static function siteWalletCurrency(): string
    {
        $env = strtoupper(trim((string) (getenv('SITE_WALLET_CURRENCY') ?: getenv('DEFAULT_CURRENCY') ?: '')));
        if ($env !== '') {
            return $env;
        }

        return self::SITE_WALLET_CURRENCY;
    }

    private static function tryToIdrExchangeRate(): float
    {
        $cfgRate = self::$cachedConfig['try_to_idr_rate'] ?? null;
        if ($cfgRate !== null && is_numeric($cfgRate) && (float) $cfgRate > 0) {
            return (float) $cfgRate;
        }

        $env = getenv('GAMINGSOFT_TRY_TO_IDR_RATE');
        if ($env !== false && is_numeric($env) && (float) $env > 0) {
            return (float) $env;
        }

        return self::DEFAULT_TRY_TO_IDR_RATE;
    }

    /** FX: 1 site unit → N base provider units (IDR for IDR/IDR2). */
    private static function exchangeRateSiteToBase(string $baseCurrency): float
    {
        $siteCurrency = self::siteWalletCurrency();
        $baseCurrency = strtoupper(trim($baseCurrency));
        if ($siteCurrency === $baseCurrency) {
            return 1.0;
        }
        // Staging: site TRY, GSC+ game wallet IDR / IDR2 / IDR3.
        if ($siteCurrency === 'TRY' && $baseCurrency === 'IDR') {
            return self::tryToIdrExchangeRate();
        }

        return 1.0;
    }

    private static function siteBalanceToBase(float $siteAmount, string $providerCurrency): float
    {
        $base = self::providerBaseCurrency($providerCurrency);

        return $siteAmount * self::exchangeRateSiteToBase($base);
    }

    private static function baseBalanceToSite(float $baseAmount, string $providerCurrency): float
    {
        $base = self::providerBaseCurrency($providerCurrency);
        $rate = self::exchangeRateSiteToBase($base);

        return $rate > 0 ? ($baseAmount / $rate) : $baseAmount;
    }

    private static function walletSourceColumn(PDO $pdo, int $userId): string
    {
        if ($userId > 0 && class_exists('WageringService', false)) {
            try {
                return WageringService::walletSourceColumn($pdo, $userId);
            } catch (Throwable) {
            }
        }

        return 'balance';
    }

    private static function userWalletColumnSql(string $column): string
    {
        return in_array($column, ['balance', 'bonus_balance'], true) ? $column : 'balance';
    }

    /** @param array<string, mixed> $row */
    private static function readWalletColumnAmount(array $row, string $column): float
    {
        if ($column === 'bonus_balance') {
            if (array_key_exists('bonus_balance', $row)) {
                return (float) $row['bonus_balance'];
            }

            return (float) ($row['bonus_bakiye'] ?? 0);
        }

        if (array_key_exists('balance', $row) && $row['balance'] !== null && $row['balance'] !== '') {
            return (float) $row['balance'];
        }

        return (float) ($row['ana_bakiye'] ?? 0);
    }

    /** @param array<string, mixed> $user */
    private static function memberWalletBalance(PDO $pdo, array $user): float
    {
        if (array_key_exists('wallet_balance', $user)) {
            return (float) $user['wallet_balance'];
        }

        $userId = (int) ($user['id'] ?? 0);
        $walletCol = self::walletSourceColumn($pdo, $userId);

        return self::readWalletColumnAmount($user, $walletCol);
    }

    /** @param array<string, mixed> $user */
    public static function memberAccountFromUser(array $user): string
    {
        $userId = (int) ($user['id'] ?? 0);
        if ($userId > 0) {
            return (string) $userId;
        }

        $username = trim((string) ($user['username'] ?? ''));
        if ($username !== '' && strlen($username) <= 50) {
            return $username;
        }

        return '0';
    }

    private static function normalizeMemberAccountForLookup(string $memberAccount): string
    {
        $memberAccount = trim($memberAccount);
        if ($memberAccount === '') {
            return '';
        }

        if (preg_match('/^stg_[a-z0-9]{1,12}_(.+)$/i', $memberAccount, $m) === 1) {
            return trim((string) ($m[1] ?? ''));
        }
        if (preg_match('/^[a-z0-9]{1,12}_(.+)$/i', $memberAccount, $m) === 1) {
            return trim((string) ($m[1] ?? ''));
        }

        return $memberAccount;
    }

    private static function memberLaunchPassword(PDO $pdo, int $userId, string $memberAccount, string $secretKey): string
    {
        $candidates = self::memberLaunchPasswordCandidates($pdo, $userId, $memberAccount, $secretKey);

        return $candidates[0] ?? md5($memberAccount . $secretKey);
    }

    /**
     * Stable GSC+ launch password (MD5).
     * Priority: stored gamingsoft_member_accounts → derived md5(member+secret).
     * Never rotate with users.password — that breaks provider sessions ("re-log in").
     *
     * @return list<string>
     */
    private static function memberLaunchPasswordCandidates(PDO $pdo, int $userId, string $memberAccount, string $secretKey): array
    {
        $candidates = [];
        $add = static function (string $value) use (&$candidates): void {
            $value = strtolower(trim($value));
            if (preg_match('/^[a-f0-9]{32}$/', $value) !== 1) {
                return;
            }
            if (!in_array($value, $candidates, true)) {
                $candidates[] = $value;
            }
        };

        $stored = '';
        if ($memberAccount !== '') {
            try {
                $stmt = $pdo->prepare(
                    'SELECT launch_password FROM gamingsoft_member_accounts
                     WHERE member_account = :ma LIMIT 1'
                );
                $stmt->execute([':ma' => $memberAccount]);
                $stored = trim((string) ($stmt->fetchColumn() ?: ''));
                $add($stored);
            } catch (Throwable) {
            }
        }
        if ($stored === '' && $userId > 0) {
            try {
                $stmt = $pdo->prepare(
                    'SELECT launch_password FROM gamingsoft_member_accounts
                     WHERE user_id = :uid LIMIT 1'
                );
                $stmt->execute([':uid' => $userId]);
                $stored = trim((string) ($stmt->fetchColumn() ?: ''));
                $add($stored);
            } catch (Throwable) {
            }
        }

        $derived = md5($memberAccount . $secretKey);
        $add($derived);

        // Last-resort: legacy MD5 site password (only if already GSC+-compatible).
        // Never promote this to stored unless a launch with it succeeds.
        if ($userId > 0) {
            try {
                $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $userId]);
                $add(trim((string) ($stmt->fetchColumn() ?: '')));
            } catch (Throwable) {
            }
        }

        // First launch only: persist derived password and never overwrite later
        // unless a successful launch reports a different working password.
        if ($userId > 0 && $stored === '') {
            self::persistLaunchPassword($pdo, $userId, $memberAccount, $derived);
        }

        return $candidates !== [] ? $candidates : [$derived];
    }

    private static function persistLaunchPassword(PDO $pdo, int $userId, string $memberAccount, string $password): void
    {
        $password = strtolower(trim($password));
        if ($userId <= 0 || $memberAccount === '' || preg_match('/^[a-f0-9]{32}$/', $password) !== 1) {
            return;
        }
        try {
            $pdo->prepare(
                'INSERT INTO gamingsoft_member_accounts (user_id, member_account, launch_password)
                 VALUES (:uid, :ma, :pw)
                 ON DUPLICATE KEY UPDATE
                    member_account = VALUES(member_account),
                    launch_password = VALUES(launch_password)'
            )->execute([
                ':uid' => $userId,
                ':ma'  => $memberAccount,
                ':pw'  => $password,
            ]);
        } catch (Throwable) {
        }
    }

    private static function userByMemberAccount(PDO $pdo, string $memberAccount): ?array
    {
        $memberAccount = self::normalizeMemberAccountForLookup($memberAccount);
        if ($memberAccount === '') {
            return null;
        }
        try {
            // Prefer explicit GSC+ member mapping (stable password / account).
            try {
                $map = $pdo->prepare(
                    'SELECT user_id FROM gamingsoft_member_accounts
                     WHERE member_account = :ma LIMIT 1'
                );
                $map->execute([':ma' => $memberAccount]);
                $mappedId = (int) ($map->fetchColumn() ?: 0);
                if ($mappedId > 0) {
                    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
                    $stmt->execute([':id' => $mappedId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (is_array($row)) {
                        $walletCol = self::walletSourceColumn($pdo, $mappedId);
                        $row['wallet_balance'] = self::readWalletColumnAmount($row, $walletCol);

                        return $row;
                    }
                }
            } catch (Throwable) {
            }

            if (ctype_digit($memberAccount)) {
                $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => (int) $memberAccount]);
            } else {
                $stmt = $pdo->prepare(
                    'SELECT * FROM users
                     WHERE username = :uname OR LOWER(username) = LOWER(:uname2)
                     LIMIT 1'
                );
                $stmt->execute([
                    ':uname'  => $memberAccount,
                    ':uname2' => $memberAccount,
                ]);
            }
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return null;
            }
            $userId = (int) ($row['id'] ?? 0);
            $walletCol = self::walletSourceColumn($pdo, $userId);
            $row['wallet_balance'] = self::readWalletColumnAmount($row, $walletCol);

            return $row;
        } catch (Throwable) {
            return null;
        }
    }

    private static function isOperatorMatch(PDO $pdo, array $payload): bool
    {
        $cfg = self::config($pdo);
        $expected = strtoupper(trim((string) ($cfg['operator_code'] ?? '')));
        $got = strtoupper(trim((string) self::payloadValue($payload, ['operator_code', 'Operator_Code', 'operatorCode'])));
        if ($expected === '' || $got === '') {
            return false;
        }

        return hash_equals($expected, $got);
    }

    private static function verifySeamlessSign(PDO $pdo, array $payload, string $endpoint): bool
    {
        $cfg = self::config($pdo);
        $secret = trim((string) ($cfg['secret_key'] ?? ''));
        $envSecret = trim((string) (getenv('GAMINGSOFT_SECRET_KEY') ?: ''));
        $secrets = [];
        foreach ([$secret, $envSecret] as $candidate) {
            if ($candidate !== '' && !in_array($candidate, $secrets, true)) {
                $secrets[] = $candidate;
            }
        }
        if ($secrets === []) {
            return false;
        }

        // Seamless wallet (OperatorCode first):
        // MD5(operator_code + request_time + method + secret_key)
        $action = match (strtolower(trim($endpoint))) {
            'balance', 'getbalance', 'get_balance' => 'getbalance',
            'withdraw' => 'withdraw',
            'deposit' => 'deposit',
            'pushbetdata', 'push_bet_data', 'push-bet-data' => 'pushbetdata',
            default => strtolower(trim($endpoint)),
        };
        if ($action === '') {
            return false;
        }

        $requestTime = trim((string) self::payloadValue($payload, ['request_time', 'Request_Time', 'requestTime']));
        // Doc: TimeSpan in seconds (not ms). Tolerate numeric JSON values.
        if ($requestTime !== '' && is_numeric($requestTime)) {
            $asFloat = (float) $requestTime;
            if ($asFloat > 20000000000) {
                // milliseconds → seconds
                $requestTime = (string) (int) floor($asFloat / 1000);
            } else {
                $requestTime = (string) (int) $asFloat;
            }
        }
        $sign = strtolower(trim((string) self::payloadValue($payload, ['sign', 'Sign', 'SIGNATURE'])));
        if ($requestTime === '' || $sign === '') {
            return false;
        }

        $operators = [];
        foreach ([
            self::payloadValue($payload, ['operator_code', 'Operator_Code', 'operatorCode']),
            $cfg['operator_code'] ?? '',
        ] as $rawOp) {
            $rawOp = trim((string) $rawOp);
            if ($rawOp === '') {
                continue;
            }
            foreach ([$rawOp, strtoupper($rawOp), strtolower($rawOp)] as $candidate) {
                if ($candidate !== '' && !in_array($candidate, $operators, true)) {
                    $operators[] = $candidate;
                }
            }
        }
        if ($operators === []) {
            return false;
        }

        $actions = [$action];
        if ($action === 'getbalance') {
            $actions[] = 'balance';
        }

        foreach ($secrets as $sk) {
            foreach ($operators as $operator) {
                foreach ($actions as $methodName) {
                    $expected = md5($operator . $requestTime . $methodName . $sk);
                    if (hash_equals($expected, $sign)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Case-insensitive first-match for GSC+ payload keys.
     *
     * @param array<string, mixed> $payload
     * @param list<string> $keys
     */
    private static function payloadValue(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload)) {
                return $payload[$key];
            }
        }
        $lowerMap = [];
        foreach ($payload as $k => $v) {
            if (is_string($k)) {
                $lowerMap[strtolower($k)] = $v;
            }
        }
        foreach ($keys as $key) {
            $lk = strtolower($key);
            if (array_key_exists($lk, $lowerMap)) {
                return $lowerMap[$lk];
            }
        }

        return null;
    }

    /** @param array<string, mixed> $cfg */
    private static function operatorSign(array $cfg, string $action, ?int $requestTime = null): string
    {
        $requestTime ??= self::requestTimeSeconds();
        // Operator APIs (RequestTime first):
        // MD5(request_time + secret_key + method + operator_code)
        return md5((string) $requestTime . trim((string) ($cfg['secret_key'] ?? '')) . $action . trim((string) ($cfg['operator_code'] ?? '')));
    }

    private static function requestTimeSeconds(): int
    {
        // GSC+ uses GMT+8 second timestamps.
        try {
            $dt = new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'));

            return (int) $dt->getTimestamp();
        } catch (Throwable) {
            return time();
        }
    }

    /** @param array<string, mixed> $gameRow @param array<string, mixed> $productRow */
    private static function gameTypeFromRow(array $gameRow, array $productRow): string
    {
        $fromGame = strtoupper(trim((string) ($gameRow['game_type'] ?? '')));
        if ($fromGame !== '') {
            return $fromGame;
        }

        $raw = trim((string) ($gameRow['raw_payload'] ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $fromRaw = strtoupper(trim((string) ($decoded['game_type'] ?? '')));
                if ($fromRaw !== '') {
                    return $fromRaw;
                }
            }
        }

        return strtoupper(trim((string) ($productRow['game_type'] ?? '')));
    }

    /** @return list<string> */
    private static function launchGameTypeCandidates(string $primaryType, string $productType): array
    {
        $candidates = [];
        $add = static function (string $value) use (&$candidates): void {
            $value = strtoupper(trim($value));
            if ($value === '') {
                return;
            }
            if (!in_array($value, $candidates, true)) {
                $candidates[] = $value;
            }
        };

        $add($productType);
        $add($primaryType);
        $add(self::normalizeProviderGameType($productType));
        $add(self::normalizeProviderGameType($primaryType));

        if (self::isLiveGameType($primaryType) || self::isLiveGameType($productType)) {
            foreach (['LIVE_CASINO', 'LIVE_CASINO_PREMIUM', 'LC', 'LIVE'] as $liveType) {
                $add($liveType);
            }
        }

        if ($candidates === []) {
            $candidates[] = 'SLOT';
        }

        return $candidates;
    }

    /**
     * Resolve the IDR (config) product row; map stale CNY product_code to the active provider product if needed.
     *
     * @param array<string, mixed> $gameRow
     * @return array<string, mixed>
     */
    private static function resolveLaunchProduct(
        PDO $pdo,
        int $productCode,
        string $targetCurrency,
        array $gameRow,
        string $preferredGameType = ''
    ): array {
        $preferredGameType = strtoupper(trim($preferredGameType));

        if ($preferredGameType !== '') {
            $exact = $pdo->prepare(
                'SELECT * FROM gamingsoft_products
                 WHERE product_code = :pc AND game_type = :gt AND is_active = 1 AND currency = :cur
                 LIMIT 1'
            );
            $exact->execute([
                ':pc'  => $productCode,
                ':gt'  => $preferredGameType,
                ':cur' => $targetCurrency,
            ]);
            $exactRow = $exact->fetch(PDO::FETCH_ASSOC);
            if (is_array($exactRow)) {
                return $exactRow;
            }
        }

        $stmt = $pdo->prepare(
            'SELECT * FROM gamingsoft_products
             WHERE product_code = :pc AND is_active = 1 AND currency = :cur
             ORDER BY synced_at DESC, id DESC
             LIMIT 1'
        );
        $stmt->execute([':pc' => $productCode, ':cur' => $targetCurrency]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $row = is_array($row) ? $row : [];

        if ($row !== []) {
            return $row;
        }

        $fallback = $pdo->prepare('SELECT * FROM gamingsoft_products WHERE product_code = :pc LIMIT 1');
        $fallback->execute([':pc' => $productCode]);
        $row = $fallback->fetch(PDO::FETCH_ASSOC);
        $row = is_array($row) ? $row : [];

        $provider = trim((string) ($row['provider'] ?? $gameRow['provider'] ?? ''));
        $productName = trim((string) ($row['product_name'] ?? $gameRow['product_name'] ?? ''));
        if ($provider === '' && $productName === '') {
            return $row;
        }

        $providerMatch = $provider !== '' ? $provider : $productName;
        $nameMatch = $productName !== '' ? $productName : $provider;
        $lookupSql = 'SELECT * FROM gamingsoft_products
             WHERE is_active = 1
               AND currency = :cur
               AND (
                    provider = :provider1 OR product_name = :provider2
                    OR provider = :pname1 OR product_name = :pname2
               )';
        $lookupParams = [
            ':cur'        => $targetCurrency,
            ':provider1'  => $providerMatch,
            ':provider2'  => $providerMatch,
            ':pname1'     => $nameMatch,
            ':pname2'     => $nameMatch,
        ];
        if ($preferredGameType !== '') {
            $lookupSql .= ' AND game_type = :gt';
            $lookupParams[':gt'] = $preferredGameType;
        }
        $lookupSql .= ' ORDER BY synced_at DESC, id DESC LIMIT 1';
        $lookup = $pdo->prepare($lookupSql);
        $lookup->execute($lookupParams);
        $match = $lookup->fetch(PDO::FETCH_ASSOC);

        return is_array($match) ? $match : $row;
    }

    private static function isOperatorProductCurrency(string $productCurrency, string $operatorCurrency): bool
    {
        $productCurrency = strtoupper(trim($productCurrency));
        $operatorCurrency = strtoupper(trim($operatorCurrency));
        if ($productCurrency === '') {
            return true;
        }
        if ($productCurrency === $operatorCurrency) {
            return true;
        }

        // VGY1 staging wallet is IDR; IDR2/VND2 are separate provider wallets.
        return false;
    }

    /** @param array<string, mixed> $game */
    private static function gameSupportsOperatorCurrency(array $game, string $operatorCurrency): bool
    {
        $operatorCurrency = strtoupper(trim($operatorCurrency));
        $support = strtoupper(trim((string) ($game['support_currency'] ?? '')));
        if ($support === '' || $operatorCurrency === '') {
            return true;
        }
        if ($support === $operatorCurrency) {
            return true;
        }

        foreach (self::splitCurrencyCandidates($support) as $candidate) {
            if ($candidate === $operatorCurrency) {
                return true;
            }
        }

        return false;
    }

    private static function purgeStaleCatalog(PDO $pdo, string $targetCurrency): void
    {
        $targetCurrency = strtoupper(trim($targetCurrency));
        if ($targetCurrency === '') {
            return;
        }

        try {
            $pdo->prepare(
                'UPDATE gamingsoft_products SET is_active = 0
                 WHERE currency <> :cur AND currency <> \'\''
            )->execute([':cur' => $targetCurrency]);
        } catch (Throwable) {
        }

        try {
            $pdo->prepare(
                'UPDATE gamingsoft_games g
                 LEFT JOIN gamingsoft_products p
                   ON p.product_code = g.product_code
                  AND p.is_active = 1
                  AND p.currency = :cur
                 SET g.is_active = 0
                 WHERE p.id IS NULL'
            )->execute([':cur' => $targetCurrency]);
        } catch (Throwable) {
        }
    }

    private static function normalizeProviderGameType(string $gameType): string
    {
        $t = strtoupper(trim($gameType));
        if ($t === '' || $t === 'LC' || $t === 'LIVE' || $t === 'LIVE CASINO' || $t === 'LIVE-CASINO') {
            return $t === '' ? '' : 'LIVE_CASINO';
        }

        return $t;
    }

    /** @param array<string, mixed> $cfg */
    private static function resolveOperatorCurrency(array $cfg): string
    {
        $currency = strtoupper(trim((string) ($cfg['currency'] ?? '')));
        if ($currency === '' || $currency === 'TRY') {
            return self::STAGING_CURRENCY;
        }

        return $currency;
    }

    /** @return list<string> */
    private static function splitCurrencyCandidates(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[,\s|;]+/', strtoupper(trim($raw))) ?: [] as $part) {
            $part = trim((string) $part);
            if ($part !== '' && !in_array($part, $out, true)) {
                $out[] = $part;
            }
        }

        return $out;
    }

    private static function pickLaunchCurrency(string $productCurrency, string $supportCurrency, string $cfgCurrency): string
    {
        $cfgCurrency = strtoupper(trim($cfgCurrency));
        if ($cfgCurrency === '' || $cfgCurrency === 'TRY') {
            $cfgCurrency = self::STAGING_CURRENCY;
        }

        // Prefer the product's own staging currency (e.g. Big Gaming = IDR2).
        foreach (self::splitCurrencyCandidates($productCurrency . ' ' . $supportCurrency) as $candidate) {
            if (in_array($candidate, self::STAGING_CURRENCIES, true)) {
                return $candidate;
            }
        }

        if (in_array($cfgCurrency, self::STAGING_CURRENCIES, true)) {
            return $cfgCurrency;
        }

        return $cfgCurrency !== '' ? $cfgCurrency : self::STAGING_CURRENCY;
    }

    /**
     * @param array<string, mixed> $cfg
     * @return array<string, mixed>
     */
    private static function buildLaunchPayload(
        array $cfg,
        string $memberAccount,
        string $memberPassword,
        string $nickname,
        string $currency,
        ?string $gameCode,
        int $productCode,
        string $gameType,
        int $languageCode,
        string $ip,
        string $platform,
        string $lobbyUrl,
        int $requestTime
    ): array {
        $payload = [
            'operator_code'      => (string) $cfg['operator_code'],
            'member_account'     => $memberAccount,
            'password'           => $memberPassword,
            'nickname'           => $nickname,
            'currency'           => $currency,
            'product_code'       => $productCode,
            'game_type'          => $gameType,
            'language_code'      => (string) $languageCode,
            'ip'                 => $ip,
            'platform'           => $platform,
            'sign'               => self::operatorSign($cfg, 'launchgame', $requestTime),
            'request_time'       => $requestTime,
            'operator_lobby_url' => $lobbyUrl,
        ];

        // GSC+ doc example uses explicit null for lobby launches.
        if ($gameCode === null) {
            $payload['game_code'] = null;
        } elseif (trim($gameCode) !== '') {
            $payload['game_code'] = trim($gameCode);
        }

        return $payload;
    }

    private static function isRecordNotFoundMessage(string $message): bool
    {
        $lower = strtolower(trim($message));

        return $lower === 'record not found'
            || str_contains($lower, 'record not found')
            || str_contains($lower, 'game not found')
            || str_contains($lower, 'product not found');
    }

    private static function isInvalidGameCodeMessage(string $message): bool
    {
        $lower = strtolower(trim($message));

        return str_contains($lower, 'invalid game code')
            || str_contains($lower, 'invalid game_code')
            || str_contains($lower, 'game code is invalid')
            || str_contains($lower, 'gamecode is invalid');
    }

    private static function isNotLoggedInMessage(string $message): bool
    {
        $lower = strtolower(trim($message));

        return str_contains($lower, 'not logged in')
            || str_contains($lower, 'not login')
            || str_contains($lower, 'please login')
            || str_contains($lower, 'please try to re-log')
            || str_contains($lower, 're-log in')
            || str_contains($lower, 'relog')
            || str_contains($lower, 'not authorized')
            || (str_contains($lower, 'processing your request') && str_contains($lower, 'launch the game'));
    }

    private static function isAgentBalanceMessage(string $message): bool
    {
        $lower = strtolower(trim($message));

        return str_contains($lower, 'insufficient agent balance')
            || str_contains($lower, 'agent balance')
            || str_contains($lower, 'insufficient credit');
    }

    /** @param array<int, array{game_code: ?string, game_type: string}> $attempts
     *  @return array<int, array{game_code: ?string, game_type: string}>
     */
    private static function dedupeLaunchAttempts(array $attempts): array
    {
        $seen = [];
        $unique = [];
        foreach ($attempts as $attempt) {
            $gameCode = $attempt['game_code'];
            $gameType = strtoupper(trim((string) ($attempt['game_type'] ?? '')));
            $key = ($gameCode === null ? 'lobby' : strtolower(trim((string) $gameCode))) . '|' . $gameType;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = ['game_code' => $gameCode, 'game_type' => $gameType];
        }

        return $unique;
    }

    /** @param array<string, mixed> $response */
    private static function extractLaunchUrlFromResponse(array $response): string
    {
        $url = trim((string) ($response['url'] ?? $response['URL'] ?? ''));
        if ($url !== '') {
            return $url;
        }

        $list = $response['list'] ?? null;
        if (is_array($list)) {
            foreach ($list as $candidate) {
                $candidateUrl = trim((string) $candidate);
                if ($candidateUrl !== '') {
                    return $candidateUrl;
                }
            }
        }

        $message = trim((string) ($response['message'] ?? $response['Message'] ?? ''));
        $parsed = self::decodeProviderErrorJson($message);
        if ($parsed !== null && is_array($parsed['list'] ?? null)) {
            foreach ($parsed['list'] as $candidate) {
                $candidateUrl = trim((string) $candidate);
                if ($candidateUrl !== '') {
                    return $candidateUrl;
                }
            }
        }

        return '';
    }

    private static function isLaunchUrlValid(string $url): bool
    {
        if ($url === '') {
            return false;
        }
        if (!preg_match('#^https?://#i', $url)) {
            return false;
        }
        if (preg_match('/[?&]token=([^&]*)/i', $url, $matches)) {
            return trim((string) ($matches[1] ?? '')) !== '';
        }

        return true;
    }

    /** @return array<string, mixed>|null */
    private static function decodeProviderErrorJson(string $message): ?array
    {
        $message = trim($message);
        if ($message === '' || !str_starts_with($message, '{')) {
            return null;
        }
        $decoded = json_decode($message, true);

        return is_array($decoded) ? $decoded : null;
    }

    private static function isRetriableProviderLaunchError(string $message): bool
    {
        $parsed = self::decodeProviderErrorJson($message);
        if ($parsed !== null) {
            $codeId = (int) ($parsed['codeId'] ?? 0);
            if (in_array($codeId, [406, 407, 408], true)) {
                return true;
            }
            $list = $parsed['list'] ?? [];
            if (is_array($list)) {
                foreach ($list as $candidate) {
                    $candidateUrl = trim((string) $candidate);
                    if ($candidateUrl !== '' && !self::isLaunchUrlValid($candidateUrl)) {
                        return true;
                    }
                }
            }
        }

        return str_contains($message, 'token=') && preg_match('/token=(?:&|$)/', $message) === 1;
    }

    private static function formatProviderBalance(float $amount, string $providerCurrency): float
    {
        $amount = max(0.0, $amount);
        $providerCurrency = strtoupper(trim($providerCurrency));
        if (in_array($providerCurrency, ['IDR', 'VND', 'KRW', 'JPY'], true)) {
            return round($amount, 2);
        }

        return round($amount, 4);
    }

    private static function mapLaunchErrorMessage(string $providerMessage, string $currency = '', float $siteBalance = 0.0): string
    {
        $parsed = self::decodeProviderErrorJson($providerMessage);
        if ($parsed !== null) {
            $codeId = (int) ($parsed['codeId'] ?? 0);
            if ($codeId === 406) {
                $limits = is_array($parsed['limits'] ?? null) ? $parsed['limits'] : [];
                $minIdr = 32000;
                if (isset($limits[0]) && is_array($limits[0]) && isset($limits[0]['min'])) {
                    $minIdr = max(1, (int) $limits[0]['min']);
                }
                $targetCurrency = $currency !== '' ? $currency : self::STAGING_CURRENCY;
                $idrBalance = self::toProviderAmount($siteBalance, $targetCurrency);
                $rate = self::tryToIdrExchangeRate();
                $message = 'Oyun başlatılamadı: Dream Gaming masa limiti karşılanamadı (minimum '
                    . number_format($minIdr, 0, ',', '.')
                    . ' IDR).';
                if ($idrBalance > 0) {
                    $message .= ' Oyun cüzdanı bakiyeniz ≈'
                        . number_format($idrBalance, 0, ',', '.')
                        . ' IDR ('
                        . number_format($siteBalance, 2, ',', '.')
                        . ' '
                        . self::siteWalletCurrency()
                        . ').';
                } else {
                    $message .= ' GSC+ cüzdanına bakiye iletilemedi — seamless wallet callback yanıtını kontrol edin.';
                }
                if ($idrBalance > 0 && $idrBalance < $minIdr) {
                    $neededTry = $minIdr / $rate;
                    $message .= ' Bu masa için en az '
                        . number_format($neededTry, 2, ',', '.')
                        . ' '
                        . self::siteWalletCurrency()
                        . ' gerekir (kur: 1 '
                        . self::siteWalletCurrency()
                        . ' = '
                        . number_format($rate, 0, ',', '.')
                        . ' IDR).';
                }
                $list = $parsed['list'] ?? [];
                if (is_array($list)) {
                    foreach ($list as $candidate) {
                        $candidateUrl = trim((string) $candidate);
                        if ($candidateUrl !== '' && !self::isLaunchUrlValid($candidateUrl)) {
                            $message .= ' Boş oyun tokenı: GSC+ bakiye sorgusu başarısız olmuş olabilir (Admin → GSC+ Wallet Logları).';
                            break;
                        }
                    }
                }

                return $message;
            }
        }

        $lower = strtolower($providerMessage);
        if ($providerMessage === '') {
            return 'Oyun başlatılamadı.';
        }

        if (self::isAgentBalanceMessage($providerMessage)) {
            $cur = $currency !== '' ? $currency : self::STAGING_CURRENCY;
            $cfgCur = self::STAGING_CURRENCY;

            return 'Oyun başlatılamadı: GSC+ acente bakiyesi yetersiz veya yanlış para birimi ('
                . $cur . '). VGY1 staging kredisi genelde '
                . $cfgCur . ' cüzdanındadır — Admin → GSC+ Ayarları → Currency = '
                . $cfgCur . ' olmalı, ardından Product Sync + Oyun Sync çalıştırın.';
        }

        if (self::isRecordNotFoundMessage($providerMessage)) {
            return 'Oyun başlatılamadı: GSC+ kaydı bulunamadı (product_code/game_code/game_type). '
                . 'Admin → GSC+ Ayarları Currency=IDR, ardından Product Sync + Oyun Sync çalıştırın. '
                . 'Canlı ürünlerde mümkünse lobby (entry_type=2) deneyin.';
        }

        if (self::isInvalidGameCodeMessage($providerMessage)) {
            return 'Oyun başlatılamadı: GSC+ geçersiz oyun kodu (invalid game code). '
                . 'Katalogdaki game_code güncel değil — Admin → GSC+ → Product Sync + Oyun Sync çalıştırın. '
                . 'Canlı casino lobisi için ürün kartını (Lobby) kullanın.';
        }
        if (self::isNotLoggedInMessage($providerMessage)) {
            return 'Oyun başlatılamadı: Sağlayıcı oturumu / seamless wallet doğrulanamadı. '
                . 'Site cüzdanı TRY → GSC+ cüzdanı IDR dönüşümü ve sabit launch password kullanılıyor. '
                . 'Tekrar deneyin; devam ederse Admin → GSC+ Wallet Loglarında balance çağrılarının code=0 '
                . 've IDR bakiyesinin (TRY × kur) döndüğünü kontrol edin.';
        }

        return 'Oyun başlatılamadı: ' . $providerMessage;
    }

    private static function activeConfig(PDO $pdo): array
    {
        $cfg = self::config($pdo);
        if ($cfg === []) {
            throw new RuntimeException('GamingSoft yapılandırması bulunamadı.');
        }
        foreach (['operator_code', 'secret_key', 'api_base_url'] as $k) {
            if (trim((string) ($cfg[$k] ?? '')) === '') {
                throw new RuntimeException('GamingSoft yapılandırması eksik: ' . $k);
            }
        }
        if ((int) ($cfg['is_active'] ?? 0) !== 1) {
            throw new RuntimeException('GamingSoft entegrasyonu pasif.');
        }

        return $cfg;
    }

    /** @param array<string, mixed> $cfg */
    private static function operatorGet(array $cfg, string $path, array $query, int $timeout = 20): array
    {
        $url = rtrim((string) $cfg['api_base_url'], '/') . $path . '?' . http_build_query($query);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => max(10, $timeout),
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        self::applyCaInfo($ch);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false || $err !== '') {
            throw new RuntimeException('GSC+ GET cURL hatası: ' . $err);
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('GSC+ geçersiz JSON (HTTP ' . $code . '): ' . substr((string) $raw, 0, 200));
        }
        if (isset($decoded['code']) && (int) $decoded['code'] !== 0 && (int) $decoded['code'] !== 200) {
            $msg = trim((string) ($decoded['message'] ?? ''));
            throw new RuntimeException('GSC+ API hatası: ' . ($msg !== '' ? $msg : ('code ' . $decoded['code'])));
        }

        return $decoded;
    }

    /** @param array<string, mixed> $cfg @param array<string, mixed> $payload */
    private static function operatorPost(array $cfg, string $path, array $payload, int $timeout = 20): array
    {
        $url = rtrim((string) $cfg['api_base_url'], '/') . $path;
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => max(10, $timeout),
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        self::applyCaInfo($ch);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false || $err !== '') {
            throw new RuntimeException('GSC+ POST cURL hatası: ' . $err);
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('GSC+ geçersiz JSON (HTTP ' . $code . '): ' . substr((string) $raw, 0, 200));
        }

        return $decoded;
    }

    /** @param resource|\CurlHandle $ch */
    private static function applyCaInfo($ch): void
    {
        foreach ([defined('BASE_PATH') ? BASE_PATH . '/config/cacert.pem' : ''] as $caInfo) {
            if ($caInfo !== '' && is_readable($caInfo)) {
                curl_setopt($ch, CURLOPT_CAINFO, $caInfo);
                break;
            }
        }
    }

    private static function backendEndpoint(): string
    {
        if (defined('BACKEND_URL') && trim((string) BACKEND_URL) !== '') {
            return rtrim((string) BACKEND_URL, '/');
        }

        return rtrim((string) (getenv('BACKEND_URL') ?: getenv('BACKEND_FALLBACK_URL') ?: 'https://admin.vegasroyalspin.com'), '/');
    }

    /** @param array<string, mixed> $payload */
    private static function batchErrorResponse(array $payload, int $code, string $message): array
    {
        $data = [];
        $batch = is_array($payload['batch_requests'] ?? null) ? $payload['batch_requests'] : [];
        foreach ($batch as $req) {
            if (!is_array($req)) {
                continue;
            }
            $data[] = [
                'member_account' => trim((string) ($req['member_account'] ?? '')),
                'product_code'   => (int) ($req['product_code'] ?? $req['Product_code'] ?? 0),
                'before_balance' => 0,
                'balance'        => 0,
                'code'           => $code,
                'message'        => $message,
            ];
        }
        if ($data === []) {
            return ['data' => [['code' => $code, 'message' => $message, 'balance' => 0]]];
        }

        return ['data' => $data];
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
            'INSERT INTO gamingsoft_wallet_logs
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
