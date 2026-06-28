<?php

/** @var list<array{label: string, ok: bool, detail: string, critical?: bool}> $checks */
/** @var array<string, string> $defaults */
/** @var string $csrf */
/** @var bool $requirementsOk */
$error = isset($error) ? (string) $error : '';
$seedAvailable = !empty($seedAvailable);
$seedSize = (string) ($seedSize ?? '');
$siteName = (string) ($siteName ?? ($defaults['site_name'] ?? 'Vegas Royal Spin'));
$pageTitle = (string) ($title ?? 'Admin Kurulum');
$failedChecks = array_values(array_filter($checks, static fn (array $c): bool => empty($c['ok'])));
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
        .iz-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 32px 16px; background: radial-gradient(1200px 500px at 50% -10%, rgba(109,40,217,.1), transparent 60%); }
        .iz-card { width: 100%; max-width: 640px; background: var(--surface, #fff); border: 1px solid var(--border, #e5e7eb); border-radius: 18px; padding: 32px; box-shadow: 0 18px 50px rgba(15,23,42,.08); }
        .iz-head { text-align: center; margin-bottom: 22px; }
        .iz-logo { width: 46px; height: 46px; border-radius: 12px; background: var(--primary, #6d28d9); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 12px; }
        .iz-logo svg { width: 26px; height: 26px; }
        .iz-title { font-size: 22px; font-weight: 700; margin: 0; letter-spacing: -.02em; }
        .iz-sub { font-size: 14px; color: var(--t-muted, #6b7280); margin-top: 6px; line-height: 1.5; }
        .iz-steps { display: flex; gap: 8px; justify-content: center; margin-bottom: 20px; }
        .iz-step { font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; padding: 6px 10px; border-radius: 999px; background: var(--bg-muted, #f3f4f6); color: var(--t-muted, #6b7280); }
        .iz-step.active { background: rgba(109,40,217,.12); color: var(--primary, #6d28d9); }
        .iz-status { display: flex; align-items: center; gap: 8px; font-size: 13px; padding: 10px 12px; border-radius: 10px; margin-bottom: 18px; }
        .iz-status.ok { background: var(--success-soft, #ecfdf5); color: var(--success, #059669); }
        .iz-status.bad { background: var(--danger-soft, #fef2f2); color: var(--danger, #dc2626); }
        .iz-fails { margin: 0 0 18px; padding: 12px 14px; border: 1px solid rgba(220,38,38,.25); border-radius: 10px; background: var(--danger-soft, #fef2f2); }
        .iz-fails li { font-size: 12.5px; margin: 2px 0; }
        .iz-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .iz-grid .full { grid-column: 1 / -1; }
        .iz-field label { display: block; font-size: 12.5px; font-weight: 550; margin-bottom: 5px; color: var(--t, #111827); }
        .iz-field input { width: 100%; box-sizing: border-box; padding: 10px 12px; font-size: 14px; border: 1px solid var(--border, #d1d5db); border-radius: 9px; background: var(--bg-input, #fff); color: var(--t, #111827); }
        .iz-field input:focus { outline: none; border-color: var(--primary, #6d28d9); box-shadow: 0 0 0 3px rgba(109,40,217,.12); }
        .iz-divider { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--t-muted, #64748b); margin: 22px 0 12px; display: flex; align-items: center; gap: 10px; }
        .iz-divider::after { content: ""; flex: 1; height: 1px; background: var(--border, #e5e7eb); }
        .iz-note-box { margin-top: 10px; padding: 12px 14px; border-radius: 12px; background: rgba(109,40,217,.06); border: 1px solid rgba(109,40,217,.15); font-size: 12.5px; line-height: 1.55; color: var(--t, #334155); }
        .iz-actions { display: flex; gap: 10px; margin-top: 20px; }
        .iz-btn { flex: 1; padding: 11px 16px; font-size: 14px; font-weight: 600; border-radius: 9px; border: 1px solid var(--border, #d1d5db); background: transparent; color: var(--t, #111827); cursor: pointer; }
        .iz-btn--primary { background: var(--primary, #6d28d9); border-color: var(--primary, #6d28d9); color: #fff; }
        .iz-btn--primary:disabled { opacity: .5; cursor: not-allowed; }
        .iz-help { margin-top: 10px; font-size: 12px; color: var(--t-muted, #6b7280); line-height: 1.5; }
        .iz-checkbox { display: flex; align-items: flex-start; gap: 8px; font-size: 13px; color: var(--t, #111827); margin-top: 8px; }
        .iz-checkbox input { margin-top: 2px; }
        .iz-alert { display: flex; gap: 8px; font-size: 13px; padding: 11px 13px; border-radius: 10px; background: var(--danger-soft, #fef2f2); color: var(--danger, #dc2626); margin-bottom: 18px; }
        #db-test-result { font-size: 12.5px; margin-top: 10px; text-align: center; }
        #db-test-result.ok { color: var(--success, #059669); }
        #db-test-result.fail { color: var(--danger, #dc2626); }
        .iz-foot { text-align: center; font-size: 11.5px; color: var(--t-muted, #9ca3af); margin-top: 16px; }
        @media (max-width: 480px) { .iz-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="iz-wrap">
    <div class="iz-card">
        <div class="iz-head">
            <span class="iz-logo">
                <svg viewBox="0 0 36 36" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path fill="#fff" d="M14.747 9.125c.527-1.426 1.736-2.573 3.317-2.573c1.643 0 2.792 1.085 3.318 2.573l6.077 16.867c.186.496.248.931.248 1.147c0 1.209-.992 2.046-2.139 2.046c-1.303 0-1.954-.682-2.264-1.611l-.931-2.915h-8.62l-.93 2.884c-.31.961-.961 1.642-2.232 1.642c-1.24 0-2.294-.93-2.294-2.17c0-.496.155-.868.217-1.023l6.233-16.867zm.34 11.256h5.891l-2.883-8.992h-.062l-2.946 8.992z"/>
                </svg>
            </span>
            <h1 class="iz-title">Backend Kurulumu</h1>
            <p class="iz-sub">Veritabanını yapılandırın ve panele giriş yapacağınız yönetici hesabını oluşturun.</p>
        </div>

        <div class="iz-steps" aria-hidden="true">
            <span class="iz-step active">1 · Veritabanı</span>
            <span class="iz-step active">2 · Yönetici</span>
        </div>

        <?php if ($error !== ''): ?>
            <div class="iz-alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($requirementsOk): ?>
            <div class="iz-status ok">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg>
                Sunucu gereksinimleri karşılanıyor
            </div>
        <?php else: ?>
            <div class="iz-status bad">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                Bazı sunucu gereksinimleri eksik
            </div>
            <ul class="iz-fails">
                <?php foreach ($failedChecks as $check): ?>
                    <li><strong><?= htmlspecialchars($check['label'], ENT_QUOTES, 'UTF-8') ?></strong> — <?= htmlspecialchars($check['detail'], ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="post" action="/install" id="install-form">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="install">

            <div class="iz-divider">Veritabanı</div>
            <div class="iz-grid">
                <div class="iz-field">
                    <label for="db_host">Host</label>
                    <input id="db_host" name="db_host" value="<?= htmlspecialchars($defaults['db_host'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="iz-field">
                    <label for="db_port">Port</label>
                    <input id="db_port" name="db_port" value="<?= htmlspecialchars($defaults['db_port'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="iz-field">
                    <label for="db_database">Veritabanı adı</label>
                    <input id="db_database" name="db_database" placeholder="metropol_db" required>
                </div>
                <div class="iz-field">
                    <label for="db_username">Kullanıcı</label>
                    <input id="db_username" name="db_username" placeholder="metropol_user" required>
                </div>
                <div class="iz-field full">
                    <label for="db_password">Şifre</label>
                    <input id="db_password" name="db_password" type="password" autocomplete="new-password">
                </div>
                <div class="iz-field full">
                    <label class="iz-checkbox">
                        <input type="checkbox" id="import_seed_database" name="import_seed_database" value="1" <?= $seedAvailable ? 'checked' : '' ?>>
                        <span>
                            Kurulum seed veritabanını yükle
                            <?php if ($seedAvailable): ?>
                                (<code>database/seed/metropolcasino.sql</code>, <?= htmlspecialchars($seedSize, ENT_QUOTES, 'UTF-8') ?>)
                            <?php else: ?>
                                — seed dosyası pakette bulunamadı
                            <?php endif; ?>
                        </span>
                    </label>
                    <label class="iz-checkbox">
                        <input type="checkbox" id="use_existing_database" name="use_existing_database" value="1">
                        <span>Mevcut veritabanını kullan (tablolar korunur, yalnızca eksik migration uygulanır — seed SQL yüklenmez)</span>
                    </label>
                    <label class="iz-checkbox">
                        <input type="checkbox" id="preserve_integrations" name="preserve_integrations" value="1" checked>
                        <span>Mevcut .env entegrasyon/secret değerlerini koru</span>
                    </label>
                    <div class="iz-help">
                        İlk kurulumda boş bir veritabanı oluşturun ve <strong>seed SQL</strong> seçeneğini işaretli bırakın.
                        Canlı veriyi koruyarak yeniden kurulum için "Mevcut veritabanını kullan" seçeneğini işaretleyin.
                    </div>
                </div>
            </div>

            <div class="iz-divider">Yönetici hesabı</div>
            <div class="iz-note-box" id="admin-section-note">
                Giriş <strong>e-posta + şifre</strong> ile yapılır. Seed veritabanı yüklense bile burada girdiğiniz bilgiler uygulanır
                (mevcut aynı e-posta varsa şifre güncellenir).
            </div>
            <div class="iz-grid" style="margin-top:12px">
                <div class="iz-field">
                    <label for="admin_email">E-posta <span style="color:var(--danger,#dc2626)">*</span></label>
                    <input id="admin_email" name="admin_email" type="email" placeholder="admin@example.com" required autocomplete="email">
                </div>
                <div class="iz-field">
                    <label for="admin_username">Kullanıcı adı</label>
                    <input id="admin_username" name="admin_username" placeholder="admin" autocomplete="username">
                </div>
                <div class="iz-field full">
                    <label for="admin_password">Şifre (en az 8 karakter) <span style="color:var(--danger,#dc2626)">*</span></label>
                    <input id="admin_password" name="admin_password" type="password" minlength="8" required autocomplete="new-password">
                </div>
                <div class="iz-field full">
                    <label for="site_name">Site adı</label>
                    <input id="site_name" name="site_name" value="<?= htmlspecialchars($defaults['site_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
            </div>

            <input type="hidden" name="backend_url" value="<?= htmlspecialchars($defaults['backend_url'], ENT_QUOTES, 'UTF-8') ?>">

            <div class="iz-actions">
                <button class="iz-btn" type="button" id="test-db-btn">Bağlantıyı test et</button>
                <button class="iz-btn iz-btn--primary" type="submit" <?= $requirementsOk ? '' : 'disabled' ?>>Kurulumu başlat</button>
            </div>
            <div id="db-test-result" aria-live="polite"></div>
        </form>

        <div class="iz-foot">Kurulum tamamlandığında anahtarlarınızı gösteren bir özet sayfasına yönlendirilirsiniz.</div>
    </div>
</div>
<script>
(function () {
    var btn = document.getElementById('test-db-btn');
    var result = document.getElementById('db-test-result');
    var useExisting = document.getElementById('use_existing_database');
    var importSeed = document.getElementById('import_seed_database');
    var adminUsername = document.getElementById('admin_username');
    var adminEmail = document.getElementById('admin_email');
    var adminPassword = document.getElementById('admin_password');

    function syncInstallOptions() {
        var existing = !!(useExisting && useExisting.checked);
        if (importSeed) {
            importSeed.disabled = existing;
            if (existing) {
                importSeed.checked = false;
            }
        }
        var required = !existing;
        [adminEmail, adminPassword].forEach(function (el) {
            if (!el) return;
            if (required) {
                el.setAttribute('required', 'required');
            } else {
                el.removeAttribute('required');
            }
        });
        var adminSection = document.getElementById('admin-section-note');
        if (adminSection) {
            adminSection.style.display = existing ? 'none' : 'block';
        }
    }

    if (useExisting) {
        useExisting.addEventListener('change', syncInstallOptions);
    }
    if (importSeed) {
        importSeed.addEventListener('change', syncInstallOptions);
    }
    syncInstallOptions();

    if (!btn || !result) return;
    btn.addEventListener('click', function () {
        result.textContent = 'Test ediliyor...';
        result.className = '';
        var fd = new FormData(document.getElementById('install-form'));
        fd.set('action', 'test-db');
        fetch('/install', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                result.textContent = data.message || (data.ok ? 'Başarılı' : 'Hata');
                result.className = data.ok ? 'ok' : 'fail';
            })
            .catch(function () {
                result.textContent = 'Test isteği başarısız.';
                result.className = 'fail';
            });
    });
})();
</script>
</body>
</html>
