<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds baseline security response headers and, when DEPLOYER_FORCE_HTTPS is
 * enabled, redirects insecure requests to HTTPS. This dashboard controls
 * deploys, so it should never be served over plain HTTP in production.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $forceHttps = (bool) config('deployer.force_https');

        if ($forceHttps && ! $request->secure() && ! app()->environment('local')) {
            return redirect()->secure($request->getRequestUri());
        }

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        if ($request->secure() || $forceHttps) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
