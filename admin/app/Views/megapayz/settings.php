<?php

$configRow = is_array($configRow ?? null) ? $configRow : [];
$flash = trim((string) ($flash ?? ''));
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$callbackUrl = (defined('BACKEND_URL') ? rtrim((string) BACKEND_URL, '/') : rtrim((string) (getenv('BACKEND_URL') ?: getenv('BACKEND_FALLBACK_URL') ?: 'https://admin.vegasroyalspin.com'), '/')) . '/api/v2/megapayz-callback';
?>
<style>
    .megapayz-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 18px; align-items: start; }
    .megapayz-card { border: 1px solid var(--border); border-radius: 18px; background: var(--bg-card); padding: 18px; box-shadow: var(--shadow-card); }
    .megapayz-stat { display: flex; justify-content: space-between; gap: 14px; padding: 12px 0; border-bottom: 1px solid var(--border); }
    .megapayz-stat:last-child { border-bottom: 0; }
    .megapayz-secret { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; letter-spacing: .02em; }
    .megapayz-help { color: var(--t-muted); font-size: 13px; line-height: 1.45; margin-top: 6px; }
    @media (max-width: 900px) { .megapayz-grid { grid-template-columns: 1fr; } }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Finans · MegaPayz</span>
        <h1 class="hero-title">MegaPayz <span class="accent">Entegrasyonu</span></h1>
        <p class="hero-sub">SID, private key, API base URL ve callback davranışı dedicated provider ekranından yönetilir.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn--primary" type="submit" form="megapayzSettingsForm">Ayarları Kaydet</button>
    </div>
</section>

<div class="megapayz-grid">
    <form id="megapayzSettingsForm" class="megapayz-card" method="post" action="<?= $text(AdminAuth::url('/megapayz/settings')) ?>">
        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">

        <div class="field">
            <label class="field-label" for="sid">SID</label>
            <input id="sid" class="input" type="text" name="sid" value="<?= $text($configRow['sid'] ?? '') ?>" autocomplete="off">
        </div>

        <div class="field">
            <label class="field-label" for="private_key">Private Key</label>
            <input id="private_key" class="input megapayz-secret" type="password" name="private_key" value="" placeholder="<?= trim((string) ($configRow['private_key'] ?? '')) !== '' ? 'Mevcut private key korunacak' : 'Private key girin' ?>" autocomplete="new-password">
            <p class="megapayz-help">Boş bırakırsanız kayıtlı private key korunur.</p>
        </div>

        <div class="field">
            <label class="field-label" for="api_base_url">API Base URL</label>
            <input id="api_base_url" class="input" type="url" name="api_base_url" value="<?= $text($configRow['api_base_url'] ?? (getenv('MEGAPAYZ_API_BASE_URL') ?: 'https://api.megapayz.net')) ?>">
        </div>

        <div class="field">
            <label class="field-label">Durum</label>
            <label class="switch" style="margin-top:10px">
                <input type="checkbox" name="is_active" value="1" <?= !empty($configRow['is_active']) ? 'checked' : '' ?>>
                <span class="track"></span>
                MegaPayz aktif
            </label>
        </div>

        <div class="form-actions admin-action-spaced-lg">
            <span class="badge dot <?= !empty($configRow['is_active']) ? 'success' : 'danger' ?>"><?= !empty($configRow['is_active']) ? 'Aktif' : 'Pasif' ?></span>
            <span class="spacer"></span>
            <button class="btn btn--primary" type="submit">Ayarları Kaydet</button>
        </div>
    </form>

    <aside class="megapayz-card">
        <h2 style="margin:0 0 12px;font-size:18px;color:var(--t-base)">Operasyon Özeti</h2>
        <div class="megapayz-stat"><span>Metot</span><strong><?= (int) ($methodsCount ?? 0) ?></strong></div>
        <div class="megapayz-stat"><span>İşlem</span><strong><?= (int) ($transactionsCount ?? 0) ?></strong></div>
        <div class="megapayz-stat"><span>Callback URL</span><code><?= $text($callbackUrl) ?></code></div>
        <div style="margin-top:16px">
            <a class="btn btn--secondary admin-full-action" href="<?= $text(AdminAuth::url('/megapayz/methods')) ?>">Ödeme Metotları</a>
        </div>
    </aside>
</div>
