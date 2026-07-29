<?php

declare(strict_types=1);

/**
 * GSC+ (GamingSoft) Operator API v2.0.6 — seamless wallet + launch + catalog sync.
 *
 * Wallet callbacks (operator → us):
 *   POST /api/v2/gamingsoft-wallet/v1/api/seamless/balance|withdraw|deposit|pushbetdata
 *
 * Operator API (us → GSC+):
 *   launch-game, available-products, provider-games
 */
final class GscPlusService
{
    public const GAME_ID_PREFIX = 'gsc:';

    public const WALLET_CODES = [
        0    => 'success',
        999  => 'Internal Server Error',
        1000 => 'API member does not exist',
        1001 => 'API member balance is insufficient',
        1002 => 'API proxy key error',
        1003 => 'Duplicate API transactions',
        1004 => 'API signature is invalid',
        1005 => 'API not getting game list',
        1006 => 'API bet does not exist',
        2000 => 'API product is under maintenance',
    ];

    /**
     * Currency ratio vs wallet storage (GSC appendix).
     * IDR2 1:1000 ⇒ provider_balance = wallet_idr / 1000 (4 decimals).
     */
    private const CURRENCY_RATIOS = [
        'BDT2' => 1000, 'BRL2' => 1000, 'CDF2' => 1000, 'CNY2' => 1000,
        'COP2' => 1000, 'EUR2' => 1000, 'HKD2' => 1000, 'IDR2' => 1000,
        'IDR3' => 100, 'INR2' => 1000, 'IRR2' => 1000, 'JPY2' => 1000,
        'KHR2' => 1000, 'KRW2' => 1000, 'LAK2' => 1000, 'LBP2' => 1000,
        'MAD2' => 1000, 'MMK2' => 1000, 'MMK3' => 100, 'MNT2' => 1000,
        'MXN2' => 1000, 'MYR2' => 1000, 'MYR3' => 100, 'NGN2' => 1000,
        'NPR2' => 1000, 'PHP2' => 1000, 'PKR2' => 1000, 'PYG2' => 1000,
        'SGD2' => 1000, 'THB2' => 1000, 'TRY2' => 1000, 'TWD2' => 1000,
        'TWD5' => 130, 'TZS2' => 1000, 'UGX2' => 1000, 'USD2' => 1000,
        'USDT2' => 1000, 'UZS2' => 1000, 'VND2' => 1000, 'VND3' => 100,
    ];

    /** Base ISO currencies accepted by seamless wallet (plus scaled *2/*3 codes). */
    private const BASE_CURRENCIES = [
        'AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN',
        'BAM', 'BBD', 'BDT', 'BGN', 'BHD', 'BIF', 'BMD', 'BND', 'BOB', 'BRL',
        'BSD', 'BTC', 'BTN', 'BWP', 'BYN', 'BZD', 'CAD', 'CDF', 'CHF', 'CLF',
        'CLP', 'CNY', 'COP', 'CRC', 'CSD', 'CUC', 'CUP', 'CVE', 'CZK', 'DJF',
        'DKK', 'DOGE', 'DOP', 'DZD', 'EGP', 'ERN', 'ETB', 'ETH', 'EUR', 'FJD',
        'FKP', 'FRF', 'FTN', 'GBP', 'GC', 'GEL', 'GGP', 'GHS', 'GIP', 'GMD',
        'GNF', 'GTQ', 'GYD', 'HKD', 'HNL', 'HRK', 'HTG', 'HUF', 'IDR', 'ILS',
        'IMP', 'INR', 'IQD', 'IRR', 'ISK', 'JEP', 'JMD', 'JOD', 'JPY', 'KES',
        'KGS', 'KHR', 'KRW', 'KSH', 'KWD', 'KZT', 'LAK', 'LBP', 'LKR', 'LRD',
        'LSL', 'LTC', 'LYD', 'MAD', 'MBTC', 'MDL', 'METH', 'MGA', 'MKD', 'MMK',
        'MNT', 'MOP', 'MRU', 'MVR', 'MWK', 'MXBT', 'MXN', 'MYR', 'MZN', 'NAD',
        'NGN', 'NIO', 'NOK', 'NOT', 'NPR', 'NTD', 'NZD', 'OMR', 'PAB', 'PEN',
        'PGK', 'PHP', 'PKR', 'PLN', 'PTI', 'PTV', 'PYG', 'QAR', 'RON', 'RSD',
        'RUB', 'RWF', 'SAR', 'SBD', 'SC', 'SCR', 'SDG', 'SEK', 'SGD', 'SHP',
        'SLL', 'SOS', 'SRD', 'SSP', 'STD', 'STN', 'SVC', 'SYP', 'SZL', 'THB',
        'TJS', 'TMT', 'TND', 'TON', 'TOP', 'TRY', 'TTD', 'TWD', 'TZS', 'UBTC',
        'UGX', 'USD', 'USDC', 'USDT', 'UYU', 'UZS', 'VES', 'VND', 'VUV', 'WST',
        'XAF', 'XAG', 'XAU', 'XBT', 'XCD', 'XDR', 'XOF', 'XPD', 'XPF', 'XPT',
        'YER', 'ZAR', 'ZMW', 'ZWL',
    ];

    /** VGY1 staging currencies enabled with GSC+ (TRY/EUR/USD still pending). */
    public const STAGING_CURRENCIES = ['IDR', 'IDR2', 'CNY', 'VND', 'VND2'];

    /**
     * Product codes contracted for VGY1 staging, keyed by currency.
     * Source: GSC+ onboarding mail (Agency Code VGY1). Official/prod env is
     * still under construction — only these staging lines are launchable.
     *
     * @var array<string, list<int>>
     */
    public const STAGING_PRODUCTS_BY_CURRENCY = [
        'IDR' => [
            1204, // ADVANT PLAY
            1220, // ASTAR
            1154, // BIGPOT
            1115, // BOOMING GAMES
            1009, // CQ9
            1052, // DREAM GAMING
            1160, // EpicWin
            1079, // FA CHAI
            1253, // GAMING PANDA
            1153, // HACKSAW
            1197, // HABANERO
            1018, // LIVE22
            1070, // BOOONGO
            1098, // FELIX
            1097, // FUNTA
            1006, // PRAGMATIC CASINO (+ BLACKJACK / LIVE_CASINO_PREMIUM)
            1185, // SA GAMING
            1250, // UUSLOTS
            1148, // WOW GAMING
            1274, // EVOPLAY YFG
        ],
        'IDR2' => [
            1004, // BIG GAMING
            1167, // BIG TIME GAMING (ASIA)
        ],
        'CNY' => [
            1223, // ALLBET
            1242, // PLAYTECH (Q6)
            1020, // WM CASINO
        ],
        'VND' => [
            1264, // VIMPLAY
        ],
        'VND2' => [
            1255, // DRAGOON SOFT
        ],
    ];

    /**
     * VGY1 staging product codes that expose LIVE_CASINO / LIVE_CASINO_PREMIUM
     * (onboarding LC lines). Slot-only codes stay out of the live lobby filter.
     *
     * @var list<int>
     */
    public const STAGING_LIVE_PRODUCT_CODES = [
        1006, // PRAGMATIC CASINO (+ LIVE_CASINO_PREMIUM blackjack)
        // 1052 Dream Gaming omitted: staging launch returns codeId 406 + empty
        // token URLs (dingdang/ddnewpc) — not a playable session. Keep in
        // STAGING_PRODUCTS_BY_CURRENCY for catalog sync; hide from live lobby.
        1185, // SA GAMING
        1220, // ASTAR
        1004, // BIG GAMING (IDR2)
        1223, // ALLBET (CNY)
        1242, // PLAYTECH Q6 (CNY)
        1020, // WM CASINO (CNY)
        1264, // VIMPLAY (VND)
    ];

    /**
     * The agent wallet is funded per currency (3.12 Wallet Balance Inquiry); the
     * staging agent is contracted primarily in IDR. Site player balance
     * (users.balance) is the seamless ledger in this same currency — sent to GSC
     * as IDR 1:1 (no TRY→IDR FX). UI may still render a ₺ symbol, but wallet
     * callbacks never treat the number as TRY.
     * TRY products are still pending on GSC+'s side.
     */
    public const DEFAULT_CURRENCY = 'IDR';

    private const DEBIT_ACTIONS = [
        'BET', 'TIP', 'BET_PRESERVE',
    ];

    private const CREDIT_ACTIONS = [
        'SETTLED', 'FREEBET', 'JACKPOT', 'BONUS', 'PROMO', 'LEADERBOARD',
        'CANCEL', 'PRESERVE_REFUND',
    ];

    private const SIGNED_ACTIONS = [
        'ROLLBACK', 'ADJUSTMENT', 'RESETTLED',
    ];

    /**
     * WBET winnings are not sent via /deposit; the operator must credit the
     * player manually after the /pushbetdata SETTLED notification (doc 2.3).
     */
    private const MANUAL_PAYOUT_PRODUCTS = [1040];

    private static bool $schemaBootstrapped = false;

    public static function bootstrap(PDO $pdo): void
    {
        if (self::$schemaBootstrapped) {
            return;
        }
        self::createSchema($pdo);
        self::ensureDefaultConfig($pdo);
        self::ensureFileLog();
        self::$schemaBootstrapped = true;
    }

    private static function ensureFileLog(): void
    {
        if (class_exists('GscFileLog', false)) {
            return;
        }
        $path = dirname(__DIR__) . '/services/GscFileLog.php';
        if (is_file($path)) {
            require_once $path;
        }
    }

    /** @param array<string,mixed> $context */
    private static function fileLog(string $event, array $context = []): void
    {
        self::ensureFileLog();
        if (class_exists('GscFileLog', false)) {
            GscFileLog::write('gsc', $event, $context);
        }
    }

    private static function createSchema(PDO $pdo): void
    {
        $paths = [
            dirname(__DIR__) . '/database/migrations/2026_07_28_100000_create_gsc_plus_tables.php',
            dirname(__DIR__) . '/admin/database/migrations/2026_07_28_100000_create_gsc_plus_tables.php',
        ];
        foreach ($paths as $path) {
            if (!is_file($path)) {
                continue;
            }
            $runner = require $path;
            if (is_callable($runner)) {
                $runner($pdo);
            }
            return;
        }
    }

    private static function ensureDefaultConfig(PDO $pdo): void
    {
        $pdo->exec(
            "INSERT IGNORE INTO gsc_config
                (id, operator_code, secret_key, operator_url, currency, language_code, channel_code, is_active)
             VALUES (1, '', '', 'https://staging.gsimw.com', '" . self::DEFAULT_CURRENCY . "', 0, 'gscp', 0)"
        );
    }

    /** Configured operator currency, falling back to the contracted default. */
    public static function configCurrency(array $cfg): string
    {
        $currency = strtoupper(trim((string) ($cfg['currency'] ?? '')));

        return $currency !== '' ? $currency : self::DEFAULT_CURRENCY;
    }

    /**
     * Currency used for seamless wallet amounts (users.balance ledger).
     * Site UI may label funds as TRY, but GSC+ staging must receive IDR —
     * never invent a TRY wallet currency toward the provider.
     */
    public static function seamlessLedgerCurrency(array $cfg): string
    {
        return self::configCurrency($cfg);
    }

    /**
     * Resolve the currency GSC asked for on a wallet callback. Empty or site
     * display codes (TRY) are coerced to the configured ledger (IDR).
     */
    private static function resolveWalletRequestCurrency(array $payload, array $cfg): string
    {
        $requested = strtoupper(trim((string) ($payload['currency'] ?? '')));
        $ledger = self::seamlessLedgerCurrency($cfg);
        // Site display currency must never leak into GSC wallet math.
        if ($requested === '' || $requested === 'TRY') {
            return $ledger;
        }

        return $requested;
    }

    /**
     * Currencies this agent may activate products for. Prefer live agent-wallet
     * codes when available; otherwise the VGY1 staging set from onboarding.
     *
     * @param list<string>|null $walletCurrencies
     * @return list<string>
     */
    public static function contractedCurrencies(?array $walletCurrencies = null): array
    {
        $out = [];
        foreach (($walletCurrencies ?? []) as $code) {
            $code = strtoupper(trim((string) $code));
            if ($code !== '' && self::isSupportedCurrency($code) && !in_array($code, $out, true)) {
                $out[] = $code;
            }
        }
        if ($out !== []) {
            return $out;
        }

        return self::STAGING_CURRENCIES;
    }

    /** True when (product_code, currency) is on the VGY1 staging contract list. */
    public static function isStagingContractedProduct(int $productCode, string $currency): bool
    {
        $currency = strtoupper(trim($currency));
        $list = self::STAGING_PRODUCTS_BY_CURRENCY[$currency] ?? null;
        if (!is_array($list)) {
            return false;
        }

        return in_array($productCode, $list, true);
    }

    /**
     * Product codes allowed on the VGY1 staging live-casino lobby.
     *
     * @return list<int>
     */
    public static function stagingLiveProductCodes(): array
    {
        return self::STAGING_LIVE_PRODUCT_CODES;
    }

    /**
     * Currencies to include in the live lobby. Empty / unknown prefer → all
     * staging currencies; a known code (e.g. IDR) narrows the lobby for tests.
     *
     * @return list<string>
     */
    public static function stagingLobbyCurrencyFilter(?string $prefer = null): array
    {
        $prefer = strtoupper(trim((string) $prefer));
        if ($prefer !== '' && in_array($prefer, self::STAGING_CURRENCIES, true)) {
            return [$prefer];
        }

        return self::STAGING_CURRENCIES;
    }

