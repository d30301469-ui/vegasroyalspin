<?php

declare(strict_types=1);

if (!function_exists('install_asset')) {
    function install_asset(string $path): string
    {
        $path = '/' . ltrim(str_replace('\\', '/', $path), '/');

        return $path === '/' ? '/' : $path;
    }
}

$pageTitle = isset($title) ? (string) $title : 'Kurulum';
$panelName = (string) ($panelName ?? 'Nexthub Backoffice');
$siteName = (string) ($siteName ?? 'Metropol Casino');
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($pageTitle . ' · ' . $siteName, ENT_QUOTES, 'UTF-8') ?></title>
    <script>
        !function(){try{var t=localStorage.getItem("dash26-theme"),e=window.matchMedia("(prefers-color-scheme: dark)").matches;document.documentElement.setAttribute("data-theme",t||(e?"dark":"light"))}catch(t){document.documentElement.setAttribute("data-theme","light")}}()
    </script>
    <script defer src="<?= htmlspecialchars(install_asset('/runtime.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script defer src="<?= htmlspecialchars(install_asset('/vendor-fullcalendar.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script defer src="<?= htmlspecialchars(install_asset('/vendor-chartjs.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script defer src="<?= htmlspecialchars(install_asset('/vendors.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <script defer src="<?= htmlspecialchars(install_asset('/2026.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
    <link href="<?= htmlspecialchars(install_asset('/style.css'), ENT_QUOTES, 'UTF-8') ?>" rel="stylesheet">
    <style>
        .install-card { max-width: 680px; width: 100%; }
        .install-card .card { margin-bottom: 16px; box-shadow: none; }
        .install-card .card:last-child { margin-bottom: 0; }
        .install-req-list { display: grid; gap: 8px; }
        .install-req-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 12px; border: 1px solid var(--border); border-radius: 10px; background: var(--bg-muted); }
        .install-req-row.fail { border-color: rgba(239,68,68,.35); background: var(--danger-soft); }
        .install-req-meta { font-size: 12px; color: var(--t-muted); margin-top: 2px; }
        .install-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .install-form-grid .span-2 { grid-column: span 2; }
        .install-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 18px; }
        .install-note { font-size: 12.5px; color: var(--t-muted); margin-top: 12px; line-height: 1.5; }
        #db-test-result { font-size: 13px; margin-top: 10px; }
        #db-test-result.ok { color: var(--success); }
        #db-test-result.fail { color: var(--danger); }
        @media (max-width: 720px) {
            .install-form-grid { grid-template-columns: 1fr; }
            .install-form-grid .span-2 { grid-column: span 1; }
        }
    </style>
</head>
<body>
    <?php require $viewFile; ?>
</body>
</html>
