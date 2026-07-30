<?php

declare(strict_types=1);

/**
 * Ortaklık (affiliate) ve üye referans takibi.
 *
 * İki ayrı referans modeli aynı anda desteklenir:
 *   • Ortak (affiliate) referansı  → users.referred_by_affiliate_id = affiliates.id
 *   • Üye→üye referansı            → users.referred_by_user_id      = users.id
 * Bir referans kodu her iki tabloda da bulunabileceği için önce affiliates tablosuna bakılır.
 */
final class AffiliateService
{
    /** Tıklama → kayıt eşleşmesinde IP'nin geçerli sayılacağı süre (gün). */
    private const CLICK_ATTRIBUTION_DAYS = 30;

    /** @var array<string, bool> */
    private static array $tableCache = [];

    public static function normalizeCode(string $code): string
    {
        $code = trim($code);
        if ($code === '' || strlen($code) > 64) {
            return '';
        }

        return preg_match('/^[A-Za-z0-9_-]+$/', $code) === 1 ? $code : '';
    }

    public static function tableExists(PDO $pdo, string $table): bool
    {
        if (isset(self::$tableCache[$table])) {
            return self::$tableCache[$table];
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = :name'
            );
            $stmt->execute(['name' => $table]);
            $exists = (int) $stmt->fetchColumn() > 0;
        } catch (Throwable) {
            $exists = false;
        }

