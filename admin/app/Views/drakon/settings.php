<?php

$configRow = is_array($configRow ?? null) ? $configRow : [];
$flash = trim((string) ($flash ?? ''));
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$backendBase = defined('BACKEND_URL') ? rtrim((string) BACKEND_URL, '/') : rtrim((string) (getenv('BACKEND_URL') ?: getenv('BACKEND_FALLBACK_URL') ?: 'https://bo-nexthub.site'), '/');
$diagnostics = is_array($integrationDiagnostics ?? null) ? $integrationDiagnostics : [];
$panelUrl = (string) ($diagnostics['drakon_panel_callback_url'] ?? $configRow['site_endpoint'] ?? $backendBase);
$webhookUrl = (string) ($diagnostics['drakon_webhook_url'] ?? $diagnostics['drakon_webhook_probe_url'] ?? rtrim($panelUrl, '/') . '/drakon_api');
?>
<style>
    .drakon-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 18px; align-items: start; }
    .drakon-card { border: 1px solid var(--border); border-radius: 18px; background: var(--bg-card); padding: 18px; box-shadow: var(--shadow-card); }
    .drakon-actions { display: flex; flex-direction: column; gap: 10px; }
    .drakon-stat { display: flex; justify-content: space-between; gap: 14px; padding: 12px 0; border-bottom: 1px solid var(--border); }
    .drakon-stat:last-child { border-bottom: 0; }
    .drakon-secret { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; letter-spacing: .02em; }
    @media (max-width: 900px) { .drakon-grid { grid-template-columns: 1fr; } }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Oyunlar · Drakon</span>
        <h1 class="hero-title">Drakon <span class="accent">Entegrasyonu</span></h1>
        <p class="hero-sub">Resmi Drakon API: game_launch + tek webhook endpoint (<code>/drakon_api</code>).</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn--primary" type="submit" form="drakonSettingsForm">Ayarları Kaydet</button>
    </div>
</section>

