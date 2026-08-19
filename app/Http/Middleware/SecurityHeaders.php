<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CSP, HSTS and the rest.
 *
 * The CSP is built from the settings table rather than hardcoded, because the
 * analytics IDs are configurable and the policy has to widen to match whatever
 * the administrator turned on — without ever being widened by hand to `*`.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=(), usb=()'
        );

        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        $response->headers->set('Content-Security-Policy', $this->policy());

        return $response;
    }

    private function policy(): string
    {
        // Analytics hosts are only admitted when the corresponding setting is
        // populated AND the visitor has consented. The consent gate lives in the
        // Vue layer; this is the second lock.
        $script = ["'self'", "'unsafe-inline'"];
        $connect = ["'self'"];
        $img = ["'self'", 'data:', 'blob:'];

        if (setting('analytics.ga4_measurement_id') || setting('analytics.gtm_container_id')) {
            $script[] = 'https://www.googletagmanager.com';
            $script[] = 'https://www.google-analytics.com';
            $connect[] = 'https://www.google-analytics.com';
            $connect[] = 'https://region1.google-analytics.com';
            $img[] = 'https://www.google-analytics.com';
        }

        if (app()->environment('local')) {
            // Vite's dev server and its HMR websocket.
            $script[] = 'http://localhost:5173';
            $connect[] = 'http://localhost:5173';
            $connect[] = 'ws://localhost:5173';
        }

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $script),
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            'img-src '.implode(' ', $img),
            'connect-src '.implode(' ', $connect),
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]);
    }
}
