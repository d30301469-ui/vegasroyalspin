<?php

declare(strict_types=1);

final class AdminKycController extends AdminController
{
    public function review(): void
    {
        $this->requirePermission('kyc');
        admin_require_project_file('services/MemberKycService.php');
        MemberKycService::ensureTables(AdminDatabase::pdo());

        $requestId = max(0, (int) ($_GET['id'] ?? 0));
        $pdo = AdminDatabase::pdo();
        $pending = $this->pendingRequests($pdo);
        $selected = null;

        if ($requestId > 0) {
            $stmt = $pdo->prepare(
                'SELECT k.*, u.username, u.email, u.name, u.surname, u.is_verified
                 FROM kyc_requests k
                 LEFT JOIN users u ON u.id = k.user_id
                 WHERE k.id = :id LIMIT 1'
            );
            $stmt->execute(['id' => $requestId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $selected = is_array($row) ? $row : null;
        }

        $this->view('kyc/review', [
            'title' => 'KYC İnceleme',
            'active' => 'kyc-review',
            'moduleKey' => 'kyc-review',
            'crumbs' => 'Üyeler | KYC İnceleme',
            'pending' => $pending,
            'selected' => $selected,
            'flash' => $this->pullFlash(),
        ]);
    }

    public function approve(): void
    {
        $this->requirePermission('kyc');
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }

        $id = max(0, (int) ($_POST['id'] ?? 0));
        if ($id <= 0) {
            $_SESSION['admin_flash'] = 'Geçersiz KYC kaydı.';
            $this->redirect(AdminAuth::url('/kyc/review'));
        }

        $pdo = AdminDatabase::pdo();
        $stmt = $pdo->prepare('SELECT user_id FROM kyc_requests WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $userId = (int) $stmt->fetchColumn();
        if ($userId <= 0) {
            $_SESSION['admin_flash'] = 'KYC kaydı bulunamadı.';
            $this->redirect(AdminAuth::url('/kyc/review'));
        }

        $admin = AdminAuth::userName();
        $pdo->prepare(
            'UPDATE kyc_requests SET status = :status, reviewed_by = :reviewed_by, reviewed_at = NOW() WHERE id = :id'
        )->execute(['status' => 'approved', 'reviewed_by' => $admin, 'id' => $id]);
        $pdo->prepare('UPDATE users SET is_verified = 1 WHERE id = :id')->execute(['id' => $userId]);

        admin_require_project_file('services/MemberNotificationService.php');
        MemberNotificationService::create($pdo, $userId, 'KYC onaylandı', 'Kimlik doğrulama talebiniz onaylandı.', 'success');

        $_SESSION['admin_flash'] = 'KYC talebi onaylandı.';
        $this->redirect(AdminAuth::url('/kyc/review?id=' . $id));
    }

    public function reject(): void
    {
        $this->requirePermission('kyc');
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }

        $id = max(0, (int) ($_POST['id'] ?? 0));
        $note = trim((string) ($_POST['note'] ?? ''));
        if ($id <= 0) {
            $_SESSION['admin_flash'] = 'Geçersiz KYC kaydı.';
            $this->redirect(AdminAuth::url('/kyc/review'));
        }

        $pdo = AdminDatabase::pdo();
        $stmt = $pdo->prepare('SELECT user_id FROM kyc_requests WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $userId = (int) $stmt->fetchColumn();

        $admin = AdminAuth::userName();
        $update = $pdo->prepare(
            'UPDATE kyc_requests SET status = :status, note = :note, reviewed_by = :reviewed_by, reviewed_at = NOW() WHERE id = :id'
        );
        $update->execute([
            'status' => 'rejected',
            'note' => $note !== '' ? $note : null,
            'reviewed_by' => $admin,
            'id' => $id,
        ]);

        if ($userId > 0) {
            admin_require_project_file('services/MemberNotificationService.php');
            MemberNotificationService::create(
                $pdo,
                $userId,
                'KYC reddedildi',
                $note !== '' ? $note : 'Kimlik doğrulama talebiniz reddedildi.',
                'warning'
            );
        }

        $_SESSION['admin_flash'] = 'KYC talebi reddedildi.';
        $this->redirect(AdminAuth::url('/kyc/review?id=' . $id));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function pendingRequests(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query(
                "SELECT k.id, k.user_id, k.username, k.document_type, k.status, k.submitted_at, u.email
                 FROM kyc_requests k
                 LEFT JOIN users u ON u.id = k.user_id
                 WHERE k.status = 'pending'
                 ORDER BY k.submitted_at ASC
                 LIMIT 100"
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function pullFlash(): string
    {
        $message = (string) ($_SESSION['admin_flash'] ?? '');
        unset($_SESSION['admin_flash']);

        return $message;
    }
}
