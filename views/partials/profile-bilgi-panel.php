<?php
/**
 * Deposit + withdraw limits info panel (Bilgi) — CM625 PaymentMethodsInfo markup.
 * Vars: $bilgi_open (bool), $bilgi_prefer_withdraw (bool)
 */
$bilgi_open = !empty($bilgi_open);
$bilgi_prefer_withdraw = !empty($bilgi_prefer_withdraw);
$depActive = !$bilgi_prefer_withdraw;
$wdrActive = $bilgi_prefer_withdraw;
?>
<div id="bilgiModal"
     class="description-wrapper-bc profile-bilgi-panel<?= $bilgi_open ? ' is-bilgi-shown' : '' ?>"
     <?= $bilgi_open ? '' : 'hidden' ?>
     aria-hidden="<?= $bilgi_open ? 'false' : 'true' ?>">
  <div class="description-container-bc deposit logged-in">
    <div class="second-tabs-bc" role="tablist" aria-label="Bilgi türü">
      <div class="tab-bc selected-underline<?= $depActive ? ' active' : '' ?>"
           data-bilgi-tab="deposit"
           role="tab"
           tabindex="0"
           aria-selected="<?= $depActive ? 'true' : 'false' ?>"
           title="PARA YATIR"><span>PARA YATIR</span></div>
      <div class="tab-bc selected-underline<?= $wdrActive ? ' active' : '' ?>"
           data-bilgi-tab="withdraw"
           role="tab"
           tabindex="0"
           aria-selected="<?= $wdrActive ? 'true' : 'false' ?>"
           title="ÇEKİM"><span>ÇEKİM</span></div>
    </div>
    <div id="bilgiListDeposit"
         data-bilgi-method-list="deposit"
         <?= $depActive ? '' : 'hidden' ?>>
      <p class="dw-methods-empty" role="status">Yatırım bilgileri API üzerinden yükleniyor...</p>
    </div>
    <div id="bilgiListWithdraw"
         data-bilgi-method-list="withdraw"
         <?= $wdrActive ? '' : 'hidden' ?>>
      <p class="dw-methods-empty" role="status">Çekim bilgileri API üzerinden yükleniyor...</p>
    </div>
  </div>
</div>
<?php
unset($bilgi_open, $bilgi_prefer_withdraw, $depActive, $wdrActive);
