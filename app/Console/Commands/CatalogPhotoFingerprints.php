<?php
namespace App\Console\Commands;
use App\Support\CatalogPhotoSimilarity;
use Illuminate\Console\Command;

class CatalogPhotoFingerprints extends Command
{
    protected $signature='catalog:photo-fingerprints';
    protected $description='Cache perceptual fingerprints for existing private catalog photos without changing products';
    public function handle(CatalogPhotoSimilarity $similarity): int
    {
        $this->info('Indexed '.count($similarity->catalog()).' product images.');
        return self::SUCCESS;
    }
}
