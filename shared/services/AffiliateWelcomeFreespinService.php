<?php

declare(strict_types=1);

/**
 * Ortak (affiliate) kayıtlarında otomatik BGaming freespin tanımlar.
 *
 * Kural tablosu genişletilebilir; ilk kural: MT2864 → All Lucky Clover 5 × 60.
 * Not: BGaming gamelist'te AllLuckyClover (base) api_freespins=0; AllLuckyClover5 destekler.
 * Kayıt başarısını bozmamak için tüm hatalar yakalanır / loglanır.
 */
final class AffiliateWelcomeFreespinService
{
    /** @var array<string, array{campaign_code: string, title: string, game_identifier: string, freespins: int, valid_days: int, bet_level: int}> */
    private const RULES = [
        'MT2864' => [
            'campaign_code' => 'affiliate_mt2864_allluckyclover',
            'title' => 'MT2864 — All Lucky Clover 5 · 60 FS',
            'game_identifier' => 'AllLuckyClover5',
            'freespins' => 60,
            'valid_days' => 30,
            'bet_level' => 0,
        ],
    ];

    /**
     * @param array<string, mixed>|null $resolvedAffiliate AffiliateService::attributeRegistration sonucu
     * @return array<string, mixed>|null
     */
    public static function maybeGrantAfterAttribution(PDO $pdo, int $userId, ?array $resolvedAffiliate): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        // resolved yoksa DB'deki referred_by_affiliate_id üzerinden dene (kayıt yolu farkları için).
        if ($resolvedAffiliate === null || ($resolvedAffiliate['type'] ?? '') !== 'affiliate') {
            return self::maybeGrantForUserId($pdo, $userId);
        }

        self::ensureAffiliateServiceLoaded();

        $code = class_exists('AffiliateService', false)
            ? AffiliateService::normalizeCode((string) ($resolvedAffiliate['referral_code'] ?? ''))
            : trim((string) ($resolvedAffiliate['referral_code'] ?? ''));
        if ($code === '') {
            return self::maybeGrantForUserId($pdo, $userId);
        }

