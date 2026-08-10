<?php

declare(strict_types=1);

/**
 * Sadakat (loyalty) puan servisi.
 *
 * Earn : gerçek bakiyeden yapılan bahisler (1 TRY = 1 puan)
 * Redeem: 100 puan = 1 TRY bonus_balance + aktif bonus kaydı
 *
 * Best-effort: earn/reverse asla bahis/cüzdan işlemini bozmaz.
 */
final class LoyaltyService
{
    /** 1 TRY çevrim = N puan */
    public const POINTS_PER_TRY = 1.0;

    /** 100 puan = 1 TRY bonus */
    public const REDEEM_POINTS_PER_TRY = 100;

    public const MIN_REDEEM_POINTS = 100;

    /** Redeem edilen bonus için çevrim çarpanı */
    public const REDEEM_WAGERING_MULTIPLIER = 1.0;

    /** Cashback / haftalık bonus çevrim çarpanı */
    public const CASHBACK_WAGERING_MULTIPLIER = 1.0;

    /** Minimum otomatik cashback ödemesi (TRY) */
    public const MIN_CASHBACK_PAYOUT = 1.0;

    public static function requireLoaded(): void
    {
        // no-op helper for call sites that want an explicit load marker
    }

    public static function ensureStorage(PDO $pdo): void
    {
        if (!class_exists('ApiLoyalty', false)) {
            foreach ([
                dirname(__DIR__) . '/api/Loyalty.php',
                shared_project_root() . '/admin/api/Loyalty.php',
            ] as $path) {
                if (is_file($path)) {
                    require_once $path;
                    break;
                }
            }
        }
        if (class_exists('ApiLoyalty', false)) {
            ApiLoyalty::ensureStorage($pdo);
        }
        self::ensureCashbackStorage($pdo);
    }

