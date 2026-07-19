<?php
require_once __DIR__ . '/_payment-page-init.php';
$profileActiveTab = (!empty($_GET['bilgi']) && (string) $_GET['bilgi'] === '1') ? 'withdraw-bilgi' : 'withdraw';
$profile_modal = !empty($_GET['modal']) && $_GET['modal'] === '1';
?>

<?php if (!$profile_modal): ?>
<?php require_once __DIR__ . '/../../views/layouts/head_full.php'; ?>
<?php endif; ?>
<script>window.__PROFILE_PAYMENT_LIMITS__ = <?php echo json_encode($paymentLimits); ?>;</script>
<script>window.__PROFILE_PAYMENT_PAGE__ = 'withdraw';</script>
<?php if (!$profile_modal): ?>
<?php include __DIR__ . '/../../views/partials/header.php'; ?>
<div class="centerWrap porfileWrap">
<?php endif; ?>
    <?php include __DIR__ . '/../../views/partials/profile-sidebar.php'; ?>
    <?php
    $dw_site_raw = (is_array($ayar ?? null) && !empty($ayar['site_adi'])) ? $ayar['site_adi'] : 'MaltaBet';
    $dw_site_brand = htmlspecialchars($dw_site_raw, ENT_QUOTES, 'UTF-8');
    $dw_site_brand_upper = htmlspecialchars(function_exists('mb_strtoupper') ? mb_strtoupper($dw_site_raw, 'UTF-8') : strtoupper($dw_site_raw), ENT_QUOTES, 'UTF-8');
    ?>
<script>window.__DEPOSIT_PANEL_SITE_BRAND__ = <?php echo json_encode($dw_site_raw, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;</script>

    <main id="profilePlayerMain" name="profilePlayerMain" class="profile-main-content">
        <?php
        $profile_content_title = 'PARA ÇEKİM';
        $profile_content_page_class = 'personal-details-page--deposit-withdraw personal-details-page--withdraw-only';
        $profile_close_href_full = '/profile/details';
        include __DIR__ . '/../../views/partials/profile-content-shell-open.php';
        ?>

<div class="vega-app vega-app--in-profile-shell">
    <div id="withdrawSection" class="withdraw-section">
        <div class="withdraw-tabs deposit-tabs" role="tablist" aria-label="Çekim kategorileri">
            <button type="button" class="withdraw-tab deposit-tab active" role="tab" aria-selected="true" data-wcategory="all"><i class="fa-solid fa-grip" aria-hidden="true"></i> TÜMÜ</button>
            <button type="button" class="withdraw-tab deposit-tab" role="tab" aria-selected="false" data-wcategory="crypto"><i class="fa-brands fa-bitcoin" aria-hidden="true"></i> KRİPTO</button>
            <button type="button" class="withdraw-tab deposit-tab" role="tab" aria-selected="false" data-wcategory="bank"><i class="fa-solid fa-right-left" aria-hidden="true"></i> BANKA TRANSFERİ</button>
        </div>
        <div class="withdraw-method-select-wrap form-group">
            <div class="deposit-method-grid-label" id="withdrawMethodGridLabel">Ödeme yöntemi</div>
            <div id="withdrawGrid" class="deposit-grid dw-methods-grid withdraw-methods-grid" role="listbox" aria-labelledby="withdrawMethodGridLabel">
                <p class="dw-methods-empty" role="status">Ödeme yöntemleri API üzerinden yükleniyor...</p>
            </div>
        </div>
        <div class="withdraw-inline-sheet" id="withdrawInlineFlow" aria-label="Çekim formu">
            <div class="panel-info-table withdraw-inline-summary vega-withdraw-summary">
                <div class="panel-info-cell"><strong>Ödeme Yöntemi</strong><span id="wInlineMethod">API üzerinden seçilecek</span></div>
                <div class="panel-info-cell"><strong>Ücret</strong><span>Ücretsiz</span></div>
                <div class="panel-info-cell"><strong>İşlem Süresi</strong><span id="wInlineProcTime">Anlık</span></div>
                <div class="panel-info-cell"><strong>Min.</strong><span id="wInlineMin">—</span></div>
                <div class="panel-info-cell"><strong>Maks.</strong><span id="wInlineMax">—</span></div>
            </div>
            <div class="vega-withdraw-balance" id="withdrawInlineBalance">
                <div class="vega-withdraw-balance-head">Çekilebilir Tutar</div>
                <div class="vega-withdraw-balance-row">
                    <span class="vega-withdraw-balance-label">Bakiye</span>
                    <span class="vega-withdraw-balance-value" id="wdrBalanceInline">0,00 ₺</span>
                </div>
                <div class="vega-withdraw-balance-row">
                    <span class="vega-withdraw-balance-label">Oynanmamış Tutar Yüzdesi</span>
                    <span class="vega-withdraw-balance-value" id="wdrUnplayedPctInline">0%</span>
                </div>
            </div>
            <div class="panel-instruction vega-withdraw-welcome withdraw-inline-msg"><?php echo htmlspecialchars($dw_site_brand, ENT_QUOTES, 'UTF-8'); ?> Ailesi olarak kazancınız adına sizleri tebrik eder ve bol şanslar dileriz. Para çekmek için lütfen aşağıdaki tüm gerekli alanları doldurun.</div>
            <div id="withdrawInlineFields" class="withdraw-inline-fields"></div>
            <div class="panel-actions withdraw-inline-actions">
                <button type="button" class="vega-withdraw-submit withdraw-btn" id="withdrawInlineSubmit">ÇEKİM YAP</button>
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
            <p>Para çekme talebiniz başarıyla alındı ve işleme konuldu.</p>
            <p>En kısa sürede bakiyeniz hesabınıza aktarılacaktır.</p>
        </div>
        <div class="popup-footer">
            <button class="btn btn-primary" onclick="closeSuccessWithdrawalPopup()">Tamam</button>
        </div>
    </div>
</div>

<!-- BİLGİ: sadece çekim yöntemleri, ortak kart şablonu -->
<div id="bilgiModal" class="profile-bilgi-panel profile-bilgi-panel--withdraw-only" hidden aria-hidden="true">
    <div class="bilgi-list-wrap">
        <div id="bilgiListWithdraw" class="bilgi-list bilgi-list-active">
            <div class="bilgi-table-scroll">
                <div class="bilgi-table" role="table" aria-label="Para çekme yöntemleri">
                    <p class="dw-methods-empty" data-bilgi-method-list="withdraw" role="status">Çekim bilgileri API üzerinden yükleniyor...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../views/partials/profile-content-shell-close.php'; ?>
    </main>
<?php if (!$profile_modal): ?>
</div>
<?php endif; ?>
<?php if (!$profile_modal): ?>
<?php include __DIR__ . '/../../views/partials/footer.php'; ?>
<?php endif; ?>
