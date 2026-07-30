<?php

declare(strict_types=1);

final class AdminCommunicationController extends AdminController
{
    public function email(): void
    {
        $this->requirePermission('email');
        $this->redirect(AdminAuth::url('/email/inbox'));
    }

    /**
     * Sayfa iskeletini IMAP'a hic dokunmadan dondurur; mesaj listesi
     * /email/inbox/list ucundan asenkron yuklenir. Boylece erisilemeyen bir
     * IMAP sunucusu e-posta menusunu tamamen kapatmaz (Apache 503).
     */
    public function inbox(): void
    {
        $this->requirePermission('email');
        $this->ensureMailTables();
        $settings = $this->mailSettingsRow();
        require_once ADMIN_APP_PATH . '/Services/MetropolMailInbox.php';
        $mailbox = trim((string) ($settings['imap_user'] ?? $settings['smtp_user'] ?? $settings['from_email'] ?? $settings['mail_from_address'] ?? ''));

        $this->view('communication/email', [
            'title' => 'Gelen e-postalar',
            'active' => 'email',
            'crumbs' => 'E-posta | Gelen e-postalar',
            'emailSection' => 'inbox',
            'mailbox' => $mailbox,
            'imapConfigured' => metropol_mail_imap_configured($settings),
            'inboxListUrl' => AdminAuth::url('/email/inbox/list'),
        ]);
    }

