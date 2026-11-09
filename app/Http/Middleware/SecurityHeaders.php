<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Route file lampiran/NC yang di-preview via <iframe> di dalam aplikasi.
     * SAMEORIGIN tetap memblokir framing dari origin lain (clickjacking).
     */
    private const FRAMEABLE_FILE_ROUTES = [
        'laporan-lampiran.file',
        'laporan-nc-foto.file',
        'laporan-nc-form.file',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set(
            'X-Frame-Options',
            $request->routeIs(...self::FRAMEABLE_FILE_ROUTES) ? 'SAMEORIGIN' : 'DENY'
        );
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if (app()->environment('production')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
