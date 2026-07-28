<?php

$configRow = is_array($configRow ?? null) ? $configRow : [];
$flash = trim((string) ($flash ?? ''));
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');

$callbackUrl = trim((string) ($callbackUrl ?? ''));
$isActive = !empty($configRow['is_active']) && $configRow['is_active'] !== '0';
?>
<style>
    .gs-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 18px; align-items: start; }
    .gs-card { border: 1px solid var(--border); border-radius: 18px; background: var(--bg-card); padding: 18px; box-shadow: var(--shadow-card); }
    .gs-actions { display: flex; flex-direction: column; gap: 10px; }
    .gs-stat { display: flex; justify-content: space-between; gap: 14px; padding: 12px 0; border-bottom: 1px solid var(--border); }
    .gs-stat:last-child { border-bottom: 0; }
    .gs-secret { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; letter-spacing: .02em; }
    .gs-help { color: var(--t-muted); font-size: 13px; line-height: 1.45; margin-top: 6px; }
    .gs-webhook-url { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; word-break: break-all; background: var(--bg); padding: 8px 10px; border-radius: 8px; border: 1px solid var(--border); }
    @media (max-width: 900px) { .gs-grid { grid-template-columns: 1fr; } }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Oyunlar · Gaming Soft</span>
        <h1 class="hero-title">Gaming Soft <span class="accent">(GSC+ v2.0.6)</span></h1>
        <p class="hero-sub">Seamless wallet callback, product/game sync ve launch-game entegrasyonu. Staging: VGY1 · IDR/IDR2/CNY/VND/VND2.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn--primary" type="submit" form="gamingSoftSettingsForm">Ayarları Kaydet</button>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert alert--info" style="margin-bottom:16px"><?= $text($flash) ?></div>
<?php endif; ?>

