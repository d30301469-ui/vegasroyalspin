$hostsPath = "$env:SystemRoot\System32\drivers\etc\hosts"
$entries = @(
    "127.0.0.1      admin.vegasroyalspin.test  #laragon vegasroyalspin admin",
    "127.0.0.1      api.vegasroyalspin.test    #laragon vegasroyalspin api",
    "127.0.0.1      m.vegasroyalspin.test      #laragon vegasroyalspin mobile"
)

$content = Get-Content -Raw -LiteralPath $hostsPath
$added = 0

foreach ($entry in $entries) {
    $hostName = ($entry -split '\s+')[1]
    if ($content -match [regex]::Escape($hostName)) {
        Write-Host "[OK] $hostName zaten mevcut" -ForegroundColor Green
    } else {
        Add-Content -LiteralPath $hostsPath -Value $entry -Encoding ascii
        Write-Host "[+] Eklendi: $hostName" -ForegroundColor Yellow
        $content = Get-Content -Raw -LiteralPath $hostsPath
        $added++
    }
}

if ($added -gt 0) {
    ipconfig /flushdns | Out-Null
    Write-Host "`nDNS cache temizlendi." -ForegroundColor Gray
}

Write-Host "`nSonuc:" -ForegroundColor Cyan
Select-String -Path $hostsPath -Pattern "vegasroyalspin" -SimpleMatch | ForEach-Object { Write-Host "  $_" }
