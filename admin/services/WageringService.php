<?php

declare(strict_types=1);

/**
 * Çevrim (turnover / wagering) takip servisi.
 *
 * Sistemde tek cüzdan alanı (users.balance) kullanıldığından bir bahis aynı anda
 * hem hesap seviyesindeki 1x ana bakiye çevrim şartına hem de kullanıcının aktif
 * bonus(lar)ındaki çevrim hedefine işlenir:
 *
 * - Ana bakiye: her onaylanan yatırım kadar 1x çevrim şartı ekler
 *   (users.wagering_required); gerçek bahisler users.wagering_progress alanını artırır.
 * - Bonus bakiyesi: user_active_bonuses.wagering_target / total_bet_amount alanları
 *   zaten şema olarak mevcut; bahis geldikçe total_bet_amount artar, hedefe
 *   ulaşılınca bonus 'completed' durumuna geçer.
 *
 * Tüm metodlar en iyi çaba (best-effort) ile çalışır: bir hata oluşursa sadece
 * loglanır, çağıran cüzdan işlemini (bet/win/deposit) ASLA bloklamaz veya
 * geri almaz.
 */
final class WageringService
{
    private static bool $schemaEnsured = false;

    public static function ensureSchema(PDO $pdo): void
    {
        if (self::$schemaEnsured) {
            return;
        }
        self::$schemaEnsured = true;

        try {
            $cols = self::tableColumns($pdo, 'users');
            if ($cols !== []) {
                if (!in_array('wagering_required', $cols, true)) {
                    $pdo->exec('ALTER TABLE users ADD COLUMN wagering_required DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER bonus_balance');
                }
                if (!in_array('wagering_progress', $cols, true)) {
                    $pdo->exec('ALTER TABLE users ADD COLUMN wagering_progress DECIMAL(15,2) NOT NULL DEFAULT 0.00 AFTER wagering_required');
                }
            }
        } catch (Throwable $e) {
            error_log('[WageringService] ensureSchema (users) failed: ' . $e->getMessage());
        }

        try {
            $bonusCols = self::tableColumns($pdo, 'user_active_bonuses');
            if ($bonusCols !== [] && !in_array('completed_at', $bonusCols, true)) {
                $pdo->exec('ALTER TABLE user_active_bonuses ADD COLUMN completed_at DATETIME NULL AFTER is_complete');
            }
        } catch (Throwable $e) {
            error_log('[WageringService] ensureSchema (user_active_bonuses) failed: ' . $e->getMessage());
        }
    }

    /**
     * Onaylanan bir yatırım kadar ana bakiye çevrim hedefini artırır (1x kural).
     */
    public static function registerDeposit(PDO $pdo, int $userId, float $amount): void
    {
        if ($userId <= 0 || $amount <= 0) {
            return;
        }
        self::ensureSchema($pdo);
        try {
            $pdo->prepare(
                'UPDATE users SET wagering_required = wagering_required + :amount WHERE id = :id'
            )->execute([
                'amount' => number_format($amount, 2, '.', ''),
                'id' => $userId,
            ]);
        } catch (Throwable $e) {
            error_log('[WageringService] registerDeposit failed: ' . $e->getMessage());
        }
    }

    /**
     * Gerçek bakiyeden yapılan bir bahsi hem hesap seviyesi (1x) hem de kullanıcının
     * o an aktif olan bonus çevrim hedeflerine işler. $amount her zaman pozitif bahis
     * tutarı olmalıdır.
     */
    public static function registerBet(PDO $pdo, int $userId, float $amount): void
    {
        if ($userId <= 0 || $amount <= 0) {
            return;
        }
        self::ensureSchema($pdo);

        try {
            $pdo->prepare(
                'UPDATE users SET wagering_progress = wagering_progress + :amount WHERE id = :id'
            )->execute([
                'amount' => number_format($amount, 2, '.', ''),
                'id' => $userId,
            ]);
        } catch (Throwable $e) {
            error_log('[WageringService] registerBet (account) failed: ' . $e->getMessage());
        }

        self::applyBonusDelta($pdo, $userId, $amount);
    }

