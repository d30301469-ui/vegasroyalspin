<?php

$configRow = is_array($configRow ?? null) ? $configRow : [];
$flash = trim((string) ($flash ?? ''));
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$currencyOptions = [
    'USD' => 'USD - US Dollar',
    'EUR' => 'EUR - Euro',
    'JPY' => 'JPY - Japanese Yen',
    'USDT' => 'USDT - Tether',
    'ETH' => 'ETH - Ethereum',
    'XRP' => 'XRP - Ripple',
    'LTC' => 'LTC - Litecoin',
    'DOG' => 'DOG - Dogecoin',
    'BTC' => 'BTC - Bitcoin',
    'BCH' => 'BCH - Bitcoin Cash',
];
$localeOptions = [
    'bg' => 'bg - Bulgarian',
    'de' => 'de - German',
    'el' => 'el - Greek',
    'en' => 'en - English',
    'es' => 'es - Spanish',
    'fr' => 'fr - French',
    'id' => 'id - Indonesian',
    'it' => 'it - Italian',
    'ko' => 'ko - Korean',
    'pt-BR' => 'pt-BR - Portuguese (Brazil)',
    'ro' => 'ro - Romanian',
    'ru' => 'ru - Russian',
    'sv' => 'sv - Swedish',
    'tr' => 'tr - Turkish',
    'uk' => 'uk - Ukrainian',
    'zh' => 'zh - Chinese',
];
$currencyValue = strtoupper(trim((string) ($configRow['currency'] ?? 'USD')));
if (!array_key_exists($currencyValue, $currencyOptions)) {
    $currencyValue = 'USD';
}
$localeValue = trim((string) ($configRow['locale'] ?? 'tr'));
foreach (array_keys($localeOptions) as $localeOption) {
    if (strcasecmp($localeValue, $localeOption) === 0) {
        $localeValue = $localeOption;
        break;
    }
}
if (!array_key_exists($localeValue, $localeOptions)) {
    $localeValue = 'tr';
}
$backendBase = defined('BACKEND_URL') ? rtrim((string) BACKEND_URL, '/') : rtrim((string) (getenv('BACKEND_URL') ?: getenv('BACKEND_FALLBACK_URL') ?: ''), '/');
$bgamingCallbackUrl = $backendBase . '/api/v2/bgaming-wallet';
?>
<style>
    .bgaming-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 18px; align-items: start; }
    .bgaming-card { border: 1px solid var(--border); border-radius: 18px; background: var(--bg-card); padding: 18px; box-shadow: var(--shadow-card); }
    .bgaming-actions { display: flex; flex-direction: column; gap: 10px; }
    .bgaming-stat { display: flex; justify-content: space-between; gap: 14px; padding: 12px 0; border-bottom: 1px solid var(--border); }
    .bgaming-stat:last-child { border-bottom: 0; }
    .bgaming-secret { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; letter-spacing: .02em; }
    .bgaming-help { color: var(--t-muted); font-size: 13px; line-height: 1.45; margin-top: 6px; }
    @media (max-width: 900px) { .bgaming-grid { grid-template-columns: 1fr; } }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Oyunlar · BGaming</span>
        <h1 class="hero-title">BGaming <span class="accent">Direct API</span></h1>
        <p class="hero-sub">GCP credentials, game list sync, launch ve wallet callback endpointleri buradan yönetilir.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn--primary" type="submit" form="bgamingSettingsForm">Ayarları Kaydet</button>
    </div>
</section>

