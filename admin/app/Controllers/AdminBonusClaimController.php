<?php

declare(strict_types=1);

/**
 * Kullanıcıların promosyon sayfası / bonustalep sayfasından oluşturduğu
 * bonus_claim_requests taleplerini onaylayıp gerçek bir user_active_bonuses
 * kaydına + users.bonus_balance kredisine dönüştürür (promocode_requests
 * onayındaki pattern ile birebir aynı yapı).
 */
final class AdminBonusClaimController extends AdminController
{
    public function approve(): void
    {
        $this->requirePermission('bonus-claims');
        $this->ensurePost();

        $id = max(0, (int) ($_POST['id'] ?? 0));
        if ($id <= 0) {
            $this->flash('Geçersiz bonus talep ID.');
            $this->redirect(AdminAuth::url('/module?key=bonus-claims'));
        }

        $pdo = AdminDatabase::pdo();
        $adminUsername = AdminAuth::userName();
        $processedByBinding = $this->processedByBinding($pdo);

        try {
            $pdo->beginTransaction();

            $reqStmt = $pdo->prepare(
                'SELECT id, user_id, promotion_id, bonus_name, category, promotion_type, requested_amount, wagering_multiplier, status
                 FROM bonus_claim_requests WHERE id = :id LIMIT 1 FOR UPDATE'
            );
            $reqStmt->execute(['id' => $id]);
            $request = $reqStmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($request)) {
                throw new RuntimeException('Talep bulunamadı.');
            }
            if ((string) ($request['status'] ?? '') !== 'pending') {
                throw new RuntimeException('Sadece pending talepler onaylanabilir.');
            }

            $userId = (int) ($request['user_id'] ?? 0);
            $userChk = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
            $userChk->execute(['id' => $userId]);
            if (!$userChk->fetch()) {
                throw new RuntimeException('Talep sahibi kullanıcı bulunamadı.');
            }

            $bonusAmount = round((float) ($request['requested_amount'] ?? 0), 2);
            if ($bonusAmount <= 0) {
                throw new RuntimeException('Talep tutarı geçersiz.');
            }
            $wageringMult = max(1.0, (float) ($request['wagering_multiplier'] ?? 1));
            $wageringTarget = $bonusAmount * $wageringMult;
            $deadline = date('Y-m-d H:i:s', strtotime('+30 days'));
            $promotionId = (int) ($request['promotion_id'] ?? 0);
            $bonusName = (string) ($request['bonus_name'] ?? 'Bonus');
            $category = (string) ($request['category'] ?? '');
            if ($category === '') {
                $category = (string) ($request['promotion_type'] ?? 'manual');
            }

            $pdo->prepare(
                "INSERT INTO user_active_bonuses
                 (user_id, promotion_id, name, category, initial_amount, current_bonus_balance,
                  wagering_requirement, wagering_target, total_bet_amount, status, granted_at, deadline)
                 VALUES (:user_id, :promotion_id, :name, :category, :initial_amount, :current_amount,
                  :wagering_req, :wagering_target, 0, 'active', NOW(), :deadline)"
            )->execute([
                'user_id' => $userId,
                'promotion_id' => $promotionId > 0 ? $promotionId : null,
                'name' => $bonusName,
                'category' => $category,
                'initial_amount' => number_format($bonusAmount, 2, '.', ''),
                'current_amount' => number_format($bonusAmount, 2, '.', ''),
                'wagering_req' => $wageringMult,
                'wagering_target' => number_format($wageringTarget, 2, '.', ''),
                'deadline' => $deadline,
            ]);

            // Bonus tutarı kullanıcının gerçek bonus_balance alanına da işlenir (bkz.
            // AdminPromotionController::assignBonus — bu talebin manuel atamayla
            // birebir aynı sonucu üretmesi gerekiyor).
            $pdo->prepare('UPDATE users SET bonus_balance = bonus_balance + :amount WHERE id = :id')
                ->execute(['amount' => number_format($bonusAmount, 2, '.', ''), 'id' => $userId]);

            $claimUpdate = $pdo->prepare(
                "UPDATE bonus_claim_requests
                 SET status = 'approved', processed_by = :processed_by, processed_at = NOW(), updated_at = NOW()
                 WHERE id = :id"
            );
            $claimUpdate->bindValue(':processed_by', $processedByBinding['value'], $processedByBinding['type']);
            $claimUpdate->bindValue(':id', $id, PDO::PARAM_INT);
            $claimUpdate->execute();

            AdminAuth::writeLog($adminUsername, 'bonus_claim_approve', 'bonus_claim_requests', 'success', (string) $id);
            $pdo->commit();
            $this->flash("Bonus talebi onaylandı: $bonusName ($bonusAmount TRY) bonus bakiyesine eklendi.");
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            AdminAuth::writeLog($adminUsername, 'bonus_claim_approve', 'bonus_claim_requests', 'failed', (string) $id);
            $this->flash('Hata: ' . $e->getMessage());
        }