        return self::grantForCode($pdo, $userId, $code);
    }

    /**
     * users.referred_by_affiliate_id → affiliates.referral_code → kural.
     *
     * @return array<string, mixed>|null
     */
    public static function maybeGrantForUserId(PDO $pdo, int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT a.referral_code, a.status
                 FROM users u
                 INNER JOIN affiliates a ON a.id = u.referred_by_affiliate_id
                 WHERE u.id = :id
                 LIMIT 1'
            );
            $stmt->execute(['id' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('[AffiliateWelcomeFreespin] lookup user=' . $userId . ' failed: ' . $e->getMessage());

            return null;
        }

        if (!is_array($row) || strtolower((string) ($row['status'] ?? '')) !== 'active') {
            return null;
        }

        self::ensureAffiliateServiceLoaded();
        $code = class_exists('AffiliateService', false)
            ? AffiliateService::normalizeCode((string) ($row['referral_code'] ?? ''))
            : trim((string) ($row['referral_code'] ?? ''));

        return self::grantForCode($pdo, $userId, $code);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function grantForCode(PDO $pdo, int $userId, string $code): ?array
    {
        $rule = self::ruleForCode($code);
        if ($rule === null) {
            return null;
        }

        try {
            $result = self::grant($pdo, $userId, $rule, $code);
            error_log(sprintf(
                '[AffiliateWelcomeFreespin] grant user=%d code=%s ok=%s skipped=%s err=%s',
                $userId,
                $code,
                !empty($result['ok']) ? '1' : '0',
                !empty($result['skipped']) ? '1' : '0',
                (string) ($result['error'] ?? '')
            ));

            return $result;
        } catch (Throwable $e) {
            error_log(sprintf(
                '[AffiliateWelcomeFreespin] user=%d code=%s failed: %s',
                $userId,
                $code,
                $e->getMessage()
            ));

            return [
                'ok' => false,
                'user_id' => $userId,
                'referral_code' => $code,
                'error' => $e->getMessage(),
            ];
        }
    }

    private static function ensureAffiliateServiceLoaded(): void
    {
        if (class_exists('AffiliateService', false)) {
            return;
        }
        $affCandidates = [
            dirname(__DIR__) . '/services/AffiliateService.php',
            dirname(__DIR__, 2) . '/services/AffiliateService.php',
            dirname(__DIR__, 2) . '/admin/services/AffiliateService.php',
        ];
        foreach ($affCandidates as $affFile) {
            if (is_file($affFile)) {
                require_once $affFile;
                break;
            }
        }
    }

    /**
     * @return array{campaign_code: string, title: string, game_identifier: string, freespins: int, valid_days: int, bet_level: int}|null
     */
    private static function ruleForCode(string $code): ?array
    {
        if ($code === '') {
            return null;
        }
        foreach (self::RULES as $key => $rule) {
            if (strcasecmp($key, $code) === 0) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * @param array{campaign_code: string, title: string, game_identifier: string, freespins: int, valid_days: int, bet_level: int} $rule
     * @return array<string, mixed>
     */
    private static function grant(PDO $pdo, int $userId, array $rule, string $referralCode): array
    {
        if (!class_exists('BgamingService', false)) {
            $candidates = [
                dirname(__DIR__) . '/services/BgamingService.php', // shared/services
                dirname(__DIR__, 2) . '/services/BgamingService.php', // repo root / admin sibling
                dirname(__DIR__, 2) . '/admin/services/BgamingService.php',
            ];
            foreach ($candidates as $bgaming) {
                if (is_file($bgaming)) {
                    require_once $bgaming;
                    break;
                }
            }
        }
        if (!class_exists('BgamingService')) {
            throw new RuntimeException('BgamingService yüklenemedi.');
        }

        $campaign = self::ensureCampaign($pdo, $rule, $referralCode);
        $campaignId = (int) ($campaign['id'] ?? 0);
        if ($campaignId <= 0) {
            throw new RuntimeException('Affiliate welcome kampanyası oluşturulamadı.');
        }

        // Idempotent: already issued → skip quietly
        $assignmentCheck = self::existingAssignmentStatus($pdo, $rule['campaign_code'], $userId);
        if (in_array($assignmentCheck, ['active', 'played', 'canceled', 'expired', 'pending'], true)) {
            return [
                'ok' => true,
                'skipped' => true,
                'user_id' => $userId,
                'referral_code' => $referralCode,
                'campaign_code' => $rule['campaign_code'],
                'status' => $assignmentCheck,
            ];
        }

        $result = BgamingService::assignCampaignToUser($pdo, $campaignId, $userId);
        $result['referral_code'] = $referralCode;
        $result['affiliate_auto'] = true;

        if (empty($result['ok'])) {
            error_log(sprintf(
                '[AffiliateWelcomeFreespin] assign failed user=%d code=%s err=%s',
                $userId,
                $referralCode,
                (string) ($result['error'] ?? 'unknown')
            ));
        }

        return $result;
    }

    /**
     * @param array{campaign_code: string, title: string, game_identifier: string, freespins: int, valid_days: int, bet_level: int} $rule
     * @return array<string, mixed>
     */
    private static function ensureCampaign(PDO $pdo, array $rule, string $referralCode): array
    {
        BgamingService::bootstrap($pdo);
        // ensureCampaignStorage is private — bootstrap + campaignByCode path creates storage via public APIs
        $existing = BgamingService::campaignByCode($pdo, $rule['campaign_code']);
        $validDays = max(1, (int) $rule['valid_days']);
        $expiresAt = time() + ($validDays * 86400);
        $beginsAt = time() - 60;

        if ($existing !== null) {
            $id = (int) ($existing['id'] ?? 0);
            $currentExpiry = (int) ($existing['expires_at'] ?? 0);
            $needsRefresh = $currentExpiry < $expiresAt
                || (int) ($existing['active'] ?? 0) !== 1
                || strcasecmp((string) ($existing['game_identifier'] ?? ''), $rule['game_identifier']) !== 0
                || (int) ($existing['freespins_per_player'] ?? 0) !== (int) $rule['freespins'];
            // Keep campaign usable for new grants (at least valid_days ahead) and sync rule changes.
            if ($needsRefresh) {
                $stmt = $pdo->prepare(
                    'UPDATE bgaming_campaigns
                     SET title = :title,
                         game_identifier = :game_identifier,
                         freespins_per_player = :freespins,
                         bet_level = :bet_level,
                         begins_at = :begins_at,
                         expires_at = :expires_at,
                         active = 1,
                         status = \'active\',
                         source = \'affiliate\',
                         payload = :payload
                     WHERE id = :id'
                );
                $stmt->execute([
                    'title' => $rule['title'],
                    'game_identifier' => $rule['game_identifier'],
                    'freespins' => (int) $rule['freespins'],
                    'bet_level' => (int) $rule['bet_level'],
                    'begins_at' => $beginsAt,
                    'expires_at' => $expiresAt,
                    'payload' => json_encode([
                        'affiliate_auto' => true,
                        'referral_code' => $referralCode,
                        'notes' => 'Auto welcome freespin for affiliate registrations',
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'id' => $id,
                ]);
            }

            $fresh = BgamingService::campaignByCode($pdo, $rule['campaign_code']);

            return is_array($fresh) ? $fresh : $existing;
        }

        $currency = 'TRY';
        try {
            $cfg = BgamingService::config($pdo);
            if (is_array($cfg) && trim((string) ($cfg['currency'] ?? '')) !== '') {
                $currency = strtoupper(trim((string) $cfg['currency']));
            }
        } catch (Throwable) {
        }

        $stmt = $pdo->prepare(
            'INSERT INTO bgaming_campaigns
                (campaign_code, title, campaign_type, game_identifier, vendor, source, currency_code,
                 freespins_per_player, bet_level, promo_amount, wagering_multiplier, begins_at, expires_at,
                 active, status, payload)
             VALUES
                (:campaign_code, :title, \'freespin\', :game_identifier, \'bgaming\', \'affiliate\', :currency_code,
                 :freespins, :bet_level, 0, 0, :begins_at, :expires_at,
                 1, \'active\', :payload)'
        );
        $stmt->execute([
            'campaign_code' => $rule['campaign_code'],
            'title' => $rule['title'],
            'game_identifier' => $rule['game_identifier'],
            'currency_code' => $currency,
            'freespins' => (int) $rule['freespins'],
            'bet_level' => (int) $rule['bet_level'],
            'begins_at' => $beginsAt,
            'expires_at' => $expiresAt,
            'payload' => json_encode([
                'affiliate_auto' => true,
                'referral_code' => $referralCode,
                'notes' => 'Auto welcome freespin for affiliate registrations',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $created = BgamingService::campaignByCode($pdo, $rule['campaign_code']);
        if ($created === null) {
            throw new RuntimeException('Kampanya insert sonrası okunamadı.');
        }

        return $created;
    }

    private static function existingAssignmentStatus(PDO $pdo, string $campaignCode, int $userId): string
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT status FROM bgaming_campaign_players
                 WHERE campaign_code = :campaign_code AND user_id = :user_id
                 LIMIT 1'
            );
            $stmt->execute(['campaign_code' => $campaignCode, 'user_id' => $userId]);
            $status = strtolower(trim((string) $stmt->fetchColumn()));

            return $status;
        } catch (Throwable) {
            return '';
        }
    }
}
