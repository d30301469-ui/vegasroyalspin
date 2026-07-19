<?php

$pageTitle = isset($title) ? (string) $title : 'Admin';
// NOT: 'dashboard' varsayılanı kullanılmamalı. 403/404/500 gibi hata sayfaları
// 'active' göndermez; varsayılan 'dashboard' olsaydı sidebar'da "Dashboard"
// yanlışlıkla aktif görünür ve kullanıcı erişim reddedilen sayfadan
// dashboard'a yönlendirilmiş gibi bir izlenime kapılırdı (bkz. footer bug).
$activePage = isset($active) ? (string) $active : '';
$crumbText = isset($crumbs) ? (string) $crumbs : 'Admin';
$panelName = (string) ($config['name'] ?? 'Backoffice');
$currentModuleKey = isset($moduleKey) ? (string) $moduleKey : '';
$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$requestPath = is_string($requestPath) && $requestPath !== '' ? $requestPath : '/';
$adminRootPath = parse_url(AdminAuth::url('/'), PHP_URL_PATH);
$adminRootPath = is_string($adminRootPath) ? rtrim($adminRootPath, '/') : '';
$normalizeAdminPath = static function (string $path) use ($adminRootPath): string {
    $clean = '/' . ltrim($path, '/');
    if ($adminRootPath !== '' && $adminRootPath !== '/' && str_starts_with($clean, $adminRootPath . '/')) {
        $clean = substr($clean, strlen($adminRootPath));
    }

    return '/' . ltrim($clean, '/');
};
$currentPath = $normalizeAdminPath($requestPath);
$rawNavigation = is_array($config['navigation'] ?? null) ? $config['navigation'] : [];
$currentUser = AdminAuth::user();
$adminFlashMessage = trim((string) ($flash ?? ''));
$adminErrorMessage = trim((string) ($error ?? ''));
$adminFlashType = 'info';
if ($adminFlashMessage !== '') {
    $adminFlashLower = function_exists('mb_strtolower')
        ? mb_strtolower($adminFlashMessage, 'UTF-8')
        : strtolower($adminFlashMessage);
    $adminFlashType = str_contains($adminFlashLower, 'hata')
        || str_contains($adminFlashLower, 'bulunamadı')
        || str_contains($adminFlashLower, 'geçersiz')
        || str_contains($adminFlashLower, 'başarısız')
        || str_contains($adminFlashLower, 'olmadı')
        || str_contains($adminFlashLower, 'edilemedi')
        || str_contains($adminFlashLower, 'tamamlanamadı')
        || str_contains($adminFlashLower, 'oluşturulamadı')
        || str_contains($adminFlashLower, 'eşleşmiyor')
        ? 'error'
        : (str_contains($adminFlashLower, 'uyarı') || str_contains($adminFlashLower, 'bekleyen') ? 'warning' : 'success');
}
$flash = '';
$isNavItemActive = static function (array $item) use ($activePage, $currentModuleKey, $currentPath, $normalizeAdminPath): bool {
    $url = trim((string) ($item['url'] ?? ''));
    if ($url !== '' && $url !== '#') {
        $itemPath = parse_url($url, PHP_URL_PATH);
        $itemPath = is_string($itemPath) && $itemPath !== '' ? $normalizeAdminPath($itemPath) : '';
        if ($itemPath !== '' && $itemPath !== '/module') {
            return $itemPath === $currentPath;
        }

        if ($itemPath === '/module' && $itemPath === $currentPath) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $itemQuery);
            $itemModuleKey = trim((string) ($itemQuery['key'] ?? ''));
            if ($itemModuleKey !== '') {
                return $currentModuleKey === $itemModuleKey;
            }

            return false;
        }
    }

    $module = (string) ($item['module'] ?? '');
    if ($module !== '') {
        return $currentModuleKey === $module;
    }

    return $activePage === (string) ($item['active'] ?? $item['key'] ?? '');
};
$canAccessItem = static function (array $item): bool {
    $permission = AdminAuth::navPermissionKey($item);
    if ($permission === '') {
        return false;
    }

    return AdminAuth::can($permission);
};
$navigation = [];
foreach ($rawNavigation as $group) {
    if (!is_array($group)) {
        continue;
    }
    $items = [];
    foreach ((array) ($group['items'] ?? []) as $item) {
        if (is_array($item) && $canAccessItem($item)) {
            $items[] = $item;
        }
    }
    if ($items !== []) {
        $group['items'] = $items;
        $navigation[] = $group;
    }
}
$layoutScalar = static function (string $sql): int {
    try {
        return (int) AdminDatabase::pdo()->query($sql)->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
};
$layoutRows = static function (string $sql): array {
    try {
        return AdminDatabase::pdo()->query($sql)->fetchAll();
    } catch (Throwable) {
        return [];
    }
};
$adminName = (string) ($currentUser['username'] ?? 'Admin');
$adminEmail = (string) ($currentUser['email'] ?? 'admin@backoffice.local');
$adminRole = (string) ($currentUser['role'] ?? 'admin');
$adminInitials = strtoupper(substr($adminName, 0, 2));
$canWithdrawals = AdminAuth::can('withdrawals');
$canKyc = AdminAuth::can('kyc');
$canEmail = AdminAuth::can('email');
$canLogs = AdminAuth::can('logs');
$canSiteSettings = AdminAuth::can('site-settings');
$canAdmins = AdminAuth::can('admins');
$notificationCount = ($canWithdrawals ? $layoutScalar("SELECT COUNT(*) FROM megapayz_transactions WHERE type = 'withdraw' AND status = 'pending'") : 0)
    + ($canKyc ? $layoutScalar("SELECT COUNT(*) FROM kyc_requests WHERE status = 'pending'") : 0);
$messageRows = $canEmail ? $layoutRows('SELECT * FROM member_inbox_messages ORDER BY created_at DESC LIMIT 3') : [];
$logRows = $canLogs ? $layoutRows('SELECT admin_username, action, status, created_at FROM admin_logs ORDER BY created_at DESC LIMIT 3') : [];
$paletteItems = [];
foreach ($navigation as $group) {
    foreach ((array) ($group['items'] ?? []) as $item) {
        $paletteItems[] = [
            'label' => (string) ($item['text'] ?? ''),
            'section' => (string) ($group['label'] ?? 'Admin'),
            'href' => AdminAuth::url((string) ($item['url'] ?? '/dashboard')),
            'icon' => (string) ($item['icon'] ?? ''),
        ];
    }
}
$navIconPalette = ['#60a5fa', '#8b5cf6', '#f59e0b', '#ef4444', '#94a3b8', '#38bdf8', '#22c55e', '#3b82f6', '#f97316', '#64748b', '#6366f1', '#a855f7', '#06b6d4'];
$navIconIndex = 0;
$adminUiVersion = (string) (@filemtime(ADMIN_BASE_PATH . '/admin-ui.js') ?: time());
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($pageTitle . ' · ' . $panelName, ENT_QUOTES, 'UTF-8') ?></title>
    <script>
        !function(){try{var t=localStorage.getItem("dash26-theme"),e=window.matchMedia("(prefers-color-scheme: dark)").matches;document.documentElement.setAttribute("data-theme",t||(e?"dark":"light"))}catch(t){document.documentElement.setAttribute("data-theme","light")}}()
    </script>
    <script defer src="<?= htmlspecialchars(AdminAuth::url('/runtime.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script defer src="<?= htmlspecialchars(AdminAuth::url('/vendor-fullcalendar.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script defer src="<?= htmlspecialchars(AdminAuth::url('/vendor-chartjs.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script defer src="<?= htmlspecialchars(AdminAuth::url('/vendors.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script defer src="<?= htmlspecialchars(AdminAuth::url('/2026.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script defer src="<?= htmlspecialchars(AdminAuth::url('/admin-ui.js?v=' . $adminUiVersion), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link href="<?= htmlspecialchars(AdminAuth::url('/style.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <style>
        .admin-table-fit .data-table th,
        .admin-table-fit .data-table td {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: middle;
        }
        .admin-table-fit .data-table td {
            white-space: normal;
        }
        .admin-table-fit .data-cell-user,
        .admin-table-fit .data-cell-user-meta {
            min-width: 0;
            max-width: 100%;
        }
        .admin-table-fit .data-cell-user-name,
        .admin-table-fit .data-cell-user-email,
        .admin-table-fit .data-cell-mono {
            display: block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .admin-table-fit .data-cell-actions {
            display: flex;
            justify-content: flex-end;
            gap: 4px;
            white-space: nowrap;
        }
        .admin-inline-form {
            display: inline;
        }
        .admin-stack {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .admin-stack.is-relaxed {
            gap: 14px;
        }
        .admin-search-md {
            width: 220px;
        }
        .admin-search-lg {
            width: 240px;
        }
        .admin-select-compact {
            width: auto;
            padding: 7px 28px 7px 10px;
            font-size: 12px;
        }
        .admin-action-spaced {
            margin-top: 16px;
        }
        .admin-action-spaced-lg {
            margin-top: 18px;
        }
        .admin-alert-spaced {
            margin-bottom: 16px;
        }
        .admin-full-action {
            justify-content: center;
            text-align: center;
            width: 100%;
        }
        .admin-date-input {
            color-scheme: light;
        }
        :root[data-theme=dark] .admin-date-input {
            color-scheme: dark;
        }
        .admin-date-input::-webkit-calendar-picker-indicator {
            cursor: pointer;
            opacity: .72;
        }
        .sidebar-brand {
            gap: 12px;
        }
        .sidebar-close {
            display: none;
            width: 36px;
            height: 36px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--bg-card);
            color: var(--t-base);
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .sidebar-close svg {
            width: 18px;
            height: 18px;
        }
        .nav-label-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            letter-spacing: .2em;
            text-transform: uppercase;
        }
        .nav-group-title {
            display: block;
            color: var(--t-muted);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .06em;
            line-height: 1.2;
            text-transform: uppercase;
        }
        .nav-caption {
            display: block;
            margin-top: 4px;
            color: var(--t-light);
            font-size: 11px;
            font-family: Inter, system-ui, sans-serif;
            font-weight: 600;
            letter-spacing: 0;
            line-height: 1.35;
            text-transform: none;
            white-space: normal;
        }
        .nav-link-text {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .nav-badge {
            margin-left: auto;
            padding: 2px 7px;
            border-radius: 999px;
            background: var(--primary-soft);
            color: var(--primary);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .02em;
            text-transform: uppercase;
        }
        .drawer-backdrop {
            display: none;
        }
        .admin-modal-backdrop {
            position: fixed;
            inset: 0;
            z-index: 120;
            display: grid;
            place-items: center;
            padding: 20px;
            background: rgba(15,23,42,.48);
            backdrop-filter: blur(8px);
        }
        .admin-modal {
            width: min(940px, 100%);
            max-height: min(86vh, 920px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: var(--bg-card);
            box-shadow: 0 24px 80px rgba(0,0,0,.28);
        }
        .admin-modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 1px solid var(--border);
            padding: 14px 16px;
        }
        .admin-modal-head h2 {
            margin: 0;
            color: var(--t-base);
            font-family: 'Inter Tight', Inter, sans-serif;
            font-size: 16px;
            font-weight: 800;
        }
        .admin-modal-close {
            width: 32px;
            height: 32px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--bg-muted);
            color: var(--t-base);
            cursor: pointer;
            font-size: 18px;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .admin-modal-body {
            overflow: auto;
            padding: 16px;
        }
        body.has-admin-modal {
            overflow: hidden;
        }
        .sidebar-footer .dd-wrap {
            width: 100%;
        }
        .sidebar-footer .workspace {
            width: 100%;
        }
        .sidebar-footer .dd-menu {
            bottom: calc(100% + 10px);
            left: 0;
            min-width: 216px;
            right: auto;
            top: auto;
            width: 100%;
        }
        body.has-drawer-open {
            overflow: hidden;
        }
        @media (max-width: 1280px) {
            .admin-table-fit .data-table th,
            .admin-table-fit .data-table td {
                padding-left: 8px;
                padding-right: 8px;
            }
            .admin-table-fit .data-cell-user .av {
                display: none;
            }
        }
        @media (max-width: 1024px) {
            .sidebar-close {
                display: inline-flex;
            }
            body.has-drawer-open .drawer-backdrop {
                display: block;
                position: fixed;
                inset: 0;
                z-index: 58;
                background: rgba(15, 23, 42, .45);
                backdrop-filter: blur(2px);
            }
        }
        .shell {
            grid-template-columns: 212px 1fr;
        }
        :root[data-theme=light] {
            --admin-shell-bg: #f3f6fb;
            --admin-sidebar-bg: #202124;
            --admin-sidebar-border: rgba(255,255,255,.06);
            --admin-sidebar-hover: rgba(255,255,255,.06);
            --admin-sidebar-active: #2f80ed;
            --admin-sidebar-title: #f7f7f8;
            --admin-sidebar-muted: rgba(255,255,255,.52);
            --admin-sidebar-label: rgba(255,255,255,.32);
            --admin-sidebar-link: rgba(255,255,255,.76);
            --admin-sidebar-link-hover: #ffffff;
            --admin-sidebar-active-text: #ffffff;
            --admin-sidebar-icon-active-bg: rgba(255,255,255,.18);
            --admin-sidebar-badge-bg: rgba(255,255,255,.18);
            --admin-sidebar-badge-text: #ffffff;
            --admin-sidebar-workspace-bg: rgba(255,255,255,.06);
            --admin-sidebar-workspace-hover: rgba(255,255,255,.09);
        }
        :root[data-theme=dark] {
            --bg-body: #18191c;
            --bg-card: #202124;
            --bg-hover: #2a2c31;
            --bg-muted: #26282d;
            --border: #34363d;
            --border-soft: #2b2d32;
            --overlay: rgba(32,33,36,.84);
            --shadow-card: 0 1px 3px 0 rgba(0,0,0,.34), 0 1px 2px -1px rgba(0,0,0,.28);
            --admin-shell-bg: #18191c;
            --admin-sidebar-bg: #202124;
            --admin-sidebar-border: rgba(255,255,255,.06);
            --admin-sidebar-hover: rgba(255,255,255,.06);
            --admin-sidebar-active: #2f80ed;
            --admin-sidebar-title: #f7f7f8;
            --admin-sidebar-muted: rgba(255,255,255,.52);
            --admin-sidebar-label: rgba(255,255,255,.32);
            --admin-sidebar-link: rgba(255,255,255,.76);
            --admin-sidebar-link-hover: #ffffff;
            --admin-sidebar-active-text: #ffffff;
            --admin-sidebar-icon-active-bg: rgba(255,255,255,.18);
            --admin-sidebar-badge-bg: rgba(255,255,255,.18);
            --admin-sidebar-badge-text: #ffffff;
            --admin-sidebar-workspace-bg: rgba(255,255,255,.06);
            --admin-sidebar-workspace-hover: rgba(255,255,255,.09);
        }
        .d-sidebar {
            background: var(--admin-sidebar-bg);
            border-right: 1px solid var(--admin-sidebar-border);
            gap: 10px;
            -ms-overflow-style: none;
            overflow: visible;
            padding: 10px 8px;
            scrollbar-width: none;
        }
        .d-sidebar::-webkit-scrollbar {
            display: none;
        }
        .sidebar-brand {
            align-items: center;
            border-bottom: 0;
            display: flex;
            justify-content: space-between;
            padding: 2px 2px 10px;
        }
        .sidebar-brand .brand {
            border-bottom: 0;
            border-radius: 12px;
            flex: 1;
            gap: 8px;
            padding: 8px;
        }
        .sidebar-brand .brand:hover {
            background: var(--admin-sidebar-hover);
        }
        .brand-mark {
            align-items: center;
            background: linear-gradient(135deg, #2f80ed, #1d4ed8);
            border-radius: 50%;
            color: #fff;
            display: inline-flex;
            flex: 0 0 auto;
            font-size: 11px;
            font-weight: 900;
            height: 30px;
            justify-content: center;
            letter-spacing: -.03em;
            width: 30px;
        }
        .brand-copy {
            display: flex;
            flex-direction: column;
            line-height: 1.05;
            min-width: 0;
        }
        .brand-title {
            color: var(--admin-sidebar-title);
            font-size: 13px;
            font-weight: 800;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .brand-subtitle {
            color: var(--admin-sidebar-muted);
            font-size: 10px;
            font-weight: 600;
            margin-top: 3px;
        }
        .sidebar-nav {
            display: flex;
            flex: 1;
            flex-direction: column;
            gap: 4px;
            min-height: 0;
            overflow-y: auto;
            padding-right: 2px;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .sidebar-nav::-webkit-scrollbar {
            display: none;
        }
        .nav-section {
            gap: 1px;
        }
        .nav-label {
            color: var(--admin-sidebar-label);
            padding: 9px 8px 4px;
        }
        .nav-group-title {
            color: inherit;
            font-size: 9px;
            letter-spacing: .09em;
        }
        .nav-link {
            border-radius: 6px;
            color: var(--admin-sidebar-link);
            font-size: 11.5px;
            font-weight: 700;
            gap: 8px;
            min-height: 29px;
            padding: 5px 8px;
        }
        .nav-link:hover {
            background: var(--admin-sidebar-hover);
            color: var(--admin-sidebar-link-hover);
        }
        .nav-link.is-active {
            background: var(--admin-sidebar-active);
            box-shadow: none;
            color: var(--admin-sidebar-active-text);
        }
        .nav-link > svg {
            background: var(--nav-icon-bg, rgba(255,255,255,.1));
            border-radius: 5px;
            color: var(--nav-icon-color, #9ca3af);
            height: 18px;
            padding: 3px;
            stroke-width: 2;
            width: 18px;
        }
        .nav-link.is-active > svg {
            background: var(--admin-sidebar-icon-active-bg);
            color: var(--admin-sidebar-active-text);
        }
        .nav-link-text {
            color: inherit;
            font-size: 11.5px;
        }
        .nav-badge {
            background: var(--admin-sidebar-badge-bg);
            color: var(--admin-sidebar-badge-text);
            font-size: 9px;
        }
        .sidebar-footer {
            border-top: 0;
            display: grid;
            gap: 8px;
            margin-top: 0;
            padding-top: 6px;
        }
        .sidebar-footer .workspace {
            background: var(--admin-sidebar-workspace-bg);
            border-radius: 8px;
            padding: 7px 8px;
        }
        .sidebar-footer .workspace:hover {
            background: var(--admin-sidebar-workspace-hover);
        }
        .workspace-name {
            color: var(--admin-sidebar-title);
            font-size: 11px;
        }
        .workspace-role {
            color: var(--admin-sidebar-muted);
            font-size: 10px;
        }
        .workspace-chev {
            color: var(--admin-sidebar-muted);
        }
        .dd-menu.dd-profile {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 16px 40px -18px rgba(0,0,0,.42), var(--shadow-lg);
            min-width: 252px;
            overflow: hidden;
            padding: 6px;
        }
        .sidebar-footer .dd-menu.dd-profile {
            background: #202124;
            border-color: rgba(255,255,255,.08);
            box-shadow: 0 18px 42px -18px rgba(0,0,0,.72);
            color: #f7f7f8;
        }
        .dd-profile-head {
            align-items: center;
            background:
                radial-gradient(circle at top right, color-mix(in srgb, var(--primary) 22%, transparent), transparent 55%),
                color-mix(in srgb, var(--bg-muted) 72%, var(--bg-card));
            border: 1px solid var(--border-soft);
            border-radius: 12px;
            display: grid;
            gap: 10px;
            grid-template-columns: 38px minmax(0, 1fr);
            margin: 0 0 6px;
            padding: 10px;
        }
        .sidebar-footer .dd-profile-head {
            background:
                radial-gradient(circle at top right, rgba(47,128,237,.24), transparent 58%),
                rgba(255,255,255,.06);
            border-color: rgba(255,255,255,.08);
        }
        .dd-profile-avatar {
            align-items: center;
            background: linear-gradient(135deg, var(--primary), var(--purple));
            border: 1px solid color-mix(in srgb, var(--primary) 40%, transparent);
            border-radius: 11px;
            color: #fff;
            display: inline-flex;
            font-size: 12px;
            font-weight: 900;
            height: 38px;
            justify-content: center;
            letter-spacing: -.03em;
            width: 38px;
        }
        .dd-profile-copy {
            line-height: 1.2;
            min-width: 0;
        }
        .dd-profile-name {
            color: var(--t-base);
            font-size: 13px;
            font-weight: 900;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .sidebar-footer .dd-profile-name {
            color: #f7f7f8;
        }
        .dd-profile-email {
            color: var(--t-muted);
            font-family: JetBrains Mono, monospace;
            font-size: 10.5px;
            letter-spacing: .01em;
            margin-top: 2px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .sidebar-footer .dd-profile-email {
            color: rgba(255,255,255,.56);
        }
        .dd-profile-role {
            background: var(--primary-soft);
            border-radius: 999px;
            color: var(--primary);
            display: inline-flex;
            font-size: 9px;
            font-weight: 900;
            letter-spacing: .08em;
            line-height: 1;
            margin-top: 7px;
            padding: 4px 7px;
            text-transform: uppercase;
        }
        .sidebar-footer .dd-profile-role {
            background: rgba(47,128,237,.18);
            color: #93c5fd;
        }
        .dd-profile .dd-menu-item {
            align-items: center;
            border-radius: 10px;
            color: var(--t-base);
            display: flex;
            font-size: 12.5px;
            font-weight: 800;
            gap: 10px;
            min-height: 36px;
            padding: 8px 10px;
        }
        .sidebar-footer .dd-profile .dd-menu-item {
            color: rgba(255,255,255,.82);
        }
        .dd-profile .dd-menu-item svg {
            background: var(--bg-muted);
            border-radius: 8px;
            color: var(--t-muted);
            height: 22px;
            padding: 5px;
            width: 22px;
        }
        .sidebar-footer .dd-profile .dd-menu-item svg {
            background: rgba(255,255,255,.08);
            color: rgba(255,255,255,.64);
        }
        .dd-profile .dd-menu-item:hover {
            background: var(--bg-hover);
            color: var(--primary);
        }
        .sidebar-footer .dd-profile .dd-menu-item:hover {
            background: rgba(255,255,255,.08);
            color: #fff;
        }
        .dd-profile .dd-menu-item:hover svg {
            background: var(--primary-soft);
            color: var(--primary);
        }
        .sidebar-footer .dd-profile .dd-menu-item:hover svg {
            background: rgba(47,128,237,.2);
            color: #93c5fd;
        }
        .dd-profile .dd-menu-item.danger {
            color: var(--danger);
        }
        .sidebar-footer .dd-profile .dd-menu-item.danger {
            color: #fca5a5;
        }
        .dd-profile .dd-menu-item.danger:hover {
            background: var(--danger-soft);
            color: var(--danger);
        }
        .sidebar-footer .dd-profile .dd-menu-item.danger:hover {
            background: rgba(248,113,113,.12);
            color: #fecaca;
        }
        .dd-profile .dd-menu-item.danger svg {
            background: var(--danger-soft);
            color: var(--danger);
        }
        .sidebar-footer .dd-profile .dd-menu-item.danger svg {
            background: rgba(248,113,113,.12);
            color: #fca5a5;
        }
        .dd-profile form .dd-menu-item {
            background: transparent;
            border: 0;
            text-align: left;
            width: 100%;
        }
        .dd-profile .dd-divider {
            background: var(--border-soft);
            margin: 6px 4px;
        }
        .sidebar-footer .dd-profile .dd-divider {
            background: rgba(255,255,255,.08);
        }
        @media(max-width:1100px) {
            .shell {
                grid-template-columns: 72px 1fr;
            }
            .brand-copy,
            .sidebar-footer .workspace-text,
            .sidebar-footer .workspace-chev {
                display: none;
            }
        }
        .main {
            background: var(--admin-shell-bg);
        }
        .content {
            padding: 18px 20px 20px;
        }
        .hero {
            align-items: center;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: var(--shadow-card);
            gap: 18px;
            margin-bottom: 14px;
            padding: 16px 18px;
        }
        .hero-text .eyebrow {
            margin-bottom: 5px;
        }
        .hero-title {
            font-size: clamp(20px,2vw,26px);
            margin-bottom: 4px;
        }
        .hero-sub {
            font-size: 12.5px;
            line-height: 1.45;
            margin: 0;
            max-width: 74ch;
        }
        .hero-actions {
            gap: 7px;
        }
        .grid {
            gap: 14px;
        }
        .card {
            border-radius: 12px;
            padding: 16px;
        }
        .card-head {
            align-items: center;
            margin-bottom: 14px;
            padding-bottom: 12px;
        }
        .card-title {
            font-size: 14px;
        }
        .btn {
            border-radius: 7px;
            font-size: 12px;
            padding: 7px 11px;
        }
        .badge,
        .tag {
            font-size: 10px;
            font-weight: 800;
        }
        .input,
        .select,
        .textarea {
            border-radius: 7px;
            font-size: 12.5px;
            min-height: 36px;
            padding: 8px 10px;
        }
        input[type="date"],
        input[type="datetime-local"],
        input[type="time"] {
            color-scheme: light;
            font-variant-numeric: tabular-nums;
        }
        :root[data-theme=dark] input[type="date"],
        :root[data-theme=dark] input[type="datetime-local"],
        :root[data-theme=dark] input[type="time"] {
            color-scheme: dark;
        }
        .admin-date-input,
        .bw-date-input {
            appearance: none;
            min-height: 34px;
            padding-right: 10px;
        }
        .field-label {
            font-size: 11.5px;
            font-weight: 800;
        }
        .field-help {
            font-size: 11px;
        }
        .data-toolbar {
            gap: 10px;
            margin-bottom: 12px;
        }
        .data-table thead th {
            background: var(--bg-muted);
            font-size: 9px;
            padding: 9px 10px;
        }
        .data-table tbody td {
            font-size: 12px;
            padding: 10px;
        }
        .table thead th {
            background: var(--bg-muted);
            font-size: 9px;
            padding: 9px 10px;
        }
        .table tbody td {
            font-size: 12px;
            padding: 10px;
        }
        .form-grid {
            gap: 14px 16px;
        }
        .form-actions {
            margin-top: 14px;
            padding-top: 14px;
        }
        .alert {
            border-radius: 9px;
            font-size: 12.5px;
            padding: 12px 14px;
        }
        .admin-surface {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .admin-compact-card,
        .admin-compact-panel {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 10px;
            box-shadow: var(--shadow-card);
            overflow: hidden;
        }
        .admin-actionbar {
            align-items: center;
            display: flex;
            gap: 8px;
            justify-content: space-between;
            min-height: 36px;
        }
        .admin-actionbar-left,
        .admin-actionbar-right {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }
        .admin-action-btn {
            align-items: center;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 5px;
            color: var(--t-base);
            display: inline-flex;
            font-size: 11px;
            font-weight: 800;
            gap: 6px;
            min-height: 31px;
            padding: 7px 11px;
            text-decoration: none;
        }
        .admin-action-btn:hover {
            background: var(--bg-hover);
            color: var(--primary);
        }
        .admin-action-btn.primary {
            background: var(--success);
            border-color: var(--success);
            color: #fff;
        }
        .admin-action-btn svg {
            fill: none;
            height: 13px;
            stroke: currentColor;
            stroke-width: 2;
            width: 13px;
        }
        .admin-compact-info {
            align-items: center;
            background: var(--info-soft);
            border: 1px solid color-mix(in srgb, var(--info) 32%, transparent);
            border-radius: 6px;
            color: var(--info);
            display: flex;
            font-size: 11px;
            font-weight: 700;
            gap: 8px;
            padding: 9px 12px;
        }
        .admin-compact-info svg {
            fill: none;
            height: 14px;
            stroke: currentColor;
            stroke-width: 2;
            width: 14px;
        }
        .admin-compact-table-wrap {
            overflow-x: hidden;
            width: 100%;
        }
        .admin-compact-table {
            border-collapse: collapse;
            min-width: 0;
            table-layout: fixed;
            width: 100%;
        }
        .admin-compact-table th,
        .admin-compact-table td {
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: middle;
            white-space: nowrap;
        }
        .admin-compact-table th {
            background: color-mix(in srgb, var(--bg-muted) 86%, var(--bg-card));
            border-bottom: 1px solid var(--border);
            color: var(--t-light);
            font-size: 9.5px;
            font-weight: 900;
            letter-spacing: .04em;
            line-height: 1.15;
            padding: 8px 7px;
            text-align: left;
            text-transform: uppercase;
        }
        .admin-compact-table td {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-soft);
            color: var(--t-base);
            font-size: 11.5px;
            height: 38px;
            line-height: 1.25;
            padding: 7px;
        }
        .admin-compact-table tbody tr:nth-child(even) td {
            background: color-mix(in srgb, var(--bg-muted) 30%, var(--bg-card));
        }
        .admin-compact-table tbody tr:hover td {
            background: color-mix(in srgb, var(--primary-soft) 64%, var(--bg-card));
        }
        .admin-compact-table th:first-child,
        .admin-compact-table td:first-child {
            text-align: center;
        }
        .admin-compact-table th:last-child,
        .admin-compact-table td:last-child {
            text-align: right;
        }
        .admin-filter-row th {
            background: color-mix(in srgb, var(--bg-muted) 70%, var(--bg-card));
            padding: 5px 7px;
        }
        .admin-filter-input {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 5px;
            color: var(--t-base);
            font-size: 10.5px;
            height: 24px;
            outline: 0;
            padding: 0 6px;
            width: 100%;
        }
        .admin-actionbar .admin-filter-input {
            height: 31px;
            min-width: 220px;
        }
        .admin-filter-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px var(--primary-ring);
        }
        .admin-row-check {
            appearance: none;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 3px;
            cursor: pointer;
            height: 14px;
            width: 14px;
        }
        .admin-row-check:checked {
            background: var(--primary);
            border-color: var(--primary);
            box-shadow: inset 0 0 0 3px var(--bg-card);
        }
        .admin-cell-text {
            display: block;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .admin-compact-table .data-cell-user {
            gap: 7px;
            min-width: 0;
        }
        .admin-compact-table .data-cell-user .av {
            height: 24px;
            width: 24px;
        }
        .admin-compact-table .data-cell-user-name,
        .admin-compact-table .data-cell-user-email,
        .admin-compact-table .data-cell-mono {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .admin-compact-table .data-cell-actions {
            align-items: center;
            display: inline-flex;
            gap: 4px;
            justify-content: flex-end;
            max-width: 100%;
            white-space: nowrap;
        }
        .admin-compact-table .btn--icon,
        .admin-compact-table .admin-tx-action {
            flex: 0 0 auto;
        }
        .admin-empty-state {
            color: var(--t-muted);
            display: none;
            font-size: 12px;
            font-weight: 800;
            padding: 18px;
            text-align: center;
        }
        .admin-compact-foot {
            align-items: center;
            background: var(--bg-muted);
            color: var(--t-muted);
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: space-between;
            min-height: 42px;
            padding: 9px 12px;
        }
        .admin-page-size,
        .admin-compact-pager {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }
        .admin-page-size a,
        .admin-compact-pager a,
        .admin-compact-pager span {
            border-radius: 4px;
            color: var(--t-muted);
            display: inline-flex;
            font-size: 11px;
            font-weight: 800;
            min-width: 24px;
            padding: 5px 7px;
            place-content: center;
            text-decoration: none;
        }
        .admin-page-size .active,
        .admin-compact-pager .active {
            background: var(--bg-card);
            color: var(--t-base);
        }
        .megapayz-card,
        .bgaming-card,
        .payment-method-card,
        .suite-card,
        .permissions-stat,
        .user-stat-card {
            border-radius: 12px !important;
            box-shadow: var(--shadow-card) !important;
        }
        .megapayz-grid,
        .bgaming-grid,
        .payment-methods-grid,
        .suite-layout,
        .user-detail-page {
            gap: 14px !important;
        }
        .megapayz-help,
        .bgaming-help,
        .field-help {
            color: var(--t-muted);
            font-size: 11px !important;
            line-height: 1.45;
        }
        .megapayz-stat,
        .bgaming-stat {
            font-size: 12px;
            padding: 10px 0 !important;
        }
        .megapayz-stat strong,
        .bgaming-stat strong {
            color: var(--t-base);
            font-size: 12px;
            overflow-wrap: anywhere;
            text-align: right;
        }
        .user-stat-card {
            padding: 12px !important;
        }
        .user-stat-card span {
            font-size: 10px !important;
        }
        .user-stat-card strong {
            font-size: 16px !important;
        }
        .permissions-card,
        .megapayz-card,
        .bgaming-card,
        .footer-card,
        .mobile-card,
        .homepage-card,
        .homepage-manager,
        .mail-rail,
        .mail-list,
        .mail-reader,
        .chat-frame,
        .chart-card,
        .cal-card {
            background: var(--bg-card) !important;
            border-color: var(--border) !important;
            color: var(--t-base);
            box-shadow: var(--shadow-card);
        }
        .permissions-layout,
        .megapayz-grid,
        .bgaming-grid,
        .footer-layout,
        .mobile-layout,
        .homepage-layout,
        .homepage-manager,
        .mail-shell,
        .chat-frame {
            gap: 14px !important;
        }
        .permissions-page,
        .homepage-manager,
        .mail-shell,
        .chat-frame {
            color: var(--t-base);
        }
        .permission-item,
        .homepage-game-row,
        .homepage-banner-row,
        .mail-row,
        .reader-card,
        .payment-method-card {
            background: var(--bg-card);
            border-color: var(--border) !important;
            border-radius: 10px !important;
        }
        .permission-item:hover,
        .mail-row:hover,
        .homepage-game-row:hover,
        .homepage-banner-row:hover {
            background: var(--bg-hover);
        }
        .mail-search,
        .chat-input,
        .homepage-field input,
        .homepage-field select {
            background: var(--bg-card) !important;
            border-color: var(--border) !important;
            color: var(--t-base) !important;
        }
        .mail-row-preview,
        .mail-row-time,
        .reader-body,
        .homepage-field span,
        .permission-desc,
        .payment-method-row {
            color: var(--t-muted) !important;
            font-size: 11px;
        }
        .mail-folder,
        .mail-tool,
        .chat-send {
            border-radius: 8px !important;
        }
        .d-topbar {
            height: 54px;
            padding: 0 20px;
        }
        .d-footer {
            padding: 14px 20px 18px;
        }
        .dashboard-page .hero,
        .bw-dashboard .hero {
            background: transparent;
            border: 0;
            box-shadow: none;
            padding: 0;
        }
        @media(max-width:720px) {
            .content {
                padding: 14px 12px;
            }
            .hero {
                align-items: flex-start;
                padding: 14px;
            }
            .d-topbar {
                padding: 0 12px;
            }
            .admin-actionbar {
                align-items: stretch;
                flex-direction: column;
            }
            .admin-actionbar-left,
            .admin-actionbar-right {
                justify-content: flex-start;
            }
        }
    </style>
    <script>
        window.__ADMIN_PALETTE_ITEMS__ = <?= json_encode($paletteItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
</head>
<body data-active="<?= htmlspecialchars($activePage, ENT_QUOTES, 'UTF-8') ?>" data-crumbs="<?= htmlspecialchars($crumbText, ENT_QUOTES, 'UTF-8') ?>">
<?php if ($adminFlashMessage !== ''): ?>
    <script type="application/json" data-admin-toast><?= json_encode(['type' => $adminFlashType, 'message' => $adminFlashMessage], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endif; ?>
<?php if ($adminErrorMessage !== ''): ?>
    <script type="application/json" data-admin-toast><?= json_encode(['type' => 'error', 'message' => $adminErrorMessage], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endif; ?>
<div class="shell">
    <aside class="d-sidebar">
        <div class="sidebar-brand">
            <a class="brand" href="<?= htmlspecialchars(AdminAuth::url('/dashboard'), ENT_QUOTES, 'UTF-8') ?>">
                <span class="brand-mark">NH</span>
                <span class="brand-copy">
                    <span class="brand-title"><?= htmlspecialchars($panelName, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="brand-subtitle">Backoffice</span>
                </span>
            </a>
            <button class="sidebar-close" type="button" data-drawer-close aria-label="Close navigation">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </button>
        </div>
        <div class="sidebar-nav">
            <?php foreach ($navigation as $group): ?>
                <?php $items = is_array($group['items'] ?? null) ? $group['items'] : []; ?>
                <nav class="nav-section">
                    <div class="nav-label">
                        <span class="nav-group-title"><?= htmlspecialchars((string) ($group['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $url = (string) ($item['url'] ?? '#');
                        $icon = (string) ($item['icon'] ?? '<circle cx="12" cy="12" r="8"/>');
                        $badge = trim((string) ($item['badge'] ?? ''));
                        $activeClass = $isNavItemActive($item) ? ' is-active' : '';
                        $navColor = $navIconPalette[$navIconIndex % count($navIconPalette)];
                        $navIconIndex++;
                        ?>
                        <a class="nav-link<?= $activeClass ?>" style="--nav-icon-color:<?= htmlspecialchars($navColor, ENT_QUOTES, 'UTF-8') ?>;--nav-icon-bg:<?= htmlspecialchars($navColor, ENT_QUOTES, 'UTF-8') ?>22" href="<?= htmlspecialchars(AdminAuth::url($url), ENT_QUOTES, 'UTF-8') ?>">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><?= $icon ?></svg>
                            <span class="nav-link-text"><?= htmlspecialchars((string) ($item['text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($badge !== ''): ?>
                                <span class="nav-badge"><?= htmlspecialchars($badge, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            <?php endforeach; ?>
        </div>
        <div class="sidebar-footer">
            <div class="dd-wrap">
                <button class="workspace" type="button" data-dropdown aria-label="Sidebar account menu">
                    <div class="workspace-avatar"><?= htmlspecialchars(strtoupper(substr((string) ($currentUser['username'] ?? 'A'), 0, 2)), ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="workspace-text">
                        <div class="workspace-name"><?= htmlspecialchars((string) ($currentUser['username'] ?? 'Admin'), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="workspace-role"><?= htmlspecialchars((string) ($currentUser['role'] ?? 'admin'), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <svg class="workspace-chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="m7 9 5-5 5 5"/><path d="m7 15 5 5 5-5"/>
                    </svg>
                </button>
                <div class="dd-menu dd-profile" role="menu">
                    <div class="dd-profile-head">
                        <div class="dd-profile-avatar"><?= htmlspecialchars($adminInitials, ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="dd-profile-copy">
                            <div class="dd-profile-name"><?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="dd-profile-email"><?= htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8') ?></div>
                            <span class="dd-profile-role"><?= htmlspecialchars($adminRole, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </div>
                    <?php if ($canSiteSettings): ?><a class="dd-menu-item" href="<?= htmlspecialchars(AdminAuth::url('/site-settings'), ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>Ayarlar</a><?php endif; ?>
                    <?php if ($canAdmins): ?><a class="dd-menu-item" href="<?= htmlspecialchars(AdminAuth::url('/module?key=admins'), ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Profil</a><?php endif; ?>
                    <?php if ($canEmail): ?><a class="dd-menu-item" href="<?= htmlspecialchars(AdminAuth::url('/email'), ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>Mesajlar</a><?php endif; ?>
                    <div class="dd-divider"></div>
                    <form method="post" action="<?= htmlspecialchars(AdminAuth::url('/logout'), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                        <button class="dd-menu-item danger" type="submit"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>Çıkış</button>
                    </form>
                </div>
            </div>
        </div>
    </aside>
    <div class="main">
        <header class="d-topbar">
            <div class="crumbs">
                <button class="hamburger" data-drawer-open aria-label="Open navigation">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <?php $crumbParts = array_values(array_filter(array_map('trim', explode('|', $crumbText)))); ?>
                <?php foreach ($crumbParts as $index => $crumbPart): ?>
                    <?php if ($index > 0): ?><svg class="sep" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg><?php endif; ?>
                    <span class="<?= $index === count($crumbParts) - 1 ? 'current' : '' ?>"><?= htmlspecialchars($crumbPart, ENT_QUOTES, 'UTF-8') ?></span>
                <?php endforeach; ?>
            </div>
            <div class="topbar-actions">
                <button class="cmd" data-admin-palette-open>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                    <span>Modül ara...</span>
                    <kbd class="kbd">Ctrl K</kbd>
                </button>
                <div class="dd-wrap">
                    <button class="icon-btn" data-dropdown aria-label="Notifications">
                        <svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        <?php if ($notificationCount > 0): ?><span class="count danger"><?= min(99, $notificationCount) ?></span><?php endif; ?>
                    </button>
                    <div class="dd-menu" role="menu">
                        <div class="dd-head"><svg viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg> Bildirimler</div>
                        <div class="dd-list">
                            <?php if ($canWithdrawals): ?><a class="dd-item" href="<?= htmlspecialchars(AdminAuth::url('/module?key=withdrawals'), ENT_QUOTES, 'UTF-8') ?>"><div class="dd-avatar a1">ÇK</div><div class="dd-body"><div class="dd-text"><strong>Bekleyen çekim</strong> talepleri</div><div class="dd-time"><?= $layoutScalar("SELECT COUNT(*) FROM megapayz_transactions WHERE type = 'withdraw' AND status = 'pending'") ?> kayıt</div></div></a><?php endif; ?>
                            <?php if ($canKyc): ?><a class="dd-item" href="<?= htmlspecialchars(AdminAuth::url('/module?key=kyc'), ENT_QUOTES, 'UTF-8') ?>"><div class="dd-avatar a2">KY</div><div class="dd-body"><div class="dd-text"><strong>KYC</strong> inceleme kuyruğu</div><div class="dd-time"><?= $layoutScalar("SELECT COUNT(*) FROM kyc_requests WHERE status = 'pending'") ?> kayıt</div></div></a><?php endif; ?>
                            <?php foreach ($logRows as $log): ?>
                                <a class="dd-item" href="<?= htmlspecialchars(AdminAuth::url('/module?key=logs'), ENT_QUOTES, 'UTF-8') ?>"><div class="dd-avatar a3">LG</div><div class="dd-body"><div class="dd-text"><strong><?= htmlspecialchars((string) ($log['admin_username'] ?? 'Admin'), ENT_QUOTES, 'UTF-8') ?></strong> <?= htmlspecialchars((string) ($log['action'] ?? 'log'), ENT_QUOTES, 'UTF-8') ?></div><div class="dd-time"><?= htmlspecialchars((string) ($log['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></div></a>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($canLogs): ?><a class="dd-footer" href="<?= htmlspecialchars(AdminAuth::url('/module?key=logs'), ENT_QUOTES, 'UTF-8') ?>">Tüm logları görüntüle →</a><?php endif; ?>
                    </div>
                </div>
                <?php if ($canEmail): ?><div class="dd-wrap">
                    <button class="icon-btn" data-dropdown aria-label="Messages">
                        <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                        <?php if (count($messageRows) > 0): ?><span class="count info"><?= min(99, count($messageRows)) ?></span><?php endif; ?>
                    </button>
                    <div class="dd-menu" role="menu">
                        <div class="dd-head"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg> Mesajlar</div>
                        <div class="dd-list">
                            <?php foreach ($messageRows as $message): ?>
                                <a class="dd-item" href="<?= htmlspecialchars(AdminAuth::url('/email'), ENT_QUOTES, 'UTF-8') ?>"><div class="dd-avatar a1">EM</div><div class="dd-body"><div class="dd-row-head"><strong><?= htmlspecialchars((string) ($message['subject'] ?? $message['title'] ?? 'Mesaj'), ENT_QUOTES, 'UTF-8') ?></strong><span class="dd-time"><?= htmlspecialchars((string) ($message['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span></div><div class="dd-preview"><?= htmlspecialchars(substr((string) ($message['body'] ?? $message['message'] ?? ''), 0, 80), ENT_QUOTES, 'UTF-8') ?></div></div></a>
                            <?php endforeach; ?>
                            <?php unset($message); ?>
                            <?php if ($messageRows === []): ?><div class="palette-empty">Mesaj bulunamadı</div><?php endif; ?>
                        </div>
                        <a class="dd-footer" href="<?= htmlspecialchars(AdminAuth::url('/email'), ENT_QUOTES, 'UTF-8') ?>">Tüm mesajlar →</a>
                    </div>
                </div><?php endif; ?>
                <button class="icon-btn" id="themeToggle" aria-label="Toggle theme"></button>
                <div class="dd-wrap">
                    <div class="avatar" data-dropdown tabindex="0" role="button" aria-label="Account menu"><?= htmlspecialchars($adminInitials, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="dd-menu dd-profile" role="menu">
                        <div class="dd-profile-head">
                            <div class="dd-profile-avatar"><?= htmlspecialchars($adminInitials, ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="dd-profile-copy">
                                <div class="dd-profile-name"><?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="dd-profile-email"><?= htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8') ?></div>
                                <span class="dd-profile-role"><?= htmlspecialchars($adminRole, ENT_QUOTES, 'UTF-8') ?></span>
                            </div>
                        </div>
                        <?php if ($canSiteSettings): ?><a class="dd-menu-item" href="<?= htmlspecialchars(AdminAuth::url('/site-settings'), ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>Ayarlar</a><?php endif; ?>
                        <?php if ($canAdmins): ?><a class="dd-menu-item" href="<?= htmlspecialchars(AdminAuth::url('/module?key=admins'), ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>Profil</a><?php endif; ?>
                        <?php if ($canEmail): ?><a class="dd-menu-item" href="<?= htmlspecialchars(AdminAuth::url('/email'), ENT_QUOTES, 'UTF-8') ?>"><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>Mesajlar</a><?php endif; ?>
                        <div class="dd-divider"></div>
                        <form method="post" action="<?= htmlspecialchars(AdminAuth::url('/logout'), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                            <button class="dd-menu-item danger" type="submit"><svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>Çıkış</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>
        <main class="content">
            <?php require $viewFile; ?>
        </main>
        <footer class="d-footer">
            <div>© <?= date('Y') ?> · <?= htmlspecialchars($panelName, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="d-footer-meta">
                <span><?= htmlspecialchars((string) ($site['site_name'] ?? $panelName), ENT_QUOTES, 'UTF-8') ?></span>
                <span><?= htmlspecialchars($adminRole, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </footer>
    </div>
</div>
<div class="drawer-backdrop" data-drawer-backdrop data-drawer-close aria-hidden="true"></div>
</body>
</html>
