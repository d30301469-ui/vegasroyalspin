<?php

declare(strict_types=1);

/**
 * AML / Risk motoru — gerçek zamanlı + batch.
 *
 * Gerçek zamanlı:
 *  - evaluateWithdraw()  çekim talebi
 *  - evaluateDeposit()   yatırım onayı
 *
 * Batch (cron):
 *  - runBatchScan()      çoklu hesap, KYC, hız, skor
 */
final class ComplianceMonitorService
{
    public static function evaluateWithdraw(PDO $pdo, int $userId, float $amount, string $trx, string $method = ''): void
    {
        if ($userId <= 0 || $amount <= 0) {
            return;
        }
        self::boot($pdo);
        $profile = self::userProfile($pdo, $userId);
        $lifetime = self::lifetimeTotals($pdo, $userId);

        $largeThreshold = self::envFloat('COMPLIANCE_AML_WITHDRAW_THRESHOLD', 25000);
        if ($amount >= $largeThreshold) {
            ComplianceService::createAmlAlert($pdo, [
                'user_id' => $userId,
                'rule_code' => 'large_withdraw',
                'severity' => $amount >= $largeThreshold * 2 ? 'critical' : 'high',
                'title' => 'Yüksek tutarlı çekim talebi',
                'description' => sprintf('%.2f TRY çekim talebi (eşik: %.2f).', $amount, $largeThreshold),
                'payload' => [
                    'trx' => $trx,
                    'amount' => $amount,
                    'method' => $method,
                    'lifetime_deposits' => $lifetime['deposits'],
                    'lifetime_withdrawals' => $lifetime['withdrawals'],
                ],
            ]);
        }

        $rapidMin = self::envFloat('COMPLIANCE_AML_RAPID_WITHDRAW_MIN', 5000);
        $hours = max(1, (int) self::envFloat('COMPLIANCE_AML_DEPOSIT_WINDOW_HOURS', 24));
        if ($amount >= $rapidMin && self::hasRecentConfirmedDeposit($pdo, $userId, $hours)) {
            ComplianceService::createAmlAlert($pdo, [
                'user_id' => $userId,
                'rule_code' => 'rapid_deposit_withdraw',
                'severity' => 'high',
                'title' => 'Hızlı yatırım sonrası çekim',
                'description' => sprintf('Son %d saatte yatırım sonrası %.2f TRY çekim.', $hours, $amount),
                'payload' => ['trx' => $trx, 'amount' => $amount, 'window_hours' => $hours, 'method' => $method],
            ]);
        }

        // Yeni hesap + yüksek çekim
        $newAccountHours = max(1, (int) self::envFloat('COMPLIANCE_AML_NEW_ACCOUNT_HOURS', 72));
        $newAccountMin = self::envFloat('COMPLIANCE_AML_NEW_ACCOUNT_WITHDRAW_MIN', 10000);
        if ($amount >= $newAccountMin && self::accountAgeHours($profile) <= $newAccountHours) {
            ComplianceService::createAmlAlert($pdo, [
                'user_id' => $userId,
                'rule_code' => 'new_account_large_withdraw',
                'severity' => 'critical',
                'title' => 'Yeni hesap yüksek çekim',
                'description' => sprintf(
                    'Hesap yaşı %.0f saat · %.2f TRY çekim.',
                    self::accountAgeHours($profile),
                    $amount
                ),
                'payload' => [
                    'trx' => $trx,
                    'amount' => $amount,
                    'account_age_hours' => self::accountAgeHours($profile),
                    'created_at' => $profile['created_at'] ?? null,
                ],
            ]);
        }

        // Çekim > toplam yatırım (veya yatırım yokken çekim)
        $overRatio = self::envFloat('COMPLIANCE_AML_WITHDRAW_OVER_DEPOSIT_RATIO', 1.0);
        $deposits = $lifetime['deposits'];
        if ($deposits <= 0 && $amount >= self::envFloat('COMPLIANCE_AML_NO_DEPOSIT_WITHDRAW_MIN', 1000)) {
            ComplianceService::createAmlAlert($pdo, [
                'user_id' => $userId,
                'rule_code' => 'withdraw_without_deposit',
                'severity' => 'critical',
                'title' => 'Yatırımsız çekim',
                'description' => sprintf('Onaylı yatırım yokken %.2f TRY çekim talebi.', $amount),
                'payload' => ['trx' => $trx, 'amount' => $amount, 'lifetime_deposits' => $deposits],
            ]);
        } elseif ($deposits > 0 && ($lifetime['withdrawals'] + $amount) > ($deposits * $overRatio)) {
            ComplianceService::createAmlAlert($pdo, [
                'user_id' => $userId,
                'rule_code' => 'withdraw_exceeds_deposits',
                'severity' => 'high',
                'title' => 'Çekim yatırımını aşıyor',
                'description' => sprintf(
                    'Toplam çekim (talep dahil) %.2f · toplam yatırım %.2f (oran eşiği %.2fx).',
                    $lifetime['withdrawals'] + $amount,
                    $deposits,
                    $overRatio
                ),
                'payload' => [
                    'trx' => $trx,
                    'amount' => $amount,
                    'lifetime_deposits' => $deposits,
                    'lifetime_withdrawals' => $lifetime['withdrawals'],
                ],
            ]);
        }

        // KYC yok + yüksek çekim
        $kycMin = self::envFloat('COMPLIANCE_AML_KYC_WITHDRAW_MIN', 15000);
        if ($amount >= $kycMin && !self::hasApprovedKyc($pdo, $userId) && (int) ($profile['is_verified'] ?? 0) !== 1) {
            ComplianceService::createAmlAlert($pdo, [
                'user_id' => $userId,
                'rule_code' => 'withdraw_without_kyc',
                'severity' => 'high',
                'title' => 'KYC’siz yüksek çekim',
                'description' => sprintf('Onaylı KYC yok · %.2f TRY çekim.', $amount),
                'payload' => ['trx' => $trx, 'amount' => $amount, 'is_verified' => (int) ($profile['is_verified'] ?? 0)],
            ]);
        }

        $pendingCount = self::countPendingWithdrawals($pdo, $userId);
        if ($pendingCount >= 2) {
            ComplianceService::createRiskAlert($pdo, [
                'user_id' => $userId,
                'alert_type' => 'multiple_pending_withdrawals',
                'severity' => $pendingCount >= 3 ? 'high' : 'medium',
                'title' => 'Çoklu bekleyen çekim',
                'description' => sprintf('%d bekleyen çekim talebi.', $pendingCount),
                'payload' => ['pending_count' => $pendingCount, 'latest_trx' => $trx],
            ]);
        }

        self::refreshUserRiskScore($pdo, $userId);
    }

