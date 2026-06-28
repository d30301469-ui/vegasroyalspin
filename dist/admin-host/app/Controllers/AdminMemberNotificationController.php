<?php

declare(strict_types=1);

final class AdminMemberNotificationController extends AdminController
{
    public function index(): void
    {
        $this->requirePermission('member-notifications');
        admin_require_project_file('services/MemberNotificationService.php');

        $pdo = AdminDatabase::pdo();
        MemberNotificationService::ensureTables($pdo);

        $userId = max(0, (int) ($_GET['user_id'] ?? 0));
        $items = [];
        if ($userId > 0) {
            $result = MemberNotificationService::listForUser($pdo, $userId, 50);
            $items = $result['items'];
        } else {
            try {
                $stmt = $pdo->query(
                    'SELECT n.*, u.username, u.email
                     FROM member_notifications n
                     LEFT JOIN users u ON u.id = n.user_id
                     ORDER BY n.created_at DESC LIMIT 50'
                );
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Throwable) {
                $items = [];
            }
        }

        $this->view('notifications/index', [
            'title' => 'Üye Bildirimleri',
            'active' => 'member-notifications',
            'crumbs' => 'İletişim | Üye Bildirimleri',
            'notifications' => is_array($items) ? $items : [],
            'userId' => $userId,
            'flash' => $this->pullFlash(),
        ]);
    }

    public function send(): void
    {
        $this->requirePermission('member-notifications');
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }

        admin_require_project_file('services/MemberNotificationService.php');

        $userId = max(0, (int) ($_POST['user_id'] ?? 0));
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $type = trim((string) ($_POST['type'] ?? 'info'));
        $actionUrl = trim((string) ($_POST['action_url'] ?? ''));

        if ($userId <= 0 || $title === '') {
            $_SESSION['admin_flash'] = 'user_id ve title zorunludur.';
            $this->redirect(AdminAuth::url('/notifications'));
        }

        if (!in_array($type, ['info', 'success', 'warning', 'promo', 'support'], true)) {
            $type = 'info';
        }

        try {
            $check = AdminDatabase::pdo()->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
            $check->execute(['id' => $userId]);
            if ((int) $check->fetchColumn() <= 0) {
                throw new RuntimeException('Üye bulunamadı.');
            }

            MemberNotificationService::create(
                AdminDatabase::pdo(),
                $userId,
                $title,
                $body,
                $type,
                $actionUrl !== '' ? $actionUrl : null
            );
            $_SESSION['admin_flash'] = 'Bildirim gönderildi.';
        } catch (Throwable $exception) {
            $_SESSION['admin_flash'] = 'Bildirim gönderilemedi: ' . $exception->getMessage();
        }

        $this->redirect(AdminAuth::url('/notifications?user_id=' . $userId));
    }

    private function pullFlash(): string
    {
        $message = (string) ($_SESSION['admin_flash'] ?? '');
        unset($_SESSION['admin_flash']);

        return $message;
    }
}
