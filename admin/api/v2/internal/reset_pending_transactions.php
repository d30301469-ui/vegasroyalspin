<?php
/**
 * Bekleyen/basarisiz yatirim-cekim islemlerini temizleme admin endpoint'i.
 * SADECE admin.vegasroyalspin.com uzerinden, admin yetkisiyle erisilebilir.
 *
 * GET  /api/v2/internal/reset-pending-transactions
 *      -> islem durumunu gosterir
 *
 * POST /api/v2/internal/reset-pending-transactions
 *      Body: { "confirm": "RESET_ALL_PENDING_TX" }
 *      -> tum pending/failed/rejected islemleri temizler, ID'leri sifirlar
 */

$_GET['route'] = 'internal/reset-pending-transactions';
require __DIR__ . '/../index.php';
