<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyLmsIntegrationToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = (string) config('services.lms.token');
        $providedToken = (string) $request->bearerToken();

        if ($expectedToken === '' || $providedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            return response()->json([
                'message' => 'Unauthorized integration request.',
            ], 401);
        }

        return $next($request);
    }
}