    public static function ensureCashbackStorage(PDO $pdo): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table
                 LIMIT 1'
            );
            $stmt->execute(['table' => 'loyalty_cashback_payments']);
            if ($stmt->fetchColumn() === false) {
                $pdo->exec(
                    "CREATE TABLE IF NOT EXISTS loyalty_cashback_payments (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        user_id INT NOT NULL,
                        username VARCHAR(120) NULL,
                        kind ENUM('cashback','weekly_bonus') NOT NULL DEFAULT 'cashback',
                        level_code VARCHAR(40) NOT NULL DEFAULT 'bronze',
                        cashback_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                        net_loss DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                        total_bets DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                        total_wins DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                        amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                        period_start DATE NOT NULL,
                        period_end DATE NOT NULL,
                        status VARCHAR(20) NOT NULL DEFAULT 'paid',
                        note VARCHAR(500) NULL,
                        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        UNIQUE KEY uniq_loyalty_cashback_period (user_id, period_start, period_end, kind),
                        KEY idx_loyalty_cashback_user (user_id, created_at),
                        KEY idx_loyalty_cashback_period (period_start, period_end)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
                );
            }
        } catch (Throwable $e) {
            error_log('[LoyaltyService] ensureCashbackStorage failed: ' . $e->getMessage());
        }

        $ready = true;
    }

    /**
     * Ana bakiyeden yapılan bahis için sadakat puanı kazandır.
     * Bonus cüzdan bahisleri puan üretmez.
     */
    public static function earnFromWager(PDO $pdo, int $userId, float $betAmount, ?string $walletSource = null, ?string $source = null, ?string $referenceId = null): void
    {
        if ($userId <= 0 || $betAmount <= 0) {
            return;
        }
        if (self::isBonusWallet($walletSource)) {
            return;
        }

        $points = (int) floor($betAmount * self::POINTS_PER_TRY);
        if ($points <= 0) {
            return;
        }

        try {
            self::ensureStorage($pdo);
            self::ensureAccount($pdo, $userId);

            $sourceKey = $source !== null && $source !== '' ? substr($source, 0, 120) : 'wager';
            $ref = $referenceId !== null && $referenceId !== '' ? substr($referenceId, 0, 120) : null;

            if ($ref !== null) {
                $dup = $pdo->prepare(
                    "SELECT id FROM loyalty_point_transactions
                     WHERE user_id = :uid AND type = 'earn' AND source = :src AND reference_id = :ref
                     LIMIT 1"
                );
                $dup->execute(['uid' => $userId, 'src' => $sourceKey, 'ref' => $ref]);
                if ($dup->fetchColumn() !== false) {
                    return;
                }
            }

            $pdo->prepare(
                "UPDATE user_loyalty_accounts
                 SET points = points + :pts,
                     lifetime_points = lifetime_points + :pts_life,
                     redeemable_points = redeemable_points + :pts_redeem,
                     last_activity_at = NOW()
                 WHERE user_id = :uid"
            )->execute([
                'pts' => $points,
                'pts_life' => $points,
                'pts_redeem' => $points,
                'uid' => $userId,
            ]);

            $uname = '';
            try {
                $u = $pdo->prepare('SELECT username FROM users WHERE id = :uid LIMIT 1');
                $u->execute(['uid' => $userId]);
                $uname = (string) ($u->fetchColumn() ?: '');
            } catch (Throwable) {
            }

            $pdo->prepare(
                "INSERT INTO loyalty_point_transactions
                    (user_id, username, type, points, source, reference_id, note, created_at)
                 VALUES
                    (:uid, :uname, 'earn', :pts, :src, :ref, :note, NOW())"
            )->execute([
                'uid' => $userId,
                'uname' => $uname !== '' ? $uname : null,
                'pts' => $points,
                'src' => $sourceKey,
                'ref' => $ref,
                'note' => sprintf('Bahis çevrimi: %.2f TRY → %d puan', $betAmount, $points),
            ]);

            self::syncLevelCode($pdo, $userId);
        } catch (Throwable $e) {
            error_log('[LoyaltyService] earnFromWager failed: ' . $e->getMessage());
        }
    }

    /**
     * Bahis iptali / rollback sonrası puan geri alımı.
     */
    public static function reverseFromWager(PDO $pdo, int $userId, float $betAmount, ?string $walletSource = null, ?string $source = null, ?string $referenceId = null): void
    {
        if ($userId <= 0 || $betAmount <= 0) {
            return;
        }
        if (self::isBonusWallet($walletSource)) {
            return;
        }

        $points = (int) floor($betAmount * self::POINTS_PER_TRY);
        if ($points <= 0) {
            return;
        }

        try {
            self::ensureStorage($pdo);
            self::ensureAccount($pdo, $userId);

            $sourceKey = $source !== null && $source !== '' ? substr($source, 0, 120) : 'wager_reverse';
            $ref = $referenceId !== null && $referenceId !== '' ? substr($referenceId, 0, 120) : null;

            $pdo->prepare(
                "UPDATE user_loyalty_accounts
                 SET points = GREATEST(0, points - :pts),
                     redeemable_points = GREATEST(0, redeemable_points - :pts_redeem),
                     last_activity_at = NOW()
                 WHERE user_id = :uid"
            )->execute(['pts' => $points, 'pts_redeem' => $points, 'uid' => $userId]);

            $uname = '';
            try {
                $u = $pdo->prepare('SELECT username FROM users WHERE id = :uid LIMIT 1');
                $u->execute(['uid' => $userId]);
                $uname = (string) ($u->fetchColumn() ?: '');
            } catch (Throwable) {
            }

            $pdo->prepare(
                "INSERT INTO loyalty_point_transactions
                    (user_id, username, type, points, source, reference_id, note, created_at)
                 VALUES
                    (:uid, :uname, 'adjust', :pts, :src, :ref, :note, NOW())"
            )->execute([
                'uid' => $userId,
                'uname' => $uname !== '' ? $uname : null,
                'pts' => -$points,
                'src' => $sourceKey,
                'ref' => $ref,
                'note' => sprintf('Bahis iptali: %.2f TRY → -%d puan', $betAmount, $points),
            ]);

            self::syncLevelCode($pdo, $userId);
        } catch (Throwable $e) {
            error_log('[LoyaltyService] reverseFromWager failed: ' . $e->getMessage());
        }
    }

    /**
     * Kullanılabilir puanı bonus bakiyesine çevir.
     *
     * @return array{points_used:int,bonus_amount:float,redeemable_points:int}
     */
    public static function redeem(PDO $pdo, int $userId, int $points): array
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Geçerli kullanıcı bulunamadı.');
        }
        if ($points < self::MIN_REDEEM_POINTS) {
            throw new InvalidArgumentException('En az ' . self::MIN_REDEEM_POINTS . ' puan kullanabilirsiniz.');
        }
        if ($points % self::REDEEM_POINTS_PER_TRY !== 0) {
            throw new InvalidArgumentException('Puan miktarı ' . self::REDEEM_POINTS_PER_TRY . ' ve katları olmalıdır.');
        }

        self::ensureStorage($pdo);
        self::ensureAccount($pdo, $userId);

        $bonusAmount = round($points / self::REDEEM_POINTS_PER_TRY, 2);
        $wageringTarget = round($bonusAmount * self::REDEEM_WAGERING_MULTIPLIER, 2);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT redeemable_points, username FROM user_loyalty_accounts WHERE user_id = :uid LIMIT 1 FOR UPDATE'
            );
            $stmt->execute(['uid' => $userId]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($account)) {
                throw new RuntimeException('Sadakat hesabı bulunamadı.');
            }
            $available = (int) ($account['redeemable_points'] ?? 0);
            if ($available < $points) {
                throw new RuntimeException('Yetersiz puan. Mevcut: ' . $available);
            }

            $userLock = $pdo->prepare('SELECT id, bonus_balance FROM users WHERE id = :uid LIMIT 1 FOR UPDATE');
            $userLock->execute(['uid' => $userId]);
            if (!$userLock->fetch(PDO::FETCH_ASSOC)) {
                throw new RuntimeException('Kullanıcı bulunamadı.');
            }

            $pdo->prepare(
                "UPDATE user_loyalty_accounts
                 SET redeemable_points = redeemable_points - :pts,
                     last_activity_at = NOW()
                 WHERE user_id = :uid AND redeemable_points >= :pts_min"
            )->execute(['pts' => $points, 'pts_min' => $points, 'uid' => $userId]);

            $pdo->prepare(
                "INSERT INTO loyalty_point_transactions
                    (user_id, username, type, points, source, reference_id, note, created_at)
                 VALUES
                    (:uid, :uname, 'redeem', :pts, 'redeem', NULL, :note, NOW())"
            )->execute([
                'uid' => $userId,
                'uname' => (string) ($account['username'] ?? ''),
                'pts' => -$points,
                'note' => sprintf('%d puan kullanıldı (%.2f TRY bonus)', $points, $bonusAmount),
            ]);

            $pdo->prepare(
                'UPDATE users SET bonus_balance = bonus_balance + :amount WHERE id = :uid'
            )->execute([
                'amount' => number_format($bonusAmount, 2, '.', ''),
                'uid' => $userId,
            ]);

            $pdo->prepare(
                "INSERT INTO user_active_bonuses
                    (user_id, name, category, initial_amount, current_bonus_balance,
                     wagering_requirement, wagering_target, total_bet_amount, status, granted_at, deadline)
                 VALUES
                    (:uid, 'Sadakat Bonusu', 'loyalty', :amt, :amt2,
                     :wreq, :wtarget, 0, 'active', NOW(), :dl)"
            )->execute([
                'uid' => $userId,
                'amt' => number_format($bonusAmount, 2, '.', ''),
                'amt2' => number_format($bonusAmount, 2, '.', ''),
                'wreq' => number_format(self::REDEEM_WAGERING_MULTIPLIER, 2, '.', ''),
                'wtarget' => number_format($wageringTarget, 2, '.', ''),
                'dl' => date('Y-m-d H:i:s', strtotime('+7 days')),
            ]);

            $left = $pdo->prepare('SELECT redeemable_points FROM user_loyalty_accounts WHERE user_id = :uid LIMIT 1');
            $left->execute(['uid' => $userId]);
            $remaining = (int) $left->fetchColumn();

            $pdo->commit();

            return [
                'points_used' => $points,
                'bonus_amount' => $bonusAmount,
                'redeemable_points' => $remaining,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private static function isBonusWallet(?string $walletSource): bool
    {
        if ($walletSource === null || $walletSource === '') {
            return false;
        }
        $w = strtolower(trim($walletSource));

        return $w === 'bonus' || $w === 'bonus_balance';
    }

    private static function ensureAccount(PDO $pdo, int $userId): void
    {
        $stmt = $pdo->prepare('SELECT id FROM user_loyalty_accounts WHERE user_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        if ($stmt->fetchColumn() !== false) {
            return;
        }

        $uname = '';
        try {
            $u = $pdo->prepare('SELECT username FROM users WHERE id = :uid LIMIT 1');
            $u->execute(['uid' => $userId]);
            $uname = (string) ($u->fetchColumn() ?: '');
        } catch (Throwable) {
        }

        $pdo->prepare(
            "INSERT INTO user_loyalty_accounts
                (user_id, username, level_code, points, lifetime_points, redeemable_points, last_activity_at)
             VALUES
                (:uid, :uname, 'bronze', 0, 0, 0, NULL)"
        )->execute([
            'uid' => $userId,
            'uname' => $uname !== '' ? $uname : null,
        ]);
    }

    private static function syncLevelCode(PDO $pdo, int $userId): void
    {
        $stmt = $pdo->prepare('SELECT points FROM user_loyalty_accounts WHERE user_id = :uid LIMIT 1');
        $stmt->execute(['uid' => $userId]);
        $points = (int) $stmt->fetchColumn();

        $levels = $pdo->query(
            'SELECT code, min_points FROM loyalty_levels WHERE is_active = 1 ORDER BY min_points ASC, sort_order ASC, id ASC'
        );
        $code = 'bronze';
        if ($levels !== false) {
            foreach ($levels->fetchAll(PDO::FETCH_ASSOC) as $level) {
                if ($points >= (int) ($level['min_points'] ?? 0)) {
                    $code = (string) ($level['code'] ?? 'bronze');
                }
            }
        }

        $pdo->prepare(
            'UPDATE user_loyalty_accounts SET level_code = :code WHERE user_id = :uid'
        )->execute(['code' => $code, 'uid' => $userId]);
    }

    /**
     * Önceki takvim haftası (Pzt 00:00 → Paz ertesi 00:00).
     *
     * @return array{start:string,end:string,start_dt:string,end_dt:string}
     */
    public static function previousWeekPeriod(?DateTimeInterface $now = null): array
    {
        $tz = new DateTimeZone(date_default_timezone_get() ?: 'Europe/Istanbul');
        $ref = $now instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($now)->setTimezone($tz)
            : new DateTimeImmutable('now', $tz);

        // Monday of current week, then go back 7 days.
        $thisMonday = $ref->modify('monday this week')->setTime(0, 0, 0);
        $start = $thisMonday->modify('-7 days');
        $end = $thisMonday;

        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->modify('-1 day')->format('Y-m-d'),
            'start_dt' => $start->format('Y-m-d H:i:s'),
            'end_dt' => $end->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Kullanıcının dönem net kaybı (gerçek bakiye bahis − kazanç).
     *
     * @return array{bets:float,wins:float,net_loss:float}
     */
    public static function computeUserNetLoss(PDO $pdo, int $userId, string $fromDt, string $toDt): array
    {
        $bets = 0.0;
        $wins = 0.0;

        // Casino Aggregator: bet amounts negative, wins positive.
        try {
            $st = $pdo->prepare(
                "SELECT
                    COALESCE(SUM(CASE WHEN txn_type = 'bet' THEN ABS(amount) ELSE 0 END), 0) AS bets,
                    COALESCE(SUM(CASE WHEN txn_type = 'win' THEN ABS(amount) ELSE 0 END), 0) AS wins
                 FROM casino_aggregator_transactions
                 WHERE user_id = :uid
                   AND created_at >= :from_dt AND created_at < :to_dt
                   AND txn_type IN ('bet','win')"
            );
            $st->execute(['uid' => $userId, 'from_dt' => $fromDt, 'to_dt' => $toDt]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $bets += (float) ($row['bets'] ?? 0);
            $wins += (float) ($row['wins'] ?? 0);
        } catch (Throwable) {
        }

        // BGaming: bet/win amounts stored as positive; exclude bonus wallet.
        try {
            $st = $pdo->prepare(
                "SELECT
                    COALESCE(SUM(CASE WHEN txn_type IN ('bet','promo_bet') THEN ABS(amount) ELSE 0 END), 0) AS bets,
                    COALESCE(SUM(CASE WHEN txn_type IN ('win','promo_win','freespins_win') THEN ABS(amount) ELSE 0 END), 0) AS wins
                 FROM bgaming_transactions
                 WHERE user_id = :uid
                   AND created_at >= :from_dt AND created_at < :to_dt
                   AND COALESCE(wallet_source, 'balance') NOT IN ('bonus','bonus_balance')"
            );
            $st->execute(['uid' => $userId, 'from_dt' => $fromDt, 'to_dt' => $toDt]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $bets += (float) ($row['bets'] ?? 0);
            $wins += (float) ($row['wins'] ?? 0);
        } catch (Throwable) {
        }

        // GSC+: BET debit / SETTLED-WIN credit.
        try {
            $st = $pdo->prepare(
                "SELECT
                    COALESCE(SUM(CASE
                        WHEN UPPER(action) IN ('BET','BET_PRESERVE')
                        THEN ABS(COALESCE(NULLIF(bet_amount, 0), amount, 0))
                        ELSE 0 END), 0) AS bets,
                    COALESCE(SUM(CASE
                        WHEN UPPER(action) IN ('SETTLED','WIN','BONUS','PRIZE')
                        THEN ABS(COALESCE(NULLIF(prize_amount, 0), amount, 0))
                        ELSE 0 END), 0) AS wins
                 FROM gsc_transactions
                 WHERE user_id = :uid
                   AND created_at >= :from_dt AND created_at < :to_dt"
            );
            $st->execute(['uid' => $userId, 'from_dt' => $fromDt, 'to_dt' => $toDt]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $bets += (float) ($row['bets'] ?? 0);
            $wins += (float) ($row['wins'] ?? 0);
        } catch (Throwable) {
        }

        // Sportsbook: signed deltas (bet negative).
        try {
            $st = $pdo->prepare(
                "SELECT
                    COALESCE(SUM(CASE WHEN txn_type = 'bet' THEN ABS(amount) ELSE 0 END), 0) AS bets,
                    COALESCE(SUM(CASE WHEN txn_type = 'win' THEN ABS(amount) ELSE 0 END), 0) AS wins
                 FROM sportsbook_transactions
                 WHERE user_id = :uid
                   AND created_at >= :from_dt AND created_at < :to_dt
                   AND txn_type IN ('bet','win')"
            );
            $st->execute(['uid' => $userId, 'from_dt' => $fromDt, 'to_dt' => $toDt]);
            $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $bets += (float) ($row['bets'] ?? 0);
            $wins += (float) ($row['wins'] ?? 0);
        } catch (Throwable) {
        }

        $bets = round($bets, 2);
        $wins = round($wins, 2);

        return [
            'bets' => $bets,
            'wins' => $wins,
            'net_loss' => round(max(0.0, $bets - $wins), 2),
        ];
    }

    /**
     * Haftalık cashback + seviye haftalık bonus ödemelerini çalıştır.
     *
     * @return array{
     *   period_start:string,
     *   period_end:string,
     *   scanned:int,
     *   cashback_paid:int,
     *   weekly_paid:int,
     *   skipped:int,
     *   total_amount:float,
     *   errors:list<string>
     * }
     */
    public static function processWeeklyPayouts(PDO $pdo, ?array $period = null): array
    {
        self::ensureStorage($pdo);
        $period = $period ?? self::previousWeekPeriod();
        $start = (string) ($period['start'] ?? '');
        $end = (string) ($period['end'] ?? '');
        $fromDt = (string) ($period['start_dt'] ?? ($start . ' 00:00:00'));
        $toDt = (string) ($period['end_dt'] ?? (date('Y-m-d', strtotime($end . ' +1 day')) . ' 00:00:00'));

        $summary = [
            'period_start' => $start,
            'period_end' => $end,
            'scanned' => 0,
            'cashback_paid' => 0,
            'weekly_paid' => 0,
            'skipped' => 0,
            'total_amount' => 0.0,
            'errors' => [],
        ];

        $levels = [];
        $lvlStmt = $pdo->query(
            'SELECT code, name, cashback_rate, weekly_bonus_amount
             FROM loyalty_levels
             WHERE is_active = 1'
        );
        if ($lvlStmt !== false) {
            foreach ($lvlStmt->fetchAll(PDO::FETCH_ASSOC) as $lvl) {
                $levels[(string) ($lvl['code'] ?? '')] = $lvl;
            }
        }

        $users = $pdo->query(
            'SELECT a.user_id, a.username, a.level_code, a.points
             FROM user_loyalty_accounts a
             INNER JOIN users u ON u.id = a.user_id
             WHERE COALESCE(u.banned, 0) = 0'
        );
        if ($users === false) {
            return $summary;
        }

        foreach ($users->fetchAll(PDO::FETCH_ASSOC) as $account) {
            $summary['scanned']++;
            $userId = (int) ($account['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $levelCode = (string) ($account['level_code'] ?? 'bronze');
            $level = $levels[$levelCode] ?? null;
            if (!is_array($level)) {
                // Re-derive from points if level row missing.
                $level = ['code' => $levelCode, 'cashback_rate' => 0, 'weekly_bonus_amount' => 0];
                foreach ($levels as $candidate) {
                    if ((int) ($account['points'] ?? 0) >= 0 && (string) ($candidate['code'] ?? '') === $levelCode) {
                        $level = $candidate;
                        break;
                    }
                }
            }

            $rate = (float) ($level['cashback_rate'] ?? 0);
            $weeklyBonus = (float) ($level['weekly_bonus_amount'] ?? 0);
            $activity = self::computeUserNetLoss($pdo, $userId, $fromDt, $toDt);

            try {
                if ($rate > 0 && $activity['net_loss'] > 0) {
                    $amount = round($activity['net_loss'] * ($rate / 100), 2);
                    if ($amount >= self::MIN_CASHBACK_PAYOUT) {
                        $paid = self::payPeriodBonus($pdo, [
                            'user_id' => $userId,
                            'username' => (string) ($account['username'] ?? ''),
                            'kind' => 'cashback',
                            'level_code' => $levelCode,
                            'cashback_rate' => $rate,
                            'net_loss' => $activity['net_loss'],
                            'total_bets' => $activity['bets'],
                            'total_wins' => $activity['wins'],
                            'amount' => $amount,
                            'period_start' => $start,
                            'period_end' => $end,
                            'bonus_name' => 'Sadakat Cashback',
                            'note' => sprintf(
                                'Haftalık cashback %s%% — net kayıp %.2f TRY',
                                number_format($rate, 2, '.', ''),
                                $activity['net_loss']
                            ),
                        ]);
                        if ($paid) {
                            $summary['cashback_paid']++;
                            $summary['total_amount'] = round($summary['total_amount'] + $amount, 2);
                        } else {
                            $summary['skipped']++;
                        }
                    } else {
                        $summary['skipped']++;
                    }
                }

                // Fixed weekly level bonus: only if member had wagering activity.
                if ($weeklyBonus >= self::MIN_CASHBACK_PAYOUT && $activity['bets'] > 0) {
                    $paid = self::payPeriodBonus($pdo, [
                        'user_id' => $userId,
                        'username' => (string) ($account['username'] ?? ''),
                        'kind' => 'weekly_bonus',
                        'level_code' => $levelCode,
                        'cashback_rate' => $rate,
                        'net_loss' => $activity['net_loss'],
                        'total_bets' => $activity['bets'],
                        'total_wins' => $activity['wins'],
                        'amount' => round($weeklyBonus, 2),
                        'period_start' => $start,
                        'period_end' => $end,
                        'bonus_name' => 'Sadakat Haftalık Bonus',
                        'note' => sprintf(
                            'Haftalık seviye bonusu (%s): %.2f TRY',
                            $levelCode,
                            $weeklyBonus
                        ),
                    ]);
                    if ($paid) {
                        $summary['weekly_paid']++;
                        $summary['total_amount'] = round($summary['total_amount'] + $weeklyBonus, 2);
                    } else {
                        $summary['skipped']++;
                    }
                }
            } catch (Throwable $e) {
                $summary['errors'][] = 'user#' . $userId . ': ' . $e->getMessage();
            }
        }

        return $summary;
    }

    /**
     * @param array{
     *   user_id:int,
     *   username?:string,
     *   kind:string,
     *   level_code:string,
     *   cashback_rate:float,
     *   net_loss:float,
     *   total_bets:float,
     *   total_wins:float,
     *   amount:float,
     *   period_start:string,
     *   period_end:string,
     *   bonus_name:string,
     *   note:string
     * } $payload
     */
    private static function payPeriodBonus(PDO $pdo, array $payload): bool
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        $amount = round((float) ($payload['amount'] ?? 0), 2);
        $kind = (string) ($payload['kind'] ?? 'cashback');
        $periodStart = (string) ($payload['period_start'] ?? '');
        $periodEnd = (string) ($payload['period_end'] ?? '');
        if ($userId <= 0 || $amount < self::MIN_CASHBACK_PAYOUT || $periodStart === '' || $periodEnd === '') {
            return false;
        }

        // Idempotent: already paid for this period/kind.
        $exists = $pdo->prepare(
            'SELECT id FROM loyalty_cashback_payments
             WHERE user_id = :uid AND period_start = :ps AND period_end = :pe AND kind = :kind
             LIMIT 1'
        );
        $exists->execute([
            'uid' => $userId,
            'ps' => $periodStart,
            'pe' => $periodEnd,
            'kind' => $kind,
        ]);
        if ($exists->fetchColumn() !== false) {
            return false;
        }

        $wageringTarget = round($amount * self::CASHBACK_WAGERING_MULTIPLIER, 2);
        $pdo->beginTransaction();
        try {
            $userLock = $pdo->prepare('SELECT id, username, bonus_balance FROM users WHERE id = :uid LIMIT 1 FOR UPDATE');
            $userLock->execute(['uid' => $userId]);
            $user = $userLock->fetch(PDO::FETCH_ASSOC);
            if (!is_array($user)) {
                $pdo->rollBack();
                return false;
            }

            $username = trim((string) ($payload['username'] ?? '')) !== ''
                ? (string) $payload['username']
                : (string) ($user['username'] ?? '');

            $ins = $pdo->prepare(
                "INSERT INTO loyalty_cashback_payments
                    (user_id, username, kind, level_code, cashback_rate, net_loss, total_bets, total_wins,
                     amount, period_start, period_end, status, note, created_at)
                 VALUES
                    (:uid, :uname, :kind, :level, :rate, :net, :bets, :wins,
                     :amount, :ps, :pe, 'paid', :note, NOW())"
            );
            $ins->execute([
                'uid' => $userId,
                'uname' => $username !== '' ? $username : null,
                'kind' => $kind,
                'level' => (string) ($payload['level_code'] ?? 'bronze'),
                'rate' => number_format((float) ($payload['cashback_rate'] ?? 0), 2, '.', ''),
                'net' => number_format((float) ($payload['net_loss'] ?? 0), 2, '.', ''),
                'bets' => number_format((float) ($payload['total_bets'] ?? 0), 2, '.', ''),
                'wins' => number_format((float) ($payload['total_wins'] ?? 0), 2, '.', ''),
                'amount' => number_format($amount, 2, '.', ''),
                'ps' => $periodStart,
                'pe' => $periodEnd,
                'note' => (string) ($payload['note'] ?? ''),
            ]);

            $pdo->prepare(
                'UPDATE users SET bonus_balance = bonus_balance + :amount WHERE id = :uid'
            )->execute([
                'amount' => number_format($amount, 2, '.', ''),
                'uid' => $userId,
            ]);

            $pdo->prepare(
                "INSERT INTO user_active_bonuses
                    (user_id, name, category, initial_amount, current_bonus_balance,
                     wagering_requirement, wagering_target, total_bet_amount, status, granted_at, deadline)
                 VALUES
                    (:uid, :name, 'loyalty_cashback', :amt, :amt2,
                     :wreq, :wtarget, 0, 'active', NOW(), :dl)"
            )->execute([
                'uid' => $userId,
                'name' => (string) ($payload['bonus_name'] ?? 'Sadakat Cashback'),
                'amt' => number_format($amount, 2, '.', ''),
                'amt2' => number_format($amount, 2, '.', ''),
                'wreq' => number_format(self::CASHBACK_WAGERING_MULTIPLIER, 2, '.', ''),
                'wtarget' => number_format($wageringTarget, 2, '.', ''),
                'dl' => date('Y-m-d H:i:s', strtotime('+7 days')),
            ]);

            $pdo->prepare(
                "INSERT INTO loyalty_point_transactions
                    (user_id, username, type, points, source, reference_id, note, created_at)
                 VALUES
                    (:uid, :uname, 'adjust', 0, :src, :ref, :note, NOW())"
            )->execute([
                'uid' => $userId,
                'uname' => $username !== '' ? $username : null,
                'src' => $kind,
                'ref' => $periodStart . '_' . $periodEnd . '_' . $kind,
                'note' => (string) ($payload['note'] ?? '') . sprintf(' → +%.2f TRY bonus', $amount),
            ]);

            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Duplicate unique key = already paid.
            if (str_contains($e->getMessage(), '1062') || stripos($e->getMessage(), 'Duplicate') !== false) {
                return false;
            }
            throw $e;
        }
    }
}