    public static function evaluateDeposit(PDO $pdo, int $userId, float $amount, string $trx, string $method = ''): void
    {
        if ($userId <= 0 || $amount <= 0) {
            return;
        }
        self::boot($pdo);
        $profile = self::userProfile($pdo, $userId);

        $largeThreshold = self::envFloat('COMPLIANCE_AML_DEPOSIT_THRESHOLD', 25000);
        if ($amount >= $largeThreshold) {
            ComplianceService::createAmlAlert($pdo, [
                'user_id' => $userId,
                'rule_code' => 'large_deposit',
                'severity' => $amount >= $largeThreshold * 2 ? 'critical' : 'high',
                'title' => 'Yüksek tutarlı yatırım',
                'description' => sprintf('%.2f TRY yatırım onaylandı (eşik: %.2f).', $amount, $largeThreshold),
                'payload' => ['trx' => $trx, 'amount' => $amount, 'method' => $method],
            ]);
        }

        // Structuring: kısa pencerede çok sayıda orta-küçük yatırım
        $structWindow = max(1, (int) self::envFloat('COMPLIANCE_AML_STRUCTURING_HOURS', 24));
        $structCount = max(3, (int) self::envFloat('COMPLIANCE_AML_STRUCTURING_COUNT', 5));
        $structMaxEach = self::envFloat('COMPLIANCE_AML_STRUCTURING_MAX_EACH', 10000);
        $structMinTotal = self::envFloat('COMPLIANCE_AML_STRUCTURING_MIN_TOTAL', 20000);
        $windowStats = self::depositWindowStats($pdo, $userId, $structWindow);
        if (
            $windowStats['count'] >= $structCount
            && $windowStats['max'] <= $structMaxEach
            && $windowStats['total'] >= $structMinTotal
        ) {
            ComplianceService::createAmlAlert($pdo, [
                'user_id' => $userId,
                'rule_code' => 'deposit_structuring',
                'severity' => 'high',
                'title' => 'Parçalı yatırım (structuring) şüphesi',
                'description' => sprintf(
                    'Son %d saatte %d yatırım · toplam %.2f TRY · max tek işlem %.2f.',
                    $structWindow,
                    $windowStats['count'],
                    $windowStats['total'],
                    $windowStats['max']
                ),
                'payload' => array_merge($windowStats, ['trx' => $trx, 'amount' => $amount, 'window_hours' => $structWindow]),
            ]);
        }

        $newAccountHours = max(1, (int) self::envFloat('COMPLIANCE_AML_NEW_ACCOUNT_HOURS', 72));
        $newDepositMin = self::envFloat('COMPLIANCE_AML_NEW_ACCOUNT_DEPOSIT_MIN', 15000);
        if ($amount >= $newDepositMin && self::accountAgeHours($profile) <= $newAccountHours) {
            ComplianceService::createRiskAlert($pdo, [
                'user_id' => $userId,
                'alert_type' => 'new_account_large_deposit',
                'severity' => 'medium',
                'title' => 'Yeni hesap yüksek yatırım',
                'description' => sprintf('Hesap yaşı %.0f saat · %.2f TRY yatırım.', self::accountAgeHours($profile), $amount),
                'payload' => [
                    'trx' => $trx,
                    'amount' => $amount,
                    'account_age_hours' => self::accountAgeHours($profile),
                ],
            ]);
        }

        self::refreshUserRiskScore($pdo, $userId);
    }

