# VegasRoyalSpin — Mobile Design Analysis Report
**Generated:** 2026-07-24T23:48:10.932547

## Summary

| Metric | Value |
|--------|-------|
| Total files scanned | 244 |
| HTML/PHP files | 149 |
| CSS files | 50 |
| JS files | 45 |
| **Total issues** | **170** |
| Fixable issues | 4 |

### By Severity
- 🟠 **HIGH**: 1
- 🟡 **MEDIUM**: 88
- 🔵 **LOW**: 81

### By Category
- **accessibility**: 32
- **css**: 39
- **html**: 5
- **js**: 43
- **mobile**: 40
- **php**: 11

## All Issues

### 🟠 [HIGH] DUPLICATE_MENU_LOGIC
**File:** `mobile/assets/js/navigation.js + assets/js/mobile_bottom.js`
**Category:** js
**Message:** mobile_bottom.js AND navigation.js both handle mobile menu toggles — duplicate logic, potential conflicts
🔧 **Auto-fix:** Consolidate menu logic into one file, remove the other's menu handling

### 🟡 [MEDIUM] MISSING_ALT
**File:** `mobile/views/partials/header.php`
**Category:** accessibility
**Message:** 4 <img> tags missing alt attribute

### 🟡 [MEDIUM] MISSING_ALT
**File:** `mobile/views/partials/mobile-footer-bc.php`
**Category:** accessibility
**Message:** 3 <img> tags missing alt attribute

### 🟡 [MEDIUM] MISSING_ALT
**File:** `mobile/views/partials/profile-panel.php`
**Category:** accessibility
**Message:** 2 <img> tags missing alt attribute

### 🟡 [MEDIUM] MISSING_ALT
**File:** `pages/games/games.php`
**Category:** accessibility
**Message:** 1 <img> tags missing alt attribute

### 🟡 [MEDIUM] MISSING_ALT
**File:** `pages/games/hepsi.php`
**Category:** accessibility
**Message:** 1 <img> tags missing alt attribute

### 🟡 [MEDIUM] MISSING_ALT
**File:** `pages/games/livecasino/games.php`
**Category:** accessibility
**Message:** 1 <img> tags missing alt attribute

### 🟡 [MEDIUM] MISSING_ALT
**File:** `pages/legacy/slot.php`
**Category:** accessibility
**Message:** 1 <img> tags missing alt attribute

### 🟡 [MEDIUM] NON_SEMANTIC_CLICK
**File:** `pages/legacy/slot.php`
**Line:** 205
**Category:** accessibility
**Message:** Non-semantic clickable element without ARIA role at line 205
```
<span class="remove" onclick='removeFilter(<?= json_encode($provider, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON
```
🔧 **Auto-fix:** Add role="button" and tabindex="0" to the element

### 🟡 [MEDIUM] MISSING_ALT
**File:** `pages/play.php`
**Category:** accessibility
**Message:** 1 <img> tags missing alt attribute

### 🟡 [MEDIUM] MISSING_ALT
**File:** `pages/profile/freespin.php`
**Category:** accessibility
**Message:** 1 <img> tags missing alt attribute

### 🟡 [MEDIUM] MISSING_ALT
**File:** `pages/promosyonlar.php`
**Category:** accessibility
**Message:** 1 <img> tags missing alt attribute

### 🟡 [MEDIUM] NON_SEMANTIC_CLICK
**File:** `pages/slot.php`
**Line:** 363
**Category:** accessibility
**Message:** Non-semantic clickable element without ARIA role at line 363
```
<span class="remove" onclick='removeFilter(<?= json_encode($provider, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON
```
🔧 **Auto-fix:** Add role="button" and tabindex="0" to the element

### 🟡 [MEDIUM] MISSING_ALT
**File:** `views/partials/auth-slider-bg.php`
**Category:** accessibility
**Message:** 1 <img> tags missing alt attribute

### 🟡 [MEDIUM] MISSING_ALT
**File:** `views/partials/footer-bc-about.php`
**Category:** accessibility
**Message:** 1 <img> tags missing alt attribute

### 🟡 [MEDIUM] MISSING_ALT
**File:** `views/partials/footer-bc.php`
**Category:** accessibility
**Message:** 3 <img> tags missing alt attribute

