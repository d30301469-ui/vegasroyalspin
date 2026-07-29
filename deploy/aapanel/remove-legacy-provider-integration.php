#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Drakon is an active provider integration and must never be removed.
 *
 * This compatibility script intentionally performs no database or permission
 * changes. It remains in place for deployment scripts that still invoke it.
 *
 * Usage: php deploy/aapanel/remove-legacy-provider-integration.php
 *        [ignored legacy project-root argument]
 */

echo "OK: Drakon is an active provider; no legacy cleanup performed.\n";
exit(0);
