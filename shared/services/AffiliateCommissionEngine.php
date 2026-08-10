<?php

declare(strict_types=1);

/**
 * Affiliate commission engine.
 *
 * Durable rules:
 * - RevShare: period net cashflow (confirmed deposits − withdrawals) × rate
 * - CPA: paid once on first qualifying deposit (FTD), not on registration day
 * - Hybrid: both legs, independently idempotent
 * - Plan-less active affiliates auto-get the default plan
 * - Aggregate RevShare rows use nullable user_id (schema hardened here)
 */
final class AffiliateCommissionEngine
{
    private const PAID_STATUSES = ['confirmed', 'approved', 'success', 'completed'];

    public static function paidStatusSql(): string
    {
        return "('" . implode("','", self::PAID_STATUSES) . "')";
    }

    public static function ensureSchema(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        try {
            // Aggregate RevShare must allow NULL user_id (legacy cron crashed on NOT NULL + FK).
            $col = $pdo->query(
                "SELECT IS_NULLABLE, COLUMN_TYPE
                 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'affiliate_commissions'
                   AND COLUMN_NAME = 'user_id'
                 LIMIT 1"
            )->fetch(PDO::FETCH_ASSOC);
            if (is_array($col) && strtoupper((string) ($col['IS_NULLABLE'] ?? '')) === 'NO') {
                try {
                    $pdo->exec('ALTER TABLE affiliate_commissions DROP FOREIGN KEY fk_aff_comm_user');
                } catch (Throwable) {
                }
                $pdo->exec('ALTER TABLE affiliate_commissions MODIFY user_id INT NULL COMMENT \'Yönlendirilen oyuncu (RevShare aggregate için NULL)\'');
                try {
                    $pdo->exec(
                        'ALTER TABLE affiliate_commissions
                         ADD CONSTRAINT fk_aff_comm_user
                         FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL'
                    );
                } catch (Throwable) {
                }
            }
        } catch (Throwable $e) {
            error_log('[AffiliateCommissionEngine] ensureSchema user_id: ' . $e->getMessage());
        }

        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS affiliate_period_runs (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    affiliate_id INT UNSIGNED NOT NULL,
                    period_start DATE NOT NULL,
                    period_end DATE NOT NULL,
                    plan_id INT UNSIGNED NULL,
                    plan_snapshot JSON NULL,
                    revshare_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    cpa_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
                    cpa_count INT NOT NULL DEFAULT 0,
                    status VARCHAR(20) NOT NULL DEFAULT \'completed\',
                    message VARCHAR(500) NOT NULL DEFAULT \'\',
                    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uniq_aff_period_run (affiliate_id, period_start, period_end),
                    KEY idx_aff_period_run_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (Throwable $e) {
            error_log('[AffiliateCommissionEngine] ensureSchema period_runs: ' . $e->getMessage());
        }

        try {
            $idx = $pdo->query("SHOW INDEX FROM affiliate_commissions WHERE Key_name = 'uniq_aff_cpa_user'")->fetch();
            if ($idx === false) {
                $pdo->exec(
                    'ALTER TABLE affiliate_commissions
                     ADD UNIQUE KEY uniq_aff_cpa_user (affiliate_id, user_id, commission_type, source)'
                );
            }
        } catch (Throwable) {
            // Duplicate historical rows may block unique index — engine still uses NOT EXISTS.
        }
    }

    public static function defaultPlanId(PDO $pdo): ?int
    {
        try {
            $id = $pdo->query(
                "SELECT id FROM affiliate_commission_plans
                 WHERE is_default = 1 AND is_active = 1
                 ORDER BY id ASC LIMIT 1"
            )->fetchColumn();
            if ($id !== false) {
                return (int) $id;
            }
            $id = $pdo->query(
                "SELECT id FROM affiliate_commission_plans
                 WHERE is_active = 1
                 ORDER BY id ASC LIMIT 1"
            )->fetchColumn();
            return $id !== false ? (int) $id : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function assignDefaultPlanIfMissing(PDO $pdo, int $affiliateId): bool
    {
        if ($affiliateId <= 0) {
            return false;
        }
        $stmt = $pdo->prepare('SELECT commission_plan_id FROM affiliates WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $affiliateId]);
        $current = $stmt->fetchColumn();
        if ($current !== false && $current !== null && (int) $current > 0) {
            return false;
        }
        $planId = self::defaultPlanId($pdo);
        if ($planId === null) {
            return false;
        }
        $upd = $pdo->prepare('UPDATE affiliates SET commission_plan_id = :plan WHERE id = :id AND (commission_plan_id IS NULL OR commission_plan_id = 0)');
        $upd->execute(['plan' => $planId, 'id' => $affiliateId]);
        return $upd->rowCount() > 0;
    }

    /**
     * @return array{
     *   period_start:string,
     *   period_end:string,
     *   affiliates:int,
     *   processed:int,
     *   total:float,
     *   log:list<string>
     * }
     */
    public static function processPeriod(
        PDO $pdo,
        string $periodStart,
        string $periodEnd,
        ?int $onlyAffiliateId = null,
        bool $force = false
    ): array {
        self::ensureSchema($pdo);

        $periodStart = self::normalizeDate($periodStart);
        $periodEnd = self::normalizeDate($periodEnd);
        if ($periodStart === '' || $periodEnd === '' || $periodStart >= $periodEnd) {
            throw new InvalidArgumentException('Geçersiz dönem: period_start < period_end olmalı (bitiş hariç).');
        }

        $log = ["Period: {$periodStart} → {$periodEnd}" . ($force ? ' (force)' : '')];
        $affiliates = self::loadAffiliates($pdo, $onlyAffiliateId);
        $log[] = 'Active affiliates with plan: ' . count($affiliates);

        $processed = 0;
        $total = 0.0;

        foreach ($affiliates as $aff) {
            $affId = (int) ($aff['id'] ?? 0);
            try {
                $result = self::processAffiliate($pdo, $aff, $periodStart, $periodEnd, $force);
                $processed += (int) ($result['processed'] ?? 0);
                $total += (float) ($result['total'] ?? 0);
                foreach ((array) ($result['log'] ?? []) as $line) {
                    $log[] = $line;
                }
            } catch (Throwable $e) {
                $log[] = "  Affiliate #{$affId} ERROR: " . $e->getMessage();
                error_log('[AffiliateCommissionEngine] affiliate #' . $affId . ': ' . $e->getMessage());
            }
        }

        return [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'affiliates' => count($affiliates),
            'processed' => $processed,
            'total' => round($total, 2),
            'log' => $log,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function loadAffiliates(PDO $pdo, ?int $onlyAffiliateId): array
    {
        // Auto-heal plan-less active affiliates before selection.
        $defaultId = self::defaultPlanId($pdo);
        if ($defaultId !== null) {
            if ($onlyAffiliateId !== null && $onlyAffiliateId > 0) {
                self::assignDefaultPlanIfMissing($pdo, $onlyAffiliateId);
            } else {
                $pdo->prepare(
                    "UPDATE affiliates
                     SET commission_plan_id = :plan
                     WHERE status = 'active'
                       AND (commission_plan_id IS NULL OR commission_plan_id = 0)"
                )->execute(['plan' => $defaultId]);
            }
        }

        $sql = "SELECT a.id, a.referral_code, a.commission_plan_id,
                       cp.id AS plan_id, cp.name AS plan_name, cp.plan_type,
                       cp.revshare_rate, cp.cpa_amount, cp.min_deposit
                FROM affiliates a
                INNER JOIN affiliate_commission_plans cp ON cp.id = a.commission_plan_id
                WHERE a.status = 'active' AND cp.is_active = 1";
        $params = [];
        if ($onlyAffiliateId !== null && $onlyAffiliateId > 0) {
            $sql .= ' AND a.id = :aid';
            $params['aid'] = $onlyAffiliateId;
        }
        $sql .= ' ORDER BY a.id ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string,mixed> $aff
     * @return array{processed:int,total:float,log:list<string>}
     */
    private static function processAffiliate(
        PDO $pdo,
        array $aff,
        string $periodStart,
        string $periodEnd,
        bool $force
    ): array {
        $affId = (int) $aff['id'];
        $planType = strtolower(trim((string) ($aff['plan_type'] ?? 'revshare')));
        $revshareRate = (float) ($aff['revshare_rate'] ?? 0);
        $cpaAmount = (float) ($aff['cpa_amount'] ?? 0);
        $minDeposit = (float) ($aff['min_deposit'] ?? 0);
        $planId = (int) ($aff['plan_id'] ?? $aff['commission_plan_id'] ?? 0);

        $log = [];
        $processed = 0;
        $total = 0.0;
        $revsharePaid = 0.0;
        $cpaPaid = 0.0;
        $cpaCount = 0;

        if ($force) {
            self::reverseUnpaidAutoCommissions($pdo, $affId, $periodStart, $periodEnd);
        }

        $snapshot = json_encode([
            'plan_id' => $planId,
            'plan_name' => (string) ($aff['plan_name'] ?? ''),
            'plan_type' => $planType,
            'revshare_rate' => $revshareRate,
            'cpa_amount' => $cpaAmount,
            'min_deposit' => $minDeposit,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (in_array($planType, ['revshare', 'hybrid'], true) && $revshareRate > 0) {
            $rs = self::processRevShare($pdo, $affId, $revshareRate, $periodStart, $periodEnd, $force);
            $processed += (int) ($rs['processed'] ?? 0);
            $total += (float) ($rs['amount'] ?? 0);
            $revsharePaid = (float) ($rs['amount'] ?? 0);
            if (($rs['message'] ?? '') !== '') {
                $log[] = '  Affiliate #' . $affId . ' ' . $rs['message'];
            }
        }

        if (in_array($planType, ['cpa', 'hybrid'], true) && $cpaAmount > 0) {
            $cpa = self::processCpa($pdo, $affId, $cpaAmount, $minDeposit, $periodStart, $periodEnd);
            $processed += (int) ($cpa['processed'] ?? 0);
            $total += (float) ($cpa['amount'] ?? 0);
            $cpaPaid = (float) ($cpa['amount'] ?? 0);
            $cpaCount = (int) ($cpa['count'] ?? 0);
            foreach ((array) ($cpa['log'] ?? []) as $line) {
                $log[] = $line;
            }
        }

        self::upsertPeriodRun($pdo, $affId, $periodStart, $periodEnd, $planId, $snapshot ?: '{}', $revsharePaid, $cpaPaid, $cpaCount);

        return ['processed' => $processed, 'total' => round($total, 2), 'log' => $log];
    }

    /**
     * @return array{processed:int,amount:float,message:string}
     */
    private static function processRevShare(
        PDO $pdo,
        int $affId,
        float $revshareRate,
        string $periodStart,
        string $periodEnd,
        bool $force
    ): array {
        if (!$force && self::hasCommission($pdo, $affId, $periodStart, $periodEnd, 'game_bet', 'revshare')) {
            return ['processed' => 0, 'amount' => 0.0, 'message' => 'RevShare already posted for period, skip.'];
        }

        $paid = self::paidStatusSql();
        $stmt = $pdo->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN t.type = 'deposit' THEN t.amount ELSE 0 END), 0) AS deposits,
                COALESCE(SUM(CASE WHEN t.type = 'withdraw' THEN t.amount ELSE 0 END), 0) AS withdrawals
             FROM megapayz_transactions t
             INNER JOIN users u ON u.id = t.user_id
             WHERE u.referred_by_affiliate_id = :aid
               AND t.status IN {$paid}
               AND t.type IN ('deposit','withdraw')
               AND t.created_at >= :ps AND t.created_at < :pe"
        );
        $stmt->execute([
            'aid' => $affId,
            'ps' => $periodStart . ' 00:00:00',
            'pe' => $periodEnd . ' 00:00:00',
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $deposits = (float) ($row['deposits'] ?? 0);
        $withdrawals = (float) ($row['withdrawals'] ?? 0);
        $net = round($deposits - $withdrawals, 2);

        if ($net <= 0) {
            return [
                'processed' => 0,
                'amount' => 0.0,
                'message' => sprintf('RevShare net <= 0 (dep %.2f, wd %.2f).', $deposits, $withdrawals),
            ];
        }

        $amount = round($net * ($revshareRate / 100), 2);
        if ($amount <= 0) {
            return ['processed' => 0, 'amount' => 0.0, 'message' => 'RevShare amount rounded to 0.'];
        }

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                "INSERT INTO affiliate_commissions
                    (affiliate_id, user_id, commission_type, amount, status, description, source, period_start, period_end, approved_at)
                 VALUES
                    (:aid, NULL, 'revshare', :amount, 'approved', :description, 'game_bet', :ps, :pe, NOW())"
            );
            $ins->execute([
                'aid' => $affId,
                'amount' => number_format($amount, 2, '.', ''),
                'description' => sprintf('RevShare %%%s — Net: %s ₺ (Yatırım %s − Çekim %s)',
                    rtrim(rtrim(number_format($revshareRate, 2, '.', ''), '0'), '.'),
                    number_format($net, 2, '.', ''),
                    number_format($deposits, 2, '.', ''),
                    number_format($withdrawals, 2, '.', '')
                ),
                'ps' => $periodStart,
                'pe' => $periodEnd,
            ]);
            self::creditAffiliate($pdo, $affId, $amount);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'processed' => 1,
            'amount' => $amount,
            'message' => sprintf('RevShare: %s ₺ (Net %s).', number_format($amount, 2, '.', ''), number_format($net, 2, '.', '')),
        ];
    }

    /**
     * CPA on first qualifying deposit time (FTD), independent of registration calendar day.
     *
     * @return array{processed:int,amount:float,count:int,log:list<string>}
     */
    private static function processCpa(
        PDO $pdo,
        int $affId,
        float $cpaAmount,
        float $minDeposit,
        string $periodStart,
        string $periodEnd
    ): array {
        $paid = self::paidStatusSql();
        // First confirmed deposit per referred user; qualify when FTD falls in period
        // and FTD amount meets min_deposit. Lifetime CPA: skip if any non-cancelled CPA exists.
        $stmt = $pdo->prepare(
            "SELECT u.id, u.username, ftd.first_at, ftd.first_amount
             FROM users u
             INNER JOIN (
                SELECT t.user_id,
                       MIN(t.created_at) AS first_at,
                       SUBSTRING_INDEX(GROUP_CONCAT(t.amount ORDER BY t.created_at ASC, t.id ASC), ',', 1) AS first_amount
                FROM megapayz_transactions t
                WHERE t.type = 'deposit'
                  AND t.status IN {$paid}
                GROUP BY t.user_id
             ) ftd ON ftd.user_id = u.id
             WHERE u.referred_by_affiliate_id = :aid
               AND ftd.first_at >= :ps AND ftd.first_at < :pe
               AND CAST(ftd.first_amount AS DECIMAL(15,2)) >= :min_deposit
               AND NOT EXISTS (
                    SELECT 1 FROM affiliate_commissions ac
                    WHERE ac.affiliate_id = :aid2
                      AND ac.user_id = u.id
                      AND ac.commission_type = 'cpa'
                      AND ac.source IN ('deposit', 'registration')
                      AND ac.status <> 'cancelled'
               )
             ORDER BY ftd.first_at ASC"
        );
        $stmt->execute([
            'aid' => $affId,
            'ps' => $periodStart . ' 00:00:00',
            'pe' => $periodEnd . ' 00:00:00',
            'min_deposit' => number_format(max(0, $minDeposit), 2, '.', ''),
            'aid2' => $affId,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $processed = 0;
        $amountTotal = 0.0;
        $log = [];

        foreach ($rows as $u) {
            $userId = (int) ($u['id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }
            $pdo->beginTransaction();
            try {
                $ins = $pdo->prepare(
                    "INSERT INTO affiliate_commissions
                        (affiliate_id, user_id, commission_type, amount, status, description, source, period_start, period_end, approved_at)
                     VALUES
                        (:aid, :uid, 'cpa', :amount, 'approved', :description, 'deposit', :ps, :pe, NOW())"
                );
                $firstAmount = (float) ($u['first_amount'] ?? 0);
                $ins->execute([
                    'aid' => $affId,
                    'uid' => $userId,
                    'amount' => number_format($cpaAmount, 2, '.', ''),
                    'description' => sprintf(
                        'CPA — %s FTD: %s ₺ (%s)',
                        (string) ($u['username'] ?? ('#' . $userId)),
                        number_format($firstAmount, 2, '.', ''),
                        (string) ($u['first_at'] ?? '')
                    ),
                    'ps' => $periodStart,
                    'pe' => $periodEnd,
                ]);
                self::creditAffiliate($pdo, $affId, $cpaAmount);
                $pdo->commit();
                $processed++;
                $amountTotal += $cpaAmount;
                $log[] = sprintf('  Affiliate #%d CPA: %s ₺ for %s', $affId, number_format($cpaAmount, 2, '.', ''), (string) ($u['username'] ?? $userId));
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                // Unique race / duplicate: skip quietly
                if (stripos($e->getMessage(), 'Duplicate') !== false) {
                    continue;
                }
                throw $e;
            }
        }

        return [
            'processed' => $processed,
            'amount' => round($amountTotal, 2),
            'count' => $processed,
            'log' => $log,
        ];
    }

    private static function creditAffiliate(PDO $pdo, int $affId, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }
        $pdo->prepare(
            'UPDATE affiliates
             SET balance = balance + :amount,
                 total_earned = total_earned + :amount2
             WHERE id = :id'
        )->execute([
            'amount' => number_format($amount, 2, '.', ''),
            'amount2' => number_format($amount, 2, '.', ''),
            'id' => $affId,
        ]);
    }

    private static function hasCommission(
        PDO $pdo,
        int $affId,
        string $periodStart,
        string $periodEnd,
        string $source,
        string $type
    ): bool {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM affiliate_commissions
             WHERE affiliate_id = :aid
               AND period_start = :ps AND period_end = :pe
               AND source = :source AND commission_type = :ctype
               AND status <> 'cancelled'
             LIMIT 1"
        );
        $stmt->execute([
            'aid' => $affId,
            'ps' => $periodStart,
            'pe' => $periodEnd,
            'source' => $source,
            'ctype' => $type,
        ]);
        return $stmt->fetchColumn() !== false;
    }

    private static function reverseUnpaidAutoCommissions(
        PDO $pdo,
        int $affId,
        string $periodStart,
        string $periodEnd
    ): void {
        $stmt = $pdo->prepare(
            "SELECT id, amount, status
             FROM affiliate_commissions
             WHERE affiliate_id = :aid
               AND period_start = :ps AND period_end = :pe
               AND source IN ('deposit', 'game_bet')
               AND commission_type IN ('revshare', 'cpa')
               AND status IN ('pending', 'approved')"
        );
        $stmt->execute(['aid' => $affId, 'ps' => $periodStart, 'pe' => $periodEnd]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $amount = (float) ($row['amount'] ?? 0);
            $id = (int) ($row['id'] ?? 0);
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
                $pdo->prepare("UPDATE affiliate_commissions SET status = 'cancelled', description = CONCAT(description, ' [recalc]') WHERE id = :id")
                    ->execute(['id' => $id]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
        }
    }

    private static function upsertPeriodRun(
        PDO $pdo,
        int $affId,
        string $periodStart,
        string $periodEnd,
        int $planId,
        string $snapshot,
        float $revshare,
        float $cpa,
        int $cpaCount
    ): void {
        try {
            $pdo->prepare(
                "INSERT INTO affiliate_period_runs
                    (affiliate_id, period_start, period_end, plan_id, plan_snapshot, revshare_amount, cpa_amount, cpa_count, status, message)
                 VALUES
                    (:aid, :ps, :pe, :plan, :snap, :rev, :cpa, :cnt, 'completed', '')
                 ON DUPLICATE KEY UPDATE
                    plan_id = VALUES(plan_id),
                    plan_snapshot = VALUES(plan_snapshot),
                    revshare_amount = VALUES(revshare_amount),
                    cpa_amount = VALUES(cpa_amount),
                    cpa_count = VALUES(cpa_count),
                    status = 'completed',
                    created_at = CURRENT_TIMESTAMP"
            )->execute([
                'aid' => $affId,
                'ps' => $periodStart,
                'pe' => $periodEnd,
                'plan' => $planId > 0 ? $planId : null,
                'snap' => $snapshot,
                'rev' => number_format($revshare, 2, '.', ''),
                'cpa' => number_format($cpa, 2, '.', ''),
                'cnt' => $cpaCount,
            ]);
        } catch (Throwable $e) {
            error_log('[AffiliateCommissionEngine] period run: ' . $e->getMessage());
        }
    }

    private static function normalizeDate(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }
        $ts = strtotime($date);
        return $ts === false ? '' : date('Y-m-d', $ts);
    }
}
