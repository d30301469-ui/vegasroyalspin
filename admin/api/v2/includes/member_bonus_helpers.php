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
     */
    function memberPromotionResolveClaimAmountV2(PDO $pdo, int $userId, array $promotion): float
    {
        $bonusType = strtolower(trim((string) ($promotion['bonus_type'] ?? '')));
        $totalDeposit = memberApprovedDepositTotalV2($pdo, $userId);

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
        if ($rule === null && isset($rules[0]) && is_array($rules[0])) {
            $rule = $rules[0];
        }

        $ruleAppliesTo = strtolower((string) ($rule['applies_to'] ?? ''));
        $ruleType = strtolower((string) ($rule['type'] ?? ''));
        $ruleValue = (float) ($rule['value'] ?? $rule['amount'] ?? 0);
        $ruleMaxAmount = isset($rule['max_amount']) ? (float) $rule['max_amount'] : null;

        // --- bonus_rules varsa onu kullan ---
        if ($rule !== null && $ruleValue > 0) {
            $isRulePct = $ruleType === 'percentage';
            $isFirstDeposit = in_array($ruleAppliesTo, ['first_deposit', 'firstdeposit'], true);

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

        // --- bonus_type alanına göre hesaplama ---
        if ($bonusType === 'first_deposit_pct') {
            $firstDeposit = memberFirstApprovedDepositAmountV2($pdo, $userId);
            if ($firstDeposit > 0) {
                $pct = (float) ($promotion['bonus_amount'] ?? 0);
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
        // bonus_type tanımlanmamışsa, başlıktan akıllı tespit dene
        if ($bonusType === '') {
            $title = strtolower((string) ($promotion['title'] ?? ''));
            $titleClean = preg_replace('/\s+/u', ' ', $title);

            // Başlıkta % var mı?
            $titlePct = 0.0;
            if (preg_match('/(\d+(?:[\.,]\d+)?)\s*%/u', $titleClean, $m)) {
                $titlePct = (float) str_replace(',', '.', (string) ($m[1] ?? '0'));
            }

            if ($titlePct > 0 && $titlePct <= 200) {
                // İlk yatırım ifadesi var mı?
                $isFirstDepositTitle = preg_match(
                    '/(?:ilk\s*yat[ıi]r[ıi]m|ho[sş]geldin|ilk\s*para\s*yat[ıi]rma|first\s*deposit)/u',
                    $titleClean
                ) === 1;

                if ($isFirstDepositTitle) {
                    $firstDeposit = memberFirstApprovedDepositAmountV2($pdo, $userId);
                    if ($firstDeposit > 0) {
                        $calculated = round(($firstDeposit * $titlePct) / 100, 2);
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

if (!function_exists('memberCheckPromotionClaimLimitV2')) {
    /**
     * Kullanıcının bu promosyondan kaç kez daha faydalanabileceğini kontrol eder.
     *
     * Kural: Her onaylı yatırım = 1 talep hakkı.
     * Örnek: 3 onaylı yatırım, 2 onaylı talep → 1 hak kaldı.
     *
     * @return array{canClaim: bool, approvedDeposits: int, approvedClaims: int, remainingRights: int, message: string}
     */
    function memberCheckPromotionClaimLimitV2(PDO $pdo, int $userId, int $promotionId): array
    {
        $approvedDeposits = memberApprovedDepositCountV2($pdo, $userId);
        $approvedClaims = memberApprovedClaimCountForPromotionV2($pdo, $userId, $promotionId);
        $remaining = max(0, $approvedDeposits - $approvedClaims);

        if ($approvedDeposits <= 0) {
            return [
                'canClaim' => false,
                'approvedDeposits' => 0,
                'approvedClaims' => $approvedClaims,
                'remainingRights' => 0,
                'message' => 'Bu bonustan faydalanabilmeniz için yatırım yapmanız gerekmektedir.',
            ];
        }

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