    /**
     * When true, Canlı Casino skips the Casino Aggregator branch so the lobby
     * is only VGY1 GSC+ staging live products. Env GSC_LIVE_LOBBY_ONLY=0 re-enables aggregator.
     */
    public static function liveLobbyGscOnly(?array $extraQuery = null): bool
    {
        if (is_array($extraQuery) && array_key_exists('gsc_only', $extraQuery)) {
            $raw = $extraQuery['gsc_only'];
            if (is_bool($raw)) {
                return $raw;
            }
            $flag = strtolower(trim((string) $raw));

            return in_array($flag, ['1', 'true', 'yes', 'on'], true);
        }

        foreach (['GSC_LIVE_LOBBY_ONLY'] as $key) {
            $value = getenv($key);
            if ($value === false && isset($_ENV[$key])) {
                $value = (string) $_ENV[$key];
            }
            if ($value === false || $value === null || trim((string) $value) === '') {
                continue;
            }
            $flag = strtolower(trim((string) $value));

            return in_array($flag, ['1', 'true', 'yes', 'on'], true);
        }

        // Staging-first default: hide aggregator until explicitly re-enabled.
        return true;
    }

    public static function config(PDO $pdo): array
    {
        self::bootstrap($pdo);
        $stmt = $pdo->query('SELECT * FROM gsc_config WHERE id = 1 LIMIT 1');
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        return is_array($row) ? $row : [];
    }

