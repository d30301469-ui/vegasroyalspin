<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('BASE_PATH', dirname(__DIR__));

$_SERVER['REQUEST_URI']    = '/slot';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST']      = 'vegasroyalspin.test';
$_SERVER['HTTPS']          = 'on';
$_GET = [];

chdir(BASE_PATH);

register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR], true)) {
        fwrite(STDERR, "\n>>> FATAL: {$e['message']} @ {$e['file']}:{$e['line']}\n");
    }
});

ob_start();
try {
    require BASE_PATH . '/pages/slot.php';
    $out = ob_get_clean();
    fwrite(STDERR, "\n>>> OK, output length = " . strlen($out) . "\n");
} catch (Throwable $e) {
    ob_end_clean();
    fwrite(STDERR, "\n>>> EXCEPTION: " . get_class($e) . ': ' . $e->getMessage()
        . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
}
