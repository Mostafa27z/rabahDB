<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Storage;

$path = Storage::disk('public')->path('test_footer_watermark.png');
if (!file_exists($path)) {
    die("File not found.\n");
}

$img = imagecreatefrompng($path);
$w = imagesx($img);
$h = imagesy($img);

$goldPixels = 0;
// Search in center area
$startX = (int)round(($w - ($w * 0.5)) / 2);
$endX = $startX + (int)round($w * 0.5);
$startY = (int)round(($h - ($h * 0.5)) / 2);
$endY = $startY + (int)round($h * 0.5);

for ($x = $startX; $x < $endX; $x++) {
    for ($y = $startY; $y < $endY; $y++) {
        $color = imagecolorat($img, $x, $y);
        $rgba = imagecolorsforindex($img, $color);
        // Gold check: R > 120, G > 100, B < 120
        if ($rgba['red'] > 120 && $rgba['green'] > 100 && $rgba['blue'] < 120) {
            $goldPixels++;
        }
    }
}

echo "Found {$goldPixels} gold pixels in center region of test_footer_watermark.png!\n";
