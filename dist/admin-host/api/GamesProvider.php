<?php

/**
 * GET games_provider — public, JWT yok (api.md). Temiz: games-provider.
 */
final class ApiGamesProvider
{
    /**
     * Ham backend zarfı; hata / boşta null.
     *
     * @return array<string, mixed>|null
     */
    public static function fetchEnvelope(): ?array
    {
        return ApiMemberApi::relayGet(MemberApiPaths::GAMES_PROVIDER, [], 20, null);
    }

    /**
     * Oyun türüne göre sağlayıcı satırları.
     *
     * @return list<array{provider: string, game_type: int, category: string}>
     */
    public static function itemsByGameType(int $gameType, ?string $category = null): array
    {
        $env   = self::fetchEnvelope();
        $items = ApiEnvelope::listFromData($env, 'items');
        if ($items === null) {
            return [];
        }

        $out = [];
        foreach ($items as $row) {
            if (!is_array($row)) {
                continue;
            }
            $gt  = array_key_exists('game_type', $row) ? (int) $row['game_type'] : 0;
            $cat = isset($row['category']) ? (string) $row['category'] : 'slots';
            if ($gt !== $gameType || ($category !== null && $cat !== $category)) {
                continue;
            }
            $p = trim((string) ($row['provider'] ?? ''));
            if ($p === '') {
                continue;
            }
            $out[] = ['provider' => $p, 'game_type' => $gt, 'category' => $cat];
        }

        return $out;
    }

    /**
     * Slot sayfası: yalnızca slot satırları (game_type 0, category slots).
     *
     * @return list<array{provider: string, game_type: int, category: string}>
     */
    public static function slotItems(): array
    {
        return self::itemsByGameType(0, 'slots');
    }

    /**
     * Benzersiz sağlayıcı görünen adları (sıra korunur, tekrar yok).
     *
     * @return list<string>
     */
    public static function slotProviderNames(): array
    {
        return self::providerNamesByGameType(0, 'slots');
    }

    /**
     * Benzersiz sağlayıcı görünen adları (sıra korunur, tekrar yok).
     *
     * @return list<string>
     */
    public static function providerNamesByGameType(int $gameType, ?string $category = null): array
    {
        $seen = [];
        $list = [];
        foreach (self::itemsByGameType($gameType, $category) as $row) {
            $p = $row['provider'];
            if (isset($seen[$p])) {
                continue;
            }
            $seen[$p] = true;
            $list[]   = $p;
        }

        return $list;
    }
}
