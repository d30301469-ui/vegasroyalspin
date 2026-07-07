$file = 'c:\laragon\www\vegasroyalspin\views\partials\slider.php'
$content = Get-Content $file -Raw
$content = $content.Replace('width="1200" height="400"', '')
Set-Content $file $content
Write-Host 'width/height kaldirildi'