### 🟡 [MEDIUM] MISSING_ALT
**File:** `views/partials/footer-content.php`
**Category:** accessibility
**Message:** 1 <img> tags missing alt attribute

### 🟡 [MEDIUM] MISSING_ALT
**File:** `views/partials/header.php`
**Category:** accessibility
**Message:** 3 <img> tags missing alt attribute

### 🟡 [MEDIUM] MISSING_ALT
**File:** `views/partials/login.php`
**Category:** accessibility
**Message:** 1 <img> tags missing alt attribute

### 🟡 [MEDIUM] MISSING_ALT
**File:** `views/partials/main-content.php`
**Category:** accessibility
**Message:** 3 <img> tags missing alt attribute

### 🟡 [MEDIUM] MISSING_ALT
**File:** `views/partials/mobile_bottom.php`
**Category:** accessibility
**Message:** 1 <img> tags missing alt attribute

### 🟡 [MEDIUM] MISSING_ALT
**File:** `views/partials/register.php`
**Category:** accessibility
**Message:** 1 <img> tags missing alt attribute

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `assets/css/bc-mobile-custom.css`
**Category:** css
**Message:** 196 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `assets/css/bc-mobile-header-original.css`
**Category:** css
**Message:** 165 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `assets/css/bc-mobile-index.css`
**Category:** css
**Message:** 26 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `assets/css/bc-mobile-maltabet.css`
**Category:** css
**Message:** 32 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `assets/css/bootstrap-utils.css`
**Category:** css
**Message:** 40 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `assets/css/components.css`
**Category:** css
**Message:** 43 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `assets/css/footer-bc.css`
**Category:** css
**Message:** 55 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `assets/css/footer.css`
**Category:** css
**Message:** 17 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `assets/css/global.css`
**Category:** css
**Message:** 17 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `assets/css/header.css`
**Category:** css
**Message:** 1156 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `assets/css/login-modal.css`
**Category:** css
**Message:** 42 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `assets/css/login.css`
**Category:** css
**Message:** 157 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] DUPLICATE_CSS_FILE
**File:** `assets/css/mobile-right-sheet.css, assets/js/mobile-right-sheet.js, mobile/assets/css/mobile-right-sheet.css`
**Category:** css
**Message:** mobile-right-sheet.css exists in 3 locations — potential style conflicts
🔧 **Auto-fix:** Merge into one file, delete the duplicate

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `assets/css/mobile-smart-panel.css`
**Category:** css
**Message:** 49 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `assets/css/modal.css`
**Category:** css
**Message:** 11 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `assets/css/profile.css`
**Category:** css
**Message:** 84 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `assets/css/register.css`
**Category:** css
**Message:** 212 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `assets/css/reset-password.css`
**Category:** css
**Message:** 11 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `assets/css/slider-mobile-bc.css`
**Category:** css
**Message:** 78 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `assets/css/slider.css`
**Category:** css
**Message:** 54 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `assets/css/winners.css`
**Category:** css
**Message:** 24 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `mobile/assets/css/auth-modals.css`
**Category:** css
**Message:** 439 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `mobile/assets/css/base.css`
**Category:** css
**Message:** 28 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `mobile/assets/css/betslip-mobile.css`
**Category:** css
**Message:** 11 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `mobile/assets/css/footer.css`
**Category:** css
**Message:** 29 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `mobile/assets/css/header.css`
**Category:** css
**Message:** 13 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `mobile/assets/css/home-widgets.css`
**Category:** css
**Message:** 17 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `mobile/assets/css/home.css`
**Category:** css
**Message:** 21 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `mobile/assets/css/menu.css`
**Category:** css
**Message:** 40 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `mobile/assets/css/mobile-right-sheet.css`
**Category:** css
**Message:** 49 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `mobile/assets/css/profile-panel.css`
**Category:** css
**Message:** 254 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] IMPORTANT_ABUSE
**File:** `mobile/assets/css/slots.css`
**Category:** css
**Message:** 59 !important declarations — indicates specificity wars

### 🟡 [MEDIUM] HARDCODED_API_URL
**File:** `controllers/Api/ApiTrackVisitController.php`
**Category:** js
**Message:** 1 hardcoded API URLs — use config/constants instead

