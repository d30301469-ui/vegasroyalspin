<?php
/** hdr-dynamic-content — CÜZDANA BAĞLAN */
$loggedIn = isset($loggedIn) ? (bool) $loggedIn : (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true);
?>
<div class="header-buttons-wallet hdr-connect-wallet-row connect-button-text">
  <button type="button"
          class="btn a-color connect-wallet hdr-connect-wallet-btn"
          id="connectWalletBtn"
          onclick="<?php if ($loggedIn): ?>if (typeof window.__openMobileBalancePage === 'function' && window.__openMobileBalancePage('deposit')) return false; if (typeof redirectToDeposit === 'function') redirectToDeposit(); return false;<?php else: ?>if (typeof showLoginWarning === 'function') showLoginWarning();<?php endif; ?>"
          title="Cüzdana Bağlan">
    CÜZDANA BAĞLAN
  </button>
</div>
