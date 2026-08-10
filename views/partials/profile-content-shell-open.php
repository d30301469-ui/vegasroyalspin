<?php
/**
 * CM622 sağ panel başlığı + kaydırılabilir içerik açılışı.
 * Sayfa <main id="profilePlayerMain"> içinde include edilmeli.
 */
$__ptitle = isset($profile_content_title) ? (string) $profile_content_title : '';
$__pclass = isset($profile_content_page_class) ? trim((string) $profile_content_page_class) : '';
?>
<div class="overlay-header"><?php echo htmlspecialchars($__ptitle, ENT_QUOTES, 'UTF-8'); ?></div>
<div class="my-profile-info-scroll<?php echo $__pclass !== '' ? ' ' . htmlspecialchars($__pclass, ENT_QUOTES, 'UTF-8') : ''; ?>" data-scroll-lock-scrollable>