    /**
     * Periyodik tarama — çoklu hesap, KYC, hız, skor.
     *
     * @return array{scanned:int,aml:int,risk:int,scored:int,errors:list<string>}
     */
    public static function runBatchScan(PDO $pdo): array
    {
        self::boot($pdo);
        $summary = ['scanned' => 0, 'aml' => 0, 'risk' => 0, 'scored' => 0, 'errors' => []];

        try {
            $summary['aml'] += self::scanDuplicateIdentity($pdo);
            $summary['risk'] += self::scanDuplicatePhone($pdo);
            $summary['risk'] += self::scanKycHighBalance($pdo);
            $summary['risk'] += self::scanHighVelocityWithdraw($pdo);
            $summary['aml'] += self::scanDepositWithdrawRatio($pdo);
        } catch (Throwable $e) {
            $summary['errors'][] = $e->getMessage();
        }

        // Skor yenile: son 7 günde işlem yapan üyeler
        try {
            $ids = $pdo->query(
                "SELECT DISTINCT user_id FROM megapayz_transactions
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND user_id IS NOT NULL
                 LIMIT 500"
            )->fetchAll(PDO::FETCH_COLUMN) ?: [];
            foreach ($ids as $id) {
                $uid = (int) $id;
                if ($uid <= 0) {
                    continue;
                }
                $summary['scanned']++;
                self::refreshUserRiskScore($pdo, $uid);
                $summary['scored']++;
            }
        } catch (Throwable $e) {
            $summary['errors'][] = 'score:' . $e->getMessage();
        }

        return $summary;
    }

    public static function refreshUserRiskScore(PDO $pdo, int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }
        self::boot($pdo);
        $score = 0;
        $factors = [];

        $openAml = self::countOpenAlerts($pdo, 'aml_alerts', $userId);
        $openRisk = self::countOpenAlerts($pdo, 'risk_alerts', $userId);
        if ($openAml > 0) {
            $add = min(40, $openAml * 15);
            $score += $add;
            $factors['open_aml'] = $openAml;
        }
        if ($openRisk > 0) {
            $add = min(25, $openRisk * 10);
            $score += $add;
            $factors['open_risk'] = $openRisk;
        }

