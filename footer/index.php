<?php

require_once dirname(__DIR__) . '/core/bootstrap.php';
require_once CONTROLLER_PATH . '/FooterPageController.php';

$slug = trim((string) ($_GET['slug'] ?? ''), '/');
if ($slug === '') {
    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $parts = explode('/', trim($path, '/'));
    $footerIndex = array_search('footer', $parts, true);
    if ($footerIndex !== false && isset($parts[$footerIndex + 1])) {
        $slug = (string) $parts[$footerIndex + 1];
    }
}

(new FooterPageController())->show($slug);
