<?php

$settings = is_array($settings ?? null) ? $settings : [];
$flash = trim((string) ($flash ?? ''));
$testResult = trim((string) ($testResult ?? ''));
$dbFingerprint = trim((string) ($dbFingerprint ?? ''));
$enabled = !empty($settings['enabled']) || !empty($settings['mail_enabled']);
$imapEnabled = !isset($settings['imap_enabled']) || (int) ($settings['imap_enabled'] ?? 1) === 1;
$emailSection = 'settings';

$imapHostValue = trim((string) ($settings['imap_host'] ?? ''));
if ($imapHostValue === '') {
    $imapHostValue = trim((string) ($settings['smtp_host'] ?? 'mail.vegasroyalspin.com'));
}
$imapPortValue = (string) (($settings['imap_port'] ?? '') !== '' && (int) ($settings['imap_port'] ?? 0) > 0
    ? (int) $settings['imap_port']
    : 993);
$imapUserValue = trim((string) ($settings['imap_user'] ?? ''));
if ($imapUserValue === '') {
    $imapUserValue = trim((string) ($settings['smtp_user'] ?? $settings['from_email'] ?? $settings['mail_from_address'] ?? 'noreply@vegasroyalspin.com'));
}
$imapEncValue = strtolower(trim((string) ($settings['imap_encryption'] ?? 'ssl')));
if ($imapEncValue === '') {
    $imapEncValue = 'ssl';
}
?>
<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">E-posta</span>
        <h1 class="hero-title">Ayarlar</h1>
        <p class="hero-sub">SMTP (gönderim) ve IMAP (gelen kutusu) ayarları.</p>
    </div>
</section>

<?php include __DIR__ . '/_nav.php'; ?>

<?php if ($dbFingerprint !== ''): ?>
    <div class="field-help" style="margin-bottom:12px;">Bağlı veritabanı: <strong><?= htmlspecialchars($dbFingerprint, ENT_QUOTES, 'UTF-8') ?></strong></div>
<?php endif; ?>

