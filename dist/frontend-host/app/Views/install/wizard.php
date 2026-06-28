<?php

/** @var list<array{label: string, ok: bool, detail: string, critical?: bool}> $checks */
/** @var array<string, string> $defaults */
/** @var string $csrf */
/** @var bool $requirementsOk */
$error = isset($error) ? (string) $error : '';
$pageTitle = (string) ($title ?? 'Frontend Kurulum');
$siteName = (string) ($siteName ?? 'Vegas Royal Spin');
$failedChecks = array_values(array_filter($checks, static fn (array $c): bool => empty($c['ok'])));
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        :root {
            --primary: #6d28d9;
            --primary-soft: rgba(109, 40, 217, .1);
            --success: #059669;
            --success-soft: #ecfdf5;
            --danger: #dc2626;
            --danger-soft: #fef2f2;
            --t: #0f172a;
            --t-muted: #64748b;
            --border: #e2e8f0;
            --surface: #ffffff;
            --bg-muted: #f8fafc;
            --bg-input: #ffffff;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Inter, "Segoe UI", system-ui, sans-serif; color: var(--t); background: #f1f5f9; }
        .iz-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 32px 16px; background: radial-gradient(1200px 500px at 50% -10%, rgba(109,40,217,.14), transparent 55%), linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%); }
        .iz-shell { width: 100%; max-width: 680px; }
        .iz-card { background: var(--surface); border: 1px solid var(--border); border-radius: 20px; padding: 32px; box-shadow: 0 24px 60px rgba(15, 23, 42, .08); }
        .iz-head { text-align: center; margin-bottom: 24px; }
        .iz-logo { width: 52px; height: 52px; border-radius: 14px; background: linear-gradient(135deg, #7c3aed, #6d28d9); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 14px; box-shadow: 0 8px 24px rgba(109,40,217,.35); }
        .iz-logo svg { width: 28px; height: 28px; }
        .iz-title { font-size: 24px; font-weight: 700; margin: 0; letter-spacing: -.02em; }
        .iz-sub { font-size: 14px; color: var(--t-muted); margin-top: 8px; line-height: 1.55; max-width: 520px; margin-left: auto; margin-right: auto; }
        .iz-steps { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; margin-bottom: 22px; }
        .iz-step { font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; padding: 7px 12px; border-radius: 999px; background: var(--bg-muted); color: var(--t-muted); border: 1px solid var(--border); }
        .iz-step.active { background: var(--primary-soft); color: var(--primary); border-color: rgba(109,40,217,.25); }
        .iz-pills { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; margin-bottom: 20px; }
        .iz-pill { font-size: 11px; font-weight: 600; padding: 5px 10px; border-radius: 999px; background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .iz-pill.warn { background: #fffbeb; color: #b45309; border-color: #fde68a; }
        .iz-status { display: flex; align-items: center; gap: 8px; font-size: 13px; padding: 11px 14px; border-radius: 12px; margin-bottom: 18px; }
        .iz-status.ok { background: var(--success-soft); color: var(--success); }
        .iz-status.bad { background: var(--danger-soft); color: var(--danger); }
        .iz-fails { margin: 0 0 18px; padding: 12px 14px; border: 1px solid rgba(220,38,38,.2); border-radius: 12px; background: var(--danger-soft); list-style: none; }
        .iz-fails li { font-size: 12.5px; margin: 4px 0; line-height: 1.45; }
        .iz-alert { font-size: 13px; padding: 12px 14px; border-radius: 12px; background: var(--danger-soft); color: var(--danger); margin-bottom: 18px; line-height: 1.45; }
        .iz-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .iz-grid .full { grid-column: 1 / -1; }
        .iz-field label { display: block; font-size: 12.5px; font-weight: 600; margin-bottom: 6px; color: var(--t); }
        .iz-field label .req { color: var(--danger); }
        .iz-field input[type="text"], .iz-field input[type="url"], .iz-field input[type="password"] { width: 100%; padding: 11px 13px; font-size: 14px; border: 1px solid var(--border); border-radius: 10px; background: var(--bg-input); color: var(--t); transition: border-color .15s, box-shadow .15s; }
        .iz-field input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(109,40,217,.12); }
        .iz-field input[readonly] { background: var(--bg-muted); color: #475569; cursor: default; }
        .iz-secret-wrap { position: relative; }
        .iz-secret-wrap input { padding-right: 88px; font-family: ui-monospace, Consolas, monospace; font-size: 13px; }
        .iz-toggle { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); border: 0; background: var(--bg-muted); color: var(--t-muted); font-size: 11px; font-weight: 600; padding: 6px 10px; border-radius: 8px; cursor: pointer; }
        .iz-toggle:hover { background: #e2e8f0; color: var(--t); }
        .iz-hint { font-size: 12px; color: var(--t-muted); margin-top: 6px; line-height: 1.45; }
        .iz-divider { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: var(--t-muted); margin: 24px 0 12px; display: flex; align-items: center; gap: 10px; }
        .iz-divider::after { content: ""; flex: 1; height: 1px; background: var(--border); }
        .iz-note-box { padding: 14px 16px; border-radius: 14px; background: var(--primary-soft); border: 1px solid rgba(109,40,217,.18); font-size: 13px; line-height: 1.55; color: #334155; }
        .iz-note-box strong { color: var(--primary); }
        .iz-checkbox { display: flex; align-items: flex-start; gap: 10px; font-size: 13px; line-height: 1.5; padding: 12px 14px; border-radius: 12px; border: 1px solid var(--border); background: var(--bg-muted); }
        .iz-checkbox input { width: auto; margin-top: 3px; accent-color: var(--primary); }
        .iz-actions { display: flex; gap: 12px; margin-top: 22px; flex-wrap: wrap; }
        .iz-btn { flex: 1; min-width: 140px; padding: 12px 16px; font-size: 14px; font-weight: 600; border-radius: 10px; border: 1px solid var(--border); background: #fff; color: var(--t); cursor: pointer; transition: background .15s, transform .1s; }
        .iz-btn:hover:not(:disabled) { background: var(--bg-muted); }
        .iz-btn--primary { background: var(--primary); border-color: var(--primary); color: #fff; }
        .iz-btn--primary:hover:not(:disabled) { background: #5b21b6; }
        .iz-btn:disabled { opacity: .5; cursor: not-allowed; }
        #backend-test-result { font-size: 13px; margin-top: 12px; padding: 10px 12px; border-radius: 10px; text-align: center; display: none; }
        #backend-test-result.ok { display: block; background: var(--success-soft); color: var(--success); }
        #backend-test-result.fail { display: block; background: var(--danger-soft); color: var(--danger); }
        #backend-test-result.pending { display: block; background: #f1f5f9; color: var(--t-muted); }
        .iz-foot { text-align: center; font-size: 12px; color: var(--t-muted); margin-top: 18px; line-height: 1.55; }
        .iz-foot code { font-size: 11px; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; }
        @media (max-width: 600px) { .iz-grid { grid-template-columns: 1fr; } .iz-card { padding: 22px 18px; } }
    </style>
</head>
<body>
<div class="iz-wrap">
    <div class="iz-shell">
        <div class="iz-card">
            <div class="iz-head">
                <span class="iz-logo" aria-hidden="true">
                    <svg viewBox="0 0 36 36" xmlns="http://www.w3.org/2000/svg">
                        <path fill="#fff" d="M14.747 9.125c.527-1.426 1.736-2.573 3.317-2.573c1.643 0 2.792 1.085 3.318 2.573l6.077 16.867c.186.496.248.931.248 1.147c0 1.209-.992 2.046-2.139 2.046c-1.303 0-1.954-.682-2.264-1.611l-.931-2.915h-8.62l-.93 2.884c-.31.961-.961 1.642-2.232 1.642c-1.24 0-2.294-.93-2.294-2.17c0-.496.155-.868.217-1.023l6.233-16.867zm.34 11.256h5.891l-2.883-8.992h-.062l-2.946 8.992z"/>
                    </svg>
                </span>
                <h1 class="iz-title">Frontend Kurulumu</h1>
                <p class="iz-sub"><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?> — API-only public site. Veritabanı bu sunucuda <strong>gerekmez</strong>; üye API ve CMS verisi <strong>bo-nexthub.site</strong> üzerinden gelir.</p>
            </div>

            <div class="iz-steps" aria-hidden="true">
                <span class="iz-step active">1 · Site adresleri</span>
                <span class="iz-step active">2 · Güvenlik anahtarları</span>
                <span class="iz-step active">3 · Backend test</span>
            </div>

            <div class="iz-pills">
                <span class="iz-pill">MySQL yok</span>
                <span class="iz-pill">Cloudflare uyumlu</span>
                <span class="iz-pill warn">Önce backend kurulumu önerilir</span>
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

            <form method="post" action="/install" id="install-form" autocomplete="off">
                <input type="hidden" name="action" value="install">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

                <div class="iz-divider">Site adresleri</div>
                <div class="iz-grid">
                    <div class="iz-field full">
                        <label for="frontend_url">Frontend URL <span class="req">*</span></label>
                        <input id="frontend_url" name="frontend_url" type="url" required value="<?= htmlspecialchars($defaults['frontend_url'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="iz-hint">Bu sunucunun public adresi — örn. https://vegasroyalspin.com</div>
                    </div>
                    <div class="iz-field full">
                        <label for="backend_url">Backend URL <span class="req">*</span></label>
                        <input id="backend_url" name="backend_url" type="url" required value="<?= htmlspecialchars($defaults['backend_url'], ENT_QUOTES, 'UTF-8') ?>">
                        <div class="iz-hint">Admin + üye API — örn. https://bo-nexthub.site (tarayıcı API: api.bo-nexthub.site)</div>
                    </div>
                    <div class="iz-field">
                        <label for="public_url_hosts">Public hosts</label>
                        <input id="public_url_hosts" type="text" readonly value="<?= htmlspecialchars($defaults['public_url_hosts'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="iz-field">
                        <label for="session_cookie_domain">Session cookie</label>
                        <input id="session_cookie_domain" type="text" readonly value="<?= htmlspecialchars($defaults['session_cookie_domain'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <div class="iz-divider">Güvenlik anahtarları</div>
                <div class="iz-note-box" style="margin-bottom:14px">
                    <strong>Backend kurulum özet sayfasından</strong> kopyalayın. <code>MEMBER_JWT_SECRET</code> ve <code>FRONTEND_CMS_PURGE_SECRET</code> her iki sunucuda <strong>birebir aynı</strong> olmalıdır.
                </div>
                <div class="iz-grid">
                    <div class="iz-field full">
                        <label for="member_jwt_secret">MEMBER_JWT_SECRET <span class="req">*</span></label>
                        <div class="iz-secret-wrap">
                            <input id="member_jwt_secret" name="member_jwt_secret" type="password" required minlength="32" placeholder="Backend .env ile aynı — min. 32 karakter">
                            <button type="button" class="iz-toggle" data-toggle="member_jwt_secret">Göster</button>
                        </div>
                    </div>
                    <div class="iz-field full">
                        <label for="frontend_cms_purge_secret">FRONTEND_CMS_PURGE_SECRET <span class="req">*</span></label>
                        <div class="iz-secret-wrap">
                            <input id="frontend_cms_purge_secret" name="frontend_cms_purge_secret" type="password" required minlength="32" placeholder="Backend kurulum özetinden">
                            <button type="button" class="iz-toggle" data-toggle="frontend_cms_purge_secret">Göster</button>
                        </div>
                    </div>
                    <div class="iz-field full">
                        <label for="app_key">APP_KEY (frontend)</label>
                        <div class="iz-secret-wrap">
                            <input id="app_key" name="app_key" type="text" required minlength="32" value="<?= htmlspecialchars($defaults['app_key'], ENT_QUOTES, 'UTF-8') ?>">
                            <button type="button" class="iz-toggle" data-toggle="app_key">Göster</button>
                        </div>
                        <div class="iz-hint">Otomatik üretildi; değiştirebilirsiniz (min. 32 karakter).</div>
                    </div>
                </div>

                <div class="iz-divider">İletişim (isteğe bağlı)</div>
                <div class="iz-grid">
                    <div class="iz-field full">
                        <label for="live_support_url">Canlı destek URL</label>
                        <input id="live_support_url" name="live_support_url" type="url" value="<?= htmlspecialchars($defaults['live_support_url'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="iz-field">
                        <label for="telegram_url">Telegram</label>
                        <input id="telegram_url" name="telegram_url" type="url" value="<?= htmlspecialchars($defaults['telegram_url'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="iz-field">
                        <label for="whatsapp_url">WhatsApp</label>
                        <input id="whatsapp_url" name="whatsapp_url" type="url" value="<?= htmlspecialchars($defaults['whatsapp_url'], ENT_QUOTES, 'UTF-8') ?>" placeholder="https://wa.me/...">
                    </div>
                </div>

                <div class="iz-divider">Backend bağlantısı</div>
                <label class="iz-checkbox">
                    <input type="checkbox" id="skip_backend_test" name="skip_backend_test" value="1">
                    <span>Backend şu an yanıt vermiyor — doğrulamayı atla ve kuruluma devam et.<br>
                    <span class="iz-hint" style="margin-top:6px;display:block">Önerilen: önce bo-nexthub.site kurulumunu bitirin. Atlanırsa site ayarları backend hazır olunca güncellenir.</span></span>
                </label>

                <div class="iz-actions">
                    <button type="button" class="iz-btn" id="btn-test-backend">Backend bağlantısını test et</button>
                    <button type="submit" class="iz-btn iz-btn--primary" <?= $requirementsOk ? '' : 'disabled' ?>>Kurulumu tamamla</button>
                </div>
                <div id="backend-test-result" aria-live="polite"></div>
            </form>

            <p class="iz-foot">Kurulum <code>.env</code> oluşturur ve <code>storage/install.lock</code> yazar.<br>Frontend sunucusuna <strong>DB_*</strong> bilgisi eklemeyin.</p>
        </div>
    </div>
</div>
<script>
(function () {
    document.querySelectorAll('[data-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.getAttribute('data-toggle');
            var input = id ? document.getElementById(id) : null;
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.textContent = show ? 'Gizle' : 'Göster';
        });
    });

    var form = document.getElementById('install-form');
    var testBtn = document.getElementById('btn-test-backend');
    var result = document.getElementById('backend-test-result');
    if (!form || !testBtn || !result) return;

    testBtn.addEventListener('click', function () {
        result.textContent = 'Backend test ediliyor…';
        result.className = 'pending';
        var body = new FormData();
        body.append('action', 'test-backend');
        body.append('_token', form.querySelector('[name="_token"]').value);
        body.append('backend_url', document.getElementById('backend_url').value);
        var controller = new AbortController();
        var timer = setTimeout(function () { controller.abort(); }, 12000);
        fetch('/install', { method: 'POST', body: body, credentials: 'same-origin', signal: controller.signal })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                clearTimeout(timer);
                result.textContent = data.message || (data.ok ? 'Backend bağlantısı başarılı.' : 'Test başarısız.');
                result.className = data.ok ? 'ok' : 'fail';
            })
            .catch(function (e) {
                clearTimeout(timer);
                result.textContent = e && e.name === 'AbortError'
                    ? 'Zaman aşımı (12 sn). Backend kapalı veya yavaş olabilir.'
                    : 'Test isteği başarısız.';
                result.className = 'fail';
            });
    });
})();
</script>
</body>
</html>
