<?php
/**
 * Profil alt sayfaları ortak kabuğu — açılış: sayfa sarmalayıcı, üst başlık + kapat (chrome), içerik kartı + gövde.
 * Başlık kartın dışında; modalda kaydırma yalnızca .profile-section-body üzerinde kalır (profile.css).
 *
 * Tanımlayın: $profile_content_title (string), $profile_modal (bool)
 * İsteğe bağlı: $profile_content_page_class (ör. 'personal-details-page--password')
 * İsteğe bağlı: $profile_close_href_full — tam sayfada X hedefi (varsayılan /profile/details)
 */
$__ptitle = isset($profile_content_title) ? (string) $profile_content_title : '';
$__pmodal = !empty($profile_modal);
$__pextra = isset($profile_content_page_class) ? trim((string) $profile_content_page_class) : '';
$__pclose = isset($profile_close_href_full) ? (string) $profile_close_href_full : '/profile/details';
$__phref = $__pmodal ? '#' : $__pclose;
?>
<div class="personal-details-page<?php echo $__pextra !== '' ? ' ' . htmlspecialchars($__pextra, ENT_QUOTES, 'UTF-8') : ''; ?><?php echo $__pmodal ? ' is-modal' : ''; ?>">
    <header class="personal-details-header" role="banner">
        <h1 class="personal-details-title"><?php echo htmlspecialchars($__ptitle, ENT_QUOTES, 'UTF-8'); ?></h1>
        <a href="<?php echo htmlspecialchars($__phref, ENT_QUOTES, 'UTF-8'); ?>" class="personal-details-close"<?php echo $__pmodal ? ' data-profile-modal-close="1"' : ''; ?> aria-label="Kapat"><i class="fa-solid fa-times"></i></a>
    </header>
    <div class="personal-details-card">
        <div class="profile-section-body">
