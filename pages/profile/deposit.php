<?php
require_once __DIR__ . '/_payment-page-init.php';
$is_bilgi_page = (!empty($_GET['bilgi']) && (string) $_GET['bilgi'] === '1');
$profileActiveTab = $is_bilgi_page ? 'deposit-bilgi' : 'deposit';
$profile_modal = !empty($_GET['modal']) && $_GET['modal'] === '1';
?>

<?php if (!$profile_modal): ?>
<?php require_once __DIR__ . '/../../views/layouts/head_full.php'; ?>
<?php endif; ?>
<?php if (!$profile_modal): ?>
<?php include __DIR__ . '/../../views/partials/header.php'; ?>
<div class="centerWrap porfileWrap popup-holder-bc windowed user-profile-container is-web">
  <div class="popup-middleware-bc" style="display:contents">
    <div class="popup-inner-bc u-i-p-c-body-bc" style="display:flex;height:min(900px,88vh)">
<?php endif; ?>
    <?php include __DIR__ . '/../../views/partials/profile-sidebar.php'; ?>
    <?php
    $dw_site_raw = (is_array($ayar ?? null) && !empty($ayar['site_adi'])) ? $ayar['site_adi'] : 'VegasRoyalSpin';
    $dw_site_brand = htmlspecialchars($dw_site_raw, ENT_QUOTES, 'UTF-8');
    ?>

    <div id="profilePlayerMain" class="my-profile-info-block deposit-page<?= $is_bilgi_page ? ' is-bilgi-active' : '' ?>" data-profile-payment-page="deposit">
