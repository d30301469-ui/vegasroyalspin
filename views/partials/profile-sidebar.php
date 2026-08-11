<?php
/**
 * Desktop CM622 profil sol paneli (user-profile-nav).
 * $profileActiveTab, $username / $user_info, $profile_modal ile include edilir.
 */
$sidebar_username   = $username ?? $user_info['username'] ?? '';
$sidebar_initial    = $initial ?? (isset($user_info['username']) ? strtoupper(substr($user_info['username'], 0, 2)) : '');
if (strlen($sidebar_initial) < 2) {
    $sidebar_initial = strtoupper(substr($sidebar_username, 0, 2));
}
$sidebar_display_name = $sidebar_username;
$sidebar_user_id = $user_id ?? $_SESSION['user_id'] ?? (isset($user_info, $user_info['id']) ? $user_info['id'] : '');
$sidebar_loyalty = [
    'name' => 'Bronze',
    'code' => 'bronze',
    'initial' => 'B',
    'points' => 0,
    'redeemable_points' => 0,
    'progress_percent' => 0,
    'icon_url' => '',
];
if ((int) $sidebar_user_id > 0) {
    if (!class_exists('ApiLoyalty', false) && defined('BASE_PATH')) {
        require_once BASE_PATH . '/api/bootstrap.php';
    }
    if (class_exists('ApiLoyalty')) {
        $sidebar_loyalty = ApiLoyalty::publicBadgeForUser((int) $sidebar_user_id);
    }
}
$active_tab     = $profileActiveTab ?? null;
$is_logged_in   = $isLoggedIn ?? (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true);
$profile_open    = in_array($active_tab, ['details', 'change-password', 'two-factor', 'freeze-account'], true);
$balance_open    = in_array($active_tab, ['deposit', 'deposit-withdraw', 'deposit-bilgi', 'withdraw', 'withdraw-bilgi', 'deposit-withdraw-history', 'withdrawal-status'], true);
$bet_open        = in_array($active_tab, ['bet-history', 'casino-history'], true);
$promotions_open = in_array($active_tab, ['bonus-spor', 'bonus-casino', 'bonus-history', 'freespin', 'loyalty-points'], true);
$bonus_sub_tab = $bonusSubTab ?? null;
$messages_open   = $active_tab === 'messages';
$unread_count    = isset($message_unread_count) ? (int) $message_unread_count : 0;

$_profile_modal_q = !empty($profile_modal) ? 'modal=1&' : '';
$depositWithDrawDepositHref = '/profile/deposit' . (!empty($profile_modal) ? '?modal=1' : '');
$depositWithDrawWithdrawHref = '/profile/withdraw' . (!empty($profile_modal) ? '?modal=1' : '');
$depositWithdrawHistoryHref = '/profile/deposit-withdraw-history' . (!empty($profile_modal) ? '?modal=1' : '');
$on_withdraw_balance = in_array($active_tab, ['withdraw', 'withdraw-bilgi'], true);
$depositInfoHref = $on_withdraw_balance
    ? ('/profile/withdraw?' . $_profile_modal_q . 'bilgi=1#bilgi')
    : ('/profile/deposit?' . $_profile_modal_q . 'bilgi=1#bilgi');
