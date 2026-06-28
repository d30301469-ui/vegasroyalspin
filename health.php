<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$root = __DIR__;
require_once $root . '/services/SplitDeployDiagnostics.php';

echo json_encode(
    SplitDeployDiagnostics::runFrontend($root, isset($_GET['deep'])),
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
);
