<?php

/**
 * Bonus talep ortak yardımcı fonksiyonları.
 * member_bonuses.php ve member_engagement.php tarafından include edilir.
 *
 * Tüm yatırım kontrolleri yalnızca megapayz_transactions tablosu üzerinden yapılır.
 */

if (!function_exists('memberApprovedDepositTotalV2')) {
    /**
     * Kullanıcının onaylı yatırım toplamını megapayz_transactions tablosundan okur.
     * Legacy tablo fallback'leri kaldırılmıştır — tüm finans verisi MegaPayz üzerindedir.
     */
    function memberApprovedDepositTotalV2(PDO $pdo, int $userId): float
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT COALESCE(SUM(amount), 0) FROM megapayz_transactions
                 WHERE user_id = :user_id AND type = 'deposit' AND status IN ('confirmed', 'approved', 'success', 'completed')"
            );
            $stmt->execute(['user_id' => $userId]);

            return round(max(0, (float) $stmt->fetchColumn()), 2);
        } catch (Throwable) {
            return 0.0;
        }
    }
}

if (!function_exists('memberFirstApprovedDepositAmountV2')) {
    /**
     * Kullanıcının İLK onaylı yatırım tutarını döndürür.
     * "İlk Yatırım Bonusu" tipindeki promosyonlar için kullanılır.
     */
    function memberFirstApprovedDepositAmountV2(PDO $pdo, int $userId): float
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT amount FROM megapayz_transactions
                 WHERE user_id = :user_id AND type = 'deposit' AND status IN ('confirmed', 'approved', 'success', 'completed')
                 ORDER BY created_at ASC LIMIT 1"
            );
            $stmt->execute(['user_id' => $userId]);

            return round(max(0, (float) $stmt->fetchColumn()), 2);
        } catch (Throwable) {
            return 0.0;
        }
    }
}

