<?php
/**
 * Profil sayfaları için ortak sidebar partial (görsel tasarıma uygun).
 * Kullanım: $username, $initial ve isteğe bağlı $profileActiveTab ile include edin.
 * $profileActiveTab: 'details' | 'deposit-withdraw-history' | 'bet-history' | 'casino-history' | 'bonus-spor' | 'bonus-casino' | 'bonus-history' | 'freespin' | 'loyalty-points' | 'deposit-withdraw' | 'deposit-bilgi' | 'withdraw' | 'withdraw-bilgi' | 'kyc' | 'references' | 'messages'
 */
$sidebar_username   = $username ?? $user_info['username'] ?? '';
$sidebar_initial    = $initial ?? (isset($user_info['username']) ? strtoupper(substr($user_info['username'], 0, 2)) : '');
if (strlen($sidebar_initial) < 2) {
    $sidebar_initial = strtoupper(substr($sidebar_username, 0, 2));
}
// Üst profil bloğunda yalnızca kullanıcı adı + ID gösterilir.
$sidebar_display_name = $sidebar_username;
$sidebar_user_id = $user_id ?? $_SESSION['user_id'] ?? (isset($user_info, $user_info['id']) ? $user_info['id'] : '');
$sidebar_loyalty = [
    'name' => 'Bronze',
    'code' => 'bronze',
    'initial' => 'B',
    'points' => 0,
    'redeemable_points' => 0,
    'progress_percent' => 0,
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
$balance_open    = in_array($active_tab, ['deposit-withdraw', 'deposit-bilgi', 'withdraw', 'withdraw-bilgi', 'deposit-withdraw-history', 'withdrawal-status'], true);
$bet_open        = in_array($active_tab, ['bet-history', 'casino-history'], true);
$promotions_open = in_array($active_tab, ['bonus-spor', 'bonus-casino', 'bonus-history', 'freespin', 'loyalty-points'], true);
$bonus_sub_tab = $bonusSubTab ?? null;
$messages_open   = $active_tab === 'messages';
$unread_count    = isset($message_unread_count) ? (int) $message_unread_count : 0;

$_profile_modal_q = !empty($profile_modal) ? 'modal=1&' : '';
$depositWithDrawDepositHref = '/profile/deposit-withdraw' . (!empty($profile_modal) ? '?modal=1' : '');
$depositWithDrawWithdrawHref = '/profile/withdraw' . (!empty($profile_modal) ? '?modal=1' : '');
$depositWithdrawHistoryHref = '/profile/deposit-withdraw-history' . (!empty($profile_modal) ? '?modal=1' : '');
$on_withdraw_balance = in_array($active_tab, ['withdraw', 'withdraw-bilgi'], true);
$depositInfoHref = $on_withdraw_balance
    ? ('/profile/withdraw?' . $_profile_modal_q . 'bilgi=1#bilgi')
    : ('/profile/deposit-withdraw?' . $_profile_modal_q . 'bilgi=1#bilgi');
$withdrawalStatusHref = '/profile/withdrawal-status' . (!empty($profile_modal) ? '?modal=1' : '');
$messagesInboxHref = '/profile/messages' . (!empty($profile_modal) ? '?modal=1' : '');
$messagesSentHref = '/profile/messages?box=sent' . (!empty($profile_modal) ? '&modal=1' : '');
$messagesNewHref = '/profile/messages?box=new' . (!empty($profile_modal) ? '&modal=1' : '');
$loyaltyPointsHref = '/profile/sadakat-puanlari' . (!empty($profile_modal) ? '?modal=1' : '');
?>
<aside id="profilePlayerSidebar" name="profilePlayerSidebar" class="sidebarMain playersidebarMain profile-sidebar-v2<?php echo $active_tab === 'details' ? ' profile-sidebar--hub' : ''; ?>">
    <div class="profile-content">
        <div class="profile-header edit-user">
            <span class="avatar-holder"><?php echo htmlspecialchars($sidebar_initial); ?></span>
            <div class="user-right">
                <span class="username"><?php echo htmlspecialchars($sidebar_display_name); ?></span>
                <?php if ($sidebar_user_id !== ''): ?>
                <span class="user-id" title="Kopyala" data-user-id="<?php echo htmlspecialchars((string)$sidebar_user_id); ?>">
                    ID: <?php echo htmlspecialchars((string)$sidebar_user_id); ?>
                    <i class="fa-regular fa-copy copy-id-icon" aria-hidden="true"></i>
                </span>
                <?php endif; ?>
            </div>
            <a class="profile-user-arrow bc-i-small-arrow-right" href="/profile/details<?= !empty($profile_modal) ? '?modal=1' : '' ?>" aria-label="Profile Details"></a>
        </div>
        <div class="main-balance main-balance-card">
            <span class="balance-label">ANA BAKİYE</span>
            <div class="total-balance">
                <span class="amount">0.00 ₺</span>
                <i class="fa-solid fa-eye eye-icon" aria-hidden="true"></i>
            </div>
            <span class="balance-watermark"><i class="fa-solid fa-dollar-sign"></i></span>
            <div class="buttons-bottom">
                <a class="btn deposit-bc" href="<?= htmlspecialchars($depositWithDrawDepositHref) ?>"><i class="fa-solid fa-wallet"></i> PARA YATIR</a>
                <a class="btn withdraw-bc" href="<?= htmlspecialchars($depositWithDrawWithdrawHref) ?>"><img alt="" src="/assets/images/withdraw-icon.png"> ÇEKİM</a>
            </div>
        </div>
        <div class="bonus-balance-card main-balance bonus">
            <span class="balance-label">TOPLAM BONUS PARA</span>
            <div class="user-balance">
                <span class="amount">0.00 ₺</span>
            </div>
            <div class="bonus-footer">
                <span class="balance-label">TOPLAM BONUS PARA</span>
                <span class="amount">0.00 ₺</span>
            </div>
            <span class="balance-watermark bonus"><i class="fa-solid fa-gift"></i></span>
        </div>
        <div class="loyalty-bar">
            <span class="loyalty-icon" data-loyalty-level-initial><?php echo htmlspecialchars((string) ($sidebar_loyalty['initial'] ?? 'B')); ?></span>
            <span class="loyalty-text">
                <span data-loyalty-level-name><?php echo htmlspecialchars((string) ($sidebar_loyalty['name'] ?? 'Bronze')); ?></span>
                <small data-loyalty-points><?php echo htmlspecialchars((string) ((int) ($sidebar_loyalty['points'] ?? 0))); ?> puan</small>
            </span>
        </div>

        <nav class="profile-mobile-nav" aria-label="Mobil profil menüsü">
            <a class="profile-mobile-nav__item <?php echo $profile_open ? 'is-active' : ''; ?>" href="/profile/details<?= !empty($profile_modal) ? '?modal=1' : '' ?>">
                <i class="user-nav-icon bc-i-user" aria-hidden="true"></i>
                <span>PROFİLİM</span>
                <i class="profile-mobile-nav__chevron bc-i-small-arrow-right" aria-hidden="true"></i>
            </a>
            <a class="profile-mobile-nav__item <?php echo $balance_open ? 'is-active' : ''; ?>" href="<?= htmlspecialchars($depositWithDrawDepositHref, ENT_QUOTES, 'UTF-8') ?>">
                <i class="user-nav-icon bc-i-balance-management" aria-hidden="true"></i>
                <span>BAKİYE YÖNETİMİ</span>
                <i class="profile-mobile-nav__chevron bc-i-small-arrow-right" aria-hidden="true"></i>
            </a>
            <a class="profile-mobile-nav__item <?php echo $bet_open ? 'is-active' : ''; ?>" href="/profile/bet-history<?= !empty($profile_modal) ? '?modal=1' : '' ?>">
                <i class="user-nav-icon bc-i-history" aria-hidden="true"></i>
                <span>BAHİS GEÇMİŞİ</span>
                <i class="profile-mobile-nav__chevron bc-i-small-arrow-right" aria-hidden="true"></i>
            </a>
            <a class="profile-mobile-nav__item <?php echo $promotions_open ? 'is-active' : ''; ?>" href="/profile/bonus-spor<?= !empty($profile_modal) ? '?modal=1' : '' ?>">
                <i class="user-nav-icon bc-i-promotion" aria-hidden="true"></i>
                <span>BONUSLAR</span>
                <i class="profile-mobile-nav__chevron bc-i-small-arrow-right" aria-hidden="true"></i>
            </a>
            <a class="profile-mobile-nav__item <?php echo $messages_open ? 'is-active' : ''; ?>" href="<?= htmlspecialchars($messagesInboxHref, ENT_QUOTES, 'UTF-8') ?>">
                <i class="user-nav-icon bc-i-message" aria-hidden="true"></i>
                <span>MESAJLAR</span>
                <?php if ($unread_count > 0): ?><b class="profile-mobile-nav__badge"><?php echo (int) $unread_count; ?></b><?php endif; ?>
                <i class="profile-mobile-nav__chevron bc-i-small-arrow-right" aria-hidden="true"></i>
            </a>
        </nav>

        <div class="profile-mobile-promo-lite" aria-label="Promo kodu">
            <input type="text" class="profile-mobile-promo-lite__input" placeholder="PROMOSYON KODU" autocomplete="off" maxlength="64">
            <button type="button" class="profile-mobile-promo-lite__btn" disabled>UYGULA</button>
        </div>
    </div>
    <div class="sideSports profile-accordion">
        <ul class="accordion-list">
            <li class="accordion-item <?php echo $profile_open ? 'open' : ''; ?>">
                <a class="accordion-trigger <?php echo $profile_open ? 'open' : ''; ?>" href="/profile/details" data-toggle-sub>
                    <span class="spCol"><i class="fa-solid fa-user"></i><span class="sportN">PROFİLİM</span></span>
                    <i class="fa-solid fa-chevron-down accordion-chevron"></i>
                </a>
                <ul class="accordion-sub">
                    <li><a class="<?php echo $active_tab === 'details' ? 'active' : ''; ?>" href="/profile/details">KİŞİSEL DETAYLAR</a></li>
                    <li><a class="<?php echo $active_tab === 'change-password' ? 'active' : ''; ?>" href="/profile/change-password">ŞİFRE DEĞİŞTİR</a></li>
                    <li><a class="<?php echo $active_tab === 'two-factor' ? 'active' : ''; ?>" href="/profile/two-factor">İKİ AŞAMALI KORUMA (2FA)</a></li>
                    <li><a class="<?php echo $active_tab === 'freeze-account' ? 'active' : ''; ?>" href="/profile/freeze-account">HESABI DONDUR</a></li>
                </ul>
            </li>
            <li class="accordion-item <?php echo $balance_open ? 'open' : ''; ?>">
                <a class="accordion-trigger <?php echo $balance_open ? 'open' : ''; ?>" href="<?= htmlspecialchars($depositWithDrawDepositHref) ?>" data-toggle-sub>
                    <span class="spCol"><i class="fa-solid fa-gear"></i><span class="sportN">BAKİYE YÖNETİMİ</span></span>
                    <i class="fa-solid fa-chevron-down accordion-chevron"></i>
                </a>
                <ul class="accordion-sub">
                    <li><a class="<?php echo $active_tab === 'deposit-withdraw' ? 'active' : ''; ?>" href="<?= htmlspecialchars($depositWithDrawDepositHref) ?>">PARA YATIR</a></li>
                    <li><a class="<?php echo $active_tab === 'withdraw' ? 'active' : ''; ?>" href="<?= htmlspecialchars($depositWithDrawWithdrawHref) ?>">ÇEKİM</a></li>
                    <li><a class="<?php echo $active_tab === 'deposit-withdraw-history' ? 'active' : ''; ?>" href="<?= htmlspecialchars($depositWithdrawHistoryHref) ?>">İŞLEM GEÇMİŞİ</a></li>
                    <li><a class="<?php echo in_array($active_tab, ['deposit-bilgi', 'withdraw-bilgi'], true) ? 'active' : ''; ?>" href="<?= htmlspecialchars($depositInfoHref) ?>">BİLGİ</a></li>
                    <li><a class="<?php echo $active_tab === 'withdrawal-status' ? 'active' : ''; ?>" href="<?= htmlspecialchars($withdrawalStatusHref) ?>">PARA ÇEKME DURUMU</a></li>
                </ul>
            </li>
            <li class="accordion-item <?php echo $bet_open ? 'open' : ''; ?>">
                <a class="accordion-trigger <?php echo $bet_open ? 'open' : ''; ?>" href="/profile/bet-history" data-toggle-sub>
                    <span class="spCol"><i class="fa-solid fa-clock-rotate-left"></i><span class="sportN">BAHİS GEÇMİŞİ</span></span>
                    <i class="fa-solid fa-chevron-down accordion-chevron"></i>
                </a>
                <ul class="accordion-sub">
                    <li><a class="<?php echo ($betHistoryFilter ?? '') === 'tumu' ? 'active' : ''; ?>" href="/profile/bet-history">TÜMÜ</a></li>
                    <li><a class="<?php echo ($betHistoryFilter ?? '') === 'acik' ? 'active' : ''; ?>" href="/profile/bet-history?filter=acik">AÇIK BAHİSLER</a></li>
                    <li><a class="<?php echo ($betHistoryFilter ?? '') === 'nakde' ? 'active' : ''; ?>" href="/profile/bet-history?filter=nakde">NAKDE ÇEVRİLDİ</a></li>
                    <li><a class="<?php echo ($betHistoryFilter ?? '') === 'kazanc' ? 'active' : ''; ?>" href="/profile/bet-history?filter=kazanc">KAZANÇ</a></li>
                    <li><a class="<?php echo ($betHistoryFilter ?? '') === 'kayip' ? 'active' : ''; ?>" href="/profile/bet-history?filter=kayip">KAYIP</a></li>
                    <li><a class="<?php echo ($betHistoryFilter ?? '') === 'iade' ? 'active' : ''; ?>" href="/profile/bet-history?filter=iade">İADE EDİLDİ</a></li>
                    <li><a class="<?php echo ($betHistoryFilter ?? '') === 'kazanan-iade' ? 'active' : ''; ?>" href="/profile/bet-history?filter=kazanan-iade">KAZANAN İADE</a></li>
                    <li><a class="<?php echo ($betHistoryFilter ?? '') === 'kayip-iade' ? 'active' : ''; ?>" href="/profile/bet-history?filter=kayip-iade">KAYIP-İADE</a></li>
                    <li><a class="<?php echo $active_tab === 'casino-history' ? 'active' : ''; ?>" href="/profile/casino-history">CASINO OYUN GEÇMİŞİ</a></li>
                </ul>
            </li>
            <li class="accordion-item <?php echo $promotions_open ? 'open' : ''; ?>">
                <a class="accordion-trigger <?php echo $promotions_open ? 'open' : ''; ?>" href="/profile/bonus-spor" data-toggle-sub>
                    <span class="spCol"><i class="fa-solid fa-th-large"></i><span class="sportN">BONUSLAR</span></span>
                    <i class="fa-solid fa-chevron-down accordion-chevron"></i>
                </a>
                <ul class="accordion-sub">
                    <li><a class="<?php echo $bonus_sub_tab === 'spor' ? 'active' : ''; ?>" href="/profile/bonus-spor">SPOR BONUSU</a></li>
                    <li><a class="<?php echo $bonus_sub_tab === 'casino' ? 'active' : ''; ?>" href="/profile/bonus-casino">CASİNO BONUSU</a></li>
                    <li><a class="<?php echo $active_tab === 'bonus-history' ? 'active' : ''; ?>" href="/profile/bonus-history">BONUS GEÇMİŞİ</a></li>
                    <li><a class="<?php echo $active_tab === 'freespin' ? 'active' : ''; ?>" href="/profile/freespin">CASİNO FREESPİNLERİ</a></li>
                    <li><a class="<?php echo $active_tab === 'loyalty-points' ? 'active' : ''; ?>" href="<?= htmlspecialchars($loyaltyPointsHref, ENT_QUOTES, 'UTF-8') ?>">Sadakat Puanları</a></li>
                </ul>
            </li>
            <li class="accordion-item <?php echo $messages_open ? 'open' : ''; ?>">
                <a class="accordion-trigger accordion-trigger--badge <?php echo $messages_open ? 'open' : ''; ?>" href="<?= htmlspecialchars($messagesInboxHref, ENT_QUOTES, 'UTF-8') ?>" data-toggle-sub>
                    <span class="spCol"><i class="fa-solid fa-envelope"></i><span class="sportN">MESAJLAR</span></span>
                    <span class="accordion-badge js-profile-inbox-unread"><?php echo $unread_count > 0 ? $unread_count : ''; ?></span>
                    <i class="fa-solid fa-chevron-down accordion-chevron"></i>
                </a>
                <ul class="accordion-sub">
                    <li><a class="<?php echo ($active_tab === 'messages' && ($messages_box ?? 'inbox') === 'inbox') ? 'active' : ''; ?>" href="<?= htmlspecialchars($messagesInboxHref, ENT_QUOTES, 'UTF-8') ?>">Gelen kutusu <span class="sub-badge js-profile-inbox-unread"><?php echo $unread_count > 0 ? $unread_count : ''; ?></span></a></li>
                    <li><a class="<?php echo ($messages_box ?? '') === 'sent' ? 'active' : ''; ?>" href="<?= htmlspecialchars($messagesSentHref, ENT_QUOTES, 'UTF-8') ?>">Gönderildi</a></li>
                    <li><a class="<?php echo ($messages_box ?? '') === 'new' ? 'active' : ''; ?>" href="<?= htmlspecialchars($messagesNewHref, ENT_QUOTES, 'UTF-8') ?>">YENİ</a></li>
                </ul>
            </li>
        </ul>
        <?php if (!empty($is_logged_in)): ?>
        <div class="profile-sidebar-promo" data-profile-promo-block>
            <span class="profile-promocodes-heading">Panel promo kodları</span>
            <div id="profilePromocodesStatus" class="profile-promocodes-status" aria-live="polite"></div>
            <div class="profile-sidebar-promo-row">
                <select id="profileModalPromoSelect" class="profile-sidebar-promo-select profile-promo-select" aria-label="Promo kodu seçin">
                    <option value="">Yükleniyor…</option>
                </select>
                <button type="button" id="profileModalPromoApply" class="profile-sidebar-promo-btn">TALEP ET</button>
            </div>
            <input type="text" id="profileModalPromoNote" class="profile-sidebar-promo-note" placeholder="Not (isteğe bağlı)" maxlength="500" autocomplete="off" aria-label="Talep notu">
            <span class="profile-promocodes-subheading">Site promosyon kodu</span>
            <div class="profile-sidebar-promo-row">
                <input type="text" id="profileModalPromoCode" class="profile-sidebar-promo-input" placeholder="KOD…" autocomplete="off" maxlength="64" aria-label="Promosyon kodu">
                <button type="button" id="profileModalPromoUseLegacy" class="profile-sidebar-promo-btn profile-sidebar-promo-btn--secondary">UYGULA</button>
            </div>
        </div>
        <?php endif; ?>
        <div class="sidebar-footer">
            <a class="sidebar-logout" href="/logout" data-nav-mode="page"><i class="bc-i-logout"></i> ÇIKIŞ YAP</a>
        </div>
    </div>
</aside>
