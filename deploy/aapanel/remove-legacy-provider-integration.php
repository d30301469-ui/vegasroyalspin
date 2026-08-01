#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Compatibility no-op for deployment scripts that still invoke this path.
 * Legacy SoftSwiss/provider cleanup is no longer performed here.
 *
 * Usage: php deploy/aapanel/remove-legacy-provider-integration.php
 *        [ignored legacy project-root argument]
 */

echo "OK: no legacy provider cleanup performed.\n";
exit(0);
