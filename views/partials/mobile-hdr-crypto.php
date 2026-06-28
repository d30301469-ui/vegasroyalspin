<?php
/** hdr-crypto-content — orijinal BC: CÜZDANA BAĞLAN */
$loggedIn = isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
?>
<div class="hdr-crypto-content" data-mobile-header-crypto>
  <button type="button"
          class="btn a-color connect-wallet"
          id="connectWalletBtn"
          title="Cüzdana Bağlan"
          onclick="<?php if ($loggedIn): ?>if (typeof redirectToDeposit === 'function') redirectToDeposit();<?php else: ?>if (typeof showLoginWarning === 'function') showLoginWarning();<?php endif; ?>">
    <span>CÜZDANA BAĞLAN</span>
  </button>
</div>
