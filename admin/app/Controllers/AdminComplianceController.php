<?php

declare(strict_types=1);

final class AdminComplianceController extends AdminController
{
    public function amlAlerts(): void
    {
        $this->requirePermission('compliance-aml');
        admin_require_project_file('services/ComplianceService.php');

        $pdo = AdminDatabase::pdo();
        $status = trim((string) ($_GET['status'] ?? 'open'));
        $severity = trim((string) ($_GET['severity'] ?? ''));
        $rule = trim((string) ($_GET['rule'] ?? ''));
        $q = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $filters = [
            'status' => $status,
            'severity' => $severity,
            'rule' => $rule,
            'q' => $q,
        ];
        $result = ComplianceService::listAmlAlerts($pdo, $page, 25, $status, $filters);

        $this->view('compliance/aml-alerts', [
            'title' => 'AML Uyarıları',
            'active' => 'compliance-aml',
            'crumbs' => 'Uyumluluk | AML Uyarıları',
            'alerts' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'status' => $status,
            'severity' => $severity,
            'rule' => $rule,
            'q' => $q,
            'summary' => ComplianceService::summary($pdo, 'aml_alerts'),
            'chartData' => ComplianceService::chartBundle($pdo, 'aml_alerts'),
            'flash' => $this->pullFlash(),
        ]);
    }

    public function riskAlerts(): void
    {
        $this->requirePermission('compliance-risk');
        admin_require_project_file('services/ComplianceService.php');

        $pdo = AdminDatabase::pdo();
        $status = trim((string) ($_GET['status'] ?? 'open'));
        $severity = trim((string) ($_GET['severity'] ?? ''));
        $rule = trim((string) ($_GET['rule'] ?? ''));
        $q = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $filters = [
            'status' => $status,
            'severity' => $severity,
            'rule' => $rule,
            'q' => $q,
        ];
        $result = ComplianceService::listRiskAlerts($pdo, $page, 25, $status, $filters);

        $this->view('compliance/risk-alerts', [
            'title' => 'Risk Uyarıları',
            'active' => 'compliance-risk',
            'crumbs' => 'Uyumluluk | Risk Uyarıları',
            'alerts' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'status' => $status,
            'severity' => $severity,
            'rule' => $rule,
            'q' => $q,
            'summary' => ComplianceService::summary($pdo, 'risk_alerts'),
            'chartData' => ComplianceService::chartBundle($pdo, 'risk_alerts'),
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
            if ($ok) {
                AdminAuditService::write(AdminDatabase::pdo(), 'aml_resolve', 'aml_alert', $id, $note !== '' ? $note : 'AML uyarısı çözüldü');
            }
            $_SESSION['admin_flash'] = $ok ? 'AML uyarısı çözüldü.' : 'Kayıt bulunamadı veya zaten kapalı.';
        } catch (Throwable $exception) {
            $_SESSION['admin_flash'] = 'İşlem başarısız: ' . $exception->getMessage();
        }

        $this->redirect(AdminAuth::url('/compliance/aml-alerts'));
    }

    public function ignoreAml(): void
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
            $ok = ComplianceService::ignoreAml(AdminDatabase::pdo(), $id, AdminAuth::userName(), $note);
            if ($ok) {
                AdminAuditService::write(AdminDatabase::pdo(), 'aml_ignore', 'aml_alert', $id, $note !== '' ? $note : 'AML uyarısı yoksayıldı');
            }
            $_SESSION['admin_flash'] = $ok ? 'AML uyarısı yoksayıldı.' : 'Kayıt bulunamadı veya zaten kapalı.';
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
            if ($ok) {
                AdminAuditService::write(AdminDatabase::pdo(), 'risk_resolve', 'risk_alert', $id, $note !== '' ? $note : 'Risk uyarısı çözüldü');
            }
            $_SESSION['admin_flash'] = $ok ? 'Risk uyarısı çözüldü.' : 'Kayıt bulunamadı veya zaten kapalı.';
        } catch (Throwable $exception) {
            $_SESSION['admin_flash'] = 'İşlem başarısız: ' . $exception->getMessage();
        }

        $this->redirect(AdminAuth::url('/compliance/risk-alerts'));
    }

    public function ignoreRisk(): void
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
            $ok = ComplianceService::ignoreRisk(AdminDatabase::pdo(), $id, AdminAuth::userName(), $note);
            if ($ok) {
                AdminAuditService::write(AdminDatabase::pdo(), 'risk_ignore', 'risk_alert', $id, $note !== '' ? $note : 'Risk uyarısı yoksayıldı');
            }
            $_SESSION['admin_flash'] = $ok ? 'Risk uyarısı yoksayıldı.' : 'Kayıt bulunamadı veya zaten kapalı.';
        } catch (Throwable $exception) {
            $_SESSION['admin_flash'] = 'İşlem başarısız: ' . $exception->getMessage();
        }

        $this->redirect(AdminAuth::url('/compliance/risk-alerts'));
    }

    public function auditLog(): void
    {
        $this->requirePermission('logs');
        $pdo = AdminDatabase::pdo();

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));
        $q = trim((string) ($_GET['q'] ?? ''));
        $action = trim((string) ($_GET['action'] ?? ''));
        $admin = trim((string) ($_GET['admin'] ?? ''));
        $source = trim((string) ($_GET['source'] ?? ''));
        if (!in_array($source, ['', 'audit', 'panel'], true)) {
            $source = '';
        }

        $result = AdminAuditService::listUnified($pdo, [
            'page' => $page,
            'per_page' => $perPage,
            'q' => $q,
            'action' => $action,
            'admin' => $admin,
            'source' => $source,
        ]);

        $this->view('compliance/audit-log', [
            'title' => 'Denetim Logu',
            'active' => 'compliance-audit',
            'crumbs' => 'Uyumluluk | Denetim Logu',
            'items' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'perPage' => $result['per_page'],
            'totalPages' => $result['total_pages'],
            'q' => $q,
            'actionFilter' => $action,
            'adminFilter' => $admin,
            'sourceFilter' => $source,
            'actionOptions' => AdminAuditService::distinctActions($pdo),
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
