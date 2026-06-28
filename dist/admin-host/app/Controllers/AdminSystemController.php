<?php

declare(strict_types=1);

final class AdminSystemController extends AdminController
{
    public function ui(): void
    {
        $this->requireAuth();
        $this->redirect(AdminAuth::url('/dashboard'));
    }

    public function buttons(): void
    {
        $this->requireAuth();
        $this->redirect(AdminAuth::url('/dashboard'));
    }

    public function forms(): void
    {
        $this->requireAuth();
        $this->redirect(AdminAuth::url('/site-settings'));
    }

    public function basicTable(): void
    {
        $this->requireAuth();
        $this->redirect(AdminAuth::url('/module?key=logs'));
    }

    public function blank(): void
    {
        $this->requireAuth();
        $this->redirect(AdminAuth::url('/dashboard'));
    }

    public function googleMaps(): void
    {
        $this->requireAuth();
        $this->redirect(AdminAuth::url('/reports/charts'));
    }

    public function vectorMaps(): void
    {
        $this->requireAuth();
        $this->redirect(AdminAuth::url('/reports/charts'));
    }

    public function signup(): void
    {
        $this->requirePermission('admins');
        $this->view('system/signup', [
            'title' => 'Yeni Admin',
            'active' => 'signup',
            'crumbs' => 'Admin | Yeni Admin',
            'flash' => $this->pullFlash(),
        ]);
    }

    public function storeAdmin(): void
    {
        $this->requirePermission('admins');
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }

        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = trim((string) ($_POST['role'] ?? 'admin')) ?: 'admin';

        if ($username === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($password) < 6) {
            $this->flash('Kullanıcı adı, geçerli email ve en az 6 karakter şifre girilmelidir.');
            $this->redirect(AdminAuth::url('/signup'));
        }

        try {
            $columns = $this->tableColumns('admins');
            $data = [];
            $expressions = [];

            foreach ([
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'twofa_enabled' => '0',
                'is_active' => '1',
            ] as $column => $value) {
                if (in_array($column, $columns, true)) {
                    $data[$column] = $value;
                }
            }

            foreach (['created_at', 'updated_at'] as $column) {
                if (in_array($column, $columns, true)) {
                    $expressions[$column] = 'NOW()';
                }
            }

            if (!isset($data['password'])) {
                throw new RuntimeException('admins.password kolonu bulunamadı.');
            }

            $names = array_merge(array_keys($data), array_keys($expressions));
            $placeholders = array_merge(
                array_map(static fn (string $name): string => ':' . $name, array_keys($data)),
                array_values($expressions)
            );
            $sql = 'INSERT INTO admins (`' . implode('`, `', $names) . '`) VALUES (' . implode(', ', $placeholders) . ')';
            AdminDatabase::pdo()->prepare($sql)->execute($data);

            $this->flash('Admin hesabı oluşturuldu.');
            $this->redirect(AdminAuth::url('/module?key=admins'));
        } catch (Throwable $exception) {
            $this->flash('Admin oluşturulamadı: ' . $exception->getMessage());
            $this->redirect(AdminAuth::url('/signup'));
        }
    }

    public function notFound(): void
    {
        $this->requireAuth();
        http_response_code(404);
        $this->view('errors/404', [
            'title' => '404',
            'active' => '404',
            'crumbs' => 'Pages | 404',
        ]);
    }

    public function serverError(): void
    {
        $this->requireAuth();
        http_response_code(500);
        $this->view('errors/500', [
            'title' => '500',
            'active' => '500',
            'crumbs' => 'Pages | 500',
            'message' => 'Tema 500 hata sayfası yönetim paneline bağlandı.',
        ]);
    }

    private function logs(): array
    {
        try {
            $stmt = AdminDatabase::pdo()->query(
                'SELECT id, admin_username, action, entity_type, entity_id, status, ip_address, created_at FROM admin_logs ORDER BY created_at DESC LIMIT 20'
            );

            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private function visitorLocations(): array
    {
        try {
            $stmt = AdminDatabase::pdo()->query(
                "SELECT COALESCE(NULLIF(country_name, ''), 'Bilinmeyen') AS country_name,
                        COALESCE(NULLIF(city, ''), '-') AS city,
                        COUNT(*) AS total,
                        AVG(lat) AS lat,
                        AVG(lon) AS lon
                 FROM visitor_logs
                 GROUP BY country_name, city
                 ORDER BY total DESC
                 LIMIT 20"
            );

            return $stmt->fetchAll();
        } catch (Throwable) {
            return [];
        }
    }

    private function tableColumns(string $table): array
    {
        $stmt = AdminDatabase::pdo()->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
        );
        $stmt->execute(['table' => $table]);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
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
