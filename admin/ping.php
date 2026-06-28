<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

echo '{"ok":true,"pong":true,"role":"backend","ts":"' . gmdate('c') . '"}';
