<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SearchIndexing
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $private = $request->is('admin', 'admin/*', 'login', 'register', 'account', 'account/*',
            'cart', 'cart/*', 'checkout', 'orders/*', 'ready', 'catalog/suggestions', 'catalog/photo-*', 'catalog/photo-*/*');
        $filtered = $request->routeIs('products.index', 'catalog.category')
            && collect(['search', 'brands', 'price_min', 'price_max', 'in_stock', 'sort'])->contains(fn ($key) => $request->filled($key));
        if ($private || $filtered) {
            $response->headers->set('X-Robots-Tag', $private ? 'noindex, nofollow' : 'noindex, follow');
        }
        return $response;
    }
}