if (!function_exists('memberHasConfirmedDepositV2')) {
    /**
     * Kullanıcının en az bir onaylı yatırımı var mı?
     */
    function memberHasConfirmedDepositV2(PDO $pdo, int $userId): bool
    {
        try {
            MegaPayzService::bootstrap($pdo);
            $check = $pdo->prepare(
                "SELECT COUNT(*) FROM megapayz_transactions
                 WHERE user_id = :user_id AND type = 'deposit' AND status IN ('confirmed', 'approved', 'success', 'completed')"
            );
            $check->execute(['user_id' => $userId]);

            return (int) $check->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }
}

if (!function_exists('memberPromotionIsFirstDepositV2')) {
    /**
     * Promosyonun "ilk yatırım / hoşgeldin" tipi olup olmadığını tespit eder.
     * bonus_type, bonus_rules.applies_to ve başlık metnine bakar.
     */
    function memberPromotionIsFirstDepositV2(array $promotion): bool
    {
        $bonusType = strtolower(trim((string) ($promotion['bonus_type'] ?? '')));
        if (in_array($bonusType, ['first_deposit_pct', 'first_deposit', 'firstdeposit'], true)) {
            return true;
        }

        $rawRules = $promotion['bonus_rules'] ?? null;
        if (is_string($rawRules) && trim($rawRules) !== '') {
            $decoded = json_decode($rawRules, true);
            $rules = [];
            if (is_array($decoded)) {
                $rules = array_is_list($decoded) ? $decoded : [$decoded];
            }
            foreach ($rules as $rule) {
                if (!is_array($rule)) {
                    continue;
                }
                $appliesTo = strtolower((string) ($rule['applies_to'] ?? ''));
                if (in_array($appliesTo, ['first_deposit', 'firstdeposit'], true)) {
                    return true;
                }
            }
        }

        $title = mb_strtolower((string) ($promotion['title'] ?? ''), 'UTF-8');
        $titleClean = preg_replace('/\s+/u', ' ', $title) ?? $title;

        return preg_match(
            '/(?:ilk\s*yat[ıi]r[ıi]m|ho[sş]geldin|ilk\s*para\s*yat[ıi]rma|first\s*deposit)/u',
            $titleClean
        ) === 1;
    }
}

if (!function_exists('memberPromotionResolveClaimAmountV2')) {
    /**
     * Talep tutarını promosyon kaydı + bonus_rules + onaylı yatırım üzerinden hesaplar.
     *
     * Hesaplama önceliği:
     * 1. bonus_rules JSON içinde ilk eşleşen kural (applies_to: first_deposit|deposit|any)
     * 2. bonus_type alanı: 'first_deposit_pct' → ilk yatırım × %, 'percentage' → toplam yatırım × %
     * 3. bonus_amount sabit değeri (onaylı yatırım toplamını aşamaz)
     *
     * KRİTİK KURAL: Hesaplanan bonus tutarı hiçbir zaman kullanıcının
     * onaylı yatırım toplamından fazla olamaz.
     * İlk yatırım bonuslarında taban her zaman ilk onaylı yatırımdır.
     */
    function memberPromotionResolveClaimAmountV2(PDO $pdo, int $userId, array $promotion): float
    {
        $bonusType = strtolower(trim((string) ($promotion['bonus_type'] ?? '')));
        $totalDeposit = memberApprovedDepositTotalV2($pdo, $userId);
        $isFirstDepositPromo = memberPromotionIsFirstDepositV2($promotion);
        $firstDeposit = $isFirstDepositPromo ? memberFirstApprovedDepositAmountV2($pdo, $userId) : 0.0;

        // --- bonus_rules JSON parse ---
        $rules = [];
        $rawRules = $promotion['bonus_rules'] ?? null;
        if (is_string($rawRules) && trim($rawRules) !== '') {
            $decoded = json_decode($rawRules, true);
            if (is_array($decoded)) {
                if (array_is_list($decoded)) {
                    foreach ($decoded as $rule) {
                        if (is_array($rule)) {
                            $rules[] = $rule;
                        }
                    }
                } else {
                    $rules[] = $decoded;
                }
            }
        }

        // İlk eşleşen kuralı bul
        $rule = null;
        foreach ($rules as $candidate) {
            $appliesTo = strtolower((string) ($candidate['applies_to'] ?? ''));
            if (in_array($appliesTo, ['', 'any', 'all', 'deposit', 'first_deposit', 'firstdeposit'], true)) {
                $rule = $candidate;
                break;
            }
        }

        if ($rule !== null) {
            $ruleAppliesTo = strtolower((string) ($rule['applies_to'] ?? ''));
            $ruleType = strtolower((string) ($rule['type'] ?? ''));
            $ruleValue = (float) ($rule['value'] ?? $rule['amount'] ?? 0);
            $ruleMaxAmount = isset($rule['max_amount']) ? (float) $rule['max_amount'] : null;

            // --- bonus_rules varsa onu kullan ---
            if ($ruleValue > 0) {
                $isRulePct = $ruleType === 'percentage';
                $isFirstDeposit = $isFirstDepositPromo
                    || in_array($ruleAppliesTo, ['first_deposit', 'firstdeposit'], true);

                if ($isRulePct) {
                    $baseAmount = $isFirstDeposit
                        ? memberFirstApprovedDepositAmountV2($pdo, $userId)
                        : $totalDeposit;

                    if ($baseAmount > 0) {
                        $calculated = round(($baseAmount * $ruleValue) / 100, 2);
                        if ($ruleMaxAmount !== null && $ruleMaxAmount > 0) {
                            $calculated = min($calculated, round($ruleMaxAmount, 2));
                        }
                        // Bonus yatırım toplamını aşamaz
                        if ($totalDeposit > 0) {
                            $calculated = min($calculated, $totalDeposit);
                        }

                        return $calculated;
                    }
                } else {
                    // fixed tipi kural: yatırım toplamını aşamaz
                    $fixed = round($ruleValue, 2);
                    if ($totalDeposit > 0) {
                        $fixed = min($fixed, $totalDeposit);
                    }

                    return $fixed;
                }
            }
        }

        // --- İlk yatırım / hoşgeldin: yüzde her zaman ilk yatırım üzerinden ---
        if ($isFirstDepositPromo || $bonusType === 'first_deposit_pct') {
            if ($firstDeposit <= 0) {
                $firstDeposit = memberFirstApprovedDepositAmountV2($pdo, $userId);
            }
            if ($firstDeposit > 0) {
                $pct = (float) ($promotion['bonus_amount'] ?? 0);
                if ($pct <= 0 || $pct > 200) {
                    $title = mb_strtolower((string) ($promotion['title'] ?? ''), 'UTF-8');
                    if (preg_match('/(\d+(?:[\.,]\d+)?)\s*%/u', $title, $m)) {
                        $pct = (float) str_replace(',', '.', (string) ($m[1] ?? '0'));
                    }
                }
                if ($pct > 0 && $pct <= 200) {
                    $calculated = round(($firstDeposit * $pct) / 100, 2);
                    if ($totalDeposit > 0) {
                        $calculated = min($calculated, $totalDeposit);
                    }

                    return $calculated;
                }
            }
        }

        if ($bonusType === 'percentage') {
            if ($totalDeposit > 0) {
                $pct = (float) ($promotion['bonus_amount'] ?? 0);
                if ($pct > 0 && $pct <= 200) {
                    $calculated = round(($totalDeposit * $pct) / 100, 2);

                    return min($calculated, $totalDeposit);
                }
            }
        }

        // --- bonus_amount sabit değer (yatırım toplamını aşamaz) ---
        // bonus_type tanımlanmamışsa veya eski 'fixed' değerindeyse, başlıktan akıllı tespit dene
        if ($bonusType === '' || $bonusType === 'fixed') {
            $title = strtolower((string) ($promotion['title'] ?? ''));
            $titleClean = preg_replace('/\s+/u', ' ', $title);

            // Başlıkta % var mı?
            $titlePct = 0.0;
            if (preg_match('/(\d+(?:[\.,]\d+)?)\s*%/u', $titleClean, $m)) {
                $titlePct = (float) str_replace(',', '.', (string) ($m[1] ?? '0'));
            }

            if ($titlePct > 0 && $titlePct <= 200) {
                if ($isFirstDepositPromo) {
                    $base = memberFirstApprovedDepositAmountV2($pdo, $userId);
                    if ($base > 0) {
                        $calculated = round(($base * $titlePct) / 100, 2);
                        if ($totalDeposit > 0) {
                            $calculated = min($calculated, $totalDeposit);
                        }

                        return $calculated;
                    }
                }

                // Genel yüzdesel (toplam yatırım üzerinden)
                if ($totalDeposit > 0) {
                    $calculated = round(($totalDeposit * $titlePct) / 100, 2);

                    return min($calculated, $totalDeposit);
                }
            }
        }

        // --- bonus_amount sabit değer (yatırım toplamını aşamaz) ---
        $amount = round((float) ($promotion['bonus_amount'] ?? 0), 2);
        if ($amount > 0 && $totalDeposit > 0) {
            // Sabit bonus, onaylı yatırım toplamından fazla olamaz
            $amount = min($amount, $totalDeposit);
        }
        if ($amount > 0) {
            return $amount;
        }

        return 0.0;
    }
}

if (!function_exists('memberPromotionsSelectColumnsV2')) {
    /**
     * Promosyon sorgularında kullanılacak ortak SELECT kolon listesi.
     */
    function memberPromotionsSelectColumnsV2(): string
    {
        return 'id, title, description, long_description, type, category, terms, image_url, link_url,
                bonus_type, bonus_amount, bonus_rules, wagering_multiplier, general_rules';
    }
}

if (!function_exists('memberApprovedDepositCountV2')) {
    /**
     * Kullanıcının onaylı yatırım sayısını döndürür.
     * Her onaylı yatırım, promosyon başına 1 talep hakkı verir.
     */
    function memberApprovedDepositCountV2(PDO $pdo, int $userId): int
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM megapayz_transactions
                 WHERE user_id = :user_id AND type = 'deposit' AND status IN ('confirmed', 'approved', 'success', 'completed')"
            );
            $stmt->execute(['user_id' => $userId]);

            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }
}

