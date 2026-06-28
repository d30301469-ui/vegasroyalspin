<?php

declare(strict_types=1);

final class AdminSupportController extends AdminController
{
    public function tickets(): void
    {
        $this->requirePermission('support-tickets');
        admin_require_project_file('services/SupportTicketService.php');

        $status = trim((string) ($_GET['status'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $pdo = AdminDatabase::pdo();
        $result = SupportTicketService::listAdmin($pdo, $page, 25, $status);

        $this->view('support/tickets', [
            'title' => 'Destek Talepleri',
            'active' => 'support-tickets',
            'crumbs' => 'İletişim | Destek Talepleri',
            'tickets' => $result['items'],
            'total' => $result['total'],
            'page' => $page,
            'status' => $status,
            'flash' => $this->pullFlash(),
        ]);
    }

    public function ticket(): void
    {
        $this->requirePermission('support-tickets');
        admin_require_project_file('services/SupportTicketService.php');

        $ticketId = max(0, (int) ($_GET['id'] ?? 0));
        if ($ticketId <= 0) {
            $this->redirect(AdminAuth::url('/support/tickets'));
        }

        $pdo = AdminDatabase::pdo();
        $ticket = SupportTicketService::getTicket($pdo, $ticketId);
        if ($ticket === null) {
            $_SESSION['admin_flash'] = 'Destek talebi bulunamadı.';
            $this->redirect(AdminAuth::url('/support/tickets'));
        }

        $this->view('support/ticket', [
            'title' => 'Destek #' . $ticketId,
            'active' => 'support-tickets',
            'crumbs' => 'İletişim | Destek | #' . $ticketId,
            'ticket' => $ticket,
            'messages' => SupportTicketService::messagesForTicket($pdo, $ticketId),
            'flash' => $this->pullFlash(),
        ]);
    }

    public function reply(): void
    {
        $this->requirePermission('support-tickets');
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }

        admin_require_project_file('services/SupportTicketService.php');
        admin_require_project_file('services/MemberNotificationService.php');

        $ticketId = max(0, (int) ($_POST['ticket_id'] ?? 0));
        $message = trim((string) ($_POST['message'] ?? ''));
        if ($ticketId <= 0 || $message === '') {
            $_SESSION['admin_flash'] = 'Mesaj metni zorunludur.';
            $this->redirect(AdminAuth::url('/support/ticket?id=' . $ticketId));
        }

        try {
            SupportTicketService::adminReply(
                AdminDatabase::pdo(),
                $ticketId,
                AdminAuth::userName(),
                $message
            );
            $_SESSION['admin_flash'] = 'Yanıt gönderildi ve üyeye bildirim iletildi.';
        } catch (Throwable $exception) {
            $_SESSION['admin_flash'] = 'Yanıt gönderilemedi: ' . $exception->getMessage();
        }

        $this->redirect(AdminAuth::url('/support/ticket?id=' . $ticketId));
    }

    public function close(): void
    {
        $this->requirePermission('support-tickets');
        if (!AdminRequest::isPost() || !AdminAuth::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(419);
            echo 'Oturum doğrulaması başarısız.';
            exit;
        }

        admin_require_project_file('services/SupportTicketService.php');
        $ticketId = max(0, (int) ($_POST['ticket_id'] ?? 0));

        try {
            SupportTicketService::closeTicket(AdminDatabase::pdo(), $ticketId, AdminAuth::userName());
            $_SESSION['admin_flash'] = 'Destek talebi kapatıldı.';
        } catch (Throwable $exception) {
            $_SESSION['admin_flash'] = 'Talep kapatılamadı: ' . $exception->getMessage();
        }

        $this->redirect(AdminAuth::url('/support/ticket?id=' . $ticketId));
    }

    private function pullFlash(): string
    {
        $message = (string) ($_SESSION['admin_flash'] ?? '');
        unset($_SESSION['admin_flash']);

        return $message;
    }
}
