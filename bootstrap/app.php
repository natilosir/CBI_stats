<?php

use App\Http\Middleware\BlockSite;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath : dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
    )
    ->withMiddleware(function ( Middleware $middleware ): void {
        $middleware->web([
        ]);
        $middleware->validateCsrfTokens(except: [
            '*'
        ]);
    })
    ->withExceptions(function ( Exceptions $exceptions ): void {
        //
    })
    ->create();
