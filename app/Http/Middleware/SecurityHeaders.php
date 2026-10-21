<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Integrations;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=(), usb=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->remove('X-Powered-By');

        if (config('security.hsts') && $request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        if (config('security.csp') && ! $headers->has('Content-Security-Policy') && ! app()->environment('local')) {
            $headers->set('Content-Security-Policy', $this->csp());
        }

        return $response;
    }

    private function csp(): string
    {
        $nonce = Vite::cspNonce();
        $storage = $this->origin((string) config('filesystems.disks.supabase.url'));
        // Google Analytics and AdSense load further scripts, frames and beacons from many Google hosts.
        // 'strict-dynamic' lets the nonced loaders do that while inline scripts without a nonce stay blocked.
        $google = Integrations::ga4() !== null || Integrations::adsenseClient() !== null;

        return implode('; ', array_filter([
            "default-src 'self'",
            // Alpine.js evaluates directive expressions, which requires 'unsafe-eval'.
            "script-src 'self' 'nonce-{$nonce}' 'strict-dynamic' 'unsafe-eval' https:",
            "style-src 'self' 'unsafe-inline'",
            // Map tiles (CARTO/OpenStreetMap), media and ad creatives.
            "img-src 'self' data: blob: https:",
            "font-src 'self' data:",
            // Direct-to-storage spreadsheet uploads go to the Supabase Storage origin.
            "connect-src 'self' ".$storage.($google ? ' https:' : ''),
            'frame-src https://www.openstreetmap.org'.($google ? ' https:' : ''),
            "frame-ancestors 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            'upgrade-insecure-requests',
        ]));
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);

        return isset($parts['scheme'], $parts['host']) ? $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '') : '';
    }
}