<div class="bgaming-grid">
    <form id="bgamingSettingsForm" class="bgaming-card" method="post" action="<?= $text(AdminAuth::url('/bgaming/settings')) ?>">
        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">

        <div class="field">
            <label class="field-label" for="server_id">Server ID</label>
            <input id="server_id" class="input" type="text" name="server_id" value="<?= $text($configRow['server_id'] ?? '') ?>" placeholder="betmarko-int" autocomplete="off">
            <p class="bgaming-help">GCP_URL içinde <code>/direct/betmarko-int</code> varsa boş bırakıldığında otomatik alınır.</p>
        </div>

        <div class="field">
            <label class="field-label" for="casino_id">Casino ID</label>
            <input id="casino_id" class="input" type="text" name="casino_id" value="<?= $text($configRow['casino_id'] ?? '') ?>" placeholder="betmarko-int" autocomplete="off">
            <p class="bgaming-help">Verilen değer: <code>betmarko-int</code>. Boş bırakılırsa Server ID kullanılır.</p>
        </div>

        <div class="field">
            <label class="field-label" for="wallet_secret">AUTH_TOKEN / Request Sign Secret</label>
            <input id="wallet_secret" class="input bgaming-secret" type="password" name="wallet_secret" value="" placeholder="<?= trim((string) ($configRow['wallet_secret'] ?? '')) !== '' ? 'Mevcut AUTH_TOKEN korunacak' : 'BGaming AUTH_TOKEN' ?>" autocomplete="new-password">
            <p class="bgaming-help">Bu token hem GCP istek imzası hem wallet callback imza doğrulaması için kullanılır.</p>
        </div>

        <div class="field">
            <label class="field-label" for="api_base_url">GCP_URL</label>
            <input id="api_base_url" class="input" type="url" name="api_base_url" value="<?= $text($configRow['api_base_url'] ?? (getenv('BGAMING_API_BASE_URL') ?: 'https://int.bgaming-system.com')) ?>" placeholder="<?= $text(getenv('BGAMING_API_BASE_URL') ?: 'https://int.bgaming-system.com/direct/betmarko-int') ?>">
            <p class="bgaming-help">Tam URL yazılabilir; sistem env'deki <code>BGAMING_API_BASE_URL</code> ve server id değerlerini otomatik ayrıştırır.</p>
        </div>

        <div class="field">
            <label class="field-label" for="wallet_url">WALLET_URL</label>
            <input id="wallet_url" class="input" type="url" name="wallet_url" value="<?= $text($configRow['wallet_url'] ?? '') ?>">
            <p class="bgaming-help">BGaming ekibine backend wallet URL verilecek. Örn: <code><?= $text((string) (defined('BACKEND_URL') ? BACKEND_URL : (getenv('BACKEND_URL') ?: getenv('BACKEND_FALLBACK_URL') ?: ''))) ?>/api/v2/bgaming-wallet</code></p>
        </div>

        <div class="field">
            <label class="field-label" for="return_url">Return URL</label>
            <input id="return_url" class="input" type="url" name="return_url" value="<?= $text($configRow['return_url'] ?? '') ?>">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
            <div class="field">
                <label class="field-label" for="currency">Para Birimi</label>
                <select id="currency" class="input" name="currency">
                    <?php foreach ($currencyOptions as $currencyCode => $currencyLabel): ?>
                        <option value="<?= $text($currencyCode) ?>" <?= $currencyValue === $currencyCode ? 'selected' : '' ?>>
                            <?= $text($currencyLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="bgaming-help">Dokümandaki desteklenen para birimleri: USD, EUR, JPY, USDT, ETH, XRP, LTC, DOG, BTC, BCH.</p>
            </div>
            <div class="field">
                <label class="field-label" for="locale">Locale</label>
                <select id="locale" class="input" name="locale">
                    <?php foreach ($localeOptions as $localeCode => $localeLabel): ?>
                        <option value="<?= $text($localeCode) ?>" <?= $localeValue === $localeCode ? 'selected' : '' ?>>
                            <?= $text($localeLabel) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="bgaming-help">Dokümandaki desteklenen diller eksiksiz listelenir.</p>
            </div>
            <div class="field">
                <label class="field-label" for="country">Country</label>
                <input id="country" class="input" type="text" name="country" value="<?= $text($configRow['country'] ?? 'TR') ?>" maxlength="2">
            </div>
        </div>

        <div class="field">
            <label class="field-label">Durum</label>
            <label class="switch" style="margin-top:10px">
                <input type="checkbox" name="is_active" value="1" <?= !empty($configRow['is_active']) ? 'checked' : '' ?>>
                <span class="track"></span>
                BGaming aktif
            </label>
        </div>

        <div class="field">
            <label class="field-label">Freespins / Promo Akışları</label>
            <label class="switch" style="margin-top:10px">
                <input type="checkbox" name="freespins_enabled" value="1" <?= (int) ($configRow['freespins_enabled'] ?? 1) === 1 ? 'checked' : '' ?>>
                <span class="track"></span>
                Freespins finish callback aktif
            </label>
            <label class="switch" style="margin-top:10px">
                <input type="checkbox" name="promo_enabled" value="1" <?= (int) ($configRow['promo_enabled'] ?? 1) === 1 ? 'checked' : '' ?>>
                <span class="track"></span>
                Promo bet / win / rollback callback aktif
            </label>
            <label class="switch" style="margin-top:10px">
                <input type="checkbox" name="token_rotation_enabled" value="1" <?= (int) ($configRow['token_rotation_enabled'] ?? 1) === 1 ? 'checked' : '' ?>>
                <span class="track"></span>
                AUTH_TOKEN rotation endpoint aktif
            </label>
            <p class="bgaming-help">Dokümana göre bu endpointler opsiyonel olsa da BGaming promo ve freespin kampanyaları açıldığında imzalı callback olarak kullanılacaktır.</p>
        </div>

        <div class="form-actions admin-action-spaced-lg">
            <span class="badge dot <?= !empty($configRow['is_active']) ? 'success' : 'danger' ?>"><?= !empty($configRow['is_active']) ? 'Aktif' : 'Pasif' ?></span>
            <span class="spacer"></span>
            <button class="btn btn--primary" type="submit">Ayarları Kaydet</button>
        </div>
    </form>

    <aside class="bgaming-card">
        <h2 style="margin:0 0 12px;font-size:18px;color:var(--t-base)">Katalog Yönetimi</h2>
        <div class="bgaming-stat"><span>Oyun</span><strong><?= (int) ($gamesCount ?? 0) ?></strong></div>
        <div class="bgaming-stat"><span>İşlem</span><strong><?= (int) ($transactionsCount ?? 0) ?></strong></div>
        <div class="bgaming-stat" style="flex-direction:column;align-items:flex-start;gap:4px"><span>Callback URL (Not)</span><code class="bgaming-secret" style="word-break:break-all"><?= $text($bgamingCallbackUrl) ?></code></div>
        <div class="bgaming-stat"><span>Son Sync</span><strong><?= $text($configRow['synced_at'] ?? '-') ?></strong></div>
        <div class="bgaming-stat"><span>Real Launch</span><strong>/sessions</strong></div>
        <div class="bgaming-stat"><span>Demo Launch</span><strong>/sessions/demo</strong></div>
        <div class="bgaming-stat"><span>Freespins</span><strong>/freespins/finish</strong></div>
        <div class="bgaming-stat"><span>Promo</span><strong>/promo/bet · /promo/win</strong></div>
        <div class="bgaming-stat"><span>Promo Rollback</span><strong>/promo/rollback</strong></div>
        <p class="bgaming-help">Frontend BGaming kartlarında Oyna ve Demo butonları görünür. Demo modu wallet callback göndermez. Promo ve freespin callbackleri imza doğrulaması ve idempotency ile işlenir.</p>
        <div class="bgaming-actions admin-action-spaced">
            <form method="post" action="<?= $text(AdminAuth::url('/bgaming/sync-games')) ?>">
                <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                <button class="btn btn--primary admin-full-action" type="submit">Oyun Sync</button>
            </form>
            <a class="btn btn--secondary admin-full-action" href="<?= $text(AdminAuth::url('/module?key=bgaming-games')) ?>">Oyunları Aç</a>
            <a class="btn btn--secondary admin-full-action" href="<?= $text(AdminAuth::url('/module?key=bgaming-transactions')) ?>">İşlemleri Aç</a>
        </div>
    </aside>
</div>
