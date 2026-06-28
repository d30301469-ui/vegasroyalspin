<?php

declare(strict_types=1);

final class SupportTicketService
{
    public static function ensureTables(PDO $pdo): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $migration = dirname(__DIR__) . '/database/migrations/2026_06_11_000001_create_member_engagement_tables.php';
        if (is_readable($migration)) {
            $runner = require $migration;
            if (is_callable($runner)) {
                $runner($pdo);
            }
        }
        $ready = true;
    }

    /** @return list<array<string, mixed>> */
    public static function listForUser(PDO $pdo, int $userId): array
    {
        self::ensureTables($pdo);
        $stmt = $pdo->prepare(
            'SELECT id, subject, status, priority, category, created_at, updated_at, closed_at
             FROM support_tickets WHERE user_id = :user_id ORDER BY updated_at DESC LIMIT 100'
        );
        $stmt->execute(['user_id' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public static function create(PDO $pdo, int $userId, string $username, array $input): array
    {
        self::ensureTables($pdo);
        $subject = trim((string) ($input['subject'] ?? ''));
        $message = trim((string) ($input['message'] ?? $input['body'] ?? ''));
        if ($subject === '' || $message === '') {
            throw new InvalidArgumentException('subject ve message zorunludur.');
        }
        $priority = in_array((string) ($input['priority'] ?? 'normal'), ['low', 'normal', 'high'], true)
            ? (string) $input['priority'] : 'normal';
        $category = trim((string) ($input['category'] ?? 'general'));
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO support_tickets (user_id, subject, status, priority, category)
                 VALUES (:user_id, :subject, :status, :priority, :category)'
            );
            $stmt->execute([
                'user_id' => $userId,
                'subject' => $subject,
                'status' => 'open',
                'priority' => $priority,
                'category' => $category !== '' ? $category : 'general',
            ]);
            $ticketId = (int) $pdo->lastInsertId();
            $pdo->prepare(
                'INSERT INTO support_ticket_messages (ticket_id, sender_type, sender_name, message)
                 VALUES (:ticket_id, :sender_type, :sender_name, :message)'
            )->execute([
                'ticket_id' => $ticketId,
                'sender_type' => 'member',
                'sender_name' => $username,
                'message' => $message,
            ]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return ['id' => $ticketId, 'status' => 'open', 'subject' => $subject];
    }

    /** @return list<array<string, mixed>> */
    public static function messages(PDO $pdo, int $userId, int $ticketId): array
    {
        self::ensureTables($pdo);
        $check = $pdo->prepare('SELECT id FROM support_tickets WHERE id = :id AND user_id = :user_id LIMIT 1');
        $check->execute(['id' => $ticketId, 'user_id' => $userId]);
        if ((int) $check->fetchColumn() <= 0) {
            throw new RuntimeException('Ticket bulunamadı.');
        }
        $stmt = $pdo->prepare(
            'SELECT id, sender_type, sender_name, message, created_at
             FROM support_ticket_messages WHERE ticket_id = :ticket_id ORDER BY created_at ASC'
        );
        $stmt->execute(['ticket_id' => $ticketId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public static function addMessage(PDO $pdo, int $userId, string $username, int $ticketId, string $message): bool
    {
        self::ensureTables($pdo);
        $message = trim($message);
        if ($message === '') {
            throw new InvalidArgumentException('message zorunludur.');
        }
        $check = $pdo->prepare('SELECT id FROM support_tickets WHERE id = :id AND user_id = :user_id LIMIT 1');
        $check->execute(['id' => $ticketId, 'user_id' => $userId]);
        if ((int) $check->fetchColumn() <= 0) {
            throw new RuntimeException('Ticket bulunamadı.');
        }
        $pdo->prepare(
            'INSERT INTO support_ticket_messages (ticket_id, sender_type, sender_name, message)
             VALUES (:ticket_id, :sender_type, :sender_name, :message)'
        )->execute([
            'ticket_id' => $ticketId,
            'sender_type' => 'member',
            'sender_name' => $username,
            'message' => $message,
        ]);
        $pdo->prepare('UPDATE support_tickets SET status = :status, updated_at = NOW() WHERE id = :id')
            ->execute(['status' => 'open', 'id' => $ticketId]);

        return true;
    }

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public static function listAdmin(PDO $pdo, int $page = 1, int $perPage = 25, string $status = ''): array
    {
        self::ensureTables($pdo);
        $page = max(1, $page);
        $perPage = min(100, max(10, $perPage));
        $offset = ($page - 1) * $perPage;
        $where = $status !== '' ? 'WHERE t.status = :status' : '';
        $bind = $status !== '' ? ['status' => $status] : [];
        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM support_tickets t ' . $where);
        $countStmt->execute($bind);
        $total = (int) $countStmt->fetchColumn();
        $sql = 'SELECT t.*, u.username, u.email, u.name, u.surname FROM support_tickets t
                LEFT JOIN users u ON u.id = t.user_id ' . $where . '
                ORDER BY t.updated_at DESC LIMIT :limit OFFSET :offset';
        $stmt = $pdo->prepare($sql);
        foreach ($bind as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['items' => is_array($items) ? $items : [], 'total' => $total];
    }

    /** @return array<string, mixed>|null */
    public static function getTicket(PDO $pdo, int $ticketId): ?array
    {
        self::ensureTables($pdo);
        $stmt = $pdo->prepare(
            'SELECT t.*, u.username, u.email, u.name, u.surname FROM support_tickets t
             LEFT JOIN users u ON u.id = t.user_id WHERE t.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $ticketId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public static function messagesForTicket(PDO $pdo, int $ticketId): array
    {
        self::ensureTables($pdo);
        $stmt = $pdo->prepare(
            'SELECT id, sender_type, sender_name, message, created_at
             FROM support_ticket_messages WHERE ticket_id = :ticket_id ORDER BY created_at ASC'
        );
        $stmt->execute(['ticket_id' => $ticketId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public static function adminReply(PDO $pdo, int $ticketId, string $adminName, string $message, bool $notifyMember = true): void
    {
        self::ensureTables($pdo);
        $message = trim($message);
        if ($message === '') {
            throw new InvalidArgumentException('message zorunludur.');
        }
        $ticket = self::getTicket($pdo, $ticketId);
        if ($ticket === null) {
            throw new RuntimeException('Ticket bulunamadı.');
        }
        $userId = (int) ($ticket['user_id'] ?? 0);
        $pdo->prepare(
            'INSERT INTO support_ticket_messages (ticket_id, sender_type, sender_name, message)
             VALUES (:ticket_id, :sender_type, :sender_name, :message)'
        )->execute([
            'ticket_id' => $ticketId,
            'sender_type' => 'admin',
            'sender_name' => $adminName,
            'message' => $message,
        ]);
        $pdo->prepare(
            'UPDATE support_tickets SET status = :status, assigned_to = :assigned_to, updated_at = NOW() WHERE id = :id'
        )->execute(['status' => 'answered', 'assigned_to' => $adminName, 'id' => $ticketId]);
        if ($notifyMember && $userId > 0) {
            admin_require_project_file('services/MemberNotificationService.php');
            MemberNotificationService::create(
                $pdo,
                $userId,
                'Destek yanıtı',
                'Destek talebinize yanıt verildi.',
                'support',
                '/support/tickets/' . $ticketId
            );
        }
    }

    public static function closeTicket(PDO $pdo, int $ticketId, string $adminName): void
    {
        self::ensureTables($pdo);
        $stmt = $pdo->prepare(
            'UPDATE support_tickets SET status = :status, assigned_to = :assigned_to, closed_at = NOW(), updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['status' => 'closed', 'assigned_to' => $adminName, 'id' => $ticketId]);
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Ticket bulunamadı.');
        }
    }
}
