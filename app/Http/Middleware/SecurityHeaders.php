<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add security headers to all responses.
 *
 * Covers: OWASP Secure Headers Project recommendations,
 *         ASVS V14.4 HTTP Security Headers.
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Generate CSP nonce before response is rendered so Blade/Vite can use it
        $nonce = app('csp-nonce');
        Vite::useCspNonce($nonce);

        $response = $next($request);

        // ── Framing / Embedding ─────────────────────────────────
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME-type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Control referrer information leakage
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Restrict browser features
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // XSS protection (legacy browsers)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // ── Cross-Origin Isolation ──────────────────────────────
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        // ── Transport Security ──────────────────────────────────
        if ($request->secure() || config('app.env') === 'production') {
            // includeSubDomains and preload are opt-in (L10): once a domain is
            // on the browsers' preload list, every subdomain must serve HTTPS
            // for years, and removal is slow.
            $hsts = 'max-age=31536000';
            if (config('mybooks.hsts.include_subdomains')) {
                $hsts .= '; includeSubDomains';
                if (config('mybooks.hsts.preload')) {
                    $hsts .= '; preload';
                }
            }
            $response->headers->set('Strict-Transport-Security', $hsts);
        }

        // ── Cache Control (prevent caching of authenticated responses) ──
        if ($request->user()) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        // ── Fingerprint removal ─────────────────────────────────
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('server');

        // Content Security Policy
        $csp = $this->buildCsp($nonce);
        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }

    /**
     * Build the Content-Security-Policy header value.
     *
     * Uses nonce-based strict CSP with 'strict-dynamic':
     * - Scripts must bear the per-request nonce or be loaded by a nonced script.
     * - 'unsafe-inline' is a fallback for CSP Level 1 browsers (ignored by Level 2+).
     * - 'unsafe-eval' remains required for Alpine.js expression evaluation.
     */
    protected function buildCsp(string $nonce): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'nonce-{$nonce}' 'strict-dynamic' 'unsafe-eval' 'unsafe-inline' https:",
            "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com",
            "font-src 'self' data:", // IBM Plex Sans is bundled (rebrand R1/R11)
            "img-src 'self' data: blob: https://*.tawk.to",
            "connect-src 'self' https://*.tawk.to wss://*.tawk.to",
            "frame-src 'self' https://*.tawk.to",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ];

        return implode('; ', $directives);
    }
}