### 🟡 [MEDIUM] HARDCODED_API_URL
**File:** `mobile/views/layouts/head.php`
**Category:** js
**Message:** 1 hardcoded API URLs — use config/constants instead

### 🟡 [MEDIUM] LARGE_INLINE_SCRIPT
**File:** `mobile/views/partials/profile-panel.php`
**Category:** js
**Message:** Large inline script (16531 chars) — extract to external .js file for caching

### 🟡 [MEDIUM] LARGE_INLINE_SCRIPT
**File:** `pages/bonustalep/index.php`
**Category:** js
**Message:** Large inline script (8791 chars) — extract to external .js file for caching

### 🟡 [MEDIUM] LARGE_INLINE_SCRIPT
**File:** `pages/games/hepsi.php`
**Category:** js
**Message:** Large inline script (3452 chars) — extract to external .js file for caching

### 🟡 [MEDIUM] LARGE_INLINE_SCRIPT
**File:** `pages/mobile/profile.php`
**Category:** js
**Message:** Large inline script (5219 chars) — extract to external .js file for caching

### 🟡 [MEDIUM] LARGE_INLINE_SCRIPT
**File:** `pages/promotions.php`
**Category:** js
**Message:** Large inline script (5670 chars) — extract to external .js file for caching

### 🟡 [MEDIUM] LARGE_INLINE_SCRIPT
**File:** `pages/sportbook.php`
**Category:** js
**Message:** Large inline script (2467 chars) — extract to external .js file for caching

### 🟡 [MEDIUM] HARDCODED_API_URL
**File:** `views/layouts/head.php`
**Category:** js
**Message:** 1 hardcoded API URLs — use config/constants instead

### 🟡 [MEDIUM] HARDCODED_API_URL
**File:** `views/layouts/head_full.php`
**Category:** js
**Message:** 1 hardcoded API URLs — use config/constants instead

### 🟡 [MEDIUM] LARGE_INLINE_SCRIPT
**File:** `views/partials/login.php`
**Category:** js
**Message:** Large inline script (3537 chars) — extract to external .js file for caching

### 🟡 [MEDIUM] HARDCODED_API_URL
**File:** `views/partials/member-api-layout-script.php`
**Category:** js
**Message:** 1 hardcoded API URLs — use config/constants instead

### 🟡 [MEDIUM] LARGE_INLINE_SCRIPT
**File:** `views/partials/member-api-layout-script.php`
**Category:** js
**Message:** Large inline script (2748 chars) — extract to external .js file for caching

### 🟡 [MEDIUM] LARGE_INLINE_SCRIPT
**File:** `views/partials/register.php`
**Category:** js
**Message:** Large inline script (12834 chars) — extract to external .js file for caching

### 🟡 [MEDIUM] NO_TOUCH_EVENTS
**File:** `assets/js/mobile-right-sheet.js`
**Category:** mobile
**Message:** Mobile JS uses click but no touch events — may have 300ms delay on iOS

### 🟡 [MEDIUM] NO_TOUCH_EVENTS
**File:** `assets/js/slider-mobile-bc.js`
**Category:** mobile
**Message:** Mobile JS uses click but no touch events — may have 300ms delay on iOS

### 🟡 [MEDIUM] NO_TOUCH_EVENTS
**File:** `mobile/assets/js/betslip-mobile.js`
**Category:** mobile
**Message:** Mobile JS uses click but no touch events — may have 300ms delay on iOS

### 🟡 [MEDIUM] NO_TOUCH_EVENTS
**File:** `mobile/assets/js/profile-panel.js`
**Category:** mobile
**Message:** Mobile JS uses click but no touch events — may have 300ms delay on iOS

### 🟡 [MEDIUM] NO_TOUCH_EVENTS
**File:** `mobile/views/partials/footer.php`
**Category:** mobile
**Message:** Mobile JS uses click but no touch events — may have 300ms delay on iOS

### 🟡 [MEDIUM] NO_TOUCH_EVENTS
**File:** `mobile/views/partials/header.php`
**Category:** mobile
**Message:** Mobile JS uses click but no touch events — may have 300ms delay on iOS

### 🟡 [MEDIUM] NO_TOUCH_EVENTS
**File:** `mobile/views/partials/profile-panel.php`
**Category:** mobile
**Message:** Mobile JS uses click but no touch events — may have 300ms delay on iOS

