<?php

/**
 * GET /api/v2/announcements.php — public · envelope (api.md).
 */
final class ApiAnnouncements
{
    /**
     * @param array<string, mixed> $get
     * @return array<string, string|int>
     */
    public static function normalizeQuery(array $get): array
    {
        $actionRaw = $get['action'] ?? 'all';
        $action    = is_string($actionRaw) ? strtolower(trim($actionRaw)) : 'all';
        $allowed   = ['active', 'featured', 'type', 'all'];
        if (!in_array($action, $allowed, true)) {
            $action = 'all';
        }

        $query = ['action' => $action];

        if ($action === 'featured') {
            $limit = isset($get['limit']) ? (int) $get['limit'] : 10;
            $limit = max(1, min(100, $limit));
            $query['limit'] = $limit;
        }

        if ($action === 'type') {
            $type = isset($get['type']) ? trim((string) $get['type']) : '';
            if ($type !== '') {
                $query['type'] = $type;
            }
        }

        return $query;
    }

    /**
     * @param array<string, string|int> $query
     * @return array<string, mixed>|null
     */
    public static function fetchEnvelope(array $query): ?array
    {
        return ApiMemberApi::relayGet(
            MemberApiPaths::ANNOUNCEMENTS,
            $query,
            30,
            null
        );
    }
}
