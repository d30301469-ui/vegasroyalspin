<?php
/**
 * Gömülü LiveChat widget'ı (live support).
 * Yapılandırma admin -> site_ayarlar (live_chat_enabled / live_chat_license)
 * üzerinden gelir; lisans boşsa live_support_url'den çıkarılır.
 *
 * Hem web hem mobil footer akışında, sayfa başına bir kez render edilir.
 */
if (defined('LIVE_CHAT_RENDERED')) {
    return;
}
define('LIVE_CHAT_RENDERED', true);

$lcSource = is_array($siteSettings ?? null) ? $siteSettings : (is_array($ayar ?? null) ? $ayar : []);
$lcConfig = class_exists('ApiSiteSettings')
    ? ApiSiteSettings::liveChatConfig($lcSource)
    : ['enabled' => false, 'license' => ''];

$lcLicense = preg_replace('/\D+/', '', (string) ($lcConfig['license'] ?? '')) ?? '';
if (empty($lcConfig['enabled']) || $lcLicense === '') {
    return;
}
?>
<!-- LiveChat (live support) -->
<script>
window.__lc = window.__lc || {};
window.__lc.license = <?= (int) $lcLicense ?>;
window.__lc.integration_name = "manual_onboarding";
window.__lc.product_name = "livechat";
;(function(n,t,c){function i(n){return e._h?e._h.apply(null,n):e._q.push(n)}var e={_q:[],_h:null,_v:"2.0",on:function(){i(["on",c.call(arguments)])},once:function(){i(["once",c.call(arguments)])},off:function(){i(["off",c.call(arguments)])},get:function(){if(!e._h)throw new Error("[LiveChatWidget] You can't use getters before load.");return i(["get",c.call(arguments)])},call:function(){i(["call",c.call(arguments)])},init:function(){var n=t.createElement("script");n.async=!0,n.type="text/javascript",n.src="https://cdn.livechatinc.com/tracking.js",t.head.appendChild(n)}};!n.__lc.asyncInit&&e.init(),n.LiveChatWidget=n.LiveChatWidget||e}(window,document,[].slice));
</script>
<noscript><a href="https://www.livechat.com/chat-with/<?= (int) $lcLicense ?>/" rel="nofollow">Canlı destek ile sohbet et</a>, powered by <a href="https://www.livechat.com/?welcome" rel="noopener nofollow" target="_blank">LiveChat</a></noscript>
<script>
/* Mevcut "Canlı Destek" tetikleyicileri gömülü widget'ı açsın (widget yüklüyse). */
(function () {
    var SELECTOR = '.callPanel, .live-chat-adviser-bc, [data-live-chat], a[href*="direct.lc.chat"], a[href*="/chat-with/"]';
    document.addEventListener('click', function (e) {
        if (!window.LiveChatWidget || typeof window.LiveChatWidget.call !== 'function') {
            return;
        }
        var trigger = e.target && e.target.closest ? e.target.closest(SELECTOR) : null;
        if (!trigger) {
            return;
        }
        e.preventDefault();
        e.stopImmediatePropagation();
        try { window.LiveChatWidget.call('maximize'); } catch (err) {}
    }, true);
})();
</script>