### 🟡 [MEDIUM] NO_TOUCH_EVENTS
**File:** `pages/mobile/profile.php`
**Category:** mobile
**Message:** Mobile JS uses click but no touch events — may have 300ms delay on iOS

### 🟡 [MEDIUM] NO_TOUCH_EVENTS
**File:** `views/partials/mobile-hdr-crypto.php`
**Category:** mobile
**Message:** Mobile JS uses click but no touch events — may have 300ms delay on iOS

### 🟡 [MEDIUM] NO_TOUCH_EVENTS
**File:** `views/partials/mobile-hdr-dynamic.php`
**Category:** mobile
**Message:** Mobile JS uses click but no touch events — may have 300ms delay on iOS

### 🟡 [MEDIUM] CDN_NO_FALLBACK
**File:** `mobile/views/partials/layout-after-header.php`
**Category:** php
**Message:** CDN resource loaded without local fallback: https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css

### 🟡 [MEDIUM] CDN_NO_FALLBACK
**File:** `pages/play.php`
**Category:** php
**Message:** CDN resource loaded without local fallback: https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css

### 🟡 [MEDIUM] CDN_NO_FALLBACK
**File:** `pages/profile/bet-history.php`
**Category:** php
**Message:** CDN resource loaded without local fallback: https://cdn.jsdelivr.net/npm/izitoast/dist/css/iziToast.min.css

### 🟡 [MEDIUM] CDN_NO_FALLBACK
**File:** `pages/profile/deposit-withdraw-history.php`
**Category:** php
**Message:** CDN resource loaded without local fallback: https://cdn.jsdelivr.net/npm/izitoast/dist/css/iziToast.min.css

### 🟡 [MEDIUM] CDN_NO_FALLBACK
**File:** `views/layouts/head.php`
**Category:** php
**Message:** CDN resource loaded without local fallback: https://cdn.jsdelivr.net/npm/izitoast/dist/css/iziToast.min.css

### 🟡 [MEDIUM] HEAD_OUT_OF_SYNC
**File:** `views/layouts/head.php vs mobile/views/layouts/head.php`
**Category:** php
**Message:** Desktop and mobile head.php load different CSS sets. Desktop-only: 46, Mobile-only: 41

### 🟡 [MEDIUM] CDN_NO_FALLBACK
**File:** `views/layouts/head_full.php`
**Category:** php
**Message:** CDN resource loaded without local fallback: https://cdn.jsdelivr.net/npm/izitoast/dist/css/iziToast.min.css

### 🟡 [MEDIUM] CDN_NO_FALLBACK
**File:** `views/layouts/profile_modal_head.php`
**Category:** php
**Message:** CDN resource loaded without local fallback: https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css

### 🟡 [MEDIUM] CDN_NO_FALLBACK
**File:** `views/partials/layout-after-header.php`
**Category:** php
**Message:** CDN resource loaded without local fallback: https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css

### 🟡 [MEDIUM] CDN_NO_FALLBACK
**File:** `views/partials/mobile_bottom.php`
**Category:** php
**Message:** CDN resource loaded without local fallback: https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css

### 🟡 [MEDIUM] CDN_NO_FALLBACK
**File:** `views/partials/profile-page-frame-open.php`
**Category:** php
**Message:** CDN resource loaded without local fallback: https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css

### 🔵 [LOW] PX_FONT_SIZE
**File:** `assets/css/bc-mobile-index.css`
**Category:** accessibility
**Message:** 384 font-size declarations use px instead of rem — harms text scaling

### 🔵 [LOW] PX_FONT_SIZE
**File:** `assets/css/footer-bc.css`
**Category:** accessibility
**Message:** 21 font-size declarations use px instead of rem — harms text scaling

### 🔵 [LOW] PX_FONT_SIZE
**File:** `assets/css/header.css`
**Category:** accessibility
**Message:** 135 font-size declarations use px instead of rem — harms text scaling

### 🔵 [LOW] PX_FONT_SIZE
**File:** `assets/css/login-modal.css`
**Category:** accessibility
**Message:** 21 font-size declarations use px instead of rem — harms text scaling