if (!function_exists('memberApprovedClaimCountForPromotionV2')) {
    /**
     * Kullanıcının belirli bir promosyondan onaylanmış talep sayısını döndürür.
     */
    function memberApprovedClaimCountForPromotionV2(PDO $pdo, int $userId, int $promotionId): int
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM bonus_claim_requests
                 WHERE user_id = :user_id AND promotion_id = :promotion_id AND status = 'approved'"
            );
            $stmt->execute(['user_id' => $userId, 'promotion_id' => $promotionId]);

            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }
}

if (!function_exists('memberPriorClaimCountForPromotionV2')) {
    /**
     * Pending/approved/rejected talepler (yeniden başvuru engeli için).
     * Pending kayıtlar replace edildiği için ayrı tutulmaz; burada sayılırsa
     * caller zaten pending'i silmeden önce kontrol etmemeli — claim path
     * pending'i sildikten sonra değil, önce limit kontrolü yapar; pending varsa
     * replace ile devam eder, bu yüzden pending'i "tüketilmiş hak" saymayız.
     */
    function memberPriorClaimCountForPromotionV2(PDO $pdo, int $userId, int $promotionId): int
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM bonus_claim_requests
                 WHERE user_id = :user_id
                   AND promotion_id = :promotion_id
                   AND status IN ('approved', 'rejected')"
            );
            $stmt->execute(['user_id' => $userId, 'promotion_id' => $promotionId]);

            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }
}

