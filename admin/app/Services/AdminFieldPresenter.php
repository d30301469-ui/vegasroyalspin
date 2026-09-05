<?php

declare(strict_types=1);

final class AdminFieldPresenter
{
    public static function label(string $column, string $moduleKey = ''): string
    {
        $label = match ($column) {
            'username' => in_array($moduleKey, ['deposits', 'withdrawals'], true) ? 'Üye Ad Soyad' : 'Kullanıcı',
            'full_name' => 'İsim Soyisim',
            'image_url' => 'Resim',
            'game_id' => 'Oyun ID',
            'game_name' => 'Oyun Adı',
            'provider_name' => 'Sağlayıcı',
            'txn_type' => str_starts_with($moduleKey, 'sportsbook') ? 'İşlem Türü' : 'Kazanç/Kayıp',
            'code' => 'Kod',
            'level_code' => 'Seviye',
            'min_points' => 'Minimum Puan',
            'points' => 'Puan',
            'lifetime_points' => 'Toplam Puan',
            'redeemable_points' => 'Kullanılabilir Puan',
            'cashback_rate' => 'Cashback %',
            'weekly_bonus_amount' => 'Haftalık Bonus',
            'source' => 'Kaynak',
            'reference_id' => 'Referans',
            default => str_replace('_', ' ', $column),
        };

        return self::isMoneyColumn($column) ? $label . ' (₺)' : $label;
    }

    public static function format(string $column, mixed $value, int $limit = 80, string $moduleKey = ''): string
    {
        if ($column === 'txn_type') {
            $value = strtolower(trim((string) $value));
            // Sportsbook ledger: bet = stake debit (not a settled loss). Casino uses bet/win as Kayip/Kazanc.
            if (str_starts_with($moduleKey, 'sportsbook')) {
                return match ($value) {
                    'bet', 'promo_bet' => 'Bahis',
                    'win', 'promo_win', 'freespins_win' => 'Kazanç',
                    'cancel', 'rollback' => 'İade',
                    default => $value !== '' ? ucfirst($value) : '-',
                };
            }
            return match ($value) {
                'bet', 'promo_bet' => 'Kayıp',
                'win', 'promo_win', 'freespins_win' => 'Kazanç',
                'cancel', 'rollback' => 'İptal',
                default => $value !== '' ? ucfirst($value) : '-',
            };
        }

        if ($column === 'status' && $moduleKey === 'active-bonuses') {
            $status = strtolower(trim((string) $value));

            return match ($status) {
                'active' => 'Aktif',
                'completed' => 'Tamamlandı',
                'expired' => 'Sonlandı',
                'cancelled', 'canceled' => 'İptal',
                default => $status !== '' ? $status : '-',
            };
        }

        return AdminDataRedactor::format($column, $value, $limit);
    }

    public static function isMoneyColumn(string $column): bool
    {
        return preg_match('/amount|balance|fee|price|total/i', $column) === 1;
    }
}
