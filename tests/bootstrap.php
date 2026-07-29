<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$service = dirname(__DIR__) . '/services/DrakonService.php';
if (is_readable($service)) {
    require_once $service;
}
