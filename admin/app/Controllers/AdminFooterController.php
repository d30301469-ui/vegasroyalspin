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
            $payload = $this->footerDefaults();
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

        error_log('Footer update: STARTED, POST data keys: ' . json_encode(array_keys($_POST)));

        try {
            $current = $this->footerPayload();
        } catch (Throwable $e) {
            $_SESSION['admin_footer_error'] = 'Mevcut footer verisi okunamadı.';
            error_log('Footer update - fetch failed: ' . $e->getMessage());
            $this->redirect(AdminAuth::url('/module?key=footer-settings'));
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
            $postValue = (string) ($_POST[$field] ?? '');
            if (trim($postValue) === '') {
                // Use current value or empty array
                $payload[$field] = $current[$field] ?? [];
                error_log('Footer update - JSON field empty, using current: ' . $field);
                continue;
            }
            $decoded = json_decode($postValue, true);
            if (!is_array($decoded)) {
                error_log('Footer update - JSON validation FAILED for field: ' . $field . ', raw: ' . $postValue);
                $_SESSION['admin_footer_error'] = $field . ' alanı geçerli JSON değil. Lütfen formatı kontrol edin.';
                $this->redirect(AdminAuth::url('/module?key=footer-settings'));
            }
            $payload[$field] = $decoded;
            error_log('Footer update - JSON OK for field: ' . $field);
        }

        // Not: Eskiden burada frontend ApiFooter::normalize() çağrılıyordu.
        // Ayrık admin dağıtımında frontend yığınını yüklemek sayfanın
        // açılmamasına yol açabildiği için kaldırıldı; alanlar zaten POST'tan
        // yapılandırılmış olarak geliyor. Sadece varsayılan anahtarların
        // eksiksiz olmasını garanti ediyoruz.
        $payload = $this->mergeFooterDefaults($payload);

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            $_SESSION['admin_footer_error'] = 'Footer verisi kaydedilemedi.';
            $this->redirect(AdminAuth::url('/footer'));
        }

        try {
            $pdo = AdminDatabase::pdo();
            $this->ensureFooterTable($pdo);
            error_log('Footer update - ensureFooterTable OK');
            $pdo->exec('UPDATE footer_settings SET is_active = 0');
            error_log('Footer update - SET is_active=0 OK');

            $stmt = $pdo->prepare('SELECT id FROM footer_settings WHERE name = :name ORDER BY id DESC LIMIT 1');
            $stmt->execute(['name' => 'default']);
            $id = (int) $stmt->fetchColumn();
            error_log('Footer update - found existing id: ' . $id);
            if ($id > 0) {
                $update = $pdo->prepare('UPDATE footer_settings SET payload = :payload, is_active = 1 WHERE id = :id');
                $update->execute(['payload' => $encoded, 'id' => $id]);
                error_log('Footer update - UPDATED existing record id=' . $id);
            } else {
                $insert = $pdo->prepare('INSERT INTO footer_settings (name, payload, is_active) VALUES (:name, :payload, 1)');
                $insert->execute(['name' => 'default', 'payload' => $encoded]);
                error_log('Footer update - INSERTED new record');
            }
        } catch (Throwable $e) {
            error_log('Footer update - DB save FAILED: ' . $e->getMessage());
            $_SESSION['admin_footer_error'] = 'Veritabanı kaydı başarısız: ' . $e->getMessage();
            error_log('Footer update - DB save failed: ' . $e->getMessage());
            $this->redirect(AdminAuth::url('/module?key=footer-settings'));
        }

        error_log('Footer update - COMPLETED SUCCESSFULLY');
        $_SESSION['admin_footer_flash'] = 'Footer ayarları güncellendi.';
        if (function_exists('metropol_notify_frontend_cms_purge')) {
            try {
                metropol_notify_frontend_cms_purge('footer');
            } catch (Throwable $e) {
                error_log('Footer update - cache purge failed: ' . $e->getMessage());
            }
        }
        // Redirect back to the module view so the form renders with updated data
        $this->redirect(AdminAuth::url('/module?key=footer-settings'));
    }

    /**
     * Footer verisini doğrudan admin veritabanından okur.
     *
     * Not: Eskiden bu metod frontend API yığınını (api/bootstrap.php + ApiFooter)
     * yüklüyordu. Ayrık (split) admin dağıtımında bu yığın farklı davranıp
     * (uzak CMS'e HTTP isteği / farklı DB politikası) footer yönetim sayfasının
     * açılmamasına ve /login'e yönlenmesine yol açabiliyordu. Artık tıpkı çalışan
     * "/module?key=footer-settings" liste ekranı gibi AdminDatabase üzerinden
     * footer_settings tablosu doğrudan okunur; hata olursa yerel varsayılan döner.
     */
    private function footerPayload(): array
    {
        try {
            $pdo = AdminDatabase::pdo();
            $this->ensureFooterTable($pdo);
            $stmt = $pdo->query(
                'SELECT payload FROM footer_settings
                 WHERE is_active = 1
                 ORDER BY updated_at DESC, id DESC
                 LIMIT 1'
            );
            $raw = $stmt !== false ? $stmt->fetchColumn() : false;
            if (is_string($raw) && trim($raw) !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    return $this->mergeFooterDefaults($decoded);
                }
            }
        } catch (Throwable $e) {
            error_log('Footer: veritabanindan okunamadi: ' . $e->getMessage());
        }

        return $this->footerDefaults();
    }

    /**
     * footer_settings tablosu yoksa oluşturur (liste ekranıyla aynı şema).
     */
    private function ensureFooterTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS footer_settings (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(120) NOT NULL DEFAULT \'default\',
                payload JSON NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_footer_settings_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    /**
     * Kaydedilmiş veriyle varsayılanları birleştirir; view'in beklediği tüm
     * üst düzey anahtarların her zaman var olmasını garanti eder.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function mergeFooterDefaults(array $payload): array
    {
        return array_merge($this->footerDefaults(), $payload);
    }

    /**
     * Frontend API'ye ihtiyaç duymadan, kendi kendine yeten varsayılan footer
     * yapısı. Site adı admin bağlamından (site_ayarlar) alınır.
     *
     * @return array<string, mixed>
     */
    private function footerDefaults(): array
    {
        $siteName = '';
        try {
            $siteName = trim((string) (AdminSiteContext::globals()['site_name'] ?? ''));
        } catch (Throwable) {
            $siteName = '';
        }

        return [
            'social_icons' => [],
            'menu_columns' => [],
            'payments' => [],
            'licence_rows' => [],
            'awards' => [],
            'partner_logos' => [],
            'jackpot_config' => [
                'epoch' => date('Y-m-d H:i:s'),
                'providers' => [],
            ],
            'flag_image' => '',
            'copyright_since' => (int) date('Y'),
            'site_name' => $siteName,
            'show_custom_content' => false,
            'support_badge' => [
                'enabled' => false,
                'label' => '7/24',
                'text' => 'ONLINE',
                'href' => 'javascript:void(0)',
            ],
            'about' => [
                'history_title' => '',
                'history_text' => '',
                'future_title' => '',
                'future_text' => '',
                'awards_title' => '',
            ],
        ];
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
