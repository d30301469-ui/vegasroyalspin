<?php

$configRow = is_array($configRow ?? null) ? $configRow : [];
$flash = trim((string) ($flash ?? ''));
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$currencyValue = strtoupper(trim((string) ($configRow['currency'] ?? 'TRY')));
$callbackUrl = (string) ($callbackUrl ?? '');
$callbackAlias = (string) ($callbackAlias ?? '');
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

<div class="gsc-grid">
    <form id="gscSettingsForm" class="gsc-card" method="post" action="<?= $text(AdminAuth::url('/gamingsoft/settings')) ?>">
        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">

        <div class="field">
            <label class="field-label" for="operator_code">Operator Code</label>
            <input id="operator_code" class="input" type="text" name="operator_code" value="<?= $text($configRow['operator_code'] ?? '') ?>" maxlength="32" autocomplete="off" required>
            <p class="gsc-help">GSC+ tarafından verilen agent kodu.</p>
        </div>

        <div class="field">
            <label class="field-label" for="secret_key">Secret Key</label>
            <input id="secret_key" class="input gsc-secret" type="password" name="secret_key" value="" placeholder="<?= trim((string) ($configRow['secret_key'] ?? '')) !== '' ? 'Mevcut secret korunacak' : 'GSC+ secret_key' ?>" autocomplete="new-password">
            <p class="gsc-help">MD5 imza için kullanılır. Boş bırakılırsa mevcut değer korunur.</p>
        </div>

        <div class="field">
            <label class="field-label" for="operator_url">Operator URL</label>
            <input id="operator_url" class="input" type="url" name="operator_url" value="<?= $text($configRow['operator_url'] ?? 'https://staging.gsimw.com') ?>" placeholder="https://staging.gsimw.com">
            <p class="gsc-help">Staging: <code>https://staging.gsimw.com</code> · Aurora: <code>https://staging-idr.pglsucs.com</code></p>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
            <div class="field">
                <label class="field-label" for="currency">Currency</label>
                <input id="currency" class="input" type="text" name="currency" value="<?= $text($currencyValue) ?>" maxlength="16">
            </div>
            <div class="field">
                <label class="field-label" for="language_code">Language Code</label>
                <input id="language_code" class="input" type="number" name="language_code" value="<?= $text((string) (int) ($configRow['language_code'] ?? 0)) ?>" min="0" max="50">
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

        <?php $agentWallet = is_array($agentWallet ?? null) ? $agentWallet : null; ?>
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
