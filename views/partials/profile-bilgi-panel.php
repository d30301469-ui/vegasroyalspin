<?php
/**
 * Deposit + withdraw limits info panel (Bilgi).
 * Vars: $bilgi_open (bool), $bilgi_prefer_withdraw (bool)
 */
$bilgi_open = !empty($bilgi_open);
$bilgi_prefer_withdraw = !empty($bilgi_prefer_withdraw);
?>
<div id="bilgiModal"
     class="profile-bilgi-panel<?= $bilgi_open ? ' is-bilgi-shown' : '' ?>"
     <?= $bilgi_open ? '' : 'hidden' ?>
     aria-hidden="<?= $bilgi_open ? 'false' : 'true' ?>">
  <div class="bilgi-tabs" role="tablist" aria-label="Bilgi türü">
    <button type="button"
            class="bilgi-tab<?= $bilgi_prefer_withdraw ? '' : ' active' ?>"
            data-bilgi-tab="deposit"
            role="tab"
            aria-selected="<?= $bilgi_prefer_withdraw ? 'false' : 'true' ?>">PARA YATIR</button>
    <button type="button"
            class="bilgi-tab<?= $bilgi_prefer_withdraw ? ' active' : '' ?>"
            data-bilgi-tab="withdraw"
            role="tab"
            aria-selected="<?= $bilgi_prefer_withdraw ? 'true' : 'false' ?>">ÇEKİM</button>
  </div>
  <div class="bilgi-list-wrap">
    <div id="bilgiListDeposit" class="bilgi-list<?= $bilgi_prefer_withdraw ? '' : ' bilgi-list-active' ?>">
      <div class="bilgi-table-scroll">
        <div class="bilgi-table" role="table" aria-label="Para yatırma yöntemleri">
          <p class="dw-methods-empty" data-bilgi-method-list="deposit" role="status">Yatırım bilgileri API üzerinden yükleniyor...</p>
        </div>
      </div>
    </div>
    <div id="bilgiListWithdraw" class="bilgi-list<?= $bilgi_prefer_withdraw ? ' bilgi-list-active' : '' ?>">
      <div class="bilgi-table-scroll">
        <div class="bilgi-table" role="table" aria-label="Para çekme yöntemleri">
          <p class="dw-methods-empty" data-bilgi-method-list="withdraw" role="status">Çekim bilgileri API üzerinden yükleniyor...</p>
        </div>
      </div>
    </div>
  </div>
</div>
<?php
unset($bilgi_open, $bilgi_prefer_withdraw);
