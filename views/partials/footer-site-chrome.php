<?php
$footerContactLinks = is_array($siteContactLinks ?? null) ? $siteContactLinks : (class_exists('ApiSiteSettings') ? ApiSiteSettings::normalizeContactLinks(is_array($ayar ?? null) ? $ayar : []) : []);
$footerTelegramUrl = (string) ($footerContactLinks['telegram_url'] ?? 'https://t.me');
$footerWhatsappUrl = trim((string) ($footerContactLinks['whatsapp_url'] ?? ''));
$footerContactPhone = trim((string) ($footerContactLinks['contact_phone'] ?? ''));
$footerPhoneHref = $footerContactPhone !== '' ? 'tel:' . preg_replace('/[^0-9+]/', '', $footerContactPhone) : '';
?>
<!-- Lisans rozeti + sosyal (sayfa altı, footerRow dışı) -->
<div style="text-align:center; margin-top:20px; margin-bottom:16px;">
    <span style="display:inline-block; transition:transform 0.3s ease;">
        <img loading="lazy" src="https://seal.cgcb.info/1c0246df-1aa7-485a-a24c-21ae5e730000"
             alt="Lisans Logosu" width="130" height="65"
             style="height:65px; width:auto; display:block; margin:0 auto; border-radius:6px;">
    </span>
</div>

<div class="license-social-wrapper" style="text-align:center; margin-bottom:32px;">
    <div class="socialMedia">
        <a href="https://x.com" class="socialLink" target="_blank" rel="noopener" aria-label="X (Twitter)">
            <i class="fa-brands fa-twitter"></i>
        </a>
        <a href="https://www.instagram.com" class="socialLink" target="_blank" rel="noopener" aria-label="Instagram">
            <i class="fa-brands fa-instagram"></i>
        </a>
        <a href="<?= htmlspecialchars($footerTelegramUrl, ENT_QUOTES, 'UTF-8') ?>" class="socialLink" target="_blank" rel="noopener" aria-label="Telegram">
            <i class="fa-brands fa-telegram"></i>
        </a>
        <?php if ($footerWhatsappUrl !== ''): ?>
        <a href="<?= htmlspecialchars($footerWhatsappUrl, ENT_QUOTES, 'UTF-8') ?>" class="socialLink" target="_blank" rel="noopener" aria-label="WhatsApp">
            <i class="fa-brands fa-whatsapp"></i>
        </a>
        <?php endif; ?>
        <?php if ($footerPhoneHref !== ''): ?>
        <a href="<?= htmlspecialchars($footerPhoneHref, ENT_QUOTES, 'UTF-8') ?>" class="socialLink" rel="noopener" aria-label="<?= htmlspecialchars($footerContactPhone, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($footerContactPhone, ENT_QUOTES, 'UTF-8') ?>">
            <i class="fa-solid fa-phone"></i>
        </a>
        <?php endif; ?>
        <a href="https://www.youtube.com" class="socialLink" target="_blank" rel="noopener" aria-label="YouTube">
            <i class="fa-brands fa-youtube"></i>
        </a>
        <a href="https://www.pinterest.com" class="socialLink" target="_blank" rel="noopener" aria-label="Pinterest">
            <i class="fa-brands fa-pinterest-p"></i>
        </a>
        <a href="https://www.tiktok.com" class="socialLink" target="_blank" rel="noopener" aria-label="TikTok">
            <i class="fa-brands fa-tiktok"></i>
        </a>
        <a href="https://www.tumblr.com" class="socialLink" target="_blank" rel="noopener" aria-label="Tumblr">
            <i class="fa-brands fa-tumblr"></i>
        </a>
        <a href="https://www.reddit.com" class="socialLink" target="_blank" rel="noopener" aria-label="Reddit">
            <i class="fa-brands fa-reddit-alien"></i>
        </a>
    </div>
</div>
