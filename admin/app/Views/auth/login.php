<?php

// Giriş ekranı tamamen statiktir: veritabanından marka/site bilgisi çekilmez.
$panelName = 'Yetkili Yönetim';
$siteName = 'Backoffice';
$siteDescription = 'Tüm yönetim işlemleri Backoffice panelinden yürütülür.';
$logoUrl = '';
$siteInitials = 'NH';
?>
<div class="auth-shell">
    <aside class="auth-aside">
        <div class="auth-brand">
            <div class="logo">
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>" style="max-height:36px;max-width:140px;object-fit:contain">
                <?php else: ?>
                    <svg viewBox="0 0 36 36" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path fill="#fff" d="M14.747 9.125c.527-1.426 1.736-2.573 3.317-2.573c1.643 0 2.792 1.085 3.318 2.573l6.077 16.867c.186.496.248.931.248 1.147c0 1.209-.992 2.046-2.139 2.046c-1.303 0-1.954-.682-2.264-1.611l-.931-2.915h-8.62l-.93 2.884c-.31.961-.961 1.642-2.232 1.642c-1.24 0-2.294-.93-2.294-2.17c0-.496.155-.868.217-1.023l6.233-16.867zm.34 11.256h5.891l-2.883-8.992h-.062l-2.946 8.992z"/>
                    </svg>
                <?php endif; ?>
            </div>
            <div class="name"><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="auth-aside-body">
            <span class="auth-aside-eyebrow"><?= htmlspecialchars($panelName, ENT_QUOTES, 'UTF-8') ?></span>
            <h1><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?> yönetim paneline hoş geldiniz.</h1>
            <?php if ($siteDescription !== ''): ?>
                <p><?= htmlspecialchars($siteDescription, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <div class="auth-quote">
                Üye yönetimi, finans, oyun sağlayıcıları ve site içeriği tek panelden yönetilir.
                <div class="auth-quote-author">
                    <div class="av"><?= htmlspecialchars($siteInitials, ENT_QUOTES, 'UTF-8') ?></div>
                    <div><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($panelName, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>
        </div>
        <div class="auth-aside-footer"><span>© <?= date('Y') ?></span> <span><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?></span></div>
    </aside>
    <main class="auth-main">
        <div class="auth-main-top">
            <a href="<?= htmlspecialchars(AdminAuth::url('/'), ENT_QUOTES, 'UTF-8') ?>" style="font-size:12.5px;color:var(--t-muted);display:inline-flex;align-items:center;gap:6px">
                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Panele dön
            </a>
            <div class="switch-link">Yetkili erişim</div>
        </div>
        <div class="auth-card">
            <h2>Admin girişi</h2>
            <p class="sub">Devam etmek için admin email adresinizi ve şifrenizi girin.</p>
            <?php if (!empty($_GET['installed'])): ?>
                <div class="alert success" style="margin-bottom:16px">Kurulum tamamlandı. Giriş yapabilirsiniz.</div>
            <?php endif; ?>
            <?php if (!empty($jwtHint) || !empty($purgeHint)): ?>
                <div class="alert success" style="margin-bottom:16px;font-size:12.5px;line-height:1.5">
                    <strong>Frontend kurulumu için</strong> aşağıdaki değerleri vegasroyalspin.com kurulum sihirbazına aynen girin:
                    <?php if (!empty($jwtHint)): ?>
                        <div style="margin-top:10px"><code>MEMBER_JWT_SECRET</code></div>
                        <div style="margin-top:4px;padding:8px;background:rgba(0,0,0,.06);border-radius:8px;word-break:break-all;font-family:monospace;font-size:11px"><?= htmlspecialchars((string) $jwtHint, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                    <?php if (!empty($purgeHint)): ?>
                        <div style="margin-top:10px"><code>FRONTEND_CMS_PURGE_SECRET</code></div>
                        <div style="margin-top:4px;padding:8px;background:rgba(0,0,0,.06);border-radius:8px;word-break:break-all;font-family:monospace;font-size:11px"><?= htmlspecialchars((string) $purgeHint, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert danger" style="margin-bottom:16px"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form class="auth-form" method="post" action="<?= htmlspecialchars(AdminAuth::url('/login'), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="field">
                    <label class="field-label" for="email">Email veya Kullanıcı Adı</label>
                    <div class="input-icon">
                        <span class="ico"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg></span>
                        <input id="email" class="input" name="email" type="text" placeholder="admin@example.com veya admin" autocomplete="username" required>
                    </div>
                </div>
                <div class="field">
                    <label class="field-label" for="password">Şifre</label>
                    <div class="input-icon">
                        <span class="ico"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></span>
                        <input id="password" class="input" name="password" type="password" placeholder="••••••••" autocomplete="current-password" required>
                    </div>
                </div>
                <button class="btn btn--primary auth-submit" type="submit">
                    Giriş yap
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
                </button>
            </form>
        </div>
        <div class="auth-main-bottom">Giriş sadece yetkili admin email ve şifresi ile yapılır.</div>
    </main>
</div>