        $lifetime = self::lifetimeTotals($pdo, $userId);
        if ($lifetime['deposits'] > 0 && $lifetime['withdrawals'] > $lifetime['deposits']) {
            $score += 20;
            $factors['withdraw_gt_deposit'] = true;
        }

        $profile = self::userProfile($pdo, $userId);
        if (self::accountAgeHours($profile) <= 72 && $lifetime['withdrawals'] >= 5000) {
            $score += 15;
            $factors['new_account_withdraw'] = true;
        }
        if (!self::hasApprovedKyc($pdo, $userId) && (int) ($profile['is_verified'] ?? 0) !== 1 && $lifetime['deposits'] >= 10000) {
            $score += 10;
            $factors['no_kyc_high_deposit'] = true;
        }

        $dupPhone = self::countUsersSharingPhone($pdo, (string) ($profile['phone'] ?? ''), $userId);
        if ($dupPhone >= 1) {
            $score += min(20, $dupPhone * 10);
            $factors['shared_phone'] = $dupPhone + 1;
        }
        $dupId = self::countUsersSharingIdentity($pdo, (string) ($profile['identity_number'] ?? ''), $userId);
        if ($dupId >= 1) {
            $score += min(25, $dupId * 12);
            $factors['shared_identity'] = $dupId + 1;
        }

        $score = max(0, min(100, $score));
        $level = match (true) {
            $score >= 80 => 'critical',
            $score >= 60 => 'high',
            $score >= 35 => 'medium',
            $score >= 15 => 'low',
            default => 'clear',
        };

        ComplianceService::upsertRiskScore($pdo, $userId, $score, $level, $factors);

