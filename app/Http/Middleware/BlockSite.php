<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockSite {
    /**
     * Handle an incoming request.
     */
    public function handle( Request $request, Closure $next ): Response {
        // URL کامل درخواست را دریافت می‌کند
        $host = $request->getHost();

        // دامنه مورد نظر برای مسدودسازی
        $targetDomain = 'codal.borzan.ir';

        // اگر دامنه برابر بود، خطای 404 بده
        if ( $host === $targetDomain ) {
            abort(404);
        }

        return $next($request);
    }
}