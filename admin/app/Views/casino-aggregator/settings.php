<?php

$configRow = is_array($configRow ?? null) ? $configRow : [];
$flash = trim((string) ($flash ?? ''));
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

$siteEndpoint = trim((string) ($configRow['site_endpoint'] ?? ''));
$webhookUrl = $siteEndpoint !== ''
    ? rtrim($siteEndpoint, '/') . '/api/v2/casino-aggregator-wallet'
    : '— site_endpoint ayarlanmamış —';
$isActive = !empty($configRow['is_active']) && $configRow['is_active'] !== '0';
$apiMode = strtolower(trim((string) ($configRow['api_mode'] ?? 'seamless')));
$apiMode = in_array($apiMode, ['seamless', 'transfer'], true) ? $apiMode : 'seamless';
?>
<style>
    .ca-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 18px; align-items: start; }
    .ca-card { border: 1px solid var(--border); border-radius: 18px; background: var(--bg-card); padding: 18px; box-shadow: var(--shadow-card); }
    .ca-actions { display: flex; flex-direction: column; gap: 10px; }
    .ca-stat { display: flex; justify-content: space-between; gap: 14px; padding: 12px 0; border-bottom: 1px solid var(--border); }
    .ca-stat:last-child { border-bottom: 0; }
    .ca-secret { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; letter-spacing: .02em; }
    .ca-help { color: var(--t-muted); font-size: 13px; line-height: 1.45; margin-top: 6px; }
    .ca-webhook-url { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; word-break: break-all; background: var(--bg); padding: 8px 10px; border-radius: 8px; border: 1px solid var(--border); }
    @media (max-width: 900px) { .ca-grid { grid-template-columns: 1fr; } }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Oyunlar · Casino Aggregator</span>
        <h1 class="hero-title">Casino Aggregator <span class="accent">(Operator API)</span></h1>
        <p class="hero-sub">Pragmatic, Evolution ve diğer vendor oyunları — agent kimlik bilgileri, vendor/oyun sync ve seamless wallet callback.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn--primary" type="submit" form="casinoAggregatorSettingsForm">Ayarları Kaydet</button>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert alert--info" style="margin-bottom:16px"><?= $text($flash) ?></div>
<?php endif; ?>

