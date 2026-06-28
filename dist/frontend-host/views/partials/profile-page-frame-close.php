<?php
/**
 * Tam sayfa profil kabuğu — kapanış. modal=1 iken çıktı üretmez.
 * Önceden: $profile_modal (bool)
 */
if (!empty($profile_modal)) {
    return;
}
echo '</div>';
include __DIR__ . '/footer.php';