if (!function_exists('memberUserPendingBonusClaimV2')) {
    /**
     * Başka promosyonda bekleyen talep var mı? (çapraz promosyon yarışını engeller)
     *
     * @return array{id:int,promotion_id:int,bonus_name:string}|null
     */
    function memberUserPendingBonusClaimV2(PDO $pdo, int $userId, ?int $exceptPromotionId = null): ?array
    {
        if ($userId <= 0) {
            return null;
        }
        try {
            $sql = "SELECT id, promotion_id, bonus_name FROM bonus_claim_requests
                    WHERE user_id = :user_id AND status = 'pending'";
            $params = ['user_id' => $userId];
            if ($exceptPromotionId !== null && $exceptPromotionId > 0) {
                $sql .= ' AND promotion_id <> :promotion_id';
                $params['promotion_id'] = $exceptPromotionId;
            }
            $sql .= ' ORDER BY id ASC LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return is_array($row) ? $row : null;
        } catch (Throwable) {
            return null;
        }
    }
}

if (!function_exists('memberPendingBonusClaimBlockMessageV2')) {
    function memberPendingBonusClaimBlockMessageV2(?array $pending): string
    {
        if (!is_array($pending)) {
            return 'Bekleyen bonus talebiniz varken yeni talep oluşturamazsınız.';
        }
        $name = trim((string) ($pending['bonus_name'] ?? ''));

        return $name !== ''
            ? 'Bekleyen bonus talebiniz var (' . $name . '). Yeni talep oluşturamazsınız.'
            : 'Bekleyen bonus talebiniz varken yeni talep oluşturamazsınız.';
    }
}

if (!function_exists('memberCheckPromotionClaimLimitV2')) {
    /**
     * Kullanıcının bu promosyondan kaç kez daha faydalanabileceğini kontrol eder.
     *
     * Genel kural: Her onaylı yatırım = 1 talep hakkı (yalnızca approved talepler hak tüketir).
     * İlk yatırım / hoşgeldin: ömür boyu 1 hak — approved VEYA rejected sonrası yeniden talep yok.
     *
     * @return array{canClaim: bool, approvedDeposits: int, approvedClaims: int, remainingRights: int, message: string}
     */
    function memberCheckPromotionClaimLimitV2(PDO $pdo, int $userId, int $promotionId): array
    {
        $approvedDeposits = memberApprovedDepositCountV2($pdo, $userId);
        $approvedClaims = memberApprovedClaimCountForPromotionV2($pdo, $userId, $promotionId);

        $promotion = null;
        try {
            $promoStmt = $pdo->prepare(
                'SELECT id, title, bonus_type, bonus_rules FROM promotions WHERE id = :id LIMIT 1'
            );
            $promoStmt->execute(['id' => $promotionId]);
            $row = $promoStmt->fetch(PDO::FETCH_ASSOC);
            $promotion = is_array($row) ? $row : null;
        } catch (Throwable) {
            $promotion = null;
        }

        $isFirstDeposit = is_array($promotion) && memberPromotionIsFirstDepositV2($promotion);

        if ($approvedDeposits <= 0) {
            return [
                'canClaim' => false,
                'approvedDeposits' => 0,
                'approvedClaims' => $approvedClaims,
                'remainingRights' => 0,
                'message' => 'Bu bonustan faydalanabilmeniz için yatırım yapmanız gerekmektedir.',
            ];
        }

        $crossPending = memberUserPendingBonusClaimV2($pdo, $userId, $promotionId);
        if (is_array($crossPending)) {
            return [
                'canClaim' => false,
                'approvedDeposits' => $approvedDeposits,
                'approvedClaims' => $approvedClaims,
                'remainingRights' => 0,
                'message' => memberPendingBonusClaimBlockMessageV2($crossPending),
            ];
        }

        if ($isFirstDeposit) {
            $priorClaims = memberPriorClaimCountForPromotionV2($pdo, $userId, $promotionId);
            if ($priorClaims > 0) {
                return [
                    'canClaim' => false,
                    'approvedDeposits' => $approvedDeposits,
                    'approvedClaims' => $approvedClaims,
                    'remainingRights' => 0,
                    'message' => 'Bu promosyondan yalnızca ilk yatırımınız için bir kez yararlanabilirsiniz.',
                ];
            }

            return [
                'canClaim' => true,
                'approvedDeposits' => $approvedDeposits,
                'approvedClaims' => $approvedClaims,
                'remainingRights' => 1,
                'message' => 'Bu promosyondan yalnızca ilk yatırımınız için bir kez yararlanabilirsiniz.',
            ];
        }

        $remaining = max(0, $approvedDeposits - $approvedClaims);

        if ($remaining <= 0) {
            return [
                'canClaim' => false,
                'approvedDeposits' => $approvedDeposits,
                'approvedClaims' => $approvedClaims,
                'remainingRights' => 0,
                'message' => 'Bu promosyondan tüm yatırımlarınız için zaten faydalandınız.',
            ];
        }

        return [
            'canClaim' => true,
            'approvedDeposits' => $approvedDeposits,
            'approvedClaims' => $approvedClaims,
            'remainingRights' => $remaining,
            'message' => "Bu promosyondan $remaining kez daha faydalanabilirsiniz.",
        ];
    }
}

