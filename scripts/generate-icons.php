#!/usr/bin/env php
<?php
/**
 * Regenerate Foodeza app icons — large "F" on red (Finder, Dock, .icns, favicon).
 */

$root = dirname(__DIR__);
$iconsDir = $root . '/desktop/src-tauri/icons';
$publicDir = $root . '/desktop/public';

$fontCandidates = [
    '/System/Library/Fonts/Supplemental/Arial Black.ttf',
    '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
    '/Library/Fonts/Arial Black.ttf',
    '/Library/Fonts/Arial Bold.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
    'C:\\Windows\\Fonts\\ariblk.ttf',
    'C:\\Windows\\Fonts\\arialbd.ttf',
];

$font = null;
foreach ($fontCandidates as $candidate) {
    if (file_exists($candidate)) {
        $font = $candidate;
        break;
    }
}

/** Fraction of canvas height used for the letter (bigger = larger F in Finder). */
const FONT_SCALE = 0.84;

function drawIcon(int $size, ?string $font, string $destPath): void
{
    $im = imagecreatetruecolor($size, $size);
    imagealphablending($im, true);
    imagesavealpha($im, true);

    $red = imagecolorallocate($im, 220, 38, 38);
    $white = imagecolorallocate($im, 255, 255, 255);
    imagefilledrectangle($im, 0, 0, $size, $size, $red);

    if ($font !== null) {
        $fontSize = $size * FONT_SCALE;
        $text = 'F';
        $bbox = imagettfbbox($fontSize, 0, $font, $text);
        $textWidth = $bbox[2] - $bbox[0];
        $textHeight = $bbox[1] - $bbox[7];
        $x = (int) (($size - $textWidth) / 2 - $bbox[0]);
        $y = (int) (($size + $textHeight) / 2 - $bbox[1] - ($size * 0.02));
        imagettftext($im, $fontSize, 0, $x, $y, $white, $font, $text);
    } else {
        $scale = max(2, (int) floor($size / 16));
        $charW = 8 * $scale;
        $charH = 13 * $scale;
        $tmp = imagecreatetruecolor($charW, $charH);
        $tmpRed = imagecolorallocate($tmp, 220, 38, 38);
        $tmpWhite = imagecolorallocate($tmp, 255, 255, 255);
        imagefilledrectangle($tmp, 0, 0, $charW, $charH, $tmpRed);
        imagestring($tmp, 5, 0, 0, 'F', $tmpWhite);
        imagecopyresized(
            $im, $tmp,
            (int) (($size - $charW) / 2), (int) (($size - $charH) / 2),
            0, 0, $charW, $charH, $charW, $charH
        );
        imagedestroy($tmp);
    }

    imagepng($im, $destPath);
    imagedestroy($im);
}

if (! is_dir($iconsDir)) {
    mkdir($iconsDir, 0755, true);
}

// High-res master for macOS .icns (Finder / Spotlight)
drawIcon(1024, $font, "$iconsDir/icon.png");

foreach ([32, 64, 128, 256, 512] as $size) {
    drawIcon($size, $font, sprintf('%s/%dx%d.png', $iconsDir, $size, $size));
}

copy("$iconsDir/128x128.png", "$iconsDir/128x128@2x.png");

// Browser / dev server favicons
if (! is_dir($publicDir)) {
    mkdir($publicDir, 0755, true);
}
foreach ([16, 32, 180] as $size) {
    drawIcon($size, $font, "$publicDir/favicon-{$size}.png");
}
copy("$iconsDir/32x32.png", "$publicDir/favicon.png");

file_put_contents("$publicDir/favicon.svg", <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
  <rect width="64" height="64" rx="14" fill="#dc2626"/>
  <text x="32" y="47" text-anchor="middle" fill="#ffffff" font-family="Arial Black, Arial, Helvetica, sans-serif" font-weight="900" font-size="46">F</text>
</svg>
SVG);

echo "Icons written to $iconsDir\n";
echo "Favicons written to $publicDir\n";
if ($font) {
    echo "Font: $font\n";
}
