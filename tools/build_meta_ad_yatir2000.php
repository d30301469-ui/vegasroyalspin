<?php
/**
 * Meta ad creative: site logo + offer text (1080x1080).
 * Run: php tools/build_meta_ad_yatir2000.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$logoPath = $root . '/assets/images/ads/logo-vegasroyalspin-ref.png';
$outPath = $root . '/assets/images/ads/meta-ad-yatir-2000-bizden-3000.png';

if (!is_file($logoPath)) {
    $webp = $root . '/_shots/logo-vegasroyalspin.webp';
    if (!is_file($webp)) {
        fwrite(STDERR, "Logo not found\n");
        exit(1);
    }
    $logoPath = $webp;
}

$size = 1080;
$im = imagecreatetruecolor($size, $size);
imagealphablending($im, true);
imagesavealpha($im, true);

for ($y = 0; $y < $size; $y++) {
    $t = $y / ($size - 1);
    $r = (int) (5 + (28 - 5) * $t);
    $g = (int) (6 + (10 - 6) * $t);
    $b = (int) (12 + (48 - 12) * $t);
    $col = imagecolorallocate($im, $r, $g, $b);
    imageline($im, 0, $y, $size, $y, $col);
}

// Soft purple glow ellipse
for ($i = 0; $i < 40; $i++) {
    $alpha = (int) min(127, 110 + $i);
    $col = imagecolorallocatealpha($im, 110, 35, 160, $alpha);
    $pad = $i * 10;
    imagefilledellipse($im, (int) ($size / 2), 520, 900 + $pad, 700 + $pad, $col);
}

$logoRaw = file_get_contents($logoPath);
if ($logoRaw === false) {
    fwrite(STDERR, "Cannot read logo\n");
    exit(1);
}
$logo = @imagecreatefromstring($logoRaw);
if ($logo === false) {
    fwrite(STDERR, "Cannot decode logo\n");
    exit(1);
}

$lw = imagesx($logo);
$lh = imagesy($logo);
$maxW = 780;
$maxH = 200;
$scale = min($maxW / $lw, $maxH / $lh);
$dw = (int) round($lw * $scale);
$dh = (int) round($lh * $scale);
$dx = (int) (($size - $dw) / 2);
$dy = 70;
imagecopyresampled($im, $logo, $dx, $dy, 0, 0, $dw, $dh, $lw, $lh);
imagedestroy($logo);

$white = imagecolorallocate($im, 245, 245, 247);
$gold = imagecolorallocate($im, 224, 195, 106);
$muted = imagecolorallocate($im, 168, 168, 179);
$btnFill = imagecolorallocate($im, 107, 27, 140);
$btnBorder = imagecolorallocate($im, 198, 161, 91);

$fontCandidates = [
    'C:/Windows/Fonts/arialbd.ttf',
    'C:/Windows/Fonts/segoeuib.ttf',
    'C:/Windows/Fonts/arial.ttf',
    'C:/Windows/Fonts/segoeui.ttf',
];
$fontBold = null;
$fontReg = null;
foreach ($fontCandidates as $f) {
    if (is_file($f)) {
        if ($fontBold === null && (str_contains(strtolower($f), 'bd') || str_contains(strtolower($f), 'bold'))) {
            $fontBold = $f;
        }
        if ($fontReg === null && !str_contains(strtolower($f), 'bd') && !str_contains(strtolower($f), 'bold')) {
            $fontReg = $f;
        }
    }
}
$fontBold = $fontBold ?: ($fontCandidates[0] ?? null);
$fontReg = $fontReg ?: $fontBold;

if ($fontBold === null || !function_exists('imagettftext')) {
    fwrite(STDERR, "Need FreeType + TTF font\n");
    exit(1);
}

$centerText = static function ($im, string $font, float $sizePt, int $color, string $text, int $cy) use ($size): void {
    $box = imagettfbbox($sizePt, 0, $font, $text);
    $tw = abs($box[2] - $box[0]);
    $th = abs($box[7] - $box[1]);
    $x = (int) (($size - $tw) / 2) - (int) $box[0];
    $y = (int) ($cy + $th / 2);
    imagettftext($im, $sizePt, 0, $x, $y, $color, $font, $text);
};

$centerText($im, $fontBold, 42, $white, 'YATIR', 320);
$centerText($im, $fontBold, 86, $gold, '2.000 ₺', 420);
$centerText($im, $fontBold, 42, $white, 'BİZDEN', 530);
$centerText($im, $fontBold, 86, $gold, '3.000 ₺', 630);

$divY = 710;
$divCol = imagecolorallocatealpha($im, 198, 161, 91, 40);
imageline($im, 340, $divY, 740, $divY, $divCol);
imageline($im, 340, $divY + 1, 740, $divY + 1, $divCol);

$centerText($im, $fontReg, 22, $muted, 'Hoş geldin yatırım bonusu', 760);

// CTA pill
$bx = 250;
$by = 850;
$bw = 580;
$bh = 90;
$radius = 45;
imagefilledellipse($im, $bx + $radius, $by + $radius, $radius * 2, $radius * 2, $btnFill);
imagefilledellipse($im, $bx + $bw - $radius, $by + $radius, $radius * 2, $radius * 2, $btnFill);
imagefilledrectangle($im, $bx + $radius, $by, $bx + $bw - $radius, $by + $bh, $btnFill);
imageellipse($im, $bx + $radius, $by + $radius, $radius * 2, $radius * 2, $btnBorder);
imageellipse($im, $bx + $bw - $radius, $by + $radius, $radius * 2, $radius * 2, $btnBorder);
imageline($im, $bx + $radius, $by, $bx + $bw - $radius, $by, $btnBorder);
imageline($im, $bx + $radius, $by + $bh - 1, $bx + $bw - $radius, $by + $bh - 1, $btnBorder);

$centerText($im, $fontBold, 30, $white, 'HEMEN KATIL', $by + (int) ($bh / 2) - 4);

imagepng($im, $outPath, 6);
imagedestroy($im);

echo "OK {$outPath} bytes=" . filesize($outPath) . PHP_EOL;
