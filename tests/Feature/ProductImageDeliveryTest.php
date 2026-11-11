<?php

namespace Tests\Feature;

use App\Support\ProductImageDelivery;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageDeliveryTest extends TestCase
{
    public function test_jpeg_is_progressive_resized_cached_and_original_is_preserved(): void
    {
        Storage::fake('local');
        $source = Storage::disk('local')->path('source.jpg');
        $image = imagecreatetruecolor(1800, 900);
        imagejpeg($image, $source, 95);
        imagedestroy($image);
        $original = hash_file('sha256', $source);
        $target = ProductImageDelivery::path($source, 'card');
        $this->assertSame([640, 320], array_slice(getimagesize($target), 0, 2));
        $this->assertStringContainsString("\xFF\xC2", file_get_contents($target));
        $this->assertSame($target, ProductImageDelivery::path($source, 'card'));
        $this->assertSame($original, hash_file('sha256', $source));
        $this->assertSame([1800, 900], array_slice(getimagesize(ProductImageDelivery::path($source, 'full')), 0, 2));
        $this->assertSame($source, ProductImageDelivery::path($source, 'invalid'));
    }

    public function test_transparency_is_preserved_and_small_images_are_not_upscaled(): void
    {
        Storage::fake('local');
        $source = Storage::disk('local')->path('source.png');
        $image = imagecreatetruecolor(40, 20);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagepng($image, $source);
        imagedestroy($image);
        $target = ProductImageDelivery::path($source, 'thumb');
        $this->assertSame([40, 20], array_slice(getimagesize($target), 0, 2));
        $decoded = imagecreatefrompng($target);
        $this->assertSame(127, imagecolorsforindex($decoded, imagecolorat($decoded, 0, 0))['alpha']);
        imagedestroy($decoded);
    }

    public function test_inline_preview_is_small_and_uses_the_same_photo(): void
    {
        Storage::fake('local');
        $file = str_repeat('a', 64).'.jpg';
        Storage::disk('local')->makeDirectory('catalog-automation/photo-quality/media');
        $source = Storage::disk('local')->path('catalog-automation/photo-quality/media/'.$file);
        $image = imagecreatetruecolor(800, 400);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 40, 30));
        imagejpeg($image, $source);
        imagedestroy($image);
        $url = 'http://localhost/catalog-photo-quality-image/'.$file;
        $previews = ProductImageDelivery::previews([$url, 'https://example.com/photo.jpg']);
        $this->assertCount(1, $previews);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $previews[$url]);
        $data = base64_decode(explode(',', $previews[$url], 2)[1]);
        $this->assertSame([32, 16], array_slice(getimagesizefromstring($data), 0, 2));
        $this->assertLessThan(2048, strlen($data));
    }

    public function test_background_detection_accepts_solid_edges_and_rejects_complex_or_transparent_edges(): void
    {
        $image = imagecreatetruecolor(32, 32);
        imagefill($image, 0, 0, imagecolorallocate($image, 240, 230, 220));
        imagefilledrectangle($image, 10, 10, 20, 20, imagecolorallocate($image, 20, 20, 20));
        $encode = function () use ($image) {
            ob_start();
            imagepng($image);
            return 'data:image/png;base64,'.base64_encode(ob_get_clean());
        };
        $this->assertSame(['photo' => '#f0e6dc'], ProductImageDelivery::backgrounds(['photo' => $encode()]));
        imagesetpixel($image, 0, 0, imagecolorallocate($image, 0, 0, 0));
        $this->assertSame([], ProductImageDelivery::backgrounds(['photo' => $encode()]));
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagesetpixel($image, 0, 0, imagecolorallocatealpha($image, 240, 230, 220, 127));
        $this->assertSame([], ProductImageDelivery::backgrounds(['photo' => $encode()]));
        imagedestroy($image);
    }
}
