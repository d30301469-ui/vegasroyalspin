<?php

$configRow = is_array($configRow ?? null) ? $configRow : [];
$flash = trim((string) ($flash ?? ''));
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$currencyValue = strtoupper(trim((string) ($configRow['currency'] ?? '')));
if ($currencyValue === '') {
    $currencyValue = GscPlusService::DEFAULT_CURRENCY;
}
$callbackUrl = rtrim((string) ($callbackUrl ?? ''), "/ \t");
$callbackAlias = rtrim((string) ($callbackAlias ?? ''), "/ \t");
$callbackLegacyUrl = rtrim((string) ($callbackLegacyUrl ?? ''), "/ \t");

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
$primaryCurrencyBalance = null;
foreach (($agentWallet['currencies'] ?? []) as $walletRow) {
    if (strtoupper(trim((string) ($walletRow['currency'] ?? ''))) === $currencyValue) {
        $primaryCurrencyBalance = (float) ($walletRow['current_balance'] ?? 0);
        break;
    }
}
$primaryCurrencyEmpty = $agentWallet !== null
    && $primaryCurrencyBalance !== null
    && $primaryCurrencyBalance <= 0
    && !$walletEmpty;
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
        <span class="eyebrow">Oyunlar · GSC+</span>
        <h1 class="hero-title">GSC+ <span class="accent">Ayarları</span></h1>
    </div>
    <div class="hero-actions">
        <button class="btn btn--primary" type="submit" form="gscSettingsForm">Ayarları Kaydet</button>
    </div>
</section>

<?php if ($flash !== ''): ?>
    <div class="alert" style="margin-bottom:16px"><?= $text($flash) ?></div>
<?php endif; ?>

<?php if ($walletEmpty): ?>
    <div class="gsc-banner gsc-banner--danger">
        <strong>Agent wallet boş:</strong> Tüm para birimlerinde bakiye 0.
    </div>
<?php elseif ($primaryCurrencyEmpty): ?>
    <div class="gsc-banner gsc-banner--danger">
        <strong><?= $text($currencyValue) ?> agent bakiyesi 0.</strong>
    </div>
<?php endif; ?>

<div class="gsc-grid">
    <form id="gscSettingsForm" class="gsc-card" method="post" action="<?= $text(AdminAuth::url('/gsc-plus/settings')) ?>">
        <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">

        <div class="field">
            <label class="field-label" for="operator_code">Operator Code</label>
            <input id="operator_code" class="input" type="text" name="operator_code" value="<?= $text($configRow['operator_code'] ?? '') ?>" maxlength="32" autocomplete="off" required placeholder="VGY1">
        </div>

        <div class="field">
            <label class="field-label" for="secret_key">Secret Key</label>
            <input id="secret_key" class="input gsc-secret" type="password" name="secret_key" value="" placeholder="<?= trim((string) ($configRow['secret_key'] ?? '')) !== '' ? 'Mevcut secret korunacak' : 'GSC+ secret_key' ?>" autocomplete="new-password">
        </div>

        <div class="field">
            <label class="field-label" for="operator_url">Operator URL</label>
            <input id="operator_url" class="input" type="url" name="operator_url" value="<?= $text($configRow['operator_url'] ?? 'https://staging.gsimw.com') ?>" placeholder="https://staging.gsimw.com">
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
            <div class="field">
                <label class="field-label" for="currency">Primary Currency</label>
                <select id="currency" class="input" name="currency">
                    <?php foreach ($currencyChoices as $choice): ?>
                        <option value="<?= $text($choice) ?>" <?= $choice === $currencyValue ? 'selected' : '' ?>><?= $text($choice) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($currencyMismatch): ?>
                    <p class="gsc-help"><strong>Uyarı:</strong> <?= $text($currencyValue) ?> agent cüzdanında tanımlı değil.</p>
                <?php endif; ?>
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
        </div>

        <div class="field">
            <label class="field-label" for="callback_allowed_ips">Callback Allowed IPs</label>
            <textarea id="callback_allowed_ips" class="input" name="callback_allowed_ips" rows="2" placeholder="virgülle ayırın"><?= $text($configRow['callback_allowed_ips'] ?? '') ?></textarea>
        </div>

        <label class="field" style="display:flex;align-items:center;gap:10px;margin-top:8px">
            <input type="checkbox" name="is_active" value="1" <?= !empty($configRow['is_active']) ? 'checked' : '' ?>>
            <span>GSC+ entegrasyonunu aktif et</span>
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
            <form method="post" action="<?= $text(AdminAuth::url('/gsc-plus/sync-products')) ?>">
                <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                <button class="btn btn--ghost" type="submit" style="width:100%">Ürünleri Sync Et</button>
            </form>
            <form method="post" action="<?= $text(AdminAuth::url('/gsc-plus/sync-games')) ?>">
                <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
                <button class="btn btn--ghost" type="submit" style="width:100%">Oyunları Sync Et</button>
            </form>
            <button
                class="btn btn--primary"
                type="button"
                style="width:100%"
                data-admin-modal-inline="#modal-gsc-auto-deposit"
                data-admin-modal-title="GSC+ Auto Deposit"
            >Auto Deposit Oluştur</button>
        </div>

        <?php if ($callbackUrl !== ''): ?>
            <div style="margin-top:18px">
                <div class="field-label">Callback URL (GSC paneline yapıştırın)</div>
                <p class="gsc-code gsc-help" style="margin-top:6px" id="gsc-callback-url"><?= $text($callbackUrl) ?></p>
                <?php if ($callbackAlias !== ''): ?>
                    <p class="gsc-help" style="margin-top:8px;font-size:12px;opacity:.85">
                        Seamless path: <code><?= $text($callbackAlias) ?></code>
                    </p>
                <?php endif; ?>
                <p class="gsc-help" style="margin-top:8px;color:#b45309;font-size:12px;line-height:1.45">
                    GSC paneline bu URL’yi yapıştırın; sonunda boşluk / <code>%20</code>
                    bırakmayın. Apache yine de <code>gsc-plus-wallet%20%20%20</code>
                    isteklerini kabul eder (AH10411 bypass).
                </p>
                <button type="button" class="btn" style="margin-top:8px" data-copy-gsc-callback>URL’yi kopyala</button>
            </div>
            <script>
            (function () {
                var btn = document.querySelector('[data-copy-gsc-callback]');
                var el = document.getElementById('gsc-callback-url');
                if (!btn || !el) return;
                btn.addEventListener('click', function () {
                    var text = (el.textContent || '').trim();
                    if (!text || !navigator.clipboard) return;
                    navigator.clipboard.writeText(text).then(function () {
                        btn.textContent = 'Kopyalandı';
                        setTimeout(function () { btn.textContent = 'URL’yi kopyala'; }, 1500);
                    });
                });
            })();
            </script>
        <?php endif; ?>
    </aside>