if (!function_exists('memberInsertBonusClaimRequestV2')) {
    /**
     * Bonus talebini transaction içinde oluşturur; yarış koşullarında çift pending engellenir.
     *
     * @return array{requestId:int,requestedAmount:float,replacedPending:bool,limit:array<string,mixed>}
     */
    function memberInsertBonusClaimRequestV2(PDO $pdo, int $userId, array $promotion, ?string $userMessage = null): array
    {
        $promotionId = (int) ($promotion['id'] ?? 0);
        if ($promotionId <= 0) {
            throw new InvalidArgumentException('Promosyon ID geçersiz.');
        }

        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
            $lock->execute(['id' => $userId]);
            if (!$lock->fetchColumn()) {
                throw new RuntimeException('Kullanıcı bulunamadı.');
            }

            $crossPending = memberUserPendingBonusClaimV2($pdo, $userId, $promotionId);
            if (is_array($crossPending)) {
                throw new RuntimeException(memberPendingBonusClaimBlockMessageV2($crossPending));
            }

            $claimLimit = memberCheckPromotionClaimLimitV2($pdo, $userId, $promotionId);
            if (!$claimLimit['canClaim']) {
                throw new RuntimeException((string) ($claimLimit['message'] ?? 'Bonus talep hakkınız bulunmuyor.'));
            }

            $replacedPending = false;
            $existingClaim = $pdo->prepare(
                "SELECT id FROM bonus_claim_requests
                 WHERE user_id = :user_id AND promotion_id = :promotion_id AND status = 'pending'
                 LIMIT 1 FOR UPDATE"
            );
            $existingClaim->execute(['user_id' => $userId, 'promotion_id' => $promotionId]);
            $existingClaimRow = $existingClaim->fetch(PDO::FETCH_ASSOC);
            if (is_array($existingClaimRow)) {
                $pdo->prepare('DELETE FROM bonus_claim_requests WHERE id = :id')
                    ->execute(['id' => (int) $existingClaimRow['id']]);
                $replacedPending = true;
            }

            $requestedAmount = memberPromotionResolveClaimAmountV2($pdo, $userId, $promotion);
            if ($requestedAmount <= 0) {
                throw new RuntimeException('Promosyon bonus tutarı hesaplanamadı.');
            }

            $insert = $pdo->prepare(
                "INSERT INTO bonus_claim_requests
                (user_id, promotion_id, bonus_name, category, promotion_type, requested_amount, wagering_multiplier, user_message, status, created_at)
                VALUES
                (:user_id, :promotion_id, :bonus_name, :category, :promotion_type, :requested_amount, :wagering_multiplier, :user_message, 'pending', NOW())"
            );
            $insert->execute([
                'user_id' => $userId,
                'promotion_id' => $promotionId,
                'bonus_name' => (string) ($promotion['title'] ?? ''),
                'category' => (string) ($promotion['type'] ?? ''),
                'promotion_type' => (string) ($promotion['bonus_type'] ?? ''),
                'requested_amount' => number_format($requestedAmount, 2, '.', ''),
                'wagering_multiplier' => number_format((float) ($promotion['wagering_multiplier'] ?? 1), 2, '.', ''),
                'user_message' => $userMessage !== null && trim($userMessage) !== '' ? trim($userMessage) : null,
            ]);

            $requestId = (int) $pdo->lastInsertId();
            $pdo->commit();

            return [
                'requestId' => $requestId,
                'requestedAmount' => $requestedAmount,
                'replacedPending' => $replacedPending,
                'limit' => $claimLimit,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
