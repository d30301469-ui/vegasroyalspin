<?php

declare(strict_types=1);

final class AdminPermissionController extends AdminController
{
    public function index(): void
    {
        $this->requirePermission('permissions');
        $this->ensureStorage();

        $admins = $this->admins();
        $selectedAdminId = max(0, (int) ($_GET['admin_id'] ?? ($admins[0]['id'] ?? 0)));

        $this->view('permissions/index', [
            'title' => 'Admin Yetkileri',
            'active' => 'datatable',
            'moduleKey' => 'permissions',
            'crumbs' => 'Admin | Permissions',
            'admins' => $admins,
            'selectedAdminId' => $selectedAdminId,
            'permissionGroups' => $this->permissionGroups(),
            'grants' => $this->grantsFor($selectedAdminId),
            'flash' => $this->pullFlash(),
        ]);
    }

    public function update(): void
    {
        $this->requirePermission('permissions');
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }

        $this->ensureStorage();
        $adminId = max(0, (int) ($_POST['admin_id'] ?? 0));
        if ($adminId <= 0 || !$this->adminExists($adminId)) {
            $this->flash('Geçerli bir admin seçin.');
            $this->redirect(AdminAuth::url('/permissions'));
        }

        $allowedKeys = [];
        foreach ($this->permissionGroups() as $group) {
            foreach ((array) ($group['items'] ?? []) as $item) {
                $allowedKeys[] = (string) ($item['key'] ?? '');
            }
        }
        $allowedKeys = array_values(array_filter(array_unique($allowedKeys)));
        $postedKeys = array_map('strval', is_array($_POST['permissions'] ?? null) ? $_POST['permissions'] : []);
        $postedKeys = array_values(array_intersect($postedKeys, $allowedKeys));
        // Süperadmin hesabının yetkileri formdan yanlışlıkla (veya eksik seçimle) daraltılamaz;
        // her zaman tüm sayfa anahtarları açık kaydedilir.
        if ($this->isSuperAdminAccount($adminId)) {
            $postedKeys = $allowedKeys;
        }

        $pdo = AdminDatabase::pdo();
        $pdo->beginTransaction();
        try {
            $delete = $pdo->prepare('DELETE FROM admin_permissions WHERE admin_id = :admin_id');
            $delete->execute(['admin_id' => $adminId]);

            $insert = $pdo->prepare(
                'INSERT INTO admin_permissions (admin_id, page_key, granted, granted_by, granted_at)
                 VALUES (:admin_id, :page_key, :granted, :granted_by, NOW())'
            );
            $currentAdmin = AdminAuth::user();
            $grantedBy = (int) ($currentAdmin['id'] ?? 0);
            foreach ($allowedKeys as $pageKey) {
                $insert->execute([
                    'admin_id' => $adminId,
                    'page_key' => $pageKey,
                    'granted' => in_array($pageKey, $postedKeys, true) ? 1 : 0,
                    'granted_by' => $grantedBy > 0 ? $grantedBy : null,
                ]);
            }

            $pdo->commit();
            $this->flash('Admin yetkileri güncellendi.');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->flash('Yetkiler kaydedilemedi: ' . $exception->getMessage());
        }

