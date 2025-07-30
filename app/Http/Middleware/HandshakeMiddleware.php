<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Middleware\Cache;

class HandshakeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        \Log::debug('HEADERS RECEIVED:', $request->headers->all());
        \Log::info('[HANDSHAKE] Middleware entered.');
        \Log::debug('[HANDSHAKE] All headers:', $request->headers->all());

        $signature = $request->header('x-request-signature');
        $nonce = $request->header('x-request-payload');
        $timestamp = $request->header('x-request-timestamp');

        if (!$signature || !$nonce || !$timestamp) {
            \Log::warning('[HANDSHAKE] Missing headers', [
                'signature' => $signature,
                'nonce' => $nonce,
                'timestamp' => $timestamp,
            ]);
            abort(403, "Missing signature, nonce, or timestamp");
        } else \Log::warning('sig, nonce, and timestamp exist:', [
            'signature' => $signature,
            'nonce' => $nonce,
            'timestamp' => $timestamp,
        ]);

        // validate timestamp 
        if (abs(time() * 1000 - (int)$timestamp) > 30000) {
            abort(403, "Request expired");
        } 
        
        // check if nonce is valid and unused
        if (Cache::pull("handshake_nonce:$nonce") == false) {
            abort(403, "Invalid or reused nonce");
        }

        \Log::info('HANDSHAKE middleware triggered', [
            'method' => $request->method(),
            'signature' => $signature,
            'nonce' => $nonce,
            'timestamp' => $timestamp,
        ]);

        $method = $request->method();

        if ($method == "GET") { 
            $payload = json_encode([
                "request" => $request->getPathInfo(), 
                "referer" => "front-end-website",
                "timestamp" => (int)$timestamp,
                "nonce" => $nonce,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
        }
        else $payload = json_encode(array_merge($request->all(), [
            "timestamp" => (int)$timestamp,
            "nonce" => $nonce
        ]));
    
        $key = config("req_verification.secret_key");
        $expectedSignature = hash_hmac('sha256', $payload, $key);
        
        \Log::info('Computed payload for signature:', ['payload' => $payload]);
        \Log::info('Expected signature:', ['signature' => $expectedSignature]);
        \Log::info('Received signature:', ['signature' => $signature]);

        if (!hash_equals($expectedSignature, $signature)) {
            abort(403, "Unauthorized");
        }
        
        return $next($request);
    }
}