### 🔵 [LOW] PX_FONT_SIZE
**File:** `assets/css/login.css`
**Category:** accessibility
**Message:** 31 font-size declarations use px instead of rem — harms text scaling

### 🔵 [LOW] PX_FONT_SIZE
**File:** `assets/css/profile.css`
**Category:** accessibility
**Message:** 61 font-size declarations use px instead of rem — harms text scaling

### 🔵 [LOW] PX_FONT_SIZE
**File:** `assets/css/register.css`
**Category:** accessibility
**Message:** 51 font-size declarations use px instead of rem — harms text scaling

### 🔵 [LOW] PX_FONT_SIZE
**File:** `assets/css/slots.css`
**Category:** accessibility
**Message:** 58 font-size declarations use px instead of rem — harms text scaling

### 🔵 [LOW] PX_FONT_SIZE
**File:** `mobile/assets/css/auth-modals.css`
**Category:** accessibility
**Message:** 35 font-size declarations use px instead of rem — harms text scaling

### 🔵 [LOW] PX_FONT_SIZE
**File:** `mobile/assets/css/profile-panel.css`
**Category:** accessibility
**Message:** 124 font-size declarations use px instead of rem — harms text scaling

### 🔵 [LOW] PX_FONT_SIZE
**File:** `mobile/assets/css/slots.css`
**Category:** accessibility
**Message:** 49 font-size declarations use px instead of rem — harms text scaling

### 🔵 [LOW] DUPLICATE_SELECTORS
**File:** `assets/css/bc-mobile-index.css`
**Category:** css
**Message:** 23 duplicate CSS selectors in this file

### 🔵 [LOW] DUPLICATE_SELECTORS
**File:** `assets/css/footer-bc.css`
**Category:** css
**Message:** 3 duplicate CSS selectors in this file

### 🔵 [LOW] DUPLICATE_SELECTORS
**File:** `assets/css/footer.css`
**Category:** css
**Message:** 4 duplicate CSS selectors in this file

### 🔵 [LOW] DUPLICATE_SELECTORS
**File:** `assets/css/header.css`
**Category:** css
**Message:** 5 duplicate CSS selectors in this file

### 🔵 [LOW] DUPLICATE_SELECTORS
**File:** `assets/css/home.css`
**Category:** css
**Message:** 7 duplicate CSS selectors in this file

### 🔵 [LOW] DUPLICATE_SELECTORS
**File:** `assets/css/profile.css`
**Category:** css
**Message:** 6 duplicate CSS selectors in this file

### 🔵 [LOW] DUPLICATE_SELECTORS
**File:** `mobile/assets/css/slots.css`
**Category:** css
**Message:** 4 duplicate CSS selectors in this file

### 🔵 [LOW] INLINE_STYLES
**File:** `pages/slot.php`
**Category:** html
**Message:** 5 inline style attributes found — extract to CSS classes

### 🔵 [LOW] INLINE_STYLES
**File:** `views/pages/slot.php`
**Category:** html
**Message:** 7 inline style attributes found — extract to CSS classes

### 🔵 [LOW] INLINE_STYLES
**File:** `views/partials/footer-bc-about.php`
**Category:** html
**Message:** 6 inline style attributes found — extract to CSS classes

### 🔵 [LOW] INLINE_STYLES
**File:** `views/partials/footer-site-chrome.php`
**Category:** html
**Message:** 4 inline style attributes found — extract to CSS classes

### 🔵 [LOW] INLINE_STYLES
**File:** `views/partials/register.php`
**Category:** html
**Message:** 16 inline style attributes found — extract to CSS classes

### 🔵 [LOW] TIMER_NO_CLEANUP
**File:** `assets/js/auth-shared.js`
**Category:** js
**Message:** 1 timers created but only 0 cleared — potential memory leaks

### 🔵 [LOW] TIMER_NO_CLEANUP
**File:** `assets/js/footer-bc.js`
**Category:** js
**Message:** 1 timers created but only 0 cleared — potential memory leaks

### 🔵 [LOW] MANY_LISTENERS
**File:** `assets/js/footer.js`
**Category:** js
**Message:** "click" event bound 28 times — potential memory/performance issue on mobile

### 🔵 [LOW] TIMER_NO_CLEANUP
**File:** `assets/js/footer.js`
**Category:** js
**Message:** 1 timers created but only 0 cleared — potential memory leaks

