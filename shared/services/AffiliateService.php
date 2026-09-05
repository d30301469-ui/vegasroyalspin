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

    /**
     * Kayıt gövdesi / cookie / session / promo alanından inbound ortak kodunu seçer.
     * Boş string ?? ile yutulmasın diye sırayla doldurur; partner kodu promo alanındaysa onu da kabul eder.
     *
     * @param array<string, mixed> $input
     */
    public static function pickInboundCode(array $input, string $bonusCode = ''): string
    {
        $candidates = [
            $input['referral_code'] ?? null,
            $input['referralCode'] ?? null,
            $input['ref'] ?? null,
            $_GET['ref'] ?? null,
            $_SERVER['HTTP_X_REFERRAL_CODE'] ?? null,
            $_COOKIE['vrs_ref'] ?? null,
            (session_status() === PHP_SESSION_ACTIVE ? ($_SESSION['referral_code'] ?? null) : null),
            $bonusCode !== '' ? $bonusCode : null,
            $input['bonus_code'] ?? null,
            $input['bonusCode'] ?? null,
        ];

        foreach ($candidates as $raw) {
            if ($raw === null) {
                continue;
            }
            $code = self::normalizeCode((string) $raw);
            if ($code !== '') {
                return $code;
            }
        }

        return '';
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
                $stmt = $pdo->prepare(
                    'SELECT id, referral_code, status
                     FROM affiliates
                     WHERE UPPER(referral_code) = UPPER(:code) AND status = \'active\'
                     LIMIT 1'
                );
                $stmt->execute(['code' => $code]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (is_array($row)) {
                    return [
                        'type' => 'affiliate',
                        'affiliate_id' => (int) $row['id'],
                        'user_id' => 0,
                        'referral_code' => (string) $row['referral_code'],
                        'status' => (string) ($row['status'] ?? 'active'),
                    ];
                }
            } catch (Throwable) {
            }
        }

        try {
            $stmt = $pdo->prepare('SELECT id, referral_code FROM users WHERE UPPER(referral_code) = UPPER(:code) LIMIT 1');
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
        if (strtolower((string) ($resolved['status'] ?? '')) !== 'active') {
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
            $days = max(1, (int) self::CLICK_ATTRIBUTION_DAYS);
            $stmt = $pdo->prepare(
                "SELECT c.referral_code
                 FROM affiliate_clicks c
                 INNER JOIN affiliates a ON a.id = c.affiliate_id AND a.status = 'active'
                 WHERE c.ip_address = :ip
                   AND c.converted = 0
                   AND c.created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
                 ORDER BY c.created_at DESC
                 LIMIT 1"
            );
            $stmt->execute(['ip' => $ip]);
            $code = (string) $stmt->fetchColumn();
        } catch (Throwable) {
            return null;
        }

        return $code !== '' ? self::resolveCode($pdo, $code) : null;
    }

    /**
     * Yeni kaydı referans sahibine bağlar.
     *
     * Öncelik (yanlış ortak yazılmasını engellemek için):
     *  1) Açık inbound kod (link/cookie/promo) → sadece bu kod çözülür
     *  2) Kod boşsa satırdaki referred_by_affiliate_id (local INSERT yolu)
     *  3) Kod tamamen boşsa IP tıklama yedeği
     *
     * Açık kod verilip çözülemezse IP'ye düşülmez; yanlış pre-insert id temizlenir.
     *
     * @return array{type: string, affiliate_id: int, user_id: int, referral_code: string, status: string}|null
     */
    public static function attributeRegistration(PDO $pdo, int $userId, string $code, string $ip = ''): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $code = self::normalizeCode($code);
        $existingAffiliateId = self::existingAffiliateId($pdo, $userId);

        $resolved = null;
        if ($code !== '') {
            $resolved = self::resolveCode($pdo, $code);
            // Açık kod geldi ama aktif ortak/üye değil: IP tahmini yok.
            // Local INSERT ile yanlış yazılmış id varsa temizle (yanlış ortak kalmasın).
            if ($resolved === null) {
                if ($existingAffiliateId > 0) {
                    try {
                        $pdo->prepare('UPDATE users SET referred_by_affiliate_id = NULL WHERE id = :id AND referred_by_affiliate_id = :aid')
                            ->execute(['id' => $userId, 'aid' => $existingAffiliateId]);
                    } catch (Throwable) {
                    }
                }

                return null;
            }
        } elseif ($existingAffiliateId > 0) {
            // Kod yok; satırda zaten doğru yazılmış ortak (local INSERT) → komisyon/tıklama için kullan.
            $resolved = self::resolveAffiliateById($pdo, $existingAffiliateId);
        } elseif ($ip !== '') {
            $resolved = self::resolveByIp($pdo, $ip);
        }

        if ($resolved === null || ($resolved['type'] === 'user' && $resolved['user_id'] === $userId)) {
            return null;
        }
        if ($resolved['type'] === 'affiliate' && strtolower((string) ($resolved['status'] ?? '')) !== 'active') {
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

    private static function existingAffiliateId(PDO $pdo, int $userId): int
    {
        try {
            $stmt = $pdo->prepare('SELECT referred_by_affiliate_id FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $userId]);
            return max(0, (int) $stmt->fetchColumn());
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @return array{type: string, affiliate_id: int, user_id: int, referral_code: string, status: string}|null
     */
    private static function resolveAffiliateById(PDO $pdo, int $affiliateId): ?array
    {
        if ($affiliateId <= 0 || !self::tableExists($pdo, 'affiliates')) {
            return null;
        }
        try {
            $stmt = $pdo->prepare(
                "SELECT id, referral_code, status
                 FROM affiliates
                 WHERE id = :id AND status = 'active'
                 LIMIT 1"
            );
            $stmt->execute(['id' => $affiliateId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return null;
            }

            return [
                'type' => 'affiliate',
                'affiliate_id' => (int) $row['id'],
                'user_id' => 0,
                'referral_code' => (string) $row['referral_code'],
                'status' => (string) ($row['status'] ?? 'active'),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private static function markClickConverted(PDO $pdo, int $affiliateId, int $userId, string $ip): void
    {
        if (!self::tableExists($pdo, 'affiliate_clicks')) {
            return;
        }

        try {
            $params = ['user_id' => $userId, 'affiliate_id' => $affiliateId];
            if ($ip !== '' && $ip !== '0.0.0.0') {
                $sql = 'UPDATE affiliate_clicks
                        SET converted = 1, converted_user_id = :user_id
                        WHERE affiliate_id = :affiliate_id AND converted = 0 AND ip_address = :ip
                        ORDER BY created_at DESC LIMIT 1';
                $params['ip'] = $ip;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                if ($stmt->rowCount() > 0) {
                    return;
                }
            }
            // Mobil/ağ değişiminde tıklama IP'si kayıt IP'sinden farklı olabilir.
            $pdo->prepare(
                'UPDATE affiliate_clicks
                 SET converted = 1, converted_user_id = :user_id
                 WHERE affiliate_id = :affiliate_id AND converted = 0
                 ORDER BY created_at DESC LIMIT 1'
            )->execute(['user_id' => $userId, 'affiliate_id' => $affiliateId]);
        } catch (Throwable $e) {
            error_log('[AffiliateService::markClickConverted] ' . $e->getMessage());
        }
    }

    /**
     * Registration-time CPA is disabled on purpose.
     * CPA is paid once on first qualifying deposit (FTD) by AffiliateCommissionEngine
     * so late first deposits are not skipped and registration never double-pays.
     */
    private static function recordRegistrationCommission(PDO $pdo, int $affiliateId, int $userId): void
    {
        // Intentionally no-op. Kept for call-site compatibility.
        unset($pdo, $affiliateId, $userId);
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

    /**
     * Güncel bakiyeyi onaylı komisyonlar ile bekleyen ödeme taleplerine göre yeniden hesaplar.
     *
     * Çekim talebi oluşturulurken tutar balance'dan düşülür; tamamlanan ödemeler balance'ı tekrar
     * düşürmez. Eski kayıtlarda ödeme tamamlandığı halde komisyon "paid" işaretlendiğinde
     * total_earned - total_paid ile görünen hayalet bakiye oluşabilir — bu metot düzeltir.
     */
    public static function reconcileBalance(PDO $pdo, int $affiliateId): float
    {
        if ($affiliateId <= 0 || !self::tableExists($pdo, 'affiliates')) {
            return 0.0;
        }

        $approved = 0.0;
        $locked = 0.0;

        if (self::tableExists($pdo, 'affiliate_commissions')) {
            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM(amount), 0)
                 FROM affiliate_commissions
                 WHERE affiliate_id = :id AND status = 'approved'"
            );
            $stmt->execute(['id' => $affiliateId]);
            $approved = (float) $stmt->fetchColumn();
        }

        if (self::tableExists($pdo, 'affiliate_payouts')) {
            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM(amount), 0)
                 FROM affiliate_payouts
                 WHERE affiliate_id = :id AND status IN ('pending', 'approved', 'processing')"
            );
            $stmt->execute(['id' => $affiliateId]);
            $locked = (float) $stmt->fetchColumn();
        }

        $balance = max(0.0, round($approved - $locked, 2));

        $pdo->prepare(
            'UPDATE affiliates SET balance = :balance, updated_at = NOW() WHERE id = :id'
        )->execute([
            'balance' => number_format($balance, 2, '.', ''),
            'id' => $affiliateId,
        ]);

        return $balance;
    }

    /**
     * Aynı dönem için birden fazla RevShare satırı varsa fazlalıkları iptal eder.
     * Öncelik: paid > approved (en düşük id).
     *
     * @return list<string>
     */
    public static function cancelDuplicateRevshareCommissions(PDO $pdo, ?int $onlyAffiliateId = null): array
    {
        if (!self::tableExists($pdo, 'affiliate_commissions')) {
            return [];
        }

        $sql = "SELECT affiliate_id, period_start, period_end, COUNT(*) AS cnt
                FROM affiliate_commissions
                WHERE commission_type = 'revshare'
                  AND source = 'game_bet'
                  AND status <> 'cancelled'
                GROUP BY affiliate_id, period_start, period_end
                HAVING cnt > 1";
        if ($onlyAffiliateId !== null && $onlyAffiliateId > 0) {
            $sql = "SELECT affiliate_id, period_start, period_end, COUNT(*) AS cnt
                    FROM affiliate_commissions
                    WHERE affiliate_id = " . (int) $onlyAffiliateId . "
                      AND commission_type = 'revshare'
                      AND source = 'game_bet'
                      AND status <> 'cancelled'
                    GROUP BY affiliate_id, period_start, period_end
                    HAVING cnt > 1";
        }

        $messages = [];
        $groups = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($groups as $group) {
            $affId = (int) ($group['affiliate_id'] ?? 0);
            $ps = (string) ($group['period_start'] ?? '');
            $pe = (string) ($group['period_end'] ?? '');
            if ($affId <= 0 || $ps === '' || $pe === '') {
                continue;
            }

            $stmt = $pdo->prepare(
                "SELECT id, amount, status
                 FROM affiliate_commissions
                 WHERE affiliate_id = :aid
                   AND period_start = :ps
                   AND period_end = :pe
                   AND commission_type = 'revshare'
                   AND source = 'game_bet'
                   AND status <> 'cancelled'
                 ORDER BY CASE status WHEN 'paid' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END, id ASC"
            );
            $stmt->execute(['aid' => $affId, 'ps' => $ps, 'pe' => $pe]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (count($rows) < 2) {
                continue;
            }

            array_shift($rows);
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                $amount = (float) ($row['amount'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $pdo->beginTransaction();
                try {
                    if ($amount > 0 && ($row['status'] ?? '') === 'approved') {
                        $pdo->prepare(
                            'UPDATE affiliates
                             SET balance = GREATEST(0, balance - :amount),
                                 total_earned = GREATEST(0, total_earned - :amount2)
                             WHERE id = :id'
                        )->execute([
                            'amount' => number_format($amount, 2, '.', ''),
                            'amount2' => number_format($amount, 2, '.', ''),
                            'id' => $affId,
                        ]);
                    }
                    $pdo->prepare(
                        "UPDATE affiliate_commissions
                         SET status = 'cancelled',
                             description = CONCAT(description, ' [duplicate period cleanup]')
                         WHERE id = :id"
                    )->execute(['id' => $id]);
                    $pdo->commit();
                    $messages[] = "affiliate={$affId} cancelled duplicate commission #{$id} ({$ps}→{$pe})";
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $messages[] = "affiliate={$affId} duplicate cleanup failed #{$id}: " . $e->getMessage();
                }
            }
            self::reconcileBalance($pdo, $affId);
        }

        return $messages;
    }
}
