<?php
/**
 * Bakım modu (bootstrap $ayar — bakim_modu). Layout/head yok; SEO için minimal.
 */
$siteAdi = isset($ayar['site_adi']) && (string) $ayar['site_adi'] !== ''
    ? (string) $ayar['site_adi']
    : 'Site';
$aciklama = isset($ayar['site_aciklama']) ? trim((string) $ayar['site_aciklama']) : '';
if ($aciklama === '') {
    $aciklama = 'Planlı bakım veya güncelleme çalışması yapılmaktadır. Kısa süre içinde tekrar deneyebilirsiniz.';
}
$loginUrl = '/login';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= htmlspecialchars($siteAdi, ENT_QUOTES, 'UTF-8') ?> — Bakım modu</title>
  <link rel="stylesheet" href="<?= htmlspecialchars(asset_url('assets/css/maintenance.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="maintenance-page">
  <div class="maintenance-wrap">
    <div class="maintenance-ico" aria-hidden="true">&#9881;</div>
    <h1>Bakımdayız</h1>
    <p><?= htmlspecialchars($aciklama, ENT_QUOTES, 'UTF-8') ?></p>
    <div class="maintenance-actions">
      <a class="btn-primary" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">Giriş sayfası</a>
    </div>
    <div class="maintenance-site"><?= htmlspecialchars($siteAdi, ENT_QUOTES, 'UTF-8') ?></div>
  </div>
</body>
</html>