<script>window.__PROFILE_PAYMENT_LIMITS__ = <?php echo json_encode($paymentLimits); ?>;</script>
<script>window.__PROFILE_PAYMENT_PAGE__ = 'deposit';</script>
<script>window.__DEPOSIT_PANEL_SITE_BRAND__ = <?php echo json_encode($dw_site_raw, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;</script>
        <div class="overlay-header"><?= $is_bilgi_page ? 'BİLGİ' : 'PARA YATIR' ?></div>

<div class="dep-w-info-bc" id="depositSection" data-scroll-lock-scrollable<?= $is_bilgi_page ? ' hidden' : '' ?>>
    <div tabindex="0" class="horizontal-sl-list horizontal-items-expanded" role="tablist" aria-label="Ödeme kategorileri">
        <div class="horizontal-sl-wheel" style="transform: translateX(0px);">
            <div data-id="-1" title="TÜMÜ" data-badge="" class="horizontal-sl-item-bc accordion-button all active deposit-tab" data-category="all" role="tab" aria-selected="true"><i class="horizontal-sl-icon-bc bc-i-default-icon bc-i-all"></i><p class="horizontal-sl-title-bc">TÜMÜ</p></div>
            <div data-id="1" title="KREDİ KARTI" data-badge="" class="horizontal-sl-item-bc accordion-button bank-card deposit-tab" data-category="creditcard" role="tab" aria-selected="false"><i class="horizontal-sl-icon-bc bc-i-default-icon bc-i-bank-card"></i><p class="horizontal-sl-title-bc">KREDİ KARTI</p></div>
            <div data-id="4" title="Kripto" data-badge="" class="horizontal-sl-item-bc accordion-button crypto deposit-tab" data-category="crypto" role="tab" aria-selected="false"><i class="horizontal-sl-icon-bc bc-i-default-icon bc-i-crypto"></i><p class="horizontal-sl-title-bc">Kripto</p></div>
            <div data-id="5" title="Banka transferi" data-badge="" class="horizontal-sl-item-bc accordion-button transfer deposit-tab" data-category="bank" role="tab" aria-selected="false"><i class="horizontal-sl-icon-bc bc-i-default-icon bc-i-transfer"></i><p class="horizontal-sl-title-bc">Banka transferi</p></div>
            <div data-id="7" title="QR" data-badge="" class="horizontal-sl-item-bc accordion-button qr deposit-tab" data-category="qr" role="tab" aria-selected="false"><i class="horizontal-sl-icon-bc bc-i-default-icon bc-i-qr"></i><p class="horizontal-sl-title-bc">QR</p></div>
        </div>
    </div>

    <div class="m-block-nav-items-bc" id="depositGrid" role="listbox" aria-label="Ödeme yöntemi">
        <p class="dw-methods-empty" role="status">Ödeme yöntemleri API üzerinden yükleniyor...</p>
    </div>

    <div class="payment-details-scrollable-container" data-scroll-lock-scrollable>
        <div class="payment-info-bc" tabindex="-1">
            <div class="payment-info-content">
                <div class="description-c-row-bc deposit">
                    <div class="description-c-row-column-bc texts">
                        <div class="description-c-row-c-title-bc">
                            <div class="description-c-r-c-t-column-bc"><span class="description-title ellipsis" title="ödeme yöntemi">ödeme yöntemi</span><span class="description-value ellipsis" id="dInlineMethod" title="">—</span></div>
                            <div class="description-c-r-c-t-column-bc"><span class="description-title ellipsis" title="Ücret">Ücret</span><span class="description-value ellipsis" title="Ücretsiz">Ücretsiz</span></div>
                            <div class="description-c-r-c-t-column-bc"><span class="description-title ellipsis" title="işlem süresi">işlem süresi</span><span class="description-value ellipsis" id="dInlineProcTime" title="Anlık">Anlık</span></div>
                            <div class="description-c-r-c-t-column-bc"><span class="description-title ellipsis" title="Min.">Min.</span><span class="description-value ellipsis" id="dInlineMin" title="">—</span></div>
                            <div class="description-c-r-c-t-column-bc"><span class="description-title ellipsis" title="Maks.">Maks.</span><span class="description-value ellipsis" id="dInlineMax" title="">—</span></div>
                        </div>
                    </div>
                </div>
                <div class="expandableContentWrapper">
                    <div class="expandableContentData payment-content not-expandable" data-scroll-lock-scrollable>
                        <div class="container">
                            <p><?php echo htmlspecialchars($dw_site_brand, ENT_QUOTES, 'UTF-8'); ?> Ailesine hoş geldiniz. İyi eğlenceler, bol şanslar dileriz. Para yatırmak için lütfen aşağıdaki tüm gerekli alanları doldurun. Minimum tutar altı yatırımlar "İADE EDİLMEZ" lütfen kurallara uygun yatırım yapınız.</p>
                        </div>
                    </div>
                </div>
                <div class="withdraw-form-l-bc" id="depositInlineQuickForm" aria-label="Para yatırma formu">
                    <form id="screenArea" onsubmit="event.preventDefault(); if (typeof processInlineVegaDeposit === 'function') processInlineVegaDeposit(); return false;">
                        <div class="u-i-p-control-item-holder-bc" id="depositCryptoTypeWrap">
                            <?php
                            $ms_id = 'depositCryptoMultiSelect';
                            $ms_input_id = 'inlineCryptoType';
                            $ms_name = 'crypto_type';
                            $ms_title = 'crypto_type';
                            $ms_selected = 'tron';
                            $ms_options = [
                                'tron' => 'tron',
                                'bsc' => 'bsc',
                                'eth' => 'eth',
                                'BTC' => 'BTC',
                                'LTC' => 'LTC',
                                'USDT_TRON' => 'USDT_TRON',
                            ];
                            include __DIR__ . '/../../views/partials/cm622-multi-select.php';
                            ?>
                        </div>
                        <div class="u-i-p-control-item-holder-bc">
                            <div class="form-control-bc default">
                                <label class="form-control-label-bc inputs">
                                    <input type="text" inputmode="decimal" class="form-control-input-bc" name="amount" id="inlineDepositAmount" step="0" value="" autocomplete="off">
                                    <i class="form-control-input-stroke-bc" aria-hidden="true"></i>
                                    <span class="form-control-title-bc ellipsis">Tutar</span>
                                </label>
                            </div>
                        </div>
                        <div class="u-i-p-c-footer-bc">
                            <button class="btn a-color deposit" type="submit" id="inlineDepositSubmitBtn" title="PARA YATIR"><span>PARA YATIR</span></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$bilgi_open = $is_bilgi_page;
$bilgi_prefer_withdraw = false;
include __DIR__ . '/../../views/partials/profile-bilgi-panel.php';
?>

    </div><!-- /#profilePlayerMain -->
<?php if (!$profile_modal): ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../../views/partials/footer.php'; ?>
<?php endif; ?>