    public static function updateConfig(PDO $pdo, array $data): void
    {
        self::bootstrap($pdo);
        $allowed = [
            'operator_code', 'secret_key', 'operator_url', 'currency',
            'language_code', 'channel_code', 'operator_lobby_url', 'callback_allowed_ips',
        ];
        $sets = [];
        $params = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = is_string($data[$key]) ? trim($data[$key]) : $data[$key];
            if ($key === 'secret_key' && ($value === null || $value === '')) {
                continue;
            }
            if ($key === 'language_code') {
                $value = (int) $value;
            }
            $sets[] = "{$key} = :{$key}";
            $params[":{$key}"] = $value;
        }
        // Only touch is_active when explicitly provided; partial updates
        // (e.g. currency-only) must not silently disable the integration.
        if (array_key_exists('is_active', $data)) {
            $sets[] = 'is_active = :is_active';
            $params[':is_active'] = !empty($data['is_active']) ? 1 : 0;
        }
        if ($sets === []) {
            return;
        }
        $sql = 'UPDATE gsc_config SET ' . implode(', ', $sets) . ' WHERE id = 1';
        $pdo->prepare($sql)->execute($params);
    }

    public static function isConfigured(PDO $pdo): bool
    {
        $cfg = self::config($pdo);
        return trim((string) ($cfg['operator_code'] ?? '')) !== ''
            && trim((string) ($cfg['secret_key'] ?? '')) !== ''
            && trim((string) ($cfg['operator_url'] ?? '')) !== ''
            && (int) ($cfg['is_active'] ?? 0) === 1;
    }

    private static function activeConfig(PDO $pdo): array
    {
        $cfg = self::config($pdo);
        if (!self::isConfigured($pdo)) {
            throw new RuntimeException('GSC+ entegrasyonu yapılandırılmamış veya pasif.');
        }
        return $cfg;
    }

    public static function ownsGameId(string $gameId): bool
    {
        return str_starts_with(strtolower(trim($gameId)), strtolower(self::GAME_ID_PREFIX));
    }

    public static function buildGameId(int $productCode, string $gameCode): string
    {
        $gameCode = trim($gameCode);
        if ($gameCode === '') {
            $gameCode = '_lobby';
        }
        return self::GAME_ID_PREFIX . $productCode . ':' . $gameCode;
    }

    /** @return array{product_code:int,game_code:string}|null */
    public static function parseGameId(string $gameId): ?array
    {
        $gameId = trim($gameId);
        if (!self::ownsGameId($gameId)) {
            return null;
        }
        $rest = substr($gameId, strlen(self::GAME_ID_PREFIX));
        $parts = explode(':', $rest, 2);
        if (count($parts) < 1 || !is_numeric($parts[0])) {
            return null;
        }
        return [
            'product_code' => (int) $parts[0],
            'game_code'    => isset($parts[1]) ? trim($parts[1]) : '_lobby',
        ];
    }

    public static function currencyRatio(string $currency): float
    {
        $currency = strtoupper(trim($currency));
        return (float) (self::CURRENCY_RATIOS[$currency] ?? 1);
    }

    /** Base currency without scale suffix (IDR2 → IDR, MMK3 → MMK). */
    public static function providerBaseCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        if (preg_match('/^([A-Z]{3})\d+$/', $currency, $m) === 1) {
            return (string) $m[1];
        }

        return $currency;
    }

    /** True when currency is a known GSC appendix code (rejects e.g. "Testing"). */
    public static function isSupportedCurrency(string $currency): bool
    {
        $currency = strtoupper(trim($currency));
        if ($currency === '') {
            return false;
        }
        if (isset(self::CURRENCY_RATIOS[$currency])) {
            return true;
        }
        if (in_array($currency, self::BASE_CURRENCIES, true)) {
            return true;
        }
        if (in_array($currency, self::STAGING_CURRENCIES, true)) {
            return true;
        }

        return false;
    }

    /**
     * Provider-facing balance precision:
     * - IDR/VND/KRW/JPY → 2 dp
     * - scaled *2/*3 (IDR2, VND2, …) → 4 dp
     */
    public static function formatProviderBalance(float $amount, string $currency): float
    {
        $currency = strtoupper(trim($currency));
        // A RESETTLED correction may legitimately leave the wallet negative
        // (GSC appendix, Wager Status note), so the sign must survive rounding.
        $sign = $amount < 0 ? -1.0 : 1.0;
        $amount = abs($amount);
        if (self::currencyRatio($currency) > 1) {
            return $sign * (float) number_format($amount, 4, '.', '');
        }
        if (in_array($currency, ['IDR', 'VND', 'KRW', 'JPY'], true)) {
            return $sign * (float) number_format($amount, 2, '.', '');
        }

        return $sign * (float) number_format($amount, 4, '.', '');
    }

    /** Convert provider (GSC) amount → wallet storage amount. */
    public static function toWalletAmount(float $providerAmount, string $currency): float
    {
        return round($providerAmount * self::currencyRatio($currency), 4);
    }

    /**
     * Convert wallet storage amount → provider (GSC) amount.
     * IDR2/VND2 (1:1000): format the base (IDR) value, then divide by the ratio
     * WITHOUT re-rounding. The GSC testcase computes base/1000 in float64 and
     * compares strictly, so returning round(x/1000, 4) fails whenever the
     * division is not exactly representable (e.g. 90598.2/1000).
     */
    public static function toProviderAmount(float $walletAmount, string $currency): float
    {
        $currency = strtoupper(trim($currency));
        $ratio = self::currencyRatio($currency);
        if ($ratio > 1) {
            $baseCurrency = self::providerBaseCurrency($currency);
            $baseAmount = self::formatProviderBalance($walletAmount, $baseCurrency);

            return $baseAmount / $ratio;
        }

        return self::formatProviderBalance($walletAmount, $currency);
    }

    /**
     * Batch-shaped error so every entry in batch_requests gets its own code, as
     * the /balance, /withdraw and /deposit responses are documented (2.1–2.3).
     *
     * @return array{code:int,message:string,data:list<array<string,mixed>>}
     */
    private static function batchError(array $payload, int $code, string $message, bool $withBeforeBalance): array
    {
        $row = $withBeforeBalance
            ? ['before_balance' => 0, 'balance' => 0]
            : ['balance' => 0];

        $data = [];
        $batch = is_array($payload['batch_requests'] ?? null) ? $payload['batch_requests'] : [];
        foreach ($batch as $req) {
            if (!is_array($req)) {
                continue;
            }
            $data[] = [
                'member_account' => trim((string) ($req['member_account'] ?? '')),
                'product_code' => (int) ($req['product_code'] ?? $req['Product_code'] ?? 0),
            ] + $row + ['code' => $code, 'message' => $message];
        }
        if ($data === []) {
            $data[] = $row + ['code' => $code, 'message' => $message];
        }

        return ['code' => $code, 'message' => $message, 'data' => $data];
    }

    public static function operatorSign(string $requestTime, string $secretKey, string $action, string $operatorCode): string
    {
        return md5($requestTime . $secretKey . $action . $operatorCode);
    }

    public static function callbackSign(string $operatorCode, string $requestTime, string $action, string $secretKey): string
    {
        return md5($operatorCode . $requestTime . $action . $secretKey);
    }

    public static function verifyCallbackSign(array $payload, string $action, string $secretKey, string $operatorCode): bool
    {
        $sign = strtolower(trim((string) ($payload['sign'] ?? '')));
        $requestTime = (string) ($payload['request_time'] ?? '');
        if ($sign === '' || $requestTime === '') {
            return false;
        }
        $expected = self::callbackSign($operatorCode, $requestTime, $action, $secretKey);
        return hash_equals($expected, $sign);
    }

    // ─── Wallet callbacks ───────────────────────────────────────────────

    /**
     * @return array{status:int,body:array}
     */
    public static function wallet(PDO $pdo, string $endpoint, array $payload, string $rawBody = ''): array
    {
        $started = microtime(true);
        self::bootstrap($pdo);
        $endpoint = strtolower(trim($endpoint, '/'));
        $actionMap = [
            'balance' => 'getbalance',
            'withdraw' => 'withdraw',
            'deposit' => 'deposit',
            'pushbetdata' => 'pushbetdata',
        ];
        if (!isset($actionMap[$endpoint])) {
            return ['status' => 404, 'body' => ['code' => 999, 'message' => 'NOT_FOUND']];
        }
        $signAction = $actionMap[$endpoint];
        $isBatch = $endpoint !== 'pushbetdata';
        $withBeforeBalance = in_array($endpoint, ['withdraw', 'deposit'], true);
        $errorBody = static fn (int $code, string $message): array => $isBatch
            ? self::batchError($payload, $code, $message, $withBeforeBalance)
            : ['code' => $code, 'message' => $message];

        try {
            $cfg = self::config($pdo);
            if ((int) ($cfg['is_active'] ?? 0) !== 1) {
                $body = $errorBody(999, 'Provider inactive');
                $meta = self::walletLogMeta($pdo, $endpoint, $payload, $body);
                // Seamless wallet protocol: always HTTP 200 with body.code; non-200
                // makes GSC treat the operator as unreachable and retry/mark down.
                self::logWallet($pdo, $endpoint, $meta['user_id'], $meta['member_account'], $meta['transaction_id'], 200, 999, 'INACTIVE', $started, $payload, $body);
                return ['status' => 200, 'body' => $body];
            }
            $operatorCode = trim((string) ($cfg['operator_code'] ?? ''));
            $secretKey = (string) ($cfg['secret_key'] ?? '');
            $reqOp = trim((string) ($payload['operator_code'] ?? ''));
            if ($operatorCode === '' || $secretKey === '' || strcasecmp($reqOp, $operatorCode) !== 0) {
                $body = $errorBody(1002, self::WALLET_CODES[1002]);
                $meta = self::walletLogMeta($pdo, $endpoint, $payload, $body);
                self::logWallet($pdo, $endpoint, $meta['user_id'], $meta['member_account'], $meta['transaction_id'], 200, 1002, 'PROXY_KEY', $started, $payload, $body);
                return ['status' => 200, 'body' => $body];
            }
            if (!self::verifyCallbackSign($payload, $signAction, $secretKey, $operatorCode)) {
                $body = $errorBody(1004, self::WALLET_CODES[1004]);
                $meta = self::walletLogMeta($pdo, $endpoint, $payload, $body);
                self::logWallet($pdo, $endpoint, $meta['user_id'], $meta['member_account'], $meta['transaction_id'], 200, 1004, 'INVALID_SIGN', $started, $payload, $body);
                return ['status' => 200, 'body' => $body];
            }

            $body = match ($endpoint) {
                'balance' => self::walletBalance($pdo, $payload, $cfg),
                'withdraw' => self::walletWithdraw($pdo, $payload, $cfg),
                'deposit' => self::walletDeposit($pdo, $payload, $cfg),
                'pushbetdata' => self::walletPushBetData($pdo, $payload, $cfg),
                default => ['code' => 999, 'message' => 'NOT_FOUND'],
            };
            $meta = self::walletLogMeta($pdo, $endpoint, $payload, $body);
            self::logWallet(
                $pdo,
                $endpoint,
                $meta['user_id'],
                $meta['member_account'],
                $meta['transaction_id'],
                200,
                $meta['status_code'],
                $meta['error_code'],
                $started,
                $payload,
                $body
            );
            self::fileLog('wallet.' . $endpoint, [
                'member_account' => $meta['member_account'],
                'transaction_id' => $meta['transaction_id'],
                'status_code' => $meta['status_code'],
                'error_code' => $meta['error_code'],
                'top_code' => (int) ($body['code'] ?? 0),
            ]);
            return ['status' => 200, 'body' => $body];
        } catch (Throwable $e) {
            $body = $errorBody(999, 'Internal Server Error');
            error_log('[GSC+] wallet ' . $endpoint . ': ' . $e->getMessage());
            self::fileLog('wallet.exception', ['endpoint' => $endpoint, 'error' => $e->getMessage()]);
            $meta = self::walletLogMeta($pdo, $endpoint, $payload, $body);
            self::logWallet(
                $pdo,
                $endpoint,
                $meta['user_id'],
                $meta['member_account'],
                $meta['transaction_id'],
                200,
                999,
                'EXCEPTION',
                $started,
                $payload,
                $body + ['error' => $e->getMessage()]
            );
            return ['status' => 200, 'body' => $body];
        }
    }

    private static function walletBalance(PDO $pdo, array $payload, array $cfg): array
    {
        $currency = self::resolveWalletRequestCurrency($payload, $cfg);
        if (!self::isSupportedCurrency($currency)) {
            return self::batchError($payload, 999, 'Invalid currency', false);
        }

        $batch = is_array($payload['batch_requests'] ?? null) ? $payload['batch_requests'] : [];
        $data = [];
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
                    'product_code' => $productCode,
                    'balance' => 0,
                    'code' => 1000,
                    'message' => self::WALLET_CODES[1000],
                ];
                continue;
            }
            // users.balance is the seamless IDR ledger — report 1:1 (or scaled for IDR2).
            $walletBal = round((float) ($user['balance'] ?? 0), 4);
            $data[] = [
                'member_account' => $member,
                'product_code' => $productCode,
                'balance' => self::toProviderAmount($walletBal, $currency),
                'code' => 0,
                'message' => '',
            ];
        }

        return ['code' => 0, 'message' => '', 'data' => $data];
    }

    private static function walletWithdraw(PDO $pdo, array $payload, array $cfg): array
    {
        return self::walletMoneyBatch($pdo, $payload, $cfg, 'withdraw');
    }

    private static function walletDeposit(PDO $pdo, array $payload, array $cfg): array
    {
        return self::walletMoneyBatch($pdo, $payload, $cfg, 'deposit');
    }

    private static function walletMoneyBatch(PDO $pdo, array $payload, array $cfg, string $direction): array
    {
        $currency = self::resolveWalletRequestCurrency($payload, $cfg);
        if (!self::isSupportedCurrency($currency)) {
            return self::batchError($payload, 999, 'Invalid currency', true);
        }

        $batch = is_array($payload['batch_requests'] ?? null) ? $payload['batch_requests'] : [];
        $data = [];
        foreach ($batch as $req) {
            if (!is_array($req)) {
                continue;
            }
            $member = trim((string) ($req['member_account'] ?? ''));
            $productCode = (int) ($req['product_code'] ?? $req['Product_code'] ?? 0);
            $gameType = (string) ($req['game_type'] ?? $payload['game_type'] ?? '');
            $transactions = is_array($req['transactions'] ?? null) ? $req['transactions'] : [];
            $user = self::userByMemberAccount($pdo, $member);
            if ($user === null) {
                $data[] = [
                    'member_account' => $member,
                    'product_code' => $productCode,
                    'before_balance' => 0,
                    'balance' => 0,
                    'code' => 1000,
                    'message' => self::WALLET_CODES[1000],
                ];
                continue;
            }

            $result = self::applyTransactions(
                $pdo,
                $user,
                $transactions,
                $direction,
                $currency,
                $productCode,
                $gameType
            );
            $data[] = [
                'member_account' => $member,
                'product_code' => $productCode,
                'before_balance' => self::toProviderAmount((float) $result['before_balance'], $currency),
                'balance' => self::toProviderAmount((float) $result['balance'], $currency),
                'code' => (int) ($result['code'] ?? 0),
                'message' => (string) ($result['message'] ?? ''),
            ];
        }

        return ['code' => 0, 'message' => '', 'data' => $data];
    }

    /**
     * Apply all transactions for one member in a single DB transaction.
     * Duplicate txn id → code 1003 with current balances (GSC duplicate rule).
     *
     * @param list<array<string,mixed>> $transactions
     * @return array{before_balance:float,balance:float,code:int,message:string}
     */
    private static function applyTransactions(
        PDO $pdo,
        array $user,
        array $transactions,
        string $direction,
        string $currency,
        int $productCode,
        string $gameType
    ): array {
        $userId = (int) $user['id'];
        $member = (string) ($user['username'] ?? '');

        if ($transactions === []) {
            $bal = round((float) ($user['balance'] ?? 0), 4);
            return ['before_balance' => $bal, 'balance' => $bal, 'code' => 0, 'message' => ''];
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id, username, balance, banned FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
            $stmt->execute([':id' => $userId]);
            $locked = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($locked)) {
                $pdo->rollBack();
                return ['before_balance' => 0, 'balance' => 0, 'code' => 1000, 'message' => self::WALLET_CODES[1000]];
            }
            if ((int) ($locked['banned'] ?? 0) === 1) {
                $pdo->rollBack();
                $bal = round((float) ($locked['balance'] ?? 0), 4);
                // 1000 is "member does not exist" — banned is an operator decision → 999.
                return ['before_balance' => $bal, 'balance' => $bal, 'code' => 999, 'message' => 'Member blocked'];
            }

            $batchBefore = round((float) $locked['balance'], 4);
            $balance = $batchBefore;
            $duplicates = 0;
            $processed = 0;

            foreach ($transactions as $tx) {
                if (!is_array($tx)) {
                    continue;
                }
                $processed++;
                $txnId = trim((string) ($tx['id'] ?? ''));
                if ($txnId === '') {
                    $pdo->rollBack();
                    return [
                        'before_balance' => $batchBefore,
                        'balance' => $batchBefore,
                        'code' => 999,
                        'message' => 'Missing transaction id',
                    ];
                }

                $existing = $pdo->prepare(
                    'SELECT before_balance, after_balance FROM gsc_transactions WHERE transaction_id = :id LIMIT 1'
                );
                $existing->execute([':id' => $txnId]);
                $prev = $existing->fetch(PDO::FETCH_ASSOC);
                if (is_array($prev)) {
                    $duplicates++;
                    // Doc 1003: report current balances, never replay historical after_balance.
                    continue;
                }

                $action = strtoupper(trim((string) ($tx['action'] ?? '')));
                if (!self::isKnownAction($action)) {
                    $pdo->rollBack();
                    return [
                        'before_balance' => $batchBefore,
                        'balance' => $batchBefore,
                        'code' => 999,
                        'message' => 'Invalid action',
                    ];
                }
                // Doc: CANCEL must confirm the bet exists (BET); ROLLBACK confirms settle/exists.
                if (in_array($action, ['CANCEL', 'ROLLBACK'], true)) {
                    $wagerCode = trim((string) ($tx['wager_code'] ?? ''));
                    if (!self::wagerExists($pdo, $wagerCode)) {
                        $pdo->rollBack();
                        return [
                            'before_balance' => $batchBefore,
                            'balance' => $batchBefore,
                            'code' => 1006,
                            'message' => self::WALLET_CODES[1006],
                        ];
                    }
                }
                $providerAmount = (float) ($tx['amount'] ?? 0);
                $deltaWallet = self::resolveWalletDelta($direction, $action, $providerAmount, $currency);
                $before = $balance;
                $after = round($before + $deltaWallet, 4);
                // GSC appendix: RESETTLED may legitimately push a balance negative
                // (fractional sportsbook corrections) and must not be rejected.
                if ($after < 0 && $action !== 'RESETTLED') {
                    $pdo->rollBack();
                    return [
                        'before_balance' => $batchBefore,
                        'balance' => $batchBefore,
                        'code' => 1001,
                        'message' => self::WALLET_CODES[1001],
                    ];
                }

                $pdo->prepare('UPDATE users SET balance = :bal WHERE id = :id')
                    ->execute([':bal' => $after, ':id' => $userId]);

                if (class_exists('WageringService', false) || class_exists('WageringService')) {
                    if (!class_exists('WageringService', false)) {
                        $wageringPath = dirname(__DIR__) . '/services/WageringService.php';
                        if (is_file($wageringPath)) {
                            require_once $wageringPath;
                        }
                    }
                    if (class_exists('WageringService', false)) {
                        if ($action === 'BET' && $deltaWallet < 0) {
                            WageringService::registerBet($pdo, $userId, abs($deltaWallet));
                        } elseif (in_array($action, ['CANCEL', 'ROLLBACK'], true) && $deltaWallet > 0) {
                            WageringService::reverseBet($pdo, $userId, abs($deltaWallet));
                        }
                    }
                }

                $pdo->prepare(
                    'INSERT INTO gsc_transactions
                        (user_id, member_account, transaction_id, action, wager_code, wager_status, wager_type,
                         round_id, product_code, game_code, game_type, channel_code, amount, bet_amount,
                         valid_bet_amount, prize_amount, tip_amount, before_balance, after_balance, currency,
                         settled_at, direction, payload, raw_payload)
                     VALUES
                        (:uid, :member, :txn, :action, :wager, :wstatus, :wtype, :round, :product, :game,
                         :gtype, :channel, :amount, :bet, :vbet, :prize, :tip, :before, :after, :cur,
                         :settled, :dir, :payload, :raw)'
                )->execute([
                    ':uid'     => $userId,
                    ':member'  => $member !== '' ? $member : (string) ($locked['username'] ?? ''),
                    ':txn'     => $txnId,
                    ':action'  => $action,
                    ':wager'   => trim((string) ($tx['wager_code'] ?? '')) ?: null,
                    ':wstatus' => trim((string) ($tx['wager_status'] ?? '')) ?: null,
                    ':wtype'   => trim((string) ($tx['wager_type'] ?? '')) ?: null,
                    ':round'   => trim((string) ($tx['round_id'] ?? '')) ?: null,
                    ':product' => $productCode > 0 ? $productCode : ((int) ($tx['product_code'] ?? 0) ?: null),
                    ':game'    => trim((string) ($tx['game_code'] ?? '')) ?: null,
                    ':gtype'   => $gameType !== '' ? $gameType : (trim((string) ($tx['game_type'] ?? '')) ?: null),
                    ':channel' => trim((string) ($tx['channel_code'] ?? $tx['Channel_code'] ?? '')) ?: null,
                    ':amount'  => self::toWalletAmount($providerAmount, $currency),
                    ':bet'     => self::toWalletAmount((float) ($tx['bet_amount'] ?? 0), $currency),
                    ':vbet'    => self::toWalletAmount((float) ($tx['valid_bet_amount'] ?? 0), $currency),
                    ':prize'   => self::toWalletAmount((float) ($tx['prize_amount'] ?? $tx['prized_amount'] ?? 0), $currency),
                    ':tip'     => self::toWalletAmount((float) ($tx['tip_amount'] ?? 0), $currency),
                    ':before'  => $before,
                    ':after'   => $after,
                    ':cur'     => $currency,
                    ':settled' => isset($tx['settled_at']) ? (int) $tx['settled_at'] : null,
                    ':dir'     => $direction,
                    ':payload' => isset($tx['payload']) ? json_encode($tx['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    ':raw'     => json_encode($tx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);

                $balance = $after;
            }

            $pdo->commit();
            // Already-seen ids are skipped rather than replayed. 1003 is only
            // reported when nothing new was applied, so a mixed batch still
            // acknowledges the transactions it did process.
            if ($duplicates > 0 && $duplicates === $processed) {
                return [
                    'before_balance' => $batchBefore,
                    'balance' => $batchBefore,
                    'code' => 1003,
                    'message' => self::WALLET_CODES[1003],
                ];
            }
            return [
                'before_balance' => $batchBefore,
                'balance' => $balance,
                'code' => 0,
                'message' => '',
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (str_contains($e->getMessage(), '1062') || stripos($e->getMessage(), 'Duplicate') !== false) {
                $balStmt = $pdo->prepare('SELECT balance FROM users WHERE id = :id LIMIT 1');
                $balStmt->execute([':id' => $userId]);
                $bal = round((float) ($balStmt->fetchColumn() ?: 0), 4);
                return [
                    'before_balance' => $bal,
                    'balance' => $bal,
                    'code' => 1003,
                    'message' => self::WALLET_CODES[1003],
                ];
            }
            throw $e;
        }
    }

    /** Only the appendix Transaction Action Types are accepted (e.g. rejects INVALID_ACTION). */
    private static function isKnownAction(string $action): bool
    {
        return in_array($action, self::DEBIT_ACTIONS, true)
            || in_array($action, self::CREDIT_ACTIONS, true)
            || in_array($action, self::SIGNED_ACTIONS, true);
    }

    /** Wager is known if seen in wallet transactions or pushed bet data. */
    private static function wagerExists(PDO $pdo, string $wagerCode): bool
    {
        if ($wagerCode === '') {
            return false;
        }
        $stmt = $pdo->prepare('SELECT id FROM gsc_transactions WHERE wager_code = :w LIMIT 1');
        $stmt->execute([':w' => $wagerCode]);
        if ($stmt->fetchColumn() !== false) {
            return true;
        }
        $stmt = $pdo->prepare('SELECT id FROM gsc_wagers WHERE wager_code = :w LIMIT 1');
        $stmt->execute([':w' => $wagerCode]);

        return $stmt->fetchColumn() !== false;
    }

    private static function resolveWalletDelta(string $direction, string $action, float $providerAmount, string $currency): float
    {
        $walletAbs = self::toWalletAmount(abs($providerAmount), $currency);
        if (in_array($action, self::SIGNED_ACTIONS, true)) {
            // Positive amount → credit; negative → debit (GSC appendix).
            $signed = self::toWalletAmount($providerAmount, $currency);
            return round($signed, 4);
        }
        if ($direction === 'withdraw') {
            if (in_array($action, self::CREDIT_ACTIONS, true)) {
                return round($walletAbs, 4);
            }
            return round(-$walletAbs, 4);
        }
        // deposit
        if (in_array($action, self::DEBIT_ACTIONS, true)) {
            return round(-$walletAbs, 4);
        }
        return round($walletAbs, 4);
    }

    private static function walletPushBetData(PDO $pdo, array $payload, array $cfg): array
    {
        $wagers = is_array($payload['wagers'] ?? null) ? $payload['wagers'] : [];
        $memberMissing = false;
        $saved = 0;
        foreach ($wagers as $wager) {
            if (!is_array($wager)) {
                continue;
            }
            $code = trim((string) ($wager['wager_code'] ?? ''));
            if ($code === '') {
                continue;
            }
            if (self::userByMemberAccount($pdo, trim((string) ($wager['member_account'] ?? ''))) === null) {
                $memberMissing = true;
                continue;
            }
            $currency = strtoupper(trim((string) ($wager['currency'] ?? '')));
            if ($currency === '') {
                $currency = self::configCurrency($cfg);
            }
            $pdo->prepare(
                'INSERT INTO gsc_wagers
                    (member_account, wager_code, wager_status, wager_type, round_id, product_code, game_code,
                     game_type, channel_code, currency, bet_amount, valid_bet_amount, prize_amount, tip_amount,
                     settled_at, wager_created_at, payload, raw_payload)
                 VALUES
                    (:member, :code, :status, :type, :round, :product, :game, :gtype, :channel, :cur,
                     :bet, :vbet, :prize, :tip, :settled, :created, :payload, :raw)
                 ON DUPLICATE KEY UPDATE
                    wager_status = VALUES(wager_status),
                    wager_type = VALUES(wager_type),
                    round_id = VALUES(round_id),
                    product_code = VALUES(product_code),
                    game_code = VALUES(game_code),
                    game_type = VALUES(game_type),
                    channel_code = VALUES(channel_code),
                    currency = VALUES(currency),
                    bet_amount = VALUES(bet_amount),
                    valid_bet_amount = VALUES(valid_bet_amount),
                    prize_amount = VALUES(prize_amount),
                    tip_amount = VALUES(tip_amount),
                    settled_at = VALUES(settled_at),
                    wager_created_at = VALUES(wager_created_at),
                    payload = VALUES(payload),
                    raw_payload = VALUES(raw_payload)'
            )->execute([
                ':member'  => trim((string) ($wager['member_account'] ?? '')),
                ':code'    => $code,
                ':status'  => trim((string) ($wager['wager_status'] ?? '')) ?: null,
                ':type'    => trim((string) ($wager['wager_type'] ?? '')) ?: null,
                ':round'   => trim((string) ($wager['round_id'] ?? '')) ?: null,
                ':product' => isset($wager['product_code']) ? (int) $wager['product_code'] : null,
                ':game'    => trim((string) ($wager['game_code'] ?? '')) ?: null,
                ':gtype'   => trim((string) ($wager['game_type'] ?? '')) ?: null,
                ':channel' => trim((string) ($wager['channel_code'] ?? '')) ?: null,
                ':cur'     => $currency,
                ':bet'     => self::toWalletAmount((float) ($wager['bet_amount'] ?? 0), $currency),
                ':vbet'    => self::toWalletAmount((float) ($wager['valid_bet_amount'] ?? 0), $currency),
                ':prize'   => self::toWalletAmount((float) ($wager['prize_amount'] ?? 0), $currency),
                ':tip'     => self::toWalletAmount((float) ($wager['tip_amount'] ?? 0), $currency),
                ':settled' => isset($wager['settled_at']) ? (int) $wager['settled_at'] : null,
                ':created' => isset($wager['created_at']) ? (int) $wager['created_at'] : null,
                ':payload' => isset($wager['payload']) ? json_encode($wager['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                ':raw'     => json_encode($wager, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            self::creditManualPayoutWager($pdo, $wager, $code, $currency);
            $saved++;
        }
        if ($memberMissing && $saved === 0) {
            return ['code' => 1000, 'message' => self::WALLET_CODES[1000]];
        }
        // Partial batches (some known members, some unknown) still sync what we can;
        // returning 1000 for the whole push made GSC treat a healthy session as failed.
        return ['code' => 0, 'message' => ''];
    }

    /**
     * WBET-style products pay winnings only via pushbetdata; credit the prize
     * once per wager_code (idempotent through gsc_transactions unique txn id).
     */
    private static function creditManualPayoutWager(PDO $pdo, array $wager, string $wagerCode, string $currency): void
    {
        $productCode = (int) ($wager['product_code'] ?? 0);
        if (!in_array($productCode, self::MANUAL_PAYOUT_PRODUCTS, true)) {
            return;
        }
        if (strtoupper(trim((string) ($wager['wager_status'] ?? ''))) !== 'SETTLED') {
            return;
        }
        $prizeWallet = self::toWalletAmount((float) ($wager['prize_amount'] ?? 0), $currency);
        if ($prizeWallet <= 0) {
            return;
        }
        $user = self::userByMemberAccount($pdo, trim((string) ($wager['member_account'] ?? '')));
        if ($user === null) {
            return;
        }
        $txnId = 'wbet:' . $wagerCode;

        $pdo->beginTransaction();
        try {
            $dup = $pdo->prepare('SELECT id FROM gsc_transactions WHERE transaction_id = :id LIMIT 1');
            $dup->execute([':id' => $txnId]);
            if ($dup->fetchColumn() !== false) {
                $pdo->rollBack();
                return;
            }
            $stmt = $pdo->prepare('SELECT id, username, balance FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
            $stmt->execute([':id' => (int) $user['id']]);
            $locked = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($locked)) {
                $pdo->rollBack();
                return;
            }
            $before = round((float) $locked['balance'], 4);
            $after = round($before + $prizeWallet, 4);
            $pdo->prepare('UPDATE users SET balance = :bal WHERE id = :id')
                ->execute([':bal' => $after, ':id' => (int) $locked['id']]);
            $pdo->prepare(
                'INSERT INTO gsc_transactions
                    (user_id, member_account, transaction_id, action, wager_code, wager_status, round_id,
                     product_code, game_code, game_type, amount, prize_amount, before_balance, after_balance,
                     currency, direction, raw_payload)
                 VALUES
                    (:uid, :member, :txn, \'SETTLED\', :wager, \'SETTLED\', :round, :product, :game, :gtype,
                     :amount, :prize, :before, :after, :cur, \'deposit\', :raw)'
            )->execute([
                ':uid'     => (int) $locked['id'],
                ':member'  => (string) ($locked['username'] ?? ''),
                ':txn'     => $txnId,
                ':wager'   => $wagerCode,
                ':round'   => trim((string) ($wager['round_id'] ?? '')) ?: null,
                ':product' => $productCode,
                ':game'    => trim((string) ($wager['game_code'] ?? '')) ?: null,
                ':gtype'   => trim((string) ($wager['game_type'] ?? '')) ?: null,
                ':amount'  => $prizeWallet,
                ':prize'   => $prizeWallet,
                ':before'  => $before,
                ':after'   => $after,
                ':cur'     => $currency,
                ':raw'     => json_encode($wager, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[GSC+] WBET payout ' . $wagerCode . ': ' . $e->getMessage());
        }
    }

    // ─── Operator API ───────────────────────────────────────────────────

    /**
     * @return array{success:bool,code:int,message:string,data?:array,game_url?:string}
     */
    public static function launch(PDO $pdo, ?array $user, array $input): array
    {
        try {
            $cfg = self::activeConfig($pdo);
        } catch (Throwable $e) {
            return ['success' => false, 'code' => 503, 'message' => $e->getMessage()];
        }

        $parsed = self::parseGameId(trim((string) ($input['game_id'] ?? $input['gameId'] ?? '')));
        if ($parsed === null) {
            return ['success' => false, 'code' => 404, 'message' => 'Geçersiz GSC+ oyun kimliği.'];
        }

        $productCode = $parsed['product_code'];
        $gameCode = $parsed['game_code'];
        $isLobby = ($gameCode === '' || $gameCode === '_lobby');

        $currency = self::configCurrency($cfg);
        $gameRow = null;
        if (!$isLobby) {
            // support_currency is a provider-side list ("ALL", "IDR,PHP,MYR"), never a
            // single code, so it must not be matched literally against our currency —
            // syncGames already folded currency support into is_active.
            $preferType = strtoupper(trim((string) ($input['game_type'] ?? '')));
            // Prefer LIVE_CASINO over LIVE_CASINO_PREMIUM when the same game_code
            // collides under uniq(product_code, game_code, support_currency).
            $gameStmt = $pdo->prepare(
                'SELECT * FROM gsc_games
                 WHERE product_code = :p AND game_code = :g AND is_active = 1
                 ORDER BY
                    (product_currency = :c) DESC,
                    CASE
                        WHEN :gt <> \'\' AND UPPER(game_type) = :gt2 THEN 0
                        WHEN UPPER(game_type) = \'LIVE_CASINO\' THEN 1
                        WHEN UPPER(game_type) LIKE \'LIVE_CASINO%\' THEN 2
                        ELSE 3
                    END ASC,
                    id ASC
                 LIMIT 1'
            );
            $gameStmt->execute([
                ':p' => $productCode,
                ':g' => $gameCode,
                ':c' => $currency,
                ':gt' => $preferType,
                ':gt2' => $preferType,
            ]);
            $gameRow = $gameStmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($gameRow)) {
                return ['success' => false, 'code' => 404, 'message' => 'Oyun bulunamadı veya pasif.'];
            }
            $status = strtoupper(trim((string) ($gameRow['status'] ?? '')));
            if ($status !== '' && !in_array($status, ['ACTIVATED', 'ACTIVAT'], true)) {
                return ['success' => false, 'code' => 503, 'message' => 'Oyun bakımda veya pasif.'];
            }
            // Prefer the row's contracted product_currency before support checks —
            // checking against gsc_config (IDR) while the row is CNY wrongly blocked
            // or allowed the wrong wallet path.
            $rowCurrency = strtoupper(trim((string) ($gameRow['product_currency'] ?? '')));
            $supportCurrency = $rowCurrency !== '' && self::isSupportedCurrency($rowCurrency)
                ? $rowCurrency
                : $currency;
            if (!self::gameSupportsCurrency((string) ($gameRow['support_currency'] ?? ''), $supportCurrency)) {
                return ['success' => false, 'code' => 503, 'message' => 'Oyun bu para birimini desteklemiyor.'];
            }
        } else {
            $prodStmt = $pdo->prepare(
                'SELECT * FROM gsc_products
                 WHERE product_code = :p AND is_active = 1
                 ORDER BY (currency = :c) DESC, id ASC
                 LIMIT 1'
            );
            $prodStmt->execute([':p' => $productCode, ':c' => $currency]);
            $prod = $prodStmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($prod)) {
                return ['success' => false, 'code' => 404, 'message' => 'Ürün bulunamadı veya pasif.'];
            }
            $gameRow = [
                'game_type' => (string) ($prod['game_type'] ?? 'SLOT'),
                'entry_type' => (int) ($prod['entry_type'] ?? 2),
                'product_name' => (string) ($prod['product_name'] ?? ''),
                'product_currency' => (string) ($prod['currency'] ?? ''),
            ];
        }

        // Products are contracted per currency (available-products returns one row
        // per currency), so launch-game must send the product's own currency.
        $productCurrency = strtoupper(trim((string) ($gameRow['product_currency'] ?? '')));
        if ($productCurrency !== '' && self::isSupportedCurrency($productCurrency)) {
            $currency = $productCurrency;
        }

        $agentGate = self::assertAgentFundsLaunch($pdo, $currency, is_array($user) ? $user : null);
        if ($agentGate !== null) {
            return $agentGate;
        }

        $isGuest = !is_array($user) || (int) ($user['id'] ?? 0) <= 0;
        if ($isGuest) {
            return ['success' => false, 'code' => 401, 'message' => 'GSC+ oyunu için giriş yapın.'];
        }

        $userId = (int) $user['id'];
        $memberAccount = self::memberAccountFromUser($user);
        $nickname = trim((string) ($user['username'] ?? ('user_' . $userId)));
        $passwordCandidates = self::memberPasswordCandidates($pdo, $cfg, $user);
        $password = $passwordCandidates[0] ?? self::memberPassword($cfg, $user);
        $gameType = strtoupper(trim((string) ($gameRow['game_type'] ?? 'SLOT')));
        $platform = self::resolvePlatform($input);
        $languageCode = (int) ($cfg['language_code'] ?? 0);
        // IDR staging: Pragmatic expects Indonesian locale in the launch URL
        // (language=id). language_code 0 (English) still launches but some UAT
        // lines reject the session with "not logged in".
        if ($languageCode === 0 && strtoupper($currency) === 'IDR') {
            $languageCode = 4;
        }
        // The frontend never sends an explicit "ip" field, so this fell straight
        // through to $_SERVER['REMOTE_ADDR'] — the Cloudflare edge IP on this
        // stack, not the player's. Providers that IP-lock a launched session
        // (Pragmatic Play games showing "It seems you are not logged in." right
        // after opening match this exactly) then reject it because the IP sent
        // at launch never matches the player's real IP once the game client
        // connects. Same detection chain BgamingService already relies on.
        $ip = trim((string) ($input['ip'] ?? ''));
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $ip = self::clientIp();
        }
        // Never send loopback/private IPs to GSC when a public visitor IP was
        // provided by the frontend play page — Pragmatic locks the session to IP.
        if (!self::isPublicIp($ip)) {
            $fromInput = trim((string) ($input['ip'] ?? ''));
            if ($fromInput !== '' && self::isPublicIp($fromInput)) {
                $ip = $fromInput;
            }
        }
        if (!self::isPublicIp($ip)) {
            self::fileLog('launch.bad_ip', [
                'ip' => $ip,
                'input_ip' => (string) ($input['ip'] ?? ''),
                'member' => $memberAccount,
                'game_id' => self::buildGameId($productCode, $isLobby ? '_lobby' : $gameCode),
            ]);
        }
        $lobbyUrl = self::resolveLobbyUrl($cfg, $input);

        $requestTime = (string) time();
        $operatorCode = (string) $cfg['operator_code'];
        $sign = self::operatorSign($requestTime, (string) $cfg['secret_key'], 'launchgame', $operatorCode);

        $body = [
            'operator_code' => $operatorCode,
            'member_account' => $memberAccount,
            'password' => $password,
            'nickname' => $nickname,
            'currency' => $currency,
            'game_code' => $isLobby ? null : $gameCode,
            'product_code' => $productCode,
            'game_type' => $gameType,
            'language_code' => $languageCode,
            'ip' => $ip,
            'platform' => $platform,
            'sign' => $sign,
            'request_time' => (int) $requestTime,
            'operator_lobby_url' => $lobbyUrl,
        ];

        // SABA Sports quick-bet widget (doc 3.1) is only addressed when the caller
        // asks for the Widget platform.
        if ($platform === 'Widget') {
            $widgetId = trim((string) ($input['widget_id'] ?? ''));
            if ($widgetId !== '') {
                $body['widget_id'] = $widgetId;
            }
            if (array_key_exists('is_widget_login', $input)) {
                $body['is_widget_login'] = (bool) $input['is_widget_login'];
            }
        }

        // A handful of live-table sub-vendors (behind DreamGaming/product 1052 and
        // similar "dd"-style front-ends) intermittently answer launch-game with a
        // table/limit selection payload carrying an empty token instead of a ready
        // URL. Treating that as success used to leak the raw JSON into the game
        // iframe and leave the player stuck on the provider's own generic
        // "please re-login" page. One retry clears most of these; if it doesn't,
        // fail cleanly and keep the evidence instead of guessing from screenshots.
        $response = [];
        $url = '';
        $content = '';
        $failureMessage = '';
        $selectedPasswordTag = 'default';
        $totalCandidates = max(1, count($passwordCandidates));
        foreach ($passwordCandidates as $candidateIdx => $candidatePassword) {
            $selectedPasswordTag = self::passwordCandidateTag($candidateIdx, $totalCandidates);
            $body['password'] = $candidatePassword;
            $maxAttempts = 2;
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                try {
                    $response = self::operatorRequest($pdo, 'POST', '/api/operators/launch-game', $body);
                } catch (Throwable $e) {
                    $failureMessage = 'GSC+ launch hatası: ' . $e->getMessage();
                    $response = [];
                    if ($attempt < $maxAttempts) {
                        usleep(200000);
                        continue;
                    }
                    break;
                }

                $code = (int) ($response['code'] ?? 0);
                $url = trim((string) ($response['url'] ?? $response['URL'] ?? ''));
                $content = (string) ($response['content'] ?? $response['Content'] ?? '');

                if ($code !== 200 && $code !== 0) {
                    $msg = trim((string) ($response['message'] ?? $response['Message'] ?? 'Launch failed'));
                    $failureMessage = self::friendlyLaunchFailureMessage($msg, $response, '', '');
                    if ($failureMessage === '') {
                        $failureMessage = 'GSC+: ' . ($msg !== '' ? $msg : ('code ' . $code));
                        $failureMessage .= self::agentBalanceHint($pdo, $currency, $msg);
                    }
                } elseif ($url === '' && $content === '') {
                    $failureMessage = 'GSC+ launch URL dönmedi.';
                } elseif (($issue = self::describeUnusableLaunchPayload($response, $url, $content)) !== null) {
                    $failureMessage = self::friendlyLaunchFailureMessage('', $response, $url, $content)
                        ?: ('Sağlayıcı geçerli bir oyun oturumu döndürmedi (' . $issue . ').');
                } elseif (self::looksLikeUnauthorizedLaunch($url, $content)) {
                    $failureMessage = 'Sağlayıcı oturumu doğrulamadı (not logged in/un-authorized).';
                } else {
                    $failureMessage = '';
                    break 2;
                }

                if ($attempt < $maxAttempts) {
                    usleep(200000);
                }
            }
        }

        if ($failureMessage !== '') {
            self::logLaunchFailure(
                $pdo,
                $userId,
                $memberAccount,
                $productCode,
                $isLobby ? null : $gameCode,
                $gameType,
                $currency,
                $platform,
                $body,
                $response !== [] ? $response : null,
                $failureMessage
            );
            self::fileLog('launch.fail', [
                'user_id' => $userId,
                'member_account' => $memberAccount,
                'product_code' => $productCode,
                'game_code' => $isLobby ? null : $gameCode,
                'game_type' => $gameType,
                'currency' => $currency,
                'platform' => $platform,
                'message' => $failureMessage,
                'provider_code' => (int) ($response['code'] ?? $response['Code'] ?? 0),
                'password_mode' => $selectedPasswordTag,
            ]);
            return ['success' => false, 'code' => 422, 'message' => $failureMessage];
        }

        $pdo->prepare(
            'INSERT INTO gsc_sessions
                (user_id, member_account, product_code, game_code, game_type, currency, platform, launch_url, request_payload, response_payload)
             VALUES (:uid, :member, :product, :game, :gtype, :cur, :platform, :url, :req, :res)'
        )->execute([
            ':uid' => $userId,
            ':member' => $memberAccount,
            ':product' => $productCode,
            ':game' => $isLobby ? null : $gameCode,
            ':gtype' => $gameType,
            ':cur' => $currency,
            ':platform' => $platform,
            ':url' => $url !== '' ? $url : null,
            ':req' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':res' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $launchUrl = $url !== '' ? $url : ('data:text/html;charset=utf-8,' . rawurlencode($content));
        // Provider URLs need a top-level navigation: session cookies on
        // efinity.prerelease-env.biz / similar hosts are third-party inside our
        // play iframe and Chrome blocks them → Pragmatic "Un-Authorized / please
        // re-log in" HTML even after a successful launch-game. HTML content
        // launches (no external URL) can still use the iframe/srcdoc path.
        $openMode = $url !== '' ? 'redirect' : 'iframe';
        self::fileLog('launch.ok', [
            'user_id' => $userId,
            'member_account' => $memberAccount,
            'product_code' => $productCode,
            'game_code' => $isLobby ? null : $gameCode,
            'game_type' => $gameType,
            'currency' => $currency,
            'platform' => $platform,
            'ip' => $ip,
            'password_mode' => $selectedPasswordTag,
            'open_mode' => $openMode,
            'has_url' => $url !== '',
            'url_host' => $url !== '' ? (string) (parse_url($url, PHP_URL_HOST) ?: '') : null,
        ]);
        return [
            'success' => true,
            'code' => 200,
            'message' => 'Oyun başlatıldı.',
            'data' => [
                'game_url' => $launchUrl,
                'launch_url' => $launchUrl,
                'open_mode' => $openMode,
                'mode' => 'real',
                'content' => $content !== '' && $url === '' ? $content : null,
            ],
            'game_url' => $launchUrl,
            'open_mode' => $openMode,
        ];
    }

    /**
     * 3.12 Wallet Balance Inquiry — agent wallet balances per contracted currency.
     *
     * @return array{is_credit:bool,currencies:list<array{currency:string,current_balance:float,updated_at:string}>}
     */
    public static function agentWalletBalance(PDO $pdo): array
    {
        $cfg = self::activeConfig($pdo);
        $requestTime = (string) (int) round(microtime(true) * 1000);
        $operatorCode = (string) $cfg['operator_code'];
        $sign = self::operatorSign($requestTime, (string) $cfg['secret_key'], 'getwalletcurrencies', $operatorCode);
        $query = http_build_query([
            'operator_code' => $operatorCode,
            'sign' => $sign,
            'request_time' => $requestTime,
        ]);
        $response = self::operatorRequest($pdo, 'GET', '/api/operators/wallet-balance?' . $query);
        $code = (int) ($response['code'] ?? -1);
        if ($code !== 0) {
            throw new RuntimeException(
                'GSC+ wallet-balance: ' . trim((string) ($response['message'] ?? ('code ' . $code)))
            );
        }
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $currencies = [];
        foreach ((is_array($data['currencies'] ?? null) ? $data['currencies'] : []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $currencies[] = [
                'currency' => strtoupper(trim((string) ($row['currency'] ?? ''))),
                'current_balance' => (float) ($row['current_balance'] ?? 0),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ];
        }

        return [
            'is_credit' => (bool) ($data['is_credit'] ?? false),
            'currencies' => $currencies,
        ];
    }

    /**
     * 3.2 Wager List — settlement-time window, capped at 5 minutes by the provider.
     *
     * @param int $startMs Settlement window start (millisecond timestamp).
     * @param int $endMs   Settlement window end (millisecond timestamp).
     * @return array{wagers:list<array<string,mixed>>,pagination:array<string,mixed>}
     */
    public static function wagerList(PDO $pdo, int $startMs, int $endMs, int $offset = 0, int $size = 5000): array
    {
        if ($endMs <= $startMs) {
            throw new RuntimeException('GSC+ wager list: end, start değerinden büyük olmalı.');
        }
        if (($endMs - $startMs) > 5 * 60 * 1000) {
            throw new RuntimeException('GSC+ wager list: zaman aralığı en fazla 5 dakika olabilir.');
        }

        $params = [
            'start' => $startMs,
            'end' => $endMs,
            'size' => max(1, min(5000, $size)),
        ];
        if ($offset > 0) {
            $params['offset'] = $offset;
        }
        $response = self::signedGet($pdo, '/api/operators/wagers', 'getwagers', $params);

        return [
            'wagers' => array_values(array_filter(
                is_array($response['wagers'] ?? null) ? $response['wagers'] : [],
                'is_array'
            )),
            'pagination' => is_array($response['pagination'] ?? null) ? $response['pagination'] : [],
        ];
    }

    /**
     * 3.3 Wager — single bet by numeric id or wager code.
     *
     * @return array<string,mixed>
     */
    public static function wager(PDO $pdo, string $idOrCode): array
    {
        $idOrCode = trim($idOrCode);
        if ($idOrCode === '') {
            throw new RuntimeException('GSC+ wager: id veya code zorunlu.');
        }
        $response = self::signedGet($pdo, '/api/operators/wagers/' . rawurlencode($idOrCode), 'getwager');

        return is_array($response['wager'] ?? null) ? $response['wager'] : [];
    }

    /**
     * 3.5 Game History — returns a URL or, for some providers (PG Soft), raw HTML.
     *
     * @return array{content:string}
     */
    public static function gameHistory(PDO $pdo, string $wagerCode): array
    {
        $wagerCode = trim($wagerCode);
        if ($wagerCode === '') {
            throw new RuntimeException('GSC+ game history: wager_code zorunlu.');
        }
        $response = self::signedGet(
            $pdo,
            '/api/operators/' . rawurlencode($wagerCode) . '/game-history',
            'gamehistory'
        );

        return ['content' => (string) ($response['content'] ?? '')];
    }

    /**
     * 3.7 Turn on Super Lobby — $type 0 = Super Lobby, 1 = Aurora LIVE.
     */
    public static function launchSuperLobby(PDO $pdo, array $user, array $input = [], int $type = 0): string
    {
        $cfg = self::activeConfig($pdo);
        if ((int) ($user['id'] ?? 0) <= 0) {
            throw new RuntimeException('Super Lobby için giriş yapın.');
        }

        $requestTime = time();
        $operatorCode = (string) $cfg['operator_code'];
        $lobbyUrl = self::resolveLobbyUrl($cfg, $input);
        $response = self::operatorRequest($pdo, 'POST', '/superlobby/launch', [
            'operator_code' => $operatorCode,
            'member_account' => self::memberAccountFromUser($user),
            'nickname' => trim((string) ($user['username'] ?? '')) ?: ('user_' . (int) $user['id']),
            'currency' => self::configCurrency($cfg),
            'language_code' => (int) ($cfg['language_code'] ?? 0),
            'platform' => self::resolvePlatform($input),
            'sign' => self::operatorSign((string) $requestTime, (string) $cfg['secret_key'], 'launchsuperlobby', $operatorCode),
            'request_time' => $requestTime,
            'type' => $type === 1 ? 1 : 0,
            'operator_lobby_url' => $lobbyUrl,
        ]);

        $url = trim((string) ($response['url'] ?? ''));
        if ($url === '') {
            throw new RuntimeException(
                'GSC+ super lobby: ' . (trim((string) ($response['message'] ?? '')) ?: 'URL dönmedi.')
            );
        }

        return $url;
    }

    /**
     * 3.8 Create Free Round for Player.
     *
     * @param list<array{gameId:string,betValues:list<array<string,mixed>>}> $gameList
     * @return string bonus_code
     */
    public static function createFreeRound(
        PDO $pdo,
        string $memberAccount,
        int $productCode,
        string $gameType,
        int $rounds,
        int $startAt,
        int $endAt,
        array $gameList
    ): string {
        $cfg = self::activeConfig($pdo);
        if ($gameList === []) {
            throw new RuntimeException('GSC+ free round: game_list boş olamaz.');
        }
        if ($rounds <= 0) {
            throw new RuntimeException('GSC+ free round: rounds sıfırdan büyük olmalı.');
        }
        if ($endAt <= $startAt) {
            throw new RuntimeException('GSC+ free round: end_at, start_at değerinden büyük olmalı.');
        }

        $requestTime = time();
        $operatorCode = (string) $cfg['operator_code'];
        $response = self::operatorRequest($pdo, 'POST', '/api/operators/create-free-round', [
            'operator_code' => $operatorCode,
            'member_account' => substr(trim($memberAccount), 0, 50),
            'currency' => self::configCurrency($cfg),
            'product_code' => $productCode,
            'game_type' => strtoupper(trim($gameType)),
            'start_at' => $startAt,
            'end_at' => $endAt,
            'rounds' => $rounds,
            'game_list' => $gameList,
            'sign' => self::operatorSign((string) $requestTime, (string) $cfg['secret_key'], 'createfreeround', $operatorCode),
            'request_time' => $requestTime,
            'channel_code' => self::channelCode($cfg),
        ]);

        $bonusCode = trim((string) ($response['bonus_code'] ?? ''));
        if ($bonusCode === '') {
            throw new RuntimeException(
                'GSC+ create free round: ' . (trim((string) ($response['message'] ?? '')) ?: 'bonus_code dönmedi.')
            );
        }

        return $bonusCode;
    }

    /** 3.9 Cancel Free Round. */
    public static function cancelFreeRound(PDO $pdo, string $bonusCode, int $productCode, string $gameType): string
    {
        $cfg = self::activeConfig($pdo);
        $bonusCode = trim($bonusCode);
        if ($bonusCode === '') {
            throw new RuntimeException('GSC+ cancel free round: bonus_code zorunlu.');
        }

        $requestTime = time();
        $operatorCode = (string) $cfg['operator_code'];
        $response = self::operatorRequest($pdo, 'POST', '/api/operators/cancel-free-round', [
            'operator_code' => $operatorCode,
            'currency' => self::configCurrency($cfg),
            'product_code' => $productCode,
            'game_type' => strtoupper(trim($gameType)),
            'bonus_code' => $bonusCode,
            'sign' => self::operatorSign((string) $requestTime, (string) $cfg['secret_key'], 'cancelfreeround', $operatorCode),
            'request_time' => $requestTime,
            'channel_code' => self::channelCode($cfg),
        ]);

        return trim((string) ($response['bonus_code'] ?? $bonusCode));
    }

    /**
     * 3.10 Get Player Free Round Bonus.
     *
     * @return list<array<string,mixed>>
     */
    public static function playerFreeRounds(PDO $pdo, string $memberAccount, int $productCode, string $gameType): array
    {
        $cfg = self::activeConfig($pdo);
        $response = self::signedGet($pdo, '/api/operators/get-player-frb', 'getplayersfrb', [
            'member_account' => substr(trim($memberAccount), 0, 50),
            'currency' => self::configCurrency($cfg),
            'product_code' => $productCode,
            'game_type' => strtoupper(trim($gameType)),
            'channel_code' => self::channelCode($cfg),
        ]);

        return array_values(array_filter(
            is_array($response['bonuses'] ?? null) ? $response['bonuses'] : [],
            'is_array'
        ));
    }

    /**
     * 3.11 Get Game Bet Scales — bet steps per game and currency.
     *
     * @param list<string> $gameIds Max 50 game ids.
     * @return list<array<string,mixed>>
     */
    public static function gameBetScales(PDO $pdo, array $gameIds, int $productCode, string $gameType): array
    {
        $cfg = self::activeConfig($pdo);
        $gameIds = array_values(array_filter(array_map(
            static fn (mixed $id): string => trim((string) $id),
            $gameIds
        ), static fn (string $id): bool => $id !== ''));
        if ($gameIds === []) {
            throw new RuntimeException('GSC+ bet scales: bet_game_list boş olamaz.');
        }
        if (count($gameIds) > 50) {
            throw new RuntimeException('GSC+ bet scales: en fazla 50 oyun sorgulanabilir.');
        }

        $response = self::signedGet($pdo, '/api/operators/get-bet-scales', 'getbetscales', [
            'currency' => self::configCurrency($cfg),
            'product_code' => $productCode,
            'game_type' => strtoupper(trim($gameType)),
            'bet_game_list' => implode(',', $gameIds),
            'channel_code' => self::channelCode($cfg),
        ]);

        return array_values(array_filter(
            is_array($response['betScales'] ?? null) ? $response['betScales'] : [],
            'is_array'
        ));
    }

    /**
     * 3.13 Auto Deposit — agent wallet top-up URL, valid for 900 seconds.
     * Must be enabled by GSC+ before use.
     */
    public static function autoDepositOrder(PDO $pdo, float $amount, string $paymentCurrency = 'USDT'): string
    {
        $cfg = self::activeConfig($pdo);
        if ($amount <= 0) {
            throw new RuntimeException('GSC+ auto deposit: amount sıfırdan büyük olmalı.');
        }

        $requestTime = (string) time();
        $operatorCode = (string) $cfg['operator_code'];
        $response = self::operatorRequest($pdo, 'POST', '/api/operators/recharge/order', [
            'operator_code' => $operatorCode,
            'payment_currency' => strtoupper(trim($paymentCurrency)),
            'deposit_currency' => self::configCurrency($cfg),
            'amount' => $amount,
            'request_time' => $requestTime,
            'sign' => self::operatorSign($requestTime, (string) $cfg['secret_key'], 'autodeposit', $operatorCode),
        ]);

        $url = trim((string) ($response['url'] ?? ''));
        if ($url === '') {
            throw new RuntimeException(
                'GSC+ auto deposit: ' . (trim((string) ($response['message'] ?? '')) ?: 'URL dönmedi.')
            );
        }

        return $url;
    }

    /**
     * Signed GET against the operator API. operator_code/sign/request_time are
     * always appended, per the shared signature scheme in section 3.
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private static function signedGet(PDO $pdo, string $path, string $action, array $params = []): array
    {
        $cfg = self::activeConfig($pdo);
        $requestTime = (string) time();
        $operatorCode = (string) $cfg['operator_code'];
        $query = http_build_query($params + [
            'operator_code' => $operatorCode,
            'sign' => self::operatorSign($requestTime, (string) $cfg['secret_key'], $action, $operatorCode),
            'request_time' => $requestTime,
        ]);
        $response = self::operatorRequest($pdo, 'GET', $path . '?' . $query);

        $code = (int) ($response['code'] ?? 0);
        if ($code !== 0 && $code !== 200) {
            throw new RuntimeException(
                'GSC+ ' . $action . ': ' . (trim((string) ($response['message'] ?? '')) ?: ('code ' . $code))
            );
        }

        return $response;
    }

    private static function channelCode(array $cfg): string
    {
        return trim((string) ($cfg['channel_code'] ?? '')) ?: 'gscp';
    }

    /** @return array{count:int} */
    public static function syncProducts(PDO $pdo): array
    {
        $cfg = self::activeConfig($pdo);
        $requestTime = (string) time();
        $operatorCode = (string) $cfg['operator_code'];
        $sign = self::operatorSign($requestTime, (string) $cfg['secret_key'], 'productlist', $operatorCode);
        $query = http_build_query([
            'operator_code' => $operatorCode,
            'sign' => $sign,
            'request_time' => $requestTime,
        ]);
        $response = self::operatorRequest($pdo, 'GET', '/api/operators/available-products?' . $query);
        $list = self::extractList($response, ['data', 'products', 'available_products']);
        if ($list === [] && array_is_list($response)) {
            $list = $response;
        }

        $operatorCurrency = self::configCurrency($cfg);
        $walletCurrencies = null;
        try {
            $wallet = self::agentWalletBalance($pdo);
            $walletCurrencies = array_column($wallet['currencies'] ?? [], 'currency');
        } catch (Throwable) {
            // Fall back to the onboarding currency set below.
        }
        $allowedCurrencies = self::contractedCurrencies(
            is_array($walletCurrencies) ? $walletCurrencies : null
        );
        $count = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }
            $productCode = (int) ($item['product_code'] ?? 0);
            if ($productCode <= 0) {
                continue;
            }
            $currency = strtoupper(trim((string) ($item['currency'] ?? '')));
            if ($currency === '') {
                $currency = $operatorCurrency;
            }
            $status = strtoupper(trim((string) ($item['status'] ?? '')));
            // VGY1 staging contracts products per currency (IDR / IDR2 / CNY / VND /
            // VND2). Activating only the single gsc_config.currency row hid every
            // CNY/VND/IDR2 line (ALLBET, WM, BigGaming, …) even though the agent
            // wallet funds them. Prefer the onboarding allowlist; if the API
            // returns a product we have not mapped yet, still activate it when
            // its currency is contracted.
            $currencyOk = in_array($currency, $allowedCurrencies, true);
            $productOk = self::isStagingContractedProduct($productCode, $currency)
                || !isset(self::STAGING_PRODUCTS_BY_CURRENCY[$currency]);
            $isActive = in_array($status, ['ACTIVATED', ''], true)
                && $currencyOk
                && $productOk;
            $pdo->prepare(
                'INSERT INTO gsc_products
                    (product_code, product_id, provider_id, provider, product_name, game_type, currency, status,
                     entry_type, is_active, raw_payload, synced_at)
                 VALUES
                    (:pc, :pid, :provid, :provider, :pname, :gtype, :cur, :status, :entry, :active, :raw, :synced)
                 ON DUPLICATE KEY UPDATE
                    product_id = VALUES(product_id),
                    provider_id = VALUES(provider_id),
                    provider = VALUES(provider),
                    product_name = VALUES(product_name),
                    game_type = VALUES(game_type),
                    status = VALUES(status),
                    entry_type = VALUES(entry_type),
                    is_active = VALUES(is_active),
                    raw_payload = VALUES(raw_payload),
                    synced_at = VALUES(synced_at)'
            )->execute([
                ':pc' => $productCode,
                ':pid' => isset($item['product_id']) ? (int) $item['product_id'] : null,
                ':provid' => isset($item['provider_id']) ? (int) $item['provider_id'] : null,
                ':provider' => trim((string) ($item['provider'] ?? '')),
                ':pname' => trim((string) ($item['product_name'] ?? '')),
                ':gtype' => strtoupper(trim((string) ($item['game_type'] ?? ''))),
                ':cur' => $currency,
                ':status' => $status,
                ':entry' => (int) ($item['entry_type'] ?? 1),
                ':active' => $isActive ? 1 : 0,
                ':raw' => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':synced' => $now,
            ]);
            $count++;
        }

        $pdo->prepare('UPDATE gsc_config SET products_synced_at = :t WHERE id = 1')->execute([':t' => $now]);
        return ['count' => $count];
    }

    /**
     * Products + live-casino game lists in one pass (deploy tool / remote sync).
     *
     * @return array{products:int,live_products:int,games:int,errors:list<string>}
     */
    public static function syncLiveCasinoCatalog(PDO $pdo): array
    {
        $errors = [];
        $productCount = 0;
        try {
            $productCount = (int) self::syncProducts($pdo)['count'];
        } catch (Throwable $e) {
            $errors[] = 'products: ' . $e->getMessage();
        }

        $liveProducts = [];
        try {
            $stmt = $pdo->query(
                "SELECT product_code FROM gsc_products
                 WHERE is_active = 1 AND UPPER(game_type) IN ('LIVE_CASINO','LIVE_CASINO_PREMIUM')
                 ORDER BY product_code"
            );
            $liveProducts = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        } catch (Throwable $e) {
            $errors[] = 'live products: ' . $e->getMessage();
        }

        // available-products lists a product once per game type, so the same code can
        // appear twice (1006 = LIVE_CASINO + LIVE_CASINO_PREMIUM); syncGames covers
        // every game type of a code in one call.
        $liveProducts = array_values(array_unique(array_map('intval', $liveProducts)));

        $games = 0;
        $startedAt = date('Y-m-d H:i:s');
        $syncedProducts = 0;
        foreach ($liveProducts as $productCode) {
            if ($productCode <= 0) {
                continue;
            }
            try {
                $games += (int) self::syncGames($pdo, $productCode)['count'];
                $syncedProducts++;
            } catch (Throwable $e) {
                $errors[] = 'product ' . $productCode . ': ' . $e->getMessage();
            }
        }

        // Deactivate stale live rows (e.g. leftovers from a previous currency).
        if ($syncedProducts > 0 && $errors === []) {
            try {
                $pdo->prepare(
                    "UPDATE gsc_games SET is_active = 0
                     WHERE UPPER(game_type) IN ('LIVE_CASINO','LIVE_CASINO_PREMIUM')
                       AND (synced_at IS NULL OR synced_at < :started)"
                )->execute([':started' => $startedAt]);
            } catch (Throwable $e) {
                $errors[] = 'stale cleanup: ' . $e->getMessage();
            }
        }

        return [
            'products' => $productCount,
            'live_products' => count($liveProducts),
            'games' => $games,
            'errors' => $errors,
        ];
    }

    /** @return array<string,mixed> Catalog counters for diagnostics (health endpoint). */
    public static function catalogStatus(PDO $pdo): array
    {
        $status = [];
        try {
            self::bootstrap($pdo);
            $status['configured'] = self::isConfigured($pdo);
            $cfg = self::config($pdo);
            $status['currency'] = (string) ($cfg['currency'] ?? '');
            $status['products_synced_at'] = (string) ($cfg['products_synced_at'] ?? '');
            $status['games_synced_at'] = (string) ($cfg['games_synced_at'] ?? '');
            $status['products_total'] = (int) $pdo->query('SELECT COUNT(*) FROM gsc_products')->fetchColumn();
            $status['products_live'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM gsc_products WHERE is_active = 1 AND UPPER(game_type) IN ('LIVE_CASINO','LIVE_CASINO_PREMIUM')"
            )->fetchColumn();
            $status['games_total'] = (int) $pdo->query('SELECT COUNT(*) FROM gsc_games')->fetchColumn();
            $status['games_live_active'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM gsc_games WHERE is_active = 1 AND UPPER(game_type) IN ('LIVE_CASINO','LIVE_CASINO_PREMIUM')"
            )->fetchColumn();
        } catch (Throwable $e) {
            $status['error'] = $e->getMessage();
        }
        return $status;
    }

    /**
     * GSC game lists report support_currency as "ALL", a single code ("IDR")
     * or a comma-separated list ("MYR,IDR,PHP"). Empty means unrestricted.
     */
    public static function gameSupportsCurrency(string $supportCurrency, string $currency): bool
    {
        $supportCurrency = strtoupper(trim($supportCurrency));
        if ($supportCurrency === '' || $supportCurrency === 'ALL') {
            return true;
        }
        $currency = strtoupper(trim($currency));
        // A scaled contract (IDR2) is the same provider currency as its base (IDR),
        // which is how game lists spell it.
        $base = self::providerBaseCurrency($currency);
        foreach (explode(',', $supportCurrency) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === $currency || $candidate === $base) {
                return true;
            }
        }
        return false;
    }

    /** @return array{count:int,products:int} */
    public static function syncGames(PDO $pdo, ?int $onlyProductCode = null): array
    {
        $cfg = self::activeConfig($pdo);
        $operatorCurrency = self::configCurrency($cfg);
        $columns = 'product_code, game_type, entry_type, provider, product_name, currency';

        if ($onlyProductCode !== null && $onlyProductCode > 0) {
            // Prefer the synced product rows so provider/product_name/entry_type stay
            // accurate. A product code can carry several game types (1006 is listed as
            // both LIVE_CASINO and LIVE_CASINO_PREMIUM) and each has its own game list.
            $stmt = $pdo->prepare(
                "SELECT {$columns} FROM gsc_products WHERE product_code = :pc AND is_active = 1"
            );
            $stmt->execute([':pc' => $onlyProductCode]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if ($products === []) {
                $products = [[
                    'product_code' => $onlyProductCode,
                    'game_type' => '',
                    'entry_type' => 1,
                    'provider' => '',
                    'product_name' => '',
                    'currency' => $operatorCurrency,
                ]];
            }
        } else {
            $prodStmt = $pdo->query("SELECT {$columns} FROM gsc_products WHERE is_active = 1");
            $products = $prodStmt ? $prodStmt->fetchAll(PDO::FETCH_ASSOC) : [];
            if ($products === []) {
                self::syncProducts($pdo);
                $prodStmt = $pdo->query("SELECT {$columns} FROM gsc_products WHERE is_active = 1");
                $products = $prodStmt ? $prodStmt->fetchAll(PDO::FETCH_ASSOC) : [];
            }
        }

        $count = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($products as $product) {
            $productCode = (int) ($product['product_code'] ?? 0);
            if ($productCode <= 0) {
                continue;
            }
            $gameType = strtoupper(trim((string) ($product['game_type'] ?? '')));
            $currency = strtoupper(trim((string) ($product['currency'] ?? ''))) ?: $operatorCurrency;
            $requestTime = (string) time();
            $operatorCode = (string) $cfg['operator_code'];
            $sign = self::operatorSign($requestTime, (string) $cfg['secret_key'], 'gamelist', $operatorCode);
            $params = [
                'product_code' => $productCode,
                'operator_code' => $operatorCode,
                'sign' => $sign,
                'request_time' => $requestTime,
            ];
            if ($gameType !== '') {
                $params['game_type'] = $gameType;
            }
            try {
                $response = self::operatorRequest(
                    $pdo,
                    'GET',
                    '/api/operators/provider-games?' . http_build_query($params)
                );
            } catch (Throwable $e) {
                error_log('[GSC+] syncGames product ' . $productCode . ': ' . $e->getMessage());
                continue;
            }

            $games = self::extractList($response, ['provider_games', 'data', 'games']);
            foreach ($games as $game) {
                if (!is_array($game)) {
                    continue;
                }
                $code = trim((string) ($game['game_code'] ?? ''));
                if ($code === '') {
                    continue;
                }
                $supportCurrency = strtoupper(trim((string) ($game['support_currency'] ?? '')));
                $status = strtoupper(trim((string) ($game['status'] ?? 'ACTIVATED')));
                $active = in_array($status, ['ACTIVATED', 'ACTIVAT'], true) ? 1 : 0;
                if (!self::gameSupportsCurrency($supportCurrency, $currency)) {
                    // Keep other-currency rows for reference, but hide them from our lobby.
                    $active = 0;
                }
                $pdo->prepare(
                    'INSERT INTO gsc_games
                        (product_code, game_code, game_name, game_type, image_url, support_currency,
                         product_currency, status, allow_free_round, entry_type, provider, product_name,
                         lang_name, lang_icon, provider_created_at, raw_payload, is_active, synced_at)
                     VALUES
                        (:pc, :gc, :gn, :gt, :img, :cur, :pcur, :status, :fr, :entry, :provider, :pname,
                         :lname, :licon, :created, :raw, :active, :synced)
                     ON DUPLICATE KEY UPDATE
                        game_name = VALUES(game_name),
                        game_type = VALUES(game_type),
                        image_url = VALUES(image_url),
                        product_currency = VALUES(product_currency),
                        status = VALUES(status),
                        allow_free_round = VALUES(allow_free_round),
                        entry_type = VALUES(entry_type),
                        provider = VALUES(provider),
                        product_name = VALUES(product_name),
                        lang_name = VALUES(lang_name),
                        lang_icon = VALUES(lang_icon),
                        provider_created_at = VALUES(provider_created_at),
                        raw_payload = VALUES(raw_payload),
                        is_active = VALUES(is_active),
                        synced_at = VALUES(synced_at)'
                )->execute([
                    ':pc' => (int) ($game['product_code'] ?? $productCode),
                    ':gc' => $code,
                    ':gn' => trim((string) ($game['game_name'] ?? $code)),
                    ':gt' => strtoupper(trim((string) ($game['game_type'] ?? $gameType))),
                    ':img' => trim((string) ($game['image_url'] ?? '')) ?: null,
                    ':cur' => substr($supportCurrency, 0, 64),
                    ':pcur' => $currency,
                    ':status' => $status,
                    ':fr' => !empty($game['allow_free_round']) ? 1 : 0,
                    ':entry' => (int) ($product['entry_type'] ?? 1),
                    ':provider' => trim((string) ($product['provider'] ?? '')),
                    ':pname' => trim((string) ($product['product_name'] ?? '')),
                    ':lname' => isset($game['lang_name']) ? json_encode($game['lang_name'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    ':licon' => isset($game['lang_icon']) ? json_encode($game['lang_icon'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    ':created' => isset($game['created_at']) ? (int) $game['created_at'] : null,
                    ':raw' => json_encode($game, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ':active' => $active,
                    ':synced' => $now,
                ]);
                $count++;
            }

            // Lobby-only products: ensure a synthetic lobby row for launch.
            if ((int) ($product['entry_type'] ?? 1) === 2) {
                $pdo->prepare(
                    'INSERT INTO gsc_games
                        (product_code, game_code, game_name, game_type, support_currency, product_currency,
                         status, entry_type, provider, product_name, is_active, synced_at)
                     VALUES
                        (:pc, \'_lobby\', :gn, :gt, :cur, :pcur, \'ACTIVATED\', 2, :provider, :pname, 1, :synced)
                     ON DUPLICATE KEY UPDATE
                        game_name = VALUES(game_name),
                        game_type = VALUES(game_type),
                        product_currency = VALUES(product_currency),
                        is_active = 1,
                        synced_at = VALUES(synced_at)'
                )->execute([
                    ':pc' => $productCode,
                    ':gn' => trim((string) ($product['product_name'] ?? ('Product ' . $productCode))) . ' Lobby',
                    ':gt' => $gameType !== '' ? $gameType : 'SLOT',
                    ':cur' => $currency,
                    ':pcur' => $currency,
                    ':provider' => trim((string) ($product['provider'] ?? '')),
                    ':pname' => trim((string) ($product['product_name'] ?? '')),
                    ':synced' => $now,
                ]);
                $count++;
            }
        }

        $pdo->prepare('UPDATE gsc_config SET games_synced_at = :t WHERE id = 1')->execute([':t' => $now]);
        return ['count' => $count, 'products' => count($products)];
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    /**
     * Buy-in agent wallet is per currency. Listing CNY/IDR2 games while only IDR
     * is funded produces GSC+'s opaque "insufficient agent balance". Fail early
     * with the exact currency mismatch instead.
     *
     * @return array{success:bool,code:int,message:string}|null
     */
    private static function assertAgentFundsLaunch(PDO $pdo, string $launchCurrency, ?array $user): ?array
    {
        $launchCurrency = strtoupper(trim($launchCurrency));
        if ($launchCurrency === '') {
            return null;
        }

        try {
            $wallet = self::agentWalletBalance($pdo);
        } catch (Throwable) {
            // Don't block launch if 3.12 is temporarily down — GSC+ will still answer.
            return null;
        }

        $byCode = [];
        foreach (($wallet['currencies'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = strtoupper(trim((string) ($row['currency'] ?? '')));
            if ($code === '') {
                continue;
            }
            $byCode[$code] = (float) ($row['current_balance'] ?? 0);
        }

        $agentBal = $byCode[$launchCurrency] ?? null;
        $summary = [];
        foreach ($byCode as $code => $bal) {
            $summary[] = $code . '=' . number_format($bal, 4, '.', '');
        }
        $summaryText = $summary !== [] ? implode(', ', $summary) : 'yok';

        if ($agentBal === null) {
            return [
                'success' => false,
                'code' => 422,
                'message' => 'Bu oyun ' . $launchCurrency . ' ile açılıyor ama agent wallet’ta bu currency yok. '
                    . 'Mevcut: ' . $summaryText . '. IDR test için Pragmatic/DreamGaming (IDR) seçin; '
                    . 'ALLBET/WM CNY, BigGaming IDR2 ister.',
            ];
        }

        if ($agentBal <= 0) {
            return [
                'success' => false,
                'code' => 422,
                'message' => 'Agent wallet (buy-in) ' . $launchCurrency . ' bakiyesi 0. '
                    . 'Sizde IDR dolu olsa bile bu ürün ' . $launchCurrency . ' credit ister. '
                    . 'Agent: ' . $summaryText,
            ];
        }

        // Player seamless balance (users.balance) is the IDR ledger reported to GSC.
        // Agent kiosk credit is separate — do not require agent >= full player balance;
        // GSC enforces agent sufficiency on bet size during play.
        return null;
    }

    /**
     * "insufficient agent balance" is GSC+'s kiosk/agent wallet (3.12), not the
     * player seamless balance on our site. Append which currency we launched with
     * and what 3.12 currently reports so ops can see IDR vs IDR2 vs CNY mismatches.
     */
    private static function agentBalanceHint(PDO $pdo, string $launchCurrency, string $providerMessage): string
    {
        $needle = strtolower($providerMessage);
        if (
            !str_contains($needle, 'insufficient')
            && !str_contains($needle, 'agent balance')
            && !str_contains($needle, 'not enough')
        ) {
            return '';
        }

        $launchCurrency = strtoupper(trim($launchCurrency));
        $parts = ['launch_currency=' . ($launchCurrency !== '' ? $launchCurrency : '?')];
        try {
            $wallet = self::agentWalletBalance($pdo);
            $parts[] = !empty($wallet['is_credit']) ? 'mode=credit' : 'mode=buy-in';
            $matched = null;
            foreach (($wallet['currencies'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $code = strtoupper(trim((string) ($row['currency'] ?? '')));
                $bal = (float) ($row['current_balance'] ?? 0);
                $parts[] = $code . '=' . $bal;
                if ($code === $launchCurrency) {
                    $matched = $bal;
                }
            }
            if ($matched === null && $launchCurrency !== '') {
                $parts[] = $launchCurrency . '=YOK (bu currency agent wallet’ta yok)';
            } elseif ($matched !== null && $matched <= 0) {
                $parts[] = 'Uyarı: ' . $launchCurrency . ' agent bakiyesi 0 — oyuncu bakiyesi değil, GSC+ kiosk credit gerekir';
            }
        } catch (Throwable $e) {
            $parts[] = 'wallet-balance okunamadı: ' . $e->getMessage();
        }

        return ' [' . implode(', ', $parts) . ']';
    }

    /**
     * Scans the whole decoded response (not just url/content) because the
     * table/limit selection payload has been observed both as the top-level
     * body and nested inside the "message" field. Returns a short, user-safe
     * reason string when the payload is unusable, null when it looks fine.
     *
     * @param array<string, mixed> $response
     */
    private static function describeUnusableLaunchPayload(array $response, string $url, string $content): ?string
    {
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
            return 'geçersiz URL formatı';
        }

        $haystack = $url . ' ' . $content . ' ' . json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (self::haystackLooksLikeEmptyTokenLaunch($haystack)) {
            return 'boş oturum jetonu (token)';
        }
        if (str_contains($haystack, '"codeId"') && str_contains($haystack, '"limits"')) {
            return 'masa/limit seçim yanıtı';
        }

        return null;
    }

    /**
     * Dream Gaming / dingdang "ddnewpc" staging often returns codeId 406 with
     * list URLs like .../direct1.html?token= (empty). Dumping that JSON into the
     * player UI looks like our bug; map it to a clear Turkish sentence instead.
     *
     * @param array<string, mixed> $response
     */
    private static function friendlyLaunchFailureMessage(
        string $providerMessage,
        array $response,
        string $url,
        string $content
    ): string {
        $haystack = $providerMessage . ' ' . $url . ' ' . $content . ' '
            . json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!self::haystackLooksLikeEmptyTokenLaunch($haystack)
            && !(str_contains($haystack, '"codeId"') && str_contains($haystack, '"limits"'))
        ) {
            return '';
        }

        return 'Bu sağlayıcı (Dream Gaming / masa seçici) staging’de boş oturum jetonu döndürdü '
            . '(codeId 406, token=). Cüzdan/callback sorunu değil. Pragmatic Live (1006) veya '
            . 'SA Gaming (1185) ile deneyin; Dream Gaming için GSC+ staging düzeltmesi gerekir.';
    }

    private static function haystackLooksLikeEmptyTokenLaunch(string $haystack): bool
    {
        // Only empty token= is unusable. Host names alone (dingdang/ddnewpc) are
        // valid once GSC returns a real session token.
        return preg_match('/token=(?=&|"|\'|\\\\"|$)/', $haystack) === 1;
    }

    /**
     * Only successful launches used to be recorded, so a provider outage left no
     * trail beyond a player's screenshot. Failures are now persisted the same way
     * (gsc_sessions.status distinguishes them) so gsc_diagnose.php can surface them.
     *
     * @param array<string, mixed> $requestBody
     * @param array<string, mixed>|null $response
     */
    private static function logLaunchFailure(
        PDO $pdo,
        int $userId,
        string $memberAccount,
        int $productCode,
        ?string $gameCode,
        string $gameType,
        string $currency,
        string $platform,
        array $requestBody,
        ?array $response,
        string $message
    ): void {
        try {
            $pdo->prepare(
                'INSERT INTO gsc_sessions
                    (user_id, member_account, product_code, game_code, game_type, currency, platform, launch_url, request_payload, response_payload, status, error_message)
                 VALUES (:uid, :member, :product, :game, :gtype, :cur, :platform, NULL, :req, :res, :status, :err)'
            )->execute([
                ':uid' => $userId,
                ':member' => $memberAccount,
                ':product' => $productCode,
                ':game' => $gameCode,
                ':gtype' => $gameType,
                ':cur' => $currency,
                ':platform' => $platform,
                ':req' => json_encode($requestBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':res' => $response !== null ? json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                ':status' => 'error',
                ':err' => $message,
            ]);
        } catch (Throwable $e) {
            error_log('[GSC+] launch failure log write failed: ' . $e->getMessage());
        }
    }

    /**
     * Prefers the project-wide metropol_cloudflare_client_ip() (config/cloudflare.php)
     * used everywhere else this stack cares about the real visitor IP; falls back to
     * the same CF-Connecting-IP → X-Forwarded-For → REMOTE_ADDR chain BgamingService
     * uses if that helper isn't loaded in this request.
     */
    private static function clientIp(): string
    {
        if (function_exists('metropol_cloudflare_client_ip')) {
            $ip = metropol_cloudflare_client_ip();
            if ($ip !== '') {
                return $ip;
            }
        }
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            $value = trim((string) ($_SERVER[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $ip = trim(explode(',', $value)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return '127.0.0.1';
    }

    private static function isPublicIp(string $ip): bool
    {
        $ip = trim($ip);
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    public static function memberAccountFromUser(array $user): string
    {
        $username = trim((string) ($user['username'] ?? ''));
        if ($username !== '') {
            return substr($username, 0, 50);
        }
        return substr('u' . (int) ($user['id'] ?? 0), 0, 50);
    }

    /** Client site URL required by launch-game and superlobby/launch. */
    private static function resolveLobbyUrl(array $cfg, array $input): string
    {
        // Prefer the runtime frontend origin sent by /play; stale admin config
        // values can produce invalid cashierUrl links in provider launch pages.
        $lobbyUrl = trim((string) ($input['home_url'] ?? ''));
        if ($lobbyUrl === '') {
            $lobbyUrl = trim((string) ($cfg['operator_lobby_url'] ?? ''));
        }
        if ($lobbyUrl === '') {
            $lobbyUrl = defined('SITE_URL') && trim((string) SITE_URL) !== ''
                ? rtrim((string) SITE_URL, '/')
                : ('https://' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        }

        return $lobbyUrl;
    }

    private static function memberPassword(array $cfg, array $user): string
    {
        // Keep a stable deterministic credential per member. Changing this formula
        // between releases can invalidate previously provisioned provider sessions.
        $seed = (string) ($cfg['secret_key'] ?? '') . '|' . (int) ($user['id'] ?? 0) . '|' . (string) ($user['username'] ?? '');
        return md5($seed);
    }

    /**
     * Build deterministic and stored-password-based candidates for launch-game.
     * Some staging lines persist an account password on first login and reject
     * subsequent launches when operators rotate formulas.
     *
     * @return list<string>
     */
    private static function memberPasswordCandidates(PDO $pdo, array $cfg, array $user): array
    {
        $candidates = [];
        $push = static function (string $value) use (&$candidates): void {
            $value = trim($value);
            if ($value === '' || in_array($value, $candidates, true)) {
                return;
            }
            $candidates[] = $value;
        };

        // Primary legacy deterministic formula (historically used in this integration).
        $push(self::memberPassword($cfg, $user));

        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return $candidates;
        }

        try {
            $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $userId]);
            $stored = trim((string) ($stmt->fetchColumn() ?: ''));
            if ($stored !== '') {
                // If the DB field already stores MD5, try it directly first.
                if (preg_match('/^[a-f0-9]{32}$/i', $stored) === 1) {
                    $push(strtolower($stored));
                }
                // Common bridge strategy: provider-side password = md5(stored value).
                $push(md5($stored));
            }
        } catch (Throwable) {
            // Launch must not fail because of candidate enrichment.
        }

        return $candidates !== [] ? $candidates : [self::memberPassword($cfg, $user)];
    }

    private static function passwordCandidateTag(int $idx, int $total): string
    {
        if ($total <= 1) {
            return 'deterministic';
        }
        return $idx === 0 ? 'deterministic' : ('candidate_' . ($idx + 1));
    }

    private static function looksLikeUnauthorizedLaunch(string $url, string $content): bool
    {
        $joined = strtolower($content . ' ' . $url);
        if (
            str_contains($joined, 'not logged in')
            || str_contains($joined, 'un-authorized')
            || str_contains($joined, 'unauthorized')
            || str_contains($joined, 're-log in')
        ) {
            return true;
        }

        if ($url === '' || !preg_match('/^https?:\/\//i', $url)) {
            return false;
        }
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        if ($host === '') {
            return false;
        }
        // Only preflight known staging launch hosts to avoid extra latency elsewhere.
        if (!str_contains($host, 'prerelease-env.biz') && !str_contains($host, 'efinity')) {
            return false;
        }
        $probe = self::httpProbeBody($url);
        if ($probe === '') {
            return false;
        }
        $probe = strtolower($probe);
        return str_contains($probe, 'not logged in')
            || str_contains($probe, 'un-authorized')
            || str_contains($probe, 'unauthorized')
            || str_contains($probe, 're-log in');
    }

    private static function httpProbeBody(string $url): string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return '';
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.8'],
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        if (!is_string($raw) || $raw === '') {
            return '';
        }
        return substr($raw, 0, 40000);
    }

    private static function resolvePlatform(array $input): string
    {
        $platform = strtoupper(trim((string) ($input['platform'] ?? '')));
        if (in_array($platform, ['WEB', 'DESKTOP', 'MOBILE'], true)) {
            return $platform;
        }
        // The documented enum is WEB, DESKTOP, MOBILE and "Widget" (mixed case).
        if ($platform === 'WIDGET') {
            return 'Widget';
        }
        $channel = strtolower(trim((string) ($input['channel'] ?? '')));
        if ($channel === 'mobile') {
            return 'MOBILE';
        }
        $ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if (str_contains($ua, 'mobile') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) {
            return 'MOBILE';
        }
        return 'WEB';
    }

    /** @return array<string,mixed>|null */
    private static function userByMemberAccount(PDO $pdo, string $member): ?array
    {
        $member = trim($member);
        if ($member === '') {
            return null;
        }
        $stmt = $pdo->prepare('SELECT id, username, balance, banned FROM users WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $member]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            return $row;
        }
        // Case-insensitive fallback — GSC sometimes lowercases member_account.
        $stmt = $pdo->prepare('SELECT id, username, balance, banned FROM users WHERE LOWER(username) = LOWER(:u) LIMIT 1');
        $stmt->execute([':u' => $member]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            return $row;
        }
        if (ctype_digit($member)) {
            $stmt = $pdo->prepare('SELECT id, username, balance, banned FROM users WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => (int) $member]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Populate gsc_wallet_logs identity columns — previously always NULL because
     * wallet() never forwarded member/txn from the payload, which hid 1000s.
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $body
     * @return array{user_id:?int,member_account:?string,transaction_id:?string,status_code:int,error_code:?string}
     */
    private static function walletLogMeta(PDO $pdo, string $endpoint, array $payload, array $body): array
    {
        $member = '';
        $txn = '';
        $status = (int) ($body['code'] ?? 0);

        if ($endpoint === 'pushbetdata') {
            $wagers = is_array($payload['wagers'] ?? null) ? $payload['wagers'] : [];
            foreach ($wagers as $wager) {
                if (!is_array($wager)) {
                    continue;
                }
                if ($member === '') {
                    $member = trim((string) ($wager['member_account'] ?? ''));
                }
                if ($txn === '') {
                    $txn = trim((string) ($wager['wager_code'] ?? ''));
                }
            }
        } else {
            $batch = is_array($payload['batch_requests'] ?? null) ? $payload['batch_requests'] : [];
            foreach ($batch as $req) {
                if (!is_array($req)) {
                    continue;
                }
                if ($member === '') {
                    $member = trim((string) ($req['member_account'] ?? ''));
                }
                $txs = is_array($req['transactions'] ?? null) ? $req['transactions'] : [];
                foreach ($txs as $tx) {
                    if (!is_array($tx)) {
                        continue;
                    }
                    if ($txn === '') {
                        $txn = trim((string) ($tx['id'] ?? $tx['wager_code'] ?? ''));
                    }
                }
            }
            // Prefer the worst per-member code so a top-level code:0 with data[].code
            // 1000 no longer looks like success in the admin datatable.
            foreach ((is_array($body['data'] ?? null) ? $body['data'] : []) as $row) {
                if (!is_array($row)) {
                    continue;
                }
                if ($member === '') {
                    $member = trim((string) ($row['member_account'] ?? ''));
                }
                $rowCode = (int) ($row['code'] ?? 0);
                if ($rowCode !== 0) {
                    $status = $rowCode;
                    break;
                }
            }
        }

        $userId = null;
        if ($member !== '') {
            $user = self::userByMemberAccount($pdo, $member);
            if (is_array($user)) {
                $userId = (int) ($user['id'] ?? 0) ?: null;
            }
        }

        $error = $status !== 0 ? (self::WALLET_CODES[$status] ?? ('code_' . $status)) : null;

        return [
            'user_id' => $userId,
            'member_account' => $member !== '' ? substr($member, 0, 64) : null,
            'transaction_id' => $txn !== '' ? substr($txn, 0, 128) : null,
            'status_code' => $status,
            'error_code' => $error !== null ? substr($error, 0, 64) : null,
        ];
    }

    /**
     * @param list<string> $keys
     * @return list<mixed>
     */
    private static function extractList(array $response, array $keys): array
    {
        foreach ($keys as $key) {
            if (isset($response[$key]) && is_array($response[$key])) {
                return array_values($response[$key]);
            }
        }
        return [];
    }

    /** @return array<string,mixed> */
    private static function operatorRequest(PDO $pdo, string $method, string $path, ?array $jsonBody = null): array
    {
        $cfg = self::config($pdo);
        $base = rtrim((string) ($cfg['operator_url'] ?? ''), '/');
        if ($base === '') {
            throw new RuntimeException('GSC+ operator_url boş.');
        }
        $url = $base . (str_starts_with($path, '/') ? $path : '/' . $path);
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl_init failed');
        }
        $headers = ['Accept: application/json'];
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
        ];
        if ($jsonBody !== null) {
            $encoded = json_encode($jsonBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = $encoded;
        }
        curl_setopt_array($ch, $opts);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno !== 0) {
            self::fileLog('operator.http_error', ['method' => $method, 'path' => $path, 'error' => $error]);
            throw new RuntimeException('GSC+ HTTP error: ' . $error);
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            self::fileLog('operator.bad_json', ['method' => $method, 'path' => $path, 'http' => $http]);
            throw new RuntimeException('GSC+ invalid JSON response (HTTP ' . $http . ')');
        }
        $code = (int) ($decoded['code'] ?? 0);
        if ($code !== 0 && $code !== 200) {
            self::fileLog('operator.response_code', [
                'method' => $method,
                'path' => $path,
                'http' => $http,
                'code' => $code,
                'message' => (string) ($decoded['message'] ?? ''),
            ]);
        }
        return $decoded;
    }

    private static function logWallet(
        PDO $pdo,
        string $method,
        ?int $userId,
        ?string $memberAccount,
        ?string $transactionId,
        int $httpStatus,
        ?int $statusCode,
        ?string $errCode,
        float $started,
        array $request,
        array $response
    ): void {
        try {
            $duration = (int) max(0, round((microtime(true) - $started) * 1000));
            $pdo->prepare(
                'INSERT INTO gsc_wallet_logs
                    (method, user_id, member_account, transaction_id, http_status, status_code, error_code,
                     duration_ms, request_payload, response_payload)
                 VALUES
                    (:method, :uid, :member, :txn, :http, :status, :err, :dur, :req, :res)'
            )->execute([
                ':method' => $method,
                ':uid' => $userId,
                ':member' => $memberAccount,
                ':txn' => $transactionId,
                ':http' => $httpStatus,
                ':status' => $statusCode,
                ':err' => $errCode,
                ':dur' => min(65535, $duration),
                ':req' => json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':res' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable) {
        }
    }

    public static function isSlotGameType(string $gameType): bool
    {
        $t = strtoupper(trim($gameType));
        return in_array($t, ['SLOT', 'FISHING', 'POKER', 'OTHERS', 'QIPAI', 'P2P', 'BONUS'], true);
    }

    public static function isLiveGameType(string $gameType): bool
    {
        $t = strtoupper(trim($gameType));
        return in_array($t, ['LIVE_CASINO', 'LIVE_CASINO_PREMIUM'], true);
    }
}
