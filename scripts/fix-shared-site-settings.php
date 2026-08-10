<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$src = $root . '/admin/api/SiteSettings.php';

// Prefer committed admin body from git via temp if loaders already replaced files
$adminLoader = (string) file_get_contents($src);
if (str_contains($adminLoader, 'Thin loader')) {
    // Use the .tmp recovered earlier if present, else rebuild from git via shell is external —
    // read shared/api/SiteSettings.php.tmp written previously
    $tmp = $root . '/shared/api/SiteSettings.php.tmp';
    if (!is_file($tmp)) {
        fwrite(STDERR, "Missing SiteSettings.tmp — run git show first\n");
        exit(1);
    }
    $body = (string) file_get_contents($tmp);
} else {
    $body = $adminLoader;
}

// Strip any broken prior helper
$body = preg_replace(
    '/^<\?php\s*if\s*\(!function_exists\(\'shared_runtime_base_path\'\)\).*?^\}\s*\n/ms',
    "<?php\n",
    $body,
    1
) ?? $body;

$helper = <<<'PHP'
<?php

if (!function_exists('shared_runtime_base_path')) {
    function shared_runtime_base_path(): string
    {
        if (defined('ADMIN_BASE_PATH')) {
            return (string) ADMIN_BASE_PATH;
        }
        if (defined('BASE_PATH')) {
            return (string) BASE_PATH;
        }

        return dirname(__DIR__, 2);
    }
}

PHP;

if (!str_starts_with(ltrim($body), '<?php')) {
    fwrite(STDERR, "Unexpected SiteSettings body\n");
    exit(1);
}
$body = preg_replace('/^<\?php\s*/', $helper . "\n", $body, 1) ?? $body;

// Replace constant *usages* only (not defined('…') string literals)
$body = str_replace('ADMIN_BASE_PATH .', 'shared_runtime_base_path() .', $body);
$body = str_replace('BASE_PATH .', 'shared_runtime_base_path() .', $body);
$body = str_replace(
    "defined('BASE_PATH') ? (string) BASE_PATH : dirname(__DIR__)",
    'shared_runtime_base_path()',
    $body
);
$body = str_replace(
    "defined('ADMIN_BASE_PATH') ? (string) ADMIN_BASE_PATH : dirname(__DIR__)",
    'shared_runtime_base_path()',
    $body
);

// Ensure frontend guard exists in ensureDefaults (from root twin)
$front = '';
$frontGitHint = $root . '/shared/api/SiteSettings.php'; // not useful
// Pull ensureDefaults guard from git-exported front if needed
$frontTmp = $root . '/shared/api/SiteSettings.front.tmp';
if (!is_file($frontTmp)) {
    // optional
}

if (!str_contains($body, 'frontend_database_allowed')) {
    $body = preg_replace(
        '/(public static function ensureDefaults\(\): void\s*\{\s*)/',
        "$1" . "        if (function_exists('frontend_database_allowed') && !frontend_database_allowed()) {\n            return;\n        }\n\n",
        $body,
        1
    ) ?? $body;
}

$out = $root . '/shared/api/SiteSettings.php';
file_put_contents($out, $body);

// Sanity: helper must not call itself via defined('shared_runtime…')
if (str_contains($body, "defined('shared_runtime_base_path()')")) {
    fwrite(STDERR, "FAIL: broken defined() still present\n");
    exit(1);
}
if (!str_contains($body, "defined('ADMIN_BASE_PATH')") || !str_contains($body, "defined('BASE_PATH')")) {
    fwrite(STDERR, "FAIL: helper constants missing\n");
    exit(1);
}

echo "SiteSettings repaired (" . strlen($body) . " bytes)\n";
@unlink($root . '/shared/api/SiteSettings.php.tmp');
