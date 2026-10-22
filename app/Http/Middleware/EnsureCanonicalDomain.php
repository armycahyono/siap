<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanonicalDomain
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $forwardedHost = strtolower($request->header('x-forwarded-host', ''));

        if (
            in_array($host, ['kurikulum.sudahonline.my.id', 'kurikulum.test', 'www.kurikulum.sudahonline.my.id']) ||
            in_array($forwardedHost, ['kurikulum.sudahonline.my.id', 'kurikulum.test', 'www.kurikulum.sudahonline.my.id'])
        ) {
            $targetScheme = $request->secure() ? 'https' : 'http';
            $targetHost = ($host === 'kurikulum.test' || $forwardedHost === 'kurikulum.test') ? 'siap.test' : 'siap.sudahonline.my.id';

            // Preserve full URI and query string
            $url = $targetScheme.'://'.$targetHost.$request->getRequestUri();

            return redirect()->away($url, 301);
        }

        return $next($request);
    }
}
