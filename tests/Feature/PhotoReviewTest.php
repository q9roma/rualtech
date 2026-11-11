<?php
namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoReviewTest extends TestCase
{
    public function test_complete_catalog_report_takes_precedence_over_sample_report(): void
    {
        Storage::fake('local');
        $this->app['env'] = 'local';
        Storage::disk('local')->put('catalog-automation/icecat-professional/review.json', '[]');
        $counts = [];
        foreach (['vvp', 'icecat_confirmed', 'combined', 'uncovered', 'intersection', 'icecat_adds', 'icecat_tested', 'icecat_review_only'] as $key) {
            $counts[$key] = ['count' => 1, 'percent' => 50];
        }
        Storage::disk('local')->put('catalog-automation/icecat-full/coverage.json', json_encode([
            'denominator' => 2, 'scope' => 'full-current-catalog', 'counts' => $counts,
            'sections' => [['name' => 'Test section', 'total' => 2, 'vvp' => 1, 'icecat' => 1, 'combined' => 1, 'percent' => 50]],
            'statuses' => ['restricted' => 1], 'ean_conflicts' => 0, 'download_errors' => 0,
        ]));
        $this->get('/catalog/photo-review')->assertOk()->assertSee('Полный проход по актуальному каталогу завершён.')
            ->assertSee('Test section')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_review_images_require_local_environment_and_manifest_membership(): void
    {
        Storage::fake('local');
        $file = str_repeat('a', 64).'.png';
        $base = 'catalog-automation/icecat-professional/';
        Storage::disk('local')->put($base.'review.json', json_encode([['review_images'=>[['file'=>$file]]]]));
        Storage::disk('local')->put($base.'media/'.$file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8ZsAAAAASUVORK5CYII='));
        $this->get('/catalog/photo-review')->assertNotFound();
        $this->get('/catalog-photo-review-image/'.$file)->assertNotFound();
        $this->app['env'] = 'local';
        $this->get('/catalog-photo-review-image/'.$file)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/catalog-photo-review-image/'.str_repeat('b',64).'.png')->assertNotFound();
        $this->get('/catalog-photo-review-image/review.json')->assertNotFound();
    }
}
