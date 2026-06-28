<?php

/** @var string $title */
/** @var string $frontendUrl */
/** @var string $backendUrl */
/** @var bool $backendVerified */
/** @var string $installMessage */
$pageTitle = (string) ($title ?? 'Kurulum Tamamlandı');
$frontendUrl = trim((string) ($frontendUrl ?? ''));
$backendUrl = trim((string) ($backendUrl ?? ''));
$backendVerified = !empty($backendVerified);
$installMessage = trim((string) ($installMessage ?? ''));
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        :root { --primary:#6d28d9; --success:#059669; --success-soft:#ecfdf5; --t:#0f172a; --t-muted:#64748b; --border:#e2e8f0; --surface:#fff; --bg-muted:#f8fafc; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Inter, "Segoe UI", system-ui, sans-serif; color: var(--t); }
        .iz-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 32px 16px; background: radial-gradient(1200px 500px at 50% -10%, rgba(109,40,217,.14), transparent 55%), linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%); }
        .iz-card { width: 100%; max-width: 640px; background: var(--surface); border: 1px solid var(--border); border-radius: 20px; padding: 32px; box-shadow: 0 24px 60px rgba(15,23,42,.08); }
        .iz-head { text-align: center; margin-bottom: 24px; }
        .iz-badge { display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; border-radius: 999px; background: var(--success-soft); color: var(--success); font-size: 13px; font-weight: 600; margin-bottom: 14px; }
        .iz-title { font-size: 24px; font-weight: 700; margin: 0; letter-spacing: -.02em; }
        .iz-sub { font-size: 14px; color: var(--t-muted); margin-top: 8px; line-height: 1.55; }
        .iz-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 22px; }
        .iz-panel { border: 1px solid var(--border); border-radius: 14px; padding: 16px; background: var(--bg-muted); }
        .iz-panel.full { grid-column: 1 / -1; }
        .iz-panel-label { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--t-muted); margin-bottom: 8px; }
        .iz-panel-value { font-size: 14px; font-weight: 600; word-break: break-all; line-height: 1.45; }
        .iz-checklist { margin: 20px 0 0; padding: 0; list-style: none; display: grid; gap: 10px; }
        .iz-checklist li { display: flex; gap: 10px; align-items: flex-start; font-size: 13px; line-height: 1.5; padding: 12px 14px; border-radius: 12px; background: var(--bg-muted); border: 1px solid var(--border); }
        .iz-checklist li svg { flex-shrink: 0; margin-top: 2px; color: var(--success); }
        .iz-warn { margin-top: 16px; padding: 12px 14px; border-radius: 12px; background: #fffbeb; border: 1px solid #fde68a; color: #92400e; font-size: 13px; line-height: 1.5; }
        .iz-actions { display: flex; gap: 12px; margin-top: 24px; flex-wrap: wrap; }
        .iz-btn { flex: 1; min-width: 140px; display: inline-flex; align-items: center; justify-content: center; padding: 12px 16px; font-size: 14px; font-weight: 600; border-radius: 10px; border: 1px solid var(--border); background: #fff; color: var(--t); text-decoration: none; cursor: pointer; }
        .iz-btn--primary { background: var(--primary); border-color: var(--primary); color: #fff; }
        .iz-note { margin-top: 16px; font-size: 12.5px; color: var(--t-muted); line-height: 1.55; }
        .iz-note code { font-size: 11px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; }
        @media (max-width: 600px) { .iz-grid { grid-template-columns: 1fr; } .iz-actions { flex-direction: column; } }
    </style>
</head>
<body>
<div class="iz-wrap">
    <div class="iz-card">
        <div class="iz-head">
            <div class="iz-badge">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>
                Kurulum başarılı
            </div>
            <h1 class="iz-title">Frontend hazır</h1>
            <p class="iz-sub"><?= $installMessage !== '' ? htmlspecialchars($installMessage, ENT_QUOTES, 'UTF-8') : 'Site yapılandırması tamamlandı. Ana sayfayı açıp giriş ve sliderları test edin.' ?></p>
        </div>

        <div class="iz-grid">
            <div class="iz-panel full">
                <div class="iz-panel-label">Frontend URL</div>
                <div class="iz-panel-value"><?= htmlspecialchars($frontendUrl !== '' ? $frontendUrl : '—', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="iz-panel">
                <div class="iz-panel-label">Backend</div>
                <div class="iz-panel-value"><?= htmlspecialchars($backendUrl !== '' ? $backendUrl : '—', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="iz-panel">
                <div class="iz-panel-label">Backend testi</div>
                <div class="iz-panel-value" style="color:<?= $backendVerified ? 'var(--success)' : '#b45309' ?>">
                    <?= $backendVerified ? 'Doğrulandı ✓' : 'Atlandı — sonra test edin' ?>
                </div>
            </div>
        </div>

        <ul class="iz-checklist">
            <li>
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
                <span><code>.env</code> oluşturuldu — <code>FRONTEND_API_ONLY=1</code>, API adresleri api subdomain'e yönlendirildi.</span>
            </li>
            <li>
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
                <span>Giriş ve bakiye için <code>MEMBER_JWT_SECRET</code> backend ile eşleşmeli (kurulumda girdiniz).</span>
            </li>
            <li>
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
                <span>Sorun olursa: <code>php deploy/aapanel/fix-frontend-env.php</code> ve <code>php scripts/post-upload-check.php</code></span>
            </li>
        </ul>

        <?php if (!$backendVerified): ?>
            <div class="iz-warn">Backend doğrulaması atlandı. bo-nexthub.site kurulumunu bitirdikten sonra <code>php deploy/aapanel/fix-frontend-env.php</code> çalıştırın ve ana sayfayı yenileyin.</div>
        <?php endif; ?>

        <div class="iz-actions">
            <a class="iz-btn" href="/health.php?quick=1">Sağlık kontrolü</a>
            <a class="iz-btn" href="/install-status.php">Kurulum durumu</a>
            <a class="iz-btn iz-btn--primary" href="/">Ana sayfaya git</a>
        </div>

        <p class="iz-note">Bu sayfa bir kez gösterilir. Kurulum kilidi: <code>storage/install.lock</code></p>
    </div>
</div>
</body>
</html>
