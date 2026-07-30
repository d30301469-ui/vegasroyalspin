<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

foreach (['DrakonService', 'LiveCasinoQuery', 'BgamingService'] as $class) {
    $service = dirname(__DIR__) . '/services/' . $class . '.php';
    if (is_readable($service)) {
        require_once $service;
    }
}