<div class="drakon-grid">
    <form id="drakonSettingsForm" class="drakon-card" method="post" action="<?= $text(AdminAuth::url('/drakon/settings')) ?>">
        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">

        <div class="field">
            <label class="field-label" for="agent_code">Agent Code</label>
            <input id="agent_code" class="input" type="text" name="agent_code" value="<?= $text($configRow['agent_code'] ?? '') ?>" autocomplete="off">
        </div>

        <div class="field">
            <label class="field-label" for="agent_token">Agent Token</label>
            <input id="agent_token" class="input drakon-secret" type="password" name="agent_token" value="" placeholder="<?= trim((string) ($configRow['agent_token'] ?? '')) !== '' ? 'Mevcut token korunacak' : 'Agent token girin' ?>" autocomplete="new-password">
        </div>

        <div class="field">
            <label class="field-label" for="agent_secret">Agent Secret</label>
            <input id="agent_secret" class="input drakon-secret" type="password" name="agent_secret" value="" placeholder="<?= trim((string) ($configRow['agent_secret'] ?? '')) !== '' ? 'Mevcut secret korunacak' : 'Agent secret girin' ?>" autocomplete="new-password">
        </div>

        <div class="field">
            <label class="field-label" for="callback_secret">Callback Secret</label>
            <input id="callback_secret" class="input drakon-secret" type="password" name="callback_secret" value="" placeholder="<?= trim((string) ($configRow['callback_secret'] ?? '')) !== '' ? 'Mevcut callback secret korunacak' : 'Opsiyonel — Drakon imza göndermiyorsa boş bırakın' ?>" autocomplete="new-password">
            <p class="text-muted" style="margin:.35rem 0 0;font-size:12px">Drakon webhook imzası dokümante edilmemiş. Boş bırakırsanız yalnızca IP allowlist kullanılır.</p>
        </div>

        <div class="field">
            <label class="field-label" for="callback_allowed_ips">Callback Allowed IPs</label>
            <input id="callback_allowed_ips" class="input" type="text" name="callback_allowed_ips" value="<?= $text($configRow['callback_allowed_ips'] ?? '') ?>" placeholder="1.2.3.4, 5.6.7.*">
        </div>

        <div class="field">
            <label class="field-label" for="api_base_url">API Base URL</label>
            <input id="api_base_url" class="input" type="url" name="api_base_url" value="<?= $text($configRow['api_base_url'] ?? (getenv('DRAKON_API_BASE_URL') ?: 'https://gator.drakon.casino/api/v1')) ?>">
        </div>

        <div class="field">
            <label class="field-label" for="site_endpoint">Site URL (Drakon Agent Panel)</label>
            <input id="site_endpoint" type="hidden" name="site_endpoint" value="<?= $text($panelUrl) ?>">
            <input class="input" type="url" value="<?= $text($panelUrl) ?>" readonly>
            <p class="text-muted" style="margin:.35rem 0 0;font-size:12px">Drakon agent paneline yazılacak site adresi (otomatik): <code><?= $text($panelUrl) ?></code></p>
            <p class="text-muted" style="margin:.35rem 0 0;font-size:12px"><strong>Drakon agent panel:</strong> yalnızca <code><?= $text($backendBase) ?></code> yazın — <strong>/drakon_api eklemeyin</strong>. Drakon otomatik <code><?= $text($webhookUrl) ?></code> adresine POST atar.</p>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="field">
                <label class="field-label" for="currency_display">Para Birimi</label>
                <input type="hidden" name="currency" value="<?= $text($configRow['currency'] ?? 'TRY') ?>">
                <input id="currency_display" class="input" type="text" value="<?= strtoupper((string) ($configRow['currency'] ?? 'TRY')) === 'TRY' ? '₺' : $text($configRow['currency'] ?? '') ?>" readonly>
            </div>
            <div class="field">
                <label class="field-label">Durum</label>
                <label class="switch" style="margin-top:10px">
                    <input type="checkbox" name="is_active" value="1" <?= !empty($configRow['is_active']) ? 'checked' : '' ?>>
                    <span class="track"></span>
                    Drakon aktif
                </label>
            </div>
        </div>

        <div class="form-actions admin-action-spaced-lg">
            <span class="badge dot <?= !empty($configRow['is_active']) ? 'success' : 'danger' ?>"><?= !empty($configRow['is_active']) ? 'Aktif' : 'Pasif' ?></span>
            <span class="spacer"></span>
            <button class="btn btn--primary" type="submit">Ayarları Kaydet</button>
        </div>
    </form>

    <aside class="drakon-card">
        <h2 style="margin:0 0 12px;font-size:18px;color:var(--t-base)">Drakon Panel URL</h2>
        <div class="drakon-stat" style="flex-direction:column;align-items:flex-start;gap:4px"><span>Agent panel site URL (kopyala)</span><code class="drakon-secret" style="word-break:break-all"><?= $text($panelUrl) ?></code></div>
        <div class="drakon-stat" style="flex-direction:column;align-items:flex-start;gap:4px"><span>Webhook URL (Drakon POST)</span><code class="drakon-secret" style="word-break:break-all"><?= $text($webhookUrl) ?></code></div>
        <?php if (!empty($diagnostics['webhook_handler']['ok'])): ?>
            <div class="drakon-stat"><span>Webhook (public + handler)</span><strong style="color:var(--success,#059669)">OK</strong></div>
        <?php elseif ($diagnostics !== []): ?>
            <div class="drakon-stat" style="flex-direction:column;align-items:flex-start;gap:4px">
                <span>Webhook testi</span>
                <strong style="color:var(--danger,#dc2626)">Hata</strong>
                <?php if (!empty($diagnostics['webhook_handler']['message'])): ?>
                    <span class="text-muted" style="font-size:12px"><?= $text($diagnostics['webhook_handler']['message']) ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <p class="text-muted" style="margin:0 0 12px;font-size:12px">422 alıyorsanız ve curl testi OK ise: <strong>Drakon agent panelinde</strong> (gator.drakon.casino) Site URL = <code><?= $text($panelUrl) ?></code> olmalı — yalnızca admin panel yeterli değil.</p>
        <div class="drakon-stat" style="flex-direction:column;align-items:flex-start;gap:4px"><span>curl test</span><code class="drakon-secret" style="word-break:break-all;font-size:11px">curl -X POST "<?= $text($webhookUrl) ?>" -H "Content-Type: application/json" -d '{"method":"user_balance","user_id":"1"}'</code></div>
        <div class="drakon-stat"><span>Provider</span><strong><?= (int) ($providersCount ?? 0) ?></strong></div>
        <div class="drakon-stat"><span>Oyun</span><strong><?= (int) ($gamesCount ?? 0) ?></strong></div>
        <div class="drakon-actions admin-action-spaced">
            <form method="post" action="<?= $text(AdminAuth::url('/drakon/test-webhook')) ?>">
                <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                <button class="btn btn--secondary admin-full-action" type="submit">Webhook Test</button>
            </form>
            <form method="post" action="<?= $text(AdminAuth::url('/drakon/sync-providers')) ?>">
                <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                <button class="btn btn--secondary admin-full-action" type="submit">Provider Sync</button>
            </form>
            <form method="post" action="<?= $text(AdminAuth::url('/drakon/sync-games')) ?>">
                <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                <button class="btn btn--primary admin-full-action" type="submit">Oyun Sync</button>
            </form>
        </div>
    </aside>
</div>
