<?php
require_once __DIR__ . '/_payment-page-init.php';
$profileActiveTab = (!empty($_GET['bilgi']) && (string) $_GET['bilgi'] === '1') ? 'deposit-bilgi' : 'deposit-withdraw';
$profile_modal = !empty($_GET['modal']) && $_GET['modal'] === '1';
?>

<?php if (!$profile_modal): ?>
<?php require_once __DIR__ . '/../../views/layouts/head_full.php'; ?>
<?php endif; ?>
<script>window.__PROFILE_PAYMENT_LIMITS__ = <?php echo json_encode($paymentLimits); ?>;</script>
<script>window.__PROFILE_PAYMENT_PAGE__ = 'deposit';</script>
<?php if (!$profile_modal): ?>
<?php include __DIR__ . '/../../views/partials/header.php'; ?>
<div class="centerWrap porfileWrap">
<?php endif; ?>
    <?php include __DIR__ . '/../../views/partials/profile-sidebar.php'; ?>
    <?php
    $dw_site_raw = (is_array($ayar ?? null) && !empty($ayar['site_adi'])) ? $ayar['site_adi'] : 'VegasRoyalSpin';
    $dw_site_brand = htmlspecialchars($dw_site_raw, ENT_QUOTES, 'UTF-8');
    $dw_site_brand_upper = htmlspecialchars(function_exists('mb_strtoupper') ? mb_strtoupper($dw_site_raw, 'UTF-8') : strtoupper($dw_site_raw), ENT_QUOTES, 'UTF-8');
    ?>
