<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Current storefront and staff UI are Russian-only. */
class UseRussianLocale
{
    public function handle(Request $request, Closure $next)
    {
        app()->setLocale('ru');
        return $next($request);
    }
}