<?php if ($flash !== ''): ?>
    <div class="alert <?= stripos($flash, 'kaydedilemedi') !== false ? 'alert--danger' : 'alert--success' ?>" style="display:block;margin-bottom:12px;white-space:pre-wrap;">
        <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if ($testResult !== ''): ?>
    <div class="alert <?= stripos($testResult, 'BASARILI') !== false ? 'alert--success' : 'alert--danger' ?>" style="display:block;margin-bottom:12px;white-space:pre-wrap;">
        <?= htmlspecialchars($testResult, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<form id="mailSettingsForm" method="post" action="<?= htmlspecialchars(AdminAuth::url('/email/settings'), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

    <section class="card">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">SMTP</span>
                <h2 class="card-title">Gönderim ayarları</h2>
            </div>
            <span class="badge <?= $enabled ? 'dot success' : 'dot danger' ?>"><?= $enabled ? 'Aktif' : 'Pasif' ?></span>
        </div>
        <div class="form-grid">
            <div class="field span-2">
                <label class="switch" style="display:flex;align-items:center;gap:10px;">
                    <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                    <span class="field-label" style="margin:0;">Mail gönderimi aktif</span>
                </label>
            </div>
            <div class="field span-2">
                <label class="field-label" for="from_email">Gönderen e-posta</label>
                <input id="from_email" class="input" type="email" name="from_email" placeholder="noreply@vegasroyalspin.com" value="<?= htmlspecialchars((string) ($settings['from_email'] ?? $settings['mail_from_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="field">
                <label class="field-label" for="smtp_host">SMTP Host</label>
                <input id="smtp_host" class="input" type="text" name="smtp_host" placeholder="mail.vegasroyalspin.com" value="<?= htmlspecialchars((string) ($settings['smtp_host'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="field">
                <label class="field-label" for="smtp_port">SMTP Port</label>
                <input id="smtp_port" class="input" type="number" min="1" max="65535" name="smtp_port" placeholder="465" value="<?= htmlspecialchars((string) ($settings['smtp_port'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="field">
                <label class="field-label" for="smtp_user">SMTP Kullanıcı</label>
                <input id="smtp_user" class="input" type="text" name="smtp_user" value="<?= htmlspecialchars((string) ($settings['smtp_user'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="field">
                <label class="field-label" for="smtp_password">SMTP Şifre</label>
                <input id="smtp_password" class="input" type="password" name="smtp_password" placeholder="Değiştirmek için yeni şifre girin">
                <div class="field-help">Boş bırakırsan mevcut şifre korunur.</div>
            </div>
        </div>
    </section>

    <section class="card" style="margin-top:16px;">
        <div class="card-head">
            <div class="card-title-wrap">
                <span class="eyebrow">IMAP</span>
                <h2 class="card-title">Gelen kutusu ayarları</h2>
            </div>
            <span class="badge <?= $imapEnabled ? 'dot success' : 'dot danger' ?>"><?= $imapEnabled ? 'Aktif' : 'Pasif' ?></span>
        </div>
        <div class="form-grid">
            <div class="field span-2">
                <label class="switch" style="display:flex;align-items:center;gap:10px;">
                    <input type="checkbox" name="imap_enabled" value="1" <?= $imapEnabled ? 'checked' : '' ?>>
                    <span class="field-label" style="margin:0;">Gelen kutusu (IMAP) aktif</span>
                </label>
                <div class="field-help">Açıkken E-posta → Gelen e-postalar, bu hesap üzerinden okunur.</div>
            </div>
            <div class="field">
                <label class="field-label" for="imap_host">IMAP Host</label>
                <input id="imap_host" class="input" type="text" name="imap_host" placeholder="mail.vegasroyalspin.com" value="<?= htmlspecialchars($imapHostValue, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="field">
                <label class="field-label" for="imap_port">IMAP Port</label>
                <input id="imap_port" class="input" type="number" min="1" max="65535" name="imap_port" placeholder="993" value="<?= htmlspecialchars($imapPortValue, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="field">
                <label class="field-label" for="imap_user">IMAP Kullanıcı</label>
                <input id="imap_user" class="input" type="text" name="imap_user" placeholder="noreply@vegasroyalspin.com" value="<?= htmlspecialchars($imapUserValue, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="field">
                <label class="field-label" for="imap_password">IMAP Şifre</label>
                <input id="imap_password" class="input" type="password" name="imap_password" placeholder="Boşsa SMTP şifresi kullanılır">
                <div class="field-help">Boş bırakırsan SMTP şifresi veya kayıtlı IMAP şifresi kullanılır.</div>
            </div>
            <div class="field">
                <label class="field-label" for="imap_encryption">Şifreleme</label>
                <select id="imap_encryption" class="input" name="imap_encryption">
                    <option value="ssl" <?= $imapEncValue === 'ssl' ? 'selected' : '' ?>>SSL (993)</option>
                    <option value="tls" <?= $imapEncValue === 'tls' ? 'selected' : '' ?>>STARTTLS (143)</option>
                    <option value="none" <?= $imapEncValue === 'none' ? 'selected' : '' ?>>Yok</option>
                </select>
            </div>
            <div class="field span-2">
                <div class="field-help">
                    Billion Mail için önerilen: Host <code>mail.vegasroyalspin.com</code>, Port <code>993</code>, Kullanıcı <code>noreply@vegasroyalspin.com</code>, Şifreleme <code>SSL</code>.
                </div>
            </div>
        </div>
        <div class="form-actions">
            <a class="btn btn--ghost" href="<?= htmlspecialchars(AdminAuth::url('/email/inbox'), ENT_QUOTES, 'UTF-8') ?>">Gelen kutusu</a>
            <span class="spacer"></span>
            <button class="btn btn--primary" type="submit">Kaydet</button>
        </div>
    </section>
</form>

<section class="card" style="margin-top:16px;">
    <div class="card-head">
        <div class="card-title-wrap">
            <span class="eyebrow">Test</span>
            <h2 class="card-title">Test mail gönder</h2>
        </div>
    </div>
    <form method="post" action="<?= htmlspecialchars(AdminAuth::url('/email/settings/test'), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <div class="form-grid">
            <div class="field span-2">
                <label class="field-label" for="test_email">Test alıcı adresi</label>
                <input id="test_email" class="input" type="email" name="test_email" placeholder="kendi-mailin@example.com" value="<?= htmlspecialchars((string) ($settings['from_email'] ?? $settings['mail_from_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            </div>
        </div>
        <div class="form-actions">
            <span class="spacer"></span>
            <button class="btn btn--primary" type="submit">Test Mail Gönder</button>
        </div>
    </form>
</section>
