<?php
require_once __DIR__ . '/_payment-page-init.php';
$is_bilgi_page = (!empty($_GET['bilgi']) && (string) $_GET['bilgi'] === '1');
$profileActiveTab = $is_bilgi_page ? 'withdraw-bilgi' : 'withdraw';
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

    <div id="profilePlayerMain" class="my-profile-info-block deposit-page<?= $is_bilgi_page ? ' is-bilgi-active' : '' ?>" data-profile-payment-page="withdraw">
<script>window.__PROFILE_PAYMENT_LIMITS__ = <?php echo json_encode($paymentLimits); ?>;</script>
<script>window.__PROFILE_PAYMENT_PAGE__ = 'withdraw';</script>
<script>window.__DEPOSIT_PANEL_SITE_BRAND__ = <?php echo json_encode($dw_site_raw, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;</script>
        <div class="overlay-header"><?= $is_bilgi_page ? 'BİLGİ' : 'PARA ÇEKİM' ?></div>

<div class="dep-w-info-bc" id="withdrawSection" data-scroll-lock-scrollable<?= $is_bilgi_page ? ' hidden' : '' ?>>
    <div tabindex="0" class="horizontal-sl-list horizontal-items-expanded" role="tablist" aria-label="Çekim kategorileri">
        <div class="horizontal-sl-wheel" style="transform: translateX(0px);">
            <div data-id="-1" title="TÜMÜ" data-badge="" class="horizontal-sl-item-bc accordion-button all active withdraw-tab" data-wcategory="all" role="tab" aria-selected="true"><i class="horizontal-sl-icon-bc bc-i-default-icon bc-i-all"></i><p class="horizontal-sl-title-bc">TÜMÜ</p></div>
            <div data-id="4" title="Kripto" data-badge="" class="horizontal-sl-item-bc accordion-button crypto withdraw-tab" data-wcategory="crypto" role="tab" aria-selected="false"><i class="horizontal-sl-icon-bc bc-i-default-icon bc-i-crypto"></i><p class="horizontal-sl-title-bc">Kripto</p></div>
            <div data-id="5" title="Banka transferi" data-badge="" class="horizontal-sl-item-bc accordion-button transfer withdraw-tab" data-wcategory="bank" role="tab" aria-selected="false"><i class="horizontal-sl-icon-bc bc-i-default-icon bc-i-transfer"></i><p class="horizontal-sl-title-bc">Banka transferi</p></div>
        </div>
    </div>

    <div class="m-block-nav-items-bc" id="withdrawGrid" role="listbox" aria-label="Ödeme yöntemi">
        <p class="dw-methods-empty" role="status">Ödeme yöntemleri API üzerinden yükleniyor...</p>
    </div>

    <div class="payment-details-scrollable-container" data-scroll-lock-scrollable>
        <div class="payment-info-bc" tabindex="-1">
            <div class="payment-info-content">
                <div class="description-c-row-bc withdraw">
                    <div class="description-c-row-column-bc texts">
                        <div class="description-c-row-c-title-bc">
                            <div class="description-c-r-c-t-column-bc"><span class="description-title ellipsis" title="ödeme yöntemi">ödeme yöntemi</span><span class="description-value ellipsis" id="wInlineMethod" title="">—</span></div>
                            <div class="description-c-r-c-t-column-bc"><span class="description-title ellipsis" title="Ücret">Ücret</span><span class="description-value ellipsis" title="Ücretsiz">Ücretsiz</span></div>
                            <div class="description-c-r-c-t-column-bc"><span class="description-title ellipsis" title="işlem süresi">işlem süresi</span><span class="description-value ellipsis" id="wInlineProcTime" title="Anlık">Anlık</span></div>
                            <div class="description-c-r-c-t-column-bc"><span class="description-title ellipsis" title="Min.">Min.</span><span class="description-value ellipsis" id="wInlineMin" title="">—</span></div>
                            <div class="description-c-r-c-t-column-bc"><span class="description-title ellipsis" title="Maks.">Maks.</span><span class="description-value ellipsis" id="wInlineMax" title="">—</span></div>
                        </div>
                    </div>
                </div>
                <div class="expandableContentWrapper">
                    <div class="expandableContentData payment-content not-expandable" data-scroll-lock-scrollable>
                        <div class="container">
                            <p><?php echo htmlspecialchars($dw_site_brand, ENT_QUOTES, 'UTF-8'); ?> Ailesi olarak kazancınız adına sizleri tebrik eder ve bol şanslar dileriz. Para çekmek için lütfen aşağıdaki tüm gerekli alanları doldurun.</p>
                            <p class="withdraw-balance-line">Çekilebilir: <b id="wdrBalanceInline">0,00 ₺</b> · Oynanmamış: <b id="wdrUnplayedPctInline">0%</b></p>
                        </div>
                    </div>
                </div>
                <div class="withdraw-form-l-bc" id="withdrawInlineFlow" aria-label="Para çekme formu">
                    <form id="withdrawScreenArea" onsubmit="event.preventDefault(); var btn=document.getElementById('withdrawInlineSubmit'); if(btn) btn.click(); return false;">
                        <div id="withdrawInlineFields"></div>
                        <div class="u-i-p-c-footer-bc">
                            <button class="btn a-color withdraw" type="button" id="withdrawInlineSubmit" title="ÇEKİM YAP"><span>ÇEKİM YAP</span></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="successWithdrawalPopup" class="popup-container">
    <div class="popup-content">
        <div class="popup-header">
            <span class="close-btn" onclick="closeSuccessWithdrawalPopup()">&times;</span>
        </div>
        <div class="popup-body">
            <h2>İşlem Başarılı!</h2>
            <p>Çekim talebiniz başarılı bir şekilde oluşturulmuştur.</p>
            <p>En kısa sürede bakiyeniz hesabınıza aktarılacaktır.</p>
        </div>
        <div class="popup-footer">
            <button class="btn btn-primary" onclick="closeSuccessWithdrawalPopup()">Tamam</button>
        </div>
    </div>
</div>

<?php
$bilgi_open = $is_bilgi_page;
$bilgi_prefer_withdraw = true;
include __DIR__ . '/../../views/partials/profile-bilgi-panel.php';
?>

    </div><!-- /#profilePlayerMain -->
<?php if (!$profile_modal): ?>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../../views/partials/footer.php'; ?>
<?php endif; ?>
