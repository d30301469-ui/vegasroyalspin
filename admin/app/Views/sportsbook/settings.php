<?php

$configRow = is_array($configRow ?? null) ? $configRow : [];
$flash     = trim((string) ($flash ?? ''));
$text      = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

$siteEndpoint = trim((string) ($configRow['site_endpoint'] ?? ''));
$webhookUrl   = $siteEndpoint !== '' ? rtrim($siteEndpoint, '/') . '/sportsbook_api' : '— site_endpoint ayarlanmamış —';
$isActive     = !empty($configRow['is_active']) && $configRow['is_active'] !== '0';
$apiMode      = strtolower(trim((string) ($configRow['api_mode'] ?? 'seamless')));
$apiMode      = in_array($apiMode, ['seamless', 'transfer'], true) ? $apiMode : 'seamless';
?>
<style>
    .sb-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 18px; align-items: start; }
    .sb-card { border: 1px solid var(--border); border-radius: 18px; background: var(--bg-card); padding: 18px; box-shadow: var(--shadow-card); }
    .sb-actions { display: flex; flex-direction: column; gap: 10px; }
    .sb-stat { display: flex; justify-content: space-between; gap: 14px; padding: 12px 0; border-bottom: 1px solid var(--border); }
    .sb-stat:last-child { border-bottom: 0; }
    .sb-secret { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; letter-spacing: .02em; }
    .sb-help { color: var(--t-muted); font-size: 13px; line-height: 1.45; margin-top: 6px; }
    .sb-webhook-url { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; word-break: break-all; background: var(--bg); padding: 8px 10px; border-radius: 8px; border: 1px solid var(--border); }
    @media (max-width: 900px) { .sb-grid { grid-template-columns: 1fr; } }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Spor · Sportsbook</span>
        <h1 class="hero-title">Sportsbook <span class="accent">(BetBy)</span></h1>
        <p class="hero-sub">Agent kimlik bilgileri, Ed25519 imzalama anahtarları ve seamless wallet callback endpointi buradan yönetilir.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn--primary" type="submit" form="sportsbookSettingsForm">Ayarları Kaydet</button>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert alert--info" style="margin-bottom:16px"><?= $text($flash) ?></div>
<?php endif; ?>