$withdrawalStatusHref = '/profile/withdrawal-status' . (!empty($profile_modal) ? '?modal=1' : '');
$messagesInboxHref = '/profile/messages' . (!empty($profile_modal) ? '?modal=1' : '');
$messagesSentHref = '/profile/messages?box=sent' . (!empty($profile_modal) ? '&modal=1' : '');
$messagesNewHref = '/profile/messages?box=new' . (!empty($profile_modal) ? '&modal=1' : '');
$loyaltyPointsHref = '/profile/sadakat-puanlari' . (!empty($profile_modal) ? '?modal=1' : '');
$loyaltyIcon = trim((string) ($sidebar_loyalty['icon_url'] ?? ''));
if ($loyaltyIcon === '') {
    $loyaltyIcon = '/assets/images/loyalty/badges/bronze.svg';
}
$mq = !empty($profile_modal) ? '?modal=1' : '';
?>
<div class="u-i-profile-page-container" id="profilePlayerSidebar" data-cm622-profile-sidebar="1">
  <div class="u-i-profile-page-bc">
  <div class="u-i-profile-page-content" data-scroll-lock-scrollable>
    <div class="u-i-p-p-u-i-edit-button-bc">
      <p class="u-i-p-p-u-i-avatar-holder-bc"><?php echo htmlspecialchars($sidebar_initial); ?></p>
      <p class="u-i-p-p-u-i-identifiers-bc">
        <span class="u-i-p-p-u-i-d-username-bc ellipsis"><?php echo htmlspecialchars($sidebar_display_name); ?></span>
        <?php if ($sidebar_user_id !== ''): ?>
        <span class="u-i-p-p-u-i-d-user-id-bc ellipsis" data-user-id="<?php echo htmlspecialchars((string)$sidebar_user_id); ?>" title="Kopyala" role="button" tabindex="0">
          <?php echo htmlspecialchars((string)$sidebar_user_id); ?>
          <i class="u-i-p-p-u-i-d-user-id-copy-bc bc-i-copy" aria-hidden="true"></i>
        </span>
        <?php endif; ?>
      </p>
    </div>

    <div class="u-i-p-amount-holder-bc">
      <div class="u-i-p-amounts-bc withdrawable">
        <div class="u-i-p-a-content-bc">
          <div class="total-balance-r-bc">
            <div class="u-i-p-a-user-balance">
              <span class="u-i-p-a-title-bc ellipsis"><?= htmlspecialchars(__('profile.main_balance'), ENT_QUOTES, 'UTF-8') ?></span>
              <b class="u-i-p-a-amount-bc" data-cm622-balance="main">0 ₺</b>
            </div>
            <i class="u-i-p-a-c-icon-bc bc-i-eye"
               data-profile-balance-toggle
               role="button"
               tabindex="0"
               title="<?= htmlspecialchars(__('profile.hide_balance'), ENT_QUOTES, 'UTF-8') ?>"
               aria-label="<?= htmlspecialchars(__('profile.hide_balance'), ENT_QUOTES, 'UTF-8') ?>"
               aria-pressed="false"></i>
          </div>
          <div class="u-i-p-a-buttons-bc">
            <a class="u-i-p-a-deposit-bc ellipsis" href="<?= htmlspecialchars($depositWithDrawDepositHref) ?>"><i class="bc-i-wallet" aria-hidden="true"></i><span class="ellipsis" title="<?= htmlspecialchars(__('profile.deposit'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('profile.deposit'), ENT_QUOTES, 'UTF-8') ?></span></a>
            <a class="u-i-p-a-withdraw-bc ellipsis" href="<?= htmlspecialchars($depositWithDrawWithdrawHref) ?>"><i class="bc-i-withdraw" aria-hidden="true"></i><span class="ellipsis" title="<?= htmlspecialchars(__('profile.withdraw'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('profile.withdraw'), ENT_QUOTES, 'UTF-8') ?></span></a>
          </div>
        </div>
      </div>
      <div class="u-i-p-amounts-bc bonuses">
        <div class="u-i-p-a-content-bc">
          <span class="u-i-p-a-title-bc ellipsis"><?= htmlspecialchars(__('profile.total_bonus'), ENT_QUOTES, 'UTF-8') ?></span>
          <span class="u-i-p-a-amount-bc" data-cm622-balance="bonus">0 ₺</span>
        </div>
      </div>
    </div>

    <a class="u-i-p-a-loyaltyPoint-bc" href="<?= htmlspecialchars($loyaltyPointsHref, ENT_QUOTES, 'UTF-8') ?>">
      <div class="loyaltyBonusHeader"><img class="loyaltyBonusImg" src="<?= htmlspecialchars($loyaltyIcon, ENT_QUOTES, 'UTF-8') ?>" alt=""></div>
      <p class="u-i-p-a-loyaltyPointText-bc ellipsis"><?= htmlspecialchars(__('profile.loyalty_points'), ENT_QUOTES, 'UTF-8') ?></p>
    </a>

    <div class="user-profile-nav<?= $profile_open ? ' active' : '' ?>">
      <div class="user-profile-nav-header" data-toggle-sub role="button" tabindex="0">
        <i class="user-profile-nav-icon bc-i-user" aria-hidden="true"></i>
        <span class="user-profile-nav-title"><?= htmlspecialchars(__('profile.section_profile'), ENT_QUOTES, 'UTF-8') ?></span>
        <i class="count-blink-even" data-badge=""></i>
        <i class="user-profile-nav-arrow <?= $profile_open ? 'bc-i-small-arrow-up' : 'bc-i-small-arrow-down' ?>" aria-hidden="true"></i>
      </div>
      <div class="user-profile-nav-list accordion-sub">
        <a class="user-profile-nav-item<?= $active_tab === 'details' ? ' active' : '' ?>" href="/profile/details<?= $mq ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('profile.personal_details'), ENT_QUOTES, 'UTF-8') ?></span><i class="count-blink-even" data-badge=""></i></a>
        <a class="user-profile-nav-item<?= $active_tab === 'change-password' ? ' active' : '' ?>" href="/profile/change-password<?= $mq ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('profile.change_password'), ENT_QUOTES, 'UTF-8') ?></span><i class="count-blink-even" data-badge=""></i></a>
        <a class="user-profile-nav-item<?= $active_tab === 'two-factor' ? ' active' : '' ?>" href="/profile/two-factor<?= $mq ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('profile.two_factor'), ENT_QUOTES, 'UTF-8') ?></span><i class="count-blink-even" data-badge=""></i></a>
        <a class="user-profile-nav-item<?= $active_tab === 'freeze-account' ? ' active' : '' ?>" href="/profile/freeze-account<?= $mq ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('profile.freeze'), ENT_QUOTES, 'UTF-8') ?></span><i class="count-blink-even" data-badge=""></i></a>
        <div class="user-profile-nav-item-cursor<?= $profile_open && in_array($active_tab, ['details','change-password','two-factor','freeze-account'], true) ? ' user-profile-cursor-visible' : '' ?>"></div>
      </div>
    </div>

    <div class="user-profile-nav<?= $balance_open ? ' active' : '' ?>">
      <div class="user-profile-nav-header" data-toggle-sub role="button" tabindex="0">
        <i class="user-profile-nav-icon bc-i-balance-management" aria-hidden="true"></i>
        <span class="user-profile-nav-title"><?= htmlspecialchars(__('profile.section_balance'), ENT_QUOTES, 'UTF-8') ?></span>
        <i class="count-blink-even" data-badge=""></i>
        <i class="user-profile-nav-arrow <?= $balance_open ? 'bc-i-small-arrow-up' : 'bc-i-small-arrow-down' ?>" aria-hidden="true"></i>
      </div>
      <div class="user-profile-nav-list accordion-sub">
        <a class="user-profile-nav-item<?= in_array($active_tab, ['deposit', 'deposit-withdraw'], true) ? ' active' : '' ?>" href="<?= htmlspecialchars($depositWithDrawDepositHref) ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('profile.deposit'), ENT_QUOTES, 'UTF-8') ?></span><i class="count-blink-even" data-badge=""></i></a>
        <a class="user-profile-nav-item<?= $active_tab === 'withdraw' ? ' active' : '' ?>" href="<?= htmlspecialchars($depositWithDrawWithdrawHref) ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('profile.withdraw'), ENT_QUOTES, 'UTF-8') ?></span><i class="count-blink-even" data-badge=""></i></a>
        <a class="user-profile-nav-item<?= $active_tab === 'deposit-withdraw-history' ? ' active' : '' ?>" href="<?= htmlspecialchars($depositWithdrawHistoryHref) ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('profile.history'), ENT_QUOTES, 'UTF-8') ?></span><i class="count-blink-even" data-badge=""></i></a>
        <a class="user-profile-nav-item<?= in_array($active_tab, ['deposit-bilgi', 'withdraw-bilgi'], true) ? ' active' : '' ?>" href="<?= htmlspecialchars($depositInfoHref) ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('profile.info'), ENT_QUOTES, 'UTF-8') ?></span><i class="count-blink-even" data-badge=""></i></a>
        <a class="user-profile-nav-item<?= $active_tab === 'withdrawal-status' ? ' active' : '' ?>" href="<?= htmlspecialchars($withdrawalStatusHref) ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('profile.withdrawal_status'), ENT_QUOTES, 'UTF-8') ?></span><i class="count-blink-even" data-badge=""></i></a>
        <div class="user-profile-nav-item-cursor<?= $balance_open ? ' user-profile-cursor-visible' : '' ?>"></div>
      </div>
    </div>

    <div class="user-profile-nav<?= $bet_open ? ' active' : '' ?>">
      <div class="user-profile-nav-header" data-toggle-sub role="button" tabindex="0">
        <i class="user-profile-nav-icon bc-i-history" aria-hidden="true"></i>
        <span class="user-profile-nav-title"><?= htmlspecialchars(__('profile.section_bets'), ENT_QUOTES, 'UTF-8') ?></span>
        <i class="count-blink-even" data-badge=""></i>
        <i class="user-profile-nav-arrow <?= $bet_open ? 'bc-i-small-arrow-up' : 'bc-i-small-arrow-down' ?>" aria-hidden="true"></i>
      </div>
      <div class="user-profile-nav-list accordion-sub">
        <a class="user-profile-nav-item<?= ($betHistoryFilter ?? '') === 'tumu' || ($active_tab === 'bet-history' && ($betHistoryFilter ?? '') === '') ? ' active' : '' ?>" href="/profile/bet-history<?= $mq ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('winners.all'), ENT_QUOTES, 'UTF-8') ?></span><i class="count-blink-even" data-badge=""></i></a>
        <a class="user-profile-nav-item<?= ($betHistoryFilter ?? '') === 'acik' ? ' active' : '' ?>" href="/profile/bet-history?filter=acik<?= !empty($profile_modal) ? '&modal=1' : '' ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('profile.open_bets'), ENT_QUOTES, 'UTF-8') ?></span><i class="count-blink-even" data-badge=""></i></a>
        <a class="user-profile-nav-item<?= ($betHistoryFilter ?? '') === 'nakde' ? ' active' : '' ?>" href="/profile/bet-history?filter=nakde<?= !empty($profile_modal) ? '&modal=1' : '' ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('profile.cashed_out'), ENT_QUOTES, 'UTF-8') ?></span><i class="count-blink-even" data-badge=""></i></a>
        <a class="user-profile-nav-item<?= ($betHistoryFilter ?? '') === 'kazanc' ? ' active' : '' ?>" href="/profile/bet-history?filter=kazanc<?= !empty($profile_modal) ? '&modal=1' : '' ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('profile.won'), ENT_QUOTES, 'UTF-8') ?></span><i class="count-blink-even" data-badge=""></i></a>
        <a class="user-profile-nav-item<?= ($betHistoryFilter ?? '') === 'kayip' ? ' active' : '' ?>" href="/profile/bet-history?filter=kayip<?= !empty($profile_modal) ? '&modal=1' : '' ?>"><span class="user-profile-nav-item-title ellipsis">KAYIP</span><i class="count-blink-even" data-badge=""></i></a>
        <a class="user-profile-nav-item<?= ($betHistoryFilter ?? '') === 'iade' ? ' active' : '' ?>" href="/profile/bet-history?filter=iade<?= !empty($profile_modal) ? '&modal=1' : '' ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('profile.returned'), ENT_QUOTES, 'UTF-8') ?></span><i class="count-blink-even" data-badge=""></i></a>
        <a class="user-profile-nav-item<?= ($betHistoryFilter ?? '') === 'kazanan-iade' ? ' active' : '' ?>" href="/profile/bet-history?filter=kazanan-iade<?= !empty($profile_modal) ? '&modal=1' : '' ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('profile.won_return'), ENT_QUOTES, 'UTF-8') ?></span><i class="count-blink-even" data-badge=""></i></a>
        <a class="user-profile-nav-item<?= ($betHistoryFilter ?? '') === 'kayip-iade' ? ' active' : '' ?>" href="/profile/bet-history?filter=kayip-iade<?= !empty($profile_modal) ? '&modal=1' : '' ?>"><span class="user-profile-nav-item-title ellipsis">KAYIP-IADE</span><i class="count-blink-even" data-badge=""></i></a>
        <div class="user-profile-nav-item-cursor<?= $bet_open ? ' user-profile-cursor-visible' : '' ?>"></div>
      </div>
    </div>

    <div class="user-profile-nav<?= $promotions_open ? ' active' : '' ?>">
      <div class="user-profile-nav-header" data-toggle-sub role="button" tabindex="0">
        <i class="user-profile-nav-icon bc-i-promotion" aria-hidden="true"></i>
        <span class="user-profile-nav-title"><?= htmlspecialchars(__('profile.section_bonuses'), ENT_QUOTES, 'UTF-8') ?></span>
        <i class="count-blink-even" data-badge=""></i>
        <i class="user-profile-nav-arrow <?= $promotions_open ? 'bc-i-small-arrow-up' : 'bc-i-small-arrow-down' ?>" aria-hidden="true"></i>
      </div>
      <div class="user-profile-nav-list accordion-sub">
        <a class="user-profile-nav-item" href="/profile/bonus-spor<?= $mq ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('profile.bonus_request'), ENT_QUOTES, 'UTF-8') ?></span><i class="count-blink-even" data-badge=""></i></a>
        <a class="user-profile-nav-item<?= $bonus_sub_tab === 'spor' ? ' active' : '' ?>" href="/profile/bonus-spor<?= $mq ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('profile.bonus_sport'), ENT_QUOTES, 'UTF-8') ?></span><i class="count-blink-even" data-badge=""></i></a>
        <a class="user-profile-nav-item<?= $bonus_sub_tab === 'casino' ? ' active' : '' ?>" href="/profile/bonus-casino<?= $mq ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('profile.bonus_casino'), ENT_QUOTES, 'UTF-8') ?></span><i class="count-blink-even" data-badge=""></i></a>
        <a class="user-profile-nav-item<?= $active_tab === 'bonus-history' ? ' active' : '' ?>" href="/profile/bonus-history<?= $mq ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('profile.bonus_history'), ENT_QUOTES, 'UTF-8') ?></span><i class="count-blink-even" data-badge=""></i></a>
        <a class="user-profile-nav-item" href="/profile/bonus-spor<?= $mq ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('profile.promo_code_nav'), ENT_QUOTES, 'UTF-8') ?></span><i class="count-blink-even" data-badge=""></i></a>
        <a class="user-profile-nav-item<?= $active_tab === 'freespin' ? ' active' : '' ?>" href="/profile/freespin<?= $mq ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('profile.freespins'), ENT_QUOTES, 'UTF-8') ?></span><i class="count-blink-even" data-badge=""></i></a>
        <a class="user-profile-nav-item<?= $active_tab === 'loyalty-points' ? ' active' : '' ?>" href="<?= htmlspecialchars($loyaltyPointsHref, ENT_QUOTES, 'UTF-8') ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('profile.loyalty_nav'), ENT_QUOTES, 'UTF-8') ?></span><i class="count-blink-even" data-badge=""></i></a>
        <div class="user-profile-nav-item-cursor<?= $promotions_open ? ' user-profile-cursor-visible' : '' ?>"></div>
      </div>
    </div>

    <div class="user-profile-nav<?= $messages_open ? ' active' : '' ?>">
      <div class="user-profile-nav-header" data-toggle-sub role="button" tabindex="0">
        <i class="user-profile-nav-icon bc-i-message" aria-hidden="true"></i>
        <span class="user-profile-nav-title"><?= htmlspecialchars(__('profile.section_messages'), ENT_QUOTES, 'UTF-8') ?></span>
        <i class="<?= $unread_count > 0 ? 'count-odd-animation count-blink-odd' : 'count-blink-even' ?>" data-badge="<?= $unread_count > 0 ? (int)$unread_count : '' ?>"></i>
        <i class="user-profile-nav-arrow <?= $messages_open ? 'bc-i-small-arrow-up' : 'bc-i-small-arrow-down' ?>" aria-hidden="true"></i>
      </div>
      <div class="user-profile-nav-list accordion-sub">
        <a class="user-profile-nav-item<?= ($active_tab === 'messages' && ($messages_box ?? 'inbox') === 'inbox') ? ' active' : '' ?>" href="<?= htmlspecialchars($messagesInboxHref, ENT_QUOTES, 'UTF-8') ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('profile.inbox'), ENT_QUOTES, 'UTF-8') ?></span><i class="<?= $unread_count > 0 ? 'count-odd-animation count-blink-odd js-profile-inbox-unread' : 'count-blink-even' ?>" data-badge="<?= $unread_count > 0 ? (int)$unread_count : '' ?>"></i></a>
        <a class="user-profile-nav-item<?= ($messages_box ?? '') === 'sent' ? ' active' : '' ?>" href="<?= htmlspecialchars($messagesSentHref, ENT_QUOTES, 'UTF-8') ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('profile.sent'), ENT_QUOTES, 'UTF-8') ?></span><i class="count-blink-even" data-badge=""></i></a>
        <a class="user-profile-nav-item<?= ($messages_box ?? '') === 'new' ? ' active' : '' ?>" href="<?= htmlspecialchars($messagesNewHref, ENT_QUOTES, 'UTF-8') ?>"><span class="user-profile-nav-item-title ellipsis"><?= htmlspecialchars(__('profile.new_message'), ENT_QUOTES, 'UTF-8') ?></span><i class="count-blink-even" data-badge=""></i></a>
        <div class="user-profile-nav-item-cursor<?= $messages_open ? ' user-profile-cursor-visible' : '' ?>"></div>
      </div>
    </div>

    <div class="promoCodeWrapper-bc profile-panel-promo-code"<?php if (!empty($is_logged_in)): ?> data-profile-promo-block<?php endif; ?>>
      <form onsubmit="return false;">
        <div class="u-i-p-control-item-holder-bc">
          <div class="form-control-bc default">
            <label class="form-control-label-bc inputs">
              <input type="text" class="form-control-input-bc" name="promoCode" id="profileModalPromoCode" step="0" value="" placeholder=" " autocomplete="off" maxlength="64">
              <i class="form-control-input-stroke-bc" aria-hidden="true"></i>
              <span class="form-control-title-bc ellipsis"><?= htmlspecialchars(__('profile.promo_code'), ENT_QUOTES, 'UTF-8') ?></span>
            </label>
          </div>
        </div>
        <div class="u-i-p-c-footer-bc">
          <button class="btn a-color big-btn" type="button" id="profileModalPromoUseLegacy" title="<?= htmlspecialchars(__('profile.promo_apply'), ENT_QUOTES, 'UTF-8') ?> " disabled><span><?= htmlspecialchars(__('profile.promo_apply'), ENT_QUOTES, 'UTF-8') ?> </span></button>
        </div>
      </form>
      <?php if (!empty($is_logged_in)): ?>
      <div class="profile-sidebar-promo-hidden" hidden>
        <select id="profileModalPromoSelect" aria-label="<?= htmlspecialchars(__('profile.promo_code'), ENT_QUOTES, 'UTF-8') ?>"><option value=""><?= htmlspecialchars(__('common.loading'), ENT_QUOTES, 'UTF-8') ?></option></select>
        <button type="button" id="profileModalPromoApply"><?= htmlspecialchars(__('profile.promo_apply'), ENT_QUOTES, 'UTF-8') ?></button>
        <input type="text" id="profileModalPromoNote" maxlength="500" autocomplete="off">
        <div id="profilePromocodesStatus" aria-live="polite"></div>
      </div>
      <?php endif; ?>
    </div>

    <?php if (!empty($is_logged_in)): ?>
    <button class="userLogoutBtn btn" type="button" onclick="window.location.href='/logout'"><i class="userLogoutIcon bc-i-logout" aria-hidden="true"></i><span><?= htmlspecialchars(__('profile.logout'), ENT_QUOTES, 'UTF-8') ?></span></button>
    <?php else: ?>
    <a class="userLogoutBtn btn" href="/logout" data-nav-mode="page"><i class="userLogoutIcon bc-i-logout" aria-hidden="true"></i><span><?= htmlspecialchars(__('profile.logout'), ENT_QUOTES, 'UTF-8') ?></span></a>
    <?php endif; ?>
  </div>
  </div>
</div>
