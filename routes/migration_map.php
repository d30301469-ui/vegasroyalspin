<?php

return [
    // Auth
    '/api/auth/login' => '/api/v2/auth/login',
    '/api/auth/register' => '/api/v2/auth/register',
    '/api/auth/session' => '/api/v2/auth/session',
    '/api/auth/logout' => '/api/v2/auth/logout',
    '/api/auth/forgot-password' => '/api/v2/auth/forgot-password',
    '/api/auth/reset-password' => '/api/v2/auth/reset-password',

    // Aliases with .php
    '/api/auth/login.php' => '/api/v2/auth/login.php',
    '/api/auth/register.php' => '/api/v2/auth/register.php',

    // Payment (example entries)
    '/api/payment' => '/api/v2/payment',
    '/api/payments.php' => '/api/v2/payment',
];
