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
            'settings' => $this->mailSettingsRow(),
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

    public function settings(): void
    {
        $this->requirePermission('email');
        $this->ensureMailTables();
        $this->view('communication/settings', [
            'title' => 'Mail Ayarları',
            'active' => 'email',
            'crumbs' => 'İletişim | E-posta | Ayarlar',
            'settings' => $this->mailSettingsRow(),
            'flash' => (string) ($_SESSION['admin_flash'] ?? ''),
        ]);
        unset($_SESSION['admin_flash']);
    }

    public function saveSettings(): void
    {
        $this->requirePermission('email');
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }

        $this->ensureMailTables();
        $existing = $this->mailSettingsRow();

        $enabled = isset($_POST['enabled']) ? 1 : 0;
        $fromEmail = trim((string) ($_POST['from_email'] ?? ''));
        $smtpHost = trim((string) ($_POST['smtp_host'] ?? ''));
        $smtpPort = (int) ($_POST['smtp_port'] ?? 0);
        $smtpUser = trim((string) ($_POST['smtp_user'] ?? ''));
        $smtpPasswordInput = trim((string) ($_POST['smtp_password'] ?? ''));
        $smtpPassword = $smtpPasswordInput !== ''
            ? $smtpPasswordInput
            : (string) ($existing['smtp_password'] ?? '');

        try {
            $pdo = AdminDatabase::pdo();
            if (is_array($existing) && isset($existing['id'])) {
                $stmt = $pdo->prepare(
                    'UPDATE mail_settings
                     SET enabled = :enabled,
                         mail_enabled = :mail_enabled,
                         from_email = :from_email,
                         mail_from_address = :mail_from_address,
                         smtp_host = :smtp_host,
                         smtp_port = :smtp_port,
                         smtp_user = :smtp_user,
                         smtp_password = :smtp_password,
                         updated_at = NOW()
                     WHERE id = :id'
                );
                $stmt->execute([
                    'id' => (int) $existing['id'],
                    'enabled' => $enabled,
                    'mail_enabled' => $enabled,
                    'from_email' => $fromEmail,
                    'mail_from_address' => $fromEmail,
                    'smtp_host' => $smtpHost,
                    'smtp_port' => $smtpPort > 0 ? $smtpPort : null,
                    'smtp_user' => $smtpUser,
                    'smtp_password' => $smtpPassword,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO mail_settings
                     (enabled, mail_enabled, from_email, mail_from_address, smtp_host, smtp_port, smtp_user, smtp_password, updated_at)
                     VALUES
                     (:enabled, :mail_enabled, :from_email, :mail_from_address, :smtp_host, :smtp_port, :smtp_user, :smtp_password, NOW())'
                );
                $stmt->execute([
                    'enabled' => $enabled,
                    'mail_enabled' => $enabled,
                    'from_email' => $fromEmail,
                    'mail_from_address' => $fromEmail,
                    'smtp_host' => $smtpHost,
                    'smtp_port' => $smtpPort > 0 ? $smtpPort : null,
                    'smtp_user' => $smtpUser,
                    'smtp_password' => $smtpPassword,
                ]);
            }

            $_SESSION['admin_flash'] = 'Mail ayarları güncellendi.';
        } catch (Throwable $exception) {
            $_SESSION['admin_flash'] = 'Mail ayarları kaydedilemedi: ' . $exception->getMessage();
        }

        $this->redirect(AdminAuth::url('/email/settings'));
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

    private function mailSettingsRow(): array
    {
        try {
            $stmt = AdminDatabase::pdo()->query('SELECT * FROM mail_settings ORDER BY id ASC LIMIT 1');
            $row = $stmt->fetch();
            return is_array($row) ? $row : [];
        } catch (Throwable) {
            return [];
        }
    }
}
