<?php

$configRow = is_array($configRow ?? null) ? $configRow : [];
$flash = trim((string) ($flash ?? ''));
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$currencyValue = strtoupper(trim((string) ($configRow['currency'] ?? '')));
if ($currencyValue === '') {
    $currencyValue = GscPlusService::DEFAULT_CURRENCY;
}
$callbackUrl = (string) ($callbackUrl ?? '');
$callbackAlias = (string) ($callbackAlias ?? '');

$agentWallet = is_array($agentWallet ?? null) ? $agentWallet : null;
$contractedCurrencies = [];
$walletTotal = 0.0;
foreach (($agentWallet['currencies'] ?? []) as $walletRow) {
    $code = strtoupper(trim((string) ($walletRow['currency'] ?? '')));
    if ($code !== '') {
        $contractedCurrencies[] = $code;
    }
    $walletTotal += (float) ($walletRow['current_balance'] ?? 0);
}
if ($contractedCurrencies === []) {
    $contractedCurrencies = GscPlusService::STAGING_CURRENCIES;
}
$currencyMismatch = $contractedCurrencies !== [] && !in_array($currencyValue, $contractedCurrencies, true);
$walletEmpty = $agentWallet !== null && $walletTotal <= 0;
$currencyChoices = array_values(array_unique(array_merge(
    GscPlusService::STAGING_CURRENCIES,
    $contractedCurrencies,
    $currencyValue !== '' ? [$currencyValue] : []
)));
?>
<style>
    .gsc-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 18px; align-items: start; }
    .gsc-card { border: 1px solid var(--border); border-radius: 18px; background: var(--bg-card); padding: 18px; box-shadow: var(--shadow-card); }
    .gsc-actions { display: flex; flex-direction: column; gap: 10px; }
    .gsc-stat { display: flex; justify-content: space-between; gap: 14px; padding: 12px 0; border-bottom: 1px solid var(--border); }
    .gsc-stat:last-child { border-bottom: 0; }
    .gsc-secret { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; letter-spacing: .02em; }
    .gsc-help { color: var(--t-muted); font-size: 13px; line-height: 1.45; margin-top: 6px; }
    .gsc-code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; word-break: break-all; }
    .gsc-banner { margin-bottom: 16px; padding: 14px 16px; border-radius: 14px; border: 1px solid rgba(252,172,0,.35); background: rgba(252,172,0,.08); color: var(--t); font-size: 13px; line-height: 1.5; }
    .gsc-banner strong { color: #fcac00; }
    .gsc-banner--danger { border-color: rgba(255,99,115,.45); background: rgba(132,32,41,.18); }
    .gsc-banner--danger strong { color: #ff8a96; }
    @media (max-width: 900px) { .gsc-grid { grid-template-columns: 1fr; } }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Oyunlar · Gaming Soft</span>
        <h1 class="hero-title">Gaming Soft <span class="accent">(GSC+ v2.0.6)</span></h1>
        <p class="hero-sub">Seamless wallet callback, product/game sync ve launch-game entegrasyonu.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn--primary" type="submit" form="gscSettingsForm">Ayarları Kaydet</button>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert" style="margin-bottom:16px"><?= $text($flash) ?></div>
<?php endif; ?>

<div class="gsc-banner">
    <strong>VGY1 staging:</strong>
    Operator code <code>VGY1</code>, URL <code>https://staging.gsimw.com</code>.
    Açık para birimleri: <code><?= $text(implode(', ', GscPlusService::STAGING_CURRENCIES)) ?></code>
    (IDR2 / VND2 oranı 1:1000). Site görünümü TRY olsa da GSC+ staging’de TRY henüz yok —
    launch ve wallet <code>IDR</code> (veya ürünün kendi para birimi) ile gider.
    Resmi / production ortamı GSC+ tarafında hâlâ kurulumda; Pragmatic URL’leri
    <code>prerelease-env.biz</code> UAT’idir. Kiosk credit (agent wallet) sıfırsa
    sağlayıcı oturumu “Un-Authorized / not logged in” ile düşer — GSC+ destekten
    top-up isteyin.
</div>

<?php if ($walletEmpty): ?>
    <div class="gsc-banner gsc-banner--danger">
        <strong>Agent wallet boş:</strong> 3.12 sorgusu tüm sözleşmeli para birimlerinde
        0 bakiye döndü. Launch URL gelse bile Pragmatic / DreamGaming oturumu reddedebilir.
        GSC+ customer support’a kiosk credit top-up tutarını iletin.
    </div>
<?php endif; ?>

<div class="gsc-grid">
    <form id="gscSettingsForm" class="gsc-card" method="post" action="<?= $text(AdminAuth::url('/gamingsoft/settings')) ?>">
        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">

        <div class="field">
            <label class="field-label" for="operator_code">Operator Code</label>
            <input id="operator_code" class="input" type="text" name="operator_code" value="<?= $text($configRow['operator_code'] ?? '') ?>" maxlength="32" autocomplete="off" required placeholder="VGY1">
            <p class="gsc-help">GSC+ Agency Code (staging: <code>VGY1</code>).</p>
        </div>

        <div class="field">
            <label class="field-label" for="secret_key">Secret Key</label>
            <input id="secret_key" class="input gsc-secret" type="password" name="secret_key" value="" placeholder="<?= trim((string) ($configRow['secret_key'] ?? '')) !== '' ? 'Mevcut secret korunacak' : 'GSC+ secret_key' ?>" autocomplete="new-password">
            <p class="gsc-help">MD5 imza için kullanılır. Boş bırakılırsa mevcut değer korunur. Secret’ı git/repo’ya yazmayın.</p>
        </div>

        <div class="field">
            <label class="field-label" for="operator_url">Operator URL</label>
            <input id="operator_url" class="input" type="url" name="operator_url" value="<?= $text($configRow['operator_url'] ?? 'https://staging.gsimw.com') ?>" placeholder="https://staging.gsimw.com">
            <p class="gsc-help">Staging: <code>https://staging.gsimw.com</code> · Production hazır olunca GSC+ yeni URL verecek.</p>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
            <div class="field">
                <label class="field-label" for="currency">Primary Currency</label>
                <select id="currency" class="input" name="currency">
                    <?php foreach ($currencyChoices as $choice): ?>
                        <option value="<?= $text($choice) ?>" <?= $choice === $currencyValue ? 'selected' : '' ?>><?= $text($choice) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="gsc-help">
                    Varsayılan launch tercihi (ürün kendi currency’si ile override edilir).
                    Sözleşmeli: <code><?= $text(implode(', ', $contractedCurrencies)) ?></code>
                    <?php if ($currencyMismatch): ?>
                        <br><strong>Uyarı:</strong> <?= $text($currencyValue) ?> agent cüzdanında tanımlı değil.
                    <?php endif; ?>
                </p>
            </div>
            <div class="field">
                <label class="field-label" for="language_code">Language Code</label>
                <input id="language_code" class="input" type="number" name="language_code" value="<?= $text((string) (int) ($configRow['language_code'] ?? 0)) ?>" min="0" max="50">
                <p class="gsc-help">IDR için önerilen: <code>4</code> (Indonesia).</p>
            </div>
            <div class="field">
                <label class="field-label" for="channel_code">Channel Code</label>
                <input id="channel_code" class="input" type="text" name="channel_code" value="<?= $text($configRow['channel_code'] ?? 'gscp') ?>">
            </div>
        </div>

        <div class="field">
            <label class="field-label" for="operator_lobby_url">Operator Lobby URL</label>
            <input id="operator_lobby_url" class="input" type="url" name="operator_lobby_url" value="<?= $text($configRow['operator_lobby_url'] ?? '') ?>" placeholder="https://yoursite.com">
            <p class="gsc-help">Launch Game için client site URL (operator_lobby_url).</p>
        </div>

        <div class="field">
            <label class="field-label" for="callback_allowed_ips">Callback Allowed IPs (opsiyonel)</label>
            <textarea id="callback_allowed_ips" class="input" name="callback_allowed_ips" rows="2" placeholder="virgülle ayırın"><?= $text($configRow['callback_allowed_ips'] ?? '') ?></textarea>
        </div>

        <label class="field" style="display:flex;align-items:center;gap:10px;margin-top:8px">
            <input type="checkbox" name="is_active" value="1" <?= !empty($configRow['is_active']) ? 'checked' : '' ?>>
            <span>Gaming Soft entegrasyonunu aktif et</span>
        </label>
    </form>

    <aside class="gsc-card">
        <div class="gsc-stat"><span>Ürünler</span><strong><?= (int) ($productsCount ?? 0) ?></strong></div>
        <div class="gsc-stat"><span>Aktif oyunlar</span><strong><?= (int) ($gamesCount ?? 0) ?></strong></div>
        <div class="gsc-stat"><span>İşlemler</span><strong><?= (int) ($transactionsCount ?? 0) ?></strong></div>
        <div class="gsc-stat">
            <span>Products sync</span>
            <strong><?= $text($configRow['products_synced_at'] ?? '—') ?></strong>
        </div>
        <div class="gsc-stat">
            <span>Games sync</span>
            <strong><?= $text($configRow['games_synced_at'] ?? '—') ?></strong>
        </div>

        <?php if ($agentWallet !== null): ?>
            <div style="margin-top:16px">
                <div class="field-label">Agent Wallet (<?= !empty($agentWallet['is_credit']) ? 'credit' : 'buy-in' ?>)</div>
                <?php foreach (($agentWallet['currencies'] ?? []) as $walletRow): ?>
                    <div class="gsc-stat">
                        <span><?= $text($walletRow['currency'] ?? '') ?></span>
                        <strong><?= $text(number_format((float) ($walletRow['current_balance'] ?? 0), 4, '.', ',')) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif (trim((string) ($agentWalletError ?? '')) !== ''): ?>
            <p class="gsc-help" style="margin-top:12px">Agent wallet sorgulanamadı: <?= $text($agentWalletError) ?></p>
        <?php endif; ?>

        <div class="gsc-actions" style="margin-top:16px">
            <form method="post" action="<?= $text(AdminAuth::url('/gamingsoft/sync-products')) ?>">
                <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                <button class="btn btn--ghost" type="submit" style="width:100%">Ürünleri Sync Et</button>
            </form>
            <form method="post" action="<?= $text(AdminAuth::url('/gamingsoft/sync-games')) ?>">
                <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                <button class="btn btn--ghost" type="submit" style="width:100%">Oyunları Sync Et</button>
            </form>
        </div>

        <div style="margin-top:18px">
            <div class="field-label">Callback URL (GSC+ paneline verin)</div>
            <p class="gsc-code gsc-help"><?= $text($callbackUrl) ?></p>
            <p class="gsc-help" style="margin-top:10px">
                balance → <code>.../v1/api/seamless/balance</code><br>
                withdraw → <code>.../v1/api/seamless/withdraw</code><br>
                deposit → <code>.../v1/api/seamless/deposit</code><br>
                pushbetdata → <code>.../v1/api/seamless/pushbetdata</code>
            </p>
            <p class="gsc-help" style="margin-top:8px">Örnek tam path:</p>
            <p class="gsc-code gsc-help"><?= $text($callbackAlias) ?>/balance</p>
        </div>
    </aside>
</div>
