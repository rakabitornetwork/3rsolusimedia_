<?php

function shrink(string $src, string $dest, int $max): void
{
    $image = @imagecreatefrompng($src);
    if (! $image) {
        echo "fail {$src}\n";

        return;
    }

    $width = imagesx($image);
    $height = imagesy($image);
    $scale = min(1, $max / max($width, $height));
    $newWidth = (int) max(1, round($width * $scale));
    $newHeight = (int) max(1, round($height * $scale));

    $output = imagecreatetruecolor($newWidth, $newHeight);
    imagealphablending($output, false);
    imagesavealpha($output, true);
    $transparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
    imagefill($output, 0, 0, $transparent);
    imagecopyresampled($output, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    imagepng($output, $dest, 6);
    imagedestroy($image);
    imagedestroy($output);

    echo basename($dest).' '.filesize($dest).PHP_EOL;
}

$base = dirname(__DIR__).'/public/images/brand';
shrink("{$base}/logo-mark.png", "{$base}/logo-mark.png", 512);
shrink("{$base}/logo-full.png", "{$base}/logo-full.png", 1200);
copy("{$base}/logo-mark.png", "{$base}/favicon.png");
echo 'favicon '.filesize("{$base}/favicon.png").PHP_EOL;