    /**
     * Bir bahis rollback/refund/cancel edildiğinde ilgili çevrim ilerlemesini geri
     * alır (fazla sayım yapılmaması için).
     */
    public static function reverseBet(PDO $pdo, int $userId, float $amount): void
    {
        if ($userId <= 0 || $amount <= 0) {
            return;
        }
        self::ensureSchema($pdo);

        try {
            $pdo->prepare(
                'UPDATE users SET wagering_progress = GREATEST(0, wagering_progress - :amount) WHERE id = :id'
            )->execute([
                'amount' => number_format($amount, 2, '.', ''),
                'id' => $userId,
            ]);
        } catch (Throwable $e) {
            error_log('[WageringService] reverseBet (account) failed: ' . $e->getMessage());
        }

        self::applyBonusDelta($pdo, $userId, -$amount);
    }

    /**
     * @return array{required: float, progress: float, remaining: float, percent: float, isComplete: bool, multiplier: int}
     */
    public static function accountProgress(PDO $pdo, int $userId): array
    {
        self::ensureSchema($pdo);
        $required = 0.0;
        $progress = 0.0;
        try {
            $stmt = $pdo->prepare('SELECT wagering_required, wagering_progress FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $required = round((float) ($row['wagering_required'] ?? 0), 2);
                $progress = round((float) ($row['wagering_progress'] ?? 0), 2);
            }
        } catch (Throwable) {
            // Şema henüz oluşmadıysa sıfır olarak dön.
        }

        $remaining = max(0.0, round($required - $progress, 2));
        $percent = $required > 0 ? min(100.0, round(($progress / $required) * 100, 2)) : 100.0;

        return [
            'required' => $required,
            'progress' => $progress,
            'remaining' => $remaining,
            'percent' => $percent,
            'isComplete' => $required <= 0 || $progress >= $required,
            'multiplier' => 1,
        ];
    }

    private static function applyBonusDelta(PDO $pdo, int $userId, float $delta): void
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT id, wagering_target, total_bet_amount FROM user_active_bonuses
                 WHERE user_id = :user_id AND status = 'active'"
            );
            $stmt->execute(['user_id' => $userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $bonusId = (int) ($row['id'] ?? 0);
                if ($bonusId <= 0) {
                    continue;
                }
                $target = round((float) ($row['wagering_target'] ?? 0), 2);
                $newTotal = max(0.0, round((float) ($row['total_bet_amount'] ?? 0) + $delta, 2));
                $isComplete = $target > 0 && $newTotal >= $target;

                $pdo->prepare(
                    "UPDATE user_active_bonuses
                     SET total_bet_amount = :total,
                         is_complete = :is_complete,
                         status = CASE WHEN :is_complete_status = 1 THEN 'completed' ELSE status END,
                         completed_at = CASE WHEN :is_complete_at = 1 THEN NOW() ELSE completed_at END
                     WHERE id = :id"
                )->execute([
                    'total' => number_format($newTotal, 2, '.', ''),
                    'is_complete' => $isComplete ? 1 : 0,
                    'is_complete_status' => $isComplete ? 1 : 0,
                    'is_complete_at' => $isComplete ? 1 : 0,
                    'id' => $bonusId,
                ]);
            }
        } catch (Throwable $e) {
            error_log('[WageringService] applyBonusDelta failed: ' . $e->getMessage());
        }
    }

    /**
     * @return list<string>
     */
    private static function tableColumns(PDO $pdo, string $table): array
    {
        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM `' . $table . '`');
            if ($stmt === false) {
                return [];
            }
            $cols = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cols[] = (string) ($row['Field'] ?? '');
            }
            return $cols;
        } catch (Throwable) {
            return [];
        }
    }
}