<div class="sb-grid">

    <form id="sportsbookSettingsForm" class="sb-card" method="post"
          action="<?= $text(AdminAuth::url('/sportsbook/settings')) ?>">
        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">

        <div class="field">
            <label class="field-label" for="agent_code">Agent Code</label>
            <input id="agent_code" class="input" type="text" name="agent_code"
                   value="<?= $text($configRow['agent_code'] ?? '') ?>"
                   placeholder="Sportsbook agent code" autocomplete="off">
            <p class="sb-help">Sağlayıcı back-office'ten verilen agent kodu.</p>
        </div>

        <div class="field">
            <label class="field-label" for="api_base_url">API Endpoint</label>
            <input id="api_base_url" class="input" type="url" name="api_base_url"
                   value="<?= $text($configRow['api_base_url'] ?? '') ?>"
                   placeholder="https://api.ilomhzji.win" autocomplete="off">
            <p class="sb-help">Operatör API adresi (GetGameUrl vb. istekler buraya POST edilir).</p>
        </div>

        <div class="field">
            <label class="field-label" for="api_token">API Token</label>
            <input id="api_token" class="input sb-secret" type="password" name="api_token"
                   value=""
                   placeholder="<?= trim((string) ($configRow['api_token'] ?? '')) !== '' ? 'Mevcut token korunacak' : 'Sportsbook API token' ?>"
                   autocomplete="new-password">
            <p class="sb-help">API isteklerinde ve wallet callback doğrulamasında kullanılan token. Boş bırakırsanız mevcut değer korunur.</p>
        </div>

        <div class="field">
            <label class="field-label" for="api_mode">API Mode</label>
            <select id="api_mode" class="input" name="api_mode">
                <option value="seamless" <?= $apiMode === 'seamless' ? 'selected' : '' ?>>Seamless (wallet callback)</option>
                <option value="transfer" <?= $apiMode === 'transfer' ? 'selected' : '' ?>>Transfer</option>
            </select>
            <p class="sb-help">Seamless modda bakiye GetBalance/ChangeBalance callback'leri ile yönetilir.</p>
        </div>

        <div class="field">
            <label class="field-label" for="sign_private_key">Request Sign Private Key (Ed25519)</label>
            <input id="sign_private_key" class="input sb-secret" type="password" name="sign_private_key"
                   value=""
                   placeholder="<?= trim((string) ($configRow['sign_private_key'] ?? '')) !== '' ? 'Mevcut anahtar korunacak' : 'base64 (32 byte) private key' ?>"
                   autocomplete="new-password">
            <p class="sb-help">Giden API isteklerini imzalamak için kullanılan Ed25519 private key (base64). Boş bırakırsanız mevcut değer korunur.</p>
        </div>

        <div class="field">
            <label class="field-label" for="verify_public_key">Callback Verify Public Key (Ed25519)</label>
            <input id="verify_public_key" class="input sb-secret" type="password" name="verify_public_key"
                   value=""
                   placeholder="<?= trim((string) ($configRow['verify_public_key'] ?? '')) !== '' ? 'Mevcut anahtar korunacak' : 'base64 (32 byte) public key' ?>"
                   autocomplete="new-password">
            <p class="sb-help">Gelen wallet callback imzalarını doğrulamak için kullanılan Ed25519 public key (base64).</p>
        </div>

        <div class="field">
            <label class="field-label" for="currency">Para Birimi</label>
            <input id="currency" class="input" type="text" name="currency"
                   value="<?= $text(strtoupper(trim((string) ($configRow['currency'] ?? 'TRY')))) ?>"
                   placeholder="TRY" maxlength="8" autocomplete="off">
            <p class="sb-help">Oyun başlatmada iletilen para birimi kodu. Örn: <code>TRY</code>, <code>USD</code>, <code>EUR</code>.</p>
        </div>

        <div class="field">
            <label class="field-label" for="lang">Dil</label>
            <input id="lang" class="input" type="text" name="lang"
                   value="<?= $text(strtolower(trim((string) ($configRow['lang'] ?? 'tr')))) ?>"
                   placeholder="tr" maxlength="8" autocomplete="off">
        </div>

        <div class="field">
            <label class="field-label" for="site_endpoint">Site Endpoint (Callback Base URL)</label>
            <input id="site_endpoint" class="input" type="url" name="site_endpoint"
                   value="<?= $text($siteEndpoint) ?>"
                     placeholder="https://admin.vegasroyalspin.com">
            <p class="sb-help">
                Sağlayıcı back-office'e kaydedeceğiniz callback URL'nin kökü.
                Bu değere <code>/sportsbook_api</code> eklenerek callback adresi oluşturulur.
            </p>
        </div>

        <div class="field">
            <div style="display:flex;align-items:center;gap:10px;padding:12px 0;">
                <input id="is_active" type="checkbox" name="is_active" value="1"
                       <?= $isActive ? 'checked' : '' ?> style="width:18px;height:18px;cursor:pointer;">
                <label for="is_active" style="cursor:pointer;font-weight:600">
                    Sportsbook entegrasyonunu aktif et
                </label>
            </div>
        </div>
    </form>

    <div class="sb-actions">

        <div class="sb-card">
            <div style="font-weight:700;margin-bottom:12px">📊 İstatistikler</div>
            <div class="sb-stat">
                <span>Toplam Oturum</span>
                <strong><?= number_format((int) ($sessionsCount ?? 0)) ?></strong>
            </div>
            <div class="sb-stat">
                <span>Toplam İşlem</span>
                <strong><?= number_format((int) ($transactionsCount ?? 0)) ?></strong>
            </div>
            <div class="sb-stat">
                <span>Durum</span>
                <strong><?= $isActive ? '<span style="color:var(--green)">Aktif</span>' : '<span style="color:var(--t-muted)">Pasif</span>' ?></strong>
            </div>
        </div>

        <div class="sb-card">
            <div style="font-weight:700;margin-bottom:10px">🔗 Callback URL</div>
            <p class="sb-help" style="margin-bottom:8px">
                Sağlayıcı back-office'teki agent ayarlarına bu adresi kaydedin:
            </p>
            <div class="sb-webhook-url"><?= $text($webhookUrl) ?></div>
        </div>

        <div class="sb-card">
            <div style="font-weight:700;margin-bottom:12px">🗂 Modüller</div>
            <?php
            $moduleLinks = [
                'sportsbook-sessions'     => ['label' => 'Oturumlar',      'path' => '/admin/tables/sportsbook_sessions'],
                'sportsbook-transactions' => ['label' => 'İşlemler',       'path' => '/admin/tables/sportsbook_transactions'],
                'sportsbook-wallet-logs'  => ['label' => 'Wallet Logları', 'path' => '/admin/tables/sportsbook_wallet_logs'],
            ];
            foreach ($moduleLinks as $mKey => $mInfo):
                $mUrl = AdminAuth::url($mInfo['path']);
            ?>
            <div class="sb-stat">
                <span><?= $text($mInfo['label']) ?></span>
                <a href="<?= $text($mUrl) ?>" class="btn btn--xs">Görüntüle</a>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>
