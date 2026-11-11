<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;

class PhotoReviewController extends Controller
{
    private function read(string $file): array
    {
        abort_unless(app()->environment('local'), 404);
        $disk = Storage::disk('local');
        $path = 'catalog-automation/icecat-professional/'.$file;
        abort_unless($disk->exists($path), 404);
        return json_decode($disk->get($path), true, flags: JSON_THROW_ON_ERROR);
    }

    public function index()
    {
        abort_unless(app()->environment('local'), 404);
        $full = 'catalog-automation/icecat-full/coverage.json';
        $coverage = Storage::disk('local')->exists($full)
            ? json_decode(Storage::disk('local')->get($full), true, flags: JSON_THROW_ON_ERROR)
            : $this->read('coverage.json');
        return response()->view('catalog.photo-review', [
            'rows' => $this->read('review.json'), 'coverage' => $coverage,
            'quality' => Storage::disk('local')->exists('catalog-automation/photo-quality/coverage.json')
                ? json_decode(Storage::disk('local')->get('catalog-automation/photo-quality/coverage.json'), true, flags: JSON_THROW_ON_ERROR) : null,
            'decisions' => Storage::disk('local')->exists('catalog-automation/candidate-review/decisions.json')
                ? json_decode(Storage::disk('local')->get('catalog-automation/candidate-review/decisions.json'), true, flags: JSON_THROW_ON_ERROR) : [],
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function image(string $file)
    {
        abort_unless(preg_match('/\A[a-f0-9]{64}\.(jpg|png|webp)\z/', $file), 404);
        $rows = $this->read('review.json');
        $allowed = collect($rows)->flatMap(fn ($row) => $row['review_images'])->contains(fn ($image) => $image['file'] === $file);
        abort_unless($allowed, 404);
        $path = Storage::disk('local')->path('catalog-automation/icecat-professional/media/'.$file);
        abort_unless(is_file($path), 404);
        return response()->file($path, ['Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff', 'X-Robots-Tag' => 'noindex, nofollow']);
    }
}
