<?php
namespace App\Support;

use Illuminate\Support\Facades\Storage;

class CatalogMediaStorage
{
    public const LEGACY = 'catalog-automation/photo-quality/media';

    public static function path(string $file): string
    {
        $disk = Storage::disk('local');
        $path = CatalogMediaCleanup::DIRECTORY.'/'.$file;
        return $disk->exists($path) ? $path : self::LEGACY.'/'.$file;
    }

    public static function consolidate(): array
    {
        $disk = Storage::disk('local'); $moved = 0; $duplicates = 0;
        foreach ($disk->files(self::LEGACY) as $old) {
            $file = basename($old);
            if (!preg_match('/\A([a-f0-9]{64})\.(jpg|png|webp)\z/', $file, $matches)) continue;
            if (!hash_equals($matches[1], hash_file('sha256', $disk->path($old)))) throw new \RuntimeException('Invalid media hash: '.$file);
            $target = CatalogMediaCleanup::DIRECTORY.'/'.$file;
            if ($disk->exists($target)) {
                if (!hash_equals($matches[1], hash_file('sha256', $disk->path($target)))) throw new \RuntimeException('Invalid canonical media: '.$file);
                $duplicates++;
            } else {
                if (!$disk->move($old, $target)) throw new \RuntimeException('Cannot migrate '.$file);
                $moved++;
            }
        }
        return compact('moved','duplicates');
    }
}
