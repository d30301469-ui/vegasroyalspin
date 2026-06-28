<?php

declare(strict_types=1);

final class MemberNotificationService
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

    /** @return array{items: list<array<string, mixed>>, unread: int} */
    public static function listForUser(PDO $pdo, int $userId, int $limit = 50): array
    {
        self::ensureTables($pdo);
        $limit = min(100, max(1, $limit));
        $stmt = $pdo->prepare(
            'SELECT id, type, title, body, action_url, is_read, read_at, created_at
             FROM member_notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $unreadStmt = $pdo->prepare('SELECT COUNT(*) FROM member_notifications WHERE user_id = :user_id AND is_read = 0');
        $unreadStmt->execute(['user_id' => $userId]);

        return ['items' => is_array($items) ? $items : [], 'unread' => (int) $unreadStmt->fetchColumn()];
    }

    public static function markRead(PDO $pdo, int $userId, int $notificationId): bool
    {
        self::ensureTables($pdo);
        $stmt = $pdo->prepare(
            'UPDATE member_notifications SET is_read = 1, read_at = NOW()
             WHERE id = :id AND user_id = :user_id AND is_read = 0'
        );
        $stmt->execute(['id' => $notificationId, 'user_id' => $userId]);

        return $stmt->rowCount() > 0;
    }

    public static function markAllRead(PDO $pdo, int $userId): int
    {
        self::ensureTables($pdo);
        $stmt = $pdo->prepare(
            'UPDATE member_notifications SET is_read = 1, read_at = NOW() WHERE user_id = :user_id AND is_read = 0'
        );
        $stmt->execute(['user_id' => $userId]);

        return $stmt->rowCount();
    }

    public static function create(PDO $pdo, int $userId, string $title, string $body = '', string $type = 'info', ?string $actionUrl = null): int
    {
        self::ensureTables($pdo);
        $stmt = $pdo->prepare(
            'INSERT INTO member_notifications (user_id, type, title, body, action_url)
             VALUES (:user_id, :type, :title, :body, :action_url)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
        ]);

        return (int) $pdo->lastInsertId();
    }
}
