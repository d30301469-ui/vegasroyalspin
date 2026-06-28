<?php

$methods = is_array($methods ?? null) ? $methods : [];
$flash = trim((string) ($flash ?? ''));
$text = static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
$money = static fn (mixed $value): string => number_format((float) $value, 2, '.', '');
$currencySymbol = static fn (mixed $value): string => strtoupper(trim((string) $value)) === 'TRY' ? '₺' : (string) $value;
?>
<style>
    .payment-methods-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(310px, 1fr));
        gap: 16px;
    }
    .payment-method-card {
        border: 1px solid var(--border);
        border-radius: 18px;
        background: var(--bg-card);
        padding: 18px;
        box-shadow: var(--shadow-card);
    }
    .payment-method-head {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 16px;
    }
    .payment-method-logo {
        width: 54px;
        height: 54px;
        border: 1px solid var(--border);
        border-radius: 16px;
        background: var(--bg-muted);
        object-fit: contain;
        padding: 8px;
        flex: 0 0 auto;
    }
    .payment-method-title {
        min-width: 0;
    }
    .payment-method-title h2 {
        margin: 0;
        font-size: 16px;
        color: var(--t-base);
    }
    .payment-method-title p {
        margin: 4px 0 0;
        color: var(--t-muted);
        font-size: 12px;
    }
    .payment-method-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    .payment-method-switches {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-top: 14px;
    }
    .payment-method-switches .switch {
        min-width: 124px;
    }
    @media (max-width: 720px) {
        .payment-method-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<section class="hero">
    <div class="hero-text">
        <span class="eyebrow">Finans · MegaPayz</span>
        <h1 class="hero-title">Ödeme <span class="accent">Metotları</span></h1>
        <p class="hero-sub">Logo, yatırım/çekim limitleri ve aktiflik durumlarını tek ekrandan güncelleyin.</p>
    </div>
    <div class="hero-actions">
        <button class="btn btn--primary" type="submit" form="paymentMethodsForm">Değişiklikleri Kaydet</button>
    </div>
</section>

<form id="paymentMethodsForm" method="post" action="<?= htmlspecialchars(AdminAuth::url('/megapayz/methods'), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="_token" value="<?= htmlspecialchars(AdminAuth::csrfToken(), ENT_QUOTES, 'UTF-8') ?>">

    <div class="payment-methods-grid">
        <?php foreach ($methods as $method): ?>
            <?php
            $id = (int) ($method['id'] ?? 0);
            $key = (string) ($method['method_key'] ?? '');
            $logo = (string) ($method['logo_url'] ?? '');
            ?>
            <section class="payment-method-card">
                <div class="payment-method-head">
                    <img class="payment-method-logo" src="<?= $text($logo !== '' ? $logo : '/assets/images/logo-placeholder.png') ?>" alt="" loading="lazy" onerror="this.style.visibility='hidden'">
                    <div class="payment-method-title">
                        <h2><?= $text($method['name'] ?? $key) ?></h2>
                        <p><?= $text($key) ?> · <?= $text($method['type'] ?? '') ?> · <?= $text($currencySymbol($method['currency'] ?? 'TRY')) ?></p>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="method_logo_<?= $id ?>">Logo URL</label>
                    <input id="method_logo_<?= $id ?>" class="input" type="text" name="methods[<?= $id ?>][logo_url]" value="<?= $text($logo) ?>" placeholder="/assets/images/footer/payments/logo.png veya https://...">
                </div>

                <div class="payment-method-row">
                    <div class="field">
                        <label class="field-label" for="method_min_<?= $id ?>">Minimum Limit (₺)</label>
                        <input id="method_min_<?= $id ?>" class="input" type="number" step="0.01" min="0" name="methods[<?= $id ?>][min_amount]" value="<?= $text($money($method['min_amount'] ?? 0)) ?>">
                    </div>
                    <div class="field">
                        <label class="field-label" for="method_max_<?= $id ?>">Maksimum Limit (₺)</label>
                        <input id="method_max_<?= $id ?>" class="input" type="number" step="0.01" min="0" name="methods[<?= $id ?>][max_amount]" value="<?= $text($money($method['max_amount'] ?? 0)) ?>">
                    </div>
                </div>

                <div class="payment-method-switches">
                    <label class="switch">
                        <input type="checkbox" name="methods[<?= $id ?>][deposit_enabled]" value="1" <?= !empty($method['deposit_enabled']) ? 'checked' : '' ?>>
                        <span class="track"></span>
                        Yatırım aktif
                    </label>
                    <label class="switch">
                        <input type="checkbox" name="methods[<?= $id ?>][withdraw_enabled]" value="1" <?= !empty($method['withdraw_enabled']) ? 'checked' : '' ?>>
                        <span class="track"></span>
                        Çekim aktif
                    </label>
                    <label class="switch">
                        <input type="checkbox" name="methods[<?= $id ?>][is_active]" value="1" <?= !empty($method['is_active']) ? 'checked' : '' ?>>
                        <span class="track"></span>
                        Listede göster
                    </label>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <div class="form-actions admin-action-spaced-lg">
        <span class="badge dot info"><?= count($methods) ?> method</span>
        <span class="spacer"></span>
        <button class="btn btn--primary" type="submit">Değişiklikleri Kaydet</button>
    </div>
</form>
