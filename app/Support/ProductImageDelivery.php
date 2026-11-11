<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/** Derived files stay private and are served only after the source access checks. */
class ProductImageDelivery
{
    /** Extend only a consistent opaque edge; patterned backgrounds are left alone. */
    public static function backgrounds(array $previews): array
    {
        if (! extension_loaded('gd')) return [];
        $backgrounds = [];
        foreach ($previews as $url => $preview) {
            $image = @imagecreatefromstring(base64_decode(explode(',', $preview, 2)[1] ?? ''));
            if (! $image) continue;
            $width = imagesx($image) - 1;
            $height = imagesy($image) - 1;
            $samples = [];
            foreach ([0, .25, .5, .75, 1] as $fraction) {
                foreach ([[round($width * $fraction), 0], [round($width * $fraction), $height], [0, round($height * $fraction)], [$width, round($height * $fraction)]] as [$x, $y]) {
                    $samples[] = imagecolorsforindex($image, imagecolorat($image, (int) $x, (int) $y));
                }
            }
            imagedestroy($image);
            if (max(array_column($samples, 'alpha')) > 0) continue;
            $rgb = [];
            foreach (['red', 'green', 'blue'] as $channel) {
                $values = array_column($samples, $channel);
                if (max($values) - min($values) > 18) continue 2;
                $rgb[] = (int) round(array_sum($values) / count($values));
            }
            $backgrounds[$url] = sprintf('#%02x%02x%02x', ...$rgb);
        }
        return $backgrounds;
    }

    public static function previews(array $urls): array
    {
        $previews = [];
        foreach ($urls as $url) {
            $path = parse_url($url, PHP_URL_PATH);
            $source = null;
            if (preg_match('~^/catalog-photo-quality-image/([a-f0-9]{64}\.(?:jpg|png|webp))$~', $path, $match)) {
                $source = Storage::disk('local')->path(\App\Support\CatalogMediaStorage::path($match[1]));
            } elseif (preg_match('~^/product-media-pilot/(\d+)/(\d+)$~', $path, $match)) {
                foreach (ProductMediaPilot::rows() as $row) {
                    if ((string) $row['id'] !== $match[1]) continue;
                    $file = $row['preview_images'][(int) $match[2]]['file'] ?? '';
                    if (preg_match('~^\d+\.(jpg|png|webp)$~', $file)) {
                        $source = Storage::disk('local')->path('icecat-pilot/media/'.$match[1].'/'.$file);
                    }
                    break;
                }
            }
            if (! $source || ! is_file($source)) continue;
            $preview = self::path($source, 'preview');
            // Never inline an original when conversion is unavailable.
            if ($preview === $source || filesize($preview) > 8192) continue;
            $mime = getimagesize($preview)['mime'];
            $previews[$url] = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($preview));
        }
        return $previews;
    }

    public static function path(string $source, string $size): string
    {
        $limit = ['preview' => 32, 'thumb' => 160, 'card' => 640, 'detail' => 1200, 'full' => 2000][$size] ?? null;
        if (! $limit || ! extension_loaded('gd')) return $source;
        $info = @getimagesize($source);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) return $source;
        // JPEG photos are progressive; PNG/WebP keep transparency and lossless output.
        $jpeg = $info[2] === IMAGETYPE_JPEG;
        $key = hash('sha256', 'v1|'.$source.'|'.hash_file('sha256', $source).'|'.$size);
        $disk = Storage::disk('local');
        $target = $disk->path('product-image-cache/'.$key.($jpeg ? '.jpg' : '.png'));
        if (is_file($target)) return $target;
        $image = @imagecreatefromstring(file_get_contents($source));
        if (! $image) return $source;
        $scale = min(1, $limit / max($info[0], $info[1]));
        $width = max(1, (int) round($info[0] * $scale));
        $height = max(1, (int) round($info[1] * $scale));
        $output = imagecreatetruecolor($width, $height);
        imagealphablending($output, false);
        imagesavealpha($output, true);
        imagecopyresampled($output, $image, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
        $disk->makeDirectory('product-image-cache');
        $temporary = tempnam(dirname($target), 'image-');
        try {
            imageinterlace($output, $jpeg);
            $saved = $jpeg ? imagejpeg($output, $temporary, 85) : imagepng($output, $temporary, 9);
            if (! $saved) return $source;
            if (! @rename($temporary, $target) && ! is_file($target)) return $source;
            return $target;
        } finally {
            if (is_file($temporary)) unlink($temporary);
            imagedestroy($output);
            imagedestroy($image);
        }
    }
}
