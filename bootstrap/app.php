<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [\App\Http\Middleware\UseRussianLocale::class, \App\Http\Middleware\SearchIndexing::class]);
        $middleware->replace(\Illuminate\Http\Middleware\TrustProxies::class,
            \App\Http\Middleware\TrustProxies::class);
        $middleware->trustHosts(at: fn () => [
            '^'.preg_quote(parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost', '/').'$',
        ], subdomains: false);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
