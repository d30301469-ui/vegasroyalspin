<?php

declare(strict_types=1);

final class MegaPayzService
{
    private const DEFAULT_API_BASE = 'https://api.megapayz.net';

    public static function bootstrap(PDO $pdo): void
    {
        if ((string) getenv('METROPOL_RUNTIME_PROVIDER_BOOTSTRAP') !== '1' || !self::runtimeSchemaChangesAllowed()) {
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
            if (is_readable(dirname(__DIR__) . '/services/ComplianceMonitorService.php')) {
                require_once dirname(__DIR__) . '/services/ComplianceMonitorService.php';
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
            $txAmount = round((float) ($tx['amount'] ?? 0), 2);
            if (abs(round($amount, 2) - $txAmount) > 0.01) {
                $pdo->rollBack();
                return ['status' => false, 'code' => 99999, 'message' => 'Callback amount mismatch'];
            }
            $oldStatus = strtolower((string) ($tx['status'] ?? ''));
            $userId = (int) ($tx['user_id'] ?? 0);
            if (in_array($oldStatus, ['confirmed', 'rejected', 'failed', 'cancelled'], true)) {
                self::markCallbackProcessed($pdo, $callbackId, 'Duplicate final callback ignored');
                $pdo->commit();
                return ['status' => true, 'code' => 200, 'message' => 'OK'];
            }
            if ($type === 'deposit' && $status === 'confirmed' && $oldStatus !== 'confirmed') {
                $pdo->prepare('UPDATE users SET balance = balance + :amount WHERE id = :id')
                    ->execute(['amount' => number_format($amount, 2, '.', ''), 'id' => $userId]);
                WageringService::registerDeposit($pdo, $userId, $amount);
            }
            // Çekim talebinde bakiye, talep anında düşülür. Sağlayıcı işlemi
            // reddederse VEYA başarısız (failed) olursa kullanıcıya iade edilmeli.
            // Final durum guard'ı (yukarıda) tekrar iadeyi engeller; bu blok yalnızca
            // pending/processing -> rejected|failed geçişinde çalışır.
            if ($type === 'withdraw' && in_array($status, ['rejected', 'failed'], true)) {
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
            self::markCallbackProcessed($pdo, $callbackId, 'OK');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['status' => false, 'code' => 99999, 'message' => 'Callback could not be processed'];
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
     * @param array<string, mixed> $server
     * @return array{valid: bool, code?: int, error?: string}
     */
    public static function verifyCallbackTransport(array $server): array
    {
        $allowedIps = trim((string) (getenv('MEGAPAYZ_CALLBACK_ALLOWED_IPS') ?: ''));
        if ($allowedIps !== '' && !self::ipAllowed((string) ($server['REMOTE_ADDR'] ?? ''), $allowedIps)) {
            return ['valid' => false, 'code' => 403, 'error' => 'IP_NOT_ALLOWED'];
        }

        $expectedToken = trim((string) (getenv('MEGAPAYZ_CALLBACK_TOKEN') ?: ''));
        if ($expectedToken === '') {
            return ['valid' => true];
        }

        $token = trim((string) (
            $server['HTTP_X_MEGAPAYZ_CALLBACK_TOKEN']
            ?? $server['HTTP_X_CALLBACK_TOKEN']
            ?? ''
        ));

        if ($token === '' || !hash_equals($expectedToken, $token)) {
            return ['valid' => false, 'code' => 403, 'error' => 'INVALID_CALLBACK_TOKEN'];
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
        $where = ['user_id = :user_id', 'type = :type'];
        $params = ['user_id' => $userId, 'type' => $type];
        if ($status !== '') {
            $where[] = 'status = :status';
            $params['status'] = $status;
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
        return $site !== '' ? $site . '/profile/deposit-withdraw-history' : '/profile/deposit-withdraw-history';
    }

    private static function defaultCallbackUrl(): string
    {
        $backend = defined('BACKEND_URL') ? rtrim((string) BACKEND_URL, '/') : rtrim((string) (getenv('BACKEND_URL') ?: getenv('BACKEND_FALLBACK_URL') ?: 'https://bo-backoffice.site'), '/');
        return $backend . '/MegaPayz/deposit';
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
        ];
    }
}
