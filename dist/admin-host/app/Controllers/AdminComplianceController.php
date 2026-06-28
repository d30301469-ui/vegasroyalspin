<?php

declare(strict_types=1);

final class AdminComplianceController extends AdminController
{
    public function amlAlerts(): void
    {
        $this->requirePermission('compliance-aml');
        admin_require_project_file('services/ComplianceService.php');

        $status = trim((string) ($_GET['status'] ?? 'open'));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = ComplianceService::listAmlAlerts(AdminDatabase::pdo(), $page, 25, $status);

        $this->view('compliance/aml-alerts', [
            'title' => 'AML Uyarıları',
            'active' => 'compliance-aml',
            'crumbs' => 'Uyumluluk | AML Uyarıları',
            'alerts' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'status' => $status,
            'flash' => $this->pullFlash(),
        ]);
    }

    public function riskAlerts(): void
    {
        $this->requirePermission('compliance-risk');
        admin_require_project_file('services/ComplianceService.php');

        $status = trim((string) ($_GET['status'] ?? 'open'));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $result = ComplianceService::listRiskAlerts(AdminDatabase::pdo(), $page, 25, $status);

        $this->view('compliance/risk-alerts', [
            'title' => 'Risk Uyarıları',
            'active' => 'compliance-risk',
            'crumbs' => 'Uyumluluk | Risk Uyarıları',
            'alerts' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'status' => $status,
            'flash' => $this->pullFlash(),
        ]);
    }

    public function resolveAml(): void
    {
        $this->requirePermission('compliance-aml');
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }

        admin_require_project_file('services/ComplianceService.php');
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $note = trim((string) ($_POST['note'] ?? ''));

        try {
            $ok = ComplianceService::resolveAml(AdminDatabase::pdo(), $id, AdminAuth::userName(), $note);
            $_SESSION['admin_flash'] = $ok ? 'AML uyarısı çözüldü.' : 'Kayıt bulunamadı veya zaten çözülmüş.';
        } catch (Throwable $exception) {
            $_SESSION['admin_flash'] = 'İşlem başarısız: ' . $exception->getMessage();
        }

        $this->redirect(AdminAuth::url('/compliance/aml-alerts'));
    }

    public function resolveRisk(): void
    {
        $this->requirePermission('compliance-risk');
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }

        admin_require_project_file('services/ComplianceService.php');
        $id = max(0, (int) ($_POST['id'] ?? 0));
        $note = trim((string) ($_POST['note'] ?? ''));

        try {
            $ok = ComplianceService::resolveRisk(AdminDatabase::pdo(), $id, AdminAuth::userName(), $note);
            $_SESSION['admin_flash'] = $ok ? 'Risk uyarısı çözüldü.' : 'Kayıt bulunamadı veya zaten çözülmüş.';
        } catch (Throwable $exception) {
            $_SESSION['admin_flash'] = 'İşlem başarısız: ' . $exception->getMessage();
        }

        $this->redirect(AdminAuth::url('/compliance/risk-alerts'));
    }

    public function auditLog(): void
    {
        $this->requirePermission('logs');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;

        $items = [];
        $total = 0;
        try {
            $pdo = AdminDatabase::pdo();
            $total = (int) $pdo->query('SELECT COUNT(*) FROM admin_audit_logs')->fetchColumn();
            $stmt = $pdo->prepare(
                'SELECT id, admin_id, admin_username, action, entity_type, entity_id, note, ip_address, created_at
                 FROM admin_audit_logs ORDER BY id DESC LIMIT :limit OFFSET :offset'
            );
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll();
        } catch (Throwable) {
        }

        $this->view('compliance/audit-log', [
            'title' => 'Audit Log',
            'active' => 'logs',
            'crumbs' => 'Uyumluluk | Audit Log',
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => max(1, (int) ceil($total / $perPage)),
            'flash' => $this->pullFlash(),
        ]);
    }

    private function pullFlash(): string
    {
        $message = (string) ($_SESSION['admin_flash'] ?? '');
        unset($_SESSION['admin_flash']);

        return $message;
    }
}