</div>

<style>
.admin-modal.admin-modal--gsc-pay {
    width: min(1120px, 100%);
    max-height: min(94vh, 980px);
}
.admin-modal.admin-modal--gsc-pay .admin-modal-body {
    display: flex;
    flex-direction: column;
    min-height: 0;
    padding: 12px;
}
.gsc-ad-pay-wrap {
    display: none;
    flex: 1;
    min-height: 0;
    flex-direction: column;
    gap: 10px;
}
.gsc-ad-pay-wrap.is-open {
    display: flex;
}
.gsc-ad-pay-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
}
.gsc-ad-pay-frame {
    flex: 1;
    width: 100%;
    min-height: min(72vh, 720px);
    border: 1px solid var(--border);
    border-radius: 12px;
    background: #fff;
}
</style>

<div id="modal-gsc-auto-deposit" style="display:none">
    <div id="gscAutoDepositStepForm">
        <form id="gscAutoDepositForm" method="post" action="<?= $text(AdminAuth::url('/gsc-plus/auto-deposit')) ?>" style="display:grid;gap:10px">
            <input type="hidden" name="_token" value="<?= $text(AdminAuth::csrfToken()) ?>">
            <input type="hidden" name="ajax" value="1">
            <div class="field">
                <label class="field-label" for="gsc_ad_amount">Tutar (USDT)</label>
                <input id="gsc_ad_amount" class="input" type="number" name="amount" min="500" step="1" value="500" required>
            </div>
            <div class="field">
                <label class="field-label" for="gsc_ad_payment">Payment Currency</label>
                <input id="gsc_ad_payment" class="input" type="text" name="payment_currency" value="USDT" required>
            </div>
            <div class="field">
                <label class="field-label" for="gsc_ad_deposit">Deposit Currency</label>
                <input id="gsc_ad_deposit" class="input" type="text" name="deposit_currency" value="<?= $text($currencyValue) ?>" required>
            </div>
            <button class="btn btn--primary" type="submit" id="gscAutoDepositSubmit">Ödeme Sayfasını Aç</button>
        </form>
        <p class="gsc-help" id="gscAutoDepositError" style="display:none;margin-top:12px;color:#ff8a96"></p>
    </div>
    <div class="gsc-ad-pay-wrap" id="gscAutoDepositPay">
        <div class="gsc-ad-pay-toolbar">
            <p class="gsc-help" id="gscAutoDepositMeta" style="margin:0"></p>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <button class="btn btn--ghost" type="button" id="gscAutoDepositBack">Forma Dön</button>
                <a class="btn btn--ghost" id="gscAutoDepositOpen" href="#" target="_blank" rel="noopener noreferrer">Yeni sekmede</a>
                <button class="btn btn--ghost" type="button" id="gscAutoDepositCopy">URL Kopyala</button>
            </div>
        </div>
        <iframe
            id="gscAutoDepositFrame"
            class="gsc-ad-pay-frame"
            title="GSC+ Auto Deposit ödeme"
            referrerpolicy="no-referrer-when-downgrade"
            allow="payment *; clipboard-write *"
        ></iframe>
        <p class="gsc-help" id="gscAutoDepositUrl" style="display:none;margin:0;word-break:break-all"></p>
    </div>
