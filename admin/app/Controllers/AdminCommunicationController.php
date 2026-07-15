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
            'testResult' => (string) ($_SESSION['admin_mail_test'] ?? ''),
            'dbFingerprint' => $this->dbFingerprint(),
        ]);
        unset($_SESSION['admin_flash'], $_SESSION['admin_mail_test']);
    }

    public function testMail(): void
    {
        $this->requirePermission('email');
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }

        $this->ensureMailTables();
        $settings = $this->mailSettingsRow();
        $to = trim((string) ($_POST['test_email'] ?? ''));
        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $to = trim((string) ($settings['from_email'] ?? $settings['mail_from_address'] ?? ''));
        }

        $enabled = (int) ($settings['enabled'] ?? $settings['mail_enabled'] ?? 0) === 1;
        $from = trim((string) ($settings['from_email'] ?? $settings['mail_from_address'] ?? ''));
        if ($from === '') {
            $from = trim((string) ($settings['smtp_user'] ?? ''));
        }

        if (!$enabled) {
            $_SESSION['admin_mail_test'] = 'HATA: Mail gönderimi pasif. Önce "Mail gonderimi aktif" kutusunu işaretleyip kaydedin.';
            $this->redirect(AdminAuth::url('/email/settings'));
        }
        if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $_SESSION['admin_mail_test'] = 'HATA: Test için geçerli bir e-posta adresi girin.';
            $this->redirect(AdminAuth::url('/email/settings'));
        }

        require_once ADMIN_APP_PATH . '/Services/MetropolMailer.php';
        $subject = 'VegasRoyalSpin SMTP Test';
        $body = "Bu bir SMTP test mailidir.\n\nGonderim zamani: " . date('Y-m-d H:i:s') . "\nHost: " . (string) ($settings['smtp_host'] ?? '');
        $error = '';
        $ok = metropol_mail_send($settings, $from, $to, $subject, $body, $error);

        try {
            $stmt = AdminDatabase::pdo()->prepare(
                'INSERT INTO mail_outbound_log (admin_id, to_email, subject, body_preview, status, created_at)
                 VALUES (:admin_id, :to_email, :subject, :body_preview, :status, NOW())'
            );
            $user = AdminAuth::user();
            $stmt->execute([
                'admin_id' => (int) ($user['id'] ?? 0),
                'to_email' => $to,
                'subject' => $subject,
                'body_preview' => $ok ? $body : ('[smtp_error] ' . $error . "\n\n" . $body),
                'status' => $ok ? 'sent' : 'failed',
            ]);
        } catch (Throwable) {
        }

        $_SESSION['admin_mail_test'] = $ok
            ? ('BASARILI: Test maili ' . $to . ' adresine gonderildi. Gelen kutusu/spam kontrol edin. DB: ' . $this->dbFingerprint())
            : ('HATA: Mail gonderilemedi. Sebep => ' . $error . ' | DB: ' . $this->dbFingerprint() . $this->mailErrorHint($error));
        $this->redirect(AdminAuth::url('/email/settings'));
    }

    /** SMTP hata metnine gore Turkce, aksiyon alinabilir ipucu ekler. */
    private function mailErrorHint(string $error): string
    {
        $lower = strtolower($error);
        if (str_contains($lower, 'auth_user_rejected') || str_contains($lower, 'auth_failed') || str_contains($lower, '535')) {
            return "\n\nIPUCU: SMTP kullanici adi/sifre Hostinger tarafindan reddedildi (535 5.7.8)."
                . " hPanel > E-postalar bolumunden: 1) mailbox'in var ve aktif oldugunu, 2) sifreyi resetleyip"
                . " aninda buraya yeniden girdiginizi, 3) SMTP Kullanici alaninin TAM e-posta adresi (orn. noreply@vegasroyalspin.com)"
                . " oldugunu dogrulayin.";
        }
        if (str_contains($lower, 'connect_failed')) {
            return "\n\nIPUCU: Sunucuya baglanti kurulamadi. Hosting saglayicisinin giden SMTP portlarini (465/587) engelleyip engellemedigini kontrol edin.";
        }
        if (str_contains($lower, 'tls_handshake_failed') || str_contains($lower, 'starttls_failed')) {
            return "\n\nIPUCU: TLS baglantisi kurulamadi. Portu (465 SSL / 587 STARTTLS) dogru sectiginizden emin olun.";
        }
        if (str_contains($lower, 'rcpt_rejected')) {
            return "\n\nIPUCU: Alici adresi sunucu tarafindan reddedildi. Alici e-postasini kontrol edin.";
        }
        if (str_contains($lower, 'smtp_host_missing')) {
            return "\n\nIPUCU: SMTP Host alani bos. Ayarlari kaydedip tekrar deneyin.";
        }
        return '';
    }

    /**
     * Kopyala-yapistir sirasinda gelebilecek gorunmez/ozel karakterleri temizler:
     * zero-width space, BOM, non-breaking space, ve normal olmayan bosluk karakterleri.
     */
    private function sanitizeSmtpField(string $value): string
    {
        $value = trim($value);
        // Zero-width space/joiner/non-joiner, BOM
        $value = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $value) ?? $value;
        // Non-breaking space -> normal space, then trim again
        $value = str_replace("\xC2\xA0", ' ', $value);
        // Strip any remaining control characters except normal printable ASCII/UTF-8
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? $value;

        return trim($value);
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
        $smtpHost = $this->sanitizeSmtpField((string) ($_POST['smtp_host'] ?? ''));
        $smtpPort = (int) ($_POST['smtp_port'] ?? 0);
        $smtpUser = $this->sanitizeSmtpField((string) ($_POST['smtp_user'] ?? ''));
        $smtpPasswordInput = $this->sanitizeSmtpField((string) ($_POST['smtp_password'] ?? ''));
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

    /** Non-secret DB fingerprint (host+database only) for admin/frontend DB-parity diagnostics. */
    private function dbFingerprint(): string
    {
        try {
            $pdo = AdminDatabase::pdo();
            $row = $pdo->query('SELECT DATABASE() AS db_name')->fetch();
            $dbName = is_array($row) ? (string) ($row['db_name'] ?? '') : '';
            $dsn = '';
            try {
                $dsn = (string) $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
            } catch (Throwable) {
            }
            return $dbName !== '' ? $dbName : 'bilinmiyor';
        } catch (Throwable $e) {
            return 'hata:' . $e->getMessage();
        }
    }
}