<div class="ca-grid">
    <form id="casinoAggregatorSettingsForm" class="ca-card" method="post"
          action="<?= $text(AdminAuth::url('/casino-aggregator/settings')) ?>">
        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">

        <div class="field">
            <label class="field-label" for="agent_code">Agent Code</label>
            <input id="agent_code" class="input" type="text" name="agent_code"
                   value="<?= $text($configRow['agent_code'] ?? '') ?>" autocomplete="off">
        </div>

        <div class="field">
            <label class="field-label" for="api_base_url">API Endpoint</label>
            <input id="api_base_url" class="input" type="url" name="api_base_url"
                   value="<?= $text($configRow['api_base_url'] ?? '') ?>"
                   placeholder="https://api.example.com" autocomplete="off">
            <p class="ca-help">GetGameUrl, GetVendors, GetVendorGames istekleri buraya POST edilir.</p>
        </div>

        <div class="field">
            <label class="field-label" for="api_token">API Token</label>
            <input id="api_token" class="input ca-secret" type="password" name="api_token" value=""
                   placeholder="<?= trim((string) ($configRow['api_token'] ?? '')) !== '' ? 'Mevcut token korunacak' : 'Agent token' ?>"
                   autocomplete="new-password">
        </div>

        <div class="field">
            <label class="field-label" for="api_mode">API Mode</label>
            <select id="api_mode" class="input" name="api_mode">
                <option value="seamless" <?= $apiMode === 'seamless' ? 'selected' : '' ?>>Seamless (wallet callback)</option>
                <option value="transfer" <?= $apiMode === 'transfer' ? 'selected' : '' ?>>Transfer</option>
            </select>
        </div>

        <div class="field">
            <label class="field-label" for="sign_private_key">Request Sign Private Key (Ed25519, opsiyonel)</label>
            <input id="sign_private_key" class="input ca-secret" type="password" name="sign_private_key" value=""
                   placeholder="<?= trim((string) ($configRow['sign_private_key'] ?? '')) !== '' ? 'Mevcut anahtar korunacak' : 'base64 private key' ?>"
                   autocomplete="new-password">
        </div>

        <div class="field">
            <label class="field-label" for="verify_public_key">Callback Verify Public Key (Ed25519, opsiyonel)</label>
            <input id="verify_public_key" class="input ca-secret" type="password" name="verify_public_key" value=""
                   placeholder="<?= trim((string) ($configRow['verify_public_key'] ?? '')) !== '' ? 'Mevcut anahtar korunacak' : 'base64 public key' ?>"
                   autocomplete="new-password">
        </div>

        <div class="field">
            <label class="field-label" for="currency">Para Birimi</label>
            <input id="currency" class="input" type="text" name="currency"
                   value="<?= $text(strtoupper(trim((string) ($configRow['currency'] ?? 'TRY')))) ?>"
                   maxlength="8" autocomplete="off">
        </div>

        <div class="field">
            <label class="field-label" for="lang">Dil</label>
            <input id="lang" class="input" type="text" name="lang"
                   value="<?= $text(strtolower(trim((string) ($configRow['lang'] ?? 'tr')))) ?>"
                   maxlength="8" autocomplete="off">
        </div>

        <div class="field">
            <label class="field-label" for="site_endpoint">Site Endpoint (Callback Base URL)</label>
            <input id="site_endpoint" class="input" type="url" name="site_endpoint"
                   value="<?= $text($siteEndpoint) ?>"
                   placeholder="https://admin.vegasroyalspin.com">
            <p class="ca-help">Sağlayıcı paneline kaydedilecek callback kök URL. Tam adres: <code>/api/v2/casino-aggregator-wallet</code></p>
        </div>

        <div class="field">
            <div style="display:flex;align-items:center;gap:10px;padding:12px 0;">
                <input id="is_active" type="checkbox" name="is_active" value="1"
                       <?= $isActive ? 'checked' : '' ?> style="width:18px;height:18px;cursor:pointer;">
                <label for="is_active" style="cursor:pointer;font-weight:600">Casino aggregator entegrasyonunu aktif et</label>
            </div>
        </div>
    </form>

    <div class="ca-actions">
        <div class="ca-card">
            <div style="font-weight:700;margin-bottom:12px">İstatistikler</div>
            <div class="ca-stat"><span>Vendor</span><strong><?= number_format((int) ($vendorsCount ?? 0)) ?></strong></div>
            <div class="ca-stat"><span>Toplam Oyun</span><strong><?= number_format((int) ($gamesCount ?? 0)) ?></strong></div>
            <div class="ca-stat"><span>Aktif Oyun</span><strong><?= number_format((int) ($activeGamesCount ?? 0)) ?></strong></div>
            <div class="ca-stat"><span>Oturum</span><strong><?= number_format((int) ($sessionsCount ?? 0)) ?></strong></div>
            <div class="ca-stat"><span>İşlem</span><strong><?= number_format((int) ($transactionsCount ?? 0)) ?></strong></div>
            <div class="ca-stat"><span>Durum</span><strong><?= $isActive ? '<span style="color:var(--green)">Aktif</span>' : '<span style="color:var(--t-muted)">Pasif</span>' ?></strong></div>
        </div>

        <div class="ca-card">
            <div style="font-weight:700;margin-bottom:10px">Callback URL</div>
            <div class="ca-webhook-url"><?= $text($webhookUrl) ?></div>
        </div>

        <form class="ca-card" method="post" action="<?= $text(AdminAuth::url('/casino-aggregator/sync-vendors')) ?>">
            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
            <div style="font-weight:700;margin-bottom:8px">Vendor Sync</div>
            <p class="ca-help" style="margin-bottom:12px">GetVendors API ile vendor listesini çeker.</p>
            <button class="btn btn--ghost" type="submit">Vendor Sync</button>
        </form>

        <form class="ca-card" method="post" action="<?= $text(AdminAuth::url('/casino-aggregator/sync-games')) ?>">
            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
            <div style="font-weight:700;margin-bottom:8px">Oyun Sync</div>
            <p class="ca-help" style="margin-bottom:12px">Aktif vendorlar için GetVendorGames ile oyun kataloğunu günceller (vendor başına ~1 sn).</p>
            <button class="btn btn--primary" type="submit">Oyun Sync</button>
        </form>

        <div class="ca-card">
            <div style="font-weight:700;margin-bottom:12px">Modüller</div>
            <?php
            $moduleLinks = [
                'casino-aggregator-vendors' => ['label' => 'Vendorlar', 'path' => '/module?key=casino-aggregator-vendors'],
                'casino-aggregator-games'   => ['label' => 'Oyunlar', 'path' => '/module?key=casino-aggregator-games'],
                'casino-aggregator-sessions' => ['label' => 'Oturumlar', 'path' => '/module?key=casino-aggregator-sessions'],
                'casino-aggregator-transactions' => ['label' => 'İşlemler', 'path' => '/module?key=casino-aggregator-transactions'],
                'casino-aggregator-wallet-logs' => ['label' => 'Wallet Logları', 'path' => '/module?key=casino-aggregator-wallet-logs'],
            ];
            foreach ($moduleLinks as $mInfo):
            ?>
            <div class="ca-stat">
                <span><?= $text($mInfo['label']) ?></span>
                <a href="<?= $text(AdminAuth::url($mInfo['path'])) ?>" class="btn btn--xs">Görüntüle</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
