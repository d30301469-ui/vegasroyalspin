$php = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"
Set-Location "c:\laragon\www\vegasroyalspin"

$files = @(
    "index.php",
    "public\index.php",
    "core\legacy_dispatch.php",
    "config\paths.php",
    "config\app.php",
    "app\Http\Controllers\Site\LegacyPublicController.php",
    "dist\frontend-host\config\app.php",
    "dist\admin-host\config\app.php",
    "dist\frontend-host\config\bootstrap_api.php",
    "dist\admin-host\config\bootstrap_api.php",
    "dist\frontend-host\controllers\Api\ApiBalanceController.php",
    "dist\admin-host\controllers\Api\ApiBalanceController.php",
    "dist\admin-host\controllers\Api\ApiCallbackController.php",
    "dist\admin-host\app\Core\AdminPaths.php"
)

$ok = 0
$err = 0

foreach ($f in $files) {
    $result = & $php -l $f 2>&1
    $resultStr = $result -join ""
    if ($resultStr -match "No syntax errors") {
        Write-Host "OK  $f"
        $ok++
    } else {
        Write-Host "ERR $f`: $resultStr"
        $err++
    }
}

Write-Host ""
Write-Host "Result: $ok OK, $err ERR"
