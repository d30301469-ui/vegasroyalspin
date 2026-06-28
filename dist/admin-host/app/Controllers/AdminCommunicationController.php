<?php

declare(strict_types=1);

final class AdminCommunicationController extends AdminController
{
    public function email(): void
    {
        $this->requirePermission('email');
        $this->ensureMailTables();
        $this->view('communication/email', [
            'title' => 'E-posta',
            'active' => 'email',
            'crumbs' => 'İletişim | E-posta | Gelen Kutusu',
            'messages' => $this->rows('member_inbox_messages', 'created_at'),
            'mailLogs' => $this->rows('mail_outbound_log', 'created_at'),
            'settings' => $this->first('mail_settings'),
        ]);
    }

    public function compose(): void
    {
        $this->requirePermission('email');
        $this->ensureMailTables();
        $this->view('communication/compose', [
            'title' => 'Mesaj Yaz',
            'active' => 'compose',
            'crumbs' => 'İletişim | Mesaj Yaz',
            'flash' => (string) ($_SESSION['admin_flash'] ?? ''),
        ]);
        unset($_SESSION['admin_flash']);
    }

    public function send(): void
    {
        $this->requirePermission('email');
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }

        $this->ensureMailTables();
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $email = trim((string) ($_POST['to_email'] ?? ''));
        try {
            $stmt = AdminDatabase::pdo()->prepare(
                'INSERT INTO mail_outbound_log (admin_id, to_email, subject, body_preview, status, created_at)
                 VALUES (:admin_id, :to_email, :subject, :body_preview, :status, NOW())'
            );
            $user = AdminAuth::user();
            $stmt->execute([
                'admin_id' => (int) ($user['id'] ?? 0),
                'to_email' => $email,
                'subject' => $subject,
                'body_preview' => substr($body, 0, 500),
                'status' => 'queued',
            ]);
            $_SESSION['admin_flash'] = 'Mesaj gönderim kuyruğuna alındı.';
        } catch (Throwable $exception) {
            $_SESSION['admin_flash'] = 'Mesaj kaydedilemedi: ' . $exception->getMessage();
        }

        $this->redirect(AdminAuth::url('/compose'));
    }

    public function chat(): void
    {
        $this->requirePermission('email');
        $this->view('communication/chat', [
            'title' => 'Canlı Talepler',
            'active' => 'chat',
            'crumbs' => 'İletişim | Canlı Talepler',
            'requests' => $this->rows('call_me_requests', 'created_at'),
            'logs' => $this->rows('admin_logs', 'created_at'),
        ]);
    }

    private function ensureMailTables(): void
    {
        try {
            $migration = ADMIN_BASE_PATH . '/database/migrations/2026_06_10_000001_create_mail_tables.php';
            if (is_file($migration)) {
                (require $migration)(AdminDatabase::pdo());
            }
        } catch (Throwable) {
        }
    }

    private function rows(string $table, string $orderColumn): array
    {
        try {
            $stmt = AdminDatabase::pdo()->query(
                'SELECT * FROM `' . str_replace('`', '``', $table) . '` ORDER BY `' . str_replace('`', '``', $orderColumn) . '` DESC LIMIT 25'
            );

            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private function first(string $table): array
    {
        try {
            $stmt = AdminDatabase::pdo()->query('SELECT * FROM `' . str_replace('`', '``', $table) . '` LIMIT 1');
            $row = $stmt->fetch();

            return is_array($row) ? $row : [];
        } catch (Throwable) {
            return [];
        }
    }
}
