<?php

declare(strict_types=1);

final class AdminFooterController extends AdminController
{
    public function edit(): void
    {
        try {
            $this->requirePermission('footer-settings');
        } catch (Throwable $e) {
            error_log('Footer: Permission check failed: ' . $e->getMessage());
            throw $e;
        }

        try {
            $payload = $this->footerPayload();
        } catch (Throwable $e) {
            error_log('Footer: Payload fetch failed: ' . $e->getMessage());
            $_SESSION['admin_footer_error'] = 'Footer verileri yüklenemedi: ' . $e->getMessage();
            $payload = ApiFooter::defaultPayload();
        }

        $this->view('footer/edit', [
            'title' => 'Footer Yönetimi',
            'active' => 'datatable',
            'moduleKey' => 'footer-settings',
            'crumbs' => 'Content | Footer',
            'payload' => $payload,
            'jsonFields' => $this->jsonFields($payload),
            'flash' => $this->pullFlash(),
            'error' => $this->pullError(),
        ]);
    }

    public function update(): void
    {
        $this->requirePermission('footer-settings');
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            return;
        }

        try {
            $current = $this->footerPayload();
        } catch (Throwable $e) {
            $_SESSION['admin_footer_error'] = 'Mevcut footer verisi okunamadı.';
            error_log('Footer update - fetch failed: ' . $e->getMessage());
            $this->redirect(AdminAuth::url('/footer'));
        }

        $payload = $current;

        $payload['site_name'] = trim((string) ($_POST['site_name'] ?? $current['site_name'] ?? ''));
        $payload['flag_image'] = trim((string) ($_POST['flag_image'] ?? $current['flag_image'] ?? ''));
        $payload['copyright_since'] = (int) ($_POST['copyright_since'] ?? $current['copyright_since'] ?? 2014);
        $payload['show_custom_content'] = isset($_POST['show_custom_content']);
        $payload['support_badge'] = [
            'enabled' => isset($_POST['support_badge_enabled']),
            'label' => trim((string) ($_POST['support_badge_label'] ?? '7/24')),
            'text' => trim((string) ($_POST['support_badge_text'] ?? 'ONLINE')),
            'href' => trim((string) ($_POST['support_badge_href'] ?? 'javascript:void(0)')),
        ];
        $payload['about'] = [
            'history_title' => trim((string) ($_POST['history_title'] ?? '')),
            'history_text' => trim((string) ($_POST['history_text'] ?? '')),
            'future_title' => trim((string) ($_POST['future_title'] ?? '')),
            'future_text' => trim((string) ($_POST['future_text'] ?? '')),
            'awards_title' => trim((string) ($_POST['awards_title'] ?? '')),
        ];

        foreach (['social_icons', 'menu_columns', 'payments', 'licence_rows', 'awards', 'partner_logos', 'jackpot_config'] as $field) {
            $decoded = json_decode((string) ($_POST[$field] ?? ''), true);
            if (!is_array($decoded)) {
                $_SESSION['admin_footer_error'] = $field . ' alanı geçerli JSON değil. Lütfen formatı kontrol edin.';
                error_log('Footer update - invalid JSON in ' . $field);
                $this->redirect(AdminAuth::url('/footer'));
            }
            $payload[$field] = $decoded;
        }

        try {
            $payload = ApiFooter::normalize($payload);
        } catch (Throwable $e) {
            $_SESSION['admin_footer_error'] = 'Footer verileri normalize edilemedi: ' . $e->getMessage();
            error_log('Footer update - normalize failed: ' . $e->getMessage());
            $this->redirect(AdminAuth::url('/footer'));
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            $_SESSION['admin_footer_error'] = 'Footer verisi kaydedilemedi.';
            $this->redirect(AdminAuth::url('/footer'));
        }

        try {
            $pdo = AdminDatabase::pdo();
            ApiFooter::ensureStorage($pdo, ApiFooter::defaultPayload());
            $pdo->exec('UPDATE footer_settings SET is_active = 0');

            $stmt = $pdo->prepare('SELECT id FROM footer_settings WHERE name = :name ORDER BY id DESC LIMIT 1');
            $stmt->execute(['name' => 'default']);
            $id = (int) $stmt->fetchColumn();
            if ($id > 0) {
                $update = $pdo->prepare('UPDATE footer_settings SET payload = :payload, is_active = 1 WHERE id = :id');
                $update->execute(['payload' => $encoded, 'id' => $id]);
            } else {
                $insert = $pdo->prepare('INSERT INTO footer_settings (name, payload, is_active) VALUES (:name, :payload, 1)');
                $insert->execute(['name' => 'default', 'payload' => $encoded]);
            }
        } catch (Throwable $e) {
            $_SESSION['admin_footer_error'] = 'Veritabanı kaydı başarısız: ' . $e->getMessage();
            error_log('Footer update - DB save failed: ' . $e->getMessage());
            $this->redirect(AdminAuth::url('/footer'));
        }

        $_SESSION['admin_footer_flash'] = 'Footer ayarları güncellendi.';
        if (function_exists('metropol_notify_frontend_cms_purge')) {
            try {
                metropol_notify_frontend_cms_purge('footer');
            } catch (Throwable $e) {
                error_log('Footer update - cache purge failed: ' . $e->getMessage());
            }
        }
        $this->redirect(AdminAuth::url('/footer'));
    }

    private function footerPayload(): array
    {
        $this->loadFooterApi();
        return ApiFooter::fetch();
    }

    private function loadFooterApi(): void
    {
        if (!defined('API_PATH')) {
            define('API_PATH', admin_project_path('api'));
        }
        require_once API_PATH . '/bootstrap.php';
    }

    private function jsonFields(array $payload): array
    {
        $fields = [];
        foreach (['social_icons', 'menu_columns', 'payments', 'licence_rows', 'awards', 'partner_logos', 'jackpot_config'] as $field) {
            $fields[$field] = json_encode($payload[$field] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $fields;
    }

    private function pullFlash(): string
    {
        $message = (string) ($_SESSION['admin_footer_flash'] ?? '');
        unset($_SESSION['admin_footer_flash']);
        return $message;
    }

    private function pullError(): string
    {
        $message = (string) ($_SESSION['admin_footer_error'] ?? '');
        unset($_SESSION['admin_footer_error']);
        return $message;
    }
}
