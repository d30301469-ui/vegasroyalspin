<?php
/**
 * CLI: php scripts/test_turkish_national_id.php
 */
require_once dirname(__DIR__) . '/services/TurkishNationalId.php';

$tests = [
    ['10100000046', true],
    ['12345678901', false],
    ['01234567890', false],
    ['123', false],
];

$ok = true;
foreach ($tests as [$tc, $expect]) {
    $got = TurkishNationalId::isValid($tc);
    if ($got !== $expect) {
        fwrite(STDERR, "FAIL tc=$tc expected " . ($expect ? 'true' : 'false') . " got " . ($got ? 'true' : 'false') . "\n");
        $ok = false;
    }
}

echo $ok ? "TurkishNationalId: OK\n" : '';
exit($ok ? 0 : 1);
