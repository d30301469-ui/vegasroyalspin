# Laragon local subdomain setup for VegasRoyalSpin
# Run as Administrator:  powershell -ExecutionPolicy Bypass -File scripts/setup-laragon-local.ps1

$ErrorActionPreference = "Stop"
$hostsFile = "$env:SystemRoot\System32\drivers\etc\hosts"
$entries = @(
    "127.0.0.1      admin.vegasroyalspin.test  #laragon vegasroyalspin admin",
    "127.0.0.1      api.vegasroyalspin.test    #laragon vegasroyalspin api"
)

Write-Host "=== VegasRoyalSpin Laragon Local Setup ===" -ForegroundColor Cyan

# 1. Hosts file
$hostsContent = Get-Content $hostsFile -Raw -ErrorAction SilentlyContinue
if (-not $hostsContent) {
    Write-Error "Cannot read hosts file. Run this script as Administrator."
}

$added = @()
foreach ($entry in $entries) {
    $hostName = ($entry -split '\s+')[1]
    if ($hostsContent -match [regex]::Escape($hostName)) {
        Write-Host "[OK] $hostName already in hosts" -ForegroundColor Green
    } else {
        Add-Content -Path $hostsFile -Value $entry
        $added += $hostName
        Write-Host "[+] Added $hostName to hosts" -ForegroundColor Yellow
    }
}

if ($added.Count -gt 0) {
    Write-Host "Flush DNS cache..." -ForegroundColor Gray
    ipconfig /flushdns | Out-Null
}

# 2. Apache vhost check
$vhost = "C:\laragon\etc\apache2\sites-enabled\auto.vegasroyalspin.test.conf"
if (Test-Path $vhost) {
    $vhostContent = Get-Content $vhost -Raw
    if ($vhostContent -match 'ServerAlias \*\.\$\{SITE\}') {
        Write-Host "[OK] Apache wildcard alias *.vegasroyalspin.test is configured" -ForegroundColor Green
    } else {
        Write-Host "[!] Add 'ServerAlias *.`${SITE}' to $vhost" -ForegroundColor Yellow
    }
} else {
    Write-Host "[!] Laragon vhost not found: $vhost" -ForegroundColor Yellow
    Write-Host "    Right-click project folder in Laragon -> 'Create virtual host'" -ForegroundColor Gray
}

# 3. .env templates
$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$laragonEnv = Join-Path $root "deploy\env\laragon.env.example"
$laragonAdminEnv = Join-Path $root "deploy\env\laragon.admin.env.example"

Write-Host ""
Write-Host "Next steps:" -ForegroundColor Cyan
Write-Host "  1. Laragon -> Stop All -> Start All (Apache restart)"
Write-Host "  2. Copy local env (if not using production .env):"
Write-Host "       copy deploy\env\laragon.env.example .env"
Write-Host "       copy deploy\env\laragon.admin.env.example admin\.env"
Write-Host "  3. Create MySQL database 'metropol_db' in Laragon/phpMyAdmin"
Write-Host "  4. Open http://admin.vegasroyalspin.test/install if not installed"
Write-Host ""
Write-Host "URLs:" -ForegroundColor Cyan
Write-Host "  Frontend : http://vegasroyalspin.test"
Write-Host "  Admin    : http://admin.vegasroyalspin.test"
Write-Host "  API v2   : http://api.vegasroyalspin.test/api/v2/site_settings.php"
