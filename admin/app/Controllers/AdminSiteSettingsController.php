<?php

declare(strict_types=1);

final class AdminSiteSettingsController extends AdminController
{
    public function edit(): void
    {
        $this->requirePermission('site-settings');
        $this->ensureStorage();
        $section = $this->activeSection();

        $this->view('site-settings/edit', [
            'title' => 'Site Ayarları',
            'active' => 'forms',
            'moduleKey' => 'site-settings',
            'crumbs' => 'Site Yapısı | Ayarlar',
            'sections' => self::sections(),
            'section' => $section,
            'row' => $this->settingsRow(),
            'flash' => $this->pullFlash(),
            'error' => $this->pullError(),
        ]);
    }

    public function update(): void
    {
        $this->requirePermission('site-settings');
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            return;
        }

        $this->ensureStorage();
        $section = trim((string) ($_POST['section'] ?? ''));
        $sections = self::sections();
        if (!isset($sections[$section])) {
            $_SESSION['admin_site_settings_error'] = 'Geçersiz ayar bölümü.';
            $this->redirect(AdminAuth::url('/site-settings'));
        }

        $row = $this->settingsRow();
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            $_SESSION['admin_site_settings_error'] = 'Site ayar kaydı bulunamadı.';
            $this->redirect(AdminAuth::url('/site-settings?section=' . rawurlencode($section)));
        }

        $columns = $this->tableColumns();
        $updates = [];
        $params = ['id' => $id];

        foreach ($sections[$section]['fields'] as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name === '' || !in_array($name, $columns, true)) {
                continue;
            }
            $type = (string) ($field['type'] ?? 'text');
            if ($type === 'checkbox') {
                $updates[] = '`' . str_replace('`', '``', $name) . '` = :' . $name;
                $params[$name] = isset($_POST[$name]) ? 1 : 0;
                continue;
            }
            $value = trim((string) ($_POST[$name] ?? ''));
            $updates[] = '`' . str_replace('`', '``', $name) . '` = :' . $name;
            $params[$name] = $value;
        }

        if ($updates === []) {
            $_SESSION['admin_site_settings_error'] = 'Bu bölümde güncellenecek alan bulunamadı.';
            $this->redirect(AdminAuth::url('/site-settings?section=' . rawurlencode($section)));
        }

        try {
            $sql = 'UPDATE site_ayarlar SET ' . implode(', ', $updates) . ', updated_at = NOW() WHERE id = :id';
            AdminDatabase::pdo()->prepare($sql)->execute($params);
            AdminAuth::writeLog(AdminAuth::userName(), 'site_settings_update', 'site_ayarlar', 'success', $section);
            $_SESSION['admin_site_settings_flash'] = ($sections[$section]['label'] ?? 'Ayar') . ' kaydedildi.';
            if (function_exists('metropol_notify_frontend_cms_purge')) {
                metropol_notify_frontend_cms_purge('site_settings');
            }
        } catch (Throwable $exception) {
            $_SESSION['admin_site_settings_error'] = 'Kayıt başarısız: ' . $exception->getMessage();
        }

        $this->redirect(AdminAuth::url('/site-settings?section=' . rawurlencode($section)));
    }

    /** @return array<string, array{label: string, caption: string, fields: list<array<string, mixed>>}> */
    public static function sections(): array
    {
        return [
            'general' => [
                'label' => 'Genel',
                'caption' => 'Marka adı, dil ve bakım',
                'fields' => [
                    ['name' => 'site_adi', 'label' => 'Site adı', 'type' => 'text', 'placeholder' => 'Nexthub Casino'],
                    ['name' => 'site_aciklama', 'label' => 'Kısa açıklama', 'type' => 'textarea', 'placeholder' => 'Güvenilir casino ve bahis'],
                    ['name' => 'language', 'label' => 'Dil kodu', 'type' => 'text', 'placeholder' => 'tr'],
                    ['name' => 'theme_color', 'label' => 'Tema rengi', 'type' => 'color', 'placeholder' => '#120023'],
                    ['name' => 'bakim_modu', 'label' => 'Bakım modu aktif', 'type' => 'checkbox'],
                ],
            ],
            'branding' => [
                'label' => 'Marka',
                'caption' => 'Logo ve görsel varlıklar',
                'fields' => [
                    ['name' => 'logo_url', 'label' => 'Logo URL', 'type' => 'text', 'placeholder' => '/assets/images/logo.png'],
                    ['name' => 'favicon_url', 'label' => 'Favicon URL', 'type' => 'text', 'placeholder' => '/assets/images/favicons/favicon.svg'],
                    ['name' => 'manifest_url', 'label' => 'Manifest URL', 'type' => 'text', 'placeholder' => '/assets/images/favicons/site.webmanifest'],
                    ['name' => 'og_image_url', 'label' => 'OG görsel URL', 'type' => 'text', 'help' => 'Boş bırakılırsa logo kullanılır.'],
                ],
            ],
            'seo' => [
                'label' => 'SEO',
                'caption' => 'Arama motoru meta bilgileri',
                'fields' => [
                    ['name' => 'meta_title', 'label' => 'Meta başlık', 'type' => 'text'],
                    ['name' => 'site_keywords', 'label' => 'Anahtar kelimeler', 'type' => 'text', 'placeholder' => 'casino, bahis, slot'],
                    ['name' => 'robots', 'label' => 'Robots', 'type' => 'text', 'placeholder' => 'index, follow'],
                ],
            ],
            'urls' => [
                'label' => 'URL & API',
                'caption' => 'Frontend, backend ve izinli hostlar',
                'fields' => [
                    ['name' => 'frontend_url', 'label' => 'Frontend URL', 'type' => 'url', 'placeholder' => 'https://example.com'],
                    ['name' => 'backend_url', 'label' => 'Backend URL', 'type' => 'url', 'placeholder' => 'https://admin.vegasroyalspin.com'],
                    ['name' => 'backend_api_base_url', 'label' => 'API base URL', 'type' => 'url', 'placeholder' => 'https://api.vegasroyalspin.com/api/v2'],
                    ['name' => 'allowed_url_hosts', 'label' => 'İzinli hostlar', 'type' => 'textarea', 'help' => 'Virgülle ayırın: site.com,admin.site.com'],
                ],
            ],
            'contact' => [
                'label' => 'İletişim',
                'caption' => 'Destek ve sosyal kanallar',
                'fields' => [
                    ['name' => 'live_support_url', 'label' => 'Canlı destek URL', 'type' => 'url'],
                    ['name' => 'live_chat_license', 'label' => 'LiveChat lisans ID', 'type' => 'text'],
                    ['name' => 'live_chat_enabled', 'label' => 'LiveChat aktif', 'type' => 'checkbox'],
                    ['name' => 'callback_url', 'label' => 'Beni ara URL', 'type' => 'text', 'placeholder' => '/beni-ara'],
                    ['name' => 'callback_widget_text', 'label' => 'Beni ara widget metni', 'type' => 'textarea'],
                    ['name' => 'contact_phone', 'label' => 'Telefon', 'type' => 'text'],
                    ['name' => 'whatsapp_url', 'label' => 'WhatsApp URL', 'type' => 'url'],
                    ['name' => 'telegram_url', 'label' => 'Telegram URL', 'type' => 'url'],
                    ['name' => 'partnership_label', 'label' => 'Ortaklık etiketi', 'type' => 'text'],
                    ['name' => 'partnership_url', 'label' => 'Ortaklık URL', 'type' => 'text'],
                ],
            ],
        ];
    }

    private function activeSection(): string
    {
        $key = trim((string) ($_GET['section'] ?? 'general'));
        $sections = self::sections();

        return isset($sections[$key]) ? $key : 'general';
    }

    /** @return array<string, mixed> */
    private function settingsRow(): array
    {
        try {
            $row = AdminDatabase::pdo()->query('SELECT * FROM site_ayarlar ORDER BY id ASC LIMIT 1')->fetch();
            return is_array($row) ? $row : [];
        } catch (Throwable) {
            return [];
        }
    }

    /** @return list<string> */
    private function tableColumns(): array
    {
        try {
            $stmt = AdminDatabase::pdo()->query(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'site_ayarlar'
                 ORDER BY ORDINAL_POSITION"
            );
            $columns = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $columns[] = (string) ($row['COLUMN_NAME'] ?? '');
            }

            return array_values(array_filter($columns));
        } catch (Throwable) {
            return [];
        }
    }

    private function ensureStorage(): void
    {
        if (!defined('API_PATH')) {
            define('API_PATH', admin_project_path('api'));
        }
        $bootstrap = API_PATH . '/bootstrap.php';
        if (is_file($bootstrap)) {
            require_once $bootstrap;
        }
        if (class_exists('ApiSiteSettings', false)) {
            ApiSiteSettings::ensureStorage();
        }
    }

    private function pullFlash(): string
    {
        $message = (string) ($_SESSION['admin_site_settings_flash'] ?? '');
        unset($_SESSION['admin_site_settings_flash']);

        return $message;
    }

    private function pullError(): string
    {
        $message = (string) ($_SESSION['admin_site_settings_error'] ?? '');
        unset($_SESSION['admin_site_settings_error']);

        return $message;
    }
}
