<?php

declare(strict_types=1);

/**
 * Create frontend .env from ENV.example and apply split-deploy defaults.
 *
 * Usage (on server):
 *   cd /www/wwwroot/vegasroyalspin.com
 *   php scripts/bootstrap-frontend-env.php
 */

$root = dirname(__DIR__);
$envFile = $root . '/.env';
$exampleFile = $root . '/ENV.example';

if (!is_readable($exampleFile)) {
    fwrite(STDERR, "ENV.example not found at {$exampleFile}\n");
    fwrite(STDERR, "Run https://vegasroyalspin.com/install or upload ENV.example from dist package.\n");
    exit(1);
}

if (!is_readable($envFile)) {
    if (!copy($exampleFile, $envFile)) {
        fwrite(STDERR, "Failed to create .env from ENV.example\n");
        exit(1);
    }
    echo "Created .env from ENV.example\n";
} else {
    echo ".env already exists — keeping file, running fix pass only.\n";
}

echo "Next: edit .env and set MEMBER_JWT_SECRET (same as backend).\n";
echo "Optional: API_BACKEND_INTERNAL_BASE_URL=http://127.0.0.1/api/v2\n";
echo "Optional: CLOUDFLARE_SSL=1 and ORIGIN_HTTP=1 for Cloudflare Flexible\n";
exit(0);
