<?php

$root = dirname(__DIR__);
$public = $root.'/public';
$images = $public.'/images';

function rgba(int $red, int $green, int $blue, float $alpha = 1): array
{
    return [$red, $green, $blue, $alpha];
}

function color(GdImage $image, array $rgba): int
{
    [$red, $green, $blue, $alpha] = $rgba;

    return imagecolorallocatealpha($image, $red, $green, $blue, (int) round((1 - $alpha) * 127));
}

function roundedRect(GdImage $image, int $x, int $y, int $width, int $height, int $radius, int $fill): void
{
    imagefilledrectangle($image, $x + $radius, $y, $x + $width - $radius, $y + $height, $fill);
    imagefilledrectangle($image, $x, $y + $radius, $x + $width, $y + $height - $radius, $fill);
    imagefilledellipse($image, $x + $radius, $y + $radius, $radius * 2, $radius * 2, $fill);
    imagefilledellipse($image, $x + $width - $radius, $y + $radius, $radius * 2, $radius * 2, $fill);
    imagefilledellipse($image, $x + $radius, $y + $height - $radius, $radius * 2, $radius * 2, $fill);
    imagefilledellipse($image, $x + $width - $radius, $y + $height - $radius, $radius * 2, $radius * 2, $fill);
}

function strokeRoundedRect(GdImage $image, int $x, int $y, int $width, int $height, int $radius, int $stroke, int $thickness): void
{
    for ($i = 0; $i < $thickness; $i++) {
        imagearc($image, $x + $radius, $y + $radius, ($radius * 2) - $i, ($radius * 2) - $i, 180, 270, $stroke);
        imagearc($image, $x + $width - $radius, $y + $radius, ($radius * 2) - $i, ($radius * 2) - $i, 270, 360, $stroke);
        imagearc($image, $x + $width - $radius, $y + $height - $radius, ($radius * 2) - $i, ($radius * 2) - $i, 0, 90, $stroke);
        imagearc($image, $x + $radius, $y + $height - $radius, ($radius * 2) - $i, ($radius * 2) - $i, 90, 180, $stroke);
        imageline($image, $x + $radius, $y + $i, $x + $width - $radius, $y + $i, $stroke);
        imageline($image, $x + $radius, $y + $height - $i, $x + $width - $radius, $y + $height - $i, $stroke);
        imageline($image, $x + $i, $y + $radius, $x + $i, $y + $height - $radius, $stroke);
        imageline($image, $x + $width - $i, $y + $radius, $x + $width - $i, $y + $height - $radius, $stroke);
    }
}

function iconPng(int $size): string
{
    $scale = max(4, (int) ceil($size / 64) * 4);
    $canvas = imagecreatetruecolor($size * $scale, $size * $scale);
    imagesavealpha($canvas, true);
    imagealphablending($canvas, false);
    imagefilledrectangle($canvas, 0, 0, imagesx($canvas), imagesy($canvas), color($canvas, rgba(0, 0, 0, 0)));
    imagealphablending($canvas, true);
    imageantialias($canvas, true);

    $unit = fn (int|float $value): int => (int) round($value * $size * $scale / 64);
    $danger = color($canvas, rgba(217, 45, 32));

    roundedRect($canvas, 0, 0, $unit(64), $unit(64), $unit(14), $danger);

    $dog = imagecreatefrompng(dirname(__DIR__).'/public/images/spd.png');
    if (! $dog) {
        throw new RuntimeException('Could not load public/images/spd.png');
    }
    imagealphablending($dog, true);
    imagesavealpha($dog, true);
    imagecopyresized($canvas, $dog, $unit(7), $unit(6), 0, 0, $unit(50), $unit(52), imagesx($dog), imagesy($dog));

    $image = imagecreatetruecolor($size, $size);
    imagesavealpha($image, true);
    imagealphablending($image, false);
    imagefilledrectangle($image, 0, 0, $size, $size, color($image, rgba(0, 0, 0, 0)));
    imagecopyresampled($image, $canvas, 0, 0, 0, 0, $size, $size, imagesx($canvas), imagesy($canvas));
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

function writePng(string $path, int $size): void
{
    file_put_contents($path, iconPng($size));
}

function writeIco(string $path, array $sizes): void
{
    $images = array_map(fn (int $size): array => [$size, iconPng($size)], $sizes);
    $offset = 6 + (16 * count($images));
    $directory = '';
    $data = '';

    foreach ($images as [$size, $png]) {
        $directory .= pack('CCCCvvVV', $size === 256 ? 0 : $size, $size === 256 ? 0 : $size, 0, 0, 1, 32, strlen($png), $offset);
        $data .= $png;
        $offset += strlen($png);
    }

    file_put_contents($path, pack('vvv', 0, 1, count($images)).$directory.$data);
}

writePng($images.'/icon-192.png', 192);
writePng($images.'/icon-512.png', 512);
writePng($images.'/apple-touch-icon.png', 180);
writeIco($images.'/favicon.ico', [16, 32, 48, 64]);
