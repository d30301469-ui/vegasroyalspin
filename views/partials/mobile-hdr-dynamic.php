<?php
/** hdr-dynamic-content — CÜZDANA BAĞLAN */
?>
<div class="header-buttons-wallet hdr-connect-wallet-row connect-button-text">
  <button type="button"
          class="btn a-color connect-wallet hdr-connect-wallet-btn"
          id="connectWalletBtn"
          onclick="<?php if ($loggedIn): ?>if (typeof redirectToDeposit === 'function') redirectToDeposit();<?php else: ?>if (typeof showLoginWarning === 'function') showLoginWarning();<?php endif; ?>"
          title="Cüzdana Bağlan">
    CÜZDANA BAĞLAN
  </button>
</div>
