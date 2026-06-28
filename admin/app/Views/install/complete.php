<?php

/** @var string $title */
/** @var string $adminEmail */
/** @var string $adminUsername */
/** @var string $jwtHint */
/** @var string $purgeHint */
$pageTitle = (string) ($title ?? 'Kurulum Tamamlandı');
$secrets = array_values(array_filter([
    [
        'key' => 'MEMBER_JWT_SECRET',
        'label' => 'Üye JWT anahtarı',
        'hint' => 'Frontend kurulumunda MEMBER_JWT_SECRET alanına yapıştırın.',
        'value' => $jwtHint,
    ],
    [
        'key' => 'FRONTEND_CMS_PURGE_SECRET',
        'label' => 'CMS önbellek purge anahtarı',
        'hint' => 'Frontend kurulumunda FRONTEND_CMS_PURGE_SECRET alanına yapıştırın.',
        'value' => $purgeHint,
    ],
], static fn (array $row): bool => trim((string) ($row['value'] ?? '')) !== ''));
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <script>
        !function(){try{var t=localStorage.getItem("dash26-theme"),e=window.matchMedia("(prefers-color-scheme: dark)").matches;document.documentElement.setAttribute("data-theme",t||(e?"dark":"light"))}catch(t){document.documentElement.setAttribute("data-theme","light")}}()
    </script>
    <link href="/style.css" rel="stylesheet">
    <style>
        .iz-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 32px 16px; background: radial-gradient(1200px 500px at 50% -10%, rgba(109,40,217,.12), transparent 60%); }
        .iz-card { width: 100%; max-width: 720px; background: var(--surface, #fff); border: 1px solid var(--border, #e5e7eb); border-radius: 18px; padding: 32px; box-shadow: 0 18px 50px rgba(15,23,42,.08); }
        .iz-head { text-align: center; margin-bottom: 24px; }
        .iz-badge { display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; border-radius: 999px; background: var(--success-soft, #ecfdf5); color: var(--success, #059669); font-size: 13px; font-weight: 600; margin-bottom: 14px; }
        .iz-title { font-size: 24px; font-weight: 700; margin: 0; letter-spacing: -.02em; }
        .iz-sub { font-size: 14px; color: var(--t-muted, #6b7280); margin-top: 8px; line-height: 1.55; }
        .iz-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-top: 22px; }
        .iz-panel { border: 1px solid var(--border, #e5e7eb); border-radius: 14px; padding: 16px; background: var(--bg-muted, #f8fafc); }
        .iz-panel.full { grid-column: 1 / -1; }
        .iz-panel-label { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--t-muted, #64748b); margin-bottom: 8px; }
        .iz-panel-value { font-size: 15px; font-weight: 600; color: var(--t, #0f172a); word-break: break-word; }
        .iz-secret { margin-top: 18px; display: grid; gap: 12px; }
        .iz-secret-card { border: 1px solid var(--border, #e5e7eb); border-radius: 14px; overflow: hidden; }
        .iz-secret-head { display: flex; justify-content: space-between; gap: 12px; align-items: center; padding: 12px 14px; background: rgba(109,40,217,.06); border-bottom: 1px solid var(--border, #e5e7eb); }
        .iz-secret-head strong { font-size: 13px; }
        .iz-secret-head code { font-size: 11px; color: var(--primary, #6d28d9); background: rgba(255,255,255,.7); padding: 3px 8px; border-radius: 6px; }
        .iz-secret-body { padding: 12px 14px; }
        .iz-secret-hint { font-size: 12px; color: var(--t-muted, #64748b); margin-bottom: 10px; line-height: 1.45; }
        .iz-secret-value { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; line-height: 1.5; word-break: break-all; padding: 12px; border-radius: 10px; background: #0f172a; color: #e2e8f0; }
        .iz-copy { border: 0; background: var(--primary, #6d28d9); color: #fff; border-radius: 8px; padding: 8px 12px; font-size: 12px; font-weight: 600; cursor: pointer; }
        .iz-copy.copied { background: var(--success, #059669); }
        .iz-actions { display: flex; gap: 12px; margin-top: 24px; }
        .iz-btn { flex: 1; display: inline-flex; align-items: center; justify-content: center; padding: 12px 16px; font-size: 14px; font-weight: 600; border-radius: 10px; border: 1px solid var(--border, #d1d5db); background: transparent; color: var(--t, #111827); text-decoration: none; cursor: pointer; }
        .iz-btn--primary { background: var(--primary, #6d28d9); border-color: var(--primary, #6d28d9); color: #fff; }
        .iz-note { margin-top: 16px; font-size: 12.5px; color: var(--t-muted, #64748b); line-height: 1.55; }
        @media (max-width: 640px) { .iz-grid { grid-template-columns: 1fr; } .iz-actions { flex-direction: column; } }
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
            <h1 class="iz-title">Backend hazır</h1>
            <p class="iz-sub">Admin hesabınız oluşturuldu. Aşağıdaki anahtarları güvenli bir yere kaydedin; frontend kurulumunda gerekecek.</p>
        </div>

        <div class="iz-grid">
            <div class="iz-panel">
                <div class="iz-panel-label">Giriş e-postası</div>
                <div class="iz-panel-value"><?= htmlspecialchars($adminEmail !== '' ? $adminEmail : '—', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="iz-panel">
                <div class="iz-panel-label">Kullanıcı adı</div>
                <div class="iz-panel-value"><?= htmlspecialchars($adminUsername !== '' ? $adminUsername : '—', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
            <div class="iz-panel full">
                <div class="iz-panel-label">Giriş notu</div>
                <div class="iz-panel-value" style="font-weight:500;font-size:13px;line-height:1.55">
                    Panele giriş <strong>e-posta + şifre</strong> ile yapılır (kullanıcı adı ile değil).
                    Kurulumda girdiğiniz şifreyi kullanın.
                </div>
            </div>
        </div>

        <?php if ($secrets !== []): ?>
            <div class="iz-secret">
                <?php foreach ($secrets as $index => $secret): ?>
                    <div class="iz-secret-card">
                        <div class="iz-secret-head">
                            <strong><?= htmlspecialchars((string) $secret['label'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <code><?= htmlspecialchars((string) $secret['key'], ENT_QUOTES, 'UTF-8') ?></code>
                        </div>
                        <div class="iz-secret-body">
                            <div class="iz-secret-hint"><?= htmlspecialchars((string) $secret['hint'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div class="iz-secret-value" id="secret-value-<?= (int) $index ?>"><?= htmlspecialchars((string) $secret['value'], ENT_QUOTES, 'UTF-8') ?></div>
                            <div style="margin-top:10px">
                                <button type="button" class="iz-copy" data-copy-target="secret-value-<?= (int) $index ?>">Kopyala</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="iz-actions">
            <a class="iz-btn iz-btn--primary" href="/login">Admin girişine git</a>
        </div>

        <p class="iz-note">Bu sayfa bir kez gösterilir. Anahtarları kaydetmeden çıkmayın. Frontend zip kurulumunda aynı değerleri vegasroyalspin.com sihirbazına girin.</p>
    </div>
</div>
<script>
(function () {
    document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-copy-target');
            var el = id ? document.getElementById(id) : null;
            if (!el) return;
            var text = (el.textContent || '').trim();
            if (!text) return;
            var done = function () {
                btn.textContent = 'Kopyalandı';
                btn.classList.add('copied');
                window.setTimeout(function () {
                    btn.textContent = 'Kopyala';
                    btn.classList.remove('copied');
                }, 1800);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done).catch(function () {
                    window.prompt('Kopyalayın:', text);
                });
            } else {
                window.prompt('Kopyalayın:', text);
                done();
            }
        });
    });
})();
</script>
</body>
</html>