### 🔵 [LOW] TIMER_NO_CLEANUP
**File:** `assets/js/header-balance-poll.js`
**Category:** js
**Message:** 2 timers created but only 0 cleared — potential memory leaks

### 🔵 [LOW] MANY_LISTENERS
**File:** `assets/js/header.js`
**Category:** js
**Message:** "click" event bound 15 times — potential memory/performance issue on mobile

### 🔵 [LOW] TIMER_NO_CLEANUP
**File:** `assets/js/home.js`
**Category:** js
**Message:** 2 timers created but only 0 cleared — potential memory leaks

### 🔵 [LOW] TIMER_NO_CLEANUP
**File:** `assets/js/jackpot.js`
**Category:** js
**Message:** 1 timers created but only 0 cleared — potential memory leaks

### 🔵 [LOW] TIMER_NO_CLEANUP
**File:** `assets/js/login.js`
**Category:** js
**Message:** 7 timers created but only 0 cleared — potential memory leaks

### 🔵 [LOW] TIMER_NO_CLEANUP
**File:** `assets/js/play-page.js`
**Category:** js
**Message:** 4 timers created but only 3 cleared — potential memory leaks

### 🔵 [LOW] MANY_LISTENERS
**File:** `assets/js/profile.js`
**Category:** js
**Message:** "click" event bound 33 times — potential memory/performance issue on mobile

### 🔵 [LOW] MANY_LISTENERS
**File:** `assets/js/profile.js`
**Category:** js
**Message:** "change" event bound 11 times — potential memory/performance issue on mobile

### 🔵 [LOW] TIMER_NO_CLEANUP
**File:** `assets/js/profile.js`
**Category:** js
**Message:** 10 timers created but only 3 cleared — potential memory leaks

### 🔵 [LOW] TIMER_NO_CLEANUP
**File:** `assets/js/promosyonlar.js`
**Category:** js
**Message:** 2 timers created but only 0 cleared — potential memory leaks

### 🔵 [LOW] MANY_LISTENERS
**File:** `assets/js/register.js`
**Category:** js
**Message:** "click" event bound 19 times — potential memory/performance issue on mobile

### 🔵 [LOW] TIMER_NO_CLEANUP
**File:** `assets/js/register.js`
**Category:** js
**Message:** 4 timers created but only 0 cleared — potential memory leaks

### 🔵 [LOW] TIMER_NO_CLEANUP
**File:** `assets/js/reset-password.js`
**Category:** js
**Message:** 1 timers created but only 0 cleared — potential memory leaks

### 🔵 [LOW] TIMER_NO_CLEANUP
**File:** `assets/js/session-heartbeat.js`
**Category:** js
**Message:** 4 timers created but only 0 cleared — potential memory leaks

### 🔵 [LOW] TIMER_NO_CLEANUP
**File:** `assets/js/slider.js`
**Category:** js
**Message:** 3 timers created but only 2 cleared — potential memory leaks

### 🔵 [LOW] MANY_LISTENERS
**File:** `assets/js/slot.js`
**Category:** js
**Message:** "click" event bound 24 times — potential memory/performance issue on mobile

### 🔵 [LOW] TIMER_NO_CLEANUP
**File:** `assets/js/toastify-helper.js`
**Category:** js
**Message:** 2 timers created but only 0 cleared — potential memory leaks

### 🔵 [LOW] TIMER_NO_CLEANUP
**File:** `mobile/assets/js/mobile-header.js`
**Category:** js
**Message:** 1 timers created but only 0 cleared — potential memory leaks

### 🔵 [LOW] TIMER_NO_CLEANUP
**File:** `mobile/assets/js/navigation.js`
**Category:** js
**Message:** 1 timers created but only 0 cleared — potential memory leaks

### 🔵 [LOW] TIMER_NO_CLEANUP
**File:** `mobile/assets/js/profile-panel.js`
**Category:** js
**Message:** 4 timers created but only 1 cleared — potential memory leaks

### 🔵 [LOW] TIMER_NO_CLEANUP
**File:** `mobile/views/partials/profile-panel.php`
**Category:** js
**Message:** 1 timers created but only 0 cleared — potential memory leaks

