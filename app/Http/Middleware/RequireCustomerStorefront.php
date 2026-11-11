<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireCustomerStorefront
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(config('commerce.enabled') && config('storefront.inertia'), 404);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }
}
