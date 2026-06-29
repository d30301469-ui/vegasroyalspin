<?php

declare(strict_types=1);

$env = static function (array $keys, string $default = ''): string {
    foreach ($keys as $key) {
        if (function_exists('getenv')) {
            $value = getenv($key);
            if ($value !== false && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }
        if (isset($_ENV[$key]) && trim((string) $_ENV[$key]) !== '') {
            return trim((string) $_ENV[$key]);
        }
        if (isset($_SERVER[$key]) && trim((string) $_SERVER[$key]) !== '') {
            return trim((string) $_SERVER[$key]);
        }
    }

    return $default;
};

$db = [
    'host' => $env(['ADMIN_DB_HOST', 'DATABASE_HOST', 'DB_HOST'], '127.0.0.1'),
    'port' => (int) $env(['ADMIN_DB_PORT', 'DATABASE_PORT', 'DB_PORT'], '3306'),
    'database' => $env(['ADMIN_DB_DATABASE', 'DATABASE_NAME', 'DATABASE_DATABASE', 'DB_DATABASE'], ''),
    'username' => $env(['ADMIN_DB_USERNAME', 'DATABASE_USERNAME', 'DB_USERNAME'], 'root'),
    'password' => $env(['ADMIN_DB_PASSWORD', 'DATABASE_PASSWORD', 'DB_PASSWORD'], ''),
    'charset' => $env(['ADMIN_DB_CHARSET', 'DATABASE_CHARSET', 'DB_CHARSET'], 'utf8mb4'),
];

if (in_array(strtolower($env(['APP_ENV'], 'development')), ['production', 'prod'], true)) {
    foreach (['host', 'database', 'username', 'password'] as $requiredKey) {
        if (trim((string) $db[$requiredKey]) === '') {
            throw new RuntimeException(sprintf('Production admin database config requires a non-empty "%s" value.', $requiredKey));
        }
    }

    if (strtolower((string) $db['username']) === 'root') {
        throw new RuntimeException('Production admin database config must not use the root database user.');
    }
}

return [
    'name' => getenv('ADMIN_PANEL_NAME') ?: 'Nexthub Backoffice',
    'db' => $db,
    'session_key' => 'bo_backoffice_admin_user',
    'csrf_key' => 'bo_backoffice_admin_csrf',
    'modules' => [
        'users' => [
            'title' => 'Üyeler',
            'table' => 'users',
            'active' => 'datatable',
            'crumbs' => 'Members | Users',
            'columns' => ['id', 'name', 'surname', 'username', 'email', 'balance', 'bonus_balance', 'phone', 'is_verified', 'banned', 'created_at'],
            'search_placeholder' => 'Üye adı, email, telefon veya ID ara...',
        ],
        'kyc' => [
            'title' => 'KYC Talepleri',
            'table' => 'kyc_requests',
            'active' => 'datatable',
            'crumbs' => 'Members | KYC',
            'columns' => ['id', 'user_id', 'username', 'document_type', 'status', 'submitted_at', 'reviewed_at', 'reviewed_by', 'note'],
            'search_placeholder' => 'KYC durumu veya yorum ara...',
        ],
        'active-bonuses' => [
            'title' => 'Aktif Bonuslar',
            'table' => 'user_active_bonuses',
            'active' => 'datatable',
            'crumbs' => 'Members | Active Bonuses',
            'columns' => ['id', 'user_id', 'name', 'initial_amount', 'current_bonus_balance', 'status', 'deadline', 'created_at'],
            'search_placeholder' => 'Aktif bonus ara...',
        ],
        'frozen-accounts' => [
            'title' => 'Dondurulan Hesaplar',
            'table' => 'user_account_freeze',
            'active' => 'datatable',
            'crumbs' => 'Members | Frozen Accounts',
            'columns' => ['user_id', 'frozen_at'],
            'search_placeholder' => 'Kullanıcı ID ara...',
        ],
        'loyalty-levels' => [
            'title' => 'Sadakat Seviyeleri',
            'table' => 'loyalty_levels',
            'active' => 'datatable',
            'crumbs' => 'Loyalty | Levels',
            'columns' => ['id', 'code', 'name', 'min_points', 'cashback_rate', 'weekly_bonus_amount', 'sort_order', 'is_active', 'updated_at'],
            'search_placeholder' => 'Seviye adı, kod veya puan eşiği ara...',
        ],
        'loyalty-accounts' => [
            'title' => 'Üye Sadakat Puanları',
            'table' => 'user_loyalty_accounts',
            'active' => 'datatable',
            'crumbs' => 'Loyalty | Member Points',
            'columns' => ['id', 'user_id', 'username', 'level_code', 'points', 'lifetime_points', 'redeemable_points', 'last_activity_at', 'updated_at'],
            'search_placeholder' => 'Üye, seviye veya puan ara...',
        ],
        'loyalty-transactions' => [
            'title' => 'Sadakat Puan Hareketleri',
            'table' => 'loyalty_point_transactions',
            'active' => 'datatable',
            'crumbs' => 'Loyalty | Point Transactions',
            'columns' => ['id', 'user_id', 'username', 'type', 'points', 'source', 'reference_id', 'note', 'created_at'],
            'search_placeholder' => 'Üye, kaynak, referans veya işlem tipi ara...',
        ],
        'deposits' => [
            'title' => 'Yatırımlar',
            'table' => 'megapayz_transactions',
            'where' => "type = 'deposit'",
            'active' => 'datatable',
            'crumbs' => 'Finance | Deposits',
            'columns' => ['id', 'type', 'username', 'method', 'amount', 'status', 'trx', 'megapayz_transaction_id', 'created_at'],
            'search_placeholder' => 'Yatırım, üye adı soyadı veya referans ara...',
        ],
        'withdrawals' => [
            'title' => 'Çekimler',
            'table' => 'megapayz_transactions',
            'where' => "type = 'withdraw'",
            'active' => 'datatable',
            'crumbs' => 'Finance | Withdrawals',
            'columns' => ['id', 'type', 'username', 'method', 'amount', 'fee', 'status', 'trx', 'created_at'],
            'search_placeholder' => 'Çekim, üye adı soyadı veya referans ara...',
        ],
        'payment-methods' => [
            'title' => 'MegaPayz Metotları',
            'table' => 'megapayz_methods',
            'active' => 'datatable',
            'crumbs' => 'Finance | Methods',
            'columns' => ['id', 'method_key', 'name', 'type', 'deposit_enabled', 'withdraw_enabled', 'min_amount', 'max_amount', 'sort_order'],
            'search_placeholder' => 'Ödeme metodu ara...',
        ],
        'drakon-games' => [
            'title' => 'Drakon Oyunları',
            'table' => 'drakon_games',
            'active' => 'datatable',
            'crumbs' => 'Drakon | Catalog',
            'columns' => ['id', 'image_url', 'game_id', 'game_name', 'provider_name', 'type', 'is_active', 'synced_at'],
            'search_placeholder' => 'Drakon oyun adı, provider veya game_id ara...',
        ],
        'drakon-providers' => [
            'title' => 'Drakon Providerları',
            'table' => 'drakon_providers',
            'active' => 'datatable',
            'crumbs' => 'Drakon | Providers',
            'columns' => ['id', 'provider_code', 'provider_name', 'rtp', 'is_active', 'synced_at'],
            'search_placeholder' => 'Provider ara...',
        ],
        'drakon-transactions' => [
            'title' => 'Drakon İşlemleri',
            'table' => 'drakon_transactions',
            'active' => 'datatable',
            'crumbs' => 'Drakon | Transactions',
            'columns' => ['id', 'image_url', 'game_name', 'provider_name', 'user_full_name', 'username', 'txn_type', 'bet_amount', 'win_amount', 'after_balance', 'created_at'],
            'search_placeholder' => 'Kullanıcı, oyun, transaction, round veya user ara...',
        ],
        'drakon-webhook-logs' => [
            'title' => 'Drakon Webhook Logları',
            'table' => 'drakon_webhook_logs',
            'active' => 'datatable',
            'crumbs' => 'Drakon | Webhook Logs',
            'columns' => ['id', 'method', 'user_id', 'transaction_id', 'http_status', 'error_code', 'duration_ms', 'created_at'],
            'search_placeholder' => 'Webhook, transaction veya hata ara...',
        ],
        'bgaming-games' => [
            'title' => 'BGaming Oyunları',
            'table' => 'bgaming_games',
            'active' => 'datatable',
            'crumbs' => 'BGaming | Catalog',
            'columns' => ['id', 'thumbnail_url', 'identifier', 'title', 'provider', 'category', 'api_freespins', 'in_game_freespins', 'default_bet_cents', 'max_multiplier', 'rtp', 'is_active', 'synced_at'],
            'search_placeholder' => 'BGaming oyun adı, provider veya identifier ara...',
        ],
        'bgaming-transactions' => [
            'title' => 'BGaming İşlemleri',
            'table' => 'bgaming_transactions',
            'active' => 'datatable',
            'crumbs' => 'BGaming | Transactions',
            'columns' => ['id', 'user_id', 'action_id', 'round_id', 'txn_type', 'amount', 'after_balance', 'processed_at'],
            'search_placeholder' => 'Action, round veya user ara...',
        ],
        'bgaming-wallet-logs' => [
            'title' => 'BGaming Wallet Logları',
            'table' => 'bgaming_wallet_logs',
            'active' => 'datatable',
            'crumbs' => 'BGaming | Wallet Logs',
            'columns' => ['id', 'endpoint', 'user_id', 'action_id', 'http_status', 'error_code', 'duration_ms', 'created_at'],
            'search_placeholder' => 'Endpoint, action veya hata ara...',
        ],
        'drakon-campaigns' => [
            'title' => 'Drakon Kampanyaları',
            'table' => 'drakon_campaigns',
            'active' => 'datatable',
            'crumbs' => 'Drakon | Campaigns',
            'columns' => ['id', 'campaign_code', 'vendor', 'currency_code', 'freespins_per_player', 'status', 'active', 'created_at'],
            'search_placeholder' => 'Kampanya veya vendor ara...',
        ],
        'promotions' => [
            'title' => 'Promosyonlar',
            'table' => 'promotions',
            'active' => 'datatable',
            'crumbs' => 'Content | Promotions',
            'columns' => ['id', 'title', 'type', 'status', 'bonus_amount', 'sort_order', 'start_date', 'end_date'],
            'search_placeholder' => 'Promosyon başlığı veya tip ara...',
        ],
        'sliders' => [
            'title' => 'Sliderlar',
            'table' => 'sliders',
            'active' => 'datatable',
            'crumbs' => 'Content | Sliders',
            'columns' => ['id', 'title', 'category', 'desktop_path', 'mobile_path', 'button_link', 'order', 'status', 'start_date', 'end_date', 'updated_at'],
            'search_placeholder' => 'Home, slot, live casino, BGaming slider başlığı veya kategori ara...',
        ],
        'auth-sliders' => [
            'title' => 'Auth Sliderları',
            'table' => 'auth_sliders',
            'active' => 'datatable',
            'crumbs' => 'Content | Login & Register Sliders',
            'columns' => ['id', 'title', 'screen', 'surface', 'sort_order', 'is_active', 'start_date', 'end_date', 'updated_at'],
            'search_placeholder' => 'Login/register slider başlığı, ekranı veya cihazı ara...',
        ],
        'homepage-sections' => [
            'title' => 'Homepage Section',
            'table' => 'homepage_sections',
            'active' => 'datatable',
            'crumbs' => 'Content | Homepage Sections',
            'columns' => ['id', 'section_key', 'title', 'type', 'surface', 'sort_order', 'is_active', 'start_date', 'end_date', 'updated_at'],
            'search_placeholder' => 'Bölüm anahtarı, başlık veya tip ara...',
        ],
        'footer-settings' => [
            'title' => 'Footer Section',
            'table' => 'footer_settings',
            'active' => 'datatable',
            'crumbs' => 'Content | Footer',
            'columns' => ['id', 'name', 'is_active', 'updated_at'],
            'search_placeholder' => 'Footer ayarı ara...',
        ],
        'mobile-menu-settings' => [
            'title' => 'Mobil Menü Yönetimi',
            'table' => 'mobile_menu_settings',
            'active' => 'datatable',
            'crumbs' => 'Content | Mobile Menu',
            'columns' => ['id', 'name', 'is_active', 'updated_at'],
            'search_placeholder' => 'Mobil menü ayarı ara...',
        ],
        'footer-pages' => [
            'title' => 'Footer Sayfaları',
            'table' => 'footer_pages',
            'active' => 'datatable',
            'crumbs' => 'Content | Footer Pages',
            'columns' => ['id', 'title', 'slug', 'is_active', 'sort_order', 'updated_at'],
            'search_placeholder' => 'Footer sayfası ara...',
        ],
        'bonus-claims' => [
            'title' => 'Bonus Talepleri',
            'table' => 'bonus_claim_requests',
            'active' => 'datatable',
            'crumbs' => 'Content | Bonus Claims',
            'columns' => ['id', 'user_id', 'bonus_name', 'requested_amount', 'status', 'processed_by', 'created_at'],
            'search_placeholder' => 'Bonus talebi ara...',
        ],
        'promocodes' => [
            'title' => 'Promocode',
            'table' => 'promocodes',
            'active' => 'datatable',
            'crumbs' => 'Content | Promocodes',
            'columns' => ['id', 'kod', 'miktar', 'son_gecerlilik_tarihi', 'kullanim_limiti', 'mevcut_kullanim'],
            'search_placeholder' => 'Promocode ara...',
        ],
        'announcements' => [
            'title' => 'Duyurular',
            'table' => 'announcements',
            'active' => 'datatable',
            'crumbs' => 'Content | Announcements',
            'columns' => ['id', 'title', 'type', 'priority', 'is_active', 'start_date', 'end_date', 'updated_at'],
            'search_placeholder' => 'Duyuru ara...',
        ],
        'site-settings' => [
            'title' => 'Site Ayarları',
            'table' => 'site_ayarlar',
            'active' => 'forms',
            'crumbs' => 'Site Yapısı | Ayarlar',
            'columns' => ['id', 'site_adi', 'site_aciklama', 'frontend_url', 'backend_url', 'updated_at'],
            'search_placeholder' => 'Site adı veya URL ara...',
        ],
        'admins' => [
            'title' => 'Adminler',
            'table' => 'admins',
            'active' => 'datatable',
            'crumbs' => 'Admin | Users',
            'columns' => ['id', 'username', 'email', 'role', 'twofa_enabled', 'created_at', 'updated_at'],
            'search_placeholder' => 'Admin email veya kullanıcı adı ara...',
        ],
        'logs' => [
            'title' => 'Admin Logları',
            'table' => 'admin_logs',
            'active' => 'basic-table',
            'crumbs' => 'Admin | Logs',
            'columns' => ['id', 'admin_username', 'action', 'entity_type', 'status', 'ip_address', 'created_at'],
            'search_placeholder' => 'Log ara...',
        ],
        'permissions' => [
            'title' => 'Admin Yetkileri',
            'table' => 'admin_permissions',
            'active' => 'datatable',
            'crumbs' => 'Admin | Permissions',
            'columns' => ['id', 'admin_id', 'page_key', 'granted', 'granted_by', 'granted_at'],
            'search_placeholder' => 'Yetki ara...',
        ],
        'sessions' => [
            'title' => 'Admin Oturumları',
            'table' => 'admin_sessions',
            'active' => 'datatable',
            'crumbs' => 'Admin | Sessions',
            'columns' => ['id', 'admin_id', 'username', 'email', 'role', 'is_active', 'last_activity', 'expired_at'],
            'search_placeholder' => 'Oturum ara...',
        ],
        'call-requests' => [
            'title' => 'Aranma Talepleri',
            'table' => 'call_me_requests',
            'active' => 'datatable',
            'crumbs' => 'Communications | Call Requests',
            'columns' => ['id', 'full_name', 'username', 'phone', 'email', 'preferred_time', 'status', 'created_at'],
            'search_placeholder' => 'İsim, kullanıcı, telefon veya durum ara...',
        ],
    ],
    'navigation' => [
        [
            'label' => 'Dashboard',
            'caption' => 'Genel bakış',
            'items' => [
                ['key' => 'dashboard', 'text' => 'Gösterge Paneli', 'url' => '/dashboard', 'active' => 'dashboard', 'icon' => '<path d="M3 12 12 3l9 9"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/>'],
                ['key' => 'backoffice-suite', 'text' => 'Backoffice Suite', 'url' => '/backoffice-suite', 'active' => 'backoffice-suite', 'permission' => 'dashboard', 'icon' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>'],
            ],
        ],
        [
            'label' => 'Üye Operasyonları',
            'caption' => 'Üyeler, KYC ve bonus talepleri',
            'items' => [
                ['key' => 'users', 'text' => 'Üyeler', 'url' => '/module?key=users', 'active' => 'datatable', 'module' => 'users', 'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
                ['key' => 'kyc', 'text' => 'KYC Talepleri', 'url' => '/module?key=kyc', 'active' => 'datatable', 'module' => 'kyc', 'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>'],
                ['key' => 'kyc-review', 'text' => 'KYC İnceleme', 'url' => '/kyc/review', 'active' => 'kyc-review', 'permission' => 'kyc', 'icon' => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>'],
                ['key' => 'active-bonuses', 'text' => 'Aktif Bonuslar', 'url' => '/module?key=active-bonuses', 'active' => 'datatable', 'module' => 'active-bonuses', 'icon' => '<path d="M20 12v10H4V12"/><path d="M2 7h20v5H2z"/><path d="M12 22V7"/>'],
                ['key' => 'bonus-claims', 'text' => 'Bonus Talepleri', 'url' => '/module?key=bonus-claims', 'active' => 'datatable', 'module' => 'bonus-claims', 'icon' => '<path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6"/>'],
                ['key' => 'frozen-accounts', 'text' => 'Dondurulan Hesaplar', 'url' => '/module?key=frozen-accounts', 'active' => 'datatable', 'module' => 'frozen-accounts', 'icon' => '<path d="M12 2v20M4.93 4.93l14.14 14.14M2 12h20M4.93 19.07 19.07 4.93"/>'],
            ],
        ],
        [
            'label' => 'Sadakat Sistemi',
            'caption' => 'Seviye, puan ve üye sadakati',
            'items' => [
                ['key' => 'loyalty-levels', 'text' => 'Sadakat Seviyeleri', 'url' => '/module?key=loyalty-levels', 'active' => 'datatable', 'module' => 'loyalty-levels', 'icon' => '<path d="M12 2 15 8l6 .9-4.5 4.3 1.1 6.1L12 16.4 6.4 19.3l1.1-6.1L3 8.9 9 8z"/>'],
                ['key' => 'loyalty-accounts', 'text' => 'Üye Puanları', 'url' => '/module?key=loyalty-accounts', 'active' => 'datatable', 'module' => 'loyalty-accounts', 'icon' => '<circle cx="12" cy="8" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/><path d="m17 11 2 2 4-5"/>'],
                ['key' => 'loyalty-transactions', 'text' => 'Puan Hareketleri', 'url' => '/module?key=loyalty-transactions', 'active' => 'datatable', 'module' => 'loyalty-transactions', 'icon' => '<path d="M3 3v18h18"/><path d="M7 15h3v3H7zM11 11h3v7h-3zM15 7h3v11h-3z"/>'],
            ],
        ],
        [
            'label' => 'Finans',
            'caption' => 'Yatırım, çekim ve ödeme ayarları',
            'items' => [
                ['key' => 'deposits', 'text' => 'Yatırımlar', 'url' => '/module?key=deposits', 'active' => 'datatable', 'module' => 'deposits', 'icon' => '<path d="M12 3v12"/><path d="m17 10-5 5-5-5"/><path d="M5 21h14"/>'],
                ['key' => 'withdrawals', 'text' => 'Çekimler', 'url' => '/module?key=withdrawals', 'active' => 'datatable', 'module' => 'withdrawals', 'icon' => '<path d="M12 21V9"/><path d="m7 14 5-5 5 5"/><path d="M5 3h14"/>'],
                ['key' => 'payment-providers', 'text' => 'MegaPayz Ayarları', 'url' => '/megapayz/settings', 'active' => 'datatable', 'module' => 'payment-providers', 'permission' => 'payment-providers', 'icon' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h2M10 15h4"/>'],
                ['key' => 'payment-methods', 'text' => 'Ödeme Metotları', 'url' => '/megapayz/methods', 'active' => 'datatable', 'module' => 'payment-methods', 'icon' => '<path d="M4 7h16M4 12h16M4 17h10"/><circle cx="18" cy="17" r="2"/>'],
            ],
        ],
        [
            'label' => 'Raporlar',
            'caption' => 'Finans, grafik ve operasyon takvimi',
            'items' => [
                ['key' => 'reports-financial', 'text' => 'Finansal Rapor', 'url' => '/reports/financial', 'active' => 'reports-financial', 'permission' => 'deposits', 'icon' => '<path d="M3 3v18h18"/><path d="M7 15h3v3H7zM11 11h3v7h-3zM15 7h3v11h-3z"/>'],
                ['key' => 'reports-charts', 'text' => 'Grafikler', 'url' => '/reports/charts', 'active' => 'reports-charts', 'permission' => 'dashboard', 'icon' => '<path d="M12 20V10M18 20V4M6 20v-4"/>'],
                ['key' => 'reports-calendar', 'text' => 'Operasyon Takvimi', 'url' => '/reports/calendar', 'active' => 'reports-calendar', 'permission' => 'dashboard', 'icon' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>'],
            ],
        ],
        [
            'label' => 'Oyun Sağlayıcıları',
            'caption' => 'Drakon ve BGaming katalogları',
            'items' => [
                ['key' => 'drakon-settings', 'text' => 'Drakon Ayarları', 'url' => '/drakon/settings', 'active' => 'datatable', 'module' => 'drakon-settings', 'icon' => '<circle cx="12" cy="12" r="3"/><path d="M4 12a8 8 0 0 1 8-8"/><path d="M20 12a8 8 0 0 1-8 8"/><path d="m8 16-4-4 4-4"/><path d="m16 8 4 4-4 4"/>'],
                ['key' => 'drakon-providers', 'text' => 'Drakon Providerları', 'url' => '/module?key=drakon-providers', 'active' => 'datatable', 'module' => 'drakon-providers', 'icon' => '<path d="M4 7h16M4 12h16M4 17h16"/>'],
                ['key' => 'drakon-games', 'text' => 'Drakon Oyunları', 'url' => '/module?key=drakon-games', 'active' => 'datatable', 'module' => 'drakon-games', 'icon' => '<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 12h4M8 10v4M15 11h.01M18 13h.01"/>'],
                ['key' => 'drakon-transactions', 'text' => 'Drakon İşlemleri', 'url' => '/module?key=drakon-transactions', 'active' => 'datatable', 'module' => 'drakon-transactions', 'icon' => '<path d="M3 3v18h18"/><path d="m7 14 3-3 3 2 4-6"/>'],
                ['key' => 'drakon-webhook-logs', 'text' => 'Drakon Webhook', 'url' => '/module?key=drakon-webhook-logs', 'active' => 'datatable', 'module' => 'drakon-webhook-logs', 'icon' => '<path d="M4 4h16v16H4z"/><path d="M8 9h8M8 13h8M8 17h5"/>'],
                ['key' => 'drakon-campaigns', 'text' => 'Drakon Kampanyaları', 'url' => '/module?key=drakon-campaigns', 'active' => 'datatable', 'module' => 'drakon-campaigns', 'icon' => '<path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2v-7"/><path d="M2 7h20v5H2z"/><path d="M12 22V7"/>'],
                ['key' => 'bgaming-settings', 'text' => 'BGaming Ayarları', 'url' => '/bgaming/settings', 'active' => 'datatable', 'module' => 'bgaming-settings', 'icon' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/><path d="m4.9 4.9 2.8 2.8M16.3 16.3l2.8 2.8M19.1 4.9l-2.8 2.8M7.7 16.3l-2.8 2.8"/>'],
                ['key' => 'bgaming-games', 'text' => 'BGaming Oyunları', 'url' => '/module?key=bgaming-games', 'active' => 'datatable', 'module' => 'bgaming-games', 'icon' => '<rect x="2" y="5" width="20" height="13" rx="2"/><path d="M8 22h8M12 18v4"/>'],
                ['key' => 'bgaming-transactions', 'text' => 'BGaming İşlemleri', 'url' => '/module?key=bgaming-transactions', 'active' => 'datatable', 'module' => 'bgaming-transactions', 'icon' => '<path d="M3 3v18h18"/><path d="m7 14 3-3 3 2 4-6"/>'],
                ['key' => 'bgaming-wallet-logs', 'text' => 'BGaming Wallet Logları', 'url' => '/module?key=bgaming-wallet-logs', 'active' => 'datatable', 'module' => 'bgaming-wallet-logs', 'icon' => '<path d="M4 4h16v16H4z"/><path d="M8 9h8M8 13h8M8 17h5"/>'],
            ],
        ],
        [
            'label' => 'İçerik & Pazarlama',
            'caption' => 'Kampanya, slider ve ana sayfa yönetimi',
            'items' => [
                ['key' => 'promotions', 'text' => 'Promosyonlar', 'url' => '/promotions', 'active' => 'promotions', 'module' => 'promotions', 'icon' => '<path d="M20 12v10H4V12"/><path d="M2 7h20v5H2z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z"/>'],
                ['key' => 'promocodes', 'text' => 'Promocode', 'url' => '/module?key=promocodes', 'active' => 'datatable', 'module' => 'promocodes', 'icon' => '<path d="M21 10V8a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v2a2 2 0 1 1 0 4v2a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-2a2 2 0 1 1 0-4z"/><path d="M8 9h.01M16 15h.01M9 16l6-8"/>'],
                ['key' => 'sliders', 'text' => 'Sliderlar', 'url' => '/module?key=sliders', 'active' => 'datatable', 'module' => 'sliders', 'icon' => '<rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 21h8M12 18v3"/><circle cx="8" cy="10" r="1"/><path d="m21 15-5-5L5 18"/>'],
                ['key' => 'auth-sliders', 'text' => 'Auth Sliderları', 'url' => '/module?key=auth-sliders', 'active' => 'datatable', 'module' => 'auth-sliders', 'icon' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M8 12h8"/><path d="M10 9h4"/><path d="M7 17l3-3 2 2 4-5 1 6"/>'],
                ['key' => 'homepage-sections', 'text' => 'Homepage Section', 'url' => '/homepage-sections', 'active' => 'datatable', 'module' => 'homepage-sections', 'icon' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10M7 12h4M14 12h3M7 16h10"/>'],
                ['key' => 'announcements', 'text' => 'Duyurular', 'url' => '/module?key=announcements', 'active' => 'datatable', 'module' => 'announcements', 'icon' => '<path d="M3 11v3a2 2 0 0 0 2 2h3l7 4V5L8 9H5a2 2 0 0 0-2 2z"/><path d="M19 9a3 3 0 0 1 0 6"/>'],
            ],
        ],
        [
            'label' => 'Site Yapısı',
            'caption' => 'Footer, mobil menü ve site ayarları',
            'items' => [
                ['key' => 'footer-settings', 'text' => 'Footer Section', 'url' => '/footer', 'active' => 'datatable', 'module' => 'footer-settings', 'icon' => '<path d="M4 5h16v10H4z"/><path d="M4 19h16"/><path d="M8 15v4M16 15v4"/>'],
                ['key' => 'footer-pages', 'text' => 'Footer Sayfaları', 'url' => '/module?key=footer-pages', 'active' => 'datatable', 'module' => 'footer-pages', 'icon' => '<path d="M4 19.5V5a2 2 0 0 1 2-2h11"/><path d="M8 7h8M8 11h8M8 15h5"/>'],
                ['key' => 'mobile-menu-settings', 'text' => 'Mobil Menü Yönetimi', 'url' => '/mobile-menu', 'active' => 'datatable', 'module' => 'mobile-menu-settings', 'icon' => '<path d="M4 6h16M4 12h16M4 18h16"/><circle cx="7" cy="6" r="1"/><circle cx="7" cy="12" r="1"/><circle cx="7" cy="18" r="1"/>'],
                ['key' => 'site-settings', 'text' => 'Site Ayarları', 'url' => '/site-settings', 'active' => 'forms', 'module' => 'site-settings', 'icon' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.6-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1A2 2 0 1 1 7.1 4l.1.1a1.7 1.7 0 0 0 1.9.3h.1a1.7 1.7 0 0 0 1-1.6V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1A2 2 0 1 1 20 7.1l-.1.1a1.7 1.7 0 0 0-.3 1.9v.1a1.7 1.7 0 0 0 1.6 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>'],
            ],
        ],
        [
            'label' => 'İletişim',
            'caption' => 'Aranma talepleri ve e-posta',
            'items' => [
                ['key' => 'call-requests', 'text' => 'Aranma Talepleri', 'url' => '/module?key=call-requests', 'active' => 'datatable', 'module' => 'call-requests', 'icon' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2A19.79 19.79 0 0 1 11.1 19a19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-2.92-8.75A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.32 1.77.6 2.61a2 2 0 0 1-.45 2.11L8 9.71a16 16 0 0 0 6.29 6.29l1.27-1.27a2 2 0 0 1 2.11-.45c.84.28 1.71.48 2.61.6A2 2 0 0 1 22 16.92z"/>'],
                ['key' => 'chat', 'text' => 'Canlı Talepler', 'url' => '/chat', 'active' => 'chat', 'permission' => 'email', 'icon' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'],
                ['key' => 'email', 'text' => 'E-posta', 'url' => '/email', 'active' => 'email', 'icon' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>'],
                ['key' => 'compose', 'text' => 'Mesaj Yaz', 'url' => '/compose', 'active' => 'compose', 'permission' => 'email', 'icon' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 1 1 3 3L7 19l-4 1 1-4z"/>'],
                ['key' => 'support-tickets', 'text' => 'Destek Talepleri', 'url' => '/support/tickets', 'active' => 'datatable', 'module' => 'support-tickets', 'icon' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'],
                ['key' => 'member-notifications', 'text' => 'Üye Bildirimleri', 'url' => '/notifications', 'active' => 'datatable', 'module' => 'member-notifications', 'icon' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>'],
            ],
        ],
        [
            'label' => 'Uyumluluk & Risk',
            'caption' => 'AML ve operasyonel risk uyarıları',
            'items' => [
                ['key' => 'compliance-aml', 'text' => 'AML Uyarıları', 'url' => '/compliance/aml-alerts', 'active' => 'datatable', 'module' => 'compliance-aml', 'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M12 8v4M12 16h.01"/>'],
                ['key' => 'compliance-risk', 'text' => 'Risk Uyarıları', 'url' => '/compliance/risk-alerts', 'active' => 'datatable', 'module' => 'compliance-risk', 'icon' => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/>'],
                ['key' => 'compliance-audit', 'text' => 'Denetim Logu', 'url' => '/compliance/audit-log', 'active' => 'compliance-audit', 'permission' => 'logs', 'icon' => '<path d="M3 3v18h18"/><path d="M7 8h10M7 12h10M7 16h6"/>'],
            ],
        ],
        [
            'label' => 'Admin & Güvenlik',
            'caption' => 'Yetki, oturum ve log yönetimi',
            'items' => [
                ['key' => 'admins', 'text' => 'Adminler', 'url' => '/module?key=admins', 'active' => 'datatable', 'module' => 'admins', 'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'],
                ['key' => 'admin-signup', 'text' => 'Yeni Admin', 'url' => '/signup', 'active' => 'signup', 'permission' => 'admins', 'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>'],
                ['key' => 'permissions', 'text' => 'Admin Yetkileri', 'url' => '/permissions', 'active' => 'datatable', 'module' => 'permissions', 'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/>'],
                ['key' => 'sessions', 'text' => 'Admin Oturumları', 'url' => '/module?key=sessions', 'active' => 'datatable', 'module' => 'sessions', 'icon' => '<rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 21h8M12 18v3"/>'],
                ['key' => 'logs', 'text' => 'Admin Logları', 'url' => '/module?key=logs', 'active' => 'basic-table', 'module' => 'logs', 'icon' => '<path d="M3 3v18h18"/><path d="M7 8h10M7 12h10M7 16h6"/>'],
            ],
        ],
    ],
];
