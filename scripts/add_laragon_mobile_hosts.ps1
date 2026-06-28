# Laragon: Metropol Casino local domain hosts kayıtları
# Yönetici olarak çalıştırın: sağ tık > PowerShell ile çalıştır (Yönetici)
# veya: powershell -ExecutionPolicy Bypass -File "...\add_laragon_mobile_hosts.ps1"

$hostsPath = "$env:SystemRoot\System32\drivers\etc\hosts"
$lines = @(
    "127.0.0.1      metropolcasino.test        #laragon metropolcasino desktop",
    "127.0.0.1      m.metropolcasino.test      #laragon metropolcasino mobile",
    "127.0.0.1      admin.metropolcasino.test  #laragon metropolcasino admin"
)

$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
    [Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    Write-Host "HATA: Yönetici yetkisi gerekli." -ForegroundColor Red
    Write-Host "  Dosyaya sağ tık > PowerShell ile çalıştır (Yönetici olarak)" -ForegroundColor Yellow
    exit 1
}

$content = Get-Content -Raw -LiteralPath $hostsPath
foreach ($line in $lines) {
    $hostPart = ($line -split '\s+')[1]
    if ($content -match [regex]::Escape($hostPart)) {
        Write-Host "Zaten var: $hostPart"
        continue
    }
    try {
        Add-Content -LiteralPath $hostsPath -Value "`r`n$line" -Encoding ascii -ErrorAction Stop
        Write-Host "Eklendi: $hostPart"
        $content = Get-Content -Raw -LiteralPath $hostsPath
    } catch {
        Write-Host "HATA ($hostPart): $_" -ForegroundColor Red
        exit 1
    }
}

try {
    ipconfig /flushdns | Out-Null
    Write-Host "`nDNS cache temizlendi."
} catch {
    Write-Host "`nUYARI: DNS cache temizlenemedi. Gerekirse komutu elle çalıştırın: ipconfig /flushdns" -ForegroundColor Yellow
}

Write-Host "`nTamam. Tarayıcıda:"
Write-Host "  http://metropolcasino.test/"
Write-Host "  http://m.metropolcasino.test/"
Write-Host "  http://admin.metropolcasino.test/"
