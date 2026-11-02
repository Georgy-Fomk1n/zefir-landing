<?php

use App\Http\Middleware\PageCache;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Публичные страницы без сессии и cookie: поэтому HTML кэшируется целиком
            Route::middleware('public')->group(base_path('routes/public.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->group('public', [PageCache::class]);
        $middleware->alias(['page.cache' => PageCache::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
