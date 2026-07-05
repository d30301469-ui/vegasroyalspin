<?php

declare(strict_types=1);

final class AdminPromocodeRequestController extends AdminController
{
    public function approve(): void
    {
        $this->requirePermission('promocode-requests');
        $this->ensurePost();

        $id = max(0, (int) ($_POST['id'] ?? 0));
        if ($id <= 0) {
            $this->flash('Geçersiz promo talep ID.');
            $this->redirect(AdminAuth::url('/module?key=promocode-requests'));
        }

        $pdo = AdminDatabase::pdo();
        $admin = AdminAuth::user();
        $adminId = (int) ($admin['id'] ?? 0);
        $adminUsername = AdminAuth::userName();

        try {
            $pdo->beginTransaction();

            $reqStmt = $pdo->prepare('SELECT id, user_id, promocode_id, promocode_code, amount, status FROM promocode_requests WHERE id = :id LIMIT 1 FOR UPDATE');
            $reqStmt->execute(['id' => $id]);
            $request = $reqStmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($request)) {
                throw new RuntimeException('Talep bulunamadı.');
            }
            if ((string) ($request['status'] ?? '') !== 'pending') {
                throw new RuntimeException('Sadece pending talepler onaylanabilir.');
            }

            $userId = (int) ($request['user_id'] ?? 0);
            $userStmt = $pdo->prepare('SELECT id, username, balance FROM users WHERE id = :id LIMIT 1 FOR UPDATE');
            $userStmt->execute(['id' => $userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($user)) {
                throw new RuntimeException('Talep sahibi kullanıcı bulunamadı.');
            }

            $promoId = (int) ($request['promocode_id'] ?? 0);
            if ($promoId > 0) {
                $promoStmt = $pdo->prepare('SELECT id, kullanim_limiti, mevcut_kullanim FROM promocodes WHERE id = :id LIMIT 1 FOR UPDATE');
                $promoStmt->execute(['id' => $promoId]);
                $promo = $promoStmt->fetch(PDO::FETCH_ASSOC);
                if (!is_array($promo)) {
                    throw new RuntimeException('İlgili promocode kaydı bulunamadı.');
                }
                $limit = (int) ($promo['kullanim_limiti'] ?? 0);
                $used = (int) ($promo['mevcut_kullanim'] ?? 0);
                if ($limit > 0 && $used >= $limit) {
                    throw new RuntimeException('Promocode kullanım limiti dolmuş.');
                }
                $pdo->prepare('UPDATE promocodes SET mevcut_kullanim = mevcut_kullanim + 1 WHERE id = :id')->execute(['id' => $promoId]);
            }

            $amount = round((float) ($request['amount'] ?? 0), 2);
            if ($amount <= 0) {
                throw new RuntimeException('Talep tutarı geçersiz.');
            }

            $beforeBalance = round((float) ($user['balance'] ?? 0), 2);
            $afterBalance = round($beforeBalance + $amount, 2);

            $pdo->prepare('UPDATE users SET balance = :balance WHERE id = :id')->execute([
                'balance' => number_format($afterBalance, 2, '.', ''),
                'id' => $userId,
            ]);

            $pdo->prepare("UPDATE promocode_requests SET status = 'approved', updated_at = NOW() WHERE id = :id")
                ->execute(['id' => $id]);

            try {
                $pdo->prepare(
                    'INSERT INTO admin_balance_adjustments
                    (user_id, username, admin_id, admin_username, wallet, action, amount, before_balance, after_balance, note)
                    VALUES
                    (:user_id, :username, :admin_id, :admin_username, :wallet, :action, :amount, :before_balance, :after_balance, :note)'
                )->execute([
                    'user_id' => $userId,
                    'username' => (string) ($user['username'] ?? ''),
                    'admin_id' => $adminId > 0 ? $adminId : null,
                    'admin_username' => $adminUsername,
                    'wallet' => 'balance',
                    'action' => 'add',
                    'amount' => number_format($amount, 2, '.', ''),
                    'before_balance' => number_format($beforeBalance, 2, '.', ''),
                    'after_balance' => number_format($afterBalance, 2, '.', ''),
                    'note' => 'Promocode onayı: ' . (string) ($request['promocode_code'] ?? ''),
                ]);
            } catch (Throwable) {
                // Balance adjustment log table may be unavailable on partially migrated environments.
            }

            AdminAuth::writeLog($adminUsername, 'promocode_request_approve', 'promocode_requests', 'success', (string) $id);
            $pdo->commit();
            $this->flash('Promocode talebi onaylandı ve bakiye eklendi.');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            AdminAuth::writeLog($adminUsername, 'promocode_request_approve', 'promocode_requests', 'failed', (string) $id);
            $this->flash('Hata: ' . $e->getMessage());
        }

        $this->redirect(AdminAuth::url('/module?key=promocode-requests'));
    }

    public function reject(): void
    {
        $this->requirePermission('promocode-requests');
        $this->ensurePost();

        $id = max(0, (int) ($_POST['id'] ?? 0));
        if ($id <= 0) {
            $this->flash('Geçersiz promo talep ID.');
            $this->redirect(AdminAuth::url('/module?key=promocode-requests'));
        }

        $pdo = AdminDatabase::pdo();
        $stmt = $pdo->prepare("UPDATE promocode_requests SET status = 'rejected', updated_at = NOW() WHERE id = :id AND status = 'pending'");
        $stmt->execute(['id' => $id]);

        if ($stmt->rowCount() > 0) {
            AdminAuth::writeLog(AdminAuth::userName(), 'promocode_request_reject', 'promocode_requests', 'success', (string) $id);
            $this->flash('Promocode talebi reddedildi.');
        } else {
            $this->flash('Talep bulunamadı veya pending değil.');
        }

        $this->redirect(AdminAuth::url('/module?key=promocode-requests'));
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
}