</div>

<script>
(function () {
    function bindAutoDeposit(root) {
        var form = root.querySelector('#gscAutoDepositForm');
        if (!form || form.dataset.bound === '1') return;
        form.dataset.bound = '1';
        var stepForm = root.querySelector('#gscAutoDepositStepForm');
        var payWrap = root.querySelector('#gscAutoDepositPay');
        var submitBtn = root.querySelector('#gscAutoDepositSubmit');
        var errorBox = root.querySelector('#gscAutoDepositError');
        var urlEl = root.querySelector('#gscAutoDepositUrl');
        var openBtn = root.querySelector('#gscAutoDepositOpen');
        var copyBtn = root.querySelector('#gscAutoDepositCopy');
        var backBtn = root.querySelector('#gscAutoDepositBack');
        var metaEl = root.querySelector('#gscAutoDepositMeta');
        var frame = root.querySelector('#gscAutoDepositFrame');
        var modal = root.closest('.admin-modal');

        function showForm() {
            if (stepForm) stepForm.style.display = '';
            if (payWrap) payWrap.classList.remove('is-open');
            if (modal) modal.classList.remove('admin-modal--gsc-pay');
            if (frame) {
                try { frame.removeAttribute('src'); } catch (e) {}
            }
        }

        function showPay(url, data) {
            if (stepForm) stepForm.style.display = 'none';
            if (payWrap) payWrap.classList.add('is-open');
            if (modal) modal.classList.add('admin-modal--gsc-pay');
            if (urlEl) urlEl.textContent = url;
            if (openBtn) openBtn.href = url;
            if (metaEl) {
                metaEl.textContent = (data.amount || '') + ' ' + (data.payment_currency || 'USDT')
                    + ' → ' + (data.deposit_currency || '')
                    + ' · ' + (data.expires_in || 900) + ' sn geçerli';
            }
            if (frame) frame.src = url;
            var title = modal ? modal.querySelector('.admin-modal-head h2') : null;
            if (title) title.textContent = 'GSC+ Auto Deposit — Ödeme';
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (errorBox) {
                errorBox.style.display = 'none';
                errorBox.textContent = '';
            }
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Oluşturuluyor…';
            }
            var body = new FormData(form);
            fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body
            }).then(function (res) {
                return res.text().then(function (text) {
                    var json = null;
                    try { json = text ? JSON.parse(text) : null; } catch (e) { json = null; }
                    return { ok: res.ok, status: res.status, json: json, text: text };
                });
            }).then(function (x) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Ödeme Sayfasını Aç';
                }
                if (!x.json || !x.json.ok || !x.json.data || !x.json.data.url) {
                    var msg = (x.json && x.json.message) ? x.json.message : ('İstek başarısız (HTTP ' + x.status + ')');
                    if (errorBox) {
                        errorBox.style.display = 'block';
                        errorBox.textContent = msg;
                    }
                    return;
                }
                showPay(String(x.json.data.url), x.json.data);
            }).catch(function (err) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Ödeme Sayfasını Aç';
                }
                if (errorBox) {
                    errorBox.style.display = 'block';
                    errorBox.textContent = (err && err.message) ? err.message : 'Bağlantı hatası';
                }
            });
        });

        if (backBtn) {
            backBtn.addEventListener('click', function () {
                showForm();
                var title = modal ? modal.querySelector('.admin-modal-head h2') : null;
                if (title) title.textContent = 'GSC+ Auto Deposit';
            });
        }

        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                var url = urlEl ? String(urlEl.textContent || '').trim() : '';
                if (!url) return;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(function () {
                        copyBtn.textContent = 'Kopyalandı';
                        setTimeout(function () { copyBtn.textContent = 'URL Kopyala'; }, 1500);
                    }).catch(function () {});
                }
            });
        }
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target && event.target.closest
            ? event.target.closest('[data-admin-modal-inline="#modal-gsc-auto-deposit"]')
            : null;
        if (!trigger) return;
        setTimeout(function () {
            var body = document.querySelector('.admin-modal-body');
            if (body) bindAutoDeposit(body);
        }, 0);
    });
})();
</script>