### 🔵 [LOW] TIMER_NO_CLEANUP
**File:** `pages/promotions.php`
**Category:** js
**Message:** 2 timers created but only 0 cleared — potential memory leaks

### 🔵 [LOW] TIMER_NO_CLEANUP
**File:** `pages/sportbook.php`
**Category:** js
**Message:** 2 timers created but only 0 cleared — potential memory leaks

### 🔵 [LOW] TIMER_NO_CLEANUP
**File:** `views/partials/register.php`
**Category:** js
**Message:** 9 timers created but only 0 cleared — potential memory leaks

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `index.php`
**Line:** 30
**Category:** mobile
**Message:** Hardcoded width 680px may overflow on mobile (max 360-414px viewport)
```
echo '<!doctype html><meta charset="utf-8"><div style="font-family:sans-serif;max-width:680px;margin:40px auto;padding:0
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/bonustalep/index.php`
**Line:** 359
**Category:** mobile
**Message:** Hardcoded width 1180px may overflow on mobile (max 360-414px viewport)
```
max-width: 1180px;
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/bonustalep/index.php`
**Line:** 572
**Category:** mobile
**Message:** Hardcoded width 520px may overflow on mobile (max 360-414px viewport)
```
min-width: 520px;
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/bonustalep/index.php`
**Line:** 646
**Category:** mobile
**Message:** Hardcoded width 1100px may overflow on mobile (max 360-414px viewport)
```
@media (max-width: 1100px) {
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/bonustalep/index.php`
**Line:** 652
**Category:** mobile
**Message:** Hardcoded width 768px may overflow on mobile (max 360-414px viewport)
```
@media (max-width: 768px) {
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/casino/casino.php`
**Line:** 133
**Category:** mobile
**Message:** Hardcoded width 768px may overflow on mobile (max 360-414px viewport)
```
@media (max-width: 768px) {
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/games/games.php`
**Line:** 55
**Category:** mobile
**Message:** Hardcoded width 768px may overflow on mobile (max 360-414px viewport)
```
@media (max-width: 768px) { .game-grid { grid-template-columns: repeat(3, 1fr); } .btn { width: 100%; padding: 15px; } }
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/games/games.php`
**Line:** 56
**Category:** mobile
**Message:** Hardcoded width 769px may overflow on mobile (max 360-414px viewport)
```
@media (min-width: 769px) { .btn { width: auto; } }
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/games/hepsi.php`
**Line:** 43
**Category:** mobile
**Message:** Hardcoded width 600px may overflow on mobile (max 360-414px viewport)
```
.search-bar { margin: 20px auto; text-align: center; position: relative; max-width: 600px; }
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/games/hepsi.php`
**Line:** 55
**Category:** mobile
**Message:** Hardcoded width 768px may overflow on mobile (max 360-414px viewport)
```
@media (max-width: 768px) { .game-grid { grid-template-columns: repeat(3, 1fr); } .btn { width: 100%; padding: 15px; } }
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/games/livecasino/games.php`
**Line:** 88
**Category:** mobile
**Message:** Hardcoded width 768px may overflow on mobile (max 360-414px viewport)
```
@media (max-width: 768px) {
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/games/livecasino/games.php`
**Line:** 99
**Category:** mobile
**Message:** Hardcoded width 769px may overflow on mobile (max 360-414px viewport)
```
@media (min-width: 769px) {
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/play.php`
**Line:** 201
**Category:** mobile
**Message:** Hardcoded width 992px may overflow on mobile (max 360-414px viewport)
```
@media (min-width: 992px) {
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/promotions.php`
**Line:** 582
**Category:** mobile
**Message:** Hardcoded width 991px may overflow on mobile (max 360-414px viewport)
```
@media (max-width: 991px) {
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/promotions.php`
**Line:** 669
**Category:** mobile
**Message:** Hardcoded width 991px may overflow on mobile (max 360-414px viewport)
```
@media (max-width: 991px) {
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/promotions.php`
**Line:** 765
**Category:** mobile
**Message:** Hardcoded width 991px may overflow on mobile (max 360-414px viewport)
```
@media (hover: none), (pointer: coarse), (max-width: 991px) {
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/promotions.php`
**Line:** 929
**Category:** mobile
**Message:** Hardcoded width 400px may overflow on mobile (max 360-414px viewport)
```
max-width: 400px;
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/promotions.php`
**Line:** 952
**Category:** mobile
**Message:** Hardcoded width 1200px may overflow on mobile (max 360-414px viewport)
```
@media (min-width: 1200px) {
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/promotions.php`
**Line:** 964
**Category:** mobile
**Message:** Hardcoded width 992px may overflow on mobile (max 360-414px viewport)
```
@media (min-width: 992px) and (max-width: 1199px) {
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/promotions.php`
**Line:** 964
**Category:** mobile
**Message:** Hardcoded width 1199px may overflow on mobile (max 360-414px viewport)
```
@media (min-width: 992px) and (max-width: 1199px) {
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/promotions.php`
**Line:** 972
**Category:** mobile
**Message:** Hardcoded width 991px may overflow on mobile (max 360-414px viewport)
```
@media (max-width: 991px) {
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/promotions.php`
**Line:** 980
**Category:** mobile
**Message:** Hardcoded width 767px may overflow on mobile (max 360-414px viewport)
```
@media (max-width: 767px) {
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/promotions.php`
**Line:** 992
**Category:** mobile
**Message:** Hardcoded width 480px may overflow on mobile (max 360-414px viewport)
```
/* Küçük mobil ekranlar (max-width: 480px) */
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/promotions.php`
**Line:** 993
**Category:** mobile
**Message:** Hardcoded width 480px may overflow on mobile (max 360-414px viewport)
```
@media (max-width: 480px) {
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/sportbook.php`
**Line:** 23
**Category:** mobile
**Message:** Hardcoded width 900px may overflow on mobile (max 360-414px viewport)
```
@media (max-width: 900px) { .sportbook-stage { height: calc(100vh - 132px - 72px - env(safe-area-inset-bottom)); min-hei
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `pages/sportbook.php`
**Line:** 29
**Category:** mobile
**Message:** Hardcoded width 460px may overflow on mobile (max 360-414px viewport)
```
.sportbook-error-box { max-width: 460px; background: #1a0a2e; border: 1px solid rgba(104,9,76,.55); border-radius: 14px;
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `views/pages/game-launch-error.php`
**Line:** 9
**Category:** mobile
**Message:** Hardcoded width 500px may overflow on mobile (max 360-414px viewport)
```
.error-box { background: #111; border: 1px solid #856A00; padding: 30px 20px; max-width: 500px; width: 90%; margin: 0 au
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `views/pages/game-launch-error.php`
**Line:** 19
**Category:** mobile
**Message:** Hardcoded width 480px may overflow on mobile (max 360-414px viewport)
```
@media (max-width: 480px) { .error-box { padding: 20px 15px; } .button-group { flex-direction: column; } button { width:
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `views/partials/login.php`
**Line:** 155
**Category:** mobile
**Message:** Hardcoded width 520px may overflow on mobile (max 360-414px viewport)
```
max-width: 520px !important;
```

### 🔵 [LOW] HARDCODED_WIDTH_PX
**File:** `views/partials/register.php`
**Line:** 830
**Category:** mobile
**Message:** Hardcoded width 480px may overflow on mobile (max 360-414px viewport)
```
@media (max-width: 480px) {
```

## Recommendations


### 📱 Mobile-Specific Recommendations

1. **Consolidate duplicate menu logic** — `mobile_bottom.js` and `navigation.js` both handle menus
2. **Add passive touch listeners** — improves scroll performance on iOS/Android
3. **Add `-webkit-overflow-scrolling: touch`** — enables momentum scrolling on iOS
4. **Ensure viewport meta is present** — critical for proper mobile rendering
5. **Extract large inline scripts** — improves caching and reduces page weight

### ♿ Accessibility Recommendations

1. Add `alt` attributes to all `<img>` tags
2. Add `role="button"` and `tabindex="0"` to clickable divs/spans
3. Use `rem` instead of `px` for font sizes (respects user text scaling)
4. Add `lang="tr"` to `<html>` tag

### ⚡ Performance Recommendations

1. Add local fallbacks for CDN resources (Toastr, Toastify)
2. Clean up `console.log` statements before production
3. Clean up timer references (setTimeout/setInterval)
4. Remove merge conflict backup files