<?php
/**
 * Dar viewport'ta masaüstü alan adından m.{ana_domain} adresine geçiş.
 * Çerez: prefer_desktop=1 ile devre dışı kalır.
 */
if (!function_exists('isMobile') || isMobile()) {
    return;
}
$mobileBase = mobile_site_base_url();
if ($mobileBase === null || $mobileBase === '') {
    return;
}
$maxW = defined('MOBILE_REDIRECT_MAX_WIDTH') ? (int) constant('MOBILE_REDIRECT_MAX_WIDTH') : 992;
if ($maxW < 320) {
    $maxW = 992;
}
?>
<script>
(function () {
  var mw = <?= (int) $maxW ?>;
  try {
    if (document.cookie.indexOf('prefer_desktop=1') !== -1) return;
    if (!window.matchMedia || !window.matchMedia('(max-width: ' + mw + 'px)').matches) return;
    var base = <?= json_encode($mobileBase, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var path = window.location.pathname || '/';
    var target = base + path + window.location.search + window.location.hash;
    if (window.location.protocol + '//' + window.location.host === base) return;
    window.location.replace(target);
  } catch (e) {}
})();
</script>
