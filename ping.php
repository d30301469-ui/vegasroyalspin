<?php

declare(strict_types=1);

/**
 * Ultra-light probe — no bootstrap. Apache + PHP canlilik testi (health.php oncesi).
 */
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

echo '{"ok":true,"pong":true,"ts":"' . gmdate('c') . '"}';