        return self::$tableCache[$table] = $exists;
    }

    /**
     * Referans kodunu ortak ya da üye olarak çözer.
     *
     * @return array{type: string, affiliate_id: int, user_id: int, referral_code: string, status: string}|null
     */
    public static function resolveCode(PDO $pdo, string $code): ?array
    {
        $code = self::normalizeCode($code);
        if ($code === '') {
            return null;
        }

        if (self::tableExists($pdo, 'affiliates')) {
            try {
                $stmt = $pdo->prepare('SELECT id, referral_code, status FROM affiliates WHERE referral_code = :code LIMIT 1');
                $stmt->execute(['code' => $code]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    return [
                        'type' => 'affiliate',
                        'affiliate_id' => (int) $row['id'],
                        'user_id' => 0,
                        'referral_code' => (string) $row['referral_code'],
                        'status' => (string) ($row['status'] ?? ''),
                    ];
                }
            } catch (Throwable) {
            }
        }

        try {
            $stmt = $pdo->prepare('SELECT id, referral_code FROM users WHERE referral_code = :code LIMIT 1');
            $stmt->execute(['code' => $code]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                return [
                    'type' => 'user',
                    'affiliate_id' => 0,
                    'user_id' => (int) $row['id'],
                    'referral_code' => (string) $row['referral_code'],
                    'status' => 'active',
                ];
            }
        } catch (Throwable) {
        }

        return null;
    }

    /**
     * Referans linki tıklamasını kaydeder. Kod bir ortağa ait değilse tıklama tablosuna yazılmaz
     * (affiliate_clicks.affiliate_id foreign key ile affiliates tablosuna bağlıdır).
     *
     * @param array{landing_url?: string, ip?: string, user_agent?: string, referrer?: string, country?: string} $context
     */
    public static function trackClick(PDO $pdo, string $code, array $context = []): bool
    {
        $resolved = self::resolveCode($pdo, $code);
        if ($resolved === null || $resolved['type'] !== 'affiliate' || !self::tableExists($pdo, 'affiliate_clicks')) {
            return false;
        }

        $userAgent = (string) ($context['user_agent'] ?? '');

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO affiliate_clicks
                 (affiliate_id, referral_code, landing_url, ip_address, user_agent, referrer_url, country, device_type)
                 VALUES (:affiliate_id, :referral_code, :landing_url, :ip_address, :user_agent, :referrer_url, :country, :device_type)'
            );
            $stmt->execute([
                'affiliate_id' => $resolved['affiliate_id'],
                'referral_code' => $resolved['referral_code'],
                'landing_url' => mb_substr((string) ($context['landing_url'] ?? ''), 0, 500),
                'ip_address' => mb_substr((string) ($context['ip'] ?? ''), 0, 45),
                'user_agent' => mb_substr($userAgent, 0, 500),
                'referrer_url' => mb_substr((string) ($context['referrer'] ?? ''), 0, 500),
                'country' => mb_substr((string) ($context['country'] ?? ''), 0, 8),
                'device_type' => self::deviceType($userAgent),
            ]);

            return true;
        } catch (Throwable $e) {
            error_log('[AffiliateService::trackClick] ' . $e->getMessage());

            return false;
        }
    }

    /**
     * IP adresinden son dönüşmemiş tıklamayı bulur (çerez/oturum kaybolduğunda yedek yol).
     *
     * @return array{type: string, affiliate_id: int, user_id: int, referral_code: string, status: string}|null
     */
    public static function resolveByIp(PDO $pdo, string $ip): ?array
    {
        $ip = trim($ip);
        if ($ip === '' || $ip === '0.0.0.0' || !self::tableExists($pdo, 'affiliate_clicks')) {
            return null;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT c.referral_code
                 FROM affiliate_clicks c
                 INNER JOIN affiliates a ON a.id = c.affiliate_id AND a.status = "active"
                 WHERE c.ip_address = :ip
                   AND c.converted = 0
                   AND c.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                 ORDER BY c.created_at DESC
                 LIMIT 1'
            );
            $stmt->bindValue('ip', $ip);
            $stmt->bindValue('days', self::CLICK_ATTRIBUTION_DAYS, PDO::PARAM_INT);
            $stmt->execute();
            $code = (string) $stmt->fetchColumn();
        } catch (Throwable) {
            return null;
        }

        return $code !== '' ? self::resolveCode($pdo, $code) : null;
    }

    /**
     * Yeni kaydı referans sahibine bağlar. Kod boşsa IP üzerinden çözmeyi dener.
     *
     * @return array{type: string, affiliate_id: int, user_id: int, referral_code: string, status: string}|null
     */
    public static function attributeRegistration(PDO $pdo, int $userId, string $code, string $ip = ''): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $resolved = self::resolveCode($pdo, $code);
        if ($resolved === null && $ip !== '') {
            $resolved = self::resolveByIp($pdo, $ip);
        }
        if ($resolved === null || ($resolved['type'] === 'user' && $resolved['user_id'] === $userId)) {
            return null;
        }

        try {
            if ($resolved['type'] === 'affiliate') {
                $pdo->prepare('UPDATE users SET referred_by_affiliate_id = :affiliate_id WHERE id = :id')
                    ->execute(['affiliate_id' => $resolved['affiliate_id'], 'id' => $userId]);
                self::markClickConverted($pdo, $resolved['affiliate_id'], $userId, $ip);
                self::recordRegistrationCommission($pdo, $resolved['affiliate_id'], $userId);
            } else {
                $pdo->prepare('UPDATE users SET referred_by_user_id = :user_id WHERE id = :id')
                    ->execute(['user_id' => $resolved['user_id'], 'id' => $userId]);
            }
        } catch (Throwable $e) {
            error_log('[AffiliateService::attributeRegistration] ' . $e->getMessage());

            return null;
        }

        return $resolved;
    }

    private static function markClickConverted(PDO $pdo, int $affiliateId, int $userId, string $ip): void
    {
        if (!self::tableExists($pdo, 'affiliate_clicks')) {
            return;
        }

        try {
            $sql = 'UPDATE affiliate_clicks
                    SET converted = 1, converted_user_id = :user_id
                    WHERE affiliate_id = :affiliate_id AND converted = 0';
            $params = ['user_id' => $userId, 'affiliate_id' => $affiliateId];
            if ($ip !== '' && $ip !== '0.0.0.0') {
                $sql .= ' AND ip_address = :ip';
                $params['ip'] = $ip;
            }
            $sql .= ' ORDER BY created_at DESC LIMIT 1';
            $pdo->prepare($sql)->execute($params);
        } catch (Throwable $e) {
            error_log('[AffiliateService::markClickConverted] ' . $e->getMessage());
        }
    }

    /**
     * Plan tipi "registration" komisyonu tanımlıysa kayıt anında komisyon oluşturur.
     * Depozito bazlı (CPA/RevShare) hesaplar affiliate-cron.php tarafından işlenir.
     */
    private static function recordRegistrationCommission(PDO $pdo, int $affiliateId, int $userId): void
    {
        if (!self::tableExists($pdo, 'affiliate_commissions')) {
            return;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT cp.plan_type, cp.cpa_amount, cp.min_deposit
                 FROM affiliates a
                 INNER JOIN affiliate_commission_plans cp ON cp.id = a.commission_plan_id
                 WHERE a.id = :id AND cp.is_active = 1
                 LIMIT 1'
            );
            $stmt->execute(['id' => $affiliateId]);
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable) {
            return;
        }

        if (!is_array($plan)) {
            return;
        }

        // Yalnızca depozito şartı olmayan CPA planları kayıt anında ödenir.
        $planType = (string) ($plan['plan_type'] ?? '');
        $amount = (float) ($plan['cpa_amount'] ?? 0);
        if (!in_array($planType, ['cpa', 'hybrid'], true) || $amount <= 0 || (float) ($plan['min_deposit'] ?? 0) > 0) {
            return;
        }

        try {
            $pdo->prepare(
                'INSERT INTO affiliate_commissions
                 (affiliate_id, user_id, commission_type, amount, status, description, source, period_start, period_end)
                 VALUES (:affiliate_id, :user_id, "cpa", :amount, "pending", :description, "registration", CURDATE(), CURDATE())'
            )->execute([
                'affiliate_id' => $affiliateId,
                'user_id' => $userId,
                'amount' => $amount,
                'description' => 'Kayıt CPA komisyonu (#' . $userId . ')',
            ]);
        } catch (Throwable $e) {
            error_log('[AffiliateService::recordRegistrationCommission] ' . $e->getMessage());
        }
    }

    private static function deviceType(string $userAgent): string
    {
        if ($userAgent === '') {
            return '';
        }
        if (preg_match('/iPad|Tablet|PlayBook|Silk/i', $userAgent) === 1) {
            return 'tablet';
        }
        if (preg_match('/Mobi|Android|iPhone|iPod|Windows Phone/i', $userAgent) === 1) {
            return 'mobile';
        }

        return 'desktop';
    }
}