        return $score;
    }

    private static function boot(PDO $pdo): void
    {
        if (!class_exists('ComplianceService', false)) {
            foreach ([
                __DIR__ . '/ComplianceService.php',
                dirname(__DIR__) . '/services/ComplianceService.php',
                shared_project_root() . '/admin/services/ComplianceService.php',
            ] as $path) {
                if (is_readable($path)) {
                    require_once $path;
                    break;
                }
            }
        }
        if (class_exists('ComplianceService', false)) {
            ComplianceService::ensureTables($pdo);
        }
    }

    private static function envFloat(string $key, float $default): float
    {
        $raw = getenv($key);
        if ($raw === false || $raw === '') {
            $raw = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        }
        if ($raw === null || $raw === '') {
            return $default;
        }

        return (float) $raw;
    }

    /** @return array<string, mixed> */
    private static function userProfile(PDO $pdo, int $userId): array
    {
        try {
            $st = $pdo->prepare(
                'SELECT id, username, name, surname, phone, identity_number, is_verified, banned, balance, bonus_balance, created_at
                 FROM users WHERE id = :id LIMIT 1'
            );
            $st->execute(['id' => $userId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : [];
        } catch (Throwable) {
            return [];
        }
    }

    private static function accountAgeHours(array $profile): float
    {
        $created = (string) ($profile['created_at'] ?? '');
        if ($created === '') {
            return 9999.0;
        }
        $ts = strtotime($created);
        if ($ts === false) {
            return 9999.0;
        }

        return max(0.0, (time() - $ts) / 3600);
    }

    /** @return array{deposits:float,withdrawals:float} */
    private static function lifetimeTotals(PDO $pdo, int $userId): array
    {
        $out = ['deposits' => 0.0, 'withdrawals' => 0.0];
        try {
            $st = $pdo->prepare(
                "SELECT
                    COALESCE(SUM(CASE WHEN type = 'deposit' AND status IN ('confirmed','success','completed') THEN amount ELSE 0 END), 0) AS deposits,
                    COALESCE(SUM(CASE WHEN type = 'withdraw' AND status IN ('confirmed','success','completed','pending','processing') THEN amount ELSE 0 END), 0) AS withdrawals
                 FROM megapayz_transactions WHERE user_id = :uid"
            );
            $st->execute(['uid' => $userId]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $out['deposits'] = round((float) ($row['deposits'] ?? 0), 2);
            $out['withdrawals'] = round((float) ($row['withdrawals'] ?? 0), 2);
        } catch (Throwable) {
        }

        return $out;
    }

    /** @return array{count:int,total:float,max:float} */
    private static function depositWindowStats(PDO $pdo, int $userId, int $hours): array
    {
        $out = ['count' => 0, 'total' => 0.0, 'max' => 0.0];
        try {
            $st = $pdo->prepare(
                "SELECT COUNT(*) AS c, COALESCE(SUM(amount),0) AS t, COALESCE(MAX(amount),0) AS m
                 FROM megapayz_transactions
                 WHERE user_id = :uid AND type = 'deposit'
                   AND status IN ('confirmed','success','completed')
                   AND created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)"
            );
            $st->bindValue(':uid', $userId, PDO::PARAM_INT);
            $st->bindValue(':hours', $hours, PDO::PARAM_INT);
            $st->execute();
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $out['count'] = (int) ($row['c'] ?? 0);
            $out['total'] = round((float) ($row['t'] ?? 0), 2);
            $out['max'] = round((float) ($row['m'] ?? 0), 2);
        } catch (Throwable) {
        }

        return $out;
    }

    private static function hasRecentConfirmedDeposit(PDO $pdo, int $userId, int $hours): bool
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM megapayz_transactions WHERE user_id = :user_id AND type = 'deposit'
                 AND status IN ('confirmed','success','completed')
                 AND created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)"
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':hours', $hours, PDO::PARAM_INT);
            $stmt->execute();

            return (int) $stmt->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private static function countPendingWithdrawals(PDO $pdo, int $userId): int
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM megapayz_transactions WHERE user_id = :user_id AND type = 'withdraw' AND status = 'pending'"
            );
            $stmt->execute(['user_id' => $userId]);

            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private static function hasApprovedKyc(PDO $pdo, int $userId): bool
    {
        try {
            $st = $pdo->prepare(
                "SELECT COUNT(*) FROM kyc_requests WHERE user_id = :uid AND status IN ('approved','verified','accepted')"
            );
            $st->execute(['uid' => $userId]);

            return (int) $st->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private static function countOpenAlerts(PDO $pdo, string $table, int $userId): int
    {
        if (!in_array($table, ['aml_alerts', 'risk_alerts'], true)) {
            return 0;
        }
        try {
            $st = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE user_id = :uid AND status = 'open'");
            $st->execute(['uid' => $userId]);

            return (int) $st->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private static function countUsersSharingPhone(PDO $pdo, string $phone, int $excludeUserId): int
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($phone) < 10) {
            return 0;
        }
        try {
            $st = $pdo->prepare(
                "SELECT COUNT(*) FROM users
                 WHERE id <> :uid AND REPLACE(REPLACE(REPLACE(COALESCE(phone,''),' ',''),'-',''),'+','') LIKE :phone
                   AND COALESCE(banned,0) = 0"
            );
            $st->execute(['uid' => $excludeUserId, 'phone' => '%' . substr($phone, -10)]);

            return (int) $st->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private static function countUsersSharingIdentity(PDO $pdo, string $identity, int $excludeUserId): int
    {
        $identity = preg_replace('/\D+/', '', $identity) ?? '';
        if (strlen($identity) < 8) {
            return 0;
        }
        try {
            $st = $pdo->prepare(
                'SELECT COUNT(*) FROM users WHERE id <> :uid AND identity_number = :idn AND COALESCE(banned,0) = 0'
            );
            $st->execute(['uid' => $excludeUserId, 'idn' => $identity]);

            return (int) $st->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private static function scanDuplicateIdentity(PDO $pdo): int
    {
        $created = 0;
        $min = max(2, (int) self::envFloat('COMPLIANCE_RISK_DUP_IDENTITY_MIN', 2));
        try {
            $rows = $pdo->query(
                "SELECT identity_number, COUNT(*) AS c, GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS ids
                 FROM users
                 WHERE identity_number IS NOT NULL AND identity_number <> '' AND COALESCE(banned,0)=0
                 GROUP BY identity_number
                 HAVING COUNT(*) >= {$min}
                 LIMIT 50"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $ids = array_map('intval', explode(',', (string) ($row['ids'] ?? '')));
                $primary = $ids[0] ?? 0;
                if ($primary <= 0) {
                    continue;
                }
                $id = ComplianceService::createAmlAlert($pdo, [
                    'user_id' => $primary,
                    'rule_code' => 'shared_identity',
                    'severity' => ((int) ($row['c'] ?? 0)) >= 3 ? 'critical' : 'high',
                    'title' => 'Aynı kimlik ile birden fazla hesap',
                    'description' => sprintf('%d hesap aynı kimlik numarasını paylaşıyor.', (int) ($row['c'] ?? 0)),
                    'payload' => [
                        'identity_number' => (string) ($row['identity_number'] ?? ''),
                        'user_ids' => $ids,
                        'count' => (int) ($row['c'] ?? 0),
                    ],
                ]);
                if ($id > 0) {
                    $created++;
                }
            }
        } catch (Throwable) {
        }

        return $created;
    }

    private static function scanDuplicatePhone(PDO $pdo): int
    {
        $created = 0;
        $min = max(2, (int) self::envFloat('COMPLIANCE_RISK_DUP_PHONE_MIN', 2));
        try {
            $rows = $pdo->query(
                "SELECT phone, COUNT(*) AS c, GROUP_CONCAT(id ORDER BY id SEPARATOR ',') AS ids
                 FROM users
                 WHERE phone IS NOT NULL AND phone <> '' AND COALESCE(banned,0)=0
                 GROUP BY phone
                 HAVING COUNT(*) >= {$min}
                 LIMIT 50"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $ids = array_map('intval', explode(',', (string) ($row['ids'] ?? '')));
                $primary = $ids[0] ?? 0;
                if ($primary <= 0) {
                    continue;
                }
                $id = ComplianceService::createRiskAlert($pdo, [
                    'user_id' => $primary,
                    'alert_type' => 'shared_phone',
                    'severity' => ((int) ($row['c'] ?? 0)) >= 3 ? 'high' : 'medium',
                    'title' => 'Aynı telefon ile birden fazla hesap',
                    'description' => sprintf('%d hesap aynı telefonu paylaşıyor.', (int) ($row['c'] ?? 0)),
                    'payload' => [
                        'phone' => (string) ($row['phone'] ?? ''),
                        'user_ids' => $ids,
                        'count' => (int) ($row['c'] ?? 0),
                    ],
                ]);
                if ($id > 0) {
                    $created++;
                }
            }
        } catch (Throwable) {
        }

        return $created;
    }

    private static function scanKycHighBalance(PDO $pdo): int
    {
        $created = 0;
        $minBalance = self::envFloat('COMPLIANCE_RISK_KYC_BALANCE_MIN', 10000);
        try {
            $st = $pdo->prepare(
                "SELECT u.id, u.balance, u.is_verified
                 FROM users u
                 LEFT JOIN kyc_requests k ON k.user_id = u.id AND k.status IN ('approved','verified','accepted')
                 WHERE COALESCE(u.banned,0)=0
                   AND u.balance >= :bal
                   AND COALESCE(u.is_verified,0)=0
                   AND k.id IS NULL
                 ORDER BY u.balance DESC
                 LIMIT 40"
            );
            $st->execute(['bal' => number_format($minBalance, 2, '.', '')]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $uid = (int) ($row['id'] ?? 0);
                if ($uid <= 0) {
                    continue;
                }
                $id = ComplianceService::createRiskAlert($pdo, [
                    'user_id' => $uid,
                    'alert_type' => 'kyc_missing_high_balance',
                    'severity' => ((float) ($row['balance'] ?? 0) >= $minBalance * 3) ? 'high' : 'medium',
                    'title' => 'KYC’siz yüksek bakiye',
                    'description' => sprintf('Bakiye %.2f TRY · onaylı KYC yok.', (float) ($row['balance'] ?? 0)),
                    'payload' => ['balance' => (float) ($row['balance'] ?? 0)],
                ]);
                if ($id > 0) {
                    $created++;
                }
            }
        } catch (Throwable) {
        }

        return $created;
    }

    private static function scanHighVelocityWithdraw(PDO $pdo): int
    {
        $created = 0;
        $hours = max(1, (int) self::envFloat('COMPLIANCE_RISK_WITHDRAW_VELOCITY_HOURS', 24));
        $countMin = max(3, (int) self::envFloat('COMPLIANCE_RISK_WITHDRAW_VELOCITY_COUNT', 3));
        $totalMin = self::envFloat('COMPLIANCE_RISK_WITHDRAW_VELOCITY_TOTAL', 30000);
        try {
            $st = $pdo->prepare(
                "SELECT user_id, COUNT(*) AS c, SUM(amount) AS t
                 FROM megapayz_transactions
                 WHERE type = 'withdraw'
                   AND status IN ('pending','processing','confirmed','success','completed')
                   AND created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
                 GROUP BY user_id
                 HAVING COUNT(*) >= :cnt AND SUM(amount) >= :total
                 LIMIT 40"
            );
            $st->bindValue(':hours', $hours, PDO::PARAM_INT);
            $st->bindValue(':cnt', $countMin, PDO::PARAM_INT);
            $st->bindValue(':total', number_format($totalMin, 2, '.', ''));
            $st->execute();
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $uid = (int) ($row['user_id'] ?? 0);
                if ($uid <= 0) {
                    continue;
                }
                $id = ComplianceService::createRiskAlert($pdo, [
                    'user_id' => $uid,
                    'alert_type' => 'withdraw_velocity',
                    'severity' => 'high',
                    'title' => 'Yüksek hızda çekim aktivitesi',
                    'description' => sprintf(
                        'Son %d saatte %d çekim · toplam %.2f TRY.',
                        $hours,
                        (int) ($row['c'] ?? 0),
                        (float) ($row['t'] ?? 0)
                    ),
                    'payload' => [
                        'count' => (int) ($row['c'] ?? 0),
                        'total' => (float) ($row['t'] ?? 0),
                        'window_hours' => $hours,
                    ],
                ]);
                if ($id > 0) {
                    $created++;
                }
            }
        } catch (Throwable) {
        }

        return $created;
    }

    private static function scanDepositWithdrawRatio(PDO $pdo): int
    {
        $created = 0;
        $days = max(1, (int) self::envFloat('COMPLIANCE_AML_RATIO_DAYS', 7));
        $minDeposit = self::envFloat('COMPLIANCE_AML_RATIO_MIN_DEPOSIT', 5000);
        $ratio = self::envFloat('COMPLIANCE_AML_RATIO_THRESHOLD', 0.9);
        try {
            $st = $pdo->prepare(
                "SELECT user_id,
                    SUM(CASE WHEN type='deposit' AND status IN ('confirmed','success','completed') THEN amount ELSE 0 END) AS dep,
                    SUM(CASE WHEN type='withdraw' AND status IN ('confirmed','success','completed','pending','processing') THEN amount ELSE 0 END) AS wit
                 FROM megapayz_transactions
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                 GROUP BY user_id
                 HAVING dep >= :min_dep AND wit >= dep * :ratio
                 LIMIT 40"
            );
            $st->bindValue(':days', $days, PDO::PARAM_INT);
            $st->bindValue(':min_dep', number_format($minDeposit, 2, '.', ''));
            $st->bindValue(':ratio', number_format($ratio, 4, '.', ''));
            $st->execute();
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $uid = (int) ($row['user_id'] ?? 0);
                if ($uid <= 0) {
                    continue;
                }
                $id = ComplianceService::createAmlAlert($pdo, [
                    'user_id' => $uid,
                    'rule_code' => 'period_withdraw_ratio',
                    'severity' => 'high',
                    'title' => 'Dönemsel çekim/yatırım oranı yüksek',
                    'description' => sprintf(
                        'Son %d günde yatırım %.2f · çekim %.2f.',
                        $days,
                        (float) ($row['dep'] ?? 0),
                        (float) ($row['wit'] ?? 0)
                    ),
                    'payload' => [
                        'deposits' => (float) ($row['dep'] ?? 0),
                        'withdrawals' => (float) ($row['wit'] ?? 0),
                        'days' => $days,
                        'ratio_threshold' => $ratio,
                    ],
                ]);
                if ($id > 0) {
                    $created++;
                }
            }
        } catch (Throwable) {
        }

        return $created;
    }
}