        $this->redirect(AdminAuth::url('/permissions?admin_id=' . rawurlencode((string) $adminId)));
    }

    private function ensureStorage(): void
    {
        try {
            $migration = ADMIN_BASE_PATH . '/database/migrations/2026_06_06_000003_create_admin_permission_tables.php';
            if (is_file($migration)) {
                (require $migration)(AdminDatabase::pdo());
            }
        } catch (Throwable) {
        }
    }

    private function admins(): array
    {
        try {
            return AdminDatabase::pdo()
                ->query('SELECT id, username, email, role FROM admins ORDER BY username ASC, id ASC')
                ->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private function adminExists(int $adminId): bool
    {
        $stmt = AdminDatabase::pdo()->prepare('SELECT COUNT(*) FROM admins WHERE id = :id');
        $stmt->execute(['id' => $adminId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function isSuperAdminAccount(int $adminId): bool
    {
        try {
            $stmt = AdminDatabase::pdo()->prepare('SELECT role FROM admins WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $adminId]);
            $role = strtolower(trim((string) $stmt->fetchColumn()));

            return in_array($role, ['superadmin', 'super_admin', 'owner'], true);
        } catch (Throwable) {
            return false;
        }
    }

    private function grantsFor(int $adminId): array
    {
        if ($adminId <= 0) {
            return [];
        }

        // Süperadmin her zaman tam yetkilidir (AdminAuth::isSuperAdmin() koddaki bypass).
        // admin_permissions tablosundaki satırlar eksik/gecikmeli olsa bile ekranda tüm
        // kutuların işaretli görünmesi ve kaydedildiğinde tam yetkinin kalıcı hâle gelmesi
        // için burada da her zaman "tümü açık" döndürülür.
        if ($this->isSuperAdminAccount($adminId)) {
            $grants = [];
            foreach ($this->permissionGroups() as $group) {
                foreach ((array) ($group['items'] ?? []) as $item) {
                    $key = (string) ($item['key'] ?? '');
                    if ($key !== '') {
                        $grants[$key] = true;
                    }
                }
            }

            return $grants;
        }

        $stmt = AdminDatabase::pdo()->prepare('SELECT page_key, granted FROM admin_permissions WHERE admin_id = :admin_id');
        $stmt->execute(['admin_id' => $adminId]);
        $grants = [];
        foreach ($stmt->fetchAll() as $row) {
            $pageKey = (string) ($row['page_key'] ?? '');
            $granted = (int) ($row['granted'] ?? 0) === 1;
            if (!$granted) {
                continue;
            }
            $canonical = AdminAuth::canonicalPermissionKey($pageKey);
            if ($canonical !== '') {
                $grants[$canonical] = true;
            }
        }

        return $grants;
    }

    private function permissionGroups(): array
    {
        $navigation = is_array($this->config['navigation'] ?? null) ? $this->config['navigation'] : [];
        $groups = [];
        foreach ($navigation as $group) {
            $items = [];
            $seen = [];
            foreach ((array) ($group['items'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $canonicalKey = AdminAuth::navPermissionKey($item);
                if ($canonicalKey === '' || isset($seen[$canonicalKey])) {
                    continue;
                }
                $seen[$canonicalKey] = true;
                $items[] = [
                    'key' => $canonicalKey,
                    'text' => (string) ($item['text'] ?? $canonicalKey),
                    'url' => (string) ($item['url'] ?? '#'),
                    'icon' => (string) ($item['icon'] ?? '<circle cx="12" cy="12" r="8"/>'),
                    'description' => $this->permissionDescription($canonicalKey),
                ];
            }
            if ($items !== []) {
                $groups[] = [
                    'label' => (string) ($group['label'] ?? 'Admin'),
                    'caption' => (string) ($group['caption'] ?? ''),
                    'items' => $items,
                ];
            }
        }

        return $groups;
    }

    private function permissionDescription(string $key): string
    {
        return match ($key) {
            'dashboard' => 'Özet dashboard ve operasyon kartlarını görüntüleme.',
            'users' => 'Üye listesi, kullanıcı detayları ve profil düzenleme.',
            'kyc', 'kyc-review' => 'KYC talepleri, inceleme ve doğrulama.',
            'active-bonuses', 'bonus-claims' => 'Bonus kullanım ve talep kayıtlarını izleme.',
            'frozen-accounts' => 'Dondurulan hesap kayıtlarını görüntüleme.',
            'loyalty-levels', 'loyalty-accounts', 'loyalty-transactions' => 'Sadakat seviyeleri, üye puanları ve puan hareketlerini yönetme.',
            'deposits', 'withdrawals', 'reports-financial' => 'Yatırım, çekim ve finansal rapor ekranları.',
            'dashboard', 'reports-charts', 'reports-calendar', 'backoffice-suite' => 'Dashboard, grafikler ve operasyon takvimi.',
            'bgaming-settings', 'bgaming-games', 'bgaming-transactions', 'bgaming-wallet-logs' => 'BGaming sağlayıcı ve oyun yönetimi.',
            'sportsbook-settings', 'sportsbook-sessions', 'sportsbook-transactions', 'sportsbook-wallet-logs' => 'Sportsbook (BetBy) spor bahisleri sağlayıcı yönetimi.',
            'payment-providers', 'payment-methods' => 'Ödeme sağlayıcı ve metot ayarlarını yönetme.',
            'promotions', 'sliders', 'auth-sliders', 'homepage-sections', 'announcements', 'promocodes', 'promocode-requests' => 'Site içerik ve kampanya alanlarını yönetme.',
            'footer-settings', 'footer-pages', 'mobile-menu-settings' => 'Footer, sayfa ve mobil menü içeriklerini düzenleme.',
            'call-requests', 'email' => 'İletişim, mesaj ve aranma taleplerini takip etme.',
            'support-tickets' => 'Üye destek ticket listesi, yanıt ve kapatma.',
            'member-notifications' => 'Üyelere uygulama içi bildirim gönderme ve geçmişi görüntüleme.',
            'compliance-aml' => 'AML uyarı kuyruğunu görüntüleme ve çözümleme.',
            'compliance-risk' => 'Operasyonel risk uyarılarını görüntüleme ve çözümleme.',
            'admins', 'permissions', 'sessions', 'logs', 'site-settings' => 'Admin, güvenlik, oturum, log ve sistem ayarları.',
            default => 'Bu admin panel modülüne erişim izni.',
        };
    }

    private function flash(string $message): void
    {
        $_SESSION['admin_flash'] = $message;
    }

    private function pullFlash(): string
    {
        $message = (string) ($_SESSION['admin_flash'] ?? '');
        unset($_SESSION['admin_flash']);

        return $message;
    }
}