    public function inboxList(): void
    {
        $this->requirePermission('email');
        $settings = $this->mailSettingsRow();
        require_once ADMIN_APP_PATH . '/Services/MetropolMailInbox.php';
        @set_time_limit(45);
        $inbox = metropol_mail_fetch_inbox($settings, 25);

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store');
        }
        $this->partial('communication/_inbox_list', [
            'inboxOk' => !empty($inbox['ok']),
            'inboxError' => (string) ($inbox['error'] ?? ''),
            'messages' => is_array($inbox['messages'] ?? null) ? $inbox['messages'] : [],
        ]);
    }

    public function inboxView(): void
    {
        $this->requirePermission('email');
        $this->ensureMailTables();
        $uid = (int) ($_GET['uid'] ?? 0);
        $settings = $this->mailSettingsRow();
        require_once ADMIN_APP_PATH . '/Services/MetropolMailInbox.php';
        $result = metropol_mail_fetch_message($settings, $uid);
        $data = [
            'title' => 'E-posta oku',
            'active' => 'email',
            'crumbs' => 'E-posta | Gelen e-postalar | Oku',
            'emailSection' => 'inbox',
            'messageOk' => !empty($result['ok']),
            'messageError' => (string) ($result['error'] ?? ''),
            'message' => is_array($result['message'] ?? null) ? $result['message'] : [],
        ];

        $isModal = (string) ($_GET['modal'] ?? '') === '1'
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        if ($isModal) {
            $this->partial('communication/_email_read', $data);
            return;
        }

        $this->view('communication/email_view', $data);
    }

    public function sent(): void
    {
        $this->requirePermission('email');
        $this->ensureMailTables();
        $this->view('communication/sent', [
            'title' => 'Gönderilen e-posta',
            'active' => 'email',
            'crumbs' => 'E-posta | Gönderilen e-posta',
            'emailSection' => 'sent',
            'mailLogs' => $this->rows('mail_outbound_log', 'created_at'),
        ]);
    }

    public function compose(): void
    {
        $this->requirePermission('email');
        $this->ensureMailTables();
        $memberEmails = $this->normalizeRecipientEmails($this->memberRecipientEmails());
        $this->view('communication/compose', [
            'title' => 'E-posta gönder',
            'active' => 'email',
            'crumbs' => 'E-posta | E-posta gönder',
            'emailSection' => 'send',
            'flash' => (string) ($_SESSION['admin_flash'] ?? ''),
            'memberEmailCount' => count($memberEmails),
            'customTemplates' => array_values(array_filter(
                $this->customMailTemplates(),
                static fn (array $template): bool => (int) ($template['is_active'] ?? 0) === 1
            )),
        ]);
        unset($_SESSION['admin_flash']);
    }

    public function settings(): void
    {
        $this->requirePermission('email');
        $this->ensureMailTables();
        $this->view('communication/settings', [
            'title' => 'E-posta ayarları',
            'active' => 'email',
            'crumbs' => 'E-posta | Ayarlar',
            'emailSection' => 'settings',
            'settings' => $this->mailSettingsRow(),
            'flash' => (string) ($_SESSION['admin_flash'] ?? ''),
            'testResult' => (string) ($_SESSION['admin_mail_test'] ?? ''),
            'dbFingerprint' => $this->dbFingerprint(),
        ]);
        unset($_SESSION['admin_flash'], $_SESSION['admin_mail_test']);
    }

    public function templates(): void
    {
        $this->requirePermission('email');
        $this->ensureMailTables();
        $settings = $this->mailSettingsRow();
        require_once ADMIN_APP_PATH . '/Services/MetropolMailer.php';
        $this->view('communication/templates', [
            'title' => 'E-posta şablonları',
            'active' => 'email',
            'crumbs' => 'E-posta | E-posta şablonları',
            'emailSection' => 'templates',
            'settings' => $settings,
            'resetPreviewHtml' => $this->renderMailTemplatePreview('reset', $settings),
            'welcomePreviewHtml' => $this->renderMailTemplatePreview('welcome', $settings),
            'depositApprovedPreviewHtml' => $this->renderMailTemplatePreview('deposit_approved', $settings),
            'withdrawApprovedPreviewHtml' => $this->renderMailTemplatePreview('withdraw_approved', $settings),
            'customTemplates' => $this->customMailTemplates(),
            'previewUrl' => AdminAuth::url('/email/templates/preview'),
            'customSaveUrl' => AdminAuth::url('/email/templates/custom'),
            'customDeleteUrl' => AdminAuth::url('/email/templates/custom/delete'),
            'flash' => (string) ($_SESSION['admin_flash'] ?? ''),
        ]);
        unset($_SESSION['admin_flash']);
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
        $siteUrl = $this->frontendSiteUrl();
        $templateOptions = $this->mailTemplateOptions($settings);
        $htmlBody = metropol_mail_render_template(
            $siteUrl,
            'SMTP test mesaji basariyla iletildi',
            'SMTP Test Mesaji',
            '<p style="margin:0 0 12px 0;">Bu, mail ayarlarinizin dogru calistigini onaylamak icin gonderilen bir test mesajidir.</p>'
                . '<p style="margin:0;color:#4a5568;font-size:15px;">Gonderim zamani: ' . htmlspecialchars(date('Y-m-d H:i:s'), ENT_QUOTES, 'UTF-8') . '<br>Host: ' . htmlspecialchars((string) ($settings['smtp_host'] ?? ''), ENT_QUOTES, 'UTF-8') . '</p>',
            'SMTP Test Linki',
            $siteUrl !== '' ? ($siteUrl . '/login') : 'https://vegasroyalspin.com/login',
            $templateOptions
        );
        $error = '';
        $ok = metropol_mail_send($settings, $from, $to, $subject, $body, $error, $htmlBody);

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

    /** Mail sablonundaki logo/CTA linkleri icin frontend site adresini cozer. */
    private function frontendSiteUrl(): string
    {
        if (function_exists('deploy_domain')) {
            $url = trim((string) deploy_domain('frontend_url'));
            if ($url !== '') {
                return rtrim($url, '/');
            }
        }
        $env = trim((string) (getenv('FRONTEND_URL') ?: getenv('SITE_URL') ?: ''));
        return $env !== '' ? rtrim($env, '/') : 'https://vegasroyalspin.com';
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

        $imapEnabled = isset($_POST['imap_enabled']) ? 1 : 0;
        $imapHost = $this->sanitizeSmtpField((string) ($_POST['imap_host'] ?? ''));
        $imapPort = (int) ($_POST['imap_port'] ?? 0);
        $imapUser = $this->sanitizeSmtpField((string) ($_POST['imap_user'] ?? ''));
        $imapPasswordInput = $this->sanitizeSmtpField((string) ($_POST['imap_password'] ?? ''));
        $imapPassword = $imapPasswordInput !== ''
            ? $imapPasswordInput
            : (string) ($existing['imap_password'] ?? '');
        $imapEncryption = strtolower(trim((string) ($_POST['imap_encryption'] ?? 'ssl')));
        if (!in_array($imapEncryption, ['ssl', 'tls', 'none'], true)) {
            $imapEncryption = 'ssl';
        }

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
                         imap_enabled = :imap_enabled,
                         imap_host = :imap_host,
                         imap_port = :imap_port,
                         imap_user = :imap_user,
                         imap_password = :imap_password,
                         imap_encryption = :imap_encryption,
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
                    'imap_enabled' => $imapEnabled,
                    'imap_host' => $imapHost,
                    'imap_port' => $imapPort > 0 ? $imapPort : 993,
                    'imap_user' => $imapUser,
                    'imap_password' => $imapPassword,
                    'imap_encryption' => $imapEncryption,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO mail_settings
                     (enabled, mail_enabled, from_email, mail_from_address, smtp_host, smtp_port, smtp_user, smtp_password, imap_enabled, imap_host, imap_port, imap_user, imap_password, imap_encryption, company_name, support_email, company_address, reset_template_html, welcome_template_html, deposit_approved_template_html, withdraw_approved_template_html, updated_at)
                     VALUES
                     (:enabled, :mail_enabled, :from_email, :mail_from_address, :smtp_host, :smtp_port, :smtp_user, :smtp_password, :imap_enabled, :imap_host, :imap_port, :imap_user, :imap_password, :imap_encryption, :company_name, :support_email, :company_address, :reset_template_html, :welcome_template_html, :deposit_approved_template_html, :withdraw_approved_template_html, NOW())'
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
                    'imap_enabled' => $imapEnabled,
                    'imap_host' => $imapHost,
                    'imap_port' => $imapPort > 0 ? $imapPort : 993,
                    'imap_user' => $imapUser,
                    'imap_password' => $imapPassword,
                    'imap_encryption' => $imapEncryption,
                    'company_name' => 'Vegasroyalspin',
                    'support_email' => '',
                    'company_address' => '',
                    'reset_template_html' => '',
                    'welcome_template_html' => '',
                    'deposit_approved_template_html' => '',
                    'withdraw_approved_template_html' => '',
                ]);
            }

            $_SESSION['admin_flash'] = 'E-posta ayarları güncellendi.';
        } catch (Throwable $exception) {
            $_SESSION['admin_flash'] = 'Mail ayarları kaydedilemedi: ' . $exception->getMessage();
        }

        $this->redirect(AdminAuth::url('/email/settings'));
    }

    public function saveTemplates(): void
    {
        $this->requirePermission('email');
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }

        $this->ensureMailTables();
        $existing = $this->mailSettingsRow();
        $companyName = trim((string) ($_POST['company_name'] ?? ''));
        $supportEmail = trim((string) ($_POST['support_email'] ?? ''));
        $companyAddress = trim((string) ($_POST['company_address'] ?? ''));
        $resetTemplateHtml = (string) ($_POST['reset_template_html'] ?? '');
        $welcomeTemplateHtml = (string) ($_POST['welcome_template_html'] ?? '');
        $depositApprovedTemplateHtml = (string) ($_POST['deposit_approved_template_html'] ?? '');
        $withdrawApprovedTemplateHtml = (string) ($_POST['withdraw_approved_template_html'] ?? '');

        try {
            $pdo = AdminDatabase::pdo();
            if (is_array($existing) && isset($existing['id'])) {
                $stmt = $pdo->prepare(
                    'UPDATE mail_settings
                     SET company_name = :company_name,
                         support_email = :support_email,
                         company_address = :company_address,
                         reset_template_html = :reset_template_html,
                         welcome_template_html = :welcome_template_html,
                         deposit_approved_template_html = :deposit_approved_template_html,
                         withdraw_approved_template_html = :withdraw_approved_template_html,
                         updated_at = NOW()
                     WHERE id = :id'
                );
                $stmt->execute([
                    'id' => (int) $existing['id'],
                    'company_name' => $companyName,
                    'support_email' => $supportEmail,
                    'company_address' => $companyAddress,
                    'reset_template_html' => $resetTemplateHtml,
                    'welcome_template_html' => $welcomeTemplateHtml,
                    'deposit_approved_template_html' => $depositApprovedTemplateHtml,
                    'withdraw_approved_template_html' => $withdrawApprovedTemplateHtml,
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO mail_settings
                     (enabled, mail_enabled, from_email, mail_from_address, smtp_host, smtp_port, smtp_user, smtp_password, company_name, support_email, company_address, reset_template_html, welcome_template_html, deposit_approved_template_html, withdraw_approved_template_html, updated_at)
                     VALUES
                     (0, 0, NULL, NULL, NULL, NULL, NULL, NULL, :company_name, :support_email, :company_address, :reset_template_html, :welcome_template_html, :deposit_approved_template_html, :withdraw_approved_template_html, NOW())'
                );
                $stmt->execute([
                    'company_name' => $companyName,
                    'support_email' => $supportEmail,
                    'company_address' => $companyAddress,
                    'reset_template_html' => $resetTemplateHtml,
                    'welcome_template_html' => $welcomeTemplateHtml,
                    'deposit_approved_template_html' => $depositApprovedTemplateHtml,
                    'withdraw_approved_template_html' => $withdrawApprovedTemplateHtml,
                ]);
            }
            $_SESSION['admin_flash'] = 'E-posta şablonları güncellendi.';
        } catch (Throwable $exception) {
            $_SESSION['admin_flash'] = 'Şablonlar kaydedilemedi: ' . $exception->getMessage();
        }

        $this->redirect(AdminAuth::url('/email/templates'));
    }

    public function saveCustomTemplate(): void
    {
        $this->requirePermission('email');
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }

        $this->ensureMailTables();
        $id = max(0, (int) ($_POST['custom_template_id'] ?? 0));
        $name = trim((string) ($_POST['custom_name'] ?? ''));
        $subject = trim((string) ($_POST['custom_subject'] ?? ''));
        $templateHtml = trim((string) ($_POST['custom_template_html'] ?? ''));
        $isActive = isset($_POST['custom_is_active']) ? 1 : 0;

        if ($name === '' || $subject === '') {
            $_SESSION['admin_flash'] = 'Şablon kaydedilemedi: ad ve e-posta konusu zorunludur.';
            $this->redirect(AdminAuth::url('/email/templates'));
        }

        try {
            $pdo = AdminDatabase::pdo();
            if ($id > 0) {
                $stmt = $pdo->prepare(
                    'UPDATE mail_custom_templates
                     SET name = :name, subject = :subject, template_html = :template_html,
                         is_active = :is_active, updated_at = NOW()
                     WHERE id = :id'
                );
                $stmt->execute([
                    'id' => $id,
                    'name' => $name,
                    'subject' => $subject,
                    'template_html' => $templateHtml,
                    'is_active' => $isActive,
                ]);
                $_SESSION['admin_flash'] = 'Özel e-posta şablonu güncellendi.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO mail_custom_templates
                     (name, subject, template_html, is_active, created_at, updated_at)
                     VALUES (:name, :subject, :template_html, :is_active, NOW(), NOW())'
                );
                $stmt->execute([
                    'name' => $name,
                    'subject' => $subject,
                    'template_html' => $templateHtml,
                    'is_active' => $isActive,
                ]);
                $_SESSION['admin_flash'] = 'Yeni e-posta şablonu eklendi.';
            }
        } catch (Throwable $exception) {
            $_SESSION['admin_flash'] = 'Şablon kaydedilemedi: ' . $exception->getMessage();
        }

        $this->redirect(AdminAuth::url('/email/templates'));
    }

    public function deleteCustomTemplate(): void
    {
        $this->requirePermission('email');
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }

        $this->ensureMailTables();
        $id = max(0, (int) ($_POST['custom_template_id'] ?? 0));
        try {
            if ($id > 0) {
                $stmt = AdminDatabase::pdo()->prepare('DELETE FROM mail_custom_templates WHERE id = :id');
                $stmt->execute(['id' => $id]);
            }
            $_SESSION['admin_flash'] = 'Özel e-posta şablonu silindi.';
        } catch (Throwable $exception) {
            $_SESSION['admin_flash'] = 'Şablon silinemedi: ' . $exception->getMessage();
        }

        $this->redirect(AdminAuth::url('/email/templates'));
    }

    public function previewTemplate(): void
    {
        $this->requirePermission('email');
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }

        $this->ensureMailTables();
        $type = strtolower(trim((string) ($_POST['template_type'] ?? 'reset')));
        if (!in_array($type, ['reset', 'welcome', 'deposit_approved', 'withdraw_approved', 'custom'], true)) {
            $type = 'reset';
        }

        $settings = $this->mailSettingsRow();
        $settings['company_name'] = trim((string) ($_POST['company_name'] ?? ($settings['company_name'] ?? '')));
        $settings['support_email'] = trim((string) ($_POST['support_email'] ?? ($settings['support_email'] ?? '')));
        $settings['company_address'] = trim((string) ($_POST['company_address'] ?? ($settings['company_address'] ?? '')));
        $settings['reset_template_html'] = (string) ($_POST['reset_template_html'] ?? ($settings['reset_template_html'] ?? ''));
        $settings['welcome_template_html'] = (string) ($_POST['welcome_template_html'] ?? ($settings['welcome_template_html'] ?? ''));
        $settings['deposit_approved_template_html'] = (string) ($_POST['deposit_approved_template_html'] ?? ($settings['deposit_approved_template_html'] ?? ''));
        $settings['withdraw_approved_template_html'] = (string) ($_POST['withdraw_approved_template_html'] ?? ($settings['withdraw_approved_template_html'] ?? ''));
        if ($type === 'custom') {
            $settings['custom_name'] = trim((string) ($_POST['custom_name'] ?? 'Özel şablon'));
            $settings['custom_subject'] = trim((string) ($_POST['custom_subject'] ?? 'E-posta konusu'));
            $settings['custom_template_html'] = (string) ($_POST['custom_template_html'] ?? '');
        }

        require_once ADMIN_APP_PATH . '/Services/MetropolMailer.php';
        header('Content-Type: text/html; charset=UTF-8');
        echo $this->renderMailTemplatePreview($type, $settings);
        exit;
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
        $customTemplateId = max(0, (int) ($_POST['custom_template_id'] ?? 0));
        $customTemplate = null;
        if ($customTemplateId > 0) {
            $customTemplate = $this->customMailTemplateById($customTemplateId, true);
            if ($customTemplate === null) {
                $_SESSION['admin_flash'] = 'Mesaj gönderilemedi: seçilen şablon bulunamadı veya pasif.';
                $this->redirect(AdminAuth::url('/email/send'));
            }
            $templateSubject = trim((string) ($customTemplate['subject'] ?? ''));
            if ($templateSubject !== '') {
                $subject = $templateSubject;
            }
        }
        $mode = strtolower(trim((string) ($_POST['send_mode'] ?? 'single')));
        if ($mode !== 'bulk') {
            $mode = 'single';
        }

        if ($subject === '' || $body === '') {
            $_SESSION['admin_flash'] = 'Mesaj gönderilemedi: konu ve mesaj zorunludur.';
            $this->redirect(AdminAuth::url('/email/send'));
        }

        // Tek mail: yalnızca 1 adres. Toplu: yalnızca veritabanındaki tüm üyeler.
        /** @var list<array{email:string,name:string,surname:string,full_name:string}> $recipients */
        $recipients = [];
        if ($mode === 'bulk') {
            $recipients = $this->memberRecipients();
            if ($recipients === []) {
                $_SESSION['admin_flash'] = 'Mesaj gönderilemedi: veritabanında geçerli e-postası olan kullanıcı bulunamadı.';
                $this->redirect(AdminAuth::url('/email/send'));
            }
            @set_time_limit(0);
            @ini_set('max_execution_time', '0');
        } else {
            $single = strtolower(trim((string) ($_POST['to_email'] ?? '')));
            if ($single === '' || filter_var($single, FILTER_VALIDATE_EMAIL) === false) {
                $_SESSION['admin_flash'] = 'Mesaj gönderilemedi: geçerli bir üye e-postası girin.';
                $this->redirect(AdminAuth::url('/email/send'));
            }
            $recipients = [$this->resolveRecipientProfile($single)];
        }

        $settings = $this->mailSettingsRow();
        $enabled = (int) ($settings['enabled'] ?? $settings['mail_enabled'] ?? 0) === 1;
        $from = trim((string) ($settings['from_email'] ?? $settings['mail_from_address'] ?? ''));
        if ($from === '') {
            $from = trim((string) ($settings['smtp_user'] ?? ''));
        }

        if ($enabled) {
            require_once ADMIN_APP_PATH . '/Services/MetropolMailer.php';
        }

        $siteUrl = $this->frontendSiteUrl();
        $templateOptionsBase = $this->mailTemplateOptions($settings);
        if (is_array($customTemplate)) {
            $templateOptionsBase['template_html'] = trim((string) ($customTemplate['template_html'] ?? ''));
        }
        $adminUser = AdminAuth::user();
        $adminId = (int) ($adminUser['id'] ?? 0);
        $sentCount = 0;
        $failedCount = 0;
        $lastError = '';
        $failedSamples = [];

        foreach ($recipients as $recipient) {
            $email = (string) ($recipient['email'] ?? '');
            $fullName = trim((string) ($recipient['full_name'] ?? ''));
            $firstName = trim((string) ($recipient['name'] ?? ''));
            $lastName = trim((string) ($recipient['surname'] ?? ''));
            if ($email === '') {
                continue;
            }

            $personalizedBody = $this->personalizeMailBody($body, $firstName, $lastName, $fullName);
            $this->writeMemberInboxMessage($email, $subject, $personalizedBody);

            $ok = false;
            $error = '';
            $htmlBody = '';
            if (!$enabled) {
                $error = 'mail_disabled';
            } else {
                $bodyHtmlEscaped = nl2br(htmlspecialchars($personalizedBody, ENT_QUOTES, 'UTF-8'));
                $templateOptions = $templateOptionsBase;
                $templateOptions['member_name'] = $fullName !== '' ? $fullName : 'Üye';
                $htmlBody = metropol_mail_render_template(
                    $siteUrl,
                    $subject !== '' ? $subject : 'VegasRoyalSpin bildirimi',
                    $subject !== '' ? $subject : 'Bildirim',
                    '<p style="margin:0;">' . $bodyHtmlEscaped . '</p>',
                    'Mesaji Gor',
                    $siteUrl !== '' ? $siteUrl : 'https://vegasroyalspin.com',
                    $templateOptions
                );
                $ok = metropol_mail_send(
                    $settings,
                    $from,
                    $email,
                    $subject,
                    $personalizedBody,
                    $error,
                    $htmlBody,
                    $fullName
                );
            }

            if ($ok) {
                $sentCount++;
            } else {
                $failedCount++;
                $lastError = $error !== '' ? $error : 'send_failed';
                if (count($failedSamples) < 5) {
                    $label = $fullName !== '' ? ($fullName . ' <' . $email . '>') : $email;
                    $failedSamples[] = $label . ' (' . $lastError . ')';
                }
            }

            try {
                $stmt = AdminDatabase::pdo()->prepare(
                    'INSERT INTO mail_outbound_log (admin_id, to_email, subject, body_preview, status, created_at)
                     VALUES (:admin_id, :to_email, :subject, :body_preview, :status, NOW())'
                );
                $preview = $ok ? $personalizedBody : ('[smtp_error] ' . ($error !== '' ? $error : 'send_failed') . "\n\n" . $personalizedBody);
                $logTo = $fullName !== '' ? ($fullName . ' <' . $email . '>') : $email;
                $stmt->execute([
                    'admin_id' => $adminId,
                    'to_email' => substr($logTo, 0, 190),
                    'subject' => $subject,
                    'body_preview' => substr($preview, 0, 500),
                    'status' => $ok ? 'sent' : ($enabled ? 'failed' : 'not_configured'),
                ]);
            } catch (Throwable) {
            }
        }

        if (count($recipients) === 1) {
            $_SESSION['admin_flash'] = $sentCount === 1
                ? 'Mesaj gönderildi.'
                : ('Mesaj gönderilemedi: ' . ($lastError !== '' ? $lastError : 'mail gönderimi pasif') . $this->mailErrorHint($lastError));
        } else {
            $lines = [
                'Toplu gönderim özeti: ' . $sentCount . ' başarılı, ' . $failedCount . ' hatalı (toplam ' . count($recipients) . ').',
            ];
            if ($failedSamples !== []) {
                $lines[] = 'Örnek hatalar: ' . implode('; ', $failedSamples);
                $lines[] = trim($this->mailErrorHint($lastError));
            }
            $_SESSION['admin_flash'] = trim(implode("\n", array_filter($lines)));
        }

        $this->redirect(AdminAuth::url('/email/send'));
    }

    /** @return list<string> */
    private function parseRecipientEmails(string $raw): array
    {
        $parts = preg_split('/[\s,;]+/u', $raw) ?: [];
        return $this->normalizeRecipientEmails($parts);
    }

    /**
     * @param list<string>|array<int, string> $emails
     * @return list<string>
     */
    private function normalizeRecipientEmails(array $emails): array
    {
        $out = [];
        foreach ($emails as $email) {
            $email = strtolower(trim((string) $email));
            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }
            $out[$email] = $email;
        }

        return array_values($out);
    }

    /** @return list<string> */
    private function memberRecipientEmails(): array
    {
        $out = [];
        foreach ($this->memberRecipients() as $recipient) {
            $out[] = (string) ($recipient['email'] ?? '');
        }

        return array_values(array_filter($out, static fn (string $email): bool => $email !== ''));
    }

    /**
     * @return list<array{email:string,name:string,surname:string,full_name:string}>
     */
    private function memberRecipients(): array
    {
        try {
            $stmt = AdminDatabase::pdo()->query(
                'SELECT email, name, surname FROM users
                 WHERE email IS NOT NULL AND TRIM(email) <> \'\'
                 ORDER BY id ASC
                 LIMIT 5000'
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!is_array($rows)) {
                return [];
            }

            $out = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $email = strtolower(trim((string) ($row['email'] ?? '')));
                if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                    continue;
                }
                $name = trim((string) ($row['name'] ?? ''));
                $surname = trim((string) ($row['surname'] ?? ''));
                $fullName = trim($name . ' ' . $surname);
                $out[$email] = [
                    'email' => $email,
                    'name' => $name,
                    'surname' => $surname,
                    'full_name' => $fullName,
                ];
            }

            return array_values($out);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array{email:string,name:string,surname:string,full_name:string}
     */
    private function resolveRecipientProfile(string $email): array
    {
        $email = strtolower(trim($email));
        $profile = [
            'email' => $email,
            'name' => '',
            'surname' => '',
            'full_name' => '',
        ];
        try {
            $stmt = AdminDatabase::pdo()->prepare(
                'SELECT name, surname FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1'
            );
            $stmt->execute(['email' => $email]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($row)) {
                $name = trim((string) ($row['name'] ?? ''));
                $surname = trim((string) ($row['surname'] ?? ''));
                $profile['name'] = $name;
                $profile['surname'] = $surname;
                $profile['full_name'] = trim($name . ' ' . $surname);
            }
        } catch (Throwable) {
        }

        return $profile;
    }

    private function personalizeMailBody(string $body, string $name, string $surname, string $fullName): string
    {
        $displayName = $fullName !== '' ? $fullName : 'Üye';
        $hasToken = preg_match('/\{\{\s*(MEMBER_NAME|NAME|SURNAME|ISIM|SOYISIM)\s*\}\}/u', $body) === 1;
        $replaced = strtr($body, [
            '{{MEMBER_NAME}}' => $displayName,
            '{{NAME}}' => $name,
            '{{SURNAME}}' => $surname,
            '{{ISIM}}' => $name,
            '{{SOYISIM}}' => $surname,
        ]);

        if ($hasToken) {
            return $replaced;
        }

        if ($fullName === '') {
            return $replaced;
        }

        return 'Merhaba ' . $fullName . ",\n\n" . $replaced;
    }

    private function writeMemberInboxMessage(string $email, string $subject, string $body): void
    {
        try {
            $pdo = AdminDatabase::pdo();
            $userStmt = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
            $userStmt->execute(['email' => $email]);
            $resolvedUserId = (int) $userStmt->fetchColumn();
            if ($resolvedUserId <= 0) {
                return;
            }

            $inboxStmt = $pdo->prepare(
                'INSERT INTO member_inbox_messages (user_id, title, body, link_url, priority, is_active, starts_at, ends_at, created_at, updated_at)
                 VALUES (:user_id, :title, :body, :link_url, :priority, :is_active, NULL, NULL, NOW(), NOW())'
            );
            $inboxStmt->execute([
                'user_id' => $resolvedUserId,
                'title' => $subject !== '' ? $subject : 'Yeni mesaj',
                'body' => $body,
                'link_url' => null,
                'priority' => 0,
                'is_active' => 1,
            ]);
        } catch (Throwable) {
            // Inbox insert başarısız olsa da mail akışı devam etsin.
        }
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

    /** @return list<array<string,mixed>> */
    private function customMailTemplates(): array
    {
        try {
            $stmt = AdminDatabase::pdo()->query(
                'SELECT id, name, subject, template_html, is_active, created_at, updated_at
                 FROM mail_custom_templates ORDER BY id DESC'
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<string,mixed>|null */
    private function customMailTemplateById(int $id, bool $activeOnly = false): ?array
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $sql = 'SELECT id, name, subject, template_html, is_active, created_at, updated_at
                    FROM mail_custom_templates WHERE id = :id';
            if ($activeOnly) {
                $sql .= ' AND is_active = 1';
            }
            $sql .= ' LIMIT 1';
            $stmt = AdminDatabase::pdo()->prepare($sql);
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string,mixed> $settings */
    private function mailTemplateOptions(array $settings): array
    {
        $siteUrl = $this->frontendSiteUrl();
        $companyName = trim((string) ($settings['company_name'] ?? ''));
        if ($companyName === '') {
            $companyName = 'Vegasroyalspin';
        }
        $logoUrl = $siteUrl !== '' ? ($siteUrl . '/assets/images/favicons/apple-touch-icon.png') : '';
        try {
            $row = AdminDatabase::pdo()->query('SELECT favicon_url FROM site_ayarlar ORDER BY id ASC LIMIT 1')->fetch();
            if (is_array($row)) {
                $favicon = trim((string) ($row['favicon_url'] ?? ''));
                if ($favicon !== '') {
                    $logoUrl = preg_match('#^https?://#i', $favicon) === 1
                        ? $favicon
                        : ($siteUrl . (str_starts_with($favicon, '/') ? $favicon : '/' . $favicon));
                }
            }
        } catch (Throwable) {
        }

        return [
            'template_html' => (string) ($settings['reset_template_html'] ?? ''),
            'company_name' => $companyName,
            'support_email' => (string) ($settings['support_email'] ?? ''),
            'company_address' => (string) ($settings['company_address'] ?? ''),
            'logo_url' => $logoUrl,
        ];
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function renderMailTemplatePreview(string $type, array $settings): string
    {
        if (!function_exists('metropol_mail_render_template')) {
            return '<!DOCTYPE html><html lang="tr"><body style="font-family:Arial,sans-serif;padding:24px;color:#111;">Önizleme motoru yüklenemedi.</body></html>';
        }

        $siteUrl = $this->frontendSiteUrl();
        $options = $this->mailTemplateOptions($settings);
        $companyName = (string) ($options['company_name'] ?? 'Vegasroyalspin');
        $safeCompany = htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8');
        $options['member_name'] = 'Örnek Üye';
        $historyUrl = $siteUrl !== '' ? ($siteUrl . '/profile/deposit-withdraw-history') : '/profile/deposit-withdraw-history';
        $sampleAmount = '1.250,00 TRY';

        if ($type === 'custom') {
            $name = trim((string) ($settings['custom_name'] ?? 'Özel şablon'));
            $subject = trim((string) ($settings['custom_subject'] ?? 'E-posta konusu'));
            $options['template_html'] = trim((string) ($settings['custom_template_html'] ?? ''));
            $bodyHtml = '<p style="margin:0;font-size:15px;line-height:1.7;color:#dcccf3;">'
                . 'Bu alan özel şablonunuzun örnek e-posta içeriğidir.'
                . '</p>';

            return metropol_mail_render_template(
                $siteUrl,
                $subject !== '' ? $subject : $name,
                $name !== '' ? $name : 'Özel Şablon',
                $bodyHtml,
                'Siteye Git',
                $siteUrl !== '' ? $siteUrl : '#',
                $options
            );
        }

        if ($type === 'welcome') {
            $options['template_html'] = trim((string) ($settings['welcome_template_html'] ?? ''));
            $bodyHtml = '<p style="margin:0 0 16px 0;font-size:15px;line-height:1.7;color:#dcccf3;">'
                . '<strong style="color:#ffffff;">' . $safeCompany . '</strong> ailesine hoş geldiniz. '
                . 'Hesabınız başarıyla oluşturuldu; oyunları ve güncel kampanyaları keşfetmeye başlayabilirsiniz.'
                . '</p>'
                . '<p style="margin:0;font-size:13px;line-height:1.7;color:#b9a3d6;">'
                . 'Güvenliğiniz için şifrenizi kimseyle paylaşmayın.'
                . '</p>';

            return metropol_mail_render_template(
                $siteUrl,
                $companyName . ' üyeliğiniz başarıyla oluşturuldu',
                'Aramıza Hoş Geldiniz!',
                $bodyHtml,
                'Siteye Git',
                $siteUrl !== '' ? $siteUrl : '#',
                $options
            );
        }

        if ($type === 'deposit_approved') {
            $options['template_html'] = trim((string) ($settings['deposit_approved_template_html'] ?? ''));
            $options['amount'] = $sampleAmount;
            $safeAmount = htmlspecialchars($sampleAmount, ENT_QUOTES, 'UTF-8');
            $bodyHtml = '<p style="margin:0 0 16px 0;font-size:15px;line-height:1.7;color:#dcccf3;">'
                . '<strong style="color:#ffffff;">' . $safeAmount . '</strong> tutarındaki yatırımınız onaylandı ve bakiyenize eklendi.'
                . '</p>'
                . '<p style="margin:0;font-size:13px;line-height:1.7;color:#b9a3d6;">'
                . 'İşlem detaylarını hesabınızdaki geçmiş sayfasından inceleyebilirsiniz.'
                . '</p>';

            return metropol_mail_render_template(
                $siteUrl,
                $companyName . ' — yatırımınız bakiyenize eklendi',
                'Yatırım Onaylandı',
                $bodyHtml,
                'İşlem Geçmişi',
                $historyUrl,
                $options
            );
        }

        if ($type === 'withdraw_approved') {
            $options['template_html'] = trim((string) ($settings['withdraw_approved_template_html'] ?? ''));
            $options['amount'] = $sampleAmount;
            $safeAmount = htmlspecialchars($sampleAmount, ENT_QUOTES, 'UTF-8');
            $bodyHtml = '<p style="margin:0 0 16px 0;font-size:15px;line-height:1.7;color:#dcccf3;">'
                . '<strong style="color:#ffffff;">' . $safeAmount . '</strong> tutarındaki çekim talebiniz tamamlandı.'
                . '</p>'
                . '<p style="margin:0;font-size:13px;line-height:1.7;color:#b9a3d6;">'
                . 'Tutar, seçtiğiniz ödeme yöntemine iletildi. İşlem geçmişinizi hesabınızdan kontrol edebilirsiniz.'
                . '</p>';

            return metropol_mail_render_template(
                $siteUrl,
                $companyName . ' — çekim talebiniz tamamlandı',
                'Çekim Tamamlandı',
                $bodyHtml,
                'İşlem Geçmişi',
                $historyUrl,
                $options
            );
        }

        $options['template_html'] = trim((string) ($settings['reset_template_html'] ?? ''));
        $resetLink = $siteUrl !== '' ? ($siteUrl . '/reset-password?token=preview-token') : '/reset-password?token=preview-token';
        $bodyHtml = '<p style="margin:0 0 16px 0;font-size:15px;line-height:1.7;color:#dcccf3;">'
            . $safeCompany . ' hesabınız için şifre sıfırlama talebi alındı. '
            . 'Aşağıdaki butona tıklayarak yeni şifrenizi belirleyebilirsiniz.'
            . '</p>'
            . '<p style="margin:0;font-size:13px;line-height:1.7;color:#b9a3d6;">'
            . '<strong style="color:#c44bb8;">Bu bağlantı 1 saat geçerlidir.</strong> '
            . 'Talebi siz oluşturmadıysanız bu e-postayı yok sayabilirsiniz.'
            . '</p>';

        return metropol_mail_render_template(
            $siteUrl,
            $companyName . ' şifre sıfırlama bağlantınız hazır',
            'Şifre Sıfırlama',
            $bodyHtml,
            'Şifremi Sıfırla',
            $resetLink,
            $options
        );
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
