<?php
/**
 * Former AMP "güncel giriş" SEO landing — Safe Browsing / social-engineering
 * risk. Redirect to the site home instead of serving mirror-domain SEO copy.
 */
declare(strict_types=1);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
$target = ($host !== '' ? ($scheme . '://' . $host) : '') . '/';

header('Location: ' . $target, true, 301);
header('Cache-Control: no-store, no-cache, must-revalidate');
exit;