<script>window.__DEPOSIT_PANEL_SITE_BRAND__ = <?php echo json_encode($dw_site_raw, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;</script>

    <main id="profilePlayerMain" name="profilePlayerMain" class="profile-main-content">
        <?php
        $profile_content_title = 'PARA YATIR';
        $profile_content_page_class = 'personal-details-page--deposit-withdraw';
        $profile_close_href_full = '/profile/details';
        include __DIR__ . '/../../views/partials/profile-content-shell-open.php';
        ?>

<div class="vega-app vega-app--in-profile-shell">
    <div id="depositSection" class="deposit-section">
        <div class="deposit-tabs" role="tablist" aria-label="Ödeme kategorileri">
            <button type="button" class="deposit-tab active" role="tab" aria-selected="true" data-category="all"><i class="fa-solid fa-table-cells" aria-hidden="true"></i> TÜMÜ</button>
            <button type="button" class="deposit-tab" role="tab" aria-selected="false" data-category="creditcard"><i class="fa-solid fa-credit-card" aria-hidden="true"></i> KREDİ KARTI</button>
            <button type="button" class="deposit-tab" role="tab" aria-selected="false" data-category="crypto"><i class="fa-brands fa-bitcoin" aria-hidden="true"></i> KRİPTO</button>
            <button type="button" class="deposit-tab" role="tab" aria-selected="false" data-category="bank"><i class="fa-solid fa-right-left" aria-hidden="true"></i> BANKA TRANSFERİ</button>
            <button type="button" class="deposit-tab" role="tab" aria-selected="false" data-category="qr"><i class="fa-solid fa-qrcode" aria-hidden="true"></i> QR</button>
        </div>
        <div class="deposit-promo-banner">
            <div class="deposit-promo-brand">
                <span class="deposit-promo-logo"><?php echo $dw_site_brand; ?></span>
                <div class="deposit-promo-copy">
                    <p class="deposit-promo-line">YATIRIMLARIN MİLYONLARA DÖNÜŞTÜĞÜ ADRES <?php echo $dw_site_brand_upper; ?>!</p>
                    <p class="deposit-promo-warn">LÜTFEN DİKKAT: ÜYELERİMİZDEN HİÇBİR ZAMAN İADE TALEBİMİZ YOKTUR.</p>
                </div>
            </div>
        </div>
        <div class="deposit-method-select-wrap form-group">
            <div class="deposit-method-grid-label" id="depositMethodGridLabel">Ödeme yöntemi</div>
            <div id="depositGrid" class="deposit-grid dw-methods-grid" role="listbox" aria-labelledby="depositMethodGridLabel">
                <p class="dw-methods-empty" role="status">Ödeme yöntemleri API üzerinden yükleniyor...</p>
            </div>
        </div>
        <div class="deposit-inline-flow vega-panel--deposit" id="depositInlineQuickForm" aria-label="Para yatırma formu">
            <div class="vega-deposit-sheet">
                <div class="panel-info-table vega-deposit-summary">
                    <div class="panel-info-cell"><strong>Ödeme Yöntemi</strong><span id="dInlineMethod">API üzerinden seçilecek</span></div>
                    <div class="panel-info-cell"><strong>Ücret</strong><span>Ücretsiz</span></div>
                    <div class="panel-info-cell"><strong>İşlem Süresi</strong><span id="dInlineProcTime">Anlık</span></div>
                    <div class="panel-info-cell"><strong>Min.</strong><span id="dInlineMin">—</span></div>
                    <div class="panel-info-cell"><strong>Maks.</strong><span id="dInlineMax">—</span></div>
                </div>
                <div class="panel-instruction vega-deposit-welcome"><?php echo htmlspecialchars($dw_site_brand, ENT_QUOTES, 'UTF-8'); ?> Ailesine hoş geldiniz. İyi eğlenceler, bol şanslar dileriz. Para yatırmak için lütfen aşağıdaki tüm gerekli alanları doldurun. Minimum tutar altı yatırımlar <strong class="panel-instruction-warn">'İADE EDİLMEZ'</strong> lütfen kurallara uygun yatırım yapınız.</div>
                <div id="depositCryptoTypeWrap" class="form-group form-group--crypto-type">
                    <label for="inlineCryptoType">Kripto türü *</label>
                    <select id="inlineCryptoType" class="vega-select" autocomplete="off">
                        <option value="tron" selected>TRON (TRC-20)</option>
                        <option value="bsc">BSC (BEP-20)</option>
                        <option value="eth">Ethereum (ERC-20)</option>
                        <option value="BTC">Bitcoin</option>
                        <option value="LTC">Litecoin</option>
                        <option value="USDT_TRON">USDT (TRC-20)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="inlineDepositAmount">Tutar *</label>
                    <input type="number" id="inlineDepositAmount" placeholder="Tutar *" min="0" max="999999999" step="1" inputmode="decimal" autocomplete="off">
                </div>
                <div class="panel-actions">
                    <button type="button" class="vega-deposit-submit" id="inlineDepositSubmitBtn" onclick="processInlineVegaDeposit()">PARA YATIR</button>
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
            <p>Para çekme talebiniz başarıyla alındı ve işleme konuldu.</p>
            <p>En kısa sürede bakiyeniz hesabınıza aktarılacaktır.</p>
        </div>
        <div class="popup-footer">
            <button class="btn btn-primary" onclick="closeSuccessWithdrawalPopup()">Tamam</button>
        </div>
    </div>
</div>

<!-- BİLGİ: kart gövdesinde vega-app ile aynı şablon; sayfa başlığı personal-details-header (kart dışı) -->
<div id="bilgiModal" class="profile-bilgi-panel" hidden aria-hidden="true">
    <div class="bilgi-tabs" role="tablist" aria-label="Ödeme bilgisi">
        <button type="button" class="bilgi-tab active" role="tab" aria-selected="true" data-bilgi-tab="deposit">PARA YATIR</button>
        <button type="button" class="bilgi-tab" role="tab" aria-selected="false" data-bilgi-tab="withdraw">ÇEKİM</button>
    </div>
    <div class="bilgi-list-wrap">
        <div id="bilgiListDeposit" class="bilgi-list bilgi-list-active">
            <div class="bilgi-table-scroll">
                <div class="bilgi-table" role="table" aria-label="Para yatırma yöntemleri">
                    <p class="dw-methods-empty" data-bilgi-method-list="deposit" role="status">Yatırım bilgileri API üzerinden yükleniyor...</p>
                </div>
            </div>
        </div>
        <div id="bilgiListWithdraw" class="bilgi-list">
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
