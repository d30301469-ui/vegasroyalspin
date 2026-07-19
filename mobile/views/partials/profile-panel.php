<?php
/**
 * Mobile-native profil paneli (slide-in overlay) — masaüstü profil modalinden bağımsız.
 * Yalnızca giriş yapmış üyeler için header.php içinde include edilir.
 */
$panelLoggedIn = isset($loggedIn) ? (bool) $loggedIn : (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true);
if (!$panelLoggedIn) {
    return;
}

$panelUsername = trim((string) ($_SESSION['username'] ?? ''));
$panelUserId = (string) ($_SESSION['user_id'] ?? '');
$panelInitial = strtoupper(mb_substr($panelUsername !== '' ? $panelUsername : 'U', 0, 2));

$panelBadge = is_array($headerLoyaltyBadge ?? null) ? $headerLoyaltyBadge : [];
$panelLoyaltyName = (string) ($panelBadge['name'] ?? 'Bronze');
$panelLoyaltyIcon = (string) ($panelBadge['icon_url'] ?? '/assets/images/loyalty/badges/bronze.png');
$panelLoyaltyPoints = (int) ($panelBadge['points'] ?? 0);
?>
<div class="mprofile-overlay" id="mprofileOverlay" aria-hidden="true"></div>
<aside class="mprofile-panel" id="mprofilePanel" aria-hidden="true" role="dialog" aria-label="Profil">
  <div class="mprofile-panel__scroll">
    <div class="mprofile-amount">
      <div class="mprofile-amount__row">
        <div class="mprofile-amount__balance">
          <span class="mprofile-amount__title">ANA BAKİYE</span>
          <b class="mprofile-amount__value"><span data-balance-target="mprofileMain">0</span> ₺</b>
        </div>
        <i class="mprofile-amount__eye bc-i-eye" aria-hidden="true"></i>
      </div>
      <div class="mprofile-amount__buttons">
        <a class="mprofile-amount__deposit" href="/profile/deposit-withdraw">
          <i class="bc-i-wallet" aria-hidden="true"></i><span>PARA YATIR</span>
        </a>
        <a class="mprofile-amount__withdraw" href="/profile/withdraw">
          <i class="bc-i-withdraw" aria-hidden="true"></i><span>ÇEKİM</span>
        </a>
      </div>
    </div>

    <a class="mprofile-loyalty" href="/profile/sadakat-puanlari">
      <img class="mprofile-loyalty__img" src="<?= htmlspecialchars($panelLoyaltyIcon, ENT_QUOTES, 'UTF-8') ?>" alt="" onerror="this.style.display='none'">
      <span class="mprofile-loyalty__text">Sadakat Puanları</span>
      <span class="mprofile-loyalty__points"><?= htmlspecialchars((string) $panelLoyaltyPoints, ENT_QUOTES, 'UTF-8') ?> puan</span>
    </a>

    <a class="mprofile-user" href="/profile/details">
      <span class="mprofile-user__avatar"><?= htmlspecialchars($panelInitial, ENT_QUOTES, 'UTF-8') ?></span>
      <span class="mprofile-user__id">
        <span class="mprofile-user__name"><?= htmlspecialchars($panelUsername, ENT_QUOTES, 'UTF-8') ?></span>
        <?php if ($panelUserId !== ''): ?>
        <span class="mprofile-user__uid"><?= htmlspecialchars($panelUserId, ENT_QUOTES, 'UTF-8') ?><i class="bc-i-copy mprofile-user__copy" data-user-id="<?= htmlspecialchars($panelUserId, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i></span>
        <?php endif; ?>
      </span>
      <i class="mprofile-user__arrow bc-i-small-arrow-right" aria-hidden="true"></i>
    </a>

    <nav class="mprofile-links" aria-label="Profil menüsü">
      <a class="mprofile-link" href="/profile/details">
        <i class="mprofile-link__icon bc-i-user" aria-hidden="true"></i>
        <span class="mprofile-link__title">PROFİLİM</span>
        <i class="mprofile-link__arrow bc-i-small-arrow-right" aria-hidden="true"></i>
      </a>
      <a class="mprofile-link" href="/profile/deposit-withdraw">
        <i class="mprofile-link__icon bc-i-balance-management" aria-hidden="true"></i>
        <span class="mprofile-link__title">BAKİYE YÖNETİMİ</span>
        <i class="mprofile-link__arrow bc-i-small-arrow-right" aria-hidden="true"></i>
      </a>
      <a class="mprofile-link" href="/profile/bet-history">
        <i class="mprofile-link__icon bc-i-history" aria-hidden="true"></i>
        <span class="mprofile-link__title">BAHİS GEÇMİŞİ</span>
        <i class="mprofile-link__arrow bc-i-small-arrow-right" aria-hidden="true"></i>
      </a>
      <a class="mprofile-link" href="/profile/bonus-spor">
        <i class="mprofile-link__icon bc-i-promotion" aria-hidden="true"></i>
        <span class="mprofile-link__title">BONUSLAR</span>
        <i class="mprofile-link__arrow bc-i-small-arrow-right" aria-hidden="true"></i>
      </a>
      <a class="mprofile-link" href="/profile/messages">
        <i class="mprofile-link__icon bc-i-message" aria-hidden="true"></i>
        <span class="mprofile-link__title">MESAJLAR</span>
        <i class="mprofile-link__arrow bc-i-small-arrow-right" aria-hidden="true"></i>
      </a>
    </nav>

    <form class="mprofile-promo" onsubmit="return false;">
      <input type="text" class="mprofile-promo__input" name="promoCode" placeholder="PROMOSYON KODU" autocomplete="off" maxlength="64">
      <button type="submit" class="mprofile-promo__btn" disabled>UYGULA</button>
    </form>

    <a class="mprofile-logout" href="/logout">
      <i class="bc-i-logout" aria-hidden="true"></i><span>Çıkış Yap</span>
    </a>
  </div>
</aside>
