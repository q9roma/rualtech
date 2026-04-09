<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $query = Product::query()->active();

        if ($request->filled('search')) {
            $search = $request->string('search')->trim()->value();
            if ($search !== '') {
                $term = '%' . addcslashes($search, '%_\\') . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('sku', 'like', $term)
                        ->orWhere('brand', 'like', $term);
                });
            }
        }

        if ($request->filled('brand')) {
            $query->where('brand', $request->string('brand')->value());
        }

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->value());
        }

        $sort = $request->query('sort', 'name');
        if (! in_array($sort, ['name', 'price_asc', 'price_desc', 'brand'], true)) {
            $sort = 'name';
        }

        match ($sort) {
            'price_asc' => $query
                ->orderByRaw('CASE WHEN price IS NULL THEN 1 ELSE 0 END')
                ->orderBy('price')
                ->orderBy('name'),
            'price_desc' => $query
                ->orderByRaw('CASE WHEN price IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('price')
                ->orderBy('name'),
            'brand' => $query->orderBy('brand')->orderBy('name'),
            default => $query->orderBy('name'),
        };

        $products = $query->paginate(48)->withQueryString();

        $brands = Product::query()->active()->distinct()->orderBy('brand')->pluck('brand')->filter();
        $categories = Product::query()->active()->distinct()->orderBy('category')->pluck('category')->filter();

        return view('frontend.products.index', compact('products', 'brands', 'categories', 'sort'));
    }

    public function show(Product $product): View
    {
        if (! $product->is_active) {
            abort(404);
        }

        $related = Product::query()
            ->active()
            ->whereKeyNot($product->getKey())
            ->when($product->category || $product->brand, function ($q) use ($product) {
                $q->where(function ($q2) use ($product) {
                    if ($product->category && $product->brand) {
                        $q2->where('category', $product->category)
                            ->orWhere('brand', $product->brand);
                    } elseif ($product->category) {
                        $q2->where('category', $product->category);
                    } else {
                        $q2->where('brand', $product->brand);
                    }
                });
            })
            ->orderBy('name')
            ->limit(6)
            ->get();

        if ($related->count() < 4) {
            $more = Product::query()
                ->active()
                ->whereKeyNot($product->getKey())
                ->whereNotIn('id', $related->pluck('id'))
                ->orderBy('name')
                ->limit(6 - $related->count())
                ->get();
            $related = $related->concat($more);
        }

        return view('frontend.products.show', compact('product', 'related'));
    }
}
