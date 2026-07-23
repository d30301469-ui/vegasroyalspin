<?php
/**
 * Bonus tablolarini sifirlama admin endpoint'i.
 * SADECE admin.vegasroyalspin.com uzerinden, admin yetkisiyle erisilebilir.
 *
 * GET  /admin/api/v2/internal/reset-bonus-claims
 *      -> tablo durumunu gosterir
 *
 * POST /admin/api/v2/internal/reset-bonus-claims
 *      Body: { "confirm": "RESET_ALL_BONUS_CLAIMS" }
 *      -> tum bonus tablolarini temizler
 */

$_GET['route'] = 'internal/reset-bonus-claims';
require __DIR__ . '/index.php';
