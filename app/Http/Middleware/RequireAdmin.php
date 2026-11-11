<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()) return redirect()->guest(route('admin.login'));
        abort_unless($request->user()->canAccessAdmin(), 403);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        return $response;
    }
}