<div class="gs-grid">
    <form id="gamingSoftSettingsForm" class="gs-card" method="post"
          action="<?= $text(AdminAuth::url('/gamingsoft/settings')) ?>">
        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">

        <div class="field">
            <label class="field-label" for="operator_code">Operator Code</label>
            <input id="operator_code" class="input" type="text" name="operator_code"
                   value="<?= $text($configRow['operator_code'] ?? '') ?>" maxlength="32" autocomplete="off">
            <p class="gs-help">GSC+ Agency Code (staging: <code>VGY1</code> / VEGASROYALSPIN).</p>
        </div>

        <div class="field">
            <label class="field-label" for="api_base_url">Operator URL (API Base)</label>
            <input id="api_base_url" class="input" type="url" name="api_base_url"
                   value="<?= $text($configRow['api_base_url'] ?? '') ?>"
                   placeholder="https://staging.gsimw.com" autocomplete="off">
            <p class="gs-help">Staging: <code>https://staging.gsimw.com</code></p>
        </div>

        <div class="field">
            <label class="field-label" for="secret_key">Secret Key</label>
            <input id="secret_key" class="input gs-secret" type="password" name="secret_key" value=""
                   placeholder="<?= trim((string) ($configRow['secret_key'] ?? '')) !== '' ? 'Mevcut secret korunacak' : 'secret_key' ?>"
                   autocomplete="new-password">
        </div>

        <div class="field">
            <label class="field-label" for="currency">Currency</label>
            <input id="currency" class="input" type="text" name="currency"
                   value="<?= $text(strtoupper(trim((string) ($configRow['currency'] ?? 'IDR')))) ?>"
                   maxlength="16" autocomplete="off">
            <p class="gs-help">
                Staging açık: <code>IDR</code>, <code>IDR2</code> (1:1000), <code>CNY</code>, <code>VND</code>, <code>VND2</code> (1:1000).
                Varsayılan test currency: <code>IDR</code>.
            </p>
        </div>

        <div class="field">
            <label class="field-label" for="try_to_idr_rate">TRY → IDR Dönüşüm Kuru</label>
            <input id="try_to_idr_rate" class="input" type="number" name="try_to_idr_rate" min="1" step="0.01"
                   value="<?= $text($configRow['try_to_idr_rate'] ?? '500') ?>">
            <p class="gs-help">
                Site cüzdanı TRY, GSC+ oyun cüzdanı IDR olduğunda kullanılır.
                Örnek: 500 = 100 TRY ≈ 50.000 IDR. Dream Gaming minimum masa limiti genelde 32.000 IDR.
            </p>
        </div>

        <div class="field">
            <label class="field-label" for="language_code">Language Code</label>
            <input id="language_code" class="input" type="number" name="language_code" min="0" max="50"
                   value="<?= $text((int) ($configRow['language_code'] ?? 0)) ?>">
            <p class="gs-help">0=English (varsayılan). GSC+ Language Code tablosuna bakın.</p>
        </div>

        <div class="field">
            <label class="field-label" for="channel_code">Channel Code</label>
            <input id="channel_code" class="input" type="text" name="channel_code"
                   value="<?= $text($configRow['channel_code'] ?? 'gscp') ?>" maxlength="32" autocomplete="off">
        </div>

        <div class="field">
            <label class="field-label" for="site_endpoint">Site Endpoint (Callback Base Host)</label>
            <input id="site_endpoint" class="input" type="url" name="site_endpoint"
                   value="<?= $text($configRow['site_endpoint'] ?? '') ?>"
                   placeholder="https://admin.vegasroyalspin.com">
            <p class="gs-help">GSC+ paneline verilecek callback kökü. Tam URL: <code>/api/v2/gamingsoft-wallet</code></p>
        </div>

        <div class="field">
            <div style="display:flex;align-items:center;gap:10px;padding:12px 0;">
                <input id="is_active" type="checkbox" name="is_active" value="1"
                       <?= $isActive ? 'checked' : '' ?> style="width:18px;height:18px;cursor:pointer;">
                <label for="is_active" style="cursor:pointer;font-weight:600">Gaming Soft entegrasyonunu aktif et</label>
            </div>
        </div>
    </form>

    <div class="gs-actions">
        <div class="gs-card">
            <div style="font-weight:700;margin-bottom:12px">İstatistikler</div>
            <div class="gs-stat"><span>Ürün</span><strong><?= number_format((int) ($productsCount ?? 0)) ?></strong></div>
            <div class="gs-stat"><span>Toplam Oyun</span><strong><?= number_format((int) ($gamesCount ?? 0)) ?></strong></div>
            <div class="gs-stat"><span>Aktif Oyun</span><strong><?= number_format((int) ($activeGamesCount ?? 0)) ?></strong></div>
            <div class="gs-stat"><span>Oturum</span><strong><?= number_format((int) ($sessionsCount ?? 0)) ?></strong></div>
            <div class="gs-stat"><span>İşlem</span><strong><?= number_format((int) ($transactionsCount ?? 0)) ?></strong></div>
            <div class="gs-stat"><span>Durum</span><strong><?= $isActive ? '<span style="color:var(--green)">Aktif</span>' : '<span style="color:var(--t-muted)">Pasif</span>' ?></strong></div>
        </div>

        <div class="gs-card">
            <div style="font-weight:700;margin-bottom:10px">Callback URL (GSC+ paneline verin)</div>
            <div class="gs-webhook-url"><?= $text($callbackUrl) ?></div>
            <p class="gs-help" style="margin-top:10px">
                balance → <code>.../v1/api/seamless/balance</code><br>
                withdraw → <code>.../v1/api/seamless/withdraw</code><br>
                deposit → <code>.../v1/api/seamless/deposit</code><br>
                pushbetdata → <code>.../v1/api/seamless/pushbetdata</code>
            </p>
        </div>

        <form class="gs-card" method="post" action="<?= $text(AdminAuth::url('/gamingsoft/sync-products')) ?>">
            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
            <div style="font-weight:700;margin-bottom:8px">Product Sync</div>
            <p class="gs-help" style="margin-bottom:12px">available-products API ile ürün listesini çeker.</p>
            <button class="btn btn--ghost" type="submit">Product Sync</button>
        </form>

        <form class="gs-card" method="post" action="<?= $text(AdminAuth::url('/gamingsoft/sync-games')) ?>">
            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
            <div style="font-weight:700;margin-bottom:8px">Oyun Sync</div>
            <p class="gs-help" style="margin-bottom:12px">Aktif ürünler için provider-games ile oyun kataloğunu günceller.</p>
            <button class="btn btn--primary" type="submit">Oyun Sync</button>
        </form>

        <div class="gs-card">
            <div style="font-weight:700;margin-bottom:12px">Modüller</div>
            <?php
            $moduleLinks = [
                'gamingsoft-products' => ['label' => 'Ürünler', 'path' => '/module?key=gamingsoft-products'],
                'gamingsoft-games' => ['label' => 'Oyunlar', 'path' => '/module?key=gamingsoft-games'],
                'gamingsoft-sessions' => ['label' => 'Oturumlar', 'path' => '/module?key=gamingsoft-sessions'],
                'gamingsoft-transactions' => ['label' => 'İşlemler', 'path' => '/module?key=gamingsoft-transactions'],
                'gamingsoft-wallet-logs' => ['label' => 'Wallet Logları', 'path' => '/module?key=gamingsoft-wallet-logs'],
            ];
            foreach ($moduleLinks as $mInfo):
            ?>
            <div class="gs-stat">
                <span><?= $text($mInfo['label']) ?></span>
                <a href="<?= $text(AdminAuth::url($mInfo['path'])) ?>" class="btn btn--xs">Aç</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
