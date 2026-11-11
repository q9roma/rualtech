<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Support\Facades\{Gate, Storage};

class ProductPhotoController extends Controller
{
    public function __invoke(Product $product, int $index)
    {
        if (! $product->is_active) Gate::authorize('view', $product);
        $path = $product->photos[$index] ?? null;
        abort_unless($path && str_starts_with($path, 'product-photos/') && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path, null, ['X-Content-Type-Options' => 'nosniff']);
    }
}
