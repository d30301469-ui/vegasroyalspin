<?php

declare(strict_types=1);

if (class_exists('MegaPayzService', false)) {
    return;
}

final class MegaPayzService
{
    private const DEFAULT_API_BASE = 'https://api.megapayz.net';

    public static function bootstrap(PDO $pdo): void
    {
        if ((string) getenv('APP_RUNTIME_PROVIDER_BOOTSTRAP') !== '1' || !self::runtimeSchemaChangesAllowed()) {
            return;
        }

        self::ensureSchema($pdo);
        self::seedConfig($pdo);
        self::seedMethods($pdo);
    }

    public static function ensureSchema(PDO $pdo): void
    {
        if (!self::runtimeSchemaChangesAllowed()) {
            throw new RuntimeException('Runtime provider schema changes are disabled in production.');
        }

        $defaultApiBase = str_replace("'", "''", trim((string) (getenv('MEGAPAYZ_API_BASE_URL') ?: self::DEFAULT_API_BASE)));
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS megapayz_config (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                code VARCHAR(64) NOT NULL DEFAULT 'default',
                sid VARCHAR(128) NOT NULL,
                private_key VARCHAR(255) NOT NULL,
                api_base_url VARCHAR(255) NOT NULL DEFAULT '{$defaultApiBase}',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_megapayz_config_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS megapayz_methods (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                method_key VARCHAR(64) NOT NULL,
                name VARCHAR(120) NOT NULL,
                type VARCHAR(64) NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT 'TRY',
                deposit_enabled TINYINT(1) NOT NULL DEFAULT 0,
                withdraw_enabled TINYINT(1) NOT NULL DEFAULT 0,
                min_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
                max_amount DECIMAL(18,2) NOT NULL DEFAULT 1000000.00,
                logo_url VARCHAR(700) NULL,
                input_fields LONGTEXT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_megapayz_method_key (method_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        try {
            $pdo->exec('ALTER TABLE megapayz_methods ADD COLUMN logo_url VARCHAR(700) NULL AFTER max_amount');
        } catch (Throwable) {
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS megapayz_transactions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                type ENUM('deposit','withdraw') NOT NULL,
                user_id INT NOT NULL,
                username VARCHAR(120) NOT NULL,
                fullname VARCHAR(255) NOT NULL,
                method VARCHAR(64) NOT NULL,
                trx VARCHAR(64) NOT NULL,
                megapayz_transaction_id VARCHAR(120) NULL,
                amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
                fee DECIMAL(18,2) NOT NULL DEFAULT 0.00,
                currency CHAR(3) NOT NULL DEFAULT 'TRY',
                status VARCHAR(32) NOT NULL DEFAULT 'pending',
                input_fields LONGTEXT NULL,
                request_payload LONGTEXT NULL,
                response_payload LONGTEXT NULL,
                callback_payload LONGTEXT NULL,
                failure_message VARCHAR(700) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                finalized_at DATETIME NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_megapayz_trx (trx),
                KEY idx_megapayz_user_type (user_id, type, id),
                KEY idx_megapayz_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS megapayz_callbacks (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                type ENUM('deposit','withdraw') NOT NULL,
                trx VARCHAR(64) NOT NULL,
                megapayz_transaction_id VARCHAR(120) NULL,
                hash_valid TINYINT(1) NOT NULL DEFAULT 0,
                processed TINYINT(1) NOT NULL DEFAULT 0,
                payload LONGTEXT NOT NULL,
                message VARCHAR(700) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_megapayz_callback_trx (trx),
                KEY idx_megapayz_callback_tx (megapayz_transaction_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public static function seedConfig(PDO $pdo): void
    {
        $sid = trim((string) (getenv('MEGAPAYZ_SID') ?: ''));
        $privateKey = trim((string) (getenv('MEGAPAYZ_PRIVATE_KEY') ?: ''));
        $apiBase = trim((string) (getenv('MEGAPAYZ_API_BASE_URL') ?: self::DEFAULT_API_BASE));
        $isActive = ($sid !== '' && $privateKey !== '') ? 1 : 0;

        $stmt = $pdo->prepare(
            "INSERT INTO megapayz_config (code, sid, private_key, api_base_url, is_active)
             VALUES ('default', :sid, :private_key, :api_base_url, :is_active)
             ON DUPLICATE KEY UPDATE
                sid = IF(sid IS NULL OR sid = '', VALUES(sid), sid),
                private_key = IF(private_key IS NULL OR private_key = '', VALUES(private_key), private_key),
                api_base_url = IF(api_base_url IS NULL OR api_base_url = '', VALUES(api_base_url), api_base_url)"
        );
        $stmt->execute([
            'sid' => $sid,
            'private_key' => $privateKey,
            'api_base_url' => $apiBase,
            'is_active' => $isActive,
        ]);
    }

    public static function seedMethods(PDO $pdo): void
    {
        $methods = [
            [
                'key' => 'wallet',
                'name' => 'Mega Wallet',
                'type' => 'wallet',
                'deposit' => 1,
                'withdraw' => 1,
                'min' => 10,
                'max' => 1000000,
                'logo' => 'https://docs.megapayz.com/images/megawallet-min.png',
                'order' => 10,
                'fields' => [
                    ['name' => 'account', 'label' => 'Hesap numarası', 'field' => 'input', 'type' => 'text', 'pattern' => '[0-9]{10}'],
                ],
            ],
            [
                'key' => 'banktransfer',
                'name' => 'Bank Transfer',
                'type' => 'bank_transfer',
                'deposit' => 1,
                'withdraw' => 1,
                'min' => 50,
                'max' => 1000000,
                'logo' => 'https://docs.megapayz.com/images/megahavale-min.png',
                'order' => 20,
                'fields' => [
                    ['name' => 'account', 'label' => 'IBAN', 'field' => 'input', 'type' => 'text', 'pattern' => '^TR([ ]?[0-9]){24}$'],
                ],
            ],
            [
                'key' => 'crypto',
                'name' => 'Crypto',
                'type' => 'crypto',
                'deposit' => 1,
                'withdraw' => 1,
                'min' => 10,
                'max' => 1000000,
                'logo' => 'https://docs.megapayz.com/images/megakripto-min.png',
                'order' => 30,
                'fields' => [
                    [
                        'name' => 'bank_id',
                        'label' => 'Ağ',
                        'field' => 'select',
                        'options' => [
                            ['value' => '65bd7bba964700005d002ae1', 'label' => 'Bitcoin'],
                            ['value' => '65bd7bc1964700005d002ae2', 'label' => 'Litecoin'],
                            ['value' => '65bd7bd5964700005d002ae4', 'label' => 'USDT TRC20'],
                        ],
                    ],
                    ['name' => 'account', 'label' => 'Cüzdan', 'field' => 'input', 'type' => 'text', 'pattern' => '[A-Za-z0-9]+'],
                ],
            ],
            [
                'key' => 'creditcard',
                'name' => 'Credit Card',
                'type' => 'card',
                'deposit' => 1,
                'withdraw' => 0,
                'min' => 50,
                'max' => 100000,
                'logo' => 'https://docs.megapayz.com/images/megakredikarti-min.png',
                'order' => 40,
                'fields' => [],
            ],
        ];

        $stmt = $pdo->prepare(
            "INSERT INTO megapayz_methods
                (method_key, name, type, currency, deposit_enabled, withdraw_enabled, min_amount, max_amount, logo_url, input_fields, sort_order, is_active)
             VALUES
                (:method_key, :name, :type, 'TRY', :deposit_enabled, :withdraw_enabled, :min_amount, :max_amount, :logo_url, :input_fields, :sort_order, 1)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                type = VALUES(type),
                logo_url = IF(logo_url IS NULL OR logo_url = '', VALUES(logo_url), logo_url),
                input_fields = VALUES(input_fields),
                sort_order = VALUES(sort_order)"
        );

        foreach ($methods as $method) {
            $stmt->execute([
                'method_key' => $method['key'],
                'name' => $method['name'],
                'type' => $method['type'],
                'deposit_enabled' => $method['deposit'],
                'withdraw_enabled' => $method['withdraw'],
                'min_amount' => number_format((float) $method['min'], 2, '.', ''),
                'max_amount' => number_format((float) $method['max'], 2, '.', ''),
                'logo_url' => $method['logo'],
                'input_fields' => json_encode($method['fields'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'sort_order' => $method['order'],
            ]);
        }
    }

    public static function dropLegacyTables(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        foreach ([
            'deposit_transactions',
            'withdraw_transactions',
            'para_yatirma_islemleri',
            'para_cekme_islemleri',
            'payment_provider_methods',
            'payment_providers',
        ] as $table) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $table . '`');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(PDO $pdo): array
    {
        self::bootstrap($pdo);
        $stmt = $pdo->query("SELECT sid, private_key, api_base_url FROM megapayz_config WHERE code = 'default' AND is_active = 1 LIMIT 1");
        $row = $stmt !== false ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (!is_array($row) || trim((string) ($row['sid'] ?? '')) === '' || trim((string) ($row['private_key'] ?? '')) === '') {
            throw new RuntimeException('MegaPayz config bulunamadı veya eksik.');
        }
        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function methods(PDO $pdo): array
    {
        self::bootstrap($pdo);
        $stmt = $pdo->query('SELECT * FROM megapayz_methods WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
        $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $fields = json_decode((string) ($row['input_fields'] ?? '[]'), true);
            if (!is_array($fields)) {
                $fields = [];
            }
            $key = (string) ($row['method_key'] ?? '');
            $out[] = [
                'id' => $key,
                'payment_method_id' => $key,
                'method_id' => $key,
                'method' => $key,
                'name' => (string) ($row['name'] ?? $key),
                'provider' => ['code' => 'megapayz', 'name' => 'MegaPayz'],
                'type' => (string) ($row['type'] ?? ''),
                'status' => 'active',
                'currency' => (string) ($row['currency'] ?? 'TRY'),
                'deposit_enabled' => (bool) ($row['deposit_enabled'] ?? false),
                'withdrawal_enabled' => (bool) ($row['withdraw_enabled'] ?? false),
                'min_amount' => (float) ($row['min_amount'] ?? 0),
                'max_amount' => (float) ($row['max_amount'] ?? 0),
                'logo_url' => self::sanitizeLogoUrl((string) ($row['logo_url'] ?? '')),
                'input_fields' => $fields,
                'processing_time' => 'Anlık',
            ];
        }
        return $out;
    }

    /**
     * Veritabanında yanlış domain ile kaydedilmiş yerel varlık URL'lerini temizler.
     * Örn: http://maltabet.test/assets/x.png → /assets/x.png
     */
    private static function sanitizeLogoUrl(string $url): string
    {
        if ($url === '' || !str_starts_with($url, 'http')) {
            return $url;
        }
        $parsed = parse_url($url);
        $path = (string) ($parsed['path'] ?? '');
        if (str_starts_with($path, '/assets/') || str_starts_with($path, '/uploads/')) {
            return $path;
        }
        return $url;
    }

    public static function findMethod(PDO $pdo, string $key, string $direction): ?array
    {
        foreach (self::methods($pdo) as $method) {
            if ((string) ($method['method'] ?? '') !== $key) {
                continue;
            }
            if ($direction === 'deposit' && empty($method['deposit_enabled'])) {
                return null;
            }
            if ($direction === 'withdraw' && empty($method['withdrawal_enabled'])) {
                return null;
            }
            return $method;
        }
        return null;
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public static function createDeposit(PDO $pdo, array $user, string $method, float $amount, string $returnUrl = ''): array
    {
        $methodRow = self::findMethod($pdo, $method, 'deposit');
        if ($methodRow === null) {
            return ['success' => false, 'code' => 422, 'message' => 'Geçersiz yatırım yöntemi.'];
        }
        $amountError = self::validateAmountAgainstMethod($amount, $methodRow, 'yatırım');
        if ($amountError !== '') {
            return ['success' => false, 'code' => 422, 'message' => $amountError];
        }
        $trx = self::newTrx('D');
        try {
            $cfg = self::config($pdo);
        } catch (Throwable) {
            return ['success' => false, 'code' => 503, 'message' => 'MegaPayz yapılandırması eksik.'];
        }
        $payload = self::basePayload($cfg, $user, $trx);
        $payload['method'] = $method;
        $payload['amount'] = $amount;
        $payload['return_url'] = $returnUrl !== '' ? $returnUrl : self::defaultReturnUrl();

        self::insertTransaction($pdo, 'deposit', $user, $method, $trx, $amount, [], $payload);
        $res = self::postToMegaPayz($cfg, '/create-deposit-by-method', $payload);
        self::storeGatewayResponse($pdo, $trx, $res);

        if (!empty($res['status']) && (int) ($res['code'] ?? 0) === 200 && !empty($res['url'])) {
            self::notifyMember(
                $pdo,
                (int) ($user['id'] ?? 0),
                'Yatırım işlemi başlatıldı',
                self::formatMoney($amount) . ' tutarındaki yatırım işleminiz oluşturuldu. Ödeme tamamlandığında bakiyenize yansıyacaktır.',
                'info',
                '/profile/deposit-withdraw-history'
            );

            return [
                'success' => true,
                'code' => 200,
                'message' => 'MegaPayz yatırım bağlantısı oluşturuldu.',
                'data' => [
                    'payment_url' => (string) $res['url'],
                    'redirect_url' => (string) $res['url'],
                    'trx' => $trx,
                    'method' => $method,
                    'provider' => 'megapayz',
                ],
            ];
        }

        self::markTransactionFailed($pdo, $trx, (string) ($res['message'] ?? 'MegaPayz yatırım isteği başarısız.'));
        return [
            'success' => false,
            'code' => (int) ($res['code'] ?? 502),
            'message' => (string) ($res['message'] ?? 'MegaPayz yatırım isteği başarısız.'),
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $inputFields
     * @return array<string, mixed>
     */
    public static function createWithdraw(PDO $pdo, array $user, string $method, float $amount, array $inputFields): array
    {
        $methodRow = self::findMethod($pdo, $method, 'withdraw');
        if ($methodRow === null) {
            return ['success' => false, 'code' => 422, 'message' => 'Geçersiz çekim yöntemi.'];
        }
        if ($amount <= 0) {
            return ['success' => false, 'code' => 422, 'message' => 'Geçerli bir tutar girin.'];
        }
        $amountError = self::validateAmountAgainstMethod($amount, $methodRow, 'çekim');
        if ($amountError !== '') {
            return ['success' => false, 'code' => 422, 'message' => $amountError];
        }
        $fieldError = self::validateWithdrawFields($method, $inputFields);
        if ($fieldError !== '') {
            return ['success' => false, 'code' => 422, 'message' => $fieldError];
        }

        try {
            $wageringPath = dirname(__DIR__) . '/services/WageringService.php';
            if (is_readable($wageringPath)) {
                require_once $wageringPath;
                if (class_exists('WageringService', false)) {
                    $progress = WageringService::accountProgress($pdo, (int) ($user['id'] ?? 0));
                    if (empty($progress['isComplete'])) {
                        return [
                            'success' => false,
                            'code' => 422,
                            'message' => 'Çekim için çevrim şartı henüz tamamlanmadı.',
                        ];
                    }
                }
            }
        } catch (Throwable) {
        }

        $trx = self::newTrx('W');
        try {
            $cfg = self::config($pdo);
        } catch (Throwable) {
            return ['success' => false, 'code' => 503, 'message' => 'MegaPayz yapılandırması eksik.'];
        }
        $fields = array_merge(['method' => $method, 'amount' => number_format($amount, 2, '.', '')], $inputFields);
        $payload = self::basePayload($cfg, $user, $trx);
        unset($payload['method']);
        $payload['input_fields'] = $fields;

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT balance FROM users WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => (int) ($user['id'] ?? 0)]);
            $balance = (float) $stmt->fetchColumn();
            if ($balance < $amount) {
                $pdo->rollBack();
                return ['success' => false, 'code' => 422, 'message' => 'Yetersiz bakiye.'];
            }
            $pdo->prepare('UPDATE users SET balance = balance - :amount WHERE id = :id')
                ->execute(['amount' => number_format($amount, 2, '.', ''), 'id' => (int) ($user['id'] ?? 0)]);
            self::insertTransaction($pdo, 'withdraw', $user, $method, $trx, $amount, $fields, $payload);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'code' => 500, 'message' => 'Çekim kaydı oluşturulamadı.'];
        }

        try {
            $monitorPath = dirname(__DIR__) . '/services/ComplianceMonitorService.php';
            if (!class_exists('ComplianceMonitorService', false) && is_readable($monitorPath)) {
                require_once $monitorPath;
            }
            if (class_exists('ComplianceMonitorService', false)) {
                ComplianceMonitorService::evaluateWithdraw(
                    $pdo,
                    (int) ($user['id'] ?? 0),
                    $amount,
                    $trx,
                    $method
                );
            }
        } catch (Throwable) {
        }

        self::notifyMember(
            $pdo,
            (int) ($user['id'] ?? 0),
            'Çekim talebi alındı',
            self::formatMoney($amount) . ' tutarındaki çekim talebiniz alındı. Admin onayı bekleniyor.',
            'info',
            '/profile/deposit-withdraw-history'
        );

        return [
            'success' => true,
            'code' => 200,
            'message' => 'Çekim talebiniz alındı, admin onayı bekliyor.',
            'data' => [
                'trx' => $trx,
                'reference_code' => $trx,
                'method' => $method,
                'provider' => 'megapayz',
                'requires_admin_approval' => true,
                'message' => 'Çekim talebiniz alındı, admin onayı bekliyor.',
            ],
        ];
    }

    /**
     * Ortaklık kripto ödeme ağları (MegaPayz bank_id).
     *
     * @return list<array{id:string,label:string}>
     */
    public static function affiliateCryptoNetworks(): array
    {
        return [
            ['id' => '65bd7bd5964700005d002ae4', 'label' => 'USDT TRC20'],
            ['id' => '65bd7bba964700005d002ae1', 'label' => 'Bitcoin'],
            ['id' => '65bd7bc1964700005d002ae2', 'label' => 'Litecoin'],
        ];
    }

    public static function resolveAffiliateCryptoBankId(string $networkOrId): string
    {
        $value = trim($networkOrId);
        if ($value === '') {
            return '';
        }
        foreach (self::affiliateCryptoNetworks() as $network) {
            if (hash_equals($network['id'], $value)) {
                return $network['id'];
            }
        }
        $normalized = strtoupper(str_replace([' ', '-', '_'], '', $value));
        return match (true) {
            str_contains($normalized, 'TRC20') || str_contains($normalized, 'USDT') => '65bd7bd5964700005d002ae4',
            str_contains($normalized, 'BTC') || str_contains($normalized, 'BITCOIN') => '65bd7bba964700005d002ae1',
            str_contains($normalized, 'LTC') || str_contains($normalized, 'LITECOIN') => '65bd7bc1964700005d002ae2',
            default => '',
        };
    }

    /**
     * Admin, bekleyen affiliate kripto ödemesini onaylayıp MegaPayz /create-withdraw'a iletir.
     *
     * @return array{success:bool,message:string,trx?:string}
     */
    public static function approveAffiliateCryptoPayout(
        PDO $pdo,
        int $payoutId,
        int $adminId = 0,
        string $adminUsername = ''
    ): array {
        self::bootstrap($pdo);
        try {
            $cfg = self::config($pdo);
        } catch (Throwable) {
            return ['success' => false, 'message' => 'MegaPayz yapılandırması eksik.'];
        }

        if (!self::columnExists($pdo, 'affiliate_payouts', 'megapayz_trx')
            || !self::columnExists($pdo, 'megapayz_transactions', 'affiliate_payout_id')) {
            return ['success' => false, 'message' => 'Affiliate MegaPayz migration uygulanmamış.'];
        }

        $methodRow = self::findMethod($pdo, 'crypto', 'withdraw');
        if ($methodRow === null) {
            return ['success' => false, 'message' => 'MegaPayz kripto çekim metodu aktif değil.'];
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'SELECT ap.*, a.full_name, a.email, a.referral_code, a.user_id AS affiliate_user_id
                 FROM affiliate_payouts ap
                 INNER JOIN affiliates a ON a.id = ap.affiliate_id
                 WHERE ap.id = :id
                 FOR UPDATE'
            );
            $stmt->execute(['id' => $payoutId]);
            $payout = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($payout)) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Ödeme talebi bulunamadı.'];
            }
            if (strtolower((string) ($payout['method'] ?? '')) !== 'crypto') {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Yalnızca kripto ödeme talepleri MegaPayz’e gönderilebilir.'];
            }
            if (!in_array((string) ($payout['status'] ?? ''), ['pending', 'approved'], true)) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Bu ödeme MegaPayz’e gönderilemez (durum: ' . ($payout['status'] ?? '') . ').'];
            }
            if (trim((string) ($payout['megapayz_trx'] ?? '')) !== '') {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Bu ödeme zaten MegaPayz’e iletilmiş.'];
            }

            $details = json_decode((string) ($payout['method_details'] ?? ''), true);
            if (!is_array($details)) {
                $details = [];
            }
            $wallet = trim((string) ($details['wallet_address'] ?? $details['account'] ?? ''));
            $bankId = self::resolveAffiliateCryptoBankId(
                (string) ($details['bank_id'] ?? $details['network'] ?? $details['crypto_network'] ?? '')
            );
            $fieldError = self::validateWithdrawFields('crypto', [
                'account' => $wallet,
                'bank_id' => $bankId,
            ]);
            if ($fieldError !== '') {
                $pdo->rollBack();
                return ['success' => false, 'message' => $fieldError];
            }

            $amount = (float) ($payout['amount'] ?? 0);
            $amountError = self::validateAmountAgainstMethod($amount, $methodRow, 'çekim');
            if ($amountError !== '') {
                $pdo->rollBack();
                return ['success' => false, 'message' => $amountError];
            }

            $affiliateId = (int) ($payout['affiliate_id'] ?? 0);
            $code = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($payout['referral_code'] ?? '')) ?: (string) $affiliateId;
            $nameParts = self::splitAffiliateFullName(
                (string) ($payout['full_name'] ?? ''),
                'Affiliate',
                $code !== '' ? $code : 'Partner'
            );
            $linkedUserId = (int) ($payout['affiliate_user_id'] ?? 0);
            $syntheticUser = [
                // Linked member id if present; otherwise stable synthetic id (never collapse to user #1).
                'id' => $linkedUserId > 0 ? $linkedUserId : (900000000 + max(1, $affiliateId)),
                'username' => 'aff_' . $code,
                'name' => $nameParts['name'],
                'surname' => $nameParts['surname'],
            ];
            $trx = self::newTrx('A');
            $inputFields = [
                'method' => 'crypto',
                'amount' => number_format($amount, 2, '.', ''),
                'account' => $wallet,
                'bank_id' => $bankId,
                'affiliate_payout_id' => $payoutId,
                'network' => (string) ($details['network'] ?? $bankId),
            ];
            $payload = self::basePayload($cfg, $syntheticUser, $trx);
            unset($payload['method']);
            $payload['input_fields'] = $inputFields;

            $insert = $pdo->prepare(
                'INSERT INTO megapayz_transactions
                    (type, user_id, affiliate_payout_id, username, fullname, method, trx, amount, currency, status, input_fields, request_payload)
                 VALUES
                    (\'withdraw\', :user_id, :affiliate_payout_id, :username, :fullname, \'crypto\', :trx, :amount, \'TRY\', \'processing\', :input_fields, :request_payload)'
            );
            $insert->execute([
                'user_id' => (int) $syntheticUser['id'],
                'affiliate_payout_id' => $payoutId,
                'username' => $syntheticUser['username'],
                'fullname' => self::fullname($syntheticUser),
                'trx' => $trx,
                'amount' => number_format($amount, 2, '.', ''),
                'input_fields' => json_encode($inputFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'request_payload' => json_encode(self::redactCallbackPayload($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $pdo->prepare(
                "UPDATE affiliate_payouts
                 SET status = 'processing', megapayz_trx = :trx, processed_at = NOW(), processed_by = :admin_id,
                     admin_notes = CONCAT(IFNULL(admin_notes,''), IF(IFNULL(admin_notes,'')='','', '\n'), :note),
                     updated_at = NOW()
                 WHERE id = :id"
            )->execute([
                'trx' => $trx,
                'admin_id' => $adminId > 0 ? $adminId : null,
                'note' => 'MegaPayz’e gönderildi' . ($adminUsername !== '' ? (' (' . $adminUsername . ')') : ''),
                'id' => $payoutId,
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[MegaPayz affiliate approve] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Ödeme MegaPayz kaydı oluşturulamadı.'];
        }

        $res = self::postToMegaPayz($cfg, '/create-withdraw', $payload);
        self::storeGatewayResponse($pdo, $trx, $res);
        if (!empty($res['status']) && (int) ($res['code'] ?? 0) === 200) {
            $pdo->prepare(
                "UPDATE megapayz_transactions
                 SET status = 'approved', failure_message = NULL, updated_at = NOW()
                 WHERE trx = :trx AND status = 'processing'"
            )->execute(['trx' => $trx]);

            return [
                'success' => true,
                'message' => 'Kripto ödeme MegaPayz’e iletildi. Callback bekleniyor.',
                'trx' => $trx,
            ];
        }

        $message = (string) ($res['message'] ?? 'MegaPayz çekim onayı başarısız.');
        $pdo->prepare(
            "UPDATE megapayz_transactions
             SET status = 'failed', failure_message = :message, finalized_at = NOW(), updated_at = NOW()
             WHERE trx = :trx"
        )->execute(['message' => $message, 'trx' => $trx]);
        $pdo->prepare(
            "UPDATE affiliate_payouts
             SET status = 'pending', megapayz_trx = NULL, updated_at = NOW(),
                 admin_notes = CONCAT(IFNULL(admin_notes,''), IF(IFNULL(admin_notes,'')='','', '\n'), :note)
             WHERE id = :id"
        )->execute([
            'note' => 'MegaPayz hatası: ' . mb_substr($message, 0, 400),
            'id' => $payoutId,
        ]);

        return ['success' => false, 'message' => $message];
    }

    public static function approveWithdraw(PDO $pdo, int $transactionId, string $adminUsername = ''): array
    {
        self::bootstrap($pdo);
        try {
            $cfg = self::config($pdo);
        } catch (Throwable) {
            return ['success' => false, 'message' => 'MegaPayz yapılandırması eksik.'];
        }
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM megapayz_transactions WHERE id = :id AND type = 'withdraw' FOR UPDATE");
            $stmt->execute(['id' => $transactionId]);
            $tx = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($tx)) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Çekim kaydı bulunamadı.'];
            }
            if ((string) ($tx['status'] ?? '') !== 'pending') {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Sadece bekleyen çekimler onaylanabilir.'];
            }
            $pdo->prepare("UPDATE megapayz_transactions SET status = 'processing', updated_at = NOW() WHERE id = :id")
                ->execute(['id' => $transactionId]);
            $pdo->commit();
        } catch (Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'message' => 'Çekim onaya alınamadı.'];
        }

        $inputFields = json_decode((string) ($tx['input_fields'] ?? '{}'), true);
        if (!is_array($inputFields)) {
            $inputFields = [];
        }
        $payload = self::basePayload($cfg, [
            'id' => (int) ($tx['user_id'] ?? 0),
            'username' => (string) ($tx['username'] ?? ''),
            'name' => (string) ($tx['fullname'] ?? ''),
            'surname' => '',
        ], (string) ($tx['trx'] ?? ''));
        unset($payload['method']);
        $payload['input_fields'] = $inputFields;

        $res = self::postToMegaPayz($cfg, '/create-withdraw', $payload);
        self::storeGatewayResponse($pdo, (string) ($tx['trx'] ?? ''), $res);
        if (!empty($res['status']) && (int) ($res['code'] ?? 0) === 200) {
            $pdo->prepare(
                "UPDATE megapayz_transactions
                 SET status = 'approved', failure_message = NULL, updated_at = NOW()
                 WHERE id = :id AND status = 'processing'"
            )->execute(['id' => $transactionId]);

            return ['success' => true, 'message' => 'Çekim MegaPayz API’ye iletildi. Callback bekleniyor.'];
        }

        $message = (string) ($res['message'] ?? 'MegaPayz çekim onayı başarısız.');
        if ($adminUsername !== '') {
            $message .= ' Admin: ' . $adminUsername;
        }
        $pdo->prepare("UPDATE megapayz_transactions SET status = 'pending', failure_message = :message, updated_at = NOW() WHERE id = :id AND status = 'processing'")
            ->execute(['message' => $message, 'id' => $transactionId]);

        return ['success' => false, 'message' => $message];
    }

    public static function rejectWithdraw(PDO $pdo, int $transactionId, string $reason = ''): array
    {
        self::bootstrap($pdo);
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM megapayz_transactions WHERE id = :id AND type = 'withdraw' FOR UPDATE");
            $stmt->execute(['id' => $transactionId]);
            $tx = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($tx)) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Çekim kaydı bulunamadı.'];
            }
            $status = strtolower((string) ($tx['status'] ?? ''));
            if ($status !== 'pending') {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Bu çekim artık reddedilemez.'];
            }
            $pdo->prepare('UPDATE users SET balance = balance + :amount WHERE id = :id')
                ->execute([
                    'amount' => number_format((float) ($tx['amount'] ?? 0), 2, '.', ''),
                    'id' => (int) ($tx['user_id'] ?? 0),
                ]);
            $pdo->prepare(
                "UPDATE megapayz_transactions
                 SET status = 'rejected', failure_message = :message, finalized_at = NOW(), updated_at = NOW()
                 WHERE id = :id"
            )->execute([
                'message' => $reason !== '' ? $reason : 'Admin tarafından reddedildi.',
                'id' => $transactionId,
            ]);
            $pdo->commit();

            $rejectReason = $reason !== '' ? $reason : 'Admin tarafından reddedildi.';
            self::notifyPaymentStatus(
                $pdo,
                'withdraw',
                (int) ($tx['user_id'] ?? 0),
                'rejected',
                (float) ($tx['amount'] ?? 0),
                (string) ($tx['currency'] ?? 'TRY'),
                $rejectReason
            );

            return ['success' => true, 'message' => 'Çekim reddedildi ve bakiye iade edildi.'];
        } catch (Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['success' => false, 'message' => 'Çekim reddedilemedi.'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function withdrawForm(PDO $pdo, array $user): array
    {
        try {
            $cfg = self::config($pdo);
        } catch (Throwable) {
            $fallback = array_values(array_filter(self::methods($pdo), static fn (array $m): bool => !empty($m['withdrawal_enabled'])));
            return ['success' => true, 'code' => 200, 'message' => 'Çekim formu', 'data' => ['methods' => $fallback, 'payment_methods' => $fallback]];
        }
        $payload = self::basePayload($cfg, $user, '');
        $payload['method'] = 'get-withdraw-form';
        $payload['trx'] = '';
        $payload['hash'] = self::hash($cfg, (string) ($user['id'] ?? ''), (string) ($user['username'] ?? ''), '');
        $payload['lang'] = 'tr';
        $res = self::postToMegaPayz($cfg, '/get-withdraw-form', $payload, 5);
        if (!empty($res['status']) && is_array($res['methods'] ?? null)) {
            return ['success' => true, 'code' => 200, 'message' => 'Çekim formu', 'data' => ['methods' => $res['methods'], 'payment_methods' => $res['methods']]];
        }
        $fallback = array_values(array_filter(self::methods($pdo), static fn (array $m): bool => !empty($m['withdrawal_enabled'])));
        return ['success' => true, 'code' => 200, 'message' => 'Çekim formu', 'data' => ['methods' => $fallback, 'payment_methods' => $fallback]];
    }

    public static function handleCallback(PDO $pdo, string $type, array $payload): array
    {
        try {
            $cfg = self::config($pdo);
        } catch (Throwable) {
            return ['status' => false, 'code' => 99999, 'message' => 'Missing MegaPayz config'];
        }
        $trx = trim((string) ($payload['trx'] ?? ''));
        $txId = trim((string) ($payload['transaction_id'] ?? ''));
        $valid = self::verifyHash($cfg, $payload);
        $callbackId = self::insertCallback($pdo, $type, $trx, $txId, $valid, $payload);
        if (!$valid) {
            return ['status' => false, 'code' => 99999, 'message' => 'Invalid hash'];
        }
        if ($trx === '') {
            return ['status' => false, 'code' => 99999, 'message' => 'Missing trx'];
        }

        $status = self::normalizeCallbackStatus((string) ($payload['status'] ?? ''));
        if (!in_array($status, ['confirmed', 'rejected', 'failed', 'pending', 'processing'], true)) {
            return ['status' => false, 'code' => 99999, 'message' => 'Invalid callback status'];
        }
        $amount = (float) ($payload['amount'] ?? 0);
        $fee = (float) ($payload['fee'] ?? 0);
        $userId = 0;
        $affiliatePayoutId = 0;

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT * FROM megapayz_transactions WHERE trx = :trx AND type = :type FOR UPDATE');
            $stmt->execute(['trx' => $trx, 'type' => $type]);
            $tx = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($tx)) {
                $pdo->rollBack();
                return ['status' => false, 'code' => 99999, 'message' => 'Transaction not found'];
            }
            if ($amount <= 0) {
                $pdo->rollBack();
                return ['status' => false, 'code' => 99999, 'message' => 'Invalid callback amount'];
            }
            // Deposit: banka transferinde kullanıcı talep tutarından farklı yatırabilir.
            // Callback'teki gerçek tutarı kabul edip bakiyeye onu yansıtıyoruz.
            // Withdraw: bakiye talep anında düşüldüğü için tutar eşleşmesi zorunlu.
            $txAmount = round((float) ($tx['amount'] ?? 0), 2);
            if ($type === 'withdraw' && abs(round($amount, 2) - $txAmount) > 0.01) {
                $pdo->rollBack();
                return ['status' => false, 'code' => 99999, 'message' => 'Callback amount mismatch'];
            }
            $oldStatus = strtolower((string) ($tx['status'] ?? ''));
            $userId = (int) ($tx['user_id'] ?? 0);
            $affiliatePayoutId = (int) ($tx['affiliate_payout_id'] ?? 0);
            if ($affiliatePayoutId <= 0) {
                $input = json_decode((string) ($tx['input_fields'] ?? ''), true);
                if (is_array($input)) {
                    $affiliatePayoutId = (int) ($input['affiliate_payout_id'] ?? 0);
                }
            }
            if (in_array($oldStatus, ['confirmed', 'rejected', 'failed', 'cancelled'], true)) {
                self::markCallbackProcessed($pdo, $callbackId, 'Duplicate final callback ignored');
                $pdo->commit();
                return ['status' => true, 'code' => 200, 'message' => 'OK'];
            }
            if ($type === 'deposit' && $status === 'confirmed' && $oldStatus !== 'confirmed') {
                $pdo->prepare('UPDATE users SET balance = balance + :amount WHERE id = :id')
                    ->execute(['amount' => number_format($amount, 2, '.', ''), 'id' => $userId]);
                try {
                    $wageringPath = dirname(__DIR__) . '/services/WageringService.php';
                    if (!class_exists('WageringService', false) && is_readable($wageringPath)) {
                        require_once $wageringPath;
                    }
                    if (class_exists('WageringService', false)) {
                        WageringService::registerDeposit($pdo, $userId, $amount);
                    }
                } catch (Throwable) {
                }
            }
            // Çekim talebinde bakiye, talep anında düşülür. Sağlayıcı işlemi
            // reddederse VEYA başarısız (failed) olursa kullanıcıya iade edilmeli.
            // Affiliate kripto ödemelerinde users.balance yerine affiliate bakiyesi
            // finalizeAffiliatePayoutCallback içinde iade edilir.
            if ($type === 'withdraw' && $affiliatePayoutId <= 0 && in_array($status, ['rejected', 'failed'], true)) {
                $pdo->prepare('UPDATE users SET balance = balance + :amount WHERE id = :id')
                    ->execute(['amount' => number_format((float) ($tx['amount'] ?? $amount), 2, '.', ''), 'id' => $userId]);
            }
            $upd = $pdo->prepare(
                'UPDATE megapayz_transactions
                 SET status = :status, amount = :amount, fee = :fee, currency = :currency,
                     megapayz_transaction_id = :mp_tx, callback_payload = :payload, finalized_at = NOW()
                 WHERE id = :id'
            );
            $upd->execute([
                'status' => $status !== '' ? $status : 'callback',
                'amount' => number_format($amount, 2, '.', ''),
                'fee' => number_format($fee, 2, '.', ''),
                'currency' => (string) ($payload['currency'] ?? 'TRY'),
                'mp_tx' => $txId !== '' ? $txId : null,
                'payload' => json_encode(self::redactCallbackPayload($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'id' => (int) $tx['id'],
            ]);
            if ($type === 'withdraw' && $affiliatePayoutId > 0 && in_array($status, ['confirmed', 'rejected', 'failed'], true)) {
                self::finalizeAffiliatePayoutCallback(
                    $pdo,
                    $affiliatePayoutId,
                    $status,
                    $txId,
                    (string) ($tx['trx'] ?? $trx),
                    (string) ($payload['message'] ?? $payload['failure_message'] ?? '')
                );
            }
            self::markCallbackProcessed($pdo, $callbackId, 'OK');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['status' => false, 'code' => 99999, 'message' => 'Callback could not be processed'];
        }

        if ($affiliatePayoutId <= 0 && $type === 'deposit' && $status === 'confirmed') {
            try {
                $monitorPath = dirname(__DIR__) . '/services/ComplianceMonitorService.php';
                if (!class_exists('ComplianceMonitorService', false) && is_readable($monitorPath)) {
                    require_once $monitorPath;
                }
                if (class_exists('ComplianceMonitorService', false)) {
                    ComplianceMonitorService::evaluateDeposit(
                        $pdo,
                        (int) ($tx['user_id'] ?? 0),
                        (float) $amount,
                        $trx,
                        (string) ($tx['method'] ?? '')
                    );
                }
            } catch (Throwable) {
            }
        }

        $finalStatus = $status !== '' ? $status : 'callback';
        if ($affiliatePayoutId <= 0 && in_array($finalStatus, ['confirmed', 'rejected', 'failed'], true)) {
            $failureMessage = trim((string) ($payload['message'] ?? $payload['failure_message'] ?? $tx['failure_message'] ?? ''));
            self::notifyPaymentStatus(
                $pdo,
                $type,
                $userId,
                $finalStatus,
                $amount > 0 ? $amount : (float) ($tx['amount'] ?? 0),
                (string) ($payload['currency'] ?? $tx['currency'] ?? 'TRY'),
                $failureMessage
            );
        }

        return ['status' => true, 'code' => 200, 'message' => 'OK'];
    }

    public static function handleUnifiedCallback(PDO $pdo, array $payload): array
    {
        self::bootstrap($pdo);

        $trx = trim((string) ($payload['trx'] ?? ''));
        $txId = trim((string) ($payload['transaction_id'] ?? $payload['megapayz_transaction_id'] ?? ''));
        $type = self::resolveCallbackType($pdo, $trx, $txId, $payload);
        if (!in_array($type, ['deposit', 'withdraw'], true)) {
            return ['status' => false, 'code' => 99999, 'message' => 'Callback transaction type could not be resolved'];
        }

        return self::handleCallback($pdo, $type, $payload);
    }

    /**
     * putenv kapalı (aaPanel) sunucularda $_ENV/$_SERVER fallback.
     */
    private static function envValue(string $key, string $default = ''): string
    {
        foreach ([
            getenv($key),
            $_ENV[$key] ?? null,
            $_SERVER[$key] ?? null,
            defined($key) ? constant($key) : null,
        ] as $candidate) {
            if ($candidate === false || $candidate === null) {
                continue;
            }
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $server
     * @return array{valid: bool, code?: int, error?: string}
     */
    public static function verifyCallbackTransport(array $server): array
    {
        $allowedIps = self::envValue('MEGAPAYZ_CALLBACK_ALLOWED_IPS');
        if ($allowedIps !== '') {
            $remoteIp = trim((string) ($server['REMOTE_ADDR'] ?? ''));
            $cfIp = trim((string) ($server['HTTP_CF_CONNECTING_IP'] ?? ''));
            $xff = trim((string) ($server['HTTP_X_FORWARDED_FOR'] ?? ''));
            if ($cfIp !== '' && filter_var($cfIp, FILTER_VALIDATE_IP)) {
                $remoteIp = $cfIp;
            } elseif ($xff !== '') {
                $first = trim((string) explode(',', $xff)[0]);
                if ($first !== '' && filter_var($first, FILTER_VALIDATE_IP)) {
                    $remoteIp = $first;
                }
            }
            if (!self::ipAllowed($remoteIp, $allowedIps)) {
                return ['valid' => false, 'code' => 403, 'error' => 'IP_NOT_ALLOWED'];
            }
        }

        // MegaPayz resmi callback kimliği body hash (private_key) ile yapılır.
        // MEGAPAYZ_CALLBACK_TOKEN isteğe bağlı ekstra katmandır; sağlayıcı bu
        // header'ı göndermezse hash doğrulaması yine handleCallback içinde çalışır.
        $expectedToken = self::envValue('MEGAPAYZ_CALLBACK_TOKEN');
        if ($expectedToken !== '') {
            $token = trim((string) (
                $server['HTTP_X_MEGAPAYZ_CALLBACK_TOKEN']
                ?? $server['HTTP_X_CALLBACK_TOKEN']
                ?? ($_GET['callback_token'] ?? '')
                ?? ''
            ));
            if ($token !== '' && !hash_equals($expectedToken, $token)) {
                return ['valid' => false, 'code' => 403, 'error' => 'INVALID_CALLBACK_TOKEN'];
            }
        }

        return ['valid' => true];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function resolveCallbackType(PDO $pdo, string $trx, string $txId, array $payload): string
    {
        if ($trx !== '') {
            $stmt = $pdo->prepare('SELECT type FROM megapayz_transactions WHERE trx = :trx ORDER BY id DESC LIMIT 1');
            $stmt->execute(['trx' => $trx]);
            $type = (string) $stmt->fetchColumn();
            if (in_array($type, ['deposit', 'withdraw'], true)) {
                return $type;
            }
        }

        if ($txId !== '') {
            $stmt = $pdo->prepare('SELECT type FROM megapayz_transactions WHERE megapayz_transaction_id = :tx_id ORDER BY id DESC LIMIT 1');
            $stmt->execute(['tx_id' => $txId]);
            $type = (string) $stmt->fetchColumn();
            if (in_array($type, ['deposit', 'withdraw'], true)) {
                return $type;
            }
        }

        $hint = strtolower(trim((string) (
            $payload['type']
            ?? $payload['transaction_type']
            ?? $payload['operation']
            ?? $payload['payment_type']
            ?? ''
        )));
        return match ($hint) {
            'deposit', 'yatirim', 'investment' => 'deposit',
            'withdraw', 'withdrawal', 'cekim' => 'withdraw',
            default => '',
        };
    }

    /**
     * @return array{items: list<array<string,mixed>>, pagination: array<string,mixed>}
     */
    public static function history(PDO $pdo, int $userId, string $type, array $query = []): array
    {
        self::bootstrap($pdo);
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($query['per_page'] ?? $query['limit'] ?? 20)));
        $offset = ($page - 1) * $perPage;
        $status = trim((string) ($query['status'] ?? ''));
        $trx = trim((string) ($query['trx'] ?? ''));
        $where = ['user_id = :user_id', 'type = :type'];
        $params = ['user_id' => $userId, 'type' => $type];
        if ($status !== '') {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }
        if ($trx !== '') {
            $where[] = 'trx = :trx';
            $params['trx'] = $trx;
        }
        $whereSql = implode(' AND ', $where);
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM megapayz_transactions WHERE ' . $whereSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT * FROM megapayz_transactions WHERE ' . $whereSql . ' ORDER BY id DESC LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rows[] = self::historyRow($row);
        }
        $totalPages = max(1, (int) ceil($total / $perPage));
        return [
            'items' => $rows,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
                'hasPrev' => $page > 1,
                'hasNext' => $page < $totalPages,
            ],
        ];
    }

    public static function findUserTransactionByTrx(PDO $pdo, int $userId, string $trx, string $type = 'deposit'): ?array
    {
        $history = self::history($pdo, $userId, $type, ['trx' => trim($trx), 'limit' => 1]);
        $items = is_array($history['items'] ?? null) ? $history['items'] : [];

        return isset($items[0]) && is_array($items[0]) ? $items[0] : null;
    }

    private static function runtimeSchemaChangesAllowed(): bool
    {
        if (in_array(strtolower(trim((string) getenv('APP_ENV'))), ['production', 'prod'], true)) {
            return false;
        }

        $override = trim((string) getenv('ALLOW_RUNTIME_MIGRATIONS'));
        if ($override !== '') {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }

        return true;
    }

    private static function finalizeAffiliatePayoutCallback(
        PDO $pdo,
        int $payoutId,
        string $status,
        string $megapayzTransactionId,
        string $trx,
        string $providerMessage = ''
    ): void {
        $stmt = $pdo->prepare('SELECT * FROM affiliate_payouts WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $payoutId]);
        $payout = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($payout)) {
            return;
        }
        $current = strtolower((string) ($payout['status'] ?? ''));
        if (in_array($current, ['completed', 'rejected', 'cancelled'], true)) {
            return;
        }

        if ($status === 'confirmed') {
            $pdo->prepare(
                "UPDATE affiliate_payouts
                 SET status = 'completed',
                     megapayz_transaction_id = COALESCE(NULLIF(:mp_tx, ''), megapayz_transaction_id),
                     processed_at = NOW(),
                     updated_at = NOW(),
                     admin_notes = CONCAT(IFNULL(admin_notes,''), IF(IFNULL(admin_notes,'')='','', '\n'), :note)
                 WHERE id = :id"
            )->execute([
                'mp_tx' => $megapayzTransactionId,
                'note' => 'MegaPayz callback: ödeme tamamlandı' . ($trx !== '' ? (' [' . $trx . ']') : ''),
                'id' => $payoutId,
            ]);
            $pdo->prepare(
                'UPDATE affiliates SET total_paid = total_paid + :amount WHERE id = :affiliate_id'
            )->execute([
                'amount' => (float) ($payout['amount'] ?? 0),
                'affiliate_id' => (int) ($payout['affiliate_id'] ?? 0),
            ]);
            $pdo->prepare(
                "UPDATE affiliate_commissions SET status = 'paid', paid_at = NOW()
                 WHERE affiliate_id = :affiliate_id AND status = 'approved' AND created_at <= :requested_at"
            )->execute([
                'affiliate_id' => (int) ($payout['affiliate_id'] ?? 0),
                'requested_at' => (string) ($payout['requested_at'] ?? date('Y-m-d H:i:s')),
            ]);
            return;
        }

        // rejected / failed
        if (in_array($current, ['pending', 'approved', 'processing'], true)) {
            $pdo->prepare(
                'UPDATE affiliates SET balance = balance + :amount WHERE id = :affiliate_id'
            )->execute([
                'amount' => (float) ($payout['amount'] ?? 0),
                'affiliate_id' => (int) ($payout['affiliate_id'] ?? 0),
            ]);
        }
        $note = 'MegaPayz callback: ödeme reddedildi/başarısız';
        if ($providerMessage !== '') {
            $note .= ' — ' . mb_substr($providerMessage, 0, 300);
        }
        $pdo->prepare(
            "UPDATE affiliate_payouts
             SET status = 'rejected',
                 megapayz_transaction_id = COALESCE(NULLIF(:mp_tx, ''), megapayz_transaction_id),
                 processed_at = NOW(),
                 updated_at = NOW(),
                 admin_notes = CONCAT(IFNULL(admin_notes,''), IF(IFNULL(admin_notes,'')='','', '\n'), :note)
             WHERE id = :id"
        )->execute([
            'mp_tx' => $megapayzTransactionId,
            'note' => $note,
            'id' => $payoutId,
        ]);
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.columns
                 WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name'
            );
            $stmt->execute(['table_name' => $table, 'column_name' => $column]);
            $cache[$key] = (int) $stmt->fetchColumn() > 0;
        } catch (Throwable) {
            $cache[$key] = false;
        }

        return $cache[$key];
    }

    private static function hash(array $cfg, string $userId, string $username, string $trx): string
    {
        return md5((string) $cfg['sid'] . $userId . $username . $trx . (string) $cfg['private_key']);
    }

    private static function validateAmountAgainstMethod(float $amount, array $methodRow, string $label): string
    {
        $min = (float) ($methodRow['min_amount'] ?? 0);
        $max = (float) ($methodRow['max_amount'] ?? 0);
        if ($amount <= 0) {
            return 'Geçerli bir tutar girin.';
        }
        if ($min > 0 && $amount < $min) {
            return 'Minimum ' . $label . ' tutarı ' . number_format($min, 2, ',', '.') . ' ₺ olmalıdır.';
        }
        if ($max > 0 && $amount > $max) {
            return 'Maksimum ' . $label . ' tutarı ' . number_format($max, 2, ',', '.') . ' ₺ olmalıdır.';
        }

        return '';
    }

    private static function validateWithdrawFields(string $method, array $inputFields): string
    {
        $account = trim((string) ($inputFields['account'] ?? $inputFields['account_number'] ?? ''));
        if ($method === 'banktransfer') {
            $iban = strtoupper(str_replace(' ', '', $account));
            if (!preg_match('/^TR[0-9]{24}$/', $iban)) {
                return 'Geçerli bir IBAN girin.';
            }
        }
        if ($method === 'crypto') {
            if ($account === '') {
                return 'Kripto cüzdan adresi zorunludur.';
            }
            if (trim((string) ($inputFields['bank_id'] ?? $inputFields['crypto_network'] ?? '')) === '') {
                return 'Kripto ağı zorunludur.';
            }
        }
        if ($method === 'wallet' && $account === '') {
            return 'Mega Wallet hesap numarası zorunludur.';
        }

        return '';
    }

    private static function normalizeCallbackStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return match ($status) {
            'success', 'successful', 'complete', 'completed', 'approved', 'confirm', 'confirmed' => 'confirmed',
            'reject', 'rejected', 'declined', 'cancel', 'cancelled', 'canceled' => 'rejected',
            'fail', 'failed', 'error' => 'failed',
            'pending', 'processing' => $status,
            default => $status,
        };
    }

    private static function ipAllowed(string $remoteIp, string $allowlist): bool
    {
        $remoteIp = trim($remoteIp);
        if ($remoteIp === '' || filter_var($remoteIp, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        foreach (preg_split('/[\s,]+/', $allowlist) ?: [] as $item) {
            $item = trim((string) $item);
            if ($item === '') {
                continue;
            }
            if ($item === $remoteIp) {
                return true;
            }
            if (str_ends_with($item, '.*')) {
                $prefix = substr($item, 0, -1);
                if ($prefix !== '' && str_starts_with($remoteIp, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function verifyHash(array $cfg, array $payload): bool
    {
        $expected = self::hash($cfg, (string) ($payload['user_id'] ?? ''), (string) ($payload['username'] ?? ''), (string) ($payload['trx'] ?? ''));
        return hash_equals($expected, (string) ($payload['hash'] ?? ''));
    }

    private static function newTrx(string $prefix): string
    {
        return $prefix . date('YmdHis') . strtoupper(bin2hex(random_bytes(5)));
    }

    private static function fullname(array $user): string
    {
        $full = trim((string) (($user['name'] ?? '') . ' ' . ($user['surname'] ?? '')));
        return $full !== '' ? $full : (string) ($user['username'] ?? '');
    }

    /**
     * MegaPayz withdraw fullname must include first + last (error 74106 otherwise).
     *
     * @return array{name:string,surname:string}
     */
    private static function splitAffiliateFullName(string $fullName, string $fallbackFirst, string $fallbackLast): array
    {
        $fullName = trim(preg_replace('/\s+/u', ' ', $fullName) ?? '');
        if ($fullName === '') {
            return [
                'name' => $fallbackFirst,
                'surname' => $fallbackLast,
            ];
        }

        $parts = preg_split('/\s+/u', $fullName, 2) ?: [];
        $first = trim((string) ($parts[0] ?? ''));
        $last = trim((string) ($parts[1] ?? ''));
        if ($first === '') {
            $first = $fallbackFirst;
        }
        if ($last === '') {
            $last = $fallbackLast;
        }

        return [
            'name' => $first,
            'surname' => $last,
        ];
    }

    private static function basePayload(array $cfg, array $user, string $trx): array
    {
        $userId = (string) ($user['id'] ?? '');
        $username = (string) ($user['username'] ?? '');
        return [
            'sid' => (string) $cfg['sid'],
            'hash' => self::hash($cfg, $userId, $username, $trx),
            'username' => $username,
            'user_id' => $userId,
            'fullname' => self::fullname($user),
            'trx' => $trx,
            'callback_url' => self::defaultCallbackUrl(),
        ];
    }

    private static function defaultReturnUrl(): string
    {
        $site = defined('FRONTEND_URL') ? rtrim((string) FRONTEND_URL, '/') : (defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '');
        return $site !== '' ? $site . '/?profile=open&account=balance&page=history' : '/?profile=open&account=balance&page=history';
    }

    private static function defaultCallbackUrl(): string
    {
        $backend = defined('BACKEND_URL') ? rtrim((string) BACKEND_URL, '/') : rtrim((string) (getenv('BACKEND_URL') ?: getenv('BACKEND_FALLBACK_URL') ?: 'https://admin.vegasroyalspin.com'), '/');
        return $backend . '/api/v2/megapayz-callback';
    }

    private static function postToMegaPayz(array $cfg, string $path, array $payload, int $timeout = 15): array
    {
        $url = rtrim((string) ($cfg['api_base_url'] ?? self::DEFAULT_API_BASE), '/') . $path;
        if (!function_exists('curl_init')) {
            return ['status' => false, 'code' => 500, 'message' => 'cURL extension bulunamadı.'];
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(5, $timeout));
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $raw = curl_exec($ch);
        $errno = (int) curl_errno($ch);
        $err = (string) curl_error($ch);
        // Bu makinenin bu istek için kullandığı gerçek çıkış (egress) IP'sini ve
        // bağlanılan MegaPayz IP'sini kaydediyoruz. "IP whitelist'te olmasına rağmen
        // 74403 alıyoruz" şikayetlerinde, whitelist'e bildirilen IP ile burada
        // görünen local_ip'nin gerçekten aynı olup olmadığını response_payload
        // üzerinden doğrulamak için (birden fazla NIC/egress IP olan sunucularda
        // bu ikisi farklı olabilir).
        $egress = [
            'local_ip' => (string) curl_getinfo($ch, CURLINFO_LOCAL_IP),
            'remote_ip' => (string) curl_getinfo($ch, CURLINFO_PRIMARY_IP),
            'http_code' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
        ];
        curl_close($ch);
        if ($raw === false || $raw === '') {
            return ['status' => false, 'code' => 502, 'message' => $err !== '' ? $err : 'MegaPayz yanıt vermedi.', 'curl_errno' => $errno, '_egress' => $egress];
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return ['status' => false, 'code' => 502, 'message' => 'MegaPayz JSON yanıtı okunamadı.', 'raw' => (string) $raw, '_egress' => $egress];
        }
        $decoded['_egress'] = $egress;
        return $decoded;
    }

    private static function insertTransaction(PDO $pdo, string $type, array $user, string $method, string $trx, float $amount, array $inputFields, array $requestPayload): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO megapayz_transactions
                (type, user_id, username, fullname, method, trx, amount, currency, status, input_fields, request_payload)
             VALUES
                (:type, :user_id, :username, :fullname, :method, :trx, :amount, :currency, :status, :input_fields, :request_payload)'
        );
        $stmt->execute([
            'type' => $type,
            'user_id' => (int) ($user['id'] ?? 0),
            'username' => (string) ($user['username'] ?? ''),
            'fullname' => self::fullname($user),
            'method' => $method,
            'trx' => $trx,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => 'TRY',
            'status' => 'pending',
            'input_fields' => $inputFields !== [] ? json_encode($inputFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'request_payload' => json_encode(self::redactCallbackPayload($requestPayload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private static function storeGatewayResponse(PDO $pdo, string $trx, array $response): void
    {
        $pdo->prepare('UPDATE megapayz_transactions SET response_payload = :response WHERE trx = :trx')
            ->execute([
                'response' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'trx' => $trx,
            ]);
    }

    private static function markTransactionFailed(PDO $pdo, string $trx, string $message): void
    {
        $pdo->prepare("UPDATE megapayz_transactions SET status = 'failed', failure_message = :message WHERE trx = :trx")
            ->execute(['message' => $message, 'trx' => $trx]);
    }

    private static function formatMoney(float $amount, string $currency = 'TRY'): string
    {
        $currency = strtoupper(trim($currency));
        if ($currency === '') {
            $currency = 'TRY';
        }

        return number_format($amount, 2, ',', '.') . ' ' . $currency;
    }

    private static function notifyMember(
        PDO $pdo,
        int $userId,
        string $title,
        string $body = '',
        string $type = 'info',
        ?string $actionUrl = null
    ): void {
        if ($userId <= 0 || $title === '') {
            return;
        }
        try {
            if (!class_exists('MemberNotificationService', false)) {
                foreach ([
                    __DIR__ . '/MemberNotificationService.php',
                    dirname(__DIR__) . '/services/MemberNotificationService.php',
                    dirname(__DIR__, 2) . '/services/MemberNotificationService.php',
                ] as $file) {
                    if (is_readable($file)) {
                        require_once $file;
                        break;
                    }
                }
            }
            if (!class_exists('MemberNotificationService', false)) {
                return;
            }
            MemberNotificationService::create($pdo, $userId, $title, $body, $type, $actionUrl);
        } catch (Throwable) {
            // Bildirim hatası ödeme akışını bozmamalı.
        }
    }

    private static function notifyPaymentStatus(
        PDO $pdo,
        string $type,
        int $userId,
        string $status,
        float $amount,
        string $currency = 'TRY',
        string $extra = ''
    ): void {
        if ($userId <= 0 || !in_array($status, ['confirmed', 'rejected', 'failed'], true)) {
            return;
        }

        $isDeposit = $type === 'deposit';
        $money = self::formatMoney($amount, $currency);
        $historyUrl = '/profile/deposit-withdraw-history';
        $extra = trim($extra);

        if ($status === 'confirmed') {
            self::notifyMember(
                $pdo,
                $userId,
                $isDeposit ? 'Yatırım onaylandı' : 'Çekim tamamlandı',
                $isDeposit
                    ? $money . ' tutarındaki yatırımınız bakiyenize eklendi.'
                    : $money . ' tutarındaki çekim talebiniz tamamlandı.',
                'success',
                $historyUrl
            );
            self::sendPaymentApprovedMail($pdo, $type, $userId, $amount, $currency);
            return;
        }

        $outcome = $status === 'failed' ? 'başarısız oldu' : 'reddedildi';
        $title = $isDeposit
            ? ($status === 'failed' ? 'Yatırım başarısız' : 'Yatırım reddedildi')
            : ($status === 'failed' ? 'Çekim başarısız' : 'Çekim reddedildi');
        $body = $isDeposit
            ? $money . ' tutarındaki yatırım işleminiz ' . $outcome . '.'
            : $money . ' tutarındaki çekim talebiniz ' . $outcome . '. Tutar bakiyenize iade edildi.';
        if ($extra !== '') {
            $body .= ' ' . $extra;
        }

        self::notifyMember($pdo, $userId, $title, $body, 'warning', $historyUrl);
    }

    private static function sendPaymentApprovedMail(
        PDO $pdo,
        string $type,
        int $userId,
        float $amount,
        string $currency = 'TRY'
    ): void {
        if ($userId <= 0 || !in_array($type, ['deposit', 'withdraw'], true)) {
            return;
        }

        try {
            if (!class_exists('MemberTransactionalMail', false)) {
                foreach ([
                    shared_project_root() . '/admin/app/Services/MemberTransactionalMail.php',
                    shared_package_root() . '/app/Services/MemberTransactionalMail.php',
                ] as $file) {
                    if (is_readable($file)) {
                        require_once $file;
                        break;
                    }
                }
            }
            if (!class_exists('MemberTransactionalMail', false)) {
                return;
            }
            if ($type === 'deposit') {
                MemberTransactionalMail::sendDepositApproved($pdo, $userId, $amount, $currency);
            } else {
                MemberTransactionalMail::sendWithdrawApproved($pdo, $userId, $amount, $currency);
            }
        } catch (Throwable) {
            // Mail hatası ödeme akışını bozmamalı.
        }
    }

    private static function refundWithdraw(PDO $pdo, string $trx, float $amount, int $userId, string $message): void
    {
        try {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE users SET balance = balance + :amount WHERE id = :id')
                ->execute(['amount' => number_format($amount, 2, '.', ''), 'id' => $userId]);
            self::markTransactionFailed($pdo, $trx, $message);
            $pdo->commit();
        } catch (Throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }

    private static function insertCallback(PDO $pdo, string $type, string $trx, string $txId, bool $valid, array $payload): int
    {
        $stmt = $pdo->prepare(
            'INSERT INTO megapayz_callbacks (type, trx, megapayz_transaction_id, hash_valid, payload)
             VALUES (:type, :trx, :tx_id, :hash_valid, :payload)'
        );
        $stmt->execute([
            'type' => $type,
            'trx' => $trx,
            'tx_id' => $txId !== '' ? $txId : null,
            'hash_valid' => $valid ? 1 : 0,
            'payload' => json_encode(self::redactCallbackPayload($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return (int) $pdo->lastInsertId();
    }

    private static function redactCallbackPayload(array $payload): array
    {
        foreach (['hash', 'token', 'secret', 'private_key', 'api_key'] as $key) {
            if (array_key_exists($key, $payload)) {
                $payload[$key] = '[redacted]';
            }
        }

        return $payload;
    }

    private static function markCallbackProcessed(PDO $pdo, int $callbackId, string $message): void
    {
        if ($callbackId <= 0) {
            return;
        }

        $pdo->prepare('UPDATE megapayz_callbacks SET processed = 1, message = :message WHERE id = :id')
            ->execute(['message' => $message, 'id' => $callbackId]);
    }

    private static function historyRow(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'method' => (string) ($row['method'] ?? ''),
            'provider' => 'megapayz',
            'trx' => (string) ($row['trx'] ?? ''),
            'referenceCode' => (string) ($row['trx'] ?? ''),
            'reference_code' => (string) ($row['trx'] ?? ''),
            'megapayzTransactionId' => (string) ($row['megapayz_transaction_id'] ?? ''),
            'megapayz_transaction_id' => (string) ($row['megapayz_transaction_id'] ?? ''),
            'amount' => (float) ($row['amount'] ?? 0),
            'fee' => (float) ($row['fee'] ?? 0),
            'currency' => (string) ($row['currency'] ?? 'TRY'),
            'status' => (string) ($row['status'] ?? ''),
            'admin_status' => null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
            'finalized_at' => (string) ($row['finalized_at'] ?? ''),
            'finalizedAt' => (string) ($row['finalized_at'] ?? ''),
        ];
    }
}