        $this->redirect(AdminAuth::url('/module?key=bonus-claims'));
    }

    public function reject(): void
    {
        $this->requirePermission('bonus-claims');
        $this->ensurePost();

        $id = max(0, (int) ($_POST['id'] ?? 0));
        if ($id <= 0) {
            $this->flash('Geçersiz bonus talep ID.');
            $this->redirect(AdminAuth::url('/module?key=bonus-claims'));
        }

        $pdo = AdminDatabase::pdo();
        $adminUsername = AdminAuth::userName();
        $processedByBinding = $this->processedByBinding($pdo);
        $stmt = $pdo->prepare(
            "UPDATE bonus_claim_requests
             SET status = 'rejected', processed_by = :processed_by, processed_at = NOW(), updated_at = NOW()
             WHERE id = :id AND status = 'pending'"
        );
        $stmt->bindValue(':processed_by', $processedByBinding['value'], $processedByBinding['type']);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            AdminAuth::writeLog($adminUsername, 'bonus_claim_reject', 'bonus_claim_requests', 'success', (string) $id);
            $this->flash('Bonus talebi reddedildi.');
        } else {
            $this->flash('Talep bulunamadı veya pending değil.');
        }

        $this->redirect(AdminAuth::url('/module?key=bonus-claims'));
    }

    /**
     * Tüm bonus tablolarını sıfırlar (canlı yayın öncesi temizlik).
     * POST /bonus-claim/reset-all
     */
    public function resetAll(): void
    {
        $this->requirePermission('bonus-claims');
        $this->ensurePost();

        $confirm = trim((string) ($_POST['confirm'] ?? ''));
        if ($confirm !== 'RESET_ALL_BONUS_CLAIMS') {
            $this->flash('Onay kodu gerekli. Lütfen "RESET_ALL_BONUS_CLAIMS" yazın.');
            $this->redirect(AdminAuth::url('/module?key=bonus-claims'));
        }

        $pdo = AdminDatabase::pdo();
        $adminUsername = AdminAuth::userName();

        try {
            $pdo->beginTransaction();
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            $pdo->exec('TRUNCATE TABLE bonus_claim_requests');
            $pdo->exec('TRUNCATE TABLE user_active_bonuses');
            $pdo->exec('TRUNCATE TABLE promocode_requests');
            $updated = $pdo->exec("UPDATE users SET bonus_balance = 0, active_wallet_mode = 'main' WHERE bonus_balance > 0 OR active_wallet_mode = 'bonus'");
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            $pdo->commit();

            AdminAuth::writeLog($adminUsername, 'reset_all_bonus_claims', 'system', 'success');
            $this->flash("Tüm bonus talepleri sıfırlandı. ($updated kullanıcı etkilendi)");
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->flash('Hata: ' . $e->getMessage());
        }

        $this->redirect(AdminAuth::url('/module?key=bonus-claims'));
    }

    private function ensurePost(): void
    {
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }
    }

    private function flash(string $message): void
    {
        $_SESSION['admin_flash'] = $message;
    }

    /**
     * processed_by kolonunu şema tipine göre güvenli biçimde doldurur.
     *
     * @return array{value:int|string,type:int}
     */
    private function processedByBinding(PDO $pdo): array
    {
        $admin = AdminAuth::user();
        $adminId = max(0, (int) ($admin['id'] ?? 0));
        $adminUsername = (string) ($admin['username'] ?? AdminAuth::userName());
        $isNumeric = true;

        try {
            $colStmt = $pdo->prepare(
                "SELECT DATA_TYPE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bonus_claim_requests' AND COLUMN_NAME = 'processed_by'
                 LIMIT 1"
            );
            $colStmt->execute();
            $dataType = strtolower((string) $colStmt->fetchColumn());
            if ($dataType !== '') {
                $isNumeric = in_array($dataType, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'], true);
            }
        } catch (Throwable) {
            // Tip tespit edilemezse integer değer kullanmak prod şemasıyla uyumludur.
            $isNumeric = true;
        }

        if ($isNumeric) {
            return ['value' => $adminId, 'type' => PDO::PARAM_INT];
        }

        return ['value' => $adminUsername, 'type' => PDO::PARAM_STR];
    }
}
