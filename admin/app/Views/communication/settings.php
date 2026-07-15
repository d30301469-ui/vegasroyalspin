<?php

$settings = is_array($settings ?? null) ? $settings : [];
$flash = trim((string) ($flash ?? ''));
$testResult = trim((string) ($testResult ?? ''));
$dbFingerprint = trim((string) ($dbFingerprint ?? ''));
$enabled = !empty($settings['enabled']) || !empty($settings['mail_enabled']);
?>
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Iletisim · SMTP</span>
        <h1 class="hero-title">Mail <span class="accent">ayarlari</span></h1>
        <p class="hero-sub">Sifre sifirlama ve sistem mailleri icin tek noktadan SMTP konfigurasyonu.</p>
    </div>
    <div class="hero-actions">
        <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/email'), ENT_QUOTES, 'UTF-8') ?>">E-posta ekrani</a>
        <button class="btn btn--primary" type="submit" form="mailSettingsForm">Kaydet</button>
    </div>
</section>

<div class="grid">
    <section class="col-12 card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">SMTP</span>
                <h2 class="card-title">Gonderim Ayarlari</h2>
            </div>
            <span class="badge <?= $enabled ? 'dot success' : 'dot danger' ?>"><?= $enabled ? 'Aktif' : 'Pasif' ?></span>
        </div>

        <?php if ($dbFingerprint !== ''): ?>
            <div class="field-help" style="margin-bottom:12px;">Bu panelin bagli oldugu veritabani: <strong><?= htmlspecialchars($dbFingerprint, ENT_QUOTES, 'UTF-8') ?></strong> (phpMyAdmin'de gordugun DB adiyla ayni olmali)</div>
        <?php endif; ?>

        <?php
        $storedHost = trim((string) ($settings['smtp_host'] ?? ''));
        $storedUser = trim((string) ($settings['smtp_user'] ?? ''));
        $storedPass = (string) ($settings['smtp_password'] ?? '');
        $storedPassLen = strlen($storedPass);
        $storedPassMask = $storedPassLen > 0
            ? (substr($storedPass, 0, 1) . str_repeat('*', max(0, $storedPassLen - 2)) . ($storedPassLen > 1 ? substr($storedPass, -1) : ''))
            : '(bos)';
        ?>
        <div class="field-help" style="margin-bottom:12px;background:rgba(0,0,0,.04);padding:10px;border-radius:8px;">
            <strong>Kayitli SMTP bilgileri (dogrulama icin):</strong><br>
            Host: <code><?= htmlspecialchars($storedHost !== '' ? $storedHost : '(bos)', ENT_QUOTES, 'UTF-8') ?></code><br>
            Kullanici (tam e-posta olmali): <code><?= htmlspecialchars($storedUser !== '' ? $storedUser : '(bos)', ENT_QUOTES, 'UTF-8') ?></code><br>
            Sifre: <code><?= htmlspecialchars($storedPassMask, ENT_QUOTES, 'UTF-8') ?></code> (<?= $storedPassLen ?> karakter)
        </div>

        <?php if ($flash !== ''): ?>
            <div class="alert <?= stripos($flash, 'kaydedilemedi') !== false ? 'alert--danger' : 'alert--success' ?>" style="margin-bottom:12px;">
                <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($testResult !== ''): ?>
            <div class="alert <?= stripos($testResult, 'BASARILI') !== false ? 'alert--success' : 'alert--danger' ?>" style="margin-bottom:12px;white-space:pre-wrap;word-break:break-word;">
                <?= htmlspecialchars($testResult, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form id="mailSettingsForm" method="post" action="<?= htmlspecialchars(AdminAuth::url('/email/settings'), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-grid">
                <div class="field span-2">
                    <label class="switch" style="display:flex;align-items:center;gap:10px;">
                        <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                        <span class="field-label" style="margin:0;">Mail gonderimi aktif</span>
                    </label>
                    <div class="field-help">Kapaliysa sifre sifirlama mailleri gonderilmez.</div>
                </div>

                <div class="field span-2">
                    <label class="field-label" for="from_email">Gonderen e-posta</label>
                    <input id="from_email" class="input" type="email" name="from_email" placeholder="noreply@vegasroyalspin.com" value="<?= htmlspecialchars((string) ($settings['from_email'] ?? $settings['mail_from_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="field">
                    <label class="field-label" for="smtp_host">SMTP Host</label>
                    <input id="smtp_host" class="input" type="text" name="smtp_host" placeholder="smtp.example.com" value="<?= htmlspecialchars((string) ($settings['smtp_host'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="field">
                    <label class="field-label" for="smtp_port">SMTP Port</label>
                    <input id="smtp_port" class="input" type="number" min="1" max="65535" name="smtp_port" placeholder="587" value="<?= htmlspecialchars((string) ($settings['smtp_port'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="field">
                    <label class="field-label" for="smtp_user">SMTP Kullanici</label>
                    <input id="smtp_user" class="input" type="text" name="smtp_user" value="<?= htmlspecialchars((string) ($settings['smtp_user'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="field">
                    <label class="field-label" for="smtp_password">SMTP Sifre</label>
                    <input id="smtp_password" class="input" type="password" name="smtp_password" placeholder="Degistirmek icin yeni sifre girin">
                    <div class="field-help">Bos birakirsan mevcut sifre korunur.</div>
                </div>
            </div>

            <div class="form-actions">
                <span class="badge dot success">mail_settings</span>
                <span class="spacer"></span>
                <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/email'), ENT_QUOTES, 'UTF-8') ?>">Iptal</a>
                <button class="btn btn--primary" type="submit">Kaydet</button>
            </div>
        </form>
    </section>

    <section class="col-12 card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">Test</span>
                <h2 class="card-title">Test Mail Gonder</h2>
            </div>
        </div>
        <p class="field-help" style="margin-bottom:10px;">Ayarlari kaydettikten sonra gercek SMTP baglantisini test eder ve hatayi aninda gosterir.</p>
        <form method="post" action="<?= htmlspecialchars(AdminAuth::url('/email/settings/test'), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-grid">
                <div class="field span-2">
                    <label class="field-label" for="test_email">Test alici adresi</label>
                    <input id="test_email" class="input" type="email" name="test_email" placeholder="kendi-mailin@example.com" value="<?= htmlspecialchars((string) ($settings['from_email'] ?? $settings['mail_from_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="field-help">Bos birakirsan gonderen adrese test edilir.</div>
                </div>
            </div>
            <div class="form-actions">
                <span class="spacer"></span>
                <button class="btn btn--primary" type="submit">Test Mail Gonder</button>
            </div>
        </form>
    </section>
</div>
