<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyHashedKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/generateOTP') || $request->is('api/verifyOTP')) {
            return $next($request);
        }

        $providedHashedKey = $request->input('hash');

        // get backend's shared key and hash it
        $sharedKey = env('API_HANDSHAKE_KEY');
        $expectedHashedKey = hash('sha256', (string)$sharedKey);

        // Guard against missing/invalid provided hash to avoid TypeError
        if (!is_string($providedHashedKey) || $providedHashedKey === '') {
            return response()->json(['message' => 'keys are not the same'], 403);
        }

        if (!hash_equals($expectedHashedKey, $providedHashedKey)) {
            return response()->json([
                'error' => 'Invalid request key',
            ], 403);
        }

        return $next($request);
    }
}
