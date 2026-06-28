<?php

declare(strict_types=1);

final class MemberKycService
{
    private const ALLOWED_TYPES = ['identity', 'address', 'source_of_funds', 'selfie', 'other'];

    public static function ensureTables(PDO $pdo): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $core = dirname(__DIR__) . '/database/migrations/2026_06_10_000000_create_core_member_tables.php';
        if (is_readable($core)) {
            $runner = require $core;
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
            'SELECT id, document_type, document_path, status, note, submitted_at, reviewed_at
             FROM kyc_requests WHERE user_id = :user_id ORDER BY submitted_at DESC LIMIT 50'
        );
        $stmt->execute(['user_id' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public static function submitDocument(PDO $pdo, int $userId, string $username, array $input): array
    {
        self::ensureTables($pdo);
        $type = strtolower(trim((string) ($input['document_type'] ?? $input['type'] ?? 'identity')));
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            $type = 'identity';
        }
        $documentPath = trim((string) ($input['document_path'] ?? $input['document_url'] ?? ''));
        if ($documentPath === '' && !empty($input['document_base64'])) {
            $documentPath = self::storeBase64Document($userId, (string) $input['document_base64'], (string) ($input['file_name'] ?? 'document.jpg'));
        }
        if ($documentPath === '') {
            throw new InvalidArgumentException('document_path veya document_base64 zorunludur.');
        }
        $stmt = $pdo->prepare(
            'INSERT INTO kyc_requests (user_id, username, document_type, document_path, status, submitted_at)
             VALUES (:user_id, :username, :document_type, :document_path, :status, NOW())'
        );
        $stmt->execute([
            'user_id' => $userId,
            'username' => $username,
            'document_type' => $type,
            'document_path' => $documentPath,
            'status' => 'pending',
        ]);

        return [
            'id' => (int) $pdo->lastInsertId(),
            'document_type' => $type,
            'document_path' => $documentPath,
            'status' => 'pending',
        ];
    }

    private static function storeBase64Document(int $userId, string $base64, string $fileName): string
    {
        $raw = preg_replace('#^data:[^;]+;base64,#', '', $base64) ?? $base64;
        $binary = base64_decode($raw, true);
        if ($binary === false || $binary === '') {
            throw new InvalidArgumentException('Geçersiz document_base64.');
        }
        if (strlen($binary) > 8 * 1024 * 1024) {
            throw new InvalidArgumentException('Belge boyutu 8MB sınırını aşıyor.');
        }
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $ext = preg_replace('/[^a-zA-Z0-9]/', '', (string) $ext) ?: 'jpg';
        $dir = dirname(__DIR__) . '/storage/uploads/kyc/' . $userId;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('KYC yükleme dizini oluşturulamadı.');
        }
        $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (file_put_contents($dir . '/' . $name, $binary) === false) {
            throw new RuntimeException('Belge kaydedilemedi.');
        }

        return '/storage/uploads/kyc/' . $userId . '/' . $name;
    }
}
