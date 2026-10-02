<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireWorkerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = (string) config('unlucky13.worker_token', '');

        if ($expectedToken === '') {
            return new JsonResponse([
                'message' => 'Worker API token is not configured.',
            ], 503);
        }

        $providedToken = $request->bearerToken();

        if (! is_string($providedToken) || ! hash_equals($expectedToken, $providedToken)) {
            return new JsonResponse([
                'message' => 'Invalid worker API token.',
            ], 401);
        }

        return $next($request);
    }
}
