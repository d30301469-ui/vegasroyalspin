<?php
/**
 * Eski: games.log → MySQL aktarımı. Veritabanı kaldırıldı.
 * Oyun senkronu için backend API veya admin panel kullanın.
 *
 * Kullanım: php scripts/import-casino-games.php (çıkış kodu 1, mesaj stderr)
 */
fwrite(STDERR, "Bu betik devre dışı: veritabanı bağlantısı kaldırıldı.\n");
exit(1);
