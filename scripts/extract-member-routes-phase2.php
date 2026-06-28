<?php

declare(strict_types=1);

$indexPath = __DIR__ . '/../admin/api/v2/index.php';
$routesDir = __DIR__ . '/../admin/api/v2/routes';
$lines = file($indexPath);
if ($lines === false) {
    fwrite(STDERR, "Cannot read index.php\n");
    exit(1);
}

$header = <<<'HDR'
<?php

declare(strict_types=1);

/** Üye API modülü — index.php tarafından include edilir. */

HDR;

$slice = static function (array $lines, int $start, int $end): array {
    return array_slice($lines, $start - 1, $end - $start + 1);
};

$writeMod = static function (string $path, string $header, array $chunks) use ($slice): void {
    $body = '';
    foreach ($chunks as $chunk) {
        $body .= implode('', $chunk);
    }
    file_put_contents($path, $header . $body);
};

$writeMod($routesDir . '/member_cms.php', $header, [
    $slice($lines, 648, 702),
    $slice($lines, 1101, 1182),
    $slice($lines, 1817, 2074),
]);

$writeMod($routesDir . '/member_games.php', $header, [
    $slice($lines, 704, 1099),
    $slice($lines, 1234, 1289),
    $slice($lines, 1728, 1756),
]);

$writeMod($routesDir . '/member_bonuses.php', $header, [
    $slice($lines, 1184, 1232),
    $slice($lines, 1398, 1724),
]);

$authPath = $routesDir . '/member_auth.php';
$authExtra = array_merge(
    $slice($lines, 1291, 1396),
    $slice($lines, 1758, 1811),
);
file_put_contents($authPath, file_get_contents($authPath) . "\n" . implode('', $authExtra));

// Rebuild index.php: keep lines 1-646, add requires, skip extracted blocks, keep admin_routes + dispatch
$keepBefore = array_slice($lines, 0, 643);
$requires = [
    "require __DIR__ . '/routes/member_auth.php';\n",
    "require __DIR__ . '/routes/member_engagement.php';\n",
    "require __DIR__ . '/routes/member_wallet.php';\n",
    "require __DIR__ . '/routes/member_cms.php';\n",
    "require __DIR__ . '/routes/member_games.php';\n",
    "require __DIR__ . '/routes/member_bonuses.php';\n",
    "require __DIR__ . '/routes/member_extended.php';\n",
    "\n",
    "require __DIR__ . '/includes/admin_routes.php';\n",
    "\n",
];
$keepAfter = array_slice($lines, 2075); // admin_api_dispatch label onward
$newIndex = array_merge($keepBefore, $requires, $keepAfter);
file_put_contents($indexPath, implode('', $newIndex));

echo "Extracted member_cms, member_games, member_bonuses; updated index.php\n";
