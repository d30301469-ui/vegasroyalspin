<?php

declare(strict_types=1);

final class AdminMobileMenuController extends AdminController
{
    public function edit(): void
    {
        $this->requirePermission('mobile-menu-settings');
        $payload = $this->menuPayload();

        $this->view('mobile-menu/edit', [
            'title' => 'Mobil Menü Yönetimi',
            'active' => 'datatable',
            'moduleKey' => 'mobile-menu-settings',
            'crumbs' => 'Content | Mobile Menu',
            'payload' => $payload,
            'flash' => $this->pullFlash(),
            'error' => $this->pullError(),
        ]);
    }

    public function update(): void
    {
        $this->requirePermission('mobile-menu-settings');
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            return;
        }

        $current = $this->menuPayload();
        $sections = $this->sectionsFromPost($_POST['sections'] ?? []);

        $payload = ApiMobileMenu::normalize(array_replace($current, [
            'title' => trim((string) ($_POST['title'] ?? $current['title'] ?? 'Menü')),
            'tab_bar' => $this->tabBarFromPost($_POST['tab_bar'] ?? [], is_array($current['tab_bar'] ?? null) ? $current['tab_bar'] : ApiMobileMenu::defaultTabBar()),
            'desktop_nav' => $this->desktopNavFromPost($_POST['desktop_nav'] ?? []),
            'sections' => $sections,
            'product_banner_base' => trim((string) ($_POST['product_banner_base'] ?? $current['product_banner_base'] ?? 'assets/images/banners')),
            'product_banners' => $this->productBannersFromPost($_POST['product_banners'] ?? []),
        ]));

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            $_SESSION['admin_mobile_menu_error'] = 'Mobil menü verisi kaydedilemedi.';
            $this->redirect(AdminAuth::url('/mobile-menu'));
        }

        $pdo = AdminDatabase::pdo();
        ApiMobileMenu::fetch(); // Ensures mobile_menu_settings exists and has a default row.
        $pdo->exec('UPDATE mobile_menu_settings SET is_active = 0');

        $stmt = $pdo->prepare('SELECT id FROM mobile_menu_settings WHERE name = :name ORDER BY id DESC LIMIT 1');
        $stmt->execute(['name' => 'default']);
        $id = (int) $stmt->fetchColumn();
        if ($id > 0) {
            $update = $pdo->prepare('UPDATE mobile_menu_settings SET payload = :payload, is_active = 1 WHERE id = :id');
            $update->execute(['payload' => $encoded, 'id' => $id]);
        } else {
            $insert = $pdo->prepare('INSERT INTO mobile_menu_settings (name, payload, is_active) VALUES (:name, :payload, 1)');
            $insert->execute(['name' => 'default', 'payload' => $encoded]);
        }

        $_SESSION['admin_mobile_menu_flash'] = 'Mobil menü ayarları güncellendi.';
        if (function_exists('metropol_notify_frontend_cms_purge')) {
            metropol_notify_frontend_cms_purge('mobile_menu');
        }
        $this->redirect(AdminAuth::url('/mobile-menu'));
    }

    private function sectionsFromPost(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }

        $sections = [];
        foreach ($input as $section) {
            if (!is_array($section)) {
                continue;
            }
            $sectionTitle = trim((string) ($section['title'] ?? ''));
            $layout = strtolower(trim((string) ($section['layout'] ?? '')));
            if (!in_array($layout, ['grid', 'list'], true)) {
                $layout = $sectionTitle === '' ? 'grid' : 'list';
            }
            $itemsInput = is_array($section['items'] ?? null) ? $section['items'] : [];
            $items = [];
            foreach ($itemsInput as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $label = trim((string) ($item['label'] ?? ''));
                $href = trim((string) ($item['href'] ?? ''));
                if ($label === '' && $href === '') {
                    continue;
                }
                $items[] = [
                    'label' => $label,
                    'href' => $href,
                    'icon' => trim((string) ($item['icon'] ?? '')),
                    'badge' => trim((string) ($item['badge'] ?? '')),
                    'target' => (string) ($item['target'] ?? '_self'),
                    'enabled' => isset($item['enabled']) && (string) $item['enabled'] === '1',
                ];
            }
            if ($items === []) {
                continue;
            }
            $sections[] = [
                'title' => $sectionTitle,
                'layout' => $layout,
                'items' => $items,
            ];
        }

        return $sections;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tabBarFromPost(mixed $input, array $fallback): array
    {
        if (!is_array($input)) {
            return $fallback;
        }

        $items = [];
        foreach ($input as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $type = strtolower(trim((string) ($row['type'] ?? 'link')));
            if (!in_array($type, ['link', 'button', 'menu'], true)) {
                $type = 'link';
            }
            $item = [
                'type' => $type,
                'label' => $label,
                'href' => trim((string) ($row['href'] ?? '')),
                'icon' => trim((string) ($row['icon'] ?? '')),
                'badge' => trim((string) ($row['badge'] ?? '')),
                'aria_label' => trim((string) ($row['aria_label'] ?? $label)),
                'enabled' => isset($row['enabled']) && (string) $row['enabled'] === '1',
            ];
            $id = trim((string) ($row['id'] ?? ''));
            if ($id !== '') {
                $item['id'] = $id;
            }
            $items[] = $item;
        }

        return $items !== [] ? $items : $fallback;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function desktopNavFromPost(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }

        $items = [];
        foreach ($input as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            $href = trim((string) ($row['href'] ?? ''));
            if ($label === '' || $href === '') {
                continue;
            }
            $items[] = [
                'label' => $label,
                'href' => $href,
                'icon' => trim((string) ($row['icon'] ?? '')),
                'enabled' => isset($row['enabled']) && (string) $row['enabled'] === '1',
            ];
        }

        return $items;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function productBannersFromPost(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }

        $banners = [];
        foreach ($input as $row) {
            if (!is_array($row)) {
                continue;
            }
            $aria = trim((string) ($row['aria'] ?? ''));
            $img = trim((string) ($row['img'] ?? ''));
            if ($aria === '' && $img === '') {
                continue;
            }
            if ($aria === '' || $img === '') {
                continue;
            }

            $item = [
                'aria' => $aria,
                'alt' => trim((string) ($row['alt'] ?? $aria)),
                'img' => $img,
                'enabled' => isset($row['enabled']) && (string) $row['enabled'] === '1',
            ];

            if (!empty($row['login_gate']) && (string) $row['login_gate'] === '1') {
                $item['login_gate'] = true;
            } else {
                $href = trim((string) ($row['href'] ?? ''));
                if ($href === '') {
                    continue;
                }
                $item['href'] = $href;
            }

            $banners[] = $item;
        }

        return $banners;
    }

    private function menuPayload(): array
    {
        $this->loadMobileMenuApi();
        return ApiMobileMenu::fetch();
    }

    private function loadMobileMenuApi(): void
    {
        if (!defined('API_PATH')) {
            define('API_PATH', admin_project_path('api'));
        }
        require_once API_PATH . '/bootstrap.php';
    }

    private function pullFlash(): string
    {
        $message = (string) ($_SESSION['admin_mobile_menu_flash'] ?? '');
        unset($_SESSION['admin_mobile_menu_flash']);
        return $message;
    }

    private function pullError(): string
    {
        $message = (string) ($_SESSION['admin_mobile_menu_error'] ?? '');
        unset($_SESSION['admin_mobile_menu_error']);
        return $message;
    }
}
